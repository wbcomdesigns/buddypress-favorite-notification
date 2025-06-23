<?php
/**
 * API functions for BuddyPress Favorite Notification
 *
 * @package BuddyPress_Favorite_Notification
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register a custom notification type
 *
 * @param string $type Notification type key
 * @param array $args Configuration array
 * @return bool Success status
 */
function bpfn_register_notification_type( $type, $args = array() ) {
	$defaults = array(
		'labels' => array(
			'single' => '',
			'multiple' => '',
		),
		'action_prefix' => $type . '_notify',
		'icon' => 'dashicons-heart',
		'settings_label' => '',
		'settings_description' => '',
	);
	
	$args = wp_parse_args( $args, $defaults );
	
	// Get notifications module
	$module = bpfn()->get_module( 'notifications' );
	if ( ! $module ) {
		return false;
	}
	
	// Register the type
	$module->register_notification_type( $type, $args );
	
	// Trigger action
	do_action( 'bpfn_registered_notification_type', $type, $args );
	
	return true;
}

/**
 * Add a notification
 *
 * @param array $args Notification arguments
 * @return int|false Notification ID or false on failure
 */
function bpfn_add_notification( $args = array() ) {
	if ( ! bp_is_active( 'notifications' ) ) {
		return false;
	}
	
	global $bp;
	
	$defaults = array(
		'user_id' => 0,
		'item_id' => 0,
		'secondary_item_id' => 0,
		'component_name' => $bp->favorite_notifier->id,
		'component_action' => '',
		'date_notified' => bp_core_current_time(),
		'is_new' => 1,
	);
	
	$args = wp_parse_args( $args, $defaults );
	
	// Validate required fields
	if ( empty( $args['user_id'] ) || empty( $args['item_id'] ) || empty( $args['component_action'] ) ) {
		return false;
	}
	
	// Add notification
	$notification_id = bp_notifications_add_notification( $args );
	
	// Trigger action
	if ( $notification_id ) {
		do_action( 'bpfn_notification_added', $notification_id, $args );
	}
	
	return $notification_id;
}

/**
 * Delete notifications
 *
 * @param array $args Arguments for deletion
 * @return bool Success status
 */
function bpfn_delete_notifications( $args = array() ) {
	if ( ! bp_is_active( 'notifications' ) ) {
		return false;
	}
	
	global $bp;
	
	$defaults = array(
		'component_name' => $bp->favorite_notifier->id,
	);
	
	$args = wp_parse_args( $args, $defaults );
	
	// Delete notifications
	$deleted = BP_Notifications_Notification::delete( $args );
	
	// Trigger action
	if ( $deleted ) {
		do_action( 'bpfn_notifications_deleted', $args );
	}
	
	return $deleted;
}

/**
 * Mark notifications as read
 *
 * @param array $args Arguments for marking
 * @return bool Success status
 */
function bpfn_mark_notifications_read( $args = array() ) {
	if ( ! bp_is_active( 'notifications' ) ) {
		return false;
	}
	
	global $bp;
	
	$defaults = array(
		'component_name' => $bp->favorite_notifier->id,
		'is_new' => 1,
	);
	
	$args = wp_parse_args( $args, $defaults );
	
	// Get notifications
	$notifications = BP_Notifications_Notification::get( $args );
	
	if ( empty( $notifications ) ) {
		return false;
	}
	
	$success = true;
	
	// Mark each as read
	foreach ( $notifications as $notification ) {
		if ( ! bp_notifications_mark_notification( $notification->id, false ) ) {
			$success = false;
		}
	}
	
	// Trigger action
	do_action( 'bpfn_marked_notifications_read', $notifications, $args );
	
	return $success;
}

/**
 * Get notifications for a user
 *
 * @param int $user_id User ID
 * @param array $args Additional arguments
 * @return array Notifications
 */
function bpfn_get_notifications( $user_id = 0, $args = array() ) {
	if ( ! bp_is_active( 'notifications' ) ) {
		return array();
	}
	
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}
	
	if ( ! $user_id ) {
		return array();
	}
	
	global $bp;
	
	$defaults = array(
		'user_id' => $user_id,
		'component_name' => $bp->favorite_notifier->id,
		'is_new' => null,
		'order_by' => 'date_notified',
		'sort_order' => 'DESC',
		'page' => 1,
		'per_page' => 20,
	);
	
	$args = wp_parse_args( $args, $defaults );
	
	// Get notifications
	$notifications = BP_Notifications_Notification::get( $args );
	
	// Format notifications
	$formatted = array();
	foreach ( $notifications as $notification ) {
		$data = bpfn_format_notification_data( $notification );
		if ( $data ) {
			$formatted[] = $data;
		}
	}
	
	return apply_filters( 'bpfn_get_notifications', $formatted, $user_id, $args );
}

