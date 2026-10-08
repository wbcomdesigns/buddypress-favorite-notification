<?php
/**
 * BuddyPress Compatibility Functions.
 *
 * Registers the favorite_notifier pseudo-component. Creating and formatting
 * notifications lives only in BPFN_Module_Notifications.
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
 */
function bpfn_compat_setup_globals() {
	global $bp;

	if ( ! bp_is_active( 'notifications' ) ) {
		return;
	}

	$bp->favorite_notifier                        = new stdClass();
	$bp->favorite_notifier->id                    = 'favorite_notifier';
	$bp->favorite_notifier->slug                  = 'favorite_notification';
	$bp->favorite_notifier->notification_callback = 'bpfn_compat_format_notifications';

	$bp->active_components[ $bp->favorite_notifier->id ] = $bp->favorite_notifier->id;

	if ( ! isset( $bp->loaded_components[ $bp->favorite_notifier->id ] ) ) {
		$bp->loaded_components[ $bp->favorite_notifier->id ] = $bp->favorite_notifier->id;
	}

	do_action( 'bpfn_compat_setup_globals' );
}

/**
 * The component's notification_callback.
 *
 * Registered at bp_setup_globals, before modules load on bp_init, so it bridges
 * to the module at call time.
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
	$module = bpfn_get_module( 'notifications' );
	return $module ? $module->format_notification( $action, $item_id, $secondary_item_id, $total_items, $format, $id ) : false;
}

add_filter( 'bp_notifications_get_registered_components', 'bpfn_compat_register_component', 999 );

/**
 * List the component so BuddyPress queries and renders its notifications.
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
