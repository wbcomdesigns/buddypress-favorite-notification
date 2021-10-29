<?php
/**
 * Class to add reviews shortcode.
 *
 * @since    1.0.0
 * @author   Wbcom Designs
 * @package  BuddyPress_Favorite_Notification
 */

defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'BPFN_Functions' ) ) {
	/**
	 * Class to serve BPFN_Functions Calls
	 *
	 * @since    1.0.0
	 * @author   Wbcom Designs
	 */
	class BPFN_Functions {

		/**
		 * Constructor.
		 *
		 * @since    1.0.0
		 * @access   public
		 * @author   Wbcom Designs
		 */
		public function __construct() {
			add_action( 'bp_activity_screen_single_activity_permalink', array( $this, 'wb_bp_fav_activity_remove_screen_notifications' ), 10, 1 );
			add_filter( 'the_content', array( $this, 'wp_bp_activity_post_notification_mark' ) );
		}

		/**
		 * Mark at-mention notifications as read when users visit their Mentions page.
		 *
		 * @since 1.0.0
		 * @param array $activity Activity Object.
		 * @author   Wbcom Designs
		 */
		public function wb_bp_fav_activity_remove_screen_notifications( $activity ) {
			global $bp;
			// Only mark read if the current user is looking at his own mentions.
			if ( empty( $activity->user_id ) || (int) bp_loggedin_user_id() !== (int) $activity->user_id ) {
				return;
			}
			$notification = bp_notifications_get_notifications_for_user( bp_loggedin_user_id(), 'object' );
			if ( ! empty( $notification ) ) {
				foreach ( $notification as $key => $value ) {
					if ( $activity->id === $value->item_id ) {
						bp_notifications_mark_notification( $value->id, 0 );
					}
				}
			}
		}

		/**
		 * Mark notifications as read when users visit their post activity page.
		 *
		 * @since 1.0.0
		 * @param string $content The content.
		 * @author   Wbcom Designs
		 */
		public function wp_bp_activity_post_notification_mark( $content ) {

			if ( is_user_logged_in() ) {
				$current_component = bp_current_component();
				if ( $current_component && 'activity' === $current_component ) {
					// Only mark read if the current user is looking at his own mentions.
					$notification = bp_notifications_get_notifications_for_user( bp_loggedin_user_id(), 'object' );
					if ( ! empty( $notification ) ) {
						foreach ( $notification as $key => $value ) {
							$action = bp_current_action();
							// Get the activity details.
							if ( '' !== $action && $action === $value->item_id ) {
								bp_notifications_mark_notifications_by_type( bp_loggedin_user_id(), $value->component_name, $value->component_action, false );
							}
						}
					}
				}
			}
			return $content;
		}
	}
	new BPFN_Functions();
}
