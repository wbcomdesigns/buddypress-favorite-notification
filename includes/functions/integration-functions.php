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
 * Send test notification
 *
 * @param int $user_id User ID to send to
 * @param string $type Notification type
 * @return bool Success status
 */
function bpfn_send_test_notification( $user_id, $type = 'favorite' ) {
	if ( ! $user_id ) {
		error_log( 'BPFN: No user ID provided for test notification' );
		return false;
	}
	
	if ( ! bp_is_active( 'notifications' ) ) {
		error_log( 'BPFN: Notifications component is not active' );
		return false;
	}
	
	if ( ! bp_is_active( 'activity' ) ) {
		error_log( 'BPFN: Activity component is not active' );
		return false;
	}
	
	// Create a test activity for the user
	$activity_args = array(
		'user_id'       => $user_id,
		'content'       => sprintf( __( 'Test activity for notification testing created at %s', 'bp-fav-notification' ), current_time( 'mysql' ) ),
		'component'     => 'activity',
		'type'          => 'activity_update',
		'recorded_time' => bp_core_current_time(),
		'hide_sitewide' => true, // Hide from main activity stream
	);
	
	// Make sure we're using the correct function
	if ( ! function_exists( 'bp_activity_add' ) ) {
		error_log( 'BPFN: bp_activity_add function does not exist' );
		return false;
	}
	
	$activity_id = bp_activity_add( $activity_args );
	
	if ( ! $activity_id ) {
		error_log( 'BPFN: Failed to create test activity - bp_activity_add returned false' );
		// Try with minimal args
		$activity_id = bp_activity_post_update( array(
			'user_id' => $user_id,
			'content' => 'Test activity for notification'
		) );
		
		if ( ! $activity_id ) {
			error_log( 'BPFN: Also failed with bp_activity_post_update' );
			return false;
		}
	}
	
	error_log( 'BPFN: Created test activity with ID: ' . $activity_id );
	
	// Add the notification directly
	global $bp;
	
	// Make sure the component is set up
	if ( ! isset( $bp->favorite_notifier ) ) {
		// Try to set it up manually
		$bp->favorite_notifier = new stdClass();
		$bp->favorite_notifier->id = 'favorite_notifier';
		error_log( 'BPFN: Had to manually set up favorite_notifier component' );
	}
	
	$notification_args = array(
		'user_id'           => $user_id,
		'item_id'           => $activity_id,
		'secondary_item_id' => get_current_user_id(),
		'component_name'    => $bp->favorite_notifier->id,
		'component_action'  => 'fav_notify_' . $activity_id,
		'date_notified'     => bp_core_current_time(),
		'is_new'            => 1,
	);
	
	$notification_id = bp_notifications_add_notification( $notification_args );
	
	if ( ! $notification_id ) {
		error_log( 'BPFN: Failed to create notification - bp_notifications_add_notification returned false' );
		// Clean up the activity
		bp_activity_delete( array( 'id' => $activity_id ) );
		return false;
	}
	
	error_log( 'BPFN: Created notification with ID: ' . $notification_id );
	
	// Trigger email if enabled
	$email_enabled = bpfn_is_notification_enabled( $user_id, 'activity_post', 'email' );
	if ( $email_enabled ) {
		$activity = new BP_Activity_Activity( $activity_id );
		if ( $activity->id ) {
			do_action( 'bpfn_after_add_notification', $notification_id, $notification_args, $activity, get_current_user_id() );
			error_log( 'BPFN: Triggered email action for notification' );
		}
	}
	
	// Clean up - delete the test activity after a short delay
	wp_schedule_single_event( time() + 60, 'bpfn_cleanup_test_activity', array( $activity_id ) );
	
	return true;
}

/**
 * Send test email
 *
 * @param int $user_id User ID to send to
 * @param string $type Email type
 * @return bool Success status
 */
function bpfn_send_test_email( $user_id, $type = 'activity_favorited' ) {
	// First, let's check if we can access the email module
	$email_module = bpfn()->get_module( 'email' );
	
	if ( ! $email_module ) {
		error_log( 'BPFN: Email module not found - trying direct approach' );
		// Fall back to direct email
		return bpfn_send_test_email_direct( $user_id, $type );
	}
	
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		error_log( 'BPFN: User not found for ID: ' . $user_id );
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
			'secondary_item_id' => get_current_user_id(),
		),
	);
	
	// Try to send using the email module
	if ( method_exists( $email_module, 'send_test_email' ) ) {
		return $email_module->send_test_email( $email_data );
	}
	
	// Try using reflection to access the private send_email method
	try {
		$reflection = new ReflectionMethod( $email_module, 'send_email' );
		$reflection->setAccessible( true );
		$result = $reflection->invoke( $email_module, $email_data );
		error_log( 'BPFN: Sent email using reflection method' );
		return $result;
	} catch ( Exception $e ) {
		error_log( 'BPFN: Failed to use reflection - ' . $e->getMessage() );
		// Fall back to direct email
		return bpfn_send_test_email_direct( $user_id, $type );
	}
}

