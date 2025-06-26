<?php
/**
 * Notifications Module for BuddyPress Favorite Notification
 *
 * @package BuddyPress_Favorite_Notification
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Notifications Module Class
 */
class BPFN_Module_Notifications {

	/**
	 * Notification types
	 */
	private $notification_types = array();

	/**
	 * Flag to prevent infinite loops
	 */
	private $fixing_display_name = false;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->register_default_types();
		$this->setup_hooks();
	}

	/**
	 * Register default notification types
	 */
	private function register_default_types() {
		$this->notification_types = array(
			'favorite' => array(
				'labels' => array(
					'single' => __( '%s favorited your activity', 'bp-fav-notification' ),
					'multiple' => __( '%d people favorited your activity', 'bp-fav-notification' ),
				),
				'action_prefix' => 'fav_notify',
			),
			'favorite_comment' => array(
				'labels' => array(
					'single' => __( '%s favorited your comment', 'bp-fav-notification' ),
					'multiple' => __( '%d people favorited your comment', 'bp-fav-notification' ),
				),
				'action_prefix' => 'fav_comment_notify',
			),
		);
	}

	/**
	 * Setup hooks
	 */
	private function setup_hooks() {
		// Activity favorite hook
		add_action( 'bp_activity_add_user_favorite', array( $this, 'add_favorite_notification' ), 10, 2 );
		add_action( 'bp_activity_remove_user_favorite', array( $this, 'remove_favorite_notification' ), 10, 2 );
		
		// Format notifications
		add_filter( 'bp_notifications_get_notifications_for_user', array( $this, 'format_notifications' ), 10, 8 );
		
		// Fix display names - with lower priority to avoid conflicts
		add_filter( 'bp_core_get_user_displayname', array( $this, 'fix_user_displayname' ), 20, 2 );
	}

	/**
	 * Add notification when activity is favorited
	 */
	public function add_favorite_notification( $activity_id, $user_id ) {
		global $bp;
		
		// Ensure component is initialized
		if ( ! isset( $bp->favorite_notifier ) || ! isset( $bp->favorite_notifier->id ) ) {
			// Try to initialize it
			if ( function_exists( 'bpfn' ) ) {
				$plugin = bpfn();
				if ( method_exists( $plugin, 'setup_globals' ) ) {
					$plugin->setup_globals();
				}
			}
			
			// Check again
			if ( ! isset( $bp->favorite_notifier ) || ! isset( $bp->favorite_notifier->id ) ) {
				error_log( 'BPFN: Component not initialized, cannot add notification' );
				return;
			}
		}
		
		// Get activity
		$activity = new BP_Activity_Activity( $activity_id );
		if ( empty( $activity->id ) || $activity->user_id == $user_id ) {
			return;
		}

		// Check if notifications are enabled
		$activity_type = $this->get_activity_notification_type( $activity );
		if ( ! bpfn_is_notification_enabled( $activity->user_id, $activity_type, 'web' ) ) {
			return;
		}

		// Add notification
		$notification_id = bp_notifications_add_notification( array(
			'user_id'           => $activity->user_id,
			'item_id'           => $activity_id,
			'secondary_item_id' => $user_id,
			'component_name'    => $bp->favorite_notifier->id,
			'component_action'  => $this->get_component_action( $activity ),
			'date_notified'     => bp_core_current_time(),
			'is_new'            => 1,
		) );

		// Trigger action for other modules
		if ( $notification_id ) {
			do_action( 'bpfn_after_add_notification', $notification_id, array(
				'activity_id' => $activity_id,
				'user_id' => $activity->user_id,
				'secondary_item_id' => $user_id,
			), $activity, $user_id );
		}
	}

	/**
	 * Remove notification when activity is unfavorited
	 */
	public function remove_favorite_notification( $activity_id, $user_id ) {
		global $bp;
		
		// Ensure component is initialized
		if ( ! isset( $bp->favorite_notifier ) || ! isset( $bp->favorite_notifier->id ) ) {
			return;
		}
		
		BP_Notifications_Notification::delete( array(
			'item_id'           => $activity_id,
			'secondary_item_id' => $user_id,
			'component_name'    => $bp->favorite_notifier->id,
		) );
	}

	/**
	 * Format notifications - Fixed parameter order
	 */
	public function format_notifications( $action, $item_id, $secondary_item_id, $total_items, $format, $component_action, $component_name, $id ) {
		global $bp;
		
		// Check if this is our component
		if ( ! isset( $bp->favorite_notifier ) || $component_name !== $bp->favorite_notifier->id ) {
			return $action;
		}

		// Format and return our notification
		$formatted = $this->format_notification( $component_action, $item_id, $secondary_item_id, $total_items, $format );
		
		// If we have a valid formatted notification, return it
		if ( $formatted !== false ) {
			return $formatted;
		}
		
		// Otherwise return the original action
		return $action;
	}

	/**
	 * Format a single notification
	 */
	public function format_notification( $action, $item_id, $secondary_item_id, $total_items, $format = 'string' ) {
		// Get activity
		$activity = new BP_Activity_Activity( $item_id );
		if ( empty( $activity->id ) ) {
			return false;
		}

		// Determine notification type
		$type = strpos( $action, 'fav_comment_notify' ) !== false ? 'favorite_comment' : 'favorite';
		$config = $this->notification_types[ $type ];

		// Format text
		if ( $total_items > 1 ) {
			$text = sprintf( $config['labels']['multiple'], $total_items );
		} else {
			$user_name = $this->get_user_displayname_safe( $secondary_item_id );
			$text = sprintf( $config['labels']['single'], $user_name );
		}

		// Build link - pass activity ID, not the object
		$link = bp_activity_get_permalink( $item_id );

		// Return formatted notification
		if ( 'string' === $format ) {
			return apply_filters( 'bpfn_notification_string', '<a href="' . esc_url( $link ) . '">' . esc_html( $text ) . '</a>', $item_id, $secondary_item_id, $total_items );
		}

		// Use bp_members_get_user_url() instead of deprecated bp_core_get_user_domain()
		$user_link = function_exists( 'bp_members_get_user_url' ) ? 
			bp_members_get_user_url( $secondary_item_id ) : 
			bp_core_get_user_domain( $secondary_item_id );

		return apply_filters( 'bpfn_notification_array', array(
			'text' => $text,
			'link' => $link,
			'notification_type' => $type,
			'activity_id' => $item_id,
			'activity_excerpt' => wp_trim_words( wp_strip_all_tags( $activity->content ), 20, '...' ),
			'activity_type' => $activity->type,
			'user_id' => $secondary_item_id,
			'user_name' => $this->get_user_displayname_safe( $secondary_item_id ),
			'user_link' => $user_link,
			'user_avatar' => bp_core_fetch_avatar( array(
				'item_id' => $secondary_item_id,
				'type' => 'thumb',
				'width' => 50,
				'height' => 50,
			) ),
		), $activity, $secondary_item_id );
	}

	/**
	 * Get user display name safely (without triggering filters)
	 */
	private function get_user_displayname_safe( $user_id ) {
		// Get user data directly to avoid filter loops
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return __( 'Someone', 'bp-fav-notification' );
		}
		
		// Check display name directly
		if ( ! empty( $user->display_name ) && ! preg_match( '/^User\s+\d+$/', $user->display_name ) ) {
			return $user->display_name;
		}
		
		// Try first + last name
		if ( ! empty( $user->first_name ) || ! empty( $user->last_name ) ) {
			$name = trim( $user->first_name . ' ' . $user->last_name );
			if ( ! empty( $name ) ) {
				return $name;
			}
		}
		
		// Use login name
		return $user->user_login ?: __( 'Someone', 'bp-fav-notification' );
	}

	/**
	 * Fix user display name filter
	 */
	public function fix_user_displayname( $display_name, $user_id ) {
		// Prevent infinite loops
		if ( $this->fixing_display_name ) {
			return $display_name;
		}
		
		// Only fix if it's a generic "User X" name
		if ( ! preg_match( '/^User\s+\d+$/', $display_name ) ) {
			return $display_name;
		}
		
		// Set flag to prevent recursion
		$this->fixing_display_name = true;
		
		// Get proper display name
		$proper_name = $this->get_user_displayname_safe( $user_id );
		
		// Reset flag
		$this->fixing_display_name = false;
		
		return $proper_name;
	}

	/**
	 * Get activity notification type
	 */
	private function get_activity_notification_type( $activity ) {
		return $activity->type === 'activity_comment' ? 'activity_comment' : 'activity_post';
	}

	/**
	 * Get component action
	 */
	private function get_component_action( $activity ) {
		$type = $activity->type === 'activity_comment' ? 'favorite_comment' : 'favorite';
		return $this->notification_types[ $type ]['action_prefix'] . '_' . $activity->id;
	}

	/**
	 * Register custom notification type
	 */
	public function register_notification_type( $type, $args ) {
		$this->notification_types[ $type ] = wp_parse_args( $args, array(
			'labels' => array(
				'single' => '',
				'multiple' => '',
			),
			'action_prefix' => $type . '_notify',
		) );
	}
}