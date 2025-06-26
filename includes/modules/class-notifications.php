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
	 * Debug mode
	 */
	private $debug = true; // Set to true for debugging

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->debug_log( 'Notifications module constructor called' );
		
		// Setup hooks first
		$this->setup_hooks();
		// Delay registration of notification types until translations are loaded
		add_action( 'init', array( $this, 'register_notification_types' ), 10 );
		
		// Add debug info action
		add_action( 'wp_footer', array( $this, 'output_debug_info' ) );
	}

	/**
	 * Debug logger
	 */
	private function debug_log( $message, $data = null ) {
		if ( ! $this->debug ) {
			return;
		}
		
		$log_message = 'BPFN_Notifications: ' . $message;
		if ( $data !== null ) {
			$log_message .= ' | Data: ' . print_r( $data, true );
		}
		
		error_log( $log_message );
		
		// Also store in transient for display
		$debug_logs = get_transient( 'bpfn_debug_logs' ) ?: array();
		$debug_logs[] = array(
			'time' => current_time( 'mysql' ),
			'message' => $message,
			'data' => $data,
		);
		
		// Keep only last 50 entries
		if ( count( $debug_logs ) > 50 ) {
			array_shift( $debug_logs );
		}
		
		set_transient( 'bpfn_debug_logs', $debug_logs, HOUR_IN_SECONDS );
	}

	/**
	 * Register notification types
	 */
	public function register_notification_types() {
		$this->debug_log( 'register_notification_types called' );
		
		// Default notification types - translations are now loaded
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
		
		$this->debug_log( 'Notification types registered', $this->notification_types );
	}

	/**
	 * Setup hooks
	 */
	private function setup_hooks() {
		$this->debug_log( 'setup_hooks called' );
		
		// Core notification hooks - use bp_init to ensure BuddyPress is ready
		add_action( 'bp_init', array( $this, 'setup_bp_hooks' ), 20 );
		
		// Notification display filters
		add_filter( 'bp_notifications_get_notifications_for_user', array( $this, 'filter_notification_content' ), 20, 8 );
		
		// Add filter to handle our component's notifications
		add_filter( 'bp_notifications_get_registered_components', array( $this, 'register_component' ) );
		
		// Debug: Check if actions are being triggered
		add_action( 'bp_activity_add_user_favorite', array( $this, 'debug_favorite_action' ), 1, 2 );
		
		// Custom hooks
		do_action( 'bpfn_notifications_setup_hooks', $this );
	}

	/**
	 * Setup BuddyPress specific hooks
	 */
	public function setup_bp_hooks() {
		$this->debug_log( 'setup_bp_hooks called' );
		
		// Check if BuddyPress is active
		if ( ! function_exists( 'buddypress' ) ) {
			$this->debug_log( 'ERROR: BuddyPress not active in setup_bp_hooks' );
			return;
		}
		
		// Core notification hooks
		add_action( 'bp_activity_add_user_favorite', array( $this, 'add_favorite_notification' ), 10, 2 );
		add_action( 'bp_activity_remove_user_favorite', array( $this, 'remove_favorite_notification' ), 10, 2 );
		add_action( 'bp_activity_screen_single_activity_permalink', array( $this, 'mark_notifications_read' ) );
		
		// Check if hooks are attached
		$hooks_attached = array(
			'bp_activity_add_user_favorite' => has_action( 'bp_activity_add_user_favorite', array( $this, 'add_favorite_notification' ) ),
			'bp_activity_remove_user_favorite' => has_action( 'bp_activity_remove_user_favorite', array( $this, 'remove_favorite_notification' ) ),
		);
		
		$this->debug_log( 'BuddyPress hooks attachment status', $hooks_attached );
	}

	/**
	 * Debug favorite action
	 */
	public function debug_favorite_action( $activity_id, $user_id ) {
		$this->debug_log( '*** FAVORITE ACTION TRIGGERED ***', array(
			'activity_id' => $activity_id,
			'user_id' => $user_id,
			'current_user' => get_current_user_id(),
			'bp_loggedin_user' => bp_loggedin_user_id(),
		) );
	}

	/**
	 * Register our component with BuddyPress notifications
	 */
	public function register_component( $components ) {
		global $bp;
		
		$this->debug_log( 'register_component called', array(
			'existing_components' => $components,
			'favorite_notifier_exists' => isset( $bp->favorite_notifier ),
		) );
		
		if ( isset( $bp->favorite_notifier ) ) {
			$components[] = $bp->favorite_notifier->id;
			$this->debug_log( 'Component registered', $bp->favorite_notifier->id );
		}
		
		return $components;
	}

	/**
	 * Add favorite notification
	 */
	public function add_favorite_notification( $activity_id, $user_id ) {
		$this->debug_log( '=== ADD FAVORITE NOTIFICATION START ===', array(
			'activity_id' => $activity_id,
			'favorited_by_user_id' => $user_id,
			'method' => 'add_favorite_notification',
		) );
		
		// Check if notifications component is active
		if ( ! bp_is_active( 'notifications' ) ) {
			$this->debug_log( 'ERROR: Notifications component not active' );
			return;
		}

		global $bp;
		
		// Check if our component is initialized
		if ( ! isset( $bp->favorite_notifier ) ) {
			$this->debug_log( 'ERROR: favorite_notifier not initialized in $bp global' );
			$this->debug_log( 'Available BP components', array_keys( (array) $bp ) );
			
			// Try to initialize it
			if ( function_exists( 'bpfn' ) ) {
				$this->debug_log( 'Attempting to initialize component via bpfn()' );
				$main = bpfn();
				$main->setup_globals();
			}
			
			// Check again
			if ( ! isset( $bp->favorite_notifier ) ) {
				$this->debug_log( 'ERROR: Failed to initialize component' );
				return;
			}
		}
		
		// Get activity details
		$activity = new BP_Activity_Activity( $activity_id );
		if ( empty( $activity->id ) ) {
			$this->debug_log( 'ERROR: Activity not found', $activity_id );
			return;
		}
		
		$this->debug_log( 'Activity details', array(
			'id' => $activity->id,
			'user_id' => $activity->user_id,
			'type' => $activity->type,
			'content' => substr( $activity->content, 0, 50 ) . '...',
		) );
		
		// Check if user is favoriting their own activity
		if ( $activity->user_id == $user_id ) {
			$this->debug_log( 'User favoriting own activity - skipping notification' );
			return;
		}

		// Check if notifications are enabled for this user
		$activity_type = bpfn_get_activity_type( $activity_id );
		$this->debug_log( 'Activity type determined', $activity_type );
		
		$is_enabled = bpfn_is_notification_enabled( $activity->user_id, $activity_type, 'web' );
		$this->debug_log( 'Notification enabled check', array(
			'activity_owner' => $activity->user_id,
			'activity_type' => $activity_type,
			'is_enabled' => $is_enabled,
		) );
		
		if ( ! $is_enabled ) {
			$this->debug_log( 'Notifications disabled for user' );
			return;
		}

		// Prepare notification data
		$component_action = $this->get_component_action( $activity );
		$notification_data = array(
			'user_id'           => $activity->user_id,
			'item_id'           => $activity_id,
			'secondary_item_id' => $user_id,
			'component_name'    => $bp->favorite_notifier->id,
			'component_action'  => $component_action,
			'date_notified'     => bp_core_current_time(),
			'is_new'            => 1,
		);
		
		$this->debug_log( 'Notification data prepared', $notification_data );
		
		// Allow filtering
		$notification_data = apply_filters( 'bpfn_favorite_notification_data', $notification_data, $activity, $user_id );
		$this->debug_log( 'Notification data after filters', $notification_data );

		// Check if notification already exists
		$existing = BP_Notifications_Notification::get( array(
			'user_id' => $notification_data['user_id'],
			'item_id' => $notification_data['item_id'],
			'secondary_item_id' => $notification_data['secondary_item_id'],
			'component_name' => $notification_data['component_name'],
		) );
		
		if ( ! empty( $existing ) ) {
			$this->debug_log( 'Notification already exists', $existing );
		}

		// Add notification
		$this->debug_log( 'Calling bp_notifications_add_notification' );
		$notification_id = bp_notifications_add_notification( $notification_data );
		
		if ( $notification_id ) {
			$this->debug_log( 'SUCCESS: Notification created', array(
				'notification_id' => $notification_id,
			) );
		} else {
			$this->debug_log( 'ERROR: Failed to create notification' );
			
			// Try direct database insert for debugging
			global $wpdb;
			$table = $bp->notifications->table_name;
			$this->debug_log( 'Notifications table', $table );
			
			// Check if table exists
			$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) === $table;
			$this->debug_log( 'Table exists', $table_exists );
		}
		
		// Log event
		bpfn_log_event( 'notification_added', array(
			'notification_id' => $notification_id,
			'activity_id' => $activity_id,
			'user_id' => $user_id,
		) );
		
		// Trigger actions
		do_action( 'bpfn_after_add_notification', $notification_id, $notification_data, $activity, $user_id );
		
		$this->debug_log( '=== ADD FAVORITE NOTIFICATION END ===', array(
			'success' => ! empty( $notification_id ),
		) );
	}

	/**
	 * Remove favorite notification
	 */
	public function remove_favorite_notification( $activity_id, $user_id ) {
		$this->debug_log( 'remove_favorite_notification called', array(
			'activity_id' => $activity_id,
			'user_id' => $user_id,
		) );
		
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
		
		// Check if it's a comment - only activity_comment type should use comment prefix
		if ( $activity->type === 'activity_comment' ) {
			$action_prefix = 'fav_comment_notify';
		}
		// activity_update is a regular status update, not a comment
		
		$action_prefix = apply_filters( 'bpfn_notification_action_prefix', $action_prefix, $activity );
		$action = $action_prefix . '_' . $activity->id;
		
		$this->debug_log( 'Component action generated', array(
			'activity_type' => $activity->type,
			'action_prefix' => $action_prefix,
			'final_action' => $action,
		) );
		
		return $action;
	}

	/**
	 * Format notification
	 */
	public function format_notification( $action, $item_id, $secondary_item_id, $total_items, $format = 'string' ) {
		$this->debug_log( 'format_notification called', array(
			'action' => $action,
			'item_id' => $item_id,
			'secondary_item_id' => $secondary_item_id,
			'total_items' => $total_items,
			'format' => $format,
		) );
		
		// Determine notification type based on action prefix
		$type = 'favorite';
		if ( strpos( $action, 'fav_comment_notify' ) === 0 ) {
			$type = 'favorite_comment';
		}
		
		// Get activity and check if it exists
		$activity = new BP_Activity_Activity( $item_id );
		if ( empty( $activity->id ) ) {
			// Return a basic message if activity not found
			return __( 'Someone favorited your activity', 'bp-fav-notification' );
		}
		
		// Double-check type based on actual activity
		if ( $type === 'favorite_comment' && $activity->type === 'activity_update' ) {
			// This is a regular activity, not a comment
			$type = 'favorite';
			$this->debug_log( 'Corrected type from favorite_comment to favorite based on activity type' );
		}
		
		$type = apply_filters( 'bpfn_notification_type', $type, $action, $item_id );
		
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
			if ( empty( $user_name ) || $user_name === 'User ' . $secondary_item_id ) {
				// Try to get real name
				$user = get_userdata( $secondary_item_id );
				if ( $user ) {
					$user_name = $user->display_name ?: $user->user_login;
				} else {
					$user_name = __( 'Someone', 'bp-fav-notification' );
				}
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
		// Ensure types are loaded
		if ( empty( $this->notification_types ) ) {
			$this->register_notification_types();
		}
		
		return apply_filters( 'bpfn_get_notification_types', $this->notification_types );
	}

	/**
	 * Register a new notification type
	 */
	public function register_notification_type( $type, $config ) {
		$this->notification_types[ $type ] = $config;
	}

	/**
	 * Output debug info in footer
	 */
	public function output_debug_info() {
		if ( ! $this->debug || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
		if ( ! isset( $_GET['bpfn_debug'] ) ) {
			return;
		}
		
		$logs = get_transient( 'bpfn_debug_logs' ) ?: array();
		
		?>
		<div id="bpfn-debug-panel" style="position: fixed; bottom: 0; right: 0; width: 500px; max-height: 400px; background: #000; color: #0f0; padding: 10px; overflow-y: auto; font-family: monospace; font-size: 12px; z-index: 99999;">
			<h3 style="color: #ff7b00;">BPFN Debug Log</h3>
			<button onclick="document.getElementById('bpfn-debug-panel').style.display='none';" style="position: absolute; top: 5px; right: 5px;">X</button>
			<div style="max-height: 350px; overflow-y: auto;">
				<?php foreach ( array_reverse( $logs ) as $log ) : ?>
					<div style="margin-bottom: 10px; padding: 5px; border-bottom: 1px solid #333;">
						<div style="color: #ff7b00;">[<?php echo esc_html( $log['time'] ); ?>]</div>
						<div><?php echo esc_html( $log['message'] ); ?></div>
						<?php if ( $log['data'] ) : ?>
							<pre style="color: #0ff; font-size: 10px;"><?php echo esc_html( print_r( $log['data'], true ) ); ?></pre>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
			<button onclick="jQuery.post(ajaxurl, {action: 'bpfn_clear_debug_logs'});">Clear Logs</button>
		</div>
		<script>
		console.log('BPFN Debug Panel loaded. Logs:', <?php echo json_encode( $logs ); ?>);
		</script>
		<?php
	}
}

// Add AJAX handler to clear debug logs
add_action( 'wp_ajax_bpfn_clear_debug_logs', function() {
	if ( current_user_can( 'manage_options' ) ) {
		delete_transient( 'bpfn_debug_logs' );
		wp_send_json_success();
	}
} );