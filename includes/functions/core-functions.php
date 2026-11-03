<?php
/**
 * Core functions for BuddyPress Favorite Notification.
 *
 * Member preferences live in ONE store, {prefix}bp_favorite_notification_prefs.
 * Everything that reads or writes them goes through bpfn_get_user_settings() /
 * bpfn_save_user_settings().
 *
 * @package BuddyPress_Favorite_Notification
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * User-meta keys BuddyPress writes for our email rows and unsubscribe links,
 * keyed by preference type.
 *
 * BuddyPress core saves the Settings > Notifications rows and handles email
 * unsubscribe links by writing these keys to user meta. BPFN_Module_Settings
 * mirrors those writes into the prefs table, and bpfn_save_user_settings()
 * writes them back, so the two never disagree.
 *
 * @return array
 */
function bpfn_email_meta_keys() {
	return array(
		'activity_post'    => 'favorite_activity',
		'activity_comment' => 'favorite_activity_comment',
	);
}

/**
 * Get user notification settings.
 *
 * @param int $user_id User ID.
 * @return array User notification settings.
 */
function bpfn_get_user_settings( $user_id ) {
	global $wpdb;

	$user_id  = (int) $user_id;
	$defaults = apply_filters(
		'bpfn_default_user_settings',
		array(
			'activity_post'    => array(
				'is_enabled'       => 1,
				'email_enabled'    => 1,
				'realtime_enabled' => 1,
			),
			'activity_comment' => array(
				'is_enabled'       => 1,
				'email_enabled'    => 1,
				'realtime_enabled' => 1,
			),
		)
	);

	// Read on every favorite, notification render and heartbeat, so cache per user.
	$results = wp_cache_get( 'bpfn_user_settings_' . $user_id, 'bpfn' );
	if ( false === $results ) {
		$table_name = $wpdb->prefix . 'bp_favorite_notification_prefs';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table, cached above.
		$results = (array) $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
				"SELECT notification_type, is_enabled, email_enabled, realtime_enabled FROM {$table_name} WHERE user_id = %d",
				$user_id
			),
			ARRAY_A
		);
		wp_cache_set( 'bpfn_user_settings_' . $user_id, $results, 'bpfn' );
	}

	$settings = $defaults;
	foreach ( $results as $row ) {
		$type = $row['notification_type'];
		if ( isset( $settings[ $type ] ) ) {
			$settings[ $type ] = array(
				'is_enabled'       => (int) $row['is_enabled'],
				'email_enabled'    => (int) $row['email_enabled'],
				'realtime_enabled' => (int) $row['realtime_enabled'],
			);
		}
	}

	return apply_filters( 'bpfn_get_user_settings', $settings, $user_id );
}

/**
 * Save user notification settings.
 *
 * @param int   $user_id  User ID.
 * @param array $settings Settings to save, keyed by preference type.
 * @return bool Success status.
 */
function bpfn_save_user_settings( $user_id, $settings ) {
	global $wpdb;

	$user_id    = (int) $user_id;
	$table_name = $wpdb->prefix . 'bp_favorite_notification_prefs';
	$settings   = apply_filters( 'bpfn_before_save_user_settings', $settings, $user_id );
	$success    = true;

	foreach ( $settings as $type => $options ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write; cache cleared below.
		$result = $wpdb->replace(
			$table_name,
			array(
				'user_id'           => $user_id,
				'notification_type' => $type,
				'is_enabled'        => isset( $options['is_enabled'] ) ? (int) $options['is_enabled'] : 1,
				'email_enabled'     => isset( $options['email_enabled'] ) ? (int) $options['email_enabled'] : 1,
				'realtime_enabled'  => isset( $options['realtime_enabled'] ) ? (int) $options['realtime_enabled'] : 1,
			),
			array( '%d', '%s', '%d', '%d', '%d' )
		);

		if ( false === $result ) {
			$success = false;
		}
	}

	wp_cache_delete( 'bpfn_user_settings_' . $user_id, 'bpfn' );

	// Keep BuddyPress's copy in step: its unsubscribe handler skips a member whose
	// meta already reads "no", so a stale "no" would make a later unsubscribe a no-op.
	foreach ( bpfn_email_meta_keys() as $type => $meta_key ) {
		if ( isset( $settings[ $type ]['email_enabled'] ) ) {
			bp_update_user_meta( $user_id, $meta_key, $settings[ $type ]['email_enabled'] ? 'yes' : 'no' );
		}
	}

	do_action( 'bpfn_after_save_user_settings', $user_id, $settings, $success );

	return $success;
}

/**
 * Map a BuddyPress activity onto a preference type (activity_post or activity_comment).
 *
 * The single resolver used by notifications, emails and real-time, so the three
 * channels always check the same preference key.
 *
 * @param BP_Activity_Activity|int $activity Activity object or ID.
 * @return string Preference type.
 */
function bpfn_get_activity_type( $activity ) {
	if ( ! $activity instanceof BP_Activity_Activity ) {
		$activity = new BP_Activity_Activity( (int) $activity );
	}

	if ( empty( $activity->id ) ) {
		return 'activity_post';
	}

	// Keep this map in step with BPFN_Module_Settings::get_notification_types().
	$type_map = apply_filters(
		'bpfn_activity_type_map',
		array(
			'activity_comment' => 'activity_comment',
			'activity_update'  => 'activity_post',
		)
	);

	return isset( $type_map[ $activity->type ] ) ? $type_map[ $activity->type ] : 'activity_post';
}

/**
 * Check if a notification type is enabled for a user on a channel.
 *
 * @param int    $user_id User ID.
 * @param string $type    Preference type.
 * @param string $channel web, email or realtime.
 * @return bool Whether enabled.
 */
function bpfn_is_notification_enabled( $user_id, $type, $channel = 'web' ) {
	// NOTE: there used to be a `defined( 'DOING_AJAX' ) return true` short-circuit
	// here. BuddyPress favouriting always posts through admin-ajax, so it made every
	// caller ignore the member's choice. Do not reintroduce it.
	$settings = bpfn_get_user_settings( $user_id );

	if ( ! isset( $settings[ $type ] ) ) {
		return true;
	}

	$keys = array(
		'email'    => 'email_enabled',
		'realtime' => 'realtime_enabled',
	);
	$key  = isset( $keys[ $channel ] ) ? $keys[ $channel ] : 'is_enabled';

	return ! empty( $settings[ $type ][ $key ] );
}

/**
 * Count of unread favorite notifications for a user.
 *
 * @param int   $user_id User ID.
 * @param array $args    Additional arguments.
 * @return int Notification count.
 */
function bpfn_get_notification_count( $user_id, $args = array() ) {
	if ( ! bp_is_active( 'notifications' ) ) {
		return 0;
	}

	$args            = wp_parse_args(
		$args,
		array(
			'component_name' => 'favorite_notifier',
			'is_new'         => 1,
		)
	);
	$args['user_id'] = $user_id;

	return (int) BP_Notifications_Notification::get_total_count( $args );
}

/**
 * Get formatted notification data.
 *
 * @param object $notification Notification object.
 * @return array|false Formatted notification data.
 */
function bpfn_format_notification_data( $notification ) {
	$data = bpfn_compat_format_notifications(
		$notification->component_action,
		$notification->item_id,
		$notification->secondary_item_id,
		1,
		'array',
		$notification->id
	);

	if ( ! is_array( $data ) ) {
		return false;
	}

	$data['id']     = $notification->id;
	$data['date']   = $notification->date_notified;
	$data['is_new'] = $notification->is_new;

	return apply_filters( 'bpfn_format_notification_data', $data, $notification );
}