/**
 * Send test email directly
 *
 * @param int $user_id User ID to send to
 * @param string $type Email type
 * @return bool Success status
 */
function bpfn_send_test_email_direct( $user_id, $type = 'activity_favorited' ) {
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return false;
	}
	
	$current_user = wp_get_current_user();
	
	// Email subject
	$subject = '[Test] ' . get_bloginfo( 'name' ) . ' - Favorite Notification Test';
	
	// Build HTML email based on type
	if ( $type === 'comment_favorited' ) {
		$title = sprintf( __( '%s favorited your comment', 'bp-fav-notification' ), $current_user->display_name );
		$content_label = __( 'Your Comment:', 'bp-fav-notification' );
	} else {
		$title = sprintf( __( '%s favorited your activity', 'bp-fav-notification' ), $current_user->display_name );
		$content_label = __( 'Your Activity:', 'bp-fav-notification' );
	}
	
	// Build HTML message
	$message = '
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<style>
		body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
		.container { max-width: 600px; margin: 0 auto; padding: 20px; }
		.header { background: #ff7b00; color: white; padding: 20px; text-align: center; }
		.content { background: #fff; padding: 30px; }
		.button { display: inline-block; padding: 12px 30px; background: #ff7b00; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
		.footer { background: #f4f4f4; padding: 20px; text-align: center; font-size: 12px; }
		.activity-box { background: #f8f9fa; border-left: 4px solid #ff7b00; padding: 15px; margin: 20px 0; }
	</style>
</head>
<body>
	<div class="container">
		<div class="header">
			<h1>' . get_bloginfo( 'name' ) . '</h1>
		</div>
		<div class="content">
			<h2>' . sprintf( __( 'Hi %s!', 'bp-fav-notification' ), $user->display_name ) . '</h2>
			<p><strong>' . __( 'This is a test email from BuddyPress Favorite Notification plugin.', 'bp-fav-notification' ) . '</strong></p>
			<p>' . $title . '</p>
			
			<h3>' . $content_label . '</h3>
			<div class="activity-box">
				' . __( 'This is a test activity content to show how your notifications will look when someone favorites your activity posts.', 'bp-fav-notification' ) . '
			</div>
			
			<p style="text-align: center;">
				<a href="' . home_url() . '" class="button">' . __( 'View Activity', 'bp-fav-notification' ) . '</a>
			</p>
			
			<p>' . __( 'If you received this email, your email notifications are working correctly!', 'bp-fav-notification' ) . '</p>
		</div>
		<div class="footer">
			<p>' . __( 'You received this test email because you initiated a test from the admin panel.', 'bp-fav-notification' ) . '</p>
			<p><a href="' . home_url() . '">' . get_bloginfo( 'name' ) . '</a></p>
		</div>
	</div>
</body>
</html>';
	
	// Set headers for HTML email
	$headers = array(
		'Content-Type: text/html; charset=UTF-8',
		'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>',
	);
	
	// Send the email
	$result = wp_mail( $user->user_email, $subject, $message, $headers );
	
	if ( $result ) {
		error_log( 'BPFN: Test email sent successfully using direct method to ' . $user->user_email );
	} else {
		error_log( 'BPFN: Failed to send test email using direct method' );
	}
	
	return $result;
}

/**
 * Clear old notifications
 *
 * @param int $days Number of days to keep (default 30)
 * @return array Result with count of deleted notifications
 */
function bpfn_clear_old_notifications( $days = 30 ) {
	global $wpdb, $bp;
	
	if ( ! bp_is_active( 'notifications' ) ) {
		return array( 'count' => 0, 'error' => 'Notifications component not active' );
	}
	
	$table = $bp->notifications->table_name;
	$component = isset( $bp->favorite_notifier ) ? $bp->favorite_notifier->id : 'favorite_notifier';
	
	// Delete notifications older than X days that are read
	$deleted = $wpdb->query( $wpdb->prepare(
		"DELETE FROM {$table} 
		WHERE component_name = %s 
		AND is_new = 0 
		AND date_notified < DATE_SUB(NOW(), INTERVAL %d DAY)",
		$component,
		$days
	) );
	
	// Get remaining count
	$remaining = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$table} WHERE component_name = %s",
		$component
	) );
	
	return array(
		'count' => $deleted,
		'remaining' => $remaining,
	);
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
	global $wpdb, $wp_version, $bp;
	
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
	
	// Check if favorite_notifier is set up
	$diagnostics['favorite_notifier_setup'] = isset( $bp->favorite_notifier );
	
	return apply_filters( 'bpfn_diagnostics', $diagnostics );
}

// Add cleanup action
add_action( 'bpfn_cleanup_test_activity', function( $activity_id ) {
	if ( function_exists( 'bp_activity_delete' ) ) {
		bp_activity_delete( array( 'id' => $activity_id ) );
		error_log( 'BPFN: Cleaned up test activity ID: ' . $activity_id );
	}
} );