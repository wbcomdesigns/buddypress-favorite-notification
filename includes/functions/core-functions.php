<?php
/**
 * Core functions for BuddyPress Favorite Notification
 *
 * @package BuddyPress_Favorite_Notification
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get user notification settings
 *
 * @param int $user_id User ID
 * @return array User notification settings
 */
function bpfn_get_user_settings( $user_id ) {
	global $wpdb;
	$table_name = $wpdb->prefix . 'bp_favorite_notification_prefs';
	
	// Default settings
	$defaults = apply_filters( 'bpfn_default_user_settings', array(
		'activity_post' => array(
			'is_enabled' => 1,
			'email_enabled' => 1,
			'realtime_enabled' => 1,
		),
		'activity_comment' => array(
			'is_enabled' => 1,
			'email_enabled' => 1,
			'realtime_enabled' => 1,
		),
		'activity_update' => array(
			'is_enabled' => 1,
			'email_enabled' => 1,
			'realtime_enabled' => 1,
		),
	) );
	
	// Get user settings from database
	$results = $wpdb->get_results( $wpdb->prepare(
		"SELECT notification_type, is_enabled, email_enabled, realtime_enabled 
		 FROM $table_name 
		 WHERE user_id = %d",
		$user_id
	), ARRAY_A );
	
	if ( empty( $results ) ) {
		return $defaults;
	}
	
	// Merge with defaults
	$settings = $defaults;
	foreach ( $results as $row ) {
		$type = $row['notification_type'];
		if ( isset( $settings[ $type ] ) ) {
			$settings[ $type ] = array(
				'is_enabled' => (int) $row['is_enabled'],
				'email_enabled' => (int) $row['email_enabled'],
				'realtime_enabled' => (int) $row['realtime_enabled'],
			);
		}
	}
	
	return apply_filters( 'bpfn_get_user_settings', $settings, $user_id );
}

/**
 * Save user notification settings
 *
 * @param int $user_id User ID
 * @param array $settings Settings to save
 * @return bool Success status
 */
function bpfn_save_user_settings( $user_id, $settings ) {
	global $wpdb;
	$table_name = $wpdb->prefix . 'bp_favorite_notification_prefs';
	
	// Allow filtering before save
	$settings = apply_filters( 'bpfn_before_save_user_settings', $settings, $user_id );
	
	$success = true;
	
	foreach ( $settings as $type => $options ) {
		$result = $wpdb->replace(
			$table_name,
			array(
				'user_id' => $user_id,
				'notification_type' => $type,
				'is_enabled' => isset( $options['is_enabled'] ) ? (int) $options['is_enabled'] : 1,
				'email_enabled' => isset( $options['email_enabled'] ) ? (int) $options['email_enabled'] : 1,
				'realtime_enabled' => isset( $options['realtime_enabled'] ) ? (int) $options['realtime_enabled'] : 1,
			),
			array( '%d', '%s', '%d', '%d', '%d' )
		);
		
		if ( false === $result ) {
			$success = false;
		}
	}
	
	// Clear any cached settings
	wp_cache_delete( 'bpfn_user_settings_' . $user_id, 'bpfn' );
	
	// Allow actions after save
	do_action( 'bpfn_after_save_user_settings', $user_id, $settings, $success );
	
	return $success;
}

/**
 * Get activity type
 *
 * @param int $activity_id Activity ID
 * @return string Activity type
 */
function bpfn_get_activity_type( $activity_id ) {
	$activity = new BP_Activity_Activity( $activity_id );
	
	if ( empty( $activity->id ) ) {
		return 'activity_post';
	}
	
	// Map activity types to our notification types
	$type_map = apply_filters( 'bpfn_activity_type_map', array(
		'activity_comment' => 'activity_comment',
		'activity_update' => 'activity_update',
	) );
	
	return isset( $type_map[ $activity->type ] ) ? $type_map[ $activity->type ] : 'activity_post';
}

/**
 * Check if user has notification type enabled
 *
 * @param int $user_id User ID
 * @param string $type Notification type
 * @param string $channel Notification channel (web, email, realtime)
 * @return bool
 */
function bpfn_is_notification_enabled( $user_id, $type, $channel = 'web' ) {
	$settings = bpfn_get_user_settings( $user_id );
	
	if ( ! isset( $settings[ $type ] ) ) {
		return true; // Default to enabled if not set
	}
	
	switch ( $channel ) {
		case 'email':
			return ! empty( $settings[ $type ]['email_enabled'] );
		case 'realtime':
			return ! empty( $settings[ $type ]['realtime_enabled'] );
		case 'web':
		default:
			return ! empty( $settings[ $type ]['is_enabled'] );
	}
}

/**
 * Get notification count for user
 *
 * @param int $user_id User ID
 * @param array $args Additional arguments
 * @return int Notification count
 */
function bpfn_get_notification_count( $user_id, $args = array() ) {
	if ( ! bp_is_active( 'notifications' ) ) {
		return 0;
	}
	
	global $bp;
	
	$defaults = array(
		'component_name' => isset( $bp->favorite_notifier ) ? $bp->favorite_notifier->id : 'favorite_notifier',
		'is_new' => 1,
	);
	
	$args = wp_parse_args( $args, $defaults );
	$args['user_id'] = $user_id;
	
	$notifications = BP_Notifications_Notification::get( $args );
	
	return count( $notifications );
}

/**
 * Get formatted notification data
 *
 * @param object $notification Notification object
 * @return array Formatted notification data
 */
function bpfn_format_notification_data( $notification ) {
	global $bp;
	
	// Check if component exists
	if ( ! isset( $bp->favorite_notifier ) || ! isset( $bp->favorite_notifier->notification_callback ) ) {
		return false;
	}
	
	// Get base data from notification callback
	$data = call_user_func(
		$bp->favorite_notifier->notification_callback,
		$notification->component_action,
		$notification->item_id,
		$notification->secondary_item_id,
		1,
		'array'
	);
	
	if ( ! is_array( $data ) ) {
		return false;
	}
	
	// Enhance with additional data
	$data['id'] = $notification->id;
	$data['date'] = $notification->date_notified;
	$data['is_new'] = $notification->is_new;
	
	return apply_filters( 'bpfn_format_notification_data', $data, $notification );
}

/**
 * Log notification events
 *
 * @param string $event Event type
 * @param array $data Event data
 */
function bpfn_log_event( $event, $data = array() ) {
	if ( ! apply_filters( 'bpfn_enable_logging', false ) ) {
		return;
	}
	
	$log_data = array(
		'event' => $event,
		'timestamp' => current_time( 'mysql' ),
		'user_id' => get_current_user_id(),
		'data' => $data,
	);
	
	do_action( 'bpfn_log_event', $log_data );
}