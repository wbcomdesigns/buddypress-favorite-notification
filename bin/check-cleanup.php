<?php
/**
 * Self-check for the retention gate and cleanup window.
 *
 * Run: wp eval-file wp-content/plugins/buddypress-favorite-notification/bin/check-cleanup.php
 * Writes inside a rolled-back transaction, so it leaves no data behind.
 * Excluded from the release zip (Gruntfile '!bin/**').
 *
 * @package BuddyPress_Favorite_Notification
 */

defined( 'ABSPATH' ) || exit;

global $wpdb, $bp;

// Retention gate: only the offered set persists, anything else falls back to 30.
bpfn_check_eq( 30, BPFN_Module_Admin::get_retention_days( 3650 ), 'out-of-range upper' );
bpfn_check_eq( 30, BPFN_Module_Admin::get_retention_days( 3 ), 'out-of-range lower' );
bpfn_check_eq( 60, BPFN_Module_Admin::get_retention_days( '60' ), 'string from POST' );

// Cleanup window is measured in GMT. Only catches clock skew on a host whose MySQL
// timezone is ahead of UTC by more than ~2.4h (keep row sits 0.1 day inside).
$table = $bp->notifications->table_name;
$wpdb->query( 'START TRANSACTION' );
$ids = array();
foreach ( array( 'keep' => 29.9, 'drop' => 30.25 ) as $label => $age ) {
	$wpdb->insert(
		$table,
		array(
			'user_id'           => 1,
			'item_id'           => 0,
			'secondary_item_id' => 0,
			'component_name'    => 'favorite_notifier',
			'component_action'  => 'bpfn_self_check',
			'date_notified'     => gmdate( 'Y-m-d H:i:s', time() - (int) ( $age * DAY_IN_SECONDS ) ),
			'is_new'            => 0,
		)
	);
	$ids[ $label ] = $wpdb->insert_id;
}
bpfn_clear_old_notifications( 30 );
$exists = static function ( $id ) use ( $wpdb, $table ) {
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
};
$keep = $exists( $ids['keep'] );
$drop = $exists( $ids['drop'] );
$wpdb->query( 'ROLLBACK' );
bpfn_check_eq( 1, $keep, 'row inside the window survives' );
bpfn_check_eq( 0, $drop, 'row past the window is deleted' );

WP_CLI::success( 'cleanup self-check passed' );

/**
 * Fail loudly on a mismatch.
 *
 * @param mixed  $expected Expected value.
 * @param mixed  $actual   Actual value.
 * @param string $what     Label.
 */
function bpfn_check_eq( $expected, $actual, $what ) {
	if ( $expected !== $actual ) {
		WP_CLI::error( sprintf( '%s: expected %s, got %s', $what, var_export( $expected, true ), var_export( $actual, true ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
	}
}
