<?php
/*
Plugin Name: Buddypress Favorite Notification
Plugin URI: http://www.wbcomdesigns.com/
Description: Adds notification for the activity favorite for the activity user.
Version: 1.0.0
Text Domain: wb-bp-fav-notification
Author: Wbcom Designs<admin@wbcomdesigns.com>
Author URI: http://www.wbcomdesigns.com/
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

	if ( ! defined( 'ABSPATH' ) ) {
		die( '-1' );
	}
	
	define( 'WB_BP_FAV_NOTIFICATION_NAME',          'Buddypress Favorite Notification' );
	define( 'WB_BP_FAV_NOTIFICATION_VERSION',       '1.0.0' );
	define( 'WB_BP_FAV_NOTIFICATION_SLUG',          'wb-bp-fav-notification' );
	define( 'WB_BP_FAV_NOTIFICATION_PLUGIN_PATH',   plugin_dir_path( __FILE__ ) );
	define( 'WB_BP_FAV_NOTIFICATION_PLUGIN_URL',    plugin_dir_url( __FILE__ ) );
	define( 'WB_BP_FAV_NOTIFICATION_UPDATER_ID',    200 );
	
	// Functions hook to add notification for favorites and display them in notification dropdown menu 
			
	//Calls the default hooks on plugin activation and get values of the setting saved in the option table

	add_action( 'bp_setup_globals', 'favorite_notifier_setup_globals' );
	add_action( 'bp_activity_add_user_favorite', 'add_notification_mark_fav', 0, 2  );
			
			
	// Add the notification on marking activity as favorite use "bp_activity_add_user_favorite" hook
	function add_notification_mark_fav( $activity_id, $user_id ) {
		
		global $bp;
		if ( bp_is_active( 'notifications' ) ) {
			$original_activity = new BP_Activity_Activity( $activity_id );
				$arg=array(
					'user_id'           => $original_activity->user_id ,
					'item_id'           => $activity_id,
					'secondary_item_id' => $user_id,
					'component_name'    => $bp->favorite_notifier->id,
					'component_action'  => 'fav_notify_'.$activity_id,
					'date_notified'     => bp_core_current_time(),
					'is_new'            => 1,
				);
				bp_notifications_add_notification($arg);
		}
	
	}
	
	//Setup new global notification object for the menu		
	function favorite_notifier_setup_globals() {
		
		global $bp;
		$bp->favorite_notifier							 = new stdClass();
		$bp->favorite_notifier->id						 = 'favorite_notifier';//I asume others are not going to use this is
		$bp->favorite_notifier->slug					 = 'favorite_notification';
		$bp->favorite_notifier->notification_callback	 = 'favorite_notifier_format_notifications';//show the notification
		/* Register this in the active components array */
		$bp->active_components[$bp->favorite_notifier->id] = $bp->favorite_notifier->id;
	
		do_action( 'favorite_notifier_setup_globals' );
	}
	
	// Function to display text and link in the top notification and in the notification area		
	function favorite_notifier_format_notifications( $action, $item_id, $secondary_item_id, $total_items, $format = 'string' ) {

		global $bp;
		
		$link = bp_activity_get_permalink($item_id);
		$amount = 'single';
		$ac_action = 'fav_notify_' . $item_id;
		if( $action == $ac_action ) {	
			if ( (int) $total_items > 1 ) {
				
					$text = sprintf( __( '%1$d members added your activity to favorite', WB_BP_FAV_NOTIFICATION_SLUG ), (int) $total_items);
					$amount = 'multiple';
				 
				 if( $format == 'string' ){
					 
				   return apply_filters( 'bp_favorite_' . $amount . '_' . $ac_action . 's_notification', '<a href="' . $link . '" title="' . __( 'Activity Added To Favorite', WB_BP_FAV_NOTIFICATION_SLUG ) . '">' . $text . '</a>', $link, $total_items, $text, $item_id, $secondary_item_id );
				   
				 }else{
					 
					return apply_filters( 'bp_favorite_' . $amount . '_' . $ac_action . '_notification', array(
							'link'  => $link,
							'text'  => $text
						), $link, $total_items, $text, $item_id, $secondary_item_id );
					}
			}
			else
			{
				$user_fullname = bp_core_get_user_displayname( $secondary_item_id );
				$text = sprintf( __( '%s added your activity to favorite', WB_BP_FAV_NOTIFICATION_SLUG ), $user_fullname );
				if( $format == 'string' ){
					
				   return apply_filters( 'bp_favorite_' . $amount . '_' . $ac_action . 's_notification', '<a href="' . $link . '" title="' . __( 'Activity Added To Favorite', WB_BP_FAV_NOTIFICATION_SLUG ) . '">' . $text . '</a>', $link, $total_items, $text, $item_id, $secondary_item_id );
				 
				 }else{
					 
					return apply_filters( 'bp_favorite_' . $amount . '_' . $ac_action . '_notification', array(
							'link'  => $link,
							'text'  => $text
						), $link, $total_items, $text, $item_id, $secondary_item_id );
				}
			}
		}
		
		return false;
	}
		
	//Activation Hook to add default option values
	function wb_bp_fav_notify_activate() {
	
		update_option( 'wb-bp-fav-notification-version', WB_BP_FAV_NOTIFICATION_VERSION );
		update_option( 'wb-bp-fav-notification-updater-id', WB_BP_FAV_NOTIFICATION_UPDATER_ID );
	}
	register_activation_hook( __FILE__, 'wb_bp_fav_notify_activate' );
	
	//Deactivation Hook to remove default option values if user has marked to delete them
	function wb_bp_fav_notify_deactivate() {
		
		delete_option( 'wb-bp-fav-notification-version' );
		delete_option( 'wb-bp-fav-notification-updater-id' );
	}
	register_deactivation_hook( __FILE__, 'wb_bp_fav_notify_deactivate' );
?>