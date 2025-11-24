<?php
/**
 * Clean Admin Module for BuddyPress Favorite Notification
 *
 * @package BuddyPress_Favorite_Notification
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clean Admin Module Class
 */
class BPFN_Module_Admin {

	/**
	 * Admin notices
	 */
	private $notices = array();

	/**
	 * Admin page hooks
	 */
	private $admin_hooks = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Setup hooks
	 */
	private function setup_hooks() {
		// Admin menu
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		
		// Admin notices
		add_action( 'admin_notices', array( $this, 'display_admin_notices' ) );
		
		// Plugin action links
		add_filter( 'plugin_action_links_' . BPFN_PLUGIN_BASENAME, array( $this, 'add_action_links' ) );
		
		// Admin init
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		
		// Admin assets
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Admin notices
		add_action( 'admin_notices', array( $this, 'migration_notice' ) );

		// Automatic cleanup
		$this->setup_automatic_cleanup();

		// AJAX handlers
		$this->register_ajax_handlers();
	}

	/**
	 * Register AJAX handlers
	 */
	private function register_ajax_handlers() {
		$ajax_actions = array(
			'clear_old_notifications',
			'get_stats',
			'migrate_favorites',
			'migration_progress'
		);

		foreach ( $ajax_actions as $action ) {
			add_action( 'wp_ajax_bpfn_' . $action, array( $this, 'ajax_' . $action ) );
		}
	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		// Single main menu page with tabs
		$hook = add_menu_page(
			__( 'BP Favorite Notification', 'bp-fav-notification' ),
			__( 'BP Favorites', 'bp-fav-notification' ),
			'manage_options',
			'bpfn-dashboard',
			array( $this, 'admin_page' ),
			'dashicons-heart',
			30
		);

		$this->admin_hooks[] = $hook;
	}

