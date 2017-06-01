<?php
// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
* Class to serve BPFN_Functions Calls
*
* @since    1.0.0
* @author   Wbcom Designs
*/

if( !class_exists( 'BPFN_Functions' ) ) {
	class BPFN_Functions{

		/**
		* Constructor.
		*
		* @since    1.0.0
		* @access   public
		* @author   Wbcom Designs
		*/
		public function __construct() {
			add_action('bp_activity_screen_single_activity_permalink', array( $this, 'wb_bp_fav_activity_remove_screen_notifications'), 10, 1 );
			add_filter('the_content', array( $this, 'wp_bp_activity_post_notification_mark' ) );
		}

		 /**
		 * Mark at-mention notifications as read when users visit their Mentions page.
		 *
		 * @since 1.0.0
		 * @author   Wbcom Designs
		 */
		 
		function wb_bp_fav_activity_remove_screen_notifications( $activity ) {
			global $bp;
			// Only mark read if the current user is looking at his own mentions.
			if ( empty( $activity->user_id ) || (int) $activity->user_id !== (int) bp_loggedin_user_id() ) {				
				return;
			}
			$notification = bp_notifications_get_notifications_for_user( bp_loggedin_user_id(), 'object' );
			if ( !empty( $notification ) ) {
				foreach ( $notification as $key => $value ) {
					if ( $activity->id === $value->item_id ) {
						bp_notifications_mark_notification( $value->id, 0);
					}
				}
			}
		}
		
		 /**
		 * Mark notifications as read when users visit their post activity page.
		 *
		 * @since 1.0.0
		 * @author   Wbcom Designs
		 *
		 */
		 
		function wp_bp_activity_post_notification_mark( $content ) {
			if ( is_single() ) {
				// Only mark read if the current user is looking at his own mentions.
				$notification = bp_notifications_get_notifications_for_user( bp_loggedin_user_id(), 'object');
				if ( !empty( $notification ) ) {
					foreach ( $notification as $key => $value ) {
						$href = bp_activity_get_permalink( $value->item_id );
						$Pobj = parse_url( $href );
						if ( isset( $Pobj['query'] ) ) {
							$obj = explode( '=', $Pobj['query'] );
							if ($obj[0] === 'p') {
								$PID = isset( $obj[1] ) ? $obj[1] : '';
								if ( !empty( $PID ) ) {
									$current_notification_post = get_permalink( $PID );
									$protocol = ( !empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443 ) ? "https://" : "http://";
									$current_post = esc_url("$protocol$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]");
									if ( $current_notification_post == $current_post ) {
										bp_notifications_mark_notifications_by_type( bp_loggedin_user_id(), $value->component_name, $value->component_action, false );
									}
								}
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