<?php
/**
 * Debug Module for BuddyPress Favorite Notification
 *
 * @package BuddyPress_Favorite_Notification
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Debug Module Class
 */
class BPFN_Module_Debug {

	/**
	 * Debug mode enabled
	 */
	private $debug_enabled = false;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Check if debug mode is enabled
		$this->debug_enabled = defined( 'WP_DEBUG' ) && WP_DEBUG;
		
		// Always apply fixes, regardless of debug mode
		$this->apply_fixes();
		
		// Setup debug hooks if debug mode is enabled
		if ( $this->debug_enabled ) {
			$this->setup_debug_hooks();
		}
	}

	/**
	 * Apply fixes for notification issues
	 */
	private function apply_fixes() {
		// Fix 1: Ensure proper activity type detection
		add_filter( 'bpfn_notification_action_prefix', array( $this, 'fix_notification_action_prefix' ), 10, 2 );
		
		// Fix 2: Ensure proper user display names
		add_filter( 'bp_core_get_user_displayname', array( $this, 'fix_user_displayname' ), 10, 2 );
		
		// Fix 3: Fix notification text
		add_filter( 'bpfn_notification_text', array( $this, 'fix_notification_text' ), 10, 5 );
		
		// Fix 4: Fix notification array data
		add_filter( 'bpfn_notification_array', array( $this, 'fix_notification_array' ), 10, 3 );
		
		// Fix 5: Override notification display in BuddyPress loop
		add_action( 'bp_before_member_body', array( $this, 'fix_notification_display' ) );
		
		// Fix 6: Fix AJAX user display names
		add_action( 'wp_ajax_bpfn_get_user_display_name', array( $this, 'ajax_get_user_display_name' ) );
		
		// Fix 7: Ensure component is properly initialized
		add_action( 'bp_init', array( $this, 'ensure_component_initialized' ), 5 );
		
		// Fix 8: Fix favorite button AJAX handling
		add_action( 'wp_ajax_activity_mark_fav', array( $this, 'debug_favorite_action' ), 1 );
		add_action( 'wp_ajax_activity_mark_unfav', array( $this, 'debug_unfavorite_action' ), 1 );
	}

	/**
	 * Setup debug hooks
	 */
	private function setup_debug_hooks() {
		// Enable error reporting for AJAX requests
		add_action( 'init', array( $this, 'enable_ajax_debugging' ) );
		
		// Log all notification events
		add_action( 'bpfn_after_add_notification', array( $this, 'log_notification_added' ), 10, 4 );
		add_action( 'bpfn_after_send_email', array( $this, 'log_email_sent' ), 10, 2 );
		
		// Add debug info to admin bar
		add_action( 'admin_bar_menu', array( $this, 'add_debug_menu' ), 999 );
		
		// Catch fatal errors
		register_shutdown_function( array( $this, 'catch_fatal_errors' ) );
		
		// Add debug panel
		add_action( 'wp_footer', array( $this, 'render_debug_panel' ) );
	}

	/**
	 * Fix notification action prefix
	 */
	public function fix_notification_action_prefix( $prefix, $activity ) {
		if ( $activity->type === 'activity_update' ) {
			return 'fav_notify';
		}
		return $prefix;
	}

	/**
	 * Fix user display name
	 */
	public function fix_user_displayname( $display_name, $user_id ) {
		// Check if display name looks like "User X"
		if ( strpos( $display_name, 'User ' ) === 0 && is_numeric( str_replace( 'User ', '', $display_name ) ) ) {
			// Get the real display name
			$user = get_userdata( $user_id );
			if ( $user ) {
				$real_name = $user->display_name ?: $user->user_login;
				
				$this->log( 'Fixed display name for user ' . $user_id . ': ' . $display_name . ' -> ' . $real_name );
				
				return $real_name;
			}
		}
		return $display_name;
	}

	/**
	 * Fix notification text
	 */
	public function fix_notification_text( $text, $item_id, $secondary_item_id, $total_items, $type ) {
		$this->log( 'Fixing notification text - Original: ' . $text );
		
		// Get the actual user name
		$user_name = bp_core_get_user_displayname( $secondary_item_id );
		
		// Get activity
		$activity = new BP_Activity_Activity( $item_id );
		if ( $activity->id ) {
			$this->log( 'Activity type: ' . $activity->type );
			
			// Fix the text based on actual activity type
			if ( $activity->type === 'activity_update' ) {
				$text = sprintf( __( '%s favorited your activity', 'bp-fav-notification' ), $user_name );
			}
		}
		
		$this->log( 'Fixed notification text: ' . $text );
		
		return $text;
	}

	/**
	 * Fix notification array data
	 */
	public function fix_notification_array( $data, $activity, $secondary_item_id ) {
		$this->log( 'Fixing notification array data' );
		
		// Ensure we have the correct activity type
		if ( $activity->type === 'activity_update' && isset( $data['notification_type'] ) && $data['notification_type'] === 'favorite_comment' ) {
			$data['notification_type'] = 'favorite';
		}
		
		// Ensure user data is correct
		if ( ! empty( $data['user_name'] ) && strpos( $data['user_name'], 'User ' ) === 0 ) {
			$data['user_name'] = bp_core_get_user_displayname( $secondary_item_id );
		}
		
		return $data;
	}

	/**
	 * Fix notification display with JavaScript
	 */
	public function fix_notification_display() {
		if ( ! bp_is_user_notifications() ) {
			return;
		}
		?>
		<script>
		jQuery(document).ready(function($) {
			// Fix "User X" display names in notifications
			$('.notification-content a, .notifications td a, .bpfn-notification-text').each(function() {
				var text = $(this).text();
				var match = text.match(/User (\d+)/);
				if (match) {
					var $link = $(this);
					var userId = match[1];
					
					// Try to get the real name via AJAX
					$.post(ajaxurl, {
						action: 'bpfn_get_user_display_name',
						user_id: userId,
						nonce: '<?php echo wp_create_nonce( 'bpfn_get_user_name' ); ?>'
					}, function(response) {
						if (response.success && response.data.name) {
							var newText = text.replace('User ' + userId, response.data.name);
							$link.text(newText);
						}
					});
				}
			});
		});
		</script>
		<?php
	}

	/**
	 * AJAX handler to get user display names
	 */
	public function ajax_get_user_display_name() {
		check_ajax_referer( 'bpfn_get_user_name', 'nonce' );
		
		$user_id = intval( $_POST['user_id'] );
		$user = get_userdata( $user_id );
		
		if ( $user ) {
			$display_name = $user->display_name;
			
			// Get a better name if display_name is generic
			if ( empty( $display_name ) || preg_match( '/^User\s+\d+$/', $display_name ) ) {
				if ( ! empty( $user->first_name ) || ! empty( $user->last_name ) ) {
					$display_name = trim( $user->first_name . ' ' . $user->last_name );
				} else {
					$display_name = $user->user_login;
				}
			}
			
			wp_send_json_success( array( 'name' => $display_name ) );
		}
		
		wp_send_json_error();
	}

	/**
	 * Ensure component is initialized
	 */
	public function ensure_component_initialized() {
		global $bp;
		
		// Check if our component is set up
		if ( ! isset( $bp->favorite_notifier ) ) {
			$this->log( 'Component not initialized in bp_init, attempting to initialize' );
			
			// Force initialization
			if ( function_exists( 'bpfn' ) ) {
				$plugin = bpfn();
				if ( method_exists( $plugin, 'setup_globals' ) ) {
					$plugin->setup_globals();
					$this->log( 'Component initialized via debug module' );
				}
			}
		}
	}

	/**
	 * Debug favorite action
	 */
	public function debug_favorite_action() {
		try {
			$this->log( '=== AJAX activity_mark_fav START ===' );
			$this->log( 'User ID: ' . get_current_user_id() );
			$this->log( 'POST data: ' . print_r( $_POST, true ) );
			
			// Check if BuddyPress is loaded
			if ( ! function_exists( 'bp_is_active' ) ) {
				$this->log( 'BuddyPress not active' );
				wp_die( 'BuddyPress not active', 'Error', array( 'response' => 500 ) );
			}
			
			// Check if activity component is active
			if ( ! bp_is_active( 'activity' ) ) {
				$this->log( 'Activity component not active' );
				wp_die( 'Activity component not active', 'Error', array( 'response' => 500 ) );
			}
			
			// Ensure our component is initialized
			$this->ensure_component_initialized();
			
		} catch ( Exception $e ) {
			$this->log( 'Exception in activity_mark_fav: ' . $e->getMessage() );
			wp_die( $e->getMessage(), 'Error', array( 'response' => 500 ) );
		}
	}

	/**
	 * Debug unfavorite action
	 */
	public function debug_unfavorite_action() {
		$this->log( '=== AJAX activity_mark_unfav START ===' );
		$this->log( 'User ID: ' . get_current_user_id() );
		$this->log( 'POST data: ' . print_r( $_POST, true ) );
	}

	/**
	 * Enable AJAX debugging
	 */
	public function enable_ajax_debugging() {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			error_reporting( E_ALL );
			ini_set( 'display_errors', 1 );
			ini_set( 'display_startup_errors', 1 );
			
			// Log which action is being called
			$action = isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : 'no action';
			$this->log( 'AJAX Request: ' . $action );
		}
	}

	/**
	 * Log notification added
	 */
	public function log_notification_added( $notification_id, $notification_data, $activity, $user_id ) {
		$this->log( 'Notification added', array(
			'id' => $notification_id,
			'data' => $notification_data,
			'activity_id' => $activity->id,
			'activity_type' => $activity->type,
			'user_id' => $user_id,
		) );
	}

	/**
	 * Log email sent
	 */
	public function log_email_sent( $sent, $email_data ) {
		$this->log( 'Email ' . ( $sent ? 'sent' : 'failed' ), array(
			'to' => $email_data['to'] ?? '',
			'subject' => $email_data['subject'] ?? '',
		) );
	}

	/**
	 * Add debug menu to admin bar
	 */
	public function add_debug_menu( $wp_admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
		global $bp;
		
		$args = array(
			'id' => 'bpfn-debug',
			'title' => '🔧 BPFN Debug',
			'href' => '#',
			'meta' => array(
				'class' => 'bpfn-debug-menu',
			),
		);
		$wp_admin_bar->add_node( $args );
		
		// Add submenu items
		$wp_admin_bar->add_node( array(
			'id' => 'bpfn-debug-status',
			'parent' => 'bpfn-debug',
			'title' => 'Component: ' . ( isset( $bp->favorite_notifier ) ? '✅ Initialized' : '❌ Not Initialized' ),
		) );
		
		$wp_admin_bar->add_node( array(
			'id' => 'bpfn-debug-logs',
			'parent' => 'bpfn-debug',
			'title' => 'View Debug Logs',
			'href' => '#bpfn-debug-panel',
			'meta' => array(
				'onclick' => 'jQuery("#bpfn-debug-panel").toggle(); return false;',
			),
		) );
	}

	/**
	 * Catch fatal errors
	 */
	public function catch_fatal_errors() {
		$error = error_get_last();
		if ( $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ) ) ) {
			$this->log( 'Fatal Error', $error );
			
			// If it's an AJAX request, log it
			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				error_log( 'BPFN AJAX Fatal Error: ' . print_r( $error, true ) );
			}
		}
	}

	/**
	 * Render debug panel
	 */
	public function render_debug_panel() {
		if ( ! current_user_can( 'manage_options' ) || ! $this->debug_enabled ) {
			return;
		}
		?>
		<div id="bpfn-debug-panel" style="display:none; position:fixed; bottom:0; left:0; right:0; background:#222; color:#fff; padding:20px; max-height:300px; overflow-y:auto; z-index:99999;">
			<h3 style="color:#fff; margin-top:0;">BPFN Debug Panel</h3>
			<div id="bpfn-debug-logs" style="font-family:monospace; font-size:12px; line-height:1.5;">
				<!-- Logs will be inserted here via JavaScript -->
			</div>
			<button onclick="jQuery('#bpfn-debug-panel').hide();" style="position:absolute; top:10px; right:10px;">Close</button>
		</div>
		<script>
		// BPFN Debug Logger
		window.BPFNDebug = {
			logs: [],
			log: function(message, data) {
				var log = {
					time: new Date().toLocaleTimeString(),
					message: message,
					data: data
				};
				this.logs.push(log);
				console.log('[BPFN Debug]', message, data || '');
				this.updatePanel();
			},
			updatePanel: function() {
				var panel = jQuery('#bpfn-debug-logs');
				if (panel.length) {
					var html = '';
					for (var i = this.logs.length - 1; i >= 0 && i > this.logs.length - 20; i--) {
						var log = this.logs[i];
						html += '<div><strong>' + log.time + ':</strong> ' + log.message;
						if (log.data) {
							html += ' <pre style="display:inline;">' + JSON.stringify(log.data, null, 2) + '</pre>';
						}
						html += '</div>';
					}
					panel.html(html);
				}
			}
		};
		</script>
		<?php
	}

	/**
	 * Log debug message
	 */
	private function log( $message, $data = null ) {
		if ( ! $this->debug_enabled ) {
			return;
		}
		
		// Log to error log
		$log_message = '[BPFN Debug] ' . $message;
		if ( $data ) {
			$log_message .= ' - ' . print_r( $data, true );
		}
		error_log( $log_message );
		
		// Also log to JavaScript console if on frontend
		if ( ! is_admin() && ! defined( 'DOING_AJAX' ) ) {
			?>
			<script>
			if (window.BPFNDebug) {
				window.BPFNDebug.log('<?php echo esc_js( $message ); ?>', <?php echo $data ? json_encode( $data ) : 'null'; ?>);
			}
			</script>
			<?php
		}
	}

	/**
	 * Public logging method for other modules
	 */
	public static function debug_log( $message, $data = null ) {
		error_log( '[BPFN] ' . $message . ( $data ? ' - ' . print_r( $data, true ) : '' ) );
	}
}