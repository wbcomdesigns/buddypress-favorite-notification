<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- Legacy file name.
/**
 * Clean Realtime Module for BuddyPress Favorite Notification.
 *
 * @package BuddyPress_Favorite_Notification
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clean Realtime Module Class.
 */
// phpcs:ignore Squiz.Commenting.ClassComment.Missing -- Class docblock is above.
class BPFN_Module_Realtime {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Setup hooks.
	 */
	private function setup_hooks() {
		// WordPress Heartbeat API.
		add_filter( 'heartbeat_received', array( $this, 'heartbeat_received' ), 10, 2 );
		add_filter( 'heartbeat_settings', array( $this, 'heartbeat_settings' ) );

		// AJAX fallback.
		add_action( 'wp_ajax_bpfn_check_notifications', array( $this, 'ajax_check_notifications' ) );
		add_action( 'wp_ajax_bpfn_dismiss_notification', array( $this, 'ajax_dismiss_notification' ) );
	}

	/**
	 * Handle heartbeat requests.
	 *
	 * @param array $response The heartbeat response.
	 * @param array $data     The heartbeat data.
	 * @return array Modified response.
	 */
	public function heartbeat_received( $response, $data ) {
		if ( ! is_user_logged_in() || empty( $data['bpfn_realtime_check'] ) ) {
			return $response;
		}

		// Verify nonce.
		if ( ! wp_verify_nonce( $data['bpfn_realtime_check']['nonce'], 'bpfn_realtime_nonce' ) ) {
			return $response;
		}

		$user_id = get_current_user_id();
		if ( ! $this->is_realtime_enabled_for_user( $user_id ) ) {
			return $response;
		}

		// Get new notifications.
		$last_checked  = intval( $data['bpfn_realtime_check']['last_checked'] );
		$notifications = $this->get_new_notifications( $user_id, $last_checked );

		// Add to response.
		$response['bpfn_realtime_notifications'] = array(
			'notifications' => $notifications,
			'count'         => bpfn_get_notification_count( $user_id ),
			'timestamp'     => time(),
		);

		return $response;
	}

	/**
	 * Configure heartbeat settings.
	 *
	 * @param array $settings The heartbeat settings.
	 * @return array Modified settings.
	 */
	public function heartbeat_settings( $settings ) {
		if ( ! is_user_logged_in() || ! $this->is_realtime_enabled_for_user( get_current_user_id() ) ) {
			return $settings;
		}

		// Set heartbeat interval (minimum 15 seconds).
		$settings['interval'] = 15;

		return $settings;
	}

	/**
	 * AJAX check notifications (fallback).
	 */
	public function ajax_check_notifications() {
		// Verify nonce.
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'bpfn-nonce' ) && ! wp_verify_nonce( $nonce, 'bpfn_realtime_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'buddypress-favorite-notification' ) ) );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Not logged in', 'buddypress-favorite-notification' ) ) );
		}

		$user_id      = get_current_user_id();
		$last_checked = isset( $_POST['last_checked'] ) ? intval( $_POST['last_checked'] ) : 0;

		$notifications = $this->get_new_notifications( $user_id, $last_checked );

		wp_send_json_success(
			array(
				'notifications' => $notifications,
				'count'         => bpfn_get_notification_count( $user_id ),
				'timestamp'     => time(),
			)
		);
	}

	/**
	 * AJAX dismiss notification.
	 */
	public function ajax_dismiss_notification() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'bpfn-nonce' ) && ! wp_verify_nonce( $nonce, 'bpfn_realtime_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'buddypress-favorite-notification' ) ) );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Not logged in', 'buddypress-favorite-notification' ) ) );
		}

		$notification_id = isset( $_POST['notification_id'] ) ? intval( $_POST['notification_id'] ) : 0;

		if ( ! $notification_id ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid notification ID', 'buddypress-favorite-notification' ) ) );
		}

		// Verify ownership and mark as read.
		$notification = BP_Notifications_Notification::get(
			array(
				'id'      => $notification_id,
				'user_id' => get_current_user_id(),
			)
		);

		if ( empty( $notification ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Notification not found', 'buddypress-favorite-notification' ) ) );
		}

		$success = bp_notifications_mark_notification( $notification_id, false );

		if ( $success ) {
			wp_send_json_success(
				array(
					'message' => esc_html__( 'Notification dismissed', 'buddypress-favorite-notification' ),
					'count'   => bpfn_get_notification_count( get_current_user_id() ),
				)
			);
		} else {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to dismiss notification', 'buddypress-favorite-notification' ) ) );
		}
	}

	/**
	 * Check if realtime is enabled for user.
	 *
	 * @param int $user_id User ID.
	 * @return bool Whether realtime is enabled.
	 */
	private function is_realtime_enabled_for_user( $user_id ) {
		$settings = bpfn_get_user_settings( $user_id );

		// Check if user has any notification type with realtime enabled.
		foreach ( $settings as $type => $options ) {
			if ( ! empty( $options['realtime_enabled'] ) ) {
				return true;
			}
		}

		// User has explicitly disabled all realtime notifications.
		return false;
	}

	/**
	 * Get new notifications since last check.
	 *
	 * @param int $user_id      User ID.
	 * @param int $last_checked Last checked timestamp.
	 * @return array New notifications.
	 */
	private function get_new_notifications( $user_id, $last_checked ) {
		global $wpdb, $bp;

		if ( ! isset( $bp->favorite_notifier ) || ! bp_is_active( 'notifications' ) ) {
			return array();
		}

		$date_query = gmdate( 'Y-m-d H:i:s', $last_checked );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Realtime query.
		$query = $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from BP.
			"SELECT * FROM {$bp->notifications->table_name} WHERE user_id = %d AND component_name = %s AND date_notified > %s AND is_new = 1 ORDER BY date_notified DESC LIMIT 5",
			$user_id,
			$bp->favorite_notifier->id,
			$date_query
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Already prepared above.
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
	 * Format notification for realtime display.
	 *
	 * @param object $notification Notification object.
	 * @return array|false Formatted data or false.
	 */
	private function format_realtime_notification( $notification ) {
		$notifications_module = bpfn()->get_module( 'notifications' );
		if ( ! $notifications_module ) {
			return false;
		}

		// Format the notification.
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

		// Add realtime specific data.
		return array_merge(
			$formatted,
			array(
				'notification_id' => $notification->id,
				'time_ago'        => sprintf(
					/* translators: %s: Human-readable time difference, e.g. "5 mins". */
					esc_html__( '%s ago', 'buddypress-favorite-notification' ),
					human_time_diff( strtotime( $notification->date_notified ), time() )
				),
				'timestamp'       => strtotime( $notification->date_notified ),
			)
		);
	}

	/**
	 * Get polling configuration.
	 *
	 * @return array Polling config.
	 */
	public function get_polling_config() {
		return array(
			'enabled'           => true,
			'interval'          => 15000,
			'max_notifications' => 5,
			'auto_dismiss_time' => 5000,
			'position'          => 'bottom-right',
		);
	}
}
