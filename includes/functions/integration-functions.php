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
			'error' => __( 'Notifications component not active', 'buddypress-favorite-notification' ),
		);
	}

	if ( empty( $bp->notifications->table_name ) ) {
		return array(
			'count' => 0,
			'error' => __( 'Notifications table is unavailable', 'buddypress-favorite-notification' ),
		);
	}

	$table     = $bp->notifications->table_name;
	$component = isset( $bp->favorite_notifier ) ? $bp->favorite_notifier->id : 'favorite_notifier';

	// date_notified is GMT (bp_core_current_time()), so compare against
	// UTC_TIMESTAMP(); NOW() is the MySQL server clock and skews by its offset.
	// Read notifications only - `is_new = 0` is deliberate. Deleting unread ones
	// destroys notifications the member has never seen. It also makes "0 cleared"
	// a normal result on a quiet site, which is why the caller reports the rule
	// alongside the number.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup query.
	$deleted = $wpdb->query(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from BP.
			"DELETE FROM {$table} WHERE component_name = %s AND is_new = 0 AND date_notified < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)",
			$component,
			$days
		)
	);

	// $wpdb->query() returns false on a database error; surface it instead of
	// reporting a successful "0 cleared".
	if ( false === $deleted ) {
		return array(
			'count' => 0,
			'error' => __( 'Database error while clearing notifications', 'buddypress-favorite-notification' ),
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
		'count'     => (int) $deleted,
		'remaining' => (int) $remaining,
		'days'      => (int) $days,
	);
}

/**
 * Bulk update user settings.
 *
 * @param array $settings Settings to apply.
 * @return int Number of users updated.
 */
