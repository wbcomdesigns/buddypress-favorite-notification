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
		// Main menu
		$hook = add_menu_page(
			__( 'BP Favorite Notification', 'bp-fav-notification' ),
			__( 'BP Favorites', 'bp-fav-notification' ),
			'manage_options',
			'bpfn-settings',
			array( $this, 'settings_page' ),
			'dashicons-heart',
			30
		);
		
		$this->admin_hooks[] = $hook;
		
		// Submenu pages
		$this->admin_hooks[] = add_submenu_page(
			'bpfn-settings',
			__( 'Settings', 'bp-fav-notification' ),
			__( 'Settings', 'bp-fav-notification' ),
			'manage_options',
			'bpfn-settings',
			array( $this, 'settings_page' )
		);
		
		$this->admin_hooks[] = add_submenu_page(
			'bpfn-settings',
			__( 'Tools', 'bp-fav-notification' ),
			__( 'Tools', 'bp-fav-notification' ),
			'manage_options',
			'bpfn-tools',
			array( $this, 'tools_page' )
		);
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
	 * Settings page
	 */
	public function settings_page() {
		?>
		<div class="wrap">
			<h1><?php _e( 'BuddyPress Favorite Notification Settings', 'bp-fav-notification' ); ?></h1>
			
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
	 * Tools page
	 */
	public function tools_page() {
		?>
		<div class="wrap">
			<h1><?php _e( 'BuddyPress Favorite Notification Tools', 'bp-fav-notification' ); ?></h1>

			<div class="postbox-container" style="width: 70%;">

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
						<p><?php _e( 'Remove read notifications older than 30 days to keep your database clean.', 'bp-fav-notification' ); ?></p>
						<button class="button" id="bpfn-clear-old-notifications">
							<?php _e( 'Clear Old Notifications', 'bp-fav-notification' ); ?>
						</button>
						<div id="bpfn-clear-result" style="margin-top: 10px;"></div>
					</div>
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

		$tools_url = admin_url( 'admin.php?page=bpfn-tools' );
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
}