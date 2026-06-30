<?php
/**
 * BuddyPress Compatibility Functions.
 *
 * Ensures the favorite_notifier component is properly registered.
 *
 * @package BuddyPress_Favorite_Notification
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'bp_setup_globals', 'bpfn_compat_setup_globals', 5 );

/**
 * Register the favorite_notifier component early in BuddyPress initialization.
 *
 * This ensures it's available when BuddyPress processes notifications.
 */
function bpfn_compat_setup_globals() {
	global $bp;

	// Don't proceed if notifications component is not active.
	if ( ! bp_is_active( 'notifications' ) ) {
		return;
	}

	// Set up the favorite_notifier component.
	$bp->favorite_notifier                        = new stdClass();
	$bp->favorite_notifier->id                    = 'favorite_notifier';
	$bp->favorite_notifier->slug                  = 'favorite_notification';
	$bp->favorite_notifier->notification_callback = 'bpfn_compat_format_notifications';

	// Register in active components - this is critical!
	$bp->active_components[ $bp->favorite_notifier->id ] = $bp->favorite_notifier->id;

	// Also ensure it's in loaded components.
	if ( ! isset( $bp->loaded_components[ $bp->favorite_notifier->id ] ) ) {
		$bp->loaded_components[ $bp->favorite_notifier->id ] = $bp->favorite_notifier->id;
	}

	do_action( 'bpfn_compat_setup_globals' );
}

/**
 * Format notifications callback.
 *
 * This bridges between the old callback style and the new modular system.
 *
 * @param string $action            The notification action.
 * @param int    $item_id           The item ID.
 * @param int    $secondary_item_id The secondary item ID.
 * @param int    $total_items       The total number of items.
 * @param string $format            The notification format.
 * @param int    $id                The notification id (passed by BuddyPress for mark-as-read).
 * @return string|array|false The formatted notification.
 */
function bpfn_compat_format_notifications( $action, $item_id, $secondary_item_id, $total_items, $format = 'string', $id = 0 ) {
	// Get the main plugin instance.
	$plugin = bpfn();

	// If we have the notifications module, use it.
	$notifications_module = $plugin ? $plugin->get_module( 'notifications' ) : null;
	if ( $notifications_module ) {
		return $notifications_module->format_notification( $action, $item_id, $secondary_item_id, $total_items, $format, $id );
	}

	// Fallback formatting if module not available.
	return bpfn_compat_fallback_format( $action, $item_id, $secondary_item_id, $total_items, $format, $id );
}

/**
 * Fallback notification formatter.
 *
 * @param string $action            The notification action.
 * @param int    $item_id           The item ID.
 * @param int    $secondary_item_id The secondary item ID.
 * @param int    $total_items       The total number of items.
 * @param string $format            The notification format.
 * @param int    $id                The notification id (for mark-as-read on click).
 * @return string|array|false The formatted notification.
 */
function bpfn_compat_fallback_format( $action, $item_id, $secondary_item_id, $total_items, $format = 'string', $id = 0 ) {
	$link = bp_activity_get_permalink( $item_id );
	if ( $id > 0 ) {
		$link = add_query_arg( 'rid', (int) $id, $link );
	}
	$text = '';

	// Check if this is a favorite notification.
	if ( 0 === strpos( $action, 'fav_notify_' ) || 0 === strpos( $action, 'fav_comment_notify_' ) ) {
		if ( $total_items > 1 ) {
			/* translators: %d: Number of members. */
			$text = sprintf( esc_html__( '%d members favorited your activity', 'buddypress-favorite-notification' ), $total_items );
		} else {
			$user_fullname = bp_core_get_user_displayname( $secondary_item_id );
			/* translators: %s: User display name. */
			$text = sprintf( esc_html__( '%s favorited your activity', 'buddypress-favorite-notification' ), $user_fullname );
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

add_filter( 'bp_notifications_get_registered_components', 'bpfn_compat_register_component', 999 );

/**
 * Hook into bp_notifications_get_registered_components to ensure our component is recognized.
 *
 * @param array $components The registered components.
 * @return array The modified components.
 */
function bpfn_compat_register_component( $components ) {
	if ( ! in_array( 'favorite_notifier', $components, true ) ) {
		$components[] = 'favorite_notifier';
	}
	return $components;
}

add_action( 'bp_activity_add_user_favorite', 'bpfn_compat_add_notification', 15, 2 );

/**
 * Ensure notifications are created when activity is favorited.
 *
 * This provides a safety net in case the module's hook doesn't fire.
 *
 * @param int $activity_id The activity ID.
 * @param int $user_id     The user ID.
 */
function bpfn_compat_add_notification( $activity_id, $user_id ) {
	// Check if notification was already created by the module.
	static $processed = array();
	$key              = $activity_id . '_' . $user_id;

	if ( isset( $processed[ $key ] ) ) {
		return;
	}

	$processed[ $key ] = true;

	// Get activity.
	$activity = new BP_Activity_Activity( $activity_id );
	if ( empty( $activity->id ) || (int) $activity->user_id === (int) $user_id ) {
		return;
	}

	// Check if we should create notification.
	$existing = BP_Notifications_Notification::get(
		array(
			'user_id'           => $activity->user_id,
			'item_id'           => $activity_id,
			'secondary_item_id' => $user_id,
			'component_name'    => 'favorite_notifier',
			'is_new'            => 1,
		)
	);

	if ( ! empty( $existing ) ) {
		return; // Notification already exists.
	}

	// Determine action based on activity type.
	$component_action = ( 'activity_comment' === $activity->type ) ?
		'fav_comment_notify_' . $activity_id :
		'fav_notify_' . $activity_id;

	// Add notification.
	bp_notifications_add_notification(
		array(
			'user_id'           => $activity->user_id,
			'item_id'           => $activity_id,
			'secondary_item_id' => $user_id,
			'component_name'    => 'favorite_notifier',
			'component_action'  => $component_action,
			'date_notified'     => bp_core_current_time(),
			'is_new'            => 1,
		)
	);
}

add_action( 'bp_init', 'bpfn_compat_verify_registration', 999 );

/**
 * Debug helper to verify component registration.
 */
function bpfn_compat_verify_registration() {
	global $bp;

	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		// Log component status.
		if ( isset( $bp->favorite_notifier ) && isset( $bp->active_components['favorite_notifier'] ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
			error_log( 'BPFN Compat: Component successfully registered' );
		} else {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
			error_log( 'BPFN Compat: Component registration failed!' );

			// Try to fix it.
			if ( ! isset( $bp->favorite_notifier ) ) {
				bpfn_compat_setup_globals();
			}
		}
	}
}
