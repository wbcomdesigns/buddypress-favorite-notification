<?php
/**
 * Integration functions for BuddyPress Favorite Notification
 *
 * @package BuddyPress_Favorite_Notification
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get notification statistics
 *
 * @param string $period Period to get stats for (all, week, month, year)
 * @return array Statistics data
 */
function bpfn_get_notification_stats( $period = 'all' ) {
	global $wpdb, $bp;
	
	$stats = array(
		'total_notifications' => 0,
		'unread_notifications' => 0,
		'active_users' => 0,
		'most_favorited' => array(),
	);
	
	if ( ! bp_is_active( 'notifications' ) ) {
		return $stats;
	}
	
	$table = $bp->notifications->table_name;
	$component = isset( $bp->favorite_notifier ) ? $bp->favorite_notifier->id : 'favorite_notifier';
	
	// Get total notifications
	$stats['total_notifications'] = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$table} WHERE component_name = %s",
		$component
	) );
	
	// Get unread notifications
	$stats['unread_notifications'] = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$table} WHERE component_name = %s AND is_new = 1",
		$component
	) );
	
	// Get active users (users with notifications)
	$stats['active_users'] = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(DISTINCT user_id) FROM {$table} WHERE component_name = %s",
		$component
	) );
	
	return apply_filters( 'bpfn_notification_stats', $stats, $period );
}

/**
 * Get chart data for admin dashboard
 *
 * @param int $days Number of days to get data for
 * @return array Chart data
 */
function bpfn_get_chart_data( $days = 7 ) {
	global $wpdb, $bp;
	
	if ( ! bp_is_active( 'notifications' ) ) {
		return array( 'labels' => array(), 'values' => array() );
	}
	
	$table = $bp->notifications->table_name;
	$component = isset( $bp->favorite_notifier ) ? $bp->favorite_notifier->id : 'favorite_notifier';
	
	$data = array(
		'labels' => array(),
		'values' => array(),
	);
	
	// Get data for last X days
	for ( $i = $days - 1; $i >= 0; $i-- ) {
		$date = date( 'Y-m-d', strtotime( "-{$i} days" ) );
		$data['labels'][] = date( 'M j', strtotime( $date ) );
		
		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} 
			WHERE component_name = %s 
			AND DATE(date_notified) = %s",
			$component,
			$date
		) );
		
		$data['values'][] = (int) $count;
	}
	
	return $data;
}

/**
 * Check if required database tables exist
 *
 * @return array Table existence status
 */
function bpfn_check_tables() {
	global $wpdb;
	
	$tables = array(
		'preferences' => $wpdb->prefix . 'bp_favorite_notification_prefs',
	);
	
	$results = array();
	
	foreach ( $tables as $key => $table ) {
		$results[ $key ] = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table;
	}
	
	// Also check BP notifications table
	if ( bp_is_active( 'notifications' ) ) {
		global $bp;
		$results['notifications'] = $wpdb->get_var( "SHOW TABLES LIKE '{$bp->notifications->table_name}'" ) === $bp->notifications->table_name;
	}
	
	return $results;
}

/**
 * Repair database tables
 *
 * @return bool Success status
 */
function bpfn_repair_tables() {
	// Trigger activation to recreate tables
	$plugin = bpfn();
	if ( method_exists( $plugin, 'activate' ) ) {
		$plugin->activate();
		return true;
	}
	
	return false;
}

/**
 * Send test email
 *
 * @param int $user_id User ID to send to
 * @param string $type Email type
 * @return bool Success status
 */
