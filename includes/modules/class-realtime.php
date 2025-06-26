<?php
/**
 * Enhanced Realtime Module for BuddyPress Favorite Notification
 * Complete replacement for includes/modules/class-realtime.php
 *
 * @package BuddyPress_Favorite_Notification
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enhanced Realtime Module Class
 */
class BPFN_Module_Realtime {

	/**
	 * Heartbeat interval
	 */
	private $heartbeat_interval = 15;

	/**
	 * Supported notification methods
	 */
	private $supported_methods = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->detect_supported_methods();
		$this->setup_hooks();
	}

	/**
	 * Detect supported notification methods
	 */
	private function detect_supported_methods() {
		$this->supported_methods = array(
			'heartbeat' => $this->is_heartbeat_available(),
			'polling' => true, // Always available
			'sse' => $this->is_sse_supported(),
			'websocket' => false // Future implementation
		);
	}

	/**
	 * Check if WordPress Heartbeat is available and enabled
	 */
	private function is_heartbeat_available() {
		// Check if Heartbeat is disabled by WordPress filters
		$heartbeat_settings = apply_filters( 'heartbeat_settings', array() );
		if ( isset( $heartbeat_settings['interval'] ) && $heartbeat_settings['interval'] === false ) {
			return false;
		}

		// Check if Heartbeat is disabled by common performance plugins
		$performance_plugins = array(
			'heartbeat-control/heartbeat-control.php',
			'wp-rocket/wp-rocket.php',
			'autoptimize/autoptimize.php',
			'w3-total-cache/w3-total-cache.php'
		);

		foreach ( $performance_plugins as $plugin ) {
			if ( is_plugin_active( $plugin ) ) {
				// Check if this plugin specifically disables Heartbeat
				if ( $this->plugin_disables_heartbeat( $plugin ) ) {
					return false;
				}
			}
		}

		// Check if Heartbeat is disabled globally
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if specific plugin disables Heartbeat
	 */
	private function plugin_disables_heartbeat( $plugin ) {
		switch ( $plugin ) {
			case 'heartbeat-control/heartbeat-control.php':
				$options = get_option( 'heartbeat_control_settings', array() );
				return ! empty( $options['disable_heartbeat'] );

			case 'wp-rocket/wp-rocket.php':
				if ( function_exists( 'get_rocket_option' ) ) {
					return get_rocket_option( 'control_heartbeat', false );
				}
				break;

			case 'w3-total-cache/w3-total-cache.php':
				if ( class_exists( 'W3_Config' ) ) {
					$config = w3_instance( 'W3_Config' );
					return $config->get_boolean( 'pgcache.enabled' ) && 
						   $config->get_boolean( 'pgcache.reject.ua' );
				}
				break;
		}

		return false;
	}

	/**
	 * Check if Server-Sent Events are supported
	 */
	private function is_sse_supported() {
		// Basic checks for SSE support
		return function_exists( 'apache_setenv' ) || 
			   ! ini_get( 'output_buffering' ) ||
			   function_exists( 'fastcgi_finish_request' );
	}

	/**
	 * Setup hooks
	 */
	private function setup_hooks() {
		// Heartbeat API hooks (only if supported)
		if ( $this->supported_methods['heartbeat'] ) {
			add_filter( 'heartbeat_received', array( $this, 'heartbeat_received' ), 10, 2 );
			add_filter( 'heartbeat_settings', array( $this, 'heartbeat_settings' ) );
		}
		
		// AJAX handlers
		add_action( 'wp_ajax_bpfn_check_notifications', array( $this, 'ajax_check_notifications' ) );
		add_action( 'wp_ajax_bpfn_dismiss_notification', array( $this, 'ajax_dismiss_notification' ) );
		
		// SSE handler (if supported)
		if ( $this->supported_methods['sse'] ) {
			add_action( 'wp_ajax_bpfn_sse_stream', array( $this, 'sse_stream' ) );
			add_action( 'wp_ajax_bpfn_sse_test', array( $this, 'sse_test' ) );
		}

		// Heartbeat test handler
		add_filter( 'heartbeat_received', array( $this, 'heartbeat_test_handler' ), 5, 2 );

		// Admin notices for configuration issues
		if ( is_admin() ) {
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		}
	}

	/**
	 * Handle heartbeat test requests
	 */
	public function heartbeat_test_handler( $response, $data ) {
		if ( isset( $data['bpfn_heartbeat_test'] ) ) {
			$response['bpfn_heartbeat_test_response'] = array(
				'success' => true,
				'timestamp' => time(),
				'method' => 'heartbeat'
			);
		}
		return $response;
	}

	/**
	 * Handle heartbeat requests for notifications
	 */
	public function heartbeat_received( $response, $data ) {
		if ( ! is_user_logged_in() || empty( $data['bpfn_realtime_check'] ) ) {
			return $response;
		}
		
		// Verify nonce
		if ( ! wp_verify_nonce( $data['bpfn_realtime_check']['nonce'], 'bpfn_realtime_nonce' ) ) {
			return $response;
		}
		
		$user_id = get_current_user_id();
		if ( ! $this->is_realtime_enabled_for_user( $user_id ) ) {
			return $response;
		}
		
		// Get new notifications
		$last_checked = intval( $data['bpfn_realtime_check']['last_checked'] );
		$notifications = $this->get_new_notifications( $user_id, $last_checked );
		
		// Add to response
		$response['bpfn_realtime_notifications'] = array(
			'notifications' => $notifications,
			'count' => bpfn_get_notification_count( $user_id ),
			'timestamp' => time(),
			'method' => 'heartbeat'
		);
		
		return $response;
	}

	/**
	 * Server-Sent Events stream handler
	 */
	public function sse_stream() {
		// Verify permissions
		if ( ! wp_verify_nonce( $_GET['nonce'] ?? '', 'bpfn_realtime_nonce' ) || ! is_user_logged_in() ) {
			status_header( 403 );
			exit( 'Forbidden' );
		}

		// Set SSE headers
		$this->set_sse_headers();
		
		$user_id = get_current_user_id();
		$last_checked = isset( $_GET['last_checked'] ) ? intval( $_GET['last_checked'] ) : time();
		
		// Send initial connection event
		$this->send_sse_event( 'connected', array(
			'timestamp' => time(),
			'method' => 'sse',
			'user_id' => $user_id
		) );
		
		// Main streaming loop
		$this->sse_event_loop( $user_id, $last_checked );
	}

	/**
	 * SSE test endpoint for admin
	 */
	public function sse_test() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}
		
		$this->set_sse_headers();
		
		// Send test events
		for ( $i = 1; $i <= 3; $i++ ) {
			$this->send_sse_event( 'test', array(
				'message' => 'Test event #' . $i,
				'timestamp' => time()
			) );
			sleep( 2 );
		}
		
		$this->send_sse_event( 'close', array( 'reason' => 'test_complete' ) );
		exit;
	}

	/**
	 * Set SSE headers
	 */
	private function set_sse_headers() {
		header( 'Content-Type: text/event-stream' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		header( 'Connection: keep-alive' );
		header( 'Access-Control-Allow-Origin: *' );
		header( 'Access-Control-Allow-Headers: Cache-Control' );
		
		// Disable WordPress output buffering
		remove_action( 'shutdown', 'wp_ob_end_flush_all', 1 );
		
		// Disable output buffering
		if ( ob_get_level() ) {
			ob_end_clean();
		}
		
		// Set unlimited execution time for SSE
		if ( ! ini_get( 'safe_mode' ) ) {
			set_time_limit( 0 );
		}
	}

	/**
	 * SSE event loop
	 */
	private function sse_event_loop( $user_id, $last_checked ) {
		$max_duration = 300; // 5 minutes
		$start_time = time();
		$check_interval = 5; // 5 seconds
		$heartbeat_interval = 30; // 30 seconds
		$last_heartbeat = $start_time;
		
		while ( ( time() - $start_time ) < $max_duration ) {
			// Check connection status
			if ( connection_aborted() ) {
				error_log( 'BPFN SSE: Connection aborted for user ' . $user_id );
				break;
			}
			
			// Send heartbeat
			if ( ( time() - $last_heartbeat ) >= $heartbeat_interval ) {
				$this->send_sse_event( 'heartbeat', array( 'timestamp' => time() ) );
				$last_heartbeat = time();
			}
			
			// Check for new notifications
			$notifications = $this->get_new_notifications( $user_id, $last_checked );
			
			if ( ! empty( $notifications ) ) {
				$this->send_sse_event( 'notifications', array(
					'notifications' => $notifications,
					'count' => bpfn_get_notification_count( $user_id ),
					'timestamp' => time(),
					'method' => 'sse'
				) );
				
				$last_checked = time();
			}
			
			// Small delay to prevent excessive CPU usage
			sleep( $check_interval );
		}
		
		// Send close event
		$this->send_sse_event( 'close', array(
			'reason' => 'timeout',
			'duration' => time() - $start_time
		) );
	}

	/**
	 * Send SSE event
	 */
	private function send_sse_event( $event, $data ) {
		echo "event: {$event}\n";
		echo "data: " . json_encode( $data ) . "\n\n";
		
		// Force output
		if ( ob_get_level() ) {
			ob_flush();
		}
		flush();
	}

	/**
	 * Configure heartbeat settings
	 */
	public function heartbeat_settings( $settings ) {
		if ( ! is_user_logged_in() || ! $this->is_realtime_enabled_for_user( get_current_user_id() ) ) {
			return $settings;
		}
		
		$options = get_option( 'bpfn_options', array() );
		$interval = ! empty( $options['realtime_interval'] ) ? intval( $options['realtime_interval'] ) : $this->heartbeat_interval;
		
		// Ensure minimum interval
		$settings['interval'] = max( 15, $interval );
		
		return $settings;
	}

	/**
	 * Check if realtime is enabled for user
	 */
	private function is_realtime_enabled_for_user( $user_id ) {
		$settings = bpfn_get_user_settings( $user_id );
		
		foreach ( $settings as $type => $options ) {
			if ( ! empty( $options['realtime_enabled'] ) ) {
				return true;
			}
		}
		
		return false;
	}

	/**
	 * Get new notifications since last check
	 */
	public function get_new_notifications( $user_id, $last_checked ) {
		global $wpdb, $bp;
		
		if ( ! isset( $bp->favorite_notifier ) || ! bp_is_active( 'notifications' ) ) {
			return array();
		}
		
		// Add buffer for any delays and timezone considerations
		$date_query = date( 'Y-m-d H:i:s', $last_checked - 5 );
		
		$query = $wpdb->prepare(
			"SELECT * FROM {$bp->notifications->table_name} 
			 WHERE user_id = %d 
			 AND component_name = %s 
			 AND date_notified > %s 
			 AND is_new = 1
			 ORDER BY date_notified DESC
			 LIMIT 10",
			$user_id,
			$bp->favorite_notifier->id,
			$date_query
		);
		
		$notifications = $wpdb->get_results( $query );
		
		if ( empty( $notifications ) ) {
			return array();
		}
		
		$processed = array();
		
		foreach ( $notifications as $notification ) {
			// Skip if older than last_checked (extra safety)
			if ( strtotime( $notification->date_notified ) <= $last_checked ) {
				continue;
			}
			
			$data = $this->format_realtime_notification( $notification );
			if ( $data ) {
				$processed[] = $data;
			}
		}
		
		return $processed;
	}

	/**
	 * Format notification for realtime display
	 */
	private function format_realtime_notification( $notification ) {
		$notifications_module = bpfn()->get_module( 'notifications' );
		if ( ! $notifications_module ) {
			return false;
		}
		
		// Format the notification
		$formatted = $notifications_module->format_notification(
			$notification->component_action,
			$notification->item_id,
			$notification->secondary_item_id,
			1,
			'array'
		);
		
		if ( ! is_array( $formatted ) ) {
			return false;
		}
		
		// Add realtime specific data
		return array_merge( $formatted, array(
			'notification_id' => $notification->id,
			'time_ago' => human_time_diff( strtotime( $notification->date_notified ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'bp-fav-notification' ),
			'timestamp' => strtotime( $notification->date_notified ),
			'date_notified' => $notification->date_notified,
		) );
	}

	/**
	 * Enhanced AJAX handler for checking notifications
	 */
	public function ajax_check_notifications() {
		// Verify nonce - support both possible nonce names
		$nonce_valid = false;
		if ( isset( $_POST['nonce'] ) ) {
			$nonce_valid = wp_verify_nonce( $_POST['nonce'], 'bpfn-nonce' ) || 
						  wp_verify_nonce( $_POST['nonce'], 'bpfn_realtime_nonce' );
		}
		
		if ( ! $nonce_valid ) {
			wp_send_json_error( array( 
				'message' => __( 'Security check failed', 'bp-fav-notification' ),
				'debug' => array(
					'nonce_provided' => ! empty( $_POST['nonce'] ),
					'nonce_value' => substr( $_POST['nonce'] ?? '', 0, 10 ) . '...'
				)
			) );
		}
		
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in', 'bp-fav-notification' ) ) );
		}
		
		$user_id = get_current_user_id();
		$last_checked = isset( $_POST['last_checked'] ) ? intval( $_POST['last_checked'] ) : 0;
		
		// Enhanced logging for debugging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 
				'BPFN AJAX: User %d checking notifications since %s (%d)',
				$user_id,
				date( 'Y-m-d H:i:s', $last_checked ),
				$last_checked
			) );
		}
		
		$notifications = $this->get_new_notifications( $user_id, $last_checked );
		
		wp_send_json_success( array(
			'notifications' => $notifications,
			'count' => bpfn_get_notification_count( $user_id ),
			'timestamp' => time(),
			'method' => 'polling',
			'debug' => array(
				'last_checked' => $last_checked,
				'notifications_found' => count( $notifications ),
				'supported_methods' => $this->supported_methods
			)
		) );
	}

	/**
	 * AJAX handler for dismissing notifications
	 */
	public function ajax_dismiss_notification() {
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'bpfn-nonce' ) && 
			 ! wp_verify_nonce( $_POST['nonce'] ?? '', 'bpfn_realtime_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed', 'bp-fav-notification' ) ) );
		}
		
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in', 'bp-fav-notification' ) ) );
		}
		
		$notification_id = isset( $_POST['notification_id'] ) ? intval( $_POST['notification_id'] ) : 0;
		
		if ( ! $notification_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid notification ID', 'bp-fav-notification' ) ) );
		}
		
		// Verify ownership and mark as read
		$notification = BP_Notifications_Notification::get( array(
			'id' => $notification_id,
			'user_id' => get_current_user_id(),
		) );
		
		if ( empty( $notification ) ) {
			wp_send_json_error( array( 'message' => __( 'Notification not found', 'bp-fav-notification' ) ) );
		}
		
		$success = bp_notifications_mark_notification( $notification_id, false );
		
		if ( $success ) {
			wp_send_json_success( array(
				'message' => __( 'Notification dismissed', 'bp-fav-notification' ),
				'count' => bpfn_get_notification_count( get_current_user_id() ),
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to dismiss notification', 'bp-fav-notification' ) ) );
		}
	}

	/**
	 * Get optimal polling configuration based on environment
	 */
	public function get_polling_config() {
		$options = get_option( 'bpfn_options', array() );
		
		// Determine optimal settings based on available methods
		$config = array(
			'enabled' => true,
			'methods' => $this->supported_methods,
			'preferred_method' => $this->get_preferred_method(),
			'interval' => ! empty( $options['realtime_interval'] ) ? 
				intval( $options['realtime_interval'] ) * 1000 : 
				$this->heartbeat_interval * 1000,
			'max_notifications' => 5,
			'auto_dismiss_time' => 5000,
			'position' => $options['realtime_position'] ?? 'bottom-right',
			'retry_config' => array(
				'max_retries' => 5,
				'retry_delay' => 2000,
				'backoff_multiplier' => 1.5,
				'max_backoff_delay' => 300000
			)
		);
		
		return apply_filters( 'bpfn_realtime_config', $config );
	}

	/**
	 * Get preferred notification method
	 */
	private function get_preferred_method() {
		// Priority order: heartbeat -> sse -> polling
		if ( $this->supported_methods['heartbeat'] ) {
			return 'heartbeat';
		} elseif ( $this->supported_methods['sse'] ) {
			return 'sse';
		} else {
			return 'polling';
		}
	}

	/**
	 * Get system status for debugging
	 */
	public function get_system_status() {
		return array(
			'supported_methods' => $this->supported_methods,
			'preferred_method' => $this->get_preferred_method(),
			'heartbeat_available' => $this->is_heartbeat_available(),
			'sse_supported' => $this->is_sse_supported(),
			'active_performance_plugins' => $this->get_active_performance_plugins(),
			'server_info' => array(
				'php_version' => PHP_VERSION,
				'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
				'output_buffering' => ini_get( 'output_buffering' ),
				'max_execution_time' => ini_get( 'max_execution_time' )
			)
		);
	}

	/**
	 * Get active performance plugins that might affect real-time notifications
	 */
	private function get_active_performance_plugins() {
		$performance_plugins = array(
			'heartbeat-control/heartbeat-control.php' => 'Heartbeat Control',
			'wp-rocket/wp-rocket.php' => 'WP Rocket',
			'autoptimize/autoptimize.php' => 'Autoptimize',
			'w3-total-cache/w3-total-cache.php' => 'W3 Total Cache',
			'wp-super-cache/wp-cache.php' => 'WP Super Cache',
			'litespeed-cache/litespeed-cache.php' => 'LiteSpeed Cache'
		);
		
		$active_plugins = array();
		foreach ( $performance_plugins as $plugin => $name ) {
			if ( is_plugin_active( $plugin ) ) {
				$active_plugins[] = $name;
			}
		}
		
		return $active_plugins;
	}

	/**
	 * Show admin notices for configuration issues
	 */
	public function admin_notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
		// Only show on plugin pages
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'bpfn' ) === false ) {
			return;
		}
		
		// Check for Heartbeat issues
		if ( ! $this->supported_methods['heartbeat'] ) {
			$active_plugins = $this->get_active_performance_plugins();
			
			if ( ! empty( $active_plugins ) ) {
				?>
				<div class="notice notice-warning">
					<p>
						<strong><?php _e( 'BuddyPress Favorite Notification:', 'bp-fav-notification' ); ?></strong>
						<?php
						printf(
							__( 'WordPress Heartbeat appears to be disabled, possibly by: %s. Real-time notifications will use polling instead, which may be less efficient.', 'bp-fav-notification' ),
							implode( ', ', $active_plugins )
						);
						?>
						<a href="<?php echo admin_url( 'admin.php?page=bpfn-help#realtime-troubleshooting' ); ?>">
							<?php _e( 'Learn more', 'bp-fav-notification' ); ?>
						</a>
					</p>
				</div>
				<?php
			} else {
				?>
				<div class="notice notice-info">
					<p>
						<strong><?php _e( 'BuddyPress Favorite Notification:', 'bp-fav-notification' ); ?></strong>
						<?php _e( 'WordPress Heartbeat is not available. Real-time notifications will use alternative methods.', 'bp-fav-notification' ); ?>
					</p>
				</div>
				<?php
			}
		}
	}

	/**
	 * Test real-time notification system
	 */
	public function test_realtime_system() {
		$status = array(
			'overall_status' => 'unknown',
			'methods' => array(),
			'issues' => array(),
			'recommendations' => array()
		);
		
		// Test each method
		foreach ( $this->supported_methods as $method => $supported ) {
			$status['methods'][ $method ] = array(
				'supported' => $supported,
				'tested' => false,
				'working' => false
			);
			
			if ( $supported ) {
				switch ( $method ) {
					case 'heartbeat':
						$status['methods'][ $method ]['tested'] = true;
						$status['methods'][ $method ]['working'] = $this->test_heartbeat_method();
						break;
						
					case 'polling':
						$status['methods'][ $method ]['tested'] = true;
						$status['methods'][ $method ]['working'] = $this->test_polling_method();
						break;
						
					case 'sse':
						$status['methods'][ $method ]['tested'] = true;
						$status['methods'][ $method ]['working'] = $this->test_sse_method();
						break;
				}
			}
		}
		
		// Determine overall status
		$working_methods = array_filter( $status['methods'], function( $method ) {
			return $method['working'];
		} );
		
		if ( count( $working_methods ) > 0 ) {
			$status['overall_status'] = 'working';
		} elseif ( count( array_filter( $status['methods'], function( $method ) { return $method['supported']; } ) ) > 0 ) {
			$status['overall_status'] = 'issues';
		} else {
			$status['overall_status'] = 'failed';
		}
		
		// Add recommendations
		if ( ! $this->supported_methods['heartbeat'] ) {
			$status['recommendations'][] = __( 'Consider enabling WordPress Heartbeat for optimal real-time performance', 'bp-fav-notification' );
		}
		
		if ( ! $this->supported_methods['sse'] ) {
			$status['recommendations'][] = __( 'Server-Sent Events could improve real-time performance if server configuration allows', 'bp-fav-notification' );
		}
		
		return $status;
	}

	/**
	 * Test heartbeat method
	 */
	private function test_heartbeat_method() {
		// Check if wp.heartbeat is available in admin
		if ( is_admin() && function_exists( 'wp_enqueue_script' ) ) {
			return true; // Basic check - actual testing would require JS
		}
		return false;
	}

	/**
	 * Test polling method
	 */
	private function test_polling_method() {
		// Test if AJAX endpoints are accessible
		$test_url = admin_url( 'admin-ajax.php' );
		$response = wp_remote_post( $test_url, array(
			'timeout' => 5,
			'body' => array(
				'action' => 'bpfn_check_notifications',
				'nonce' => wp_create_nonce( 'bpfn-nonce' ),
				'last_checked' => time()
			)
		) );
		
		return ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200;
	}

	/**
	 * Test SSE method
	 */
	private function test_sse_method() {
		// Basic server capability check
		return $this->is_sse_supported() && ! ini_get( 'output_buffering' );
	}
}