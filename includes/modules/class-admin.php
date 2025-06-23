<?php
/**
 * Admin Module for BuddyPress Favorite Notification
 *
 * @package BuddyPress_Favorite_Notification
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin Module Class
 */
class BPFN_Module_Admin {

	/**
	 * Admin notices
	 */
	private $notices = array();

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
		
		// Plugin meta links
		add_filter( 'plugin_row_meta', array( $this, 'add_meta_links' ), 10, 2 );
		
		// Admin init
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		
		// AJAX handlers
		add_action( 'wp_ajax_bpfn_dismiss_notice', array( $this, 'ajax_dismiss_notice' ) );
		
		// Review notice
		$this->maybe_show_review_notice();
		
		// Custom hooks
		do_action( 'bpfn_admin_setup_hooks', $this );
	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		// Main menu
		$main_page = add_menu_page(
			__( 'BP Favorite Notification', 'bp-fav-notification' ),
			__( 'BP Favorites', 'bp-fav-notification' ),
			'manage_options',
			'bpfn-settings',
			array( $this, 'settings_page' ),
			'dashicons-heart',
			30
		);
		
		// Submenu pages
		add_submenu_page(
			'bpfn-settings',
			__( 'Settings', 'bp-fav-notification' ),
			__( 'Settings', 'bp-fav-notification' ),
			'manage_options',
			'bpfn-settings',
			array( $this, 'settings_page' )
		);
		
		add_submenu_page(
			'bpfn-settings',
			__( 'Tools', 'bp-fav-notification' ),
			__( 'Tools', 'bp-fav-notification' ),
			'manage_options',
			'bpfn-tools',
			array( $this, 'tools_page' )
		);
		
		add_submenu_page(
			'bpfn-settings',
			__( 'Help', 'bp-fav-notification' ),
			__( 'Help', 'bp-fav-notification' ),
			'manage_options',
			'bpfn-help',
			array( $this, 'help_page' )
		);
		
		// Hook for page specific scripts
		add_action( 'admin_print_scripts-' . $main_page, array( $this, 'enqueue_admin_scripts' ) );
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
			
			<div class="bpfn-tools-container">
				<?php $this->render_tools(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Help page
	 */
	public function help_page() {
		?>
		<div class="wrap">
			<h1><?php _e( 'BuddyPress Favorite Notification Help', 'bp-fav-notification' ); ?></h1>
			
			<div class="bpfn-help-container">
				<?php $this->render_help(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render admin sidebar
	 */
	private function render_sidebar() {
		?>
		<div class="bpfn-admin-widget">
			<h3><?php _e( 'Quick Stats', 'bp-fav-notification' ); ?></h3>
			<?php $this->render_stats(); ?>
		</div>
		
		<div class="bpfn-admin-widget">
			<h3><?php _e( 'Documentation', 'bp-fav-notification' ); ?></h3>
			<ul>
				<li><a href="https://wbcomdesigns.com/docs/buddypress-favorite-notification/" target="_blank"><?php _e( 'Getting Started', 'bp-fav-notification' ); ?></a></li>
				<li><a href="https://wbcomdesigns.com/docs/buddypress-favorite-notification/hooks/" target="_blank"><?php _e( 'Developer Docs', 'bp-fav-notification' ); ?></a></li>
				<li><a href="https://wordpress.org/support/plugin/bp-favorite-notification/" target="_blank"><?php _e( 'Support Forum', 'bp-fav-notification' ); ?></a></li>
			</ul>
		</div>
		
		<div class="bpfn-admin-widget">
			<h3><?php _e( 'Support Us', 'bp-fav-notification' ); ?></h3>
			<p><?php _e( 'Love this plugin? Please consider:', 'bp-fav-notification' ); ?></p>
			<ul>
				<li><a href="https://wordpress.org/support/plugin/bp-favorite-notification/reviews/#new-post" target="_blank"><?php _e( 'Leave a Review', 'bp-fav-notification' ); ?></a></li>
				<li><a href="https://wbcomdesigns.com/donate/" target="_blank"><?php _e( 'Make a Donation', 'bp-fav-notification' ); ?></a></li>
			</ul>
		</div>
		<?php
	}

	/**
	 * Render stats
	 */
	private function render_stats() {
		global $wpdb, $bp;
		
		// Get notification count
		$table_name = $bp->notifications->table_name;
		$total_notifications = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table_name} WHERE component_name = %s",
			$bp->favorite_notifier->id
		) );
		
		// Get active users count
		$active_users = $wpdb->get_var( 
			"SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}bp_favorite_notification_prefs"
		);
		
		?>
		<ul class="bpfn-stats">
			<li>
				<span class="bpfn-stat-value"><?php echo number_format_i18n( $total_notifications ); ?></span>
				<span class="bpfn-stat-label"><?php _e( 'Total Notifications', 'bp-fav-notification' ); ?></span>
			</li>
			<li>
				<span class="bpfn-stat-value"><?php echo number_format_i18n( $active_users ); ?></span>
				<span class="bpfn-stat-label"><?php _e( 'Active Users', 'bp-fav-notification' ); ?></span>
			</li>
		</ul>
		<?php
	}