/**
 * Send test notification
 *
 * @param int $user_id User ID to send to
 * @param string $type Notification type
 * @return bool Success status
 */
function bpfn_send_test_notification( $user_id, $type = 'favorite' ) {
	if ( ! $user_id || ! bp_is_active( 'notifications' ) ) {
		return false;
	}
	
	// Create a test activity for the user
	$activity_args = array(
		'user_id' => $user_id,
		'content' => sprintf( __( 'Test activity for notification testing created at %s', 'bp-fav-notification' ), current_time( 'mysql' ) ),
		'type' => 'activity_update',
		'recorded_time' => bp_core_current_time(),
		'hide_sitewide' => false, // Make sure it's not hidden
	);
	
	$activity_id = bp_activity_add( $activity_args );
	
	if ( ! $activity_id ) {
		error_log( 'BPFN: Failed to create test activity' );
		return false;
	}
	
	// Add the notification directly
	global $bp;
	
	$notification_args = array(
		'user_id'           => $user_id,
		'item_id'           => $activity_id,
		'secondary_item_id' => get_current_user_id(),
		'component_name'    => isset( $bp->favorite_notifier ) ? $bp->favorite_notifier->id : 'favorite_notifier',
		'component_action'  => 'fav_notify_' . $activity_id,
		'date_notified'     => bp_core_current_time(),
		'is_new'            => 1,
	);
	
	$notification_id = bp_notifications_add_notification( $notification_args );
	
	if ( ! $notification_id ) {
		error_log( 'BPFN: Failed to create notification' );
		// Clean up the activity
		bp_activity_delete( array( 'id' => $activity_id ) );
		return false;
	}
	
	// Trigger email if enabled
	$email_enabled = bpfn_is_notification_enabled( $user_id, 'activity_post', 'email' );
	if ( $email_enabled ) {
		$activity = new BP_Activity_Activity( $activity_id );
		do_action( 'bpfn_after_add_notification', $notification_id, $notification_args, $activity, get_current_user_id() );
	}
	
	// Clean up - delete the test activity after a short delay
	wp_schedule_single_event( time() + 60, 'bpfn_cleanup_test_activity', array( $activity_id ) );
	
	return true;
}

/**
 * Get module instance
 *
 * @param string $module_name Module name
 * @return object|null Module instance or null
 */
function bpfn_get_module( $module_name ) {
	return bpfn()->get_module( $module_name );
}

/**
 * Register a custom module
 *
 * @param string $module_name Module name
 * @param object $module_instance Module instance
 * @return bool Success status
 */
function bpfn_register_module( $module_name, $module_instance ) {
	bpfn()->register_module( $module_name, $module_instance );
	
	// Trigger action
	do_action( 'bpfn_module_registered', $module_name, $module_instance );
	
	return true;
}

/**
 * Check if a feature is enabled
 *
 * @param string $feature Feature name
 * @return bool
 */
function bpfn_is_feature_enabled( $feature ) {
	$options = get_option( 'bpfn_options', array() );
	
	$features = array(
		'enhanced_notifications' => ! empty( $options['enable_enhanced_notifications'] ),
		'realtime_notifications' => true, // Always enabled if user has it enabled
		'email_notifications' => true, // Always enabled if user has it enabled
	);
	
	$enabled = isset( $features[ $feature ] ) ? $features[ $feature ] : false;
	
	return apply_filters( 'bpfn_is_feature_enabled', $enabled, $feature );
}

/**
 * Get plugin version
 *
 * @return string Plugin version
 */
function bpfn_get_version() {
	return BPFN_VERSION;
}

/**
 * Get plugin URL
 *
 * @param string $path Optional path to append
 * @return string Plugin URL
 */
function bpfn_get_plugin_url( $path = '' ) {
	$url = BPFN_PLUGIN_URL;
	
	if ( $path ) {
		$url .= ltrim( $path, '/' );
	}
	
	return $url;
}

/**
 * Get plugin path
 *
 * @param string $path Optional path to append
 * @return string Plugin path
 */
function bpfn_get_plugin_path( $path = '' ) {
	$plugin_path = BPFN_PLUGIN_PATH;
	
	if ( $path ) {
		$plugin_path .= ltrim( $path, '/' );
	}
	
	return $plugin_path;
}