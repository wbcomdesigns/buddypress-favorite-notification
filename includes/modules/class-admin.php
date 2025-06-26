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
		// Admin menu - needs to be early
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		
		// Admin notices
		add_action( 'admin_notices', array( $this, 'display_admin_notices' ) );
		
		// Plugin action links
		add_filter( 'plugin_action_links_' . BPFN_PLUGIN_BASENAME, array( $this, 'add_action_links' ) );
		
		// Plugin meta links
		add_filter( 'plugin_row_meta', array( $this, 'add_meta_links' ), 10, 2 );
		
		// Admin init - for settings registration
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		
		// Admin assets
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		
		// AJAX handlers
		$this->register_ajax_handlers();
		
		// Review notice - delay until translations are ready
		add_action( 'admin_init', array( $this, 'maybe_show_review_notice' ) );
		
		// Custom hooks
		do_action( 'bpfn_admin_setup_hooks', $this );
	}

	/**
	 * Register AJAX handlers
	 */
	private function register_ajax_handlers() {
		$ajax_actions = array(
			'dismiss_notice',
			'send_test_notification',
			'send_test_email',
			'test_wp_email',
			'clear_old_notifications',
			'repair_tables',
			'bulk_update_settings',
			'export_settings',
			'import_settings',
			'get_stats',
			'run_diagnostics'
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
		
		// Store the hook for asset loading
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
		
		$this->admin_hooks[] = add_submenu_page(
			'bpfn-settings',
			__( 'Help', 'bp-fav-notification' ),
			__( 'Help', 'bp-fav-notification' ),
			'manage_options',
			'bpfn-help',
			array( $this, 'help_page' )
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
				'testing' => __( 'Sending test notification...', 'bp-fav-notification' ),
				'test_success' => __( 'Test notification sent successfully!', 'bp-fav-notification' ),
				'test_error' => __( 'Failed to send test notification.', 'bp-fav-notification' ),
				'confirm_clear' => __( 'Are you sure you want to clear old notifications?', 'bp-fav-notification' ),
				'clearing' => __( 'Clearing...', 'bp-fav-notification' ),
				'clear_success' => __( 'Notifications cleared successfully.', 'bp-fav-notification' ),
				'clear_error' => __( 'Failed to clear notifications.', 'bp-fav-notification' ),
			),
		) );
		
		// Add Chart.js for stats if on settings page
		if ( isset( $_GET['page'] ) && $_GET['page'] === 'bpfn-settings' ) {
			wp_enqueue_script(
				'chart-js',
				'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
				array(),
				'3.9.1',
				true
			);
		}
		
		// Add color picker if needed
		if ( isset( $_GET['page'] ) && $_GET['page'] === 'bpfn-settings' ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-color-picker' );
		}
		
		// Allow additional admin assets
		do_action( 'bpfn_enqueue_admin_assets', $hook );
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
		if ( ! function_exists( 'bpfn_get_notification_stats' ) ) {
			echo '<p>' . __( 'Stats not available', 'bp-fav-notification' ) . '</p>';
			return;
		}
		
		$stats = bpfn_get_notification_stats( 'all' );
		?>
		<ul class="bpfn-stats">
			<li>
				<span class="bpfn-stat-value"><?php echo number_format_i18n( $stats['total_notifications'] ); ?></span>
				<span class="bpfn-stat-label"><?php _e( 'Total Notifications', 'bp-fav-notification' ); ?></span>
			</li>
			<li>
				<span class="bpfn-stat-value"><?php echo number_format_i18n( $stats['active_users'] ); ?></span>
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
			<h3><?php _e( 'Test Email Template', 'bp-fav-notification' ); ?></h3>
			<p><?php _e( 'Send a test email to preview the email template.', 'bp-fav-notification' ); ?></p>
			<select id="bpfn-test-email-type">
				<option value="activity_favorited"><?php _e( 'Activity Favorited', 'bp-fav-notification' ); ?></option>
				<option value="comment_favorited"><?php _e( 'Comment Favorited', 'bp-fav-notification' ); ?></option>
			</select>
			<button class="button" id="bpfn-send-test-email">
				<?php _e( 'Send Test Email', 'bp-fav-notification' ); ?>
			</button>
		</div>
		
		<div class="bpfn-tool-box">
			<h3><?php _e( 'Database Maintenance', 'bp-fav-notification' ); ?></h3>
			<p><?php _e( 'Check and repair database tables.', 'bp-fav-notification' ); ?></p>
			<?php
			if ( function_exists( 'bpfn_check_tables' ) ) {
				$tables = bpfn_check_tables();
				foreach ( $tables as $table => $exists ) {
					echo '<p>';
					echo ucfirst( $table ) . ' table: ';
					echo $exists ? '<span style="color:green;">✓ OK</span>' : '<span style="color:red;">✗ Missing</span>';
					echo '</p>';
				}
				?>
				<button class="button" id="bpfn-repair-tables" <?php echo ! in_array( false, $tables ) ? 'disabled' : ''; ?>>
					<?php _e( 'Repair Tables', 'bp-fav-notification' ); ?>
				</button>
				<?php
			}
			?>
		</div>
		
		<div class="bpfn-tool-box">
			<h3><?php _e( 'Test WordPress Email', 'bp-fav-notification' ); ?></h3>
			<p><?php _e( 'Send a simple test email to check if WordPress email is working.', 'bp-fav-notification' ); ?></p>
			<button class="button" id="bpfn-test-wp-email">
				<?php _e( 'Send Simple Test Email', 'bp-fav-notification' ); ?>
			</button>
			<div id="bpfn-wp-email-result"></div>
			<script>
			jQuery(document).ready(function($) {
				$('#bpfn-test-wp-email').on('click', function(e) {
					e.preventDefault();
					var $button = $(this);
					var originalText = $button.text();
					var $result = $('#bpfn-wp-email-result');
					
					$button.prop('disabled', true).text('Sending...');
					$result.html('');
					
					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'bpfn_test_wp_email',
							nonce: bpfnAdmin.nonce
						},
						success: function(response) {
							if (response.success) {
								$result.html('<div style="color: green; margin-top: 10px;">' + response.data.message + '</div>');
							} else {
								$result.html('<div style="color: red; margin-top: 10px;">' + response.data.message + '</div>');
							}
						},
						error: function() {
							$result.html('<div style="color: red; margin-top: 10px;">AJAX Error</div>');
						},
						complete: function() {
							$button.prop('disabled', false).text(originalText);
						}
					});
				});
			});
			</script>
		</div>
		
		<div class="bpfn-tool-box">
			<h3><?php _e( 'Test Real-time Notifications', 'bp-fav-notification' ); ?></h3>
			<p><?php _e( 'Test if real-time notifications are working properly.', 'bp-fav-notification' ); ?></p>
			<button class="button" id="bpfn-test-realtime-notification">
				<?php _e( 'Show Test Real-time Notification', 'bp-fav-notification' ); ?>
			</button>
			<div id="bpfn-realtime-test-result"></div>
			<script>
			jQuery(document).ready(function($) {
				$('#bpfn-test-realtime-notification').on('click', function(e) {
					e.preventDefault();
					
					// Check if realtime module is loaded
					if (typeof BPFN !== 'undefined' && BPFN.Realtime) {
						// Show a test notification
						BPFN.Realtime.showTestNotification();
						$('#bpfn-realtime-test-result').html('<div style="color: green; margin-top: 10px;">Test notification triggered. Check bottom-right corner of your screen.</div>');
					} else {
						$('#bpfn-realtime-test-result').html('<div style="color: red; margin-top: 10px;">Real-time module not loaded. Make sure you have real-time notifications enabled in your user settings.</div>');
					}
				});
			});
			</script>
		</div>
		
		<div class="bpfn-tool-box">
			<h3><?php _e( 'Clear Old Notifications', 'bp-fav-notification' ); ?></h3>
			<p><?php _e( 'Remove read notifications older than 30 days.', 'bp-fav-notification' ); ?></p>
			<button class="button" id="bpfn-clear-old-notifications">
				<?php _e( 'Clear Old Notifications', 'bp-fav-notification' ); ?>
			</button>
		</div>
		
		<div class="bpfn-tool-box">
			<h3><?php _e( 'Bulk Settings Update', 'bp-fav-notification' ); ?></h3>
			<p><?php _e( 'Apply default notification settings to all users.', 'bp-fav-notification' ); ?></p>
			<label>
				<input type="checkbox" id="bpfn-bulk-web" checked> <?php _e( 'Web Notifications', 'bp-fav-notification' ); ?>
			</label><br>
			<label>
				<input type="checkbox" id="bpfn-bulk-email" checked> <?php _e( 'Email Notifications', 'bp-fav-notification' ); ?>
			</label><br>
			<label>
				<input type="checkbox" id="bpfn-bulk-realtime" checked> <?php _e( 'Real-time Notifications', 'bp-fav-notification' ); ?>
			</label><br><br>
			<button class="button" id="bpfn-bulk-update">
				<?php _e( 'Update All Users', 'bp-fav-notification' ); ?>
			</button>
		</div>
		
		<div class="bpfn-tool-box">
			<h3><?php _e( 'Export/Import Settings', 'bp-fav-notification' ); ?></h3>
			<p><?php _e( 'Export your plugin settings for backup or migration.', 'bp-fav-notification' ); ?></p>
			<button class="button" id="bpfn-export-settings">
				<?php _e( 'Export Settings', 'bp-fav-notification' ); ?>
			</button>
			<br><br>
			<label for="bpfn-import-file"><?php _e( 'Import Settings:', 'bp-fav-notification' ); ?></label><br>
			<input type="file" id="bpfn-import-settings-file" accept=".json">
		</div>
		
		<div class="bpfn-tool-box">
			<h3><?php _e( 'System Diagnostics', 'bp-fav-notification' ); ?></h3>
			<p><?php _e( 'Run system diagnostics to check plugin health.', 'bp-fav-notification' ); ?></p>
			<button class="button" id="bpfn-run-diagnostics">
				<?php _e( 'Run Diagnostics', 'bp-fav-notification' ); ?>
			</button>
			<div id="bpfn-diagnostics-results"></div>
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
		
		// Email settings section
		add_settings_section(
			'bpfn_email',
			__( 'Email Settings', 'bp-fav-notification' ),
			array( $this, 'section_email' ),
			'bpfn-settings'
		);
		
		// Social links fields
		$social_networks = array( 'facebook', 'twitter', 'instagram', 'linkedin' );
		foreach ( $social_networks as $network ) {
			add_settings_field(
				'social_' . $network,
				ucfirst( $network ) . ' URL',
				array( $this, 'field_text' ),
				'bpfn-settings',
				'bpfn_email',
				array(
					'name' => 'social_links][' . $network,
					'placeholder' => 'https://' . $network . '.com/yourpage',
				)
			);
		}
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
	 * Email section
	 */
	public function section_email() {
		echo '<p>' . __( 'Configure email notification settings and social links for email footers.', 'bp-fav-notification' ) . '</p>';
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
	 * Text field
	 */
	public function field_text( $args ) {
		$options = get_option( 'bpfn_options', array() );
		$name_parts = explode( '][', $args['name'] );
		$value = $options;
		
		foreach ( $name_parts as $part ) {
			$part = trim( $part, ']' );
			$value = isset( $value[ $part ] ) ? $value[ $part ] : '';
		}
		?>
		<input type="text" 
			   name="bpfn_options[<?php echo esc_attr( $args['name'] ); ?>]" 
			   value="<?php echo esc_attr( $value ); ?>"
			   placeholder="<?php echo esc_attr( $args['placeholder'] ?? '' ); ?>"
			   class="regular-text" />
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
		
		// Social links
		if ( isset( $input['social_links'] ) ) {
			$sanitized['social_links'] = array();
			foreach ( $input['social_links'] as $network => $url ) {
				$sanitized['social_links'][ $network ] = esc_url_raw( $url );
			}
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
	 * Maybe show review notice
	 */
	public function maybe_show_review_notice() {
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
		
		// Add notice - content will be generated when displayed
		$this->add_notice( 'review', '', 'info', true );
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
			// Get content for review notice if empty
			if ( $id === 'review' && empty( $notice['message'] ) ) {
				$notice['message'] = $this->get_review_notice_content();
			}
			
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

	/**
	 * AJAX send test notification
	 */
	public function ajax_send_test_notification() {
		check_ajax_referer( 'bpfn-admin-nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'bp-fav-notification' ) ) );
		}
		
		if ( ! function_exists( 'bpfn_send_test_notification' ) ) {
			wp_send_json_error( array( 'message' => __( 'Test function not available', 'bp-fav-notification' ) ) );
		}
		
		$user_id = get_current_user_id();
		$result = bpfn_send_test_notification( $user_id );
		
		if ( $result ) {
			$user = get_userdata( $user_id );
			$email_enabled = bpfn_is_notification_enabled( $user_id, 'activity_post', 'email' );
			
			$details = array(
				'web' => true,
				'email' => $email_enabled ? $user->user_email : false,
				'realtime' => bpfn_is_notification_enabled( $user_id, 'activity_post', 'realtime' ),
			);
			
			wp_send_json_success( array( 
				'message' => __( 'Test notification sent!', 'bp-fav-notification' ),
				'details' => $details,
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to send test notification', 'bp-fav-notification' ) ) );
		}
	}

	/**
	 * AJAX send test email
	 */
	public function ajax_send_test_email() {
		check_ajax_referer( 'bpfn-admin-nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'bp-fav-notification' ) ) );
		}
		
		if ( ! function_exists( 'bpfn_send_test_email' ) ) {
			wp_send_json_error( array( 'message' => __( 'Test function not available', 'bp-fav-notification' ) ) );
		}
		
		$type = sanitize_text_field( $_POST['type'] ?? 'activity_favorited' );
		$user_id = get_current_user_id();
		
		$result = bpfn_send_test_email( $user_id, $type );
		
		if ( $result ) {
			wp_send_json_success( array( 
				'message' => sprintf( __( 'Test email sent to %s', 'bp-fav-notification' ), wp_get_current_user()->user_email ),
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to send test email', 'bp-fav-notification' ) ) );
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
		
		global $wpdb, $bp;
		
		if ( ! bp_is_active( 'notifications' ) ) {
			wp_send_json_error( array( 'message' => __( 'Notifications component is not active', 'bp-fav-notification' ) ) );
		}
		
		$table = $bp->notifications->table_name;
		$component = isset( $bp->favorite_notifier ) ? $bp->favorite_notifier->id : 'favorite_notifier';
		
		// Delete notifications older than 30 days that are read
		$deleted = $wpdb->query( $wpdb->prepare(
			"DELETE FROM {$table} 
			WHERE component_name = %s 
			AND is_new = 0 
			AND date_notified < DATE_SUB(NOW(), INTERVAL 30 DAY)",
			$component
		) );
		
		// Get remaining count
		$remaining = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE component_name = %s",
			$component
		) );
		
		wp_send_json_success( array(
			'count' => $deleted,
			'remaining' => $remaining,
			'message' => sprintf( __( 'Cleared %d old notifications', 'bp-fav-notification' ), $deleted ),
		) );
	}

	/**
	 * AJAX repair tables
	 */
	public function ajax_repair_tables() {
		check_ajax_referer( 'bpfn-admin-nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'bp-fav-notification' ) ) );
		}
		
		if ( ! function_exists( 'bpfn_repair_tables' ) ) {
			wp_send_json_error( array( 'message' => __( 'Repair function not available', 'bp-fav-notification' ) ) );
		}
		
		$result = bpfn_repair_tables();
		
		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Tables repaired successfully', 'bp-fav-notification' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to repair tables', 'bp-fav-notification' ) ) );
		}
	}

	/**
	 * AJAX bulk update settings
	 */
	public function ajax_bulk_update_settings() {
		check_ajax_referer( 'bpfn-admin-nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'bp-fav-notification' ) ) );
		}
		
		if ( ! function_exists( 'bpfn_bulk_update_settings' ) ) {
			wp_send_json_error( array( 'message' => __( 'Bulk update function not available', 'bp-fav-notification' ) ) );
		}
		
		$settings = array(
			'activity_post' => array(
				'is_enabled' => ! empty( $_POST['web'] ),
				'email_enabled' => ! empty( $_POST['email'] ),
				'realtime_enabled' => ! empty( $_POST['realtime'] ),
			),
			'activity_comment' => array(
				'is_enabled' => ! empty( $_POST['web'] ),
				'email_enabled' => ! empty( $_POST['email'] ),
				'realtime_enabled' => ! empty( $_POST['realtime'] ),
			),
		);
		
		$updated = bpfn_bulk_update_settings( $settings );
		
		wp_send_json_success( array(
			'count' => $updated,
			'message' => sprintf( __( 'Updated settings for %d users', 'bp-fav-notification' ), $updated ),
		) );
	}

	/**
	 * AJAX export settings
	 */
	public function ajax_export_settings() {
		check_ajax_referer( 'bpfn-admin-nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'bp-fav-notification' ) ) );
		}
		
		$export_data = array(
			'version' => BPFN_VERSION,
			'timestamp' => current_time( 'mysql' ),
			'site_url' => home_url(),
			'options' => get_option( 'bpfn_options', array() ),
		);
		
		if ( function_exists( 'bpfn_get_notification_stats' ) ) {
			$export_data['stats'] = bpfn_get_notification_stats( 'all' );
		}
		
		wp_send_json_success( $export_data );
	}

	/**
	 * AJAX import settings
	 */
	public function ajax_import_settings() {
		check_ajax_referer( 'bpfn-admin-nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'bp-fav-notification' ) ) );
		}
		
		$settings = json_decode( stripslashes( $_POST['settings'] ), true );
		
		if ( ! is_array( $settings ) || ! isset( $settings['options'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid settings format', 'bp-fav-notification' ) ) );
		}
		
		// Update options
		update_option( 'bpfn_options', $settings['options'] );
		
		wp_send_json_success( array( 'message' => __( 'Settings imported successfully', 'bp-fav-notification' ) ) );
	}

	/**
	 * AJAX get stats
	 */
	public function ajax_get_stats() {
		check_ajax_referer( 'bpfn-admin-nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'bp-fav-notification' ) ) );
		}
		
		$response = array();
		
		if ( function_exists( 'bpfn_get_notification_stats' ) ) {
			$period = sanitize_text_field( $_POST['period'] ?? 'week' );
			$stats = bpfn_get_notification_stats( $period );
			$response['stats'] = $stats;
			$response['total_notifications'] = $stats['total_notifications'];
			$response['active_users'] = $stats['active_users'];
		}
		
		if ( function_exists( 'bpfn_get_chart_data' ) ) {
			$chart = bpfn_get_chart_data( 7 );
			$response['chart'] = $chart;
		}
		
		wp_send_json_success( $response );
	}

	/**
	 * AJAX run diagnostics
	 */
	public function ajax_run_diagnostics() {
		check_ajax_referer( 'bpfn-admin-nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'bp-fav-notification' ) ) );
		}
		
		if ( ! function_exists( 'bpfn_get_diagnostics' ) ) {
			wp_send_json_error( array( 'message' => __( 'Diagnostics function not available', 'bp-fav-notification' ) ) );
		}
		
		$diagnostics = bpfn_get_diagnostics();
		
		// Format for display
		$formatted = array(
			'php_version' => $diagnostics['environment']['php_version'] ?? 'Unknown',
			'wp_version' => $diagnostics['environment']['wp_version'] ?? 'Unknown',
			'bp_version' => $diagnostics['environment']['bp_version'] ?? 'Unknown',
			'plugin_version' => $diagnostics['environment']['plugin_version'] ?? 'Unknown',
			'tables' => $diagnostics['tables'] ?? array(),
			'stats' => $diagnostics['stats'] ?? array(),
			'components' => $diagnostics['components'] ?? array(),
		);
		
		wp_send_json_success( $formatted );
	}

	/**
	 * AJAX test WordPress email
	 */
	public function ajax_test_wp_email() {
		check_ajax_referer( 'bpfn-admin-nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'bp-fav-notification' ) ) );
		}
		
		$user = wp_get_current_user();
		$to = $user->user_email;
		$subject = 'WordPress Email Test - ' . get_bloginfo( 'name' );
		$message = 'This is a test email sent at ' . current_time( 'mysql' ) . ' to verify WordPress email functionality.';
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		
		error_log( 'BPFN: Testing wp_mail to ' . $to );
		$sent = wp_mail( $to, $subject, $message, $headers );
		error_log( 'BPFN: wp_mail result: ' . ( $sent ? 'true' : 'false' ) );
		
		if ( $sent ) {
			wp_send_json_success( array( 
				'message' => sprintf( __( 'Test email sent to %s. Check your inbox (and spam folder).', 'bp-fav-notification' ), $to ),
			) );
		} else {
			wp_send_json_error( array( 
				'message' => __( 'Failed to send email. WordPress wp_mail() returned false. This means email is not configured on your site.', 'bp-fav-notification' ) 
			) );
		}
	}



	/**
	 * Enhanced Admin Tools for BuddyPress Favorite Notification
	 * Complete replacement for enhanced admin functionality
	 * Add these methods to your BPFN_Module_Admin class
	 */

	public function render_enhanced_tools() {
		$realtime_module = bpfn()->get_module('realtime');
		$system_status = $realtime_module ? $realtime_module->get_system_status() : null;
		?>
		
		<!-- Real-time System Diagnostics -->
		<div class="bpfn-tool-box">
			<h3>🔍 Real-time System Diagnostics</h3>
			<p>Comprehensive analysis of your real-time notification system.</p>
			
			<?php if ($system_status): ?>
				<div class="bpfn-diagnostics-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0;">
					
					<!-- System Status -->
					<div>
						<h4>System Status</h4>
						<table class="widefat striped">
							<tr>
								<td><strong>Heartbeat Available:</strong></td>
								<td><?php echo $system_status['heartbeat_available'] ? 
									'<span style="color: green;">✅ Available</span>' : 
									'<span style="color: red;">❌ Unavailable</span>'; ?></td>
							</tr>
							<tr>
								<td><strong>SSE Supported:</strong></td>
								<td><?php echo $system_status['sse_supported'] ? 
									'<span style="color: green;">✅ Supported</span>' : 
									'<span style="color: orange;">⚠️ Limited</span>'; ?></td>
							</tr>
							<tr>
								<td><strong>Preferred Method:</strong></td>
								<td><strong><?php echo ucfirst($system_status['preferred_method']); ?></strong></td>
							</tr>
							<tr>
								<td><strong>Server Software:</strong></td>
								<td><?php echo esc_html($system_status['server_info']['server_software']); ?></td>
							</tr>
						</table>
					</div>
					
					<!-- Performance Impact -->
					<div>
						<h4>Performance Analysis</h4>
						<table class="widefat striped">
							<tr>
								<td><strong>PHP Version:</strong></td>
								<td><?php echo $system_status['server_info']['php_version']; ?>
									<?php if (version_compare($system_status['server_info']['php_version'], '8.0', '>=')): ?>
										<span style="color: green;">✅</span>
									<?php else: ?>
										<span style="color: orange;">⚠️</span>
									<?php endif; ?>
								</td>
							</tr>
							<tr>
								<td><strong>Max Execution Time:</strong></td>
								<td><?php 
									$max_exec = $system_status['server_info']['max_execution_time'];
									echo $max_exec . 's';
									if ($max_exec >= 300) {
										echo ' <span style="color: green;">✅</span>';
									} elseif ($max_exec >= 60) {
										echo ' <span style="color: orange;">⚠️</span>';
									} else {
										echo ' <span style="color: red;">❌</span>';
									}
								?></td>
							</tr>
							<tr>
								<td><strong>Output Buffering:</strong></td>
								<td><?php 
									$ob = $system_status['server_info']['output_buffering'];
									echo $ob ? $ob : 'Off';
									echo $ob ? ' <span style="color: orange;">⚠️</span>' : ' <span style="color: green;">✅</span>';
								?></td>
							</tr>
							<tr>
								<td><strong>Performance Plugins:</strong></td>
								<td><?php 
									if (empty($system_status['active_performance_plugins'])) {
										echo '<span style="color: green;">None detected ✅</span>';
									} else {
										echo '<span style="color: orange;">' . 
											implode(', ', $system_status['active_performance_plugins']) . 
											' ⚠️</span>';
									}
								?></td>
							</tr>
						</table>
					</div>
				</div>
				
				<!-- Method-specific Status -->
				<div style="margin: 20px 0;">
					<h4>Notification Methods</h4>
					<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
						
						<?php foreach ($system_status['supported_methods'] as $method => $supported): ?>
							<div class="method-status" style="padding: 15px; border: 1px solid #ddd; border-radius: 5px; text-align: center;">
								<h5 style="margin: 0 0 10px;"><?php echo ucfirst($method); ?></h5>
								<?php if ($supported): ?>
									<span style="font-size: 24px; color: green;">✅</span>
									<p style="margin: 5px 0; color: green;">Available</p>
								<?php else: ?>
									<span style="font-size: 24px; color: #ccc;">⭕</span>
									<p style="margin: 5px 0; color: #666;">Not Available</p>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
				
				<!-- Testing Tools -->
				<div style="margin: 20px 0;">
					<h4>Testing Tools</h4>
					<div class="button-group">
						<button class="button button-primary" id="test-all-methods">🧪 Test All Methods</button>
						<button class="button" id="test-heartbeat-only">💓 Test Heartbeat</button>
						<button class="button" id="test-sse-only">📡 Test SSE</button>
						<button class="button" id="test-polling-only">🔄 Test Polling</button>
						<button class="button" id="force-polling-mode">⚙️ Force Polling Mode</button>
					</div>
				</div>
				
				<!-- Live Test Results -->
				<div id="realtime-test-results" style="margin-top: 20px; display: none;">
					<h4>Test Results</h4>
					<div id="test-output" style="background: #f5f5f5; padding: 15px; border-radius: 5px; font-family: monospace; max-height: 300px; overflow-y: auto;"></div>
				</div>
				
			<?php else: ?>
				<div style="color: red; padding: 20px; text-align: center;">
					<h4>❌ Real-time Module Not Available</h4>
					<p>The real-time notifications module could not be loaded. Please check your plugin installation.</p>
				</div>
			<?php endif; ?>
		</div>
		
		<!-- Connection Monitor -->
		<div class="bpfn-tool-box">
			<h3>📊 Live Connection Monitor</h3>
			<p>Monitor real-time notification performance in real-time.</p>
			
			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin: 20px 0;">
				<div class="stat-box" style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 5px;">
					<div style="font-size: 24px; font-weight: bold; color: #007cba;" id="connection-status">Disconnected</div>
					<div style="font-size: 12px; color: #666;">Connection Status</div>
				</div>
				<div class="stat-box" style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 5px;">
					<div style="font-size: 24px; font-weight: bold; color: #00a32a;" id="success-count">0</div>
					<div style="font-size: 12px; color: #666;">Successful Requests</div>
				</div>
				<div class="stat-box" style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 5px;">
					<div style="font-size: 24px; font-weight: bold; color: #d63638;" id="error-count">0</div>
					<div style="font-size: 12px; color: #666;">Failed Requests</div>
				</div>
				<div class="stat-box" style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 5px;">
					<div style="font-size: 24px; font-weight: bold; color: #ff7b00;" id="avg-response">0ms</div>
					<div style="font-size: 12px; color: #666;">Avg Response Time</div>
				</div>
			</div>
			
			<div style="margin: 20px 0;">
				<button class="button button-primary" id="start-monitoring">📈 Start Monitoring</button>
				<button class="button" id="stop-monitoring">⏹️ Stop Monitoring</button>
				<button class="button" id="clear-monitor">🗑️ Clear Data</button>
			</div>
			
			<div id="monitoring-log" style="background: #fff; border: 1px solid #ddd; padding: 15px; max-height: 200px; overflow-y: auto; font-family: monospace; font-size: 12px; display: none;"></div>
		</div>
		
		<!-- Configuration Optimizer -->
		<div class="bpfn-tool-box">
			<h3>⚙️ Configuration Optimizer</h3>
			<p>Automatically optimize settings based on your server environment.</p>
			
			<div id="optimization-results" style="margin: 20px 0;">
				<h4>Current Configuration</h4>
				<table class="widefat striped">
					<tr>
						<td><strong>Check Interval:</strong></td>
						<td><?php echo get_option('bpfn_options')['realtime_interval'] ?? 15; ?> seconds</td>
					</tr>
					<tr>
						<td><strong>Max Notifications:</strong></td>
						<td>5</td>
					</tr>
					<tr>
						<td><strong>Auto Dismiss:</strong></td>
						<td>5 seconds</td>
					</tr>
				</table>
			</div>
			
			<button class="button button-primary" id="optimize-config">🎯 Auto-Optimize Configuration</button>
			<button class="button" id="reset-config">🔄 Reset to Defaults</button>
			
			<div id="optimization-suggestions" style="margin-top: 20px; display: none;"></div>
		</div>
		
		<!-- Troubleshooting Guide -->
		<div class="bpfn-tool-box">
			<h3>🛠️ Troubleshooting Assistant</h3>
			<p>Get personalized help based on your specific configuration.</p>
			
			<div class="troubleshooting-steps" style="margin: 20px 0;">
				<?php if (!empty($system_status['active_performance_plugins'])): ?>
					<div class="notice notice-warning" style="margin: 10px 0;">
						<p><strong>Performance Plugin Detected:</strong> 
						<?php echo implode(', ', $system_status['active_performance_plugins']); ?> may interfere with real-time notifications.</p>
						<button class="button" onclick="showPerformancePluginHelp()">Show Solutions</button>
					</div>
				<?php endif; ?>
				
				<?php if (!$system_status['heartbeat_available']): ?>
					<div class="notice notice-error" style="margin: 10px 0;">
						<p><strong>Heartbeat Unavailable:</strong> WordPress Heartbeat API is not working.</p>
						<button class="button" onclick="showHeartbeatHelp()">Show Solutions</button>
					</div>
				<?php endif; ?>
				
				<?php if (!$system_status['sse_supported']): ?>
					<div class="notice notice-info" style="margin: 10px 0;">
						<p><strong>SSE Limited:</strong> Server-Sent Events have limited support on your server.</p>
						<button class="button" onclick="showSSEHelp()">Show Solutions</button>
					</div>
				<?php endif; ?>
			</div>
			
			<button class="button button-primary" id="run-full-diagnostic">🔍 Run Full Diagnostic</button>
			<button class="button" id="generate-support-info">📋 Generate Support Info</button>
			
			<div id="diagnostic-output" style="margin-top: 20px; display: none;"></div>
		</div>
		
		<script>
		jQuery(document).ready(function($) {
			// Enhanced testing functionality
			var testMonitor = {
				stats: {
					connected: false,
					successCount: 0,
					errorCount: 0,
					responseTimes: [],
					isMonitoring: false
				},
				
				updateStats: function() {
					$('#connection-status').text(this.stats.connected ? 'Connected' : 'Disconnected');
					$('#success-count').text(this.stats.successCount);
					$('#error-count').text(this.stats.errorCount);
					
					if (this.stats.responseTimes.length > 0) {
						var avg = this.stats.responseTimes.reduce((a, b) => a + b, 0) / this.stats.responseTimes.length;
						$('#avg-response').text(Math.round(avg) + 'ms');
					}
				},
				
				log: function(message, type = 'info') {
					var timestamp = new Date().toLocaleTimeString();
					var color = type === 'error' ? 'red' : type === 'success' ? 'green' : 'black';
					$('#monitoring-log').append(
						'<div style="color: ' + color + '">[' + timestamp + '] ' + message + '</div>'
					).scrollTop($('#monitoring-log')[0].scrollHeight);
				}
			};
			
			// Test all methods
			$('#test-all-methods').on('click', function() {
				var $button = $(this);
				var $output = $('#test-output');
				var $results = $('#realtime-test-results');
				
				$button.prop('disabled', true).text('🧪 Testing...');
				$results.show();
				$output.html('<div>Starting comprehensive test suite...</div>');
				
				// Test sequence
				Promise.resolve()
					.then(() => testHeartbeat($output))
					.then(() => testSSE($output))
					.then(() => testPolling($output))
					.then(() => {
						$output.append('<div style="color: green; font-weight: bold; margin-top: 20px;">✅ All tests completed!</div>');
					})
					.catch(error => {
						$output.append('<div style="color: red; font-weight: bold; margin-top: 20px;">❌ Test suite failed: ' + error + '</div>');
					})
					.finally(() => {
						$button.prop('disabled', false).text('🧪 Test All Methods');
					});
			});
			
			// Individual test functions
			function testHeartbeat($output) {
				return new Promise((resolve, reject) => {
					$output.append('<div><strong>Testing WordPress Heartbeat...</strong></div>');
					
					if (typeof wp === 'undefined' || !wp.heartbeat) {
						$output.append('<div style="color: red;">❌ WordPress Heartbeat not available</div>');
						resolve();
						return;
					}
					
					var timeout = setTimeout(() => {
						$output.append('<div style="color: red;">❌ Heartbeat test timeout</div>');
						$(document).off('.bpfn-test');
						resolve();
					}, 10000);
					
					$(document).on('heartbeat-send.bpfn-test', function(e, data) {
						data.bpfn_test = { timestamp: Date.now() };
						$output.append('<div style="color: blue;">🔵 Heartbeat test sent</div>');
					});
					
					$(document).on('heartbeat-tick.bpfn-test', function(e, data) {
						if (data.bpfn_test_response) {
							clearTimeout(timeout);
							$output.append('<div style="color: green;">✅ Heartbeat working! Response time: ' + 
										(Date.now() - data.bpfn_test_response.timestamp) + 'ms</div>');
							$(document).off('.bpfn-test');
							resolve();
						}
					});
					
					$(document).on('heartbeat-error.bpfn-test', function() {
						clearTimeout(timeout);
						$output.append('<div style="color: red;">❌ Heartbeat error occurred</div>');
						$(document).off('.bpfn-test');
						resolve();
					});
					
					wp.heartbeat.connectNow();
				});
			}
			
			function testSSE($output) {
				return new Promise((resolve) => {
					$output.append('<div><strong>Testing Server-Sent Events...</strong></div>');
					
					if (!window.EventSource) {
						$output.append('<div style="color: red;">❌ SSE not supported by browser</div>');
						resolve();
						return;
					}
					
					var testUrl = ajaxurl + '?action=bpfn_sse_test&nonce=' + encodeURIComponent(bpfnAdmin.nonce);
					var eventSource = new EventSource(testUrl);
					var startTime = Date.now();
					
					var timeout = setTimeout(() => {
						eventSource.close();
						$output.append('<div style="color: red;">❌ SSE test timeout</div>');
						resolve();
					}, 15000);
					
					eventSource.onopen = function() {
						$output.append('<div style="color: green;">✅ SSE connection opened</div>');
					};
					
					eventSource.onmessage = function(event) {
						var data = JSON.parse(event.data);
						if (data.message) {
							$output.append('<div style="color: blue;">📨 ' + data.message + '</div>');
						}
					};
					
					eventSource.addEventListener('close', function(event) {
						clearTimeout(timeout);
						var data = JSON.parse(event.data);
						var duration = Date.now() - startTime;
						$output.append('<div style="color: green;">✅ SSE test completed (' + duration + 'ms)</div>');
						eventSource.close();
						resolve();
					});
					
					eventSource.onerror = function(error) {
						clearTimeout(timeout);
						$output.append('<div style="color: red;">❌ SSE connection error</div>');
						eventSource.close();
						resolve();
					};
				});
			}
			
			function testPolling($output) {
				return new Promise((resolve) => {
					$output.append('<div><strong>Testing AJAX Polling...</strong></div>');
					
					var startTime = Date.now();
					
					$.ajax({
						url: ajaxurl,
						type: 'POST',
						timeout: 10000,
						data: {
							action: 'bpfn_check_notifications',
							last_checked: Math.floor(Date.now() / 1000),
							nonce: bpfnAdmin.nonce
						},
						success: function(response) {
							var responseTime = Date.now() - startTime;
							if (response.success) {
								$output.append('<div style="color: green;">✅ Polling successful (' + responseTime + 'ms)</div>');
								$output.append('<div style="color: blue;">📊 Found ' + response.data.notifications.length + ' notifications</div>');
							} else {
								$output.append('<div style="color: orange;">⚠️ Polling returned error: ' + (response.data?.message || 'Unknown error') + '</div>');
							}
							resolve();
						},
						error: function(xhr, status, error) {
							$output.append('<div style="color: red;">❌ Polling failed: ' + error + '</div>');
							resolve();
						}
					});
				});
			}
			
			// Start monitoring
			$('#start-monitoring').on('click', function() {
				testMonitor.stats.isMonitoring = true;
				$('#monitoring-log').show();
				testMonitor.log('Monitoring started', 'success');
				
				function monitor() {
					if (!testMonitor.stats.isMonitoring) return;
					
					var startTime = Date.now();
					testMonitor.stats.connected = false;
					testMonitor.updateStats();
					
					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'bpfn_check_notifications',
							last_checked: Math.floor(Date.now() / 1000),
							nonce: bpfnAdmin.nonce
						},
						success: function(response) {
							var responseTime = Date.now() - startTime;
							testMonitor.stats.connected = true;
							testMonitor.stats.successCount++;
							testMonitor.stats.responseTimes.push(responseTime);
							
							// Keep only last 10 response times
							if (testMonitor.stats.responseTimes.length > 10) {
								testMonitor.stats.responseTimes.shift();
							}
							
							testMonitor.updateStats();
							testMonitor.log('Success (' + responseTime + 'ms)', 'success');
							
							setTimeout(monitor, 5000);
						},
						error: function(xhr, status, error) {
							testMonitor.stats.connected = false;
							testMonitor.stats.errorCount++;
							testMonitor.updateStats();
							testMonitor.log('Error: ' + error, 'error');
							
							setTimeout(monitor, 5000);
						}
					});
				}
				
				monitor();
			});
			
			$('#stop-monitoring').on('click', function() {
				testMonitor.stats.isMonitoring = false;
				testMonitor.log('Monitoring stopped', 'info');
			});
			
			$('#clear-monitor').on('click', function() {
				testMonitor.stats = {
					connected: false,
					successCount: 0,
					errorCount: 0,
					responseTimes: [],
					isMonitoring: false
				};
				testMonitor.updateStats();
				$('#monitoring-log').empty();
			});
			
			// Configuration optimizer
			$('#optimize-config').on('click', function() {
				var $button = $(this);
				$button.prop('disabled', true).text('⚙️ Optimizing...');
				
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'bpfn_optimize_config',
						nonce: bpfnAdmin.nonce
					},
					success: function(response) {
						if (response.success) {
							$('#optimization-suggestions').html(response.data.suggestions).show();
						}
					},
					complete: function() {
						$button.prop('disabled', false).text('🎯 Auto-Optimize Configuration');
					}
				});
			});
			
			// Troubleshooting helpers
			window.showPerformancePluginHelp = function() {
				alert('Performance Plugin Solutions:\n\n1. WP Rocket: Enable "Load JavaScript deferred" exception for heartbeat\n2. W3 Total Cache: Exclude heartbeat from page cache\n3. Heartbeat Control: Allow heartbeat on user pages\n\nFor detailed instructions, visit the Help page.');
			};
			
			window.showHeartbeatHelp = function() {
				alert('Heartbeat Solutions:\n\n1. Check if Heartbeat Control plugin is disabling it\n2. Verify performance plugins are not blocking heartbeat\n3. Contact your hosting provider about server limitations\n4. Enable fallback polling mode\n\nReal-time notifications will work with polling as backup.');
			};
			
			window.showSSEHelp = function() {
				alert('SSE Solutions:\n\n1. Disable output buffering in PHP settings\n2. Configure web server for SSE support\n3. Check if hosting provider supports long-running connections\n4. Verify firewall is not blocking connections\n\nPolling will be used as fallback.');
			};
		});
		</script>
		
		<?php
	}

	/**
	 * Add AJAX handlers for the new functionality
	 */
	public function ajax_optimize_config() {
		check_ajax_referer('bpfn-admin-nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => 'Insufficient permissions'));
		}
		
		$realtime_module = bpfn()->get_module('realtime');
		if (!$realtime_module) {
			wp_send_json_error(array('message' => 'Realtime module not available'));
		}
		
		$system_status = $realtime_module->get_system_status();
		$suggestions = array();
		
		// Generate optimization suggestions
		if (!$system_status['heartbeat_available']) {
			$suggestions[] = '<div class="notice notice-warning"><p><strong>Recommendation:</strong> Increase polling interval to 30 seconds to reduce server load since Heartbeat is unavailable.</p></div>';
		}
		
		if (!empty($system_status['active_performance_plugins'])) {
			$suggestions[] = '<div class="notice notice-info"><p><strong>Recommendation:</strong> Consider configuring ' . implode(', ', $system_status['active_performance_plugins']) . ' to allow Heartbeat for better performance.</p></div>';
		}
		
		if ($system_status['server_info']['max_execution_time'] < 60) {
			$suggestions[] = '<div class="notice notice-warning"><p><strong>Recommendation:</strong> Reduce max notifications to 3 due to limited execution time.</p></div>';
		}
		
		if (empty($suggestions)) {
			$suggestions[] = '<div class="notice notice-success"><p><strong>Great!</strong> Your configuration appears to be optimized for your server environment.</p></div>';
		}
		
		wp_send_json_success(array(
			'suggestions' => implode('', $suggestions)
		));
	}

	/**
	 * Modified tools page to include enhanced tools
	 */
	public function tools_page() {
		?>
		<div class="wrap">
			<h1><?php _e( 'BuddyPress Favorite Notification Tools', 'bp-fav-notification' ); ?></h1>
			
			<div class="bpfn-tools-container">
				<?php $this->render_enhanced_tools(); ?>
				<?php $this->render_original_tools(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render original tools (existing functionality)
	 */
	private function render_original_tools() {
		?>
		<!-- Test WordPress Email -->
		<div class="bpfn-tool-box">
			<h3><?php _e( 'Test WordPress Email', 'bp-fav-notification' ); ?></h3>
			<p><?php _e( 'Send a simple test email to check if WordPress email is working.', 'bp-fav-notification' ); ?></p>
			<button class="button" id="bpfn-test-wp-email">
				<?php _e( 'Send Simple Test Email', 'bp-fav-notification' ); ?>
			</button>
			<div id="bpfn-wp-email-result"></div>
		</div>
		
		<!-- Clear Old Notifications -->
		<div class="bpfn-tool-box">
			<h3><?php _e( 'Clear Old Notifications', 'bp-fav-notification' ); ?></h3>
			<p><?php _e( 'Remove read notifications older than 30 days.', 'bp-fav-notification' ); ?></p>
			<button class="button" id="bpfn-clear-old-notifications">
				<?php _e( 'Clear Old Notifications', 'bp-fav-notification' ); ?>
			</button>
		</div>
		
		<!-- Database Maintenance -->
		<div class="bpfn-tool-box">
			<h3><?php _e( 'Database Maintenance', 'bp-fav-notification' ); ?></h3>
			<p><?php _e( 'Check and repair database tables.', 'bp-fav-notification' ); ?></p>
			<?php
			if ( function_exists( 'bpfn_check_tables' ) ) {
				$tables = bpfn_check_tables();
				foreach ( $tables as $table => $exists ) {
					echo '<p>';
					echo ucfirst( $table ) . ' table: ';
					echo $exists ? '<span style="color:green;">✓ OK</span>' : '<span style="color:red;">✗ Missing</span>';
					echo '</p>';
				}
				?>
				<button class="button" id="bpfn-repair-tables" <?php echo ! in_array( false, $tables ) ? 'disabled' : ''; ?>>
					<?php _e( 'Repair Tables', 'bp-fav-notification' ); ?>
				</button>
				<?php
			}
			?>
		</div>
		
		<!-- Export/Import Settings -->
		<div class="bpfn-tool-box">
			<h3><?php _e( 'Export/Import Settings', 'bp-fav-notification' ); ?></h3>
			<p><?php _e( 'Export your plugin settings for backup or migration.', 'bp-fav-notification' ); ?></p>
			<button class="button" id="bpfn-export-settings">
				<?php _e( 'Export Settings', 'bp-fav-notification' ); ?>
			</button>
			<br><br>
			<label for="bpfn-import-file"><?php _e( 'Import Settings:', 'bp-fav-notification' ); ?></label><br>
			<input type="file" id="bpfn-import-settings-file" accept=".json">
		</div>
		<?php
	}
}