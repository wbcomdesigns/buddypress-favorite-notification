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
		
		// AJAX handlers
		$this->register_ajax_handlers();
	}

	/**
	 * Register AJAX handlers
	 */
	private function register_ajax_handlers() {
		$ajax_actions = array(
			'clear_old_notifications',
			'get_stats'
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
}