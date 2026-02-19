<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- Legacy file name.
/**
 * Clean Assets Module for BuddyPress Favorite Notification.
 *
 * @package BuddyPress_Favorite_Notification
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clean Assets Module Class.
 */
// phpcs:ignore Squiz.Commenting.ClassComment.Missing -- Class docblock is above.
class BPFN_Module_Assets {

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
		// Frontend assets.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

		// Admin assets.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Enqueue frontend assets.
	 */
	public function enqueue_frontend_assets() {
		// Check if we should load assets.
		if ( ! $this->should_load_assets() ) {
			return;
		}

		// Core styles.
		wp_enqueue_style(
			'bpfn-notifications',
			BPFN_ASSETS_URL . 'css/notifications.css',
			array(),
			BPFN_VERSION
		);

		// Core scripts.
		wp_enqueue_script(
			'bpfn-notifications',
			BPFN_ASSETS_URL . 'js/notifications.js',
			array( 'jquery' ),
			BPFN_VERSION,
			true
		);

		// Localize main script.
		wp_localize_script( 'bpfn-notifications', 'BPFN', $this->get_localized_data() );

		// Real-time notifications.
		if ( $this->should_load_realtime() ) {
			$this->enqueue_realtime_assets();
		}

		// Favorite display assets.
		$this->enqueue_favorite_display_assets();
	}

	/**
	 * Enqueue favorite display assets.
	 */
	private function enqueue_favorite_display_assets() {
		// Favorite display styles.
		wp_enqueue_style(
			'bpfn-favorite-display',
			BPFN_ASSETS_URL . 'css/favorite-display.css',
			array( 'bpfn-notifications' ),
			BPFN_VERSION
		);

		// Favorite display scripts.
		wp_enqueue_script(
			'bpfn-favorite-display',
			BPFN_ASSETS_URL . 'js/favorite-display.js',
			array( 'jquery', 'bpfn-notifications' ),
			BPFN_VERSION,
			true
		);

		// Localize favorite display script.
		wp_localize_script(
			'bpfn-favorite-display',
			'bpfnFavorites',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'bpfn-favorite-nonce' ),
			)
		);
	}

	/**
	 * Enqueue real-time assets.
	 */
	private function enqueue_realtime_assets() {
		// Heartbeat API.
		wp_enqueue_script( 'heartbeat' );

		// Real-time styles.
		wp_enqueue_style(
			'bpfn-realtime',
			BPFN_ASSETS_URL . 'css/realtime.css',
			array( 'bpfn-notifications' ),
			BPFN_VERSION
		);

		// Real-time scripts.
		wp_enqueue_script(
			'bpfn-realtime',
			BPFN_ASSETS_URL . 'js/realtime.js',
			array( 'jquery', 'heartbeat', 'bpfn-notifications' ),
			BPFN_VERSION,
			true
		);

		// Get realtime configuration.
		$realtime_module = bpfn()->get_module( 'realtime' );
		$polling_config  = $realtime_module ? $realtime_module->get_polling_config() : $this->get_fallback_realtime_config();

		// Localize real-time script.
		wp_localize_script(
			'bpfn-realtime',
			'BPFNRealtime',
			array(
				'ajax_url'         => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'bpfn_realtime_nonce' ),
				'checkInterval'    => $polling_config['interval'],
				'position'         => $polling_config['position'],
				'maxNotifications' => $polling_config['max_notifications'],
				'autoDismiss'      => $polling_config['auto_dismiss_time'],
				'strings'          => array(
					'new_notification' => esc_html__( 'New notification', 'buddypress-favorite-notification' ),
					'view_activity'    => esc_html__( 'View Activity', 'buddypress-favorite-notification' ),
					'dismiss'          => esc_html__( 'Dismiss', 'buddypress-favorite-notification' ),
				),
			)
		);
	}

	/**
	 * Get fallback realtime configuration.
	 *
	 * @return array Fallback config.
	 */
	private function get_fallback_realtime_config() {
		return array(
			'enabled'           => true,
			'interval'          => 15000,
			'max_notifications' => 5,
			'auto_dismiss_time' => 5000,
			'position'          => 'bottom-right',
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook The admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		// Check if we're on one of our admin pages.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page slug check only.
		if ( ! ( isset( $_GET['page'] ) && false !== strpos( sanitize_text_field( wp_unslash( $_GET['page'] ) ), 'bpfn' ) ) ) {
			return;
		}

		// Admin styles.
		wp_enqueue_style(
			'bpfn-admin',
			BPFN_ASSETS_URL . 'css/admin.css',
			array(),
			BPFN_VERSION
		);

		// Admin scripts.
		wp_enqueue_script(
			'bpfn-admin',
			BPFN_ASSETS_URL . 'js/admin.js',
			array( 'jquery' ),
			BPFN_VERSION,
			true
		);

		// Localization for admin script.
		wp_localize_script(
			'bpfn-admin',
			'bpfnAdmin',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'bpfn-admin-nonce' ),
				'strings'  => array(
					'testing'       => esc_html__( 'Sending test...', 'buddypress-favorite-notification' ),
					'test_success'  => esc_html__( 'Test sent successfully!', 'buddypress-favorite-notification' ),
					'test_error'    => esc_html__( 'Test failed.', 'buddypress-favorite-notification' ),
					'confirm_clear' => esc_html__( 'Are you sure?', 'buddypress-favorite-notification' ),
					'clearing'      => esc_html__( 'Clearing...', 'buddypress-favorite-notification' ),
				),
			)
		);
	}

	/**
	 * Check if assets should be loaded.
	 *
	 * @return bool Whether to load assets.
	 */
	private function should_load_assets() {
		// Don't load if user is not logged in.
		if ( ! is_user_logged_in() ) {
			return false;
		}

		// Don't load if notifications component is not active.
		if ( ! bp_is_active( 'notifications' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if real-time assets should be loaded.
	 *
	 * @return bool Whether to load realtime assets.
	 */
	private function should_load_realtime() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}

		return true;
	}

	/**
	 * Get localized data for scripts.
	 *
	 * @return array Localized data.
	 */
	private function get_localized_data() {
		global $bp;

		return array(
			'ajax_url'     => admin_url( 'admin-ajax.php' ),
			'nonce'        => wp_create_nonce( 'bpfn-nonce' ),
			'user_id'      => get_current_user_id(),
			'component_id' => isset( $bp->favorite_notifier ) ? $bp->favorite_notifier->id : '',
			'strings'      => array(
				'loading'      => esc_html__( 'Loading...', 'buddypress-favorite-notification' ),
				'error'        => esc_html__( 'An error occurred', 'buddypress-favorite-notification' ),
				'favoriting'   => esc_html__( 'Adding to favorites...', 'buddypress-favorite-notification' ),
				'unfavoriting' => esc_html__( 'Removing from favorites...', 'buddypress-favorite-notification' ),
				'favorited'    => esc_html__( 'Added to favorites!', 'buddypress-favorite-notification' ),
				'unfavorited'  => esc_html__( 'Removed from favorites!', 'buddypress-favorite-notification' ),
			),
		);
	}
}
