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
	 * Constructor
	 */
	public function __construct() {
		// Register notification types first
		$this->register_notification_types();
		// Then setup hooks
		$this->setup_hooks();
	}

	/**
	 * Register notification types
	 */
	private function register_notification_types() {
		// Load text domain if not loaded
		if ( ! is_textdomain_loaded( 'bp-fav-notification' ) ) {
			load_plugin_textdomain( 'bp-fav-notification', false, dirname( plugin_basename( BPFN_PLUGIN_FILE ) ) . '/languages/' );
		}
		
		// Default notification types
		$this->notification_types = array(
			'favorite' => array(
				'action_prefix' => 'fav_notify',
				'labels' => array(
					'single' => __( '%s favorited your activity', 'bp-fav-notification' ),
					'multiple' => __( '%d members favorited your activity', 'bp-fav-notification' ),
				),
			),
			'favorite_comment' => array(
				'action_prefix' => 'fav_comment_notify',
				'labels' => array(
					'single' => __( '%s favorited your comment', 'bp-fav-notification' ),
					'multiple' => __( '%d members favorited your comment', 'bp-fav-notification' ),
				),
			),
		);
		
		// Allow filtering after types are set
		$this->notification_types = apply_filters( 'bpfn_notification_types', $this->notification_types );
	}

	/**
	 * Setup hooks
	 */
	private function setup_hooks() {
		// Core notification hooks
		add_action( 'bp_activity_add_user_favorite', array( $this, 'add_favorite_notification' ), 10, 2 );
		add_action( 'bp_activity_remove_user_favorite', array( $this, 'remove_favorite_notification' ), 10, 2 );
		add_action( 'bp_activity_screen_single_activity_permalink', array( $this, 'mark_notifications_read' ) );
		
		// Notification display filters
		add_filter( 'bp_notifications_get_notifications_for_user', array( $this, 'filter_notification_content' ), 20, 8 );
		
		// Add filter to handle our component's notifications
		add_filter( 'bp_notifications_get_registered_components', array( $this, 'register_component' ) );
		
		// Custom hooks
		do_action( 'bpfn_notifications_setup_hooks', $this );
	}

	/**
	 * Register our component with BuddyPress notifications
	 */
	public function register_component( $components ) {
		global $bp;
		
		if ( isset( $bp->favorite_notifier ) ) {
			$components[] = $bp->favorite_notifier->id;
		}
		
		return $components;
	}

	/**
	 * Add favorite notification
	 */
	public function add_favorite_notification( $activity_id, $user_id ) {
		error_log( 'BPFN: add_favorite_notification called - Activity: ' . $activity_id . ', User: ' . $user_id );
		
		if ( ! bp_is_active( 'notifications' ) ) {
			error_log( 'BPFN: Notifications component not active' );
			return;
		}

		global $bp;
		
		// Ensure component is initialized
		if ( ! isset( $bp->favorite_notifier ) ) {
			error_log( 'BPFN: Component not initialized in add_favorite_notification' );
			// Try to initialize it
			if ( function_exists( 'bpfn' ) ) {
				$main = bpfn();
				$main->setup_globals();
			}
			// Check again
			if ( ! isset( $bp->favorite_notifier ) ) {
				error_log( 'BPFN: Failed to initialize component' );
				return;
			}
		}
		
		// Get activity
		$activity = new BP_Activity_Activity( $activity_id );
		if ( empty( $activity->id ) ) {
			error_log( 'BPFN: Activity not found for ID: ' . $activity_id );
			return;
		}
		
		error_log( 'BPFN: Activity found - User ID: ' . $activity->user_id . ', Type: ' . $activity->type );
		
		// Don't notify yourself
		if ( $activity->user_id == $user_id ) {
			error_log( 'BPFN: User favoriting own activity - no notification' );
			return;
		}

		// Check if notifications are enabled
		$activity_type = bpfn_get_activity_type( $activity_id );
		if ( ! bpfn_is_notification_enabled( $activity->user_id, $activity_type, 'web' ) ) {
			error_log( 'BPFN: Notifications disabled for user ' . $activity->user_id );
			return;
		}

		// Prepare notification data
		$notification_data = array(
			'user_id'           => $activity->user_id,
			'item_id'           => $activity_id,
			'secondary_item_id' => $user_id,
			'component_name'    => $bp->favorite_notifier->id,
			'component_action'  => $this->get_component_action( $activity ),
			'date_notified'     => bp_core_current_time(),
			'is_new'            => 1,
		);
		
		error_log( 'BPFN: Notification data: ' . print_r( $notification_data, true ) );
		
		// Allow filtering
		$notification_data = apply_filters( 'bpfn_favorite_notification_data', $notification_data, $activity, $user_id );

		// Add notification
		$notification_id = bp_notifications_add_notification( $notification_data );
		
		if ( $notification_id ) {
			error_log( 'BPFN: Notification created with ID: ' . $notification_id );
		} else {
			error_log( 'BPFN: Failed to create notification' );
		}
		
		// Log event
		bpfn_log_event( 'notification_added', array(
			'notification_id' => $notification_id,
			'activity_id' => $activity_id,
			'user_id' => $user_id,
		) );
		
		// Trigger actions
		do_action( 'bpfn_after_add_notification', $notification_id, $notification_data, $activity, $user_id );
	}

	/**
	 * Remove favorite notification
	 */
	public function remove_favorite_notification( $activity_id, $user_id ) {
		if ( ! bp_is_active( 'notifications' ) ) {
			return;
		}

		global $bp;
		
		// Ensure component is initialized
		if ( ! isset( $bp->favorite_notifier ) ) {
			return;
		}
		
		// Delete notification
		BP_Notifications_Notification::delete( array(
			'item_id' => $activity_id,
			'secondary_item_id' => $user_id,
			'component_name' => $bp->favorite_notifier->id,
		) );
		
		// Trigger action
		do_action( 'bpfn_after_remove_notification', $activity_id, $user_id );
	}

	/**
	 * Get component action based on activity
	 */
	private function get_component_action( $activity ) {
		$action_prefix = 'fav_notify';
		
		// Check if it's a comment
		if ( $activity->type === 'activity_comment' ) {
			$action_prefix = 'fav_comment_notify';
		}
		
		$action_prefix = apply_filters( 'bpfn_notification_action_prefix', $action_prefix, $activity );
		
		return $action_prefix . '_' . $activity->id;
	}

	/**
	 * Format notification
	 */
	public function format_notification( $action, $item_id, $secondary_item_id, $total_items, $format = 'string' ) {
		// Determine notification type
		$type = 'favorite';
		if ( strpos( $action, 'fav_comment_notify' ) === 0 ) {
			$type = 'favorite_comment';
		}
		
		$type = apply_filters( 'bpfn_notification_type', $type, $action, $item_id );
		
		// Get activity and check if it exists
		$activity = new BP_Activity_Activity( $item_id );
		if ( empty( $activity->id ) ) {
			// Return a basic message if activity not found
			return __( 'Someone favorited your activity', 'bp-fav-notification' );
		}
		
		// Ensure notification types are loaded
		if ( empty( $this->notification_types ) ) {
			$this->register_notification_types();
		}
		
		// Get notification labels
		$labels = isset( $this->notification_types[ $type ]['labels'] ) 
			? $this->notification_types[ $type ]['labels'] 
			: array(
				'single' => __( '%s favorited your activity', 'bp-fav-notification' ),
				'multiple' => __( '%d members favorited your activity', 'bp-fav-notification' ),
			);
		
		// Format notification text
		if ( $total_items > 1 ) {
			$text = sprintf( $labels['multiple'], $total_items );
		} else {
			$user_name = bp_core_get_user_displayname( $secondary_item_id );
			if ( empty( $user_name ) ) {
				$user_name = __( 'Someone', 'bp-fav-notification' );
			}
			$text = sprintf( $labels['single'], $user_name );
		}
		
		// Get link
		$link = bp_activity_get_permalink( $item_id );
		if ( empty( $link ) ) {
			$link = bp_get_activity_directory_permalink();
		}
		
		// Apply filters
		$text = apply_filters( 'bpfn_notification_text', $text, $item_id, $secondary_item_id, $total_items, $type );
		$link = apply_filters( 'bpfn_notification_link', $link, $item_id, $secondary_item_id, $total_items, $type );
		
		// Return formatted notification
		if ( 'string' === $format ) {
			return '<a href="' . esc_url( $link ) . '">' . esc_html( $text ) . '</a>';
		} else {
			// Enhanced array format
			return $this->get_notification_array( $activity, $text, $link, $secondary_item_id, $type );
		}
	}

	/**
	 * Get notification array with enhanced data
	 */
	private function get_notification_array( $activity, $text, $link, $secondary_item_id, $type ) {
		// Get user data
		$user_data = get_userdata( $secondary_item_id );
		
		// Get activity excerpt
		$activity_excerpt = wp_trim_words( wp_strip_all_tags( $activity->content ), 15, '...' );
		
		// Get user avatar
		$avatar = bp_core_fetch_avatar( array(
			'item_id' => $secondary_item_id,
			'type' => 'thumb',
			'width' => 50,
			'height' => 50,
			'html' => true,
		) );
		
		$data = array(
			'text' => $text,
			'link' => $link,
			'activity_id' => $activity->id,
			'activity_excerpt' => $activity_excerpt,
			'activity_type' => $activity->type,
			'user_id' => $secondary_item_id,
			'user_name' => $user_data ? $user_data->display_name : '',
			'user_link' => bp_core_get_user_domain( $secondary_item_id ),
			'user_avatar' => $avatar,
			'timestamp' => strtotime( $activity->date_recorded ),
			'notification_type' => $type,
		);
		
		return apply_filters( 'bpfn_notification_array', $data, $activity, $secondary_item_id );
	}

	/**
	 * Mark notifications as read
	 */
	public function mark_notifications_read() {
		if ( ! bp_is_single_activity() || ! is_user_logged_in() ) {
			return;
		}

		global $activities_template, $bp;
		
		// Ensure component is initialized
		if ( ! isset( $bp->favorite_notifier ) ) {
			return;
		}
		
		if ( empty( $activities_template->activity ) ) {
			return;
		}

		$activity = $activities_template->activity;
		$user_id = bp_loggedin_user_id();
		
		// Only mark as read for the activity owner
		if ( $activity->user_id != $user_id ) {
			return;
		}

		// Get notifications
		$notifications = BP_Notifications_Notification::get( array(
			'user_id' => $user_id,
			'item_id' => $activity->id,
			'component_name' => $bp->favorite_notifier->id,
			'is_new' => 1,
		) );

		// Mark as read
		foreach ( $notifications as $notification ) {
			bp_notifications_mark_notification( $notification->id, false );
		}
		
		// Trigger action
		do_action( 'bpfn_after_mark_notifications_read', $notifications, $activity, $user_id );
	}

	/**
	 * Filter notification content for enhanced display
	 */
	public function filter_notification_content( $component_action_name, $component_name, $item_id, $secondary_item_id, $total_items, $format, $action, $notification ) {
		global $bp;
		
		// Ensure component is initialized
		if ( ! isset( $bp->favorite_notifier ) ) {
			return $component_action_name;
		}
		
		// Only filter our notifications
		if ( $component_name !== $bp->favorite_notifier->id ) {
			return $component_action_name;
		}
		
		// Check if enhanced notifications are enabled
		if ( ! apply_filters( 'bpfn_enable_enhanced_notifications', true ) ) {
			return $component_action_name;
		}
		
		// Get notification data
		$notification_data = $this->format_notification( $action, $item_id, $secondary_item_id, $total_items, 'array' );
		
		if ( ! is_array( $notification_data ) ) {
			return $component_action_name;
		}
		
		// Render enhanced notification
		return bpfn_render_notification( $notification_data );
	}

	/**
	 * Get registered notification types
	 */
	public function get_notification_types() {
		return apply_filters( 'bpfn_get_notification_types', $this->notification_types );
	}

	/**
	 * Register a new notification type
	 */
	public function register_notification_type( $type, $config ) {
		$this->notification_types[ $type ] = $config;
	}
}