	/**
	 * Enqueue admin assets
	 */
	public function enqueue_admin_assets( $hook ) {
		// Check if we're on one of our admin pages
		if ( ! in_array( $hook, $this->admin_hooks ) && 
			 ! ( isset( $_GET['page'] ) && strpos( $_GET['page'], 'bpfn' ) !== false ) ) {
			return;
		}
		
		// Admin styles
		wp_enqueue_style(
			'bpfn-admin',
			BPFN_ASSETS_URL . 'css/admin.css',
			array(),
			BPFN_VERSION
		);
		
		// Admin scripts
		wp_enqueue_script(
			'bpfn-admin',
			BPFN_ASSETS_URL . 'js/admin.js',
			array( 'jquery' ),
			BPFN_VERSION,
			true
		);
		
		// Localize script
		wp_localize_script( 'bpfn-admin', 'bpfnAdmin', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'bpfn-admin-nonce' ),
			'strings' => array(
				'testing' => __( 'Sending test...', 'bp-fav-notification' ),
				'test_success' => __( 'Test sent successfully!', 'bp-fav-notification' ),
				'test_error' => __( 'Test failed.', 'bp-fav-notification' ),
				'confirm_clear' => __( 'Are you sure?', 'bp-fav-notification' ),
				'clearing' => __( 'Clearing...', 'bp-fav-notification' ),
			),
		) );
	}

	/**
	 * Main admin page (single page with sections)
	 */
	public function admin_page() {
		?>
		<div class="wrap">
			<h1><?php _e( 'BuddyPress Favorite Notification', 'bp-fav-notification' ); ?></h1>

			<?php
			// Show success message if settings were saved
			if ( isset( $_GET['settings_updated'] ) && $_GET['settings_updated'] === 'true' ) :
				?>
				<div class="notice notice-success is-dismissible">
					<p><?php _e( 'Settings saved successfully!', 'bp-fav-notification' ); ?></p>
				</div>
				<?php
			endif;
			?>

			<!-- All sections on one page -->
			<?php $this->render_dashboard_section(); ?>
			<?php $this->render_settings_section(); ?>
			<?php $this->render_tools_section(); ?>

		</div>
		<?php
	}

	/**
	 * Render dashboard section
	 */
	private function render_dashboard_section() {
		?>
		<div class="bpfn-dashboard" style="margin-bottom: 40px;">
			<style>
				.bpfn-dashboard { margin-top: 20px; }
				.bpfn-stat-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
				.bpfn-stat-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
				.bpfn-stat-card h3 { margin: 0 0 10px 0; font-size: 14px; color: #646970; text-transform: uppercase; font-weight: 600; }
				.bpfn-stat-card .stat-number { font-size: 32px; font-weight: 600; color: #2271b1; margin: 10px 0; }
				.bpfn-stat-card .stat-label { font-size: 13px; color: #646970; }
				.bpfn-stat-card .stat-trend { font-size: 12px; margin-top: 8px; padding-top: 8px; border-top: 1px solid #f0f0f1; }
				.stat-trend.positive { color: #00a32a; }
				.stat-trend.negative { color: #d63638; }
			</style>

			<h2><?php _e( 'Overview', 'bp-fav-notification' ); ?></h2>

			<?php
			// Get stats for last 7 days
			$stats = $this->get_dashboard_stats();
			?>

			<div class="bpfn-stat-cards">
				<!-- Total Favorites -->
				<div class="bpfn-stat-card">
					<h3><?php _e( 'Total Favorites', 'bp-fav-notification' ); ?></h3>
					<div class="stat-number"><?php echo number_format_i18n( $stats['total_favorites'] ); ?></div>
					<div class="stat-label"><?php _e( 'All time', 'bp-fav-notification' ); ?></div>
					<?php if ( $stats['favorites_last_7_days'] > 0 ) : ?>
						<div class="stat-trend positive">
							↑ <?php printf( __( '+%s in last 7 days', 'bp-fav-notification' ), number_format_i18n( $stats['favorites_last_7_days'] ) ); ?>
						</div>
					<?php endif; ?>
				</div>

				<!-- Total Notifications -->
				<div class="bpfn-stat-card">
					<h3><?php _e( 'Notifications Sent', 'bp-fav-notification' ); ?></h3>
					<div class="stat-number"><?php echo number_format_i18n( $stats['total_notifications'] ); ?></div>
					<div class="stat-label"><?php _e( 'All time', 'bp-fav-notification' ); ?></div>
					<?php if ( $stats['notifications_last_7_days'] > 0 ) : ?>
						<div class="stat-trend positive">
							↑ <?php printf( __( '+%s in last 7 days', 'bp-fav-notification' ), number_format_i18n( $stats['notifications_last_7_days'] ) ); ?>
						</div>
					<?php endif; ?>
				</div>

				<!-- Active Users -->
				<div class="bpfn-stat-card">
					<h3><?php _e( 'Active Users', 'bp-fav-notification' ); ?></h3>
					<div class="stat-number"><?php echo number_format_i18n( $stats['active_users_7_days'] ); ?></div>
					<div class="stat-label"><?php _e( 'Last 7 days', 'bp-fav-notification' ); ?></div>
				</div>

				<!-- Most Liked Activity -->
				<div class="bpfn-stat-card">
					<h3><?php _e( 'Most Liked Activity', 'bp-fav-notification' ); ?></h3>
					<div class="stat-number"><?php echo number_format_i18n( $stats['most_liked_count'] ); ?></div>
					<div class="stat-label">
						<?php
						if ( $stats['most_liked_activity'] ) {
							printf(
								__( 'Activity #%d', 'bp-fav-notification' ),
								$stats['most_liked_activity']
							);
						} else {
							_e( 'No data yet', 'bp-fav-notification' );
						}
						?>
					</div>
				</div>
			</div>

			<!-- Recent Activity -->
			<div class="postbox">
				<h2 class="hndle"><?php _e( 'Recent Activity (Last 7 Days)', 'bp-fav-notification' ); ?></h2>
				<div class="inside">
					<?php $this->render_recent_activity( $stats ); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get dashboard statistics
	 */
	private function get_dashboard_stats() {
		global $wpdb, $bp;

		$stats = array(
			'total_favorites'         => 0,
			'favorites_last_7_days'   => 0,
			'total_notifications'     => 0,
			'notifications_last_7_days' => 0,
			'active_users_7_days'     => 0,
			'most_liked_activity'     => 0,
			'most_liked_count'        => 0,
			'recent_activities'       => array(),
		);

		// Get total favorites from our table
		$favorites_table = $wpdb->prefix . 'bp_activity_favorites';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$favorites_table}'" ) === $favorites_table ) {
			$stats['total_favorites'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$favorites_table}" );

			// Favorites in last 7 days
			$stats['favorites_last_7_days'] = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$favorites_table}
				WHERE favorited_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
			);

			// Active users (who liked something in last 7 days)
			$stats['active_users_7_days'] = (int) $wpdb->get_var(
				"SELECT COUNT(DISTINCT user_id) FROM {$favorites_table}
				WHERE favorited_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
			);

			// Most liked activity
			$most_liked = $wpdb->get_row(
				"SELECT activity_id, COUNT(*) as count
				FROM {$favorites_table}
				GROUP BY activity_id
				ORDER BY count DESC
				LIMIT 1"
			);

			if ( $most_liked ) {
				$stats['most_liked_activity'] = (int) $most_liked->activity_id;
				$stats['most_liked_count'] = (int) $most_liked->count;
			}

			// Recent favorited activities (last 10)
			$stats['recent_activities'] = $wpdb->get_results(
				"SELECT activity_id, user_id, favorited_at
				FROM {$favorites_table}
				WHERE favorited_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
				ORDER BY favorited_at DESC
				LIMIT 10"
			);
		}

		// Get notification stats
		if ( bp_is_active( 'notifications' ) ) {
			$notifications_table = $bp->notifications->table_name;
			$component = isset( $bp->favorite_notifier ) ? $bp->favorite_notifier->id : 'favorite_notifier';

			$stats['total_notifications'] = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$notifications_table} WHERE component_name = %s",
				$component
			) );

			$stats['notifications_last_7_days'] = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$notifications_table}
				WHERE component_name = %s
				AND date_notified >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
				$component
			) );
		}

		return $stats;
	}

	/**
	 * Render recent activity
	 */
	private function render_recent_activity( $stats ) {
		if ( empty( $stats['recent_activities'] ) ) {
			echo '<p>' . esc_html__( 'No favorites in the last 7 days.', 'bp-fav-notification' ) . '</p>';
			return;
		}

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead>';
		echo '<tr>';
		echo '<th>' . esc_html__( 'User', 'bp-fav-notification' ) . '</th>';
		echo '<th>' . esc_html__( 'Activity', 'bp-fav-notification' ) . '</th>';
		echo '<th>' . esc_html__( 'Date', 'bp-fav-notification' ) . '</th>';
		echo '</tr>';
		echo '</thead>';
		echo '<tbody>';

		foreach ( $stats['recent_activities'] as $activity ) {
			$user = get_userdata( $activity->user_id );
			$user_name = $user ? $user->display_name : __( 'Unknown User', 'bp-fav-notification' );

			echo '<tr>';
			echo '<td>' . esc_html( $user_name ) . '</td>';
			echo '<td>' . sprintf( esc_html__( 'Activity #%d', 'bp-fav-notification' ), $activity->activity_id ) . '</td>';
			echo '<td>' . esc_html( human_time_diff( strtotime( $activity->favorited_at ), current_time( 'timestamp' ) ) ) . ' ' . esc_html__( 'ago', 'bp-fav-notification' ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody>';
		echo '</table>';
	}

	/**
	 * Render settings section
	 */
	private function render_settings_section() {
		?>
		<div class="bpfn-settings-section" style="margin-bottom: 40px;">
			<h2><?php _e( 'Settings', 'bp-fav-notification' ); ?></h2>
			
			<div class="bpfn-admin-container">
				<div class="bpfn-admin-main">
					<form method="post" action="options.php">
						<?php
						settings_fields( 'bpfn_settings' );
						do_settings_sections( 'bpfn-settings' );
						submit_button();
						?>
					</form>
				</div>
				
				<div class="bpfn-admin-sidebar">
					<?php $this->render_sidebar(); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render tools section
	 */
	private function render_tools_section() {
		?>
		<div class="bpfn-tools-section" style="margin-bottom: 40px;">
			<h2><?php _e( 'Tools & Maintenance', 'bp-fav-notification' ); ?></h2>

			<!-- Migrate Favorites -->
				<?php
				require_once BPFN_INCLUDES_PATH . 'migrations/class-favorites-migration.php';
				$migration = new BPFN_Favorites_Migration();
				$stats = $migration->get_migration_stats();
				?>
				<div class="postbox">
					<h3 class="hndle"><?php _e( 'Migrate Favorites', 'bp-fav-notification' ); ?></h3>
					<div class="inside">
						<?php if ( $stats['migrated'] ) : ?>
							<div class="notice notice-success inline">
								<p><strong><?php _e( 'Migration completed!', 'bp-fav-notification' ); ?></strong></p>
							</div>
							<?php
							$log = $migration->get_migration_log();
							if ( ! empty( $log ) ) :
								?>
								<p>
									<?php
									printf(
										/* translators: 1: number of users, 2: number of favorites */
										esc_html__( 'Processed %1$d users and migrated %2$d favorites.', 'bp-fav-notification' ),
										isset( $log['users_processed'] ) ? $log['users_processed'] : 0,
										isset( $log['favorites_added'] ) ? $log['favorites_added'] : 0
									);
									?>
								</p>
								<?php if ( isset( $log['start_time'] ) ) : ?>
									<p>
										<small>
											<?php
											printf(
												/* translators: %s: migration date/time */
												esc_html__( 'Completed on: %s', 'bp-fav-notification' ),
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
									/* translators: 1: number of users, 2: number of favorites */
									esc_html__( 'Found %1$d users with %2$d favorites to migrate.', 'bp-fav-notification' ),
									$stats['users_with_favorites'],
									$stats['meta_favorites_count']
								);
								?>
							</p>
							<p><?php _e( 'Click the button below to migrate existing favorites to the new optimized table.', 'bp-fav-notification' ); ?></p>
							<button class="button button-primary" id="bpfn-migrate-favorites">
								<?php _e( 'Run Migration', 'bp-fav-notification' ); ?>
							</button>
							<div id="bpfn-migrate-result" style="margin-top: 10px;"></div>
						<?php else : ?>
							<p><?php _e( 'No favorites found to migrate.', 'bp-fav-notification' ); ?></p>
						<?php endif; ?>
					</div>
				</div>

				<!-- Clear Old Notifications -->
				<div class="postbox">
					<h3 class="hndle"><?php _e( 'Database Maintenance', 'bp-fav-notification' ); ?></h3>
					<div class="inside">
						<p><?php _e( 'Remove read notifications older than a specified period to keep your database clean.', 'bp-fav-notification' ); ?></p>

						<!-- Automatic Cleanup Settings -->
						<form method="post" action="">
							<?php wp_nonce_field( 'bpfn_cleanup_settings', 'bpfn_cleanup_nonce' ); ?>

							<table class="form-table">
								<tr>
									<th scope="row">
										<label for="bpfn_auto_cleanup_enabled">
											<?php _e( 'Automatic Cleanup', 'bp-fav-notification' ); ?>
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
											<?php _e( 'Enable automatic monthly cleanup', 'bp-fav-notification' ); ?>
										</label>
										<p class="description">
											<?php _e( 'Automatically remove old read notifications once per month via WP Cron.', 'bp-fav-notification' ); ?>
										</p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="bpfn_auto_cleanup_days">
											<?php _e( 'Retention Period', 'bp-fav-notification' ); ?>
										</label>
									</th>
									<td>
										<?php $cleanup_days = get_option( 'bpfn_auto_cleanup_days', 30 ); ?>
										<select name="bpfn_auto_cleanup_days" id="bpfn_auto_cleanup_days">
											<option value="7" <?php selected( $cleanup_days, 7 ); ?>>7 <?php _e( 'days', 'bp-fav-notification' ); ?></option>
											<option value="15" <?php selected( $cleanup_days, 15 ); ?>>15 <?php _e( 'days', 'bp-fav-notification' ); ?></option>
											<option value="30" <?php selected( $cleanup_days, 30 ); ?>>30 <?php _e( 'days', 'bp-fav-notification' ); ?></option>
											<option value="60" <?php selected( $cleanup_days, 60 ); ?>>60 <?php _e( 'days', 'bp-fav-notification' ); ?></option>
											<option value="90" <?php selected( $cleanup_days, 90 ); ?>>90 <?php _e( 'days', 'bp-fav-notification' ); ?></option>
										</select>
										<p class="description">
											<?php _e( 'Only read/dismissed notifications older than this will be deleted.', 'bp-fav-notification' ); ?>
										</p>
									</td>
								</tr>
							</table>

							<p class="submit">
								<button type="submit" name="bpfn_save_cleanup_settings" class="button button-primary">
									<?php _e( 'Save Settings', 'bp-fav-notification' ); ?>
								</button>
							</p>
						</form>

						<?php
						// Show last cleanup info
						$last_cleanup = get_option( 'bpfn_last_auto_cleanup', array() );
						if ( ! empty( $last_cleanup ) ) :
							?>
							<div class="notice notice-info inline">
								<p>
									<strong><?php _e( 'Last automatic cleanup:', 'bp-fav-notification' ); ?></strong><br>
									<?php
									printf(
										/* translators: 1: date, 2: number deleted, 3: number remaining */
										esc_html__( '%1$s - Deleted %2$d notifications, %3$d remaining', 'bp-fav-notification' ),
										isset( $last_cleanup['date'] ) ? esc_html( $last_cleanup['date'] ) : 'N/A',
										isset( $last_cleanup['deleted'] ) ? (int) $last_cleanup['deleted'] : 0,
										isset( $last_cleanup['remaining'] ) ? (int) $last_cleanup['remaining'] : 0
									);
									?>
								</p>
							</div>
							<?php
						endif;

						// Show next scheduled cleanup
						$next_cleanup = wp_next_scheduled( 'bpfn_auto_cleanup_notifications' );
						if ( $next_cleanup && $auto_enabled === 'yes' ) :
							?>
							<p>
								<small>
									<?php
									printf(
										/* translators: %s: next cleanup date/time */
										esc_html__( 'Next automatic cleanup: %s', 'bp-fav-notification' ),
										date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_cleanup )
									);
									?>
								</small>
							</p>
							<?php
						endif;
						?>

						<hr style="margin: 20px 0;">

						<!-- Manual Cleanup -->
						<h4><?php _e( 'Manual Cleanup', 'bp-fav-notification' ); ?></h4>
						<p><?php _e( 'Run cleanup immediately without waiting for the automatic schedule.', 'bp-fav-notification' ); ?></p>
						<button class="button" id="bpfn-clear-old-notifications">
							<?php _e( 'Clear Old Notifications Now', 'bp-fav-notification' ); ?>
						</button>
						<div id="bpfn-clear-result" style="margin-top: 10px;"></div>
					</div>
				</div>

		</div>
		<?php
	}

	/**
	 * Render admin sidebar
	 */
	private function render_sidebar() {
		?>
		<div class="postbox">
			<h3 class="hndle"><?php _e( 'Quick Stats', 'bp-fav-notification' ); ?></h3>
			<div class="inside">
				<?php $this->render_stats(); ?>
			</div>
		</div>
		
		<div class="postbox">
			<h3 class="hndle"><?php _e( 'Support', 'bp-fav-notification' ); ?></h3>
			<div class="inside">
				<ul>
					<li><a href="https://wordpress.org/support/plugin/bp-favorite-notification/" target="_blank"><?php _e( 'Support Forum', 'bp-fav-notification' ); ?></a></li>
					<li><a href="https://wordpress.org/support/plugin/bp-favorite-notification/reviews/" target="_blank"><?php _e( 'Leave Review', 'bp-fav-notification' ); ?></a></li>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Render stats
	 */
	private function render_stats() {
		$stats = array(
			'total_notifications' => 0,
			'active_users' => 0
		);
		
		if ( function_exists( 'bpfn_get_notification_stats' ) ) {
			$stats = bpfn_get_notification_stats();
		}
		
		?>
		<table class="widefat">
			<tr>
				<td><?php _e( 'Total Notifications:', 'bp-fav-notification' ); ?></td>
				<td><strong><?php echo number_format_i18n( $stats['total_notifications'] ); ?></strong></td>
			</tr>
			<tr>
				<td><?php _e( 'Active Users:', 'bp-fav-notification' ); ?></td>
				<td><strong><?php echo number_format_i18n( $stats['active_users'] ); ?></strong></td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Admin init
	 */
	public function admin_init() {
		// Register settings
		register_setting( 'bpfn_settings', 'bpfn_options', array( $this, 'sanitize_options' ) );

		// Add settings sections
		add_settings_section(
			'bpfn_general',
			__( 'General Settings', 'bp-fav-notification' ),
			array( $this, 'section_general' ),
			'bpfn-settings'
		);

		// Add settings fields
		add_settings_field(
			'enable_enhanced_notifications',
			__( 'Enhanced Notifications', 'bp-fav-notification' ),
			array( $this, 'field_checkbox' ),
			'bpfn-settings',
			'bpfn_general',
			array(
				'name' => 'enable_enhanced_notifications',
				'label' => __( 'Enable enhanced notification display', 'bp-fav-notification' ),
			)
		);

		// Handle cleanup settings save
		$this->handle_cleanup_settings_save();
	}

	/**
	 * Handle cleanup settings save
	 */
	private function handle_cleanup_settings_save() {
		if ( ! isset( $_POST['bpfn_save_cleanup_settings'] ) ) {
			return;
		}

		// Verify nonce
		if ( ! isset( $_POST['bpfn_cleanup_nonce'] ) || ! wp_verify_nonce( $_POST['bpfn_cleanup_nonce'], 'bpfn_cleanup_settings' ) ) {
			return;
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Save enabled/disabled
		$enabled = isset( $_POST['bpfn_auto_cleanup_enabled'] ) ? 'yes' : 'no';
		$old_enabled = get_option( 'bpfn_auto_cleanup_enabled', 'yes' );
		update_option( 'bpfn_auto_cleanup_enabled', $enabled );

		// Save retention period
		$days = isset( $_POST['bpfn_auto_cleanup_days'] ) ? absint( $_POST['bpfn_auto_cleanup_days'] ) : 30;
		if ( $days < 7 ) {
			$days = 7;
		}
		update_option( 'bpfn_auto_cleanup_days', $days );

		// Schedule or unschedule based on enabled status
		$next_scheduled = wp_next_scheduled( 'bpfn_auto_cleanup_notifications' );

		if ( $enabled === 'yes' && ! $next_scheduled ) {
			// Schedule if enabled and not already scheduled
			wp_schedule_event( time(), 'monthly', 'bpfn_auto_cleanup_notifications' );
		} elseif ( $enabled === 'no' && $next_scheduled ) {
			// Unschedule if disabled
			wp_clear_scheduled_hook( 'bpfn_auto_cleanup_notifications' );
		}

		// Redirect with success message
		wp_redirect( add_query_arg( array( 'page' => 'bpfn-dashboard', 'settings_updated' => 'true' ), admin_url( 'admin.php' ) ) . '#tools' );
		exit;
	}

	/**
	 * General section
	 */
	public function section_general() {
		echo '<p>' . __( 'Configure general plugin settings.', 'bp-fav-notification' ) . '</p>';
	}

	/**
	 * Checkbox field
	 */
	public function field_checkbox( $args ) {
		$options = get_option( 'bpfn_options', array() );
		$value = isset( $options[ $args['name'] ] ) ? $options[ $args['name'] ] : 0;
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
	 * Sanitize options
	 */
	public function sanitize_options( $input ) {
		$sanitized = array();

		if ( isset( $input['enable_enhanced_notifications'] ) ) {
			$sanitized['enable_enhanced_notifications'] = 1;
		}

		return $sanitized;
	}

	/**
	 * Add plugin action links
	 */
	public function add_action_links( $links ) {
		$action_links = array(
			'settings' => '<a href="' . admin_url( 'admin.php?page=bpfn-settings' ) . '">' . __( 'Settings', 'bp-fav-notification' ) . '</a>',
		);
		
		return array_merge( $action_links, $links );
	}

	/**
	 * Display admin notices
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
	 * AJAX clear old notifications
	 */
	public function ajax_clear_old_notifications() {
		check_ajax_referer( 'bpfn-admin-nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'bp-fav-notification' ) ) );
		}
		
		if ( ! function_exists( 'bpfn_clear_old_notifications' ) ) {
			wp_send_json_error( array( 'message' => __( 'Function not available', 'bp-fav-notification' ) ) );
		}
		
		$result = bpfn_clear_old_notifications();
		
		if ( isset( $result['count'] ) ) {
			wp_send_json_success( array( 
				'message' => sprintf( __( 'Cleared %d old notifications', 'bp-fav-notification' ), $result['count'] )
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to clear notifications', 'bp-fav-notification' ) ) );
		}
	}

	/**
	 * AJAX get stats
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
	 * AJAX migrate favorites
	 */
	public function ajax_migrate_favorites() {
		check_ajax_referer( 'bpfn-admin-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'bp-fav-notification' ) ) );
		}

		require_once BPFN_INCLUDES_PATH . 'migrations/class-favorites-migration.php';
		$migration = new BPFN_Favorites_Migration();

		// Check if we should use background processing
		$stats = $migration->get_migration_stats();
		$use_background = $stats['users_with_favorites'] > 100; // Use background for 100+ users

		if ( $use_background ) {
			// Start background migration
			$result = $migration->start_migration();
			wp_send_json_success( array(
				'message'    => $result['message'],
				'background' => true,
			) );
		} else {
			// Run synchronously for small sites
			$log = $migration->run_migration();

			if ( isset( $log['message'] ) ) {
				wp_send_json_success( array(
					'message'    => $log['message'],
					'log'        => $log,
					'background' => false,
				) );
			} else {
				wp_send_json_error( array( 'message' => __( 'Migration failed', 'bp-fav-notification' ) ) );
			}
		}
	}

	/**
	 * AJAX handler to check migration progress
	 */
	public function ajax_migration_progress() {
		check_ajax_referer( 'bpfn-admin-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'bp-fav-notification' ) ) );
		}

		require_once BPFN_INCLUDES_PATH . 'migrations/class-favorites-migration.php';
		$migration = new BPFN_Favorites_Migration();

		$progress = $migration->get_migration_progress();

		wp_send_json_success( $progress );
	}

	/**
	 * Show migration notice
	 */
	public function migration_notice() {
		// Only show on admin pages
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check if notice should be shown
		if ( ! get_option( 'bpfn_show_migration_notice', false ) ) {
			return;
		}

		require_once BPFN_INCLUDES_PATH . 'migrations/class-favorites-migration.php';
		$migration = new BPFN_Favorites_Migration();
		$stats = $migration->get_migration_stats();

		// Don't show if migration is complete
		if ( $stats['migrated'] || ! $stats['migration_pending'] ) {
			delete_option( 'bpfn_show_migration_notice' );
			return;
		}

		$tools_url = admin_url( 'admin.php?page=bpfn-dashboard#tools' );
		?>
		<div class="notice notice-info is-dismissible" data-dismissible="bpfn-migration-notice">
			<p>
				<strong><?php _e( 'BuddyPress Favorite Notification:', 'bp-fav-notification' ); ?></strong>
				<?php
				printf(
					/* translators: 1: number of users, 2: number of favorites, 3: tools page link */
					__( 'Found %1$d users with %2$d favorites that need to be migrated to the new optimized system. <a href="%3$s">Run migration now</a>', 'bp-fav-notification' ),
					$stats['users_with_favorites'],
					$stats['meta_favorites_count'],
					esc_url( $tools_url )
				);
				?>
			</p>
		</div>
		<script>
		jQuery(document).on('click', '[data-dismissible="bpfn-migration-notice"] .notice-dismiss', function() {
			jQuery.post(ajaxurl, {
				action: 'bpfn_dismiss_migration_notice',
				nonce: '<?php echo wp_create_nonce( 'bpfn-dismiss-notice' ); ?>'
			});
		});
		</script>
		<?php
	}

	/**
	 * Setup automatic cleanup
	 */
	private function setup_automatic_cleanup() {
		// Register WP Cron action
		add_action( 'bpfn_auto_cleanup_notifications', array( $this, 'run_automatic_cleanup' ) );

		// Schedule if not already scheduled and option is enabled
		$enabled = get_option( 'bpfn_auto_cleanup_enabled', 'yes' );
		if ( $enabled === 'yes' && ! wp_next_scheduled( 'bpfn_auto_cleanup_notifications' ) ) {
			wp_schedule_event( time(), 'monthly', 'bpfn_auto_cleanup_notifications' );
		}
	}

	/**
	 * Run automatic cleanup
	 */
	public function run_automatic_cleanup() {
		// Check if enabled
		$enabled = get_option( 'bpfn_auto_cleanup_enabled', 'yes' );
		if ( $enabled !== 'yes' ) {
			return;
		}

		// Get retention period (default 30 days)
		$days = get_option( 'bpfn_auto_cleanup_days', 30 );
		$days = absint( $days );
		if ( $days < 7 ) {
			$days = 7; // Minimum 7 days
		}

		// Run cleanup
		if ( function_exists( 'bpfn_clear_old_notifications' ) ) {
			$result = bpfn_clear_old_notifications( $days );

			// Log result
			update_option( 'bpfn_last_auto_cleanup', array(
				'date'      => current_time( 'mysql' ),
				'deleted'   => isset( $result['count'] ) ? $result['count'] : 0,
				'remaining' => isset( $result['remaining'] ) ? $result['remaining'] : 0,
			) );
		}
	}
}