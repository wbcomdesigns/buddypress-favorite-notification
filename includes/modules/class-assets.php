<?php
/**
 * Clean Assets Module for BuddyPress Favorite Notification
 *
 * @package BuddyPress_Favorite_Notification
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clean Assets Module Class
 */
class BPFN_Module_Assets {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Setup hooks
	 */
	private function setup_hooks() {
		// Frontend assets
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		
		// Admin assets
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Enqueue frontend assets
	 */
	public function enqueue_frontend_assets() {
		// Check if we should load assets
		if ( ! $this->should_load_assets() ) {
			return;
		}
		
		// Core styles
		wp_enqueue_style(
			'bpfn-notifications',
			BPFN_ASSETS_URL . 'css/notifications.css',
			array(),
			BPFN_VERSION
		);
		
		// Core scripts
		wp_enqueue_script(
			'bpfn-notifications',
			BPFN_ASSETS_URL . 'js/notifications.js',
			array( 'jquery' ),
			BPFN_VERSION,
			true
		);
		
		// Localize main script
		wp_localize_script( 'bpfn-notifications', 'BPFN', $this->get_localized_data() );
		
		// Real-time notifications
		if ( $this->should_load_realtime() ) {
			$this->enqueue_realtime_assets();
		}
	}

	/**
	 * Enqueue real-time assets
	 */
	private function enqueue_realtime_assets() {
		// Heartbeat API
		wp_enqueue_script( 'heartbeat' );
		
		// Real-time styles
		wp_enqueue_style(
			'bpfn-realtime',
			BPFN_ASSETS_URL . 'css/realtime.css',
			array( 'bpfn-notifications' ),
			BPFN_VERSION
		);
		
		// Real-time scripts
		wp_enqueue_script(
			'bpfn-realtime',
			BPFN_ASSETS_URL . 'js/realtime.js',
			array( 'jquery', 'heartbeat', 'bpfn-notifications' ),
			BPFN_VERSION,
			true
		);
		
		// Get realtime configuration
		$realtime_module = bpfn()->get_module( 'realtime' );
		$polling_config = $realtime_module ? $realtime_module->get_polling_config() : $this->get_fallback_realtime_config();
		
		// Localize real-time script
		wp_localize_script( 'bpfn-realtime', 'BPFNRealtime', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'bpfn_realtime_nonce' ),
			'checkInterval' => $polling_config['interval'],
			'position' => $polling_config['position'],
			'maxNotifications' => $polling_config['max_notifications'],
			'autoDismiss' => $polling_config['auto_dismiss_time'],
			'strings' => array(
				'new_notification' => __( 'New notification', 'bp-fav-notification' ),
				'view_activity' => __( 'View Activity', 'bp-fav-notification' ),
				'dismiss' => __( 'Dismiss', 'bp-fav-notification' ),
			),
		) );
	}

	/**
	 * Get fallback realtime configuration
	 */
	private function get_fallback_realtime_config() {
		return array(
			'enabled' => true,
			'interval' => 15000,
			'max_notifications' => 5,
			'auto_dismiss_time' => 5000,
			'position' => 'bottom-right'
		);
	}

	/**
	 * Enqueue admin assets
	 */
	public function enqueue_admin_assets( $hook ) {
		// Check if we're on one of our admin pages
		if ( ! ( isset( $_GET['page'] ) && strpos( $_GET['page'], 'bpfn' ) !== false ) ) {
			return;
		}
		
		// Admin styles
		wp_enqueue_style(
			'bpfn-admin',
			BPFN_ASSETS_URL . 'css/admin.css',
			array(),
			BPFN_VERSION
		);
		
		// Admin scripts
		wp_enqueue_script(
			'bpfn-admin',
			BPFN_ASSETS_URL . 'js/admin.js',
			array( 'jquery' ),
			BPFN_VERSION,
			true
		);
		
		// Localization for admin script
		wp_localize_script( 'bpfn-admin', 'bpfnAdmin', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'bpfn-admin-nonce' ),
			'strings' => array(
				'testing' => __( 'Sending test...', 'bp-fav-notification' ),
				'test_success' => __( 'Test sent successfully!', 'bp-fav-notification' ),
				'test_error' => __( 'Test failed.', 'bp-fav-notification' ),
				'confirm_clear' => __( 'Are you sure?', 'bp-fav-notification' ),
				'clearing' => __( 'Clearing...', 'bp-fav-notification' ),
			),
		) );
	}

	/**
	 * Check if assets should be loaded
	 */
	private function should_load_assets() {
		// Don't load if user is not logged in
		if ( ! is_user_logged_in() ) {
			return false;
		}
		
		// Don't load if notifications component is not active
		if ( ! bp_is_active( 'notifications' ) ) {
			return false;
		}
		
		return true;
	}

	/**
	 * Check if real-time assets should be loaded
	 */
	private function should_load_realtime() {
		// Realtime notifications are always available
		// Load assets for all logged-in users
		// User preferences control whether notifications are actually shown
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return false;  // Not logged in
		}

		return true;
	}

	/**
	 * Get localized data for scripts
	 */
	private function get_localized_data() {
		global $bp;
		
		return array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'bpfn-nonce' ),
			'user_id' => get_current_user_id(),
			'component_id' => isset( $bp->favorite_notifier ) ? $bp->favorite_notifier->id : '',
			'strings' => array(
				'loading' => __( 'Loading...', 'bp-fav-notification' ),
				'error' => __( 'An error occurred', 'bp-fav-notification' ),
				'favoriting' => __( 'Adding to favorites...', 'bp-fav-notification' ),
				'unfavoriting' => __( 'Removing from favorites...', 'bp-fav-notification' ),
				'favorited' => __( 'Added to favorites!', 'bp-fav-notification' ),
				'unfavorited' => __( 'Removed from favorites!', 'bp-fav-notification' ),
			),
		);
	}
}