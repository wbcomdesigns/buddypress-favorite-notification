<?php
/**
 * Debug Module for BuddyPress Favorite Notification
 *
 * @package BuddyPress_Favorite_Notification
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Debug Module Class
 */
class BPFN_Module_Debug {

	/**
	 * Debug enabled
	 */
	private $debug_enabled = false;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Enable debug if WP_DEBUG is on or GET parameter is set
		$this->debug_enabled = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || isset( $_GET['bpfn_debug'] );
		
		if ( $this->debug_enabled ) {
			$this->setup_hooks();
		}
	}

	/**
	 * Setup debug hooks
	 */
	private function setup_hooks() {
		// Component initialization debug
		add_action( 'bp_setup_components', array( $this, 'debug_component_setup' ), 999 );
		add_action( 'bp_init', array( $this, 'debug_bp_init' ), 999 );
		
		// Notification creation debug
		add_action( 'bp_activity_add_user_favorite', array( $this, 'debug_favorite_action' ), 1, 2 );
		add_action( 'bpfn_after_add_notification', array( $this, 'debug_notification_added' ), 10, 4 );
		
		// Notification display debug
		add_filter( 'bp_notifications_get_notifications_for_user', array( $this, 'debug_notification_formatting' ), 1, 8 );
		add_action( 'bp_before_member_body', array( $this, 'debug_user_notifications_page' ) );
		
		// Email debug
		add_action( 'bpfn_after_send_email', array( $this, 'debug_email_sent' ), 10, 2 );
		
		// Database debug
		add_action( 'bp_notifications_before_save', array( $this, 'debug_before_notification_save' ) );
		add_action( 'bp_notification_after_save', array( $this, 'debug_after_notification_save' ) );
		
		// Add debug info to admin bar
		add_action( 'admin_bar_menu', array( $this, 'add_debug_menu' ), 999 );
		
		// AJAX debug
		add_action( 'wp_ajax_bpfn_debug_info', array( $this, 'ajax_debug_info' ) );
		
		// Component registration debug
		add_action( 'bp_register_notification_group', array( $this, 'debug_notification_registration' ) );
	}

	/**
	 * Debug component setup
	 */
	public function debug_component_setup() {
		global $bp;
		
		$this->log( '=== BP Setup Components ===' );
		$this->log( 'Active components: ' . implode( ', ', array_keys( $bp->active_components ) ) );
		
		if ( isset( $bp->favorite_notifier ) ) {
			$this->log( '✓ favorite_notifier component is set' );
			$this->log( 'Component ID: ' . $bp->favorite_notifier->id );
			$this->log( 'Component slug: ' . $bp->favorite_notifier->slug );
			$this->log( 'Has callback: ' . ( isset( $bp->favorite_notifier->notification_callback ) ? 'YES' : 'NO' ) );
		} else {
			$this->log( '✗ favorite_notifier component NOT set!' );
		}
	}

	/**
	 * Debug BP Init
	 */
	public function debug_bp_init() {
		global $bp;
		
		$this->log( '=== BP Init ===' );
		
		// Check if notifications component is active
		if ( bp_is_active( 'notifications' ) ) {
			$this->log( '✓ Notifications component active' );
			
			// Check notifications table
			if ( isset( $bp->notifications->table_name ) ) {
				$this->log( 'Notifications table: ' . $bp->notifications->table_name );
			}
		} else {
			$this->log( '✗ Notifications component NOT active!' );
		}
		
		// Check our component again
		if ( isset( $bp->favorite_notifier ) && isset( $bp->active_components['favorite_notifier'] ) ) {
			$this->log( '✓ favorite_notifier in active components' );
		} else {
			$this->log( '✗ favorite_notifier NOT in active components!' );
			
			// Try to add it
			if ( isset( $bp->favorite_notifier ) ) {
				$bp->active_components['favorite_notifier'] = 'favorite_notifier';
				$this->log( '→ Added favorite_notifier to active components' );
			}
		}
	}

	/**
	 * Debug favorite action
	 */
	public function debug_favorite_action( $activity_id, $user_id ) {
		$this->log( '=== Favorite Action Triggered ===' );
		$this->log( 'Activity ID: ' . $activity_id );
		$this->log( 'User ID (who favorited): ' . $user_id );
		
		$activity = new BP_Activity_Activity( $activity_id );
		if ( $activity->id ) {
			$this->log( 'Activity type: ' . $activity->type );
			$this->log( 'Activity user_id (owner): ' . $activity->user_id );
			$this->log( 'Activity content: ' . substr( $activity->content, 0, 50 ) . '...' );
			
			// Check if notification should be created
			if ( $activity->user_id == $user_id ) {
				$this->log( '→ Same user, no notification needed' );
			} else {
				$this->log( '→ Different user, notification should be created' );
				
				// Check if notifications are enabled for this user
				$activity_type = $activity->type === 'activity_comment' ? 'activity_comment' : 'activity_post';
				$web_enabled = bpfn_is_notification_enabled( $activity->user_id, $activity_type, 'web' );
				$email_enabled = bpfn_is_notification_enabled( $activity->user_id, $activity_type, 'email' );
				
				$this->log( 'Web notifications enabled: ' . ( $web_enabled ? 'YES' : 'NO' ) );
				$this->log( 'Email notifications enabled: ' . ( $email_enabled ? 'YES' : 'NO' ) );
			}
		} else {
			$this->log( '✗ Activity not found!' );
		}
	}

	/**
	 * Debug notification added
	 */
	public function debug_notification_added( $notification_id, $notification_data, $activity, $user_id ) {
		$this->log( '=== Notification Added ===' );
		$this->log( 'Notification ID: ' . $notification_id );
		$this->log( 'Notification data: ' . print_r( $notification_data, true ) );
		
		if ( $notification_id ) {
			// Verify it was saved
			$notification = BP_Notifications_Notification::get( array(
				'id' => $notification_id,
			) );
			
			if ( ! empty( $notification ) ) {
				$this->log( '✓ Notification verified in database' );
			} else {
				$this->log( '✗ Notification NOT found in database!' );
			}
		} else {
			$this->log( '✗ Notification ID is empty - failed to create!' );
		}
	}

	/**
	 * Debug notification formatting
	 */
	public function debug_notification_formatting( $renderable, $item_id, $secondary_item_id, $total_items, $format, $component_action, $component_name, $id ) {
		if ( $component_name === 'favorite_notifier' ) {
			$this->log( '=== Formatting Notification ===' );
			$this->log( 'Component: ' . $component_name );
			$this->log( 'Action: ' . $component_action );
			$this->log( 'Item ID: ' . $item_id );
			$this->log( 'Format: ' . $format );
			$this->log( 'Current renderable: ' . print_r( $renderable, true ) );
			
			// Check if callback exists
			global $bp;
			if ( isset( $bp->favorite_notifier->notification_callback ) ) {
				$this->log( '✓ Notification callback exists' );
			} else {
				$this->log( '✗ Notification callback NOT found!' );
			}
		}
		
		return $renderable;
	}

	/**
	 * Debug user notifications page
	 */
	public function debug_user_notifications_page() {
		if ( ! bp_is_user_notifications() ) {
			return;
		}
		
		global $bp;
		$user_id = bp_displayed_user_id();
		
		$this->log( '=== Notifications Page Debug ===' );
		$this->log( 'User ID: ' . $user_id );
		
		// Get all notifications
		$all_notifications = BP_Notifications_Notification::get( array(
			'user_id' => $user_id,
			'order_by' => 'date_notified',
			'sort_order' => 'DESC',
		) );
		
		$this->log( 'Total notifications: ' . count( $all_notifications ) );
		
		// Get favorite notifications
		$fav_notifications = BP_Notifications_Notification::get( array(
			'user_id' => $user_id,
			'component_name' => 'favorite_notifier',
			'order_by' => 'date_notified',
			'sort_order' => 'DESC',
		) );
		
		$this->log( 'Favorite notifications: ' . count( $fav_notifications ) );
		
		// List first 5 notifications
		if ( ! empty( $fav_notifications ) ) {
			$this->log( 'Recent favorite notifications:' );
			$count = 0;
			foreach ( $fav_notifications as $notif ) {
				$this->log( sprintf( 
					'  - ID: %d, Action: %s, New: %s, Date: %s',
					$notif->id,
					$notif->component_action,
					$notif->is_new ? 'YES' : 'NO',
					$notif->date_notified
				) );
				
				if ( ++$count >= 5 ) break;
			}
		}
		
		// Output debug info on page (admin only)
		if ( current_user_can( 'manage_options' ) && isset( $_GET['bpfn_debug'] ) ) {
			echo '<div style="background: #f0f0f0; padding: 20px; margin: 20px 0; font-family: monospace; font-size: 12px;">';
			echo '<h3>BPFN Debug Info</h3>';
			echo '<pre>';
			echo 'Component registered: ' . ( isset( $bp->favorite_notifier ) ? 'YES' : 'NO' ) . "\n";
			echo 'Total notifications: ' . count( $all_notifications ) . "\n";
			echo 'Favorite notifications: ' . count( $fav_notifications ) . "\n";
			echo '</pre>';
			echo '</div>';
		}
	}

	/**
	 * Debug email sent
	 */
	public function debug_email_sent( $sent, $email_data ) {
		$this->log( '=== Email Debug ===' );
		$this->log( 'Email sent: ' . ( $sent ? 'SUCCESS' : 'FAILED' ) );
		$this->log( 'To: ' . $email_data['to'] );
		$this->log( 'Subject: ' . ( $email_data['subject'] ?? 'No subject' ) );
		
		if ( ! $sent ) {
			$this->log( '✗ wp_mail() failed - check mail configuration' );
		}
	}

	/**
	 * Debug before notification save
	 */
	public function debug_before_notification_save( $notification ) {
		$this->log( '=== Before Notification Save ===' );
		$this->log( 'Component: ' . ( $notification->component_name ?? 'not set' ) );
		$this->log( 'Action: ' . ( $notification->component_action ?? 'not set' ) );
	}

	/**
	 * Debug after notification save
	 */
	public function debug_after_notification_save( $notification ) {
		$this->log( '=== After Notification Save ===' );
		$this->log( 'Saved with ID: ' . $notification->id );
	}

	/**
	 * Add debug menu to admin bar
	 */
	public function add_debug_menu( $wp_admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
		global $bp;
		
		$debug_info = array(
			'Component' => isset( $bp->favorite_notifier ) ? '✓' : '✗',
			'Active' => isset( $bp->active_components['favorite_notifier'] ) ? '✓' : '✗',
			'Notifications' => bp_is_active( 'notifications' ) ? '✓' : '✗',
		);
		
		$title = '🔧 BPFN Debug';
		foreach ( $debug_info as $key => $value ) {
			$title .= ' | ' . $key . ': ' . $value;
		}
		
		$args = array(
			'id' => 'bpfn-debug',
			'title' => $title,
			'href' => '#',
			'meta' => array(
				'class' => 'bpfn-debug-menu',
				'onclick' => 'jQuery.post(ajaxurl, {action: "bpfn_debug_info"}, function(r) { console.log(r); alert("Debug info logged to console"); }); return false;',
			),
		);
		
		$wp_admin_bar->add_node( $args );
	}

	/**
	 * AJAX debug info
	 */
	public function ajax_debug_info() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die();
		}
		
		global $bp, $wpdb;
		
		$info = array(
			'component_setup' => isset( $bp->favorite_notifier ),
			'component_active' => isset( $bp->active_components['favorite_notifier'] ),
			'notifications_active' => bp_is_active( 'notifications' ),
			'recent_notifications' => array(),
		);
		
		// Get recent notifications
		if ( bp_is_active( 'notifications' ) ) {
			$recent = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$bp->notifications->table_name} 
				WHERE component_name = %s 
				ORDER BY date_notified DESC 
				LIMIT 10",
				'favorite_notifier'
			) );
			
			$info['recent_notifications'] = $recent;
		}
		
		wp_send_json( $info );
	}

	/**
	 * Debug notification registration
	 */
	public function debug_notification_registration() {
		$this->log( '=== Notification Registration ===' );
		$this->log( 'bp_register_notification_group action fired' );
	}

	/**
	 * Log debug message
	 */
	private function log( $message, $data = null ) {
		if ( ! $this->debug_enabled ) {
			return;
		}
		
		$log_message = '[BPFN Debug] ' . $message;
		if ( $data ) {
			$log_message .= ' - ' . print_r( $data, true );
		}
		
		error_log( $log_message );
		
		// Store in transient for display
		$logs = get_transient( 'bpfn_debug_logs' ) ?: array();
		$logs[] = array(
			'time' => current_time( 'mysql' ),
			'message' => $message,
			'data' => $data,
		);
		
		// Keep only last 50 logs
		if ( count( $logs ) > 50 ) {
			$logs = array_slice( $logs, -50 );
		}
		
		set_transient( 'bpfn_debug_logs', $logs, HOUR_IN_SECONDS );
	}

	/**
	 * Get debug logs
	 */
	public static function get_logs() {
		return get_transient( 'bpfn_debug_logs' ) ?: array();
	}

	/**
	 * Clear debug logs
	 */
	public static function clear_logs() {
		delete_transient( 'bpfn_debug_logs' );
	}
}

// Initialize debug module if debugging is enabled
if ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || isset( $_GET['bpfn_debug'] ) ) {
	new BPFN_Module_Debug();
}