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
	 * Heartbeat intervals (seconds) the owner can pick on the Display tab.
	 */
	const INTERVALS = array( 30, 60 );

	/**
	 * Whether the site owner has switched real-time toasts on.
	 *
	 * Off unless saved: a fresh install must not add a 30-second request per
	 * logged-in member. Sites upgraded from before 2.2.0 get 'yes' written once by
	 * the upgrade routine, so nothing changes under them.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return 'yes' === get_option( 'bpfn_realtime_enabled', 'no' );
	}

	/**
	 * Heartbeat interval in seconds, normalised to one of INTERVALS.
	 *
	 * @param mixed $seconds Raw value. Null reads the stored option.
	 * @return int
	 */
	public static function get_interval( $seconds = null ) {
		if ( null === $seconds ) {
			$seconds = get_option( 'bpfn_realtime_interval', 30 );
		}
		$seconds = absint( $seconds );
		return in_array( $seconds, self::INTERVALS, true ) ? $seconds : 30;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( ! self::is_enabled() ) {
			return;
		}
		add_filter( 'heartbeat_received', array( $this, 'heartbeat_received' ), 10, 2 );
		add_filter( 'heartbeat_settings', array( $this, 'heartbeat_settings' ) );
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

		if ( ! wp_verify_nonce( $data['bpfn_realtime_check']['nonce'], 'bpfn_realtime_nonce' ) ) {
			return $response;
		}

		$user_id = get_current_user_id();
		if ( ! self::is_enabled_for_user( $user_id ) ) {
			return $response;
		}

		$response['bpfn_realtime_notifications'] = array(
			'notifications' => $this->get_new_notifications( $user_id, intval( $data['bpfn_realtime_check']['last_checked'] ) ),
			'count'         => bpfn_get_notification_count( $user_id ),
			'timestamp'     => time(),
		);

		return $response;
	}

	/**
	 * Use the owner's interval on pages where a member receives toasts.
	 *
	 * @param array $settings The heartbeat settings.
	 * @return array Modified settings.
	 */
	public function heartbeat_settings( $settings ) {
		if ( ! is_admin() && self::is_enabled_for_user( get_current_user_id() ) ) {
			$settings['interval'] = self::get_interval();
		}
		return $settings;
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

		// Not bp_notifications_mark_notification(): it checks bp_displayed_user_id(),
		// which is 0 in admin-ajax, so it always failed. Ownership is in the WHERE.
		$success = BP_Notifications_Notification::update(
			array( 'is_new' => 0 ),
			array(
				'id'      => $notification_id,
				'user_id' => get_current_user_id(),
			)
		);

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
	 * Whether this member gets toasts: owner switch on and at least one type enabled.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function is_enabled_for_user( $user_id ) {
		if ( ! $user_id || ! self::is_enabled() ) {
			return false;
		}
		foreach ( bpfn_get_user_settings( $user_id ) as $options ) {
			if ( ! empty( $options['realtime_enabled'] ) ) {
				return true;
			}
		}
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
		$settings  = bpfn_get_user_settings( $user_id );

		foreach ( $notifications as $notification ) {
			$type = BPFN_Module_Notifications::get_type_for_action( $notification->component_action );
			if ( empty( $settings[ $type ]['realtime_enabled'] ) ) {
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
			'array',
			(int) $notification->id // Adds `rid`, so opening the activity marks it read.
		);

		if ( ! is_array( $formatted ) ) {
			return false;
		}

		// realtime.js concatenates these into HTML, so escape here, where the data
		// leaves PHP. text carries a member display name (member-controlled).
		$formatted['text']              = esc_html( $formatted['text'] );
		$formatted['link']              = esc_url( $formatted['link'] );
		$formatted['notification_type'] = sanitize_html_class( $formatted['notification_type'] );

		return array_merge(
			$formatted,
			array(
				'notification_id' => (int) $notification->id,
				'time_ago'        => sprintf(
					/* translators: %s: human-readable time difference, e.g. "5 mins". */
					esc_html__( '%s ago', 'buddypress-favorite-notification' ),
					human_time_diff( strtotime( $notification->date_notified ), time() )
				),
				'timestamp'       => strtotime( $notification->date_notified ),
			)
		);
	}
}
