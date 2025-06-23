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
		
		// Custom hooks
		do_action( 'bpfn_realtime_setup_hooks', $this );
	}

	/**
	 * Handle heartbeat requests
	 */
	public function heartbeat_received( $response, $data ) {
		if ( ! is_user_logged_in() ) {
			return $response;
		}
		
		// Check if this is our request
		if ( empty( $data['bpfn_realtime_check'] ) ) {
			return $response;
		}
		
		// Verify nonce
		if ( ! isset( $data['bpfn_realtime_check']['nonce'] ) || 
			 ! wp_verify_nonce( $data['bpfn_realtime_check']['nonce'], 'bpfn_realtime_nonce' ) ) {
			return $response;
		}
		
		// Check if realtime is enabled for user
		$user_id = get_current_user_id();
		if ( ! $this->is_realtime_enabled_for_user( $user_id ) ) {
			return $response;
		}
		
		// Get last checked time
		$last_checked = isset( $data['bpfn_realtime_check']['last_checked'] ) 
			? intval( $data['bpfn_realtime_check']['last_checked'] ) 
			: 0;
		
		// Get new notifications
		$notifications = $this->get_new_notifications( $user_id, $last_checked );
		
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
		
		// Set custom interval for our checks
		$interval = apply_filters( 'bpfn_heartbeat_interval', $this->heartbeat_interval );
		$settings['interval'] = $interval;
		
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
		
		// Convert timestamp to MySQL format
		$date_query = date( 'Y-m-d H:i:s', $last_checked );
		
		// Get new notifications
		$notifications = $wpdb->get_results( $wpdb->prepare(
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
		) );
		
		if ( empty( $notifications ) ) {
			return array();
		}
		
		$processed = array();
		
		foreach ( $notifications as $notification ) {
			// Get formatted notification data
			$data = bpfn_format_notification_data( $notification );
			
			if ( ! $data ) {
				continue;
			}
			
			// Add realtime specific data
			$data['notification_id'] = $notification->id;
			$data['time_ago'] = human_time_diff( strtotime( $notification->date_notified ), current_time( 'timestamp' ) );
			
			$processed[] = apply_filters( 'bpfn_realtime_notification_data', $data, $notification );
		}
		
		return $processed;
	}

	/**
	 * AJAX handler for checking notifications
	 */
	public function ajax_check_notifications() {
		check_ajax_referer( 'bpfn-nonce', 'nonce' );
		
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in', 'bp-fav-notification' ) ) );
		}
		
		$user_id = get_current_user_id();
		$last_checked = isset( $_POST['last_checked'] ) ? intval( $_POST['last_checked'] ) : 0;
		
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
		check_ajax_referer( 'bpfn-nonce', 'nonce' );
		
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in', 'bp-fav-notification' ) ) );
		}
		
		$notification_id = isset( $_POST['notification_id'] ) ? intval( $_POST['notification_id'] ) : 0;
		
		if ( ! $notification_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid notification ID', 'bp-fav-notification' ) ) );
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
	 * Get polling configuration
	 */
	public function get_polling_config() {
		return apply_filters( 'bpfn_realtime_polling_config', array(
			'enabled' => true,
			'interval' => $this->heartbeat_interval * 1000, // Convert to milliseconds
			'max_notifications' => 5,
			'auto_dismiss_time' => 5000,
			'position' => 'bottom-right',
		) );
	}
}