	/**
	 * Render tools
	 */
	private function render_tools() {
		?>
		<div class="bpfn-tool-box">
			<h3><?php _e( 'Test Notifications', 'bp-fav-notification' ); ?></h3>
			<p><?php _e( 'Send a test notification to yourself to verify everything is working correctly.', 'bp-fav-notification' ); ?></p>
			<button class="button button-primary" id="bpfn-send-test-notification">
				<?php _e( 'Send Test Notification', 'bp-fav-notification' ); ?>
			</button>
			<div id="bpfn-test-result"></div>
		</div>
		
		<div class="bpfn-tool-box">
			<h3><?php _e( 'Clear Old Notifications', 'bp-fav-notification' ); ?></h3>
			<p><?php _e( 'Remove read notifications older than 30 days.', 'bp-fav-notification' ); ?></p>
			<button class="button" id="bpfn-clear-old-notifications">
				<?php _e( 'Clear Old Notifications', 'bp-fav-notification' ); ?>
			</button>
		</div>
		
		<div class="bpfn-tool-box">
			<h3><?php _e( 'Export Settings', 'bp-fav-notification' ); ?></h3>
			<p><?php _e( 'Export your plugin settings for backup or migration.', 'bp-fav-notification' ); ?></p>
			<button class="button" id="bpfn-export-settings">
				<?php _e( 'Export Settings', 'bp-fav-notification' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Render help
	 */
	private function render_help() {
		?>
		<div class="bpfn-help-section">
			<h2><?php _e( 'Frequently Asked Questions', 'bp-fav-notification' ); ?></h2>
			
			<div class="bpfn-faq">
				<h3><?php _e( 'How do I customize notification templates?', 'bp-fav-notification' ); ?></h3>
				<p><?php _e( 'You can override templates by copying them to your theme directory under:', 'bp-fav-notification' ); ?></p>
				<code>/your-theme/buddypress/bp-favorite-notification/</code>
			</div>
			
			<div class="bpfn-faq">
				<h3><?php _e( 'How do I add custom notification types?', 'bp-fav-notification' ); ?></h3>
				<p><?php _e( 'Use the bpfn_register_notification_type() function:', 'bp-fav-notification' ); ?></p>
				<pre><code>bpfn_register_notification_type( 'custom_type', array(
    'labels' => array(
        'single' => __( '%s did something', 'textdomain' ),
        'multiple' => __( '%d people did something', 'textdomain' ),
    ),
    'action_prefix' => 'custom_notify',
) );</code></pre>
			</div>
		</div>
		
		<div class="bpfn-help-section">
			<h2><?php _e( 'Available Hooks', 'bp-fav-notification' ); ?></h2>
			
			<h3><?php _e( 'Actions', 'bp-fav-notification' ); ?></h3>
			<ul>
				<li><code>bpfn_init</code> - Plugin initialized</li>
				<li><code>bpfn_after_add_notification</code> - After notification added</li>
				<li><code>bpfn_after_send_email</code> - After email sent</li>
			</ul>
			
			<h3><?php _e( 'Filters', 'bp-fav-notification' ); ?></h3>
			<ul>
				<li><code>bpfn_notification_types</code> - Modify notification types</li>
				<li><code>bpfn_email_templates</code> - Modify email templates</li>
				<li><code>bpfn_notification_text</code> - Modify notification text</li>
			</ul>
		</div>
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
		
		add_settings_section(
			'bpfn_notifications',
			__( 'Notification Settings', 'bp-fav-notification' ),
			array( $this, 'section_notifications' ),
			'bpfn-settings'
		);
		
		// Add settings fields
		$this->add_settings_fields();
	}

	/**
	 * Add settings fields
	 */
	private function add_settings_fields() {
		// General fields
		add_settings_field(
			'enable_for_logged_out',
			__( 'Show for Logged Out Users', 'bp-fav-notification' ),
			array( $this, 'field_checkbox' ),
			'bpfn-settings',
			'bpfn_general',
			array(
				'name' => 'enable_for_logged_out',
				'label' => __( 'Display favorite counts to logged out users', 'bp-fav-notification' ),
			)
		);
		
		// Notification fields
		add_settings_field(
			'enable_enhanced_notifications',
			__( 'Enhanced Notifications', 'bp-fav-notification' ),
			array( $this, 'field_checkbox' ),
			'bpfn-settings',
			'bpfn_notifications',
			array(
				'name' => 'enable_enhanced_notifications',
				'label' => __( 'Enable enhanced notification display with avatars and excerpts', 'bp-fav-notification' ),
			)
		);
		
		add_settings_field(
			'realtime_interval',
			__( 'Real-time Check Interval', 'bp-fav-notification' ),
			array( $this, 'field_number' ),
			'bpfn-settings',
			'bpfn_notifications',
			array(
				'name' => 'realtime_interval',
				'description' => __( 'Seconds between real-time notification checks (minimum 15)', 'bp-fav-notification' ),
				'min' => 15,
				'max' => 300,
				'default' => 15,
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
	 * Notifications section
	 */
	public function section_notifications() {
		echo '<p>' . __( 'Configure notification display and behavior.', 'bp-fav-notification' ) . '</p>';
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
		
		if ( ! empty( $args['description'] ) ) {
			echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
		}
	}

	/**
	 * Number field
	 */
	public function field_number( $args ) {
		$options = get_option( 'bpfn_options', array() );
		$value = isset( $options[ $args['name'] ] ) ? $options[ $args['name'] ] : $args['default'];
		
		?>
		<input type="number" 
			   name="bpfn_options[<?php echo esc_attr( $args['name'] ); ?>]" 
			   value="<?php echo esc_attr( $value ); ?>"
			   min="<?php echo esc_attr( $args['min'] ); ?>"
			   max="<?php echo esc_attr( $args['max'] ); ?>"
			   class="small-text" />
		<?php
		
		if ( ! empty( $args['description'] ) ) {
			echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
		}
	}

	/**
	 * Sanitize options
	 */
	public function sanitize_options( $input ) {
		$sanitized = array();
		
		// Checkboxes
		$checkboxes = array( 'enable_for_logged_out', 'enable_enhanced_notifications' );
		foreach ( $checkboxes as $checkbox ) {
			$sanitized[ $checkbox ] = isset( $input[ $checkbox ] ) ? 1 : 0;
		}
		
		// Numbers
		if ( isset( $input['realtime_interval'] ) ) {
			$sanitized['realtime_interval'] = max( 15, min( 300, intval( $input['realtime_interval'] ) ) );
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
	 * Add plugin meta links
	 */
	public function add_meta_links( $links, $file ) {
		if ( $file !== BPFN_PLUGIN_BASENAME ) {
			return $links;
		}
		
		$meta_links = array(
			'docs' => '<a href="https://wbcomdesigns.com/docs/buddypress-favorite-notification/" target="_blank">' . __( 'Documentation', 'bp-fav-notification' ) . '</a>',
			'support' => '<a href="https://wordpress.org/support/plugin/bp-favorite-notification/" target="_blank">' . __( 'Support', 'bp-fav-notification' ) . '</a>',
		);
		
		return array_merge( $links, $meta_links );
	}

	/**
	 * Enqueue admin scripts
	 */
	public function enqueue_admin_scripts() {
		// Enqueue admin specific scripts if needed
		wp_enqueue_script( 'bpfn-admin-tools', BPFN_ASSETS_URL . 'js/admin-tools.js', array( 'jquery' ), BPFN_VERSION );
		
		wp_localize_script( 'bpfn-admin-tools', 'bpfnAdmin', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'bpfn-admin-nonce' ),
			'strings' => array(
				'testing' => __( 'Sending test notification...', 'bp-fav-notification' ),
				'test_success' => __( 'Test notification sent successfully!', 'bp-fav-notification' ),
				'test_error' => __( 'Failed to send test notification.', 'bp-fav-notification' ),
			),
		) );
	}

	/**
	 * Maybe show review notice
	 */
	private function maybe_show_review_notice() {
		// Check if we should show review notice
		$install_date = get_option( 'bpfn_install_date', false );
		
		if ( ! $install_date ) {
			update_option( 'bpfn_install_date', time() );
			return;
		}
		
		// Show after 7 days
		if ( ( time() - $install_date ) < ( 7 * DAY_IN_SECONDS ) ) {
			return;
		}
		
		// Check if already dismissed
		if ( get_option( 'bpfn_review_dismissed', false ) ) {
			return;
		}
		
		// Add notice
		$this->add_notice( 'review', $this->get_review_notice_content(), 'info', true );
	}

	/**
	 * Get review notice content
	 */
	private function get_review_notice_content() {
		return sprintf(
			'<p><strong>%s</strong></p><p>%s</p><p><a href="%s" class="button button-primary" target="_blank">%s</a> <a href="#" class="button bpfn-dismiss-notice" data-notice="review">%s</a></p>',
			__( 'Enjoying BuddyPress Favorite Notification?', 'bp-fav-notification' ),
			__( 'We\'d love to hear your feedback! Please take a moment to rate us on WordPress.org.', 'bp-fav-notification' ),
			'https://wordpress.org/support/plugin/bp-favorite-notification/reviews/#new-post',
			__( 'Leave a Review', 'bp-fav-notification' ),
			__( 'Maybe Later', 'bp-fav-notification' )
		);
	}

	/**
	 * Add admin notice
	 */
	public function add_notice( $id, $message, $type = 'info', $dismissible = true ) {
		$this->notices[ $id ] = array(
			'message' => $message,
			'type' => $type,
			'dismissible' => $dismissible,
		);
	}

	/**
	 * Display admin notices
	 */
	public function display_admin_notices() {
		foreach ( $this->notices as $id => $notice ) {
			$classes = 'notice notice-' . $notice['type'];
			if ( $notice['dismissible'] ) {
				$classes .= ' is-dismissible';
			}
			
			printf(
				'<div class="%s" data-notice-id="%s">%s</div>',
				esc_attr( $classes ),
				esc_attr( $id ),
				wp_kses_post( $notice['message'] )
			);
		}
	}

	/**
	 * AJAX dismiss notice
	 */
	public function ajax_dismiss_notice() {
		check_ajax_referer( 'bpfn-admin-nonce', 'nonce' );
		
		$notice_id = isset( $_POST['notice_id'] ) ? sanitize_text_field( $_POST['notice_id'] ) : '';
		
		if ( $notice_id === 'review' ) {
			update_option( 'bpfn_review_dismissed', true );
		}
		
		wp_send_json_success();
	}
}