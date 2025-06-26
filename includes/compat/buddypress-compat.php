<?php
/**
 * BuddyPress Compatibility Functions
 * 
 * Ensures the favorite_notifier component is properly registered
 * 
 * @package BuddyPress_Favorite_Notification
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the favorite_notifier component early in BuddyPress initialization
 * This ensures it's available when BuddyPress processes notifications
 */
add_action( 'bp_setup_globals', 'bpfn_compat_setup_globals', 5 );

function bpfn_compat_setup_globals() {
	global $bp;
	
	// Don't proceed if notifications component is not active
	if ( ! bp_is_active( 'notifications' ) ) {
		return;
	}
	
	// Set up the favorite_notifier component
	$bp->favorite_notifier = new stdClass();
	$bp->favorite_notifier->id = 'favorite_notifier';
	$bp->favorite_notifier->slug = 'favorite_notification';
	$bp->favorite_notifier->notification_callback = 'bpfn_compat_format_notifications';
	
	// Register in active components - this is critical!
	$bp->active_components[ $bp->favorite_notifier->id ] = $bp->favorite_notifier->id;
	
	// Also ensure it's in loaded components
	if ( ! isset( $bp->loaded_components[ $bp->favorite_notifier->id ] ) ) {
		$bp->loaded_components[ $bp->favorite_notifier->id ] = $bp->favorite_notifier->id;
	}
	
	do_action( 'bpfn_compat_setup_globals' );
}

/**
 * Format notifications callback
 * This bridges between the old callback style and the new modular system
 */
function bpfn_compat_format_notifications( $action, $item_id, $secondary_item_id, $total_items, $format = 'string' ) {
	// Get the main plugin instance
	$plugin = bpfn();
	
	// If we have the notifications module, use it
	if ( $plugin && $notifications_module = $plugin->get_module( 'notifications' ) ) {
		return $notifications_module->format_notification( $action, $item_id, $secondary_item_id, $total_items, $format );
	}
	
	// Fallback formatting if module not available
	return bpfn_compat_fallback_format( $action, $item_id, $secondary_item_id, $total_items, $format );
}

/**
 * Fallback notification formatter
 */
function bpfn_compat_fallback_format( $action, $item_id, $secondary_item_id, $total_items, $format = 'string' ) {
	$link = bp_activity_get_permalink( $item_id );
	$text = '';
	
	// Check if this is a favorite notification
	if ( strpos( $action, 'fav_notify_' ) === 0 || strpos( $action, 'fav_comment_notify_' ) === 0 ) {
		if ( $total_items > 1 ) {
			$text = sprintf( __( '%d members favorited your activity', 'bp-fav-notification' ), $total_items );
		} else {
			$user_fullname = bp_core_get_user_displayname( $secondary_item_id );
			$text = sprintf( __( '%s favorited your activity', 'bp-fav-notification' ), $user_fullname );
		}
		
		if ( 'string' === $format ) {
			return '<a href="' . esc_url( $link ) . '">' . esc_html( $text ) . '</a>';
		} else {
			return array(
				'link' => $link,
				'text' => $text,
			);
		}
	}
	
	return false;
}

/**
 * Hook into bp_notifications_get_registered_components to ensure our component is recognized
 */
add_filter( 'bp_notifications_get_registered_components', 'bpfn_compat_register_component', 999 );

function bpfn_compat_register_component( $components ) {
	if ( ! in_array( 'favorite_notifier', $components ) ) {
		$components[] = 'favorite_notifier';
	}
	return $components;
}

/**
 * Ensure notifications are created when activity is favorited
 * This provides a safety net in case the module's hook doesn't fire
 */
add_action( 'bp_activity_add_user_favorite', 'bpfn_compat_add_notification', 15, 2 );

function bpfn_compat_add_notification( $activity_id, $user_id ) {
	global $bp;
	
	// Check if notification was already created by the module
	static $processed = array();
	$key = $activity_id . '_' . $user_id;
	
	if ( isset( $processed[ $key ] ) ) {
		return;
	}
	
	$processed[ $key ] = true;
	
	// Get activity
	$activity = new BP_Activity_Activity( $activity_id );
	if ( empty( $activity->id ) || $activity->user_id == $user_id ) {
		return;
	}
	
	// Check if we should create notification
	$existing = BP_Notifications_Notification::get( array(
		'user_id' => $activity->user_id,
		'item_id' => $activity_id,
		'secondary_item_id' => $user_id,
		'component_name' => 'favorite_notifier',
		'is_new' => 1,
	) );
	
	if ( ! empty( $existing ) ) {
		return; // Notification already exists
	}
	
	// Determine action based on activity type
	$component_action = ( $activity->type === 'activity_comment' ) ? 
		'fav_comment_notify_' . $activity_id : 
		'fav_notify_' . $activity_id;
	
	// Add notification
	bp_notifications_add_notification( array(
		'user_id'           => $activity->user_id,
		'item_id'           => $activity_id,
		'secondary_item_id' => $user_id,
		'component_name'    => 'favorite_notifier',
		'component_action'  => $component_action,
		'date_notified'     => bp_core_current_time(),
		'is_new'            => 1,
	) );
}

/**
 * Debug helper to verify component registration
 */
add_action( 'bp_init', 'bpfn_compat_verify_registration', 999 );

function bpfn_compat_verify_registration() {
	global $bp;
	
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		// Log component status
		if ( isset( $bp->favorite_notifier ) && isset( $bp->active_components['favorite_notifier'] ) ) {
			error_log( 'BPFN Compat: Component successfully registered' );
		} else {
			error_log( 'BPFN Compat: Component registration failed!' );
			
			// Try to fix it
			if ( ! isset( $bp->favorite_notifier ) ) {
				bpfn_compat_setup_globals();
			}
		}
	}
}