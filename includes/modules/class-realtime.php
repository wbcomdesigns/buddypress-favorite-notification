<?php
/**
 * Clean Realtime Module for BuddyPress Favorite Notification
 *
 * @package BuddyPress_Favorite_Notification
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clean Realtime Module Class
 */
class BPFN_Module_Realtime {

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
		// WordPress Heartbeat API
		add_filter( 'heartbeat_received', array( $this, 'heartbeat_received' ), 10, 2 );
		add_filter( 'heartbeat_settings', array( $this, 'heartbeat_settings' ) );
		
		// AJAX fallback
		add_action( 'wp_ajax_bpfn_check_notifications', array( $this, 'ajax_check_notifications' ) );
		add_action( 'wp_ajax_bpfn_dismiss_notification', array( $this, 'ajax_dismiss_notification' ) );
	}

	/**
	 * Handle heartbeat requests
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
			'timestamp' => time()
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
		
		// Set heartbeat interval (minimum 15 seconds)
		$settings['interval'] = 15;
		
		return $settings;
	}

	/**
	 * AJAX check notifications (fallback)
	 */
	public function ajax_check_notifications() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'bpfn-nonce' ) && 
			 ! wp_verify_nonce( $_POST['nonce'] ?? '', 'bpfn_realtime_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed', 'bp-fav-notification' ) ) );
		}
		
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in', 'bp-fav-notification' ) ) );
		}
		
		$user_id = get_current_user_id();
		$last_checked = isset( $_POST['last_checked'] ) ? intval( $_POST['last_checked'] ) : 0;
		
		$notifications = $this->get_new_notifications( $user_id, $last_checked );
		
		wp_send_json_success( array(
			'notifications' => $notifications,
			'count' => bpfn_get_notification_count( $user_id ),
			'timestamp' => time()
		) );
	}

	/**
	 * AJAX dismiss notification
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
	private function get_new_notifications( $user_id, $last_checked ) {
		global $wpdb, $bp;
		
		if ( ! isset( $bp->favorite_notifier ) || ! bp_is_active( 'notifications' ) ) {
			return array();
		}
		
		$date_query = date( 'Y-m-d H:i:s', $last_checked );
		
		$query = $wpdb->prepare(
			"SELECT * FROM {$bp->notifications->table_name} 
			 WHERE user_id = %d 
			 AND component_name = %s 
			 AND date_notified > %s 
			 AND is_new = 1
			 ORDER BY date_notified DESC
			 LIMIT 5",
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
		) );
	}

	/**
	 * Get polling configuration
	 */
	public function get_polling_config() {
		$options = get_option( 'bpfn_options', array() );
		
		return array(
			'enabled' => true,
			'interval' => 15000, // 15 seconds
			'max_notifications' => 5,
			'auto_dismiss_time' => 5000, // 5 seconds
			'position' => 'bottom-right'
		);
	}
}