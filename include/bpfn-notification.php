<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

	add_action( 'bp_setup_globals', 'favorite_notifier_setup_globals' );

	/**
	 * Setup new global notification object for the menu
	 *
	 * @since 1.0.0
	 * @author   Wbcom Designs
	 */
function favorite_notifier_setup_globals() {
	global $bp;
	$bp->favorite_notifier                        = new stdClass();
	$bp->favorite_notifier->id                    = 'favorite_notifier'; // I asume others are not going to use this is
	$bp->favorite_notifier->slug                  = 'favorite_notification';
	$bp->favorite_notifier->notification_callback = 'favorite_notifier_format_notifications'; // show the notification
	/* Register this in the active components array */
	$bp->active_components[ $bp->favorite_notifier->id ] = $bp->favorite_notifier->id;
	do_action( 'favorite_notifier_setup_globals' );
}

	add_action( 'bp_activity_add_user_favorite', 'add_notification_mark_fav', 0, 2 );

	/**
	 * Add the notification on marking activity as favorite use "bp_activity_add_user_favorite" hook
	 *
	 * @since 1.0.0
	 * @author   Wbcom Designs
	 */

function add_notification_mark_fav( $activity_id, $user_id ) {
	global $bp;
	if ( bp_is_active( 'notifications' ) ) {
		$original_activity = new BP_Activity_Activity( $activity_id );
		if ( $original_activity->user_id != $user_id ) {
			$arg = array(
				'user_id'           => $original_activity->user_id,
				'item_id'           => $activity_id,
				'secondary_item_id' => $user_id,
				'component_name'    => $bp->favorite_notifier->id,
				'component_action'  => 'fav_notify_' . $activity_id,
				'date_notified'     => bp_core_current_time(),
				'is_new'            => 1,
			);
			bp_notifications_add_notification( $arg );
		}
	}
}

	/**
	 * Function to display text and link in the top notification and in the notification area
	 *
	 * @since 1.0.0
	 * @author   Wbcom Designs
	 */
function favorite_notifier_format_notifications( $action, $item_id, $secondary_item_id, $total_items, $format = 'string' ) {
	global $bp;
	$link      = bp_activity_get_permalink( $item_id );
	$amount    = 'single';
	$ac_action = 'fav_notify_' . $item_id;

	if ( $action == $ac_action ) {
		if ( (int) $total_items > 1 ) {
				$text   = sprintf( __( '%1$d members added your activity to favorite', 'bp-fav-notification' ), (int) $total_items );
				$amount = 'multiple';
			if ( $format == 'string' ) {
				return apply_filters( 'bp_favorite_' . $amount . '_' . $ac_action . 's_notification', '<a href="' . $link . '" title="' . __( 'Activity added to favorite', 'bp-fav-notification' ) . '">' . $text . '</a>', $link, $total_items, $text, $item_id, $secondary_item_id );
			} else {
				return apply_filters(
					'bp_favorite_' . $amount . '_' . $ac_action . '_notification', array(
						'link' => $link,
						'text' => $text,
					), $link, $total_items, $text, $item_id, $secondary_item_id
				);
			}
		} else {
				$user_fullname = bp_core_get_user_displayname( $secondary_item_id );
				$text          = sprintf( __( '%s added your activity to favorite', 'bp-fav-notification' ), $user_fullname );
			if ( $format == 'string' ) {
				return apply_filters( 'bp_favorite_' . $amount . '_' . $ac_action . 's_notification', '<a href="' . $link . '" title="' . __( 'Activity Added To Favorite', 'bp-fav-notification' ) . '">' . $text . '</a>', $link, $total_items, $text, $item_id, $secondary_item_id );
			} else {
				return apply_filters(
					'bp_favorite_' . $amount . '_' . $ac_action . '_notification', array(
						'link' => $link,
						'text' => $text,
					), $link, $total_items, $text, $item_id, $secondary_item_id
				);
			}
		}
	}
	return false;
}
