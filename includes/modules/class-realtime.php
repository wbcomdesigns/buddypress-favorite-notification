<?php
/**
 * Realtime Module for BuddyPress Favorite Notification
 *
 * @package BuddyPress_Favorite_Notification
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Realtime Module Class
 */
class BPFN_Module_Realtime {

	/**
	 * Heartbeat interval
	 */
	private $heartbeat_interval = 15;

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
		// Heartbeat API hooks
		add_filter( 'heartbeat_received', array( $this, 'heartbeat_received' ), 10, 2 );
		add_filter( 'heartbeat_settings', array( $this, 'heartbeat_settings' ) );
		
		// AJAX handlers
		add_action( 'wp_ajax_bpfn_check_notifications', array( $this, 'ajax_check_notifications' ) );
		add_action( 'wp_ajax_bpfn_dismiss_notification', array( $this, 'ajax_dismiss_notification' ) );
		
		// Debug handlers
		add_action( 'wp_ajax_bpfn_test_realtime', array( $this, 'ajax_test_realtime' ) );
		
		// Custom hooks
		do_action( 'bpfn_realtime_setup_hooks', $this );
	}

	/**
	 * Handle heartbeat requests
	 */
	public function heartbeat_received( $response, $data ) {
		// Log heartbeat request for debugging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'BPFN Heartbeat: Received request' );
		}
		
		if ( ! is_user_logged_in() ) {
			return $response;
		}
		
		// Check if this is our request
		if ( empty( $data['bpfn_realtime_check'] ) ) {
			return $response;
		}
		
		// Log the check
		error_log( 'BPFN Heartbeat: Processing realtime check for user ' . get_current_user_id() );
		
		// Verify nonce
		if ( ! isset( $data['bpfn_realtime_check']['nonce'] ) || 
			 ! wp_verify_nonce( $data['bpfn_realtime_check']['nonce'], 'bpfn_realtime_nonce' ) ) {
			error_log( 'BPFN Heartbeat: Nonce verification failed' );
			return $response;
		}
		
		// Check if realtime is enabled for user
		$user_id = get_current_user_id();
		if ( ! $this->is_realtime_enabled_for_user( $user_id ) ) {
			error_log( 'BPFN Heartbeat: Realtime not enabled for user ' . $user_id );
			return $response;
		}
		
		// Get last checked time
		$last_checked = isset( $data['bpfn_realtime_check']['last_checked'] ) 
			? intval( $data['bpfn_realtime_check']['last_checked'] ) 
			: 0;
		
		error_log( 'BPFN Heartbeat: Last checked timestamp: ' . $last_checked . ' (' . date( 'Y-m-d H:i:s', $last_checked ) . ')' );
		
		// Get new notifications
		$notifications = $this->get_new_notifications( $user_id, $last_checked );
		
		error_log( 'BPFN Heartbeat: Found ' . count( $notifications ) . ' new notifications' );
		
		// Add to response
		$response['bpfn_realtime_notifications'] = array(
			'notifications' => $notifications,
			'count' => bpfn_get_notification_count( $user_id ),
			'timestamp' => time(),
		);
		
		return $response;
	}

	/**
	 * Configure heartbeat settings
	 */
	public function heartbeat_settings( $settings ) {
		if ( ! is_user_logged_in() || ! $this->is_realtime_enabled_for_user( get_current_user_id() ) ) {
			return $settings;
		}
		
		// Get interval from options
		$options = get_option( 'bpfn_options', array() );
		$interval = ! empty( $options['realtime_interval'] ) ? intval( $options['realtime_interval'] ) : $this->heartbeat_interval;
		
		// Set custom interval for our checks
		$interval = apply_filters( 'bpfn_heartbeat_interval', $interval );
		$settings['interval'] = max( 15, $interval ); // Minimum 15 seconds
		
		return $settings;
	}

	/**
	 * Check if realtime is enabled for user
	 */
	private function is_realtime_enabled_for_user( $user_id ) {
		$settings = bpfn_get_user_settings( $user_id );
		
		// Check if any notification type has realtime enabled
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
	private function get_new_notifications( $user_id, $last_checked ) {
		global $wpdb, $bp;
		
		// Ensure component is set
		if ( ! isset( $bp->favorite_notifier ) ) {
			error_log( 'BPFN: favorite_notifier component not set in get_new_notifications' );
			return array();
		}
		
		// Ensure notifications table exists
		if ( ! bp_is_active( 'notifications' ) || ! isset( $bp->notifications->table_name ) ) {
			error_log( 'BPFN: Notifications component not active or table not set' );
			return array();
		}
		
		// Convert timestamp to MySQL format
		$date_query = date( 'Y-m-d H:i:s', $last_checked );
		
		// Get new notifications - include a buffer time for any delays
		$buffer_time = 2; // 2 seconds buffer
		$date_query_buffer = date( 'Y-m-d H:i:s', $last_checked - $buffer_time );
		
		error_log( 'BPFN: Querying notifications newer than ' . $date_query_buffer );
		
		// Get new notifications
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
			$date_query_buffer
		);
		
		$notifications = $wpdb->get_results( $query );
		
		error_log( 'BPFN: Query: ' . $query );
		error_log( 'BPFN: Found ' . count( $notifications ) . ' raw notifications' );
		
		if ( empty( $notifications ) ) {
			return array();
		}
		
		$processed = array();
		
		foreach ( $notifications as $notification ) {
			// Skip if this notification is actually older than last_checked (due to buffer)
			if ( strtotime( $notification->date_notified ) <= $last_checked ) {
				error_log( 'BPFN: Skipping notification ' . $notification->id . ' - older than last_checked' );
				continue;
			}
			
			// Get formatted notification data
			$data = $this->format_realtime_notification( $notification );
			
			if ( ! $data ) {
				error_log( 'BPFN: Failed to format notification ' . $notification->id );
				continue;
			}
			
			error_log( 'BPFN: Successfully formatted notification ' . $notification->id );
			$processed[] = $data;
		}
		
		error_log( 'BPFN: Returning ' . count( $processed ) . ' processed notifications' );
		
		return $processed;
	}

	/**
	 * Format notification for realtime display
	 */
	private function format_realtime_notification( $notification ) {
		global $bp;
		
		// Get the notifications module to format the notification
		$notifications_module = bpfn()->get_module( 'notifications' );
		if ( ! $notifications_module ) {
			error_log( 'BPFN: Notifications module not found' );
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
			error_log( 'BPFN: Failed to format notification as array' );
			// Try to create basic data
			$formatted = array(
				'text' => __( 'Someone favorited your activity', 'bp-fav-notification' ),
				'link' => bp_get_activity_directory_permalink(),
			);
		}
		
		// Add realtime specific data
		$data = array_merge( $formatted, array(
			'notification_id' => $notification->id,
			'time_ago' => human_time_diff( strtotime( $notification->date_notified ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'bp-fav-notification' ),
			'timestamp' => strtotime( $notification->date_notified ),
		) );
		
		// Ensure we have required fields
		if ( empty( $data['text'] ) ) {
			$data['text'] = __( 'Someone favorited your activity', 'bp-fav-notification' );
		}
		
		if ( empty( $data['link'] ) ) {
			$data['link'] = bp_get_activity_directory_permalink();
		}
		
		// Add user avatar if not present
		if ( empty( $data['user_avatar'] ) && ! empty( $notification->secondary_item_id ) ) {
			$data['user_avatar'] = bp_core_fetch_avatar( array(
				'item_id' => $notification->secondary_item_id,
				'type' => 'thumb',
				'width' => 60,
				'height' => 60,
				'html' => true,
			) );
		}
		
		return apply_filters( 'bpfn_realtime_notification_data', $data, $notification );
	}

	/**
	 * AJAX handler for checking notifications
	 */
	public function ajax_check_notifications() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'bpfn-nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed', 'bp-fav-notification' ) ) );
		}
		
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in', 'bp-fav-notification' ) ) );
		}
		
		$user_id = get_current_user_id();
		$last_checked = isset( $_POST['last_checked'] ) ? intval( $_POST['last_checked'] ) : 0;
		
		error_log( 'BPFN AJAX: Checking notifications for user ' . $user_id . ' since ' . date( 'Y-m-d H:i:s', $last_checked ) );
		
		$notifications = $this->get_new_notifications( $user_id, $last_checked );
		
		wp_send_json_success( array(
			'notifications' => $notifications,
			'count' => bpfn_get_notification_count( $user_id ),
			'timestamp' => time(),
		) );
	}

	/**
	 * AJAX handler for dismissing notifications
	 */
	public function ajax_dismiss_notification() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'bpfn-nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed', 'bp-fav-notification' ) ) );
		}
		
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in', 'bp-fav-notification' ) ) );
		}
		
		$notification_id = isset( $_POST['notification_id'] ) ? intval( $_POST['notification_id'] ) : 0;
		
		if ( ! $notification_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid notification ID', 'bp-fav-notification' ) ) );
		}
		
		// Verify the notification belongs to the current user
		$notification = BP_Notifications_Notification::get( array(
			'id' => $notification_id,
			'user_id' => get_current_user_id(),
		) );
		
		if ( empty( $notification ) ) {
			wp_send_json_error( array( 'message' => __( 'Notification not found', 'bp-fav-notification' ) ) );
		}
		
		// Mark as read
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
	 * AJAX handler for testing realtime
	 */
	public function ajax_test_realtime() {
		// This is for testing, so we'll be lenient with permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'bp-fav-notification' ) ) );
		}
		
		// Create a test notification
		$test_data = array(
			'notification_id' => 'test-' . time(),
			'notification_type' => 'favorite',
			'text' => '<strong>Test User</strong> favorited your activity',
			'link' => home_url(),
			'time_ago' => 'just now',
			'user_avatar' => bp_core_fetch_avatar( array(
				'item_id' => get_current_user_id(),
				'type' => 'thumb',
				'width' => 60,
				'height' => 60,
				'html' => true,
			) ),
			'activity_excerpt' => 'This is a test activity content to verify real-time notifications are working correctly.',
		);
		
		wp_send_json_success( array(
			'message' => __( 'Test notification data generated', 'bp-fav-notification' ),
			'notification' => $test_data,
		) );
	}

	/**
	 * Get polling configuration
	 */
	public function get_polling_config() {
		$options = get_option( 'bpfn_options', array() );
		
		return apply_filters( 'bpfn_realtime_polling_config', array(
			'enabled' => true,
			'interval' => ! empty( $options['realtime_interval'] ) ? intval( $options['realtime_interval'] ) * 1000 : $this->heartbeat_interval * 1000,
			'max_notifications' => 5,
			'auto_dismiss_time' => 5000,
			'position' => 'bottom-right',
		) );
	}

	/**
	 * Check if realtime module should load
	 */
	public function should_load() {
		// Don't load if user is not logged in
		if ( ! is_user_logged_in() ) {
			return false;
		}
		
		// Don't load if notifications component is not active
		if ( ! bp_is_active( 'notifications' ) ) {
			return false;
		}
		
		// Check if user has realtime enabled
		return $this->is_realtime_enabled_for_user( get_current_user_id() );
	}

	/**
	 * Debug log helper
	 */
	private function debug_log( $message, $data = null ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'BPFN Realtime: ' . $message . ( $data ? ' - ' . print_r( $data, true ) : '' ) );
		}
	}
}