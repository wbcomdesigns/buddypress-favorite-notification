<?php
/**
 * Integration functions for BuddyPress Favorite Notification.
 *
 * @package BuddyPress_Favorite_Notification
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clear old notifications.
 *
 * @param int $days Number of days to keep (default 30).
 * @return array Result with count of deleted notifications.
 */
function bpfn_clear_old_notifications( $days = 30 ) {
	global $wpdb, $bp;

	if ( ! bp_is_active( 'notifications' ) ) {
		return array(
			'count' => 0,
			'error' => 'Notifications component not active',
		);
	}

	if ( empty( $bp->notifications->table_name ) ) {
		return array(
			'count' => 0,
			'error' => 'Notifications table is unavailable',
		);
	}

	$table     = $bp->notifications->table_name;
	$component = isset( $bp->favorite_notifier ) ? $bp->favorite_notifier->id : 'favorite_notifier';

	// Delete notifications older than X days that are read.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup query.
	$deleted = $wpdb->query(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from BP.
			"DELETE FROM {$table} WHERE component_name = %s AND is_new = 0 AND date_notified < DATE_SUB(NOW(), INTERVAL %d DAY)",
			$component,
			$days
		)
	);

	// $wpdb->query() returns false on a database error; surface it instead of
	// reporting a successful "0 cleared".
	if ( false === $deleted ) {
		return array(
			'count' => 0,
			'error' => 'Database error while clearing notifications',
		);
	}

	// Get remaining count.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Stats query.
	$remaining = $wpdb->get_var(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from BP.
			"SELECT COUNT(*) FROM {$table} WHERE component_name = %s",
			$component
		)
	);

	return array(
		'count'     => $deleted,
		'remaining' => $remaining,
	);
}

/**
 * Bulk update user settings.
 *
 * @param array $settings Settings to apply.
 * @return int Number of users updated.
 */
