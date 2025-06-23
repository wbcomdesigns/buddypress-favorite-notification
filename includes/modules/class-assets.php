<?php
/**
 * Assets Module for BuddyPress Favorite Notification
 *
 * @package BuddyPress_Favorite_Notification
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Assets Module Class
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
		
		// Admin assets - this needs to be on admin_enqueue_scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		
		// Also add a specific hook for our menu pages as a fallback
		add_action( 'admin_head', array( $this, 'enqueue_admin_assets_fallback' ) );
		
		// Login page assets
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue_login_assets' ) );
		
		// Custom hooks
		do_action( 'bpfn_assets_setup_hooks', $this );
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
		
		// Enhanced notifications
		if ( $this->should_load_enhanced() ) {
			$this->enqueue_enhanced_assets();
		}
		
		// Allow additional assets
		do_action( 'bpfn_enqueue_frontend_assets' );
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
		
		// Get realtime module
		$realtime_module = bpfn()->get_module( 'realtime' );
		$polling_config = $realtime_module ? $realtime_module->get_polling_config() : array();
		
		// Localize real-time script
		wp_localize_script( 'bpfn-realtime', 'BPFNRealtime', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'bpfn_realtime_nonce' ),
			'polling' => $polling_config,
			'strings' => array(
				'new_notification' => __( 'New notification', 'bp-fav-notification' ),
				'view_activity' => __( 'View Activity', 'bp-fav-notification' ),
				'dismiss' => __( 'Dismiss', 'bp-fav-notification' ),
			),
		) );
	}

	/**
	 * Enqueue enhanced notification assets
	 */
	private function enqueue_enhanced_assets() {
		wp_enqueue_style(
			'bpfn-enhanced',
			BPFN_ASSETS_URL . 'css/enhanced.css',
			array( 'bpfn-notifications' ),
			BPFN_VERSION
		);
	}

	/**
	 * Enqueue admin assets
	 */
	public function enqueue_admin_assets( $hook ) {
		// Check if we're on any of our admin pages
		$is_our_page = false;
		
		// Check main menu pages - more flexible checking
		if ( strpos( $hook, 'bpfn' ) !== false ) {
			$is_our_page = true;
		}
		
		// Check if it's the main menu page or subpages
		// The actual hooks might be different depending on menu position
		$our_pages = array(
			'toplevel_page_bpfn-settings',
			'bp-favorites_page_bpfn-tools', 
			'bp-favorites_page_bpfn-help',
			// Alternative possible hooks
			'admin_page_bpfn-tools',
			'admin_page_bpfn-help',
			'bpfn-settings_page_bpfn-tools',
			'bpfn-settings_page_bpfn-help',
			// Check for variations
			'toplevel_page_bp-favorite-notification',
			'bp-favorite-notification_page_bpfn-tools',
			'bp-favorite-notification_page_bpfn-help',
		);
		
		if ( in_array( $hook, $our_pages ) ) {
			$is_our_page = true;
		}
		
		// Also check for options page if added there
		if ( $hook === 'settings_page_bpfn-settings' ) {
			$is_our_page = true;
		}
		
		// More flexible check - if the page parameter contains our slug
		if ( isset( $_GET['page'] ) && strpos( $_GET['page'], 'bpfn' ) !== false ) {
			$is_our_page = true;
		}
		
		if ( ! $is_our_page ) {
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
		
		// Localize admin script
		wp_localize_script( 'bpfn-admin', 'bpfnAdmin', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'bpfn-admin-nonce' ),
			'strings' => array(
				'testing' => __( 'Sending test notification...', 'bp-fav-notification' ),
				'test_success' => __( 'Test notification sent successfully!', 'bp-fav-notification' ),
				'test_error' => __( 'Failed to send test notification.', 'bp-fav-notification' ),
				'confirm_clear' => __( 'Are you sure you want to clear old notifications?', 'bp-fav-notification' ),
				'clearing' => __( 'Clearing...', 'bp-fav-notification' ),
				'clear_success' => __( 'Notifications cleared successfully.', 'bp-fav-notification' ),
				'clear_error' => __( 'Failed to clear notifications.', 'bp-fav-notification' ),
			),
		) );
		
		// Add color picker if needed
		if ( $hook === 'toplevel_page_bpfn-settings' || $hook === 'settings_page_bpfn-settings' ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-color-picker' );
		}
		
		// Add Chart.js for stats
		if ( $hook === 'toplevel_page_bpfn-settings' ) {
			wp_enqueue_script(
				'chart-js',
				'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
				array(),
				'3.9.1',
				true
			);
		}
		
		// Allow additional admin assets
		do_action( 'bpfn_enqueue_admin_assets', $hook );
	}

	/**
	 * Enqueue login page assets
	 */
	public function enqueue_login_assets() {
		// Allow custom login styles
		if ( apply_filters( 'bpfn_enable_login_styles', false ) ) {
			wp_enqueue_style(
				'bpfn-login',
				BPFN_ASSETS_URL . 'css/login.css',
				array(),
				BPFN_VERSION
			);
		}
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
		
		// Allow filtering
		return apply_filters( 'bpfn_should_load_assets', true );
	}

	/**
	 * Check if real-time assets should be loaded
	 */
	private function should_load_realtime() {
		$user_id = get_current_user_id();
		$settings = bpfn_get_user_settings( $user_id );
		
		// Check if any notification type has real-time enabled
		foreach ( $settings as $type => $options ) {
			if ( ! empty( $options['realtime_enabled'] ) ) {
				return true;
			}
		}
		
		return false;
	}

	/**
	 * Check if enhanced assets should be loaded
	 */
	private function should_load_enhanced() {
		$options = get_option( 'bpfn_options', array() );
		return ! empty( $options['enable_enhanced_notifications'] );
	}

	/**
	 * Get localized data for scripts
	 */
	private function get_localized_data() {
		global $bp;
		
		$data = array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'bpfn-nonce' ),
			'user_id' => get_current_user_id(),
			'component_id' => isset( $bp->favorite_notifier ) ? $bp->favorite_notifier->id : '',
			'settings' => array(
				'refresh_interval' => apply_filters( 'bpfn_refresh_interval', 60000 ),
				'animation_duration' => apply_filters( 'bpfn_animation_duration', 2000 ),
			),
			'strings' => array(
				'loading' => __( 'Loading...', 'bp-fav-notification' ),
				'error' => __( 'An error occurred', 'bp-fav-notification' ),
				'favoriting' => __( 'Adding to favorites...', 'bp-fav-notification' ),
				'unfavoriting' => __( 'Removing from favorites...', 'bp-fav-notification' ),
				'favorited' => __( 'Added to favorites!', 'bp-fav-notification' ),
				'unfavorited' => __( 'Removed from favorites!', 'bp-fav-notification' ),
			),
		);
		
		// Initialize config properly
		$data['config'] = array(
			'debug' => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'bpfn-nonce' ),
			'userId' => get_current_user_id(),
			'componentId' => isset( $bp->favorite_notifier ) ? $bp->favorite_notifier->id : '',
		);
		
		return apply_filters( 'bpfn_localized_data', $data );
	}

	/**
	 * Add inline styles
	 */
	public function add_inline_styles( $styles ) {
		if ( ! empty( $styles ) ) {
			wp_add_inline_style( 'bpfn-notifications', $styles );
		}
	}

	/**
	 * Add inline scripts
	 */
	public function add_inline_script( $script, $position = 'after' ) {
		if ( ! empty( $script ) ) {
			wp_add_inline_script( 'bpfn-notifications', $script, $position );
		}
	}
	
	/**
	 * Fallback method to ensure admin assets are loaded
	 */
	public function enqueue_admin_assets_fallback() {
		// Only run if we're on our admin pages and assets haven't been loaded
		if ( ! isset( $_GET['page'] ) || strpos( $_GET['page'], 'bpfn' ) === false ) {
			return;
		}
		
		// Check if assets are already enqueued
		if ( wp_style_is( 'bpfn-admin', 'enqueued' ) ) {
			return;
		}
		
		// If we're here, assets haven't been loaded, so load them
		global $hook_suffix;
		$this->enqueue_admin_assets( $hook_suffix );
	}
}