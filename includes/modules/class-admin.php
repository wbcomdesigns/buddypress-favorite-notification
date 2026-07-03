<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- Legacy file name.
/**
 * Legacy admin service module for BuddyPress Favorite Notification.
 *
 * Post-2.0.0 (card-panel migration) this class NO LONGER owns the admin
 * menu, the admin page UI, the `bpfn_options` Settings API registration,
 * asset enqueue, the migration notice, or the action links — those moved to
 * BPFN_Admin (includes/admin/class-bpfn-admin.php) and its views.
 *
 * What it still owns (all reachable):
 *  - The three admin AJAX handlers (clear/migrate/progress).
 *  - The Tools-tab cleanup settings save handler (nonce bpfn_cleanup_settings).
 *  - The monthly auto-cleanup cron registration + runner.
 *
 * @package BuddyPress_Favorite_Notification
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Legacy admin service module class (AJAX + cleanup save + cron).
 */
// phpcs:ignore Squiz.Commenting.ClassComment.Missing -- Class docblock is above.
class BPFN_Module_Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Setup hooks.
	 */
	private function setup_hooks() {
		// Admin init — only the Tools-tab cleanup save handler now.
		add_action( 'admin_init', array( $this, 'admin_init' ) );

		// Register the custom "monthly" cron interval BEFORE anything tries to
		// schedule on it — wp_schedule_event() silently fails on an unknown
		// recurrence, which left the auto-cleanup cron uncreated.
		add_filter( 'cron_schedules', array( $this, 'register_cron_schedule' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected -- Monthly interval is intentional for retention cleanup.

		// Automatic cleanup cron registration.
		$this->setup_automatic_cleanup();

		// AJAX handlers.
		$this->register_ajax_handlers();
	}

	/**
	 * Register AJAX handlers.
	 */
	private function register_ajax_handlers() {
		$ajax_actions = array(
			'clear_old_notifications',
			'migrate_favorites',
			'migration_progress',
		);

		foreach ( $ajax_actions as $action ) {
			add_action( 'wp_ajax_bpfn_' . $action, array( $this, 'ajax_' . $action ) );
		}
	}

	/**
	 * Admin init — handle the Tools-tab cleanup settings save.
	 */
	public function admin_init() {
		$this->handle_cleanup_settings_save();
	}

	/**
	 * Handle cleanup settings save (Tools tab).
	 */
	private function handle_cleanup_settings_save() {
		if ( ! isset( $_POST['bpfn_save_cleanup_settings'] ) ) {
			return;
		}

		// Verify nonce.
		if ( ! isset( $_POST['bpfn_cleanup_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bpfn_cleanup_nonce'] ) ), 'bpfn_cleanup_settings' ) ) {
			return;
		}

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Save enabled/disabled.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Checkbox presence check only.
		$enabled = isset( $_POST['bpfn_auto_cleanup_enabled'] ) ? 'yes' : 'no';
		update_option( 'bpfn_auto_cleanup_enabled', $enabled );

		// Save retention period.
		$days = isset( $_POST['bpfn_auto_cleanup_days'] ) ? absint( $_POST['bpfn_auto_cleanup_days'] ) : 30;
		if ( $days < 7 ) {
			$days = 7;
		}
		update_option( 'bpfn_auto_cleanup_days', $days );

		// Schedule or unschedule based on enabled status.
		$next_scheduled = wp_next_scheduled( 'bpfn_auto_cleanup_notifications' );

		if ( 'yes' === $enabled && ! $next_scheduled ) {
			wp_schedule_event( time(), 'monthly', 'bpfn_auto_cleanup_notifications' );
		} elseif ( 'no' === $enabled && $next_scheduled ) {
			wp_clear_scheduled_hook( 'bpfn_auto_cleanup_notifications' );
		}

		// Redirect back to the Tools tab with a success flag.
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'             => 'bpfn-dashboard',
					'tab'              => 'tools',
					'settings_updated' => 'true',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * AJAX clear old notifications.
	 */
	public function ajax_clear_old_notifications() {
		check_ajax_referer( 'bpfn-admin-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions', 'buddypress-favorite-notification' ) ) );
		}

		if ( ! function_exists( 'bpfn_clear_old_notifications' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Function not available', 'buddypress-favorite-notification' ) ) );
		}

		// Honour the configured retention period instead of the hard-coded
		// 30-day default, so the manual "Clear Old Notifications Now" action
		// matches the Automatic Cleanup setting shown on the Tools tab.
		$days = absint( get_option( 'bpfn_auto_cleanup_days', 30 ) );
		if ( $days < 7 ) {
			$days = 7;
		}

		$result = bpfn_clear_old_notifications( $days );

		if ( isset( $result['count'] ) ) {
			wp_send_json_success(
				array(
					/* translators: %d: Number of cleared notifications. */
					'message'   => sprintf( esc_html__( 'Cleared %d old notifications', 'buddypress-favorite-notification' ), $result['count'] ),
					'count'     => isset( $result['count'] ) ? (int) $result['count'] : 0,
					'remaining' => isset( $result['remaining'] ) ? (int) $result['remaining'] : 0,
				)
			);
		} else {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to clear notifications', 'buddypress-favorite-notification' ) ) );
		}
	}

	/**
	 * AJAX migrate favorites.
	 */
	public function ajax_migrate_favorites() {
		check_ajax_referer( 'bpfn-admin-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions', 'buddypress-favorite-notification' ) ) );
		}

		require_once BPFN_INCLUDES_PATH . 'migrations/class-favorites-migration.php';
		$migration = new BPFN_Favorites_Migration();

		// Check if we should use background processing.
		$stats          = $migration->get_migration_stats();
		$use_background = $stats['users_with_favorites'] > 100; // Use background for 100+ users.

		if ( $use_background ) {
			$result = $migration->start_migration();
			wp_send_json_success(
				array(
					'message'    => $result['message'],
					'background' => true,
				)
			);
		} else {
			$log = $migration->run_migration();

			if ( isset( $log['message'] ) ) {
				wp_send_json_success(
					array(
						'message'    => $log['message'],
						'log'        => $log,
						'background' => false,
					)
				);
			} else {
				wp_send_json_error( array( 'message' => esc_html__( 'Migration failed', 'buddypress-favorite-notification' ) ) );
			}
		}
	}

	/**
	 * AJAX handler to check migration progress.
	 */
	public function ajax_migration_progress() {
		check_ajax_referer( 'bpfn-admin-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions', 'buddypress-favorite-notification' ) ) );
		}

		require_once BPFN_INCLUDES_PATH . 'migrations/class-favorites-migration.php';
		$migration = new BPFN_Favorites_Migration();

		$progress = $migration->get_migration_progress();

		wp_send_json_success( $progress );
	}

	/**
	 * Register the custom "monthly" cron interval.
	 *
	 * WordPress only ships hourly/twicedaily/daily/weekly, so scheduling the
	 * auto-cleanup event on 'monthly' failed until this interval was added.
	 *
	 * @param array $schedules Existing cron schedules.
	 * @return array Schedules including a 'monthly' interval.
	 */
	public function register_cron_schedule( $schedules ) {
		if ( ! isset( $schedules['monthly'] ) ) {
			$schedules['monthly'] = array(
				'interval' => MONTH_IN_SECONDS,
				'display'  => __( 'Once Monthly', 'buddypress-favorite-notification' ),
			);
		}
		return $schedules;
	}

	/**
	 * Setup automatic cleanup cron.
	 */
	private function setup_automatic_cleanup() {
		// Register WP Cron action.
		add_action( 'bpfn_auto_cleanup_notifications', array( $this, 'run_automatic_cleanup' ) );

		// Schedule if not already scheduled and option is enabled.
		$enabled = get_option( 'bpfn_auto_cleanup_enabled', 'yes' );
		if ( 'yes' === $enabled && ! wp_next_scheduled( 'bpfn_auto_cleanup_notifications' ) ) {
			wp_schedule_event( time(), 'monthly', 'bpfn_auto_cleanup_notifications' );
		}
	}

	/**
	 * Run automatic cleanup.
	 */
	public function run_automatic_cleanup() {
		// Check if enabled.
		$enabled = get_option( 'bpfn_auto_cleanup_enabled', 'yes' );
		if ( 'yes' !== $enabled ) {
			return;
		}

		// Get retention period (default 30 days).
		$days = get_option( 'bpfn_auto_cleanup_days', 30 );
		$days = absint( $days );
		if ( $days < 7 ) {
			$days = 7; // Minimum 7 days.
		}

		// Run cleanup.
		if ( function_exists( 'bpfn_clear_old_notifications' ) ) {
			$result = bpfn_clear_old_notifications( $days );

			update_option(
				'bpfn_last_auto_cleanup',
				array(
					'date'      => current_time( 'mysql' ),
					'deleted'   => isset( $result['count'] ) ? $result['count'] : 0,
					'remaining' => isset( $result['remaining'] ) ? $result['remaining'] : 0,
				)
			);
		}
	}
}
