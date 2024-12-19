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
			if ( function_exists( 'bp_is_active' ) && bp_is_active( 'notifications' ) && function_exists( 'bp_notifications_get_all_notifications_for_user' ) ) {
				$notification = bp_notifications_get_all_notifications_for_user( bp_loggedin_user_id(), 'object' );
				
				if ( ! empty( $notification ) ) {					
					foreach ( $notification as $key => $value ) {
						if ( $activity->id === $value->item_id ) {
							bp_notifications_mark_notification( $value->id, 0 );
							
						}
					}
				
				}
			}
			
		}

	}
	new BPFN_Functions();
}