function bpfn_send_test_email( $user_id, $type = 'activity_favorited' ) {
	$email_module = bpfn()->get_module( 'email' );
	
	if ( ! $email_module ) {
		error_log( 'BPFN: Email module not found' );
		return false;
	}
	
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		error_log( 'BPFN: User not found' );
		return false;
	}
	
	// Get current user as the favoriter
	$current_user = wp_get_current_user();
	
	// Prepare test email data
	$email_data = array(
		'to' => $user->user_email,
		'subject' => '[Test] ' . get_bloginfo( 'name' ) . ' - Favorite Notification Test',
		'template' => 'emails/' . str_replace( '_', '-', $type ) . '.php',
		'tokens' => array(
			'site_name' => get_bloginfo( 'name' ),
			'site_url' => home_url(),
			'user_name' => $user->display_name,
			'recipient_name' => $user->display_name,
			'activity_content' => __( 'This is a test activity content to show how your notifications will look when someone favorites your activity posts.', 'bp-fav-notification' ),
			'activity_link' => home_url(),
			'settings_link' => bp_is_active( 'settings' ) ? bp_core_get_user_domain( $user_id ) . bp_get_settings_slug() . '/notifications/' : home_url(),
			'favorited_by' => $current_user->display_name,
			'favorited_by_link' => bp_core_get_user_domain( get_current_user_id() ),
			'secondary_item_id' => get_current_user_id(), // For avatar in template
		),
	);
	
	// Try to call send_test_email if it exists
	if ( method_exists( $email_module, 'send_test_email' ) ) {
		return $email_module->send_test_email( $email_data );
	}
	
	// Try reflection to access private method
	try {
		$reflection = new ReflectionMethod( $email_module, 'send_email' );
		$reflection->setAccessible( true );
		return $reflection->invoke( $email_module, $email_data );
	} catch ( Exception $e ) {
		error_log( 'BPFN: Failed to use reflection - ' . $e->getMessage() );
		
		// If reflection fails, try direct email approach
		$subject = '[Test] ' . get_bloginfo( 'name' ) . ' - Favorite Notification Test';
		
		// Build simple HTML email
		$message = sprintf(
			'<h2>%s</h2><p>%s</p><p>%s</p>',
			sprintf( __( 'Hi %s!', 'bp-fav-notification' ), $user->display_name ),
			__( 'This is a test email from BuddyPress Favorite Notification plugin.', 'bp-fav-notification' ),
			sprintf( __( 'If you received this email, your email notifications are working correctly.', 'bp-fav-notification' ) )
		);
		
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		
		return wp_mail( $user->user_email, $subject, $message, $headers );
	}
}



/**
 * Bulk update user settings
 *
 * @param array $settings Settings to apply
 * @return int Number of users updated
 */
function bpfn_bulk_update_settings( $settings ) {
	global $wpdb;
	
	// Get all users
	$users = get_users( array( 'fields' => 'ID' ) );
	$updated = 0;
	
	foreach ( $users as $user_id ) {
		if ( bpfn_save_user_settings( $user_id, $settings ) ) {
			$updated++;
		}
	}
	
	return $updated;
}

/**
 * Get system diagnostics
 *
 * @return array Diagnostic information
 */
function bpfn_get_diagnostics() {
	global $wpdb, $wp_version;
	
	$diagnostics = array(
		'environment' => array(
			'php_version' => PHP_VERSION,
			'wp_version' => $wp_version,
			'bp_version' => defined( 'BP_VERSION' ) ? BP_VERSION : 'Not installed',
			'plugin_version' => BPFN_VERSION,
			'memory_limit' => ini_get( 'memory_limit' ),
			'max_execution_time' => ini_get( 'max_execution_time' ),
		),
		'tables' => bpfn_check_tables(),
		'stats' => bpfn_get_notification_stats( 'all' ),
		'components' => array(
			'notifications' => bp_is_active( 'notifications' ),
			'activity' => bp_is_active( 'activity' ),
			'settings' => bp_is_active( 'settings' ),
		),
		'options' => get_option( 'bpfn_options', array() ),
	);
	
	return apply_filters( 'bpfn_diagnostics', $diagnostics );
}

// Add cleanup action
add_action( 'bpfn_cleanup_test_activity', function( $activity_id ) {
	bp_activity_delete( array( 'id' => $activity_id ) );
} );