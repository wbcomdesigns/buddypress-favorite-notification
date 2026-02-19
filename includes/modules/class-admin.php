<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- Legacy file name.
/**
 * Clean Admin Module for BuddyPress Favorite Notification.
 *
 * @package BuddyPress_Favorite_Notification
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clean Admin Module Class.
 */
// phpcs:ignore Squiz.Commenting.ClassComment.Missing -- Class docblock is above.
class BPFN_Module_Admin {

	/**
	 * Admin notices.
	 *
	 * @var array
	 */
	private $notices = array();

	/**
	 * Admin page hooks.
	 *
	 * @var array
	 */
	private $admin_hooks = array();

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
		// Admin menu.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// Admin notices.
		add_action( 'admin_notices', array( $this, 'display_admin_notices' ) );

		// Plugin action links.
		add_filter( 'plugin_action_links_' . BPFN_PLUGIN_BASENAME, array( $this, 'add_action_links' ) );

		// Admin init.
		add_action( 'admin_init', array( $this, 'admin_init' ) );

		// Admin assets.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Admin notices.
		add_action( 'admin_notices', array( $this, 'migration_notice' ) );

		// Automatic cleanup.
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
			'get_stats',
			'migrate_favorites',
			'migration_progress',
		);

		foreach ( $ajax_actions as $action ) {
			add_action( 'wp_ajax_bpfn_' . $action, array( $this, 'ajax_' . $action ) );
		}
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		// Single main menu page with tabs.
		$hook = add_menu_page(
			esc_html__( 'BP Favorite Notification', 'buddypress-favorite-notification' ),
			esc_html__( 'BP Favorites', 'buddypress-favorite-notification' ),
			'manage_options',
			'bpfn-dashboard',
			array( $this, 'admin_page' ),
			'dashicons-heart',
			30
		);

		$this->admin_hooks[] = $hook;
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook The admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		// Check if we're on one of our admin pages.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page slug check only.
		if ( ! in_array( $hook, $this->admin_hooks, true ) &&
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page slug check only.
			! ( isset( $_GET['page'] ) && false !== strpos( sanitize_text_field( wp_unslash( $_GET['page'] ) ), 'bpfn' ) ) ) {
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

		// Localize script.
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
	 * Main admin page (single page with sections).
	 */
	public function admin_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'BuddyPress Favorite Notification', 'buddypress-favorite-notification' ); ?></h1>

			<?php
			// Show success message if settings were saved.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only check.
			if ( isset( $_GET['settings_updated'] ) && 'true' === sanitize_text_field( wp_unslash( $_GET['settings_updated'] ) ) ) :
				?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings saved successfully!', 'buddypress-favorite-notification' ); ?></p>
				</div>
				<?php
			endif;
			?>

			<!-- All sections on one page -->
			<div class="bpfn-admin-section">
				<?php $this->render_dashboard_section(); ?>
			</div>

			<div class="bpfn-admin-section">
				<?php $this->render_settings_section(); ?>
			</div>

			<div class="bpfn-admin-section">
				<?php $this->render_tools_section(); ?>
			</div>

		</div>
		<?php
	}

	/**
	 * Render dashboard section.
	 */
	private function render_dashboard_section() {
		?>
		<h2 class="title"><?php esc_html_e( 'Overview', 'buddypress-favorite-notification' ); ?></h2>

		<?php
		// Get stats for last 7 days.
		$stats = $this->get_dashboard_stats();
		?>

		<!-- Stats Cards -->
		<div class="bpfn-stat-cards">
			<!-- Total Favorites -->
			<div class="postbox">
				<div class="inside">
					<div class="bpfn-stat-content">
						<h3><?php esc_html_e( 'Total Favorites', 'buddypress-favorite-notification' ); ?></h3>
						<div class="bpfn-stat-number"><?php echo esc_html( number_format_i18n( $stats['total_favorites'] ) ); ?></div>
						<p class="bpfn-stat-label"><?php esc_html_e( 'All time', 'buddypress-favorite-notification' ); ?></p>
						<?php if ( $stats['favorites_last_7_days'] > 0 ) : ?>
							<p class="bpfn-stat-trend positive">
								<?php
								/* translators: %s: Number of new favorites. */
								echo '&#8593; ' . esc_html( sprintf( __( '+%s in last 7 days', 'buddypress-favorite-notification' ), number_format_i18n( $stats['favorites_last_7_days'] ) ) );
								?>
							</p>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<!-- Total Notifications -->
			<div class="postbox">
				<div class="inside">
					<div class="bpfn-stat-content">
						<h3><?php esc_html_e( 'Notifications Sent', 'buddypress-favorite-notification' ); ?></h3>
						<div class="bpfn-stat-number"><?php echo esc_html( number_format_i18n( $stats['total_notifications'] ) ); ?></div>
						<p class="bpfn-stat-label"><?php esc_html_e( 'All time', 'buddypress-favorite-notification' ); ?></p>
						<?php if ( $stats['notifications_last_7_days'] > 0 ) : ?>
							<p class="bpfn-stat-trend positive">
								<?php
								/* translators: %s: Number of new notifications. */
								echo '&#8593; ' . esc_html( sprintf( __( '+%s in last 7 days', 'buddypress-favorite-notification' ), number_format_i18n( $stats['notifications_last_7_days'] ) ) );
								?>
							</p>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<!-- Active Users -->
			<div class="postbox">
				<div class="inside">
					<div class="bpfn-stat-content">
						<h3><?php esc_html_e( 'Active Users', 'buddypress-favorite-notification' ); ?></h3>
						<div class="bpfn-stat-number"><?php echo esc_html( number_format_i18n( $stats['active_users_7_days'] ) ); ?></div>
						<p class="bpfn-stat-label"><?php esc_html_e( 'Last 7 days', 'buddypress-favorite-notification' ); ?></p>
					</div>
				</div>
			</div>

			<!-- Most Liked Activity -->
			<div class="postbox">
				<div class="inside">
					<div class="bpfn-stat-content">
						<h3><?php esc_html_e( 'Most Liked Activity', 'buddypress-favorite-notification' ); ?></h3>
						<div class="bpfn-stat-number"><?php echo esc_html( number_format_i18n( $stats['most_liked_count'] ) ); ?></div>
						<p class="bpfn-stat-label">
							<?php
							if ( $stats['most_liked_activity'] ) {
								printf(
									/* translators: %d: Activity ID. */
									esc_html__( 'Activity #%d', 'buddypress-favorite-notification' ),
									(int) $stats['most_liked_activity']
								);
							} else {
								esc_html_e( 'No data yet', 'buddypress-favorite-notification' );
							}
							?>
						</p>
					</div>
				</div>
			</div>
		</div>

		<!-- Recent Activity -->
		<div class="postbox">
			<h3 class="title"><?php esc_html_e( 'Recent Favorites (Last 7 Days)', 'buddypress-favorite-notification' ); ?></h3>
			<div class="inside">
				<?php $this->render_recent_activity( $stats ); ?>
			</div>
		</div>

		<!-- Trending Activities -->
		<div class="bpfn-trending-wrapper">
			<div class="postbox">
				<h3 class="title"><?php esc_html_e( 'Trending Activities (Last 7 Days)', 'buddypress-favorite-notification' ); ?></h3>
				<div class="inside">
					<?php $this->render_trending_activities( $stats, 7 ); ?>
				</div>
			</div>

			<div class="postbox">
				<h3 class="title"><?php esc_html_e( 'Trending Activities (Last 30 Days)', 'buddypress-favorite-notification' ); ?></h3>
				<div class="inside">
					<?php $this->render_trending_activities( $stats, 30 ); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get dashboard statistics.
	 *
	 * @return array Dashboard stats.
	 */
	private function get_dashboard_stats() {
		global $wpdb, $bp;

		$stats = array(
			'total_favorites'           => 0,
			'favorites_last_7_days'     => 0,
			'total_notifications'       => 0,
			'notifications_last_7_days' => 0,
			'active_users_7_days'       => 0,
			'most_liked_activity'       => 0,
			'most_liked_count'          => 0,
			'recent_activities'         => array(),
			'trending_7_days'           => array(),
			'trending_30_days'          => array(),
		);

		// Get total favorites from our table.
		$favorites_table = $wpdb->prefix . 'bp_activity_favorites';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin stats query.
		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $favorites_table )
		);

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Admin stats with custom table.
		if ( $table_exists === $favorites_table ) {
			$stats['total_favorites'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$favorites_table}" );

			// Favorites in last 7 days.
			$stats['favorites_last_7_days'] = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$favorites_table}
				WHERE favorited_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
			);

			// Active users (who liked something in last 7 days).
			$stats['active_users_7_days'] = (int) $wpdb->get_var(
				"SELECT COUNT(DISTINCT user_id) FROM {$favorites_table}
				WHERE favorited_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
			);

			// Most liked activity.
			$most_liked = $wpdb->get_row(
				"SELECT activity_id, COUNT(*) as count
				FROM {$favorites_table}
				GROUP BY activity_id
				ORDER BY count DESC
				LIMIT 1"
			);

			if ( $most_liked ) {
				$stats['most_liked_activity'] = (int) $most_liked->activity_id;
				$stats['most_liked_count']    = (int) $most_liked->count;
			}

			// Recent favorited activities (last 10).
			$stats['recent_activities'] = $wpdb->get_results(
				"SELECT activity_id, user_id, favorited_at
				FROM {$favorites_table}
				WHERE favorited_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
				ORDER BY favorited_at DESC
				LIMIT 10"
			);

			// Trending activities in last 7 days (most favorites).
			$stats['trending_7_days'] = $wpdb->get_results(
				"SELECT activity_id, COUNT(*) as favorite_count
				FROM {$favorites_table}
				WHERE favorited_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
				GROUP BY activity_id
				ORDER BY favorite_count DESC
				LIMIT 10"
			);

			// Trending activities in last 30 days (most favorites).
			$stats['trending_30_days'] = $wpdb->get_results(
				"SELECT activity_id, COUNT(*) as favorite_count
				FROM {$favorites_table}
				WHERE favorited_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
				GROUP BY activity_id
				ORDER BY favorite_count DESC
				LIMIT 10"
			);
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Get notification stats.
		if ( bp_is_active( 'notifications' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from BP.
			$notifications_table = $bp->notifications->table_name;
			$component           = isset( $bp->favorite_notifier ) ? $bp->favorite_notifier->id : 'favorite_notifier';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin stats query.
			$stats['total_notifications'] = (int) $wpdb->get_var(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from BP.
					"SELECT COUNT(*) FROM {$notifications_table} WHERE component_name = %s",
					$component
				)
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin stats query.
			$stats['notifications_last_7_days'] = (int) $wpdb->get_var(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from BP.
					"SELECT COUNT(*) FROM {$notifications_table}
				WHERE component_name = %s
				AND date_notified >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
					$component
				)
			);
		}

		return $stats;
	}

	/**
	 * Render recent activity.
	 *
	 * @param array $stats Dashboard stats.
	 */
	private function render_recent_activity( $stats ) {
		if ( empty( $stats['recent_activities'] ) ) {
			echo '<p>' . esc_html__( 'No favorites in the last 7 days.', 'buddypress-favorite-notification' ) . '</p>';
			return;
		}

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead>';
		echo '<tr>';
		echo '<th>' . esc_html__( 'User', 'buddypress-favorite-notification' ) . '</th>';
		echo '<th>' . esc_html__( 'Activity', 'buddypress-favorite-notification' ) . '</th>';
		echo '<th>' . esc_html__( 'Date', 'buddypress-favorite-notification' ) . '</th>';
		echo '</tr>';
		echo '</thead>';
		echo '<tbody>';

		foreach ( $stats['recent_activities'] as $activity ) {
			$user      = get_userdata( $activity->user_id );
			$user_name = $user ? $user->display_name : esc_html__( 'Unknown User', 'buddypress-favorite-notification' );

			// Get activity link.
			$activity_link = bp_activity_get_permalink( $activity->activity_id );

			echo '<tr>';
			echo '<td>' . esc_html( $user_name ) . '</td>';
			echo '<td><a href="' . esc_url( $activity_link ) . '" target="_blank">';
			printf(
				/* translators: %d: Activity ID. */
				esc_html__( 'Activity #%d', 'buddypress-favorite-notification' ),
				(int) $activity->activity_id
			);
			echo '</a></td>';
			// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Used for display only.
			echo '<td>' . esc_html( human_time_diff( strtotime( $activity->favorited_at ), current_time( 'timestamp' ) ) ) . ' ' . esc_html__( 'ago', 'buddypress-favorite-notification' ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody>';
		echo '</table>';
	}

	/**
	 * Render trending activities.
	 *
	 * @param array $stats Dashboard stats.
	 * @param int   $days  Number of days to show.
	 */
	private function render_trending_activities( $stats, $days = 7 ) {
		$key = 'trending_' . $days . '_days';

		if ( empty( $stats[ $key ] ) ) {
			printf(
				'<p>%s</p>',
				sprintf(
					/* translators: %d: Number of days. */
					esc_html__( 'No trending activities in the last %d days.', 'buddypress-favorite-notification' ),
					(int) $days
				)
			);
			return;
		}

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead>';
		echo '<tr>';
		echo '<th>' . esc_html__( 'Rank', 'buddypress-favorite-notification' ) . '</th>';
		echo '<th>' . esc_html__( 'Activity', 'buddypress-favorite-notification' ) . '</th>';
		echo '<th>' . esc_html__( 'Favorites', 'buddypress-favorite-notification' ) . '</th>';
		echo '<th>' . esc_html__( 'Author', 'buddypress-favorite-notification' ) . '</th>';
		echo '<th>' . esc_html__( 'Preview', 'buddypress-favorite-notification' ) . '</th>';
		echo '</tr>';
		echo '</thead>';
		echo '<tbody>';

		$rank = 1;
		foreach ( $stats[ $key ] as $trending ) {
			// Get activity details.
			$activity = bp_activity_get_specific( array( 'activity_ids' => $trending->activity_id ) );

			if ( ! empty( $activity['activities'][0] ) ) {
				$activity_obj = $activity['activities'][0];
				$author       = get_userdata( $activity_obj->user_id );
				$author_name  = $author ? $author->display_name : esc_html__( 'Unknown', 'buddypress-favorite-notification' );

				// Get activity content preview (first 100 chars).
				$content = wp_strip_all_tags( $activity_obj->content );
				$preview = mb_strlen( $content ) > 100 ? mb_substr( $content, 0, 100 ) . '...' : $content;

				// Activity link.
				$activity_link = bp_activity_get_permalink( $trending->activity_id );
			} else {
				$author_name   = esc_html__( 'Unknown', 'buddypress-favorite-notification' );
				$preview       = esc_html__( 'Activity not found', 'buddypress-favorite-notification' );
				$activity_link = '#';
			}

			echo '<tr>';
			echo '<td><strong>#' . esc_html( $rank ) . '</strong></td>';
			echo '<td><a href="' . esc_url( $activity_link ) . '" target="_blank">';
			printf(
				/* translators: %d: Activity ID. */
				esc_html__( 'Activity #%d', 'buddypress-favorite-notification' ),
				(int) $trending->activity_id
			);
			echo '</a></td>';
			echo '<td><span class="bpfn-badge">' . esc_html( number_format_i18n( $trending->favorite_count ) ) . '</span></td>';
			echo '<td>' . esc_html( $author_name ) . '</td>';
			echo '<td class="bpfn-preview">' . esc_html( $preview ) . '</td>';
			echo '</tr>';

			++$rank;
		}

		echo '</tbody>';
		echo '</table>';
	}

	/**
	 * Render settings section.
	 */
	private function render_settings_section() {
		?>
		<h2 class="title"><?php esc_html_e( 'Settings', 'buddypress-favorite-notification' ); ?></h2>

		<div class="postbox">
			<h3 class="title"><?php esc_html_e( 'Notification Settings', 'buddypress-favorite-notification' ); ?></h3>
			<div class="inside">
				<form method="post" action="options.php">
					<?php
					settings_fields( 'bpfn_settings' );
					do_settings_sections( 'bpfn-settings' );
					submit_button();
					?>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Render tools section.
	 */
	private function render_tools_section() {
		?>
		<h2 class="title"><?php esc_html_e( 'Tools & Maintenance', 'buddypress-favorite-notification' ); ?></h2>

		<!-- Migrate Favorites -->
				<?php
				require_once BPFN_INCLUDES_PATH . 'migrations/class-favorites-migration.php';
				$migration = new BPFN_Favorites_Migration();
				$stats     = $migration->get_migration_stats();
				?>
				<div class="postbox">
					<h3 class="title"><?php esc_html_e( 'Migrate Favorites', 'buddypress-favorite-notification' ); ?></h3>
					<div class="inside">
						<?php if ( $stats['migrated'] ) : ?>
							<div class="notice notice-success inline">
								<p><strong><?php esc_html_e( 'Migration completed!', 'buddypress-favorite-notification' ); ?></strong></p>
							</div>
							<?php
							$log = $migration->get_migration_log();
							if ( ! empty( $log ) ) :
								?>
								<p>
									<?php
									printf(
										/* translators: 1: Number of users, 2: Number of favorites. */
										esc_html__( 'Processed %1$d users and migrated %2$d favorites.', 'buddypress-favorite-notification' ),
										isset( $log['users_processed'] ) ? (int) $log['users_processed'] : 0,
										isset( $log['favorites_added'] ) ? (int) $log['favorites_added'] : 0
									);
									?>
								</p>
								<?php if ( isset( $log['start_time'] ) ) : ?>
									<p>
										<small>
											<?php
											printf(
												/* translators: %s: Migration date/time. */
												esc_html__( 'Completed on: %s', 'buddypress-favorite-notification' ),
												esc_html( $log['start_time'] )
											);
											?>
										</small>
									</p>
								<?php endif; ?>
							<?php endif; ?>
						<?php elseif ( $stats['migration_pending'] ) : ?>
							<p>
								<?php
								printf(
									/* translators: 1: Number of users, 2: Number of favorites. */
									esc_html__( 'Found %1$d users with %2$d favorites to migrate.', 'buddypress-favorite-notification' ),
									(int) $stats['users_with_favorites'],
									(int) $stats['meta_favorites_count']
								);
								?>
							</p>
							<p><?php esc_html_e( 'Click the button below to migrate existing favorites to the new optimized table.', 'buddypress-favorite-notification' ); ?></p>
							<button class="button button-primary" id="bpfn-migrate-favorites">
								<?php esc_html_e( 'Run Migration', 'buddypress-favorite-notification' ); ?>
							</button>
							<div id="bpfn-migrate-result" style="margin-top: 10px;"></div>
						<?php else : ?>
							<p><?php esc_html_e( 'No favorites found to migrate.', 'buddypress-favorite-notification' ); ?></p>
						<?php endif; ?>
					</div>
				</div>

				<!-- Clear Old Notifications -->
				<div class="postbox">
					<h3 class="title"><?php esc_html_e( 'Database Maintenance', 'buddypress-favorite-notification' ); ?></h3>
					<div class="inside">
						<p><?php esc_html_e( 'Remove read notifications older than a specified period to keep your database clean.', 'buddypress-favorite-notification' ); ?></p>

						<!-- Automatic Cleanup Settings -->
						<form method="post" action="">
							<?php wp_nonce_field( 'bpfn_cleanup_settings', 'bpfn_cleanup_nonce' ); ?>

							<table class="form-table">
								<tr>
									<th scope="row">
										<label for="bpfn_auto_cleanup_enabled">
											<?php esc_html_e( 'Automatic Cleanup', 'buddypress-favorite-notification' ); ?>
										</label>
									</th>
									<td>
										<?php $auto_enabled = get_option( 'bpfn_auto_cleanup_enabled', 'yes' ); ?>
										<label>
											<input type="checkbox"
													name="bpfn_auto_cleanup_enabled"
													id="bpfn_auto_cleanup_enabled"
													value="yes"
													<?php checked( $auto_enabled, 'yes' ); ?> />
											<?php esc_html_e( 'Enable automatic monthly cleanup', 'buddypress-favorite-notification' ); ?>
										</label>
										<p class="description">
											<?php esc_html_e( 'Automatically remove old read notifications once per month via WP Cron.', 'buddypress-favorite-notification' ); ?>
										</p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="bpfn_auto_cleanup_days">
											<?php esc_html_e( 'Retention Period', 'buddypress-favorite-notification' ); ?>
										</label>
									</th>
									<td>
										<?php $cleanup_days = get_option( 'bpfn_auto_cleanup_days', 30 ); ?>
										<select name="bpfn_auto_cleanup_days" id="bpfn_auto_cleanup_days">
											<option value="7" <?php selected( $cleanup_days, 7 ); ?>>7 <?php esc_html_e( 'days', 'buddypress-favorite-notification' ); ?></option>
											<option value="15" <?php selected( $cleanup_days, 15 ); ?>>15 <?php esc_html_e( 'days', 'buddypress-favorite-notification' ); ?></option>
											<option value="30" <?php selected( $cleanup_days, 30 ); ?>>30 <?php esc_html_e( 'days', 'buddypress-favorite-notification' ); ?></option>
											<option value="60" <?php selected( $cleanup_days, 60 ); ?>>60 <?php esc_html_e( 'days', 'buddypress-favorite-notification' ); ?></option>
											<option value="90" <?php selected( $cleanup_days, 90 ); ?>>90 <?php esc_html_e( 'days', 'buddypress-favorite-notification' ); ?></option>
										</select>
										<p class="description">
											<?php esc_html_e( 'Only read/dismissed notifications older than this will be deleted.', 'buddypress-favorite-notification' ); ?>
										</p>
									</td>
								</tr>
							</table>

							<p class="submit">
								<button type="submit" name="bpfn_save_cleanup_settings" class="button button-primary">
									<?php esc_html_e( 'Save Settings', 'buddypress-favorite-notification' ); ?>
								</button>
							</p>
						</form>

						<?php
						// Show last cleanup info.
						$last_cleanup = get_option( 'bpfn_last_auto_cleanup', array() );
						if ( ! empty( $last_cleanup ) ) :
							?>
							<div class="notice notice-info inline">
								<p>
									<strong><?php esc_html_e( 'Last automatic cleanup:', 'buddypress-favorite-notification' ); ?></strong><br>
									<?php
									printf(
										/* translators: 1: Date, 2: Number deleted, 3: Number remaining. */
										esc_html__( '%1$s - Deleted %2$d notifications, %3$d remaining', 'buddypress-favorite-notification' ),
										isset( $last_cleanup['date'] ) ? esc_html( $last_cleanup['date'] ) : 'N/A',
										isset( $last_cleanup['deleted'] ) ? (int) $last_cleanup['deleted'] : 0,
										isset( $last_cleanup['remaining'] ) ? (int) $last_cleanup['remaining'] : 0
									);
									?>
								</p>
							</div>
							<?php
						endif;

						// Show next scheduled cleanup.
						$next_cleanup = wp_next_scheduled( 'bpfn_auto_cleanup_notifications' );
						if ( $next_cleanup && 'yes' === $auto_enabled ) :
							?>
							<p>
								<small>
									<?php
									printf(
										/* translators: %s: Next cleanup date/time. */
										esc_html__( 'Next automatic cleanup: %s', 'buddypress-favorite-notification' ),
										esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_cleanup ) )
									);
									?>
								</small>
							</p>
							<?php
						endif;
						?>

						<hr style="margin: 20px 0;">

						<!-- Manual Cleanup -->
						<h3><?php esc_html_e( 'Manual Cleanup', 'buddypress-favorite-notification' ); ?></h3>
						<p><?php esc_html_e( 'Run cleanup immediately without waiting for the automatic schedule.', 'buddypress-favorite-notification' ); ?></p>
						<button class="button" id="bpfn-clear-old-notifications">
							<?php esc_html_e( 'Clear Old Notifications Now', 'buddypress-favorite-notification' ); ?>
						</button>
						<div id="bpfn-clear-result" style="margin-top: 10px;"></div>
					</div>
				</div>
		<?php
	}

	/**
	 * Admin init.
	 */
	public function admin_init() {
		// Register settings.
		register_setting( 'bpfn_settings', 'bpfn_options', array( $this, 'sanitize_options' ) );

		// Add settings sections.
		add_settings_section(
			'bpfn_general',
			esc_html__( 'General Settings', 'buddypress-favorite-notification' ),
			array( $this, 'section_general' ),
			'bpfn-settings'
		);

		// Add settings fields.
		add_settings_field(
			'enable_enhanced_notifications',
			esc_html__( 'Enhanced Notifications', 'buddypress-favorite-notification' ),
			array( $this, 'field_checkbox' ),
			'bpfn-settings',
			'bpfn_general',
			array(
				'name'  => 'enable_enhanced_notifications',
				'label' => esc_html__( 'Enable enhanced notification display', 'buddypress-favorite-notification' ),
			)
		);

		// Handle cleanup settings save.
		$this->handle_cleanup_settings_save();
	}

	/**
	 * Handle cleanup settings save.
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
			// Schedule if enabled and not already scheduled.
			wp_schedule_event( time(), 'monthly', 'bpfn_auto_cleanup_notifications' );
		} elseif ( 'no' === $enabled && $next_scheduled ) {
			// Unschedule if disabled.
			wp_clear_scheduled_hook( 'bpfn_auto_cleanup_notifications' );
		}

		// Redirect with success message.
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'             => 'bpfn-dashboard',
					'settings_updated' => 'true',
				),
				admin_url( 'admin.php' )
			) . '#tools'
		);
		exit;
	}

	/**
	 * General section.
	 */
	public function section_general() {
		echo '<p>' . esc_html__( 'Configure general plugin settings.', 'buddypress-favorite-notification' ) . '</p>';
	}

	/**
	 * Checkbox field.
	 *
	 * @param array $args Field arguments.
	 */
	public function field_checkbox( $args ) {
		$options = get_option( 'bpfn_options', array() );
		$value   = isset( $options[ $args['name'] ] ) ? $options[ $args['name'] ] : 0;
		?>
		<label>
			<input type="checkbox"
					name="bpfn_options[<?php echo esc_attr( $args['name'] ); ?>]"
					value="1"
					<?php checked( $value, 1 ); ?> />
			<?php echo esc_html( $args['label'] ); ?>
		</label>
		<?php
	}

	/**
	 * Sanitize options.
	 *
	 * @param array $input Raw options.
	 * @return array Sanitized options.
	 */
	public function sanitize_options( $input ) {
		$sanitized = array();

		if ( isset( $input['enable_enhanced_notifications'] ) ) {
			$sanitized['enable_enhanced_notifications'] = 1;
		}

		return $sanitized;
	}

	/**
	 * Add plugin action links.
	 *
	 * @param array $links Plugin action links.
	 * @return array Modified links.
	 */
	public function add_action_links( $links ) {
		$action_links = array(
			'settings' => '<a href="' . admin_url( 'admin.php?page=bpfn-dashboard' ) . '">' . esc_html__( 'Settings', 'buddypress-favorite-notification' ) . '</a>',
		);

		return array_merge( $action_links, $links );
	}

	/**
	 * Display admin notices.
	 */
	public function display_admin_notices() {
		foreach ( $this->notices as $notice ) {
			printf(
				'<div class="notice notice-%s"><p>%s</p></div>',
				esc_attr( $notice['type'] ),
				wp_kses_post( $notice['message'] )
			);
		}
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

		$result = bpfn_clear_old_notifications();

		if ( isset( $result['count'] ) ) {
			wp_send_json_success(
				array(
					/* translators: %d: Number of cleared notifications. */
					'message' => sprintf( esc_html__( 'Cleared %d old notifications', 'buddypress-favorite-notification' ), $result['count'] ),
				)
			);
		} else {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to clear notifications', 'buddypress-favorite-notification' ) ) );
		}
	}

	/**
	 * AJAX get stats.
	 */
	public function ajax_get_stats() {
		check_ajax_referer( 'bpfn-admin-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$stats = array();
		if ( function_exists( 'bpfn_get_notification_stats' ) ) {
			$stats = bpfn_get_notification_stats();
		}

		wp_send_json_success( $stats );
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
			// Start background migration.
			$result = $migration->start_migration();
			wp_send_json_success(
				array(
					'message'    => $result['message'],
					'background' => true,
				)
			);
		} else {
			// Run synchronously for small sites.
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
	 * Show migration notice.
	 */
	public function migration_notice() {
		// Only show on admin pages.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check if notice should be shown.
		if ( ! get_option( 'bpfn_show_migration_notice', false ) ) {
			return;
		}

		require_once BPFN_INCLUDES_PATH . 'migrations/class-favorites-migration.php';
		$migration = new BPFN_Favorites_Migration();
		$stats     = $migration->get_migration_stats();

		// Don't show if migration is complete.
		if ( $stats['migrated'] || ! $stats['migration_pending'] ) {
			delete_option( 'bpfn_show_migration_notice' );
			return;
		}

		$tools_url = admin_url( 'admin.php?page=bpfn-dashboard#tools' );
		?>
		<div class="notice notice-info is-dismissible" data-dismissible="bpfn-migration-notice">
			<p>
				<strong><?php esc_html_e( 'BuddyPress Favorite Notification:', 'buddypress-favorite-notification' ); ?></strong>
				<?php
				printf(
					wp_kses(
						/* translators: 1: Number of users, 2: Number of favorites, 3: Tools page link. */
						__( 'Found %1$d users with %2$d favorites that need to be migrated to the new optimized system. <a href="%3$s">Run migration now</a>', 'buddypress-favorite-notification' ),
						array( 'a' => array( 'href' => array() ) )
					),
					(int) $stats['users_with_favorites'],
					(int) $stats['meta_favorites_count'],
					esc_url( $tools_url )
				);
				?>
			</p>
		</div>
		<script>
		jQuery(document).on('click', '[data-dismissible="bpfn-migration-notice"] .notice-dismiss', function() {
			jQuery.post(ajaxurl, {
				action: 'bpfn_dismiss_migration_notice',
				nonce: '<?php echo esc_js( wp_create_nonce( 'bpfn-dismiss-notice' ) ); ?>'
			});
		});
		</script>
		<?php
	}

	/**
	 * Setup automatic cleanup.
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

			// Log result.
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
