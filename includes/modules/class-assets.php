<?php
/**
 * Enhanced Assets Module for BuddyPress Favorite Notification
 * Complete replacement for includes/modules/class-assets.php
 *
 * @package BuddyPress_Favorite_Notification
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enhanced Assets Module Class
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
		
		// Ensure jQuery is loaded
		wp_enqueue_script( 'jquery' );
		
		// Core styles
		wp_enqueue_style(
			'bpfn-notifications',
			BPFN_ASSETS_URL . 'css/notifications.css',
			array(),
			BPFN_VERSION
		);
		
		// Core scripts with proper dependencies
		wp_enqueue_script(
			'bpfn-notifications',
			BPFN_ASSETS_URL . 'js/notifications.js',
			array( 'jquery' ),
			BPFN_VERSION,
			true  // Load in footer
		);
		
		// Localize main script
		wp_localize_script( 'bpfn-notifications', 'BPFN', $this->get_localized_data() );
		
		// Initialize script inline after localization
		wp_add_inline_script( 'bpfn-notifications', '
			jQuery(document).ready(function($) {
				if (typeof BPFN !== "undefined" && BPFN.init) {
					BPFN.init(BPFN.config || {});
				}
			});
		', 'after' );
		
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
	 * Enqueue real-time assets with enhanced configuration
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
		
		// Get enhanced realtime configuration
		$realtime_module = bpfn()->get_module( 'realtime' );
		$polling_config = $realtime_module ? $realtime_module->get_polling_config() : $this->get_fallback_realtime_config();
		
		// Localize real-time script with enhanced config
		wp_localize_script( 'bpfn-realtime', 'BPFNRealtime', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'bpfn_realtime_nonce' ),
			'polling' => $polling_config,
			'checkInterval' => $polling_config['interval'],
			'position' => $polling_config['position'],
			'maxNotifications' => $polling_config['max_notifications'],
			'autoDismiss' => $polling_config['auto_dismiss_time'],
			'methods' => $polling_config['methods'] ?? array(
				'heartbeat' => true,
				'polling' => true,
				'sse' => false
			),
			'preferredMethod' => $polling_config['preferred_method'] ?? 'polling',
			'retryConfig' => $polling_config['retry_config'] ?? array(
				'max_retries' => 5,
				'retry_delay' => 2000,
				'backoff_multiplier' => 1.5,
				'max_backoff_delay' => 300000
			),
			'connectionTimeout' => 30000,
			'debug' => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'strings' => array(
				'new_notification' => __( 'New notification', 'bp-fav-notification' ),
				'view_activity' => __( 'View Activity', 'bp-fav-notification' ),
				'dismiss' => __( 'Dismiss', 'bp-fav-notification' ),
				'connection_lost' => __( 'Connection lost, trying to reconnect...', 'bp-fav-notification' ),
				'connection_restored' => __( 'Connection restored', 'bp-fav-notification' ),
			),
		) );
		
		// Initialize real-time after localization with enhanced error handling
		wp_add_inline_script( 'bpfn-realtime', '
			jQuery(document).ready(function($) {
				// Initialize realtime with enhanced error handling
				if (window.BPFN && window.BPFN.Realtime && !window.BPFN.Realtime.state.initialized) {
					window.BPFN.Realtime.init().catch(function(error) {
						console.warn("[BPFN] Real-time initialization failed:", error.message);
						
						// Show user-friendly notification in debug mode
						if (window.BPFNRealtime && window.BPFNRealtime.debug) {
							console.info("[BPFN] Real-time notifications will use polling fallback");
						}
					});
				}
			});
		', 'after' );
	}

	/**
	 * Get fallback realtime configuration when module isn't available
	 */
	private function get_fallback_realtime_config() {
		$options = get_option( 'bpfn_options', array() );
		
		return array(
			'enabled' => true,
			'methods' => array(
				'heartbeat' => true,
				'polling' => true,
				'sse' => false
			),
			'preferred_method' => 'polling',
			'interval' => ! empty( $options['realtime_interval'] ) ? 
				intval( $options['realtime_interval'] ) * 1000 : 
				15000,
			'max_notifications' => 5,
			'auto_dismiss_time' => 5000,
			'position' => $options['realtime_position'] ?? 'bottom-right',
			'retry_config' => array(
				'max_retries' => 5,
				'retry_delay' => 2000,
				'backoff_multiplier' => 1.5,
				'max_backoff_delay' => 300000
			)
		);
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
	 * Enqueue admin assets with enhanced functionality
	 */
	public function enqueue_admin_assets( $hook ) {
		// Check if we're on any of our admin pages
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
		
		// Enhanced localization for admin script
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
				'optimizing' => __( 'Optimizing configuration...', 'bp-fav-notification' ),
				'optimization_complete' => __( 'Configuration optimized!', 'bp-fav-notification' ),
				'testing_methods' => __( 'Testing notification methods...', 'bp-fav-notification' ),
				'heartbeat_test' => __( 'Testing WordPress Heartbeat...', 'bp-fav-notification' ),
				'sse_test' => __( 'Testing Server-Sent Events...', 'bp-fav-notification' ),
				'polling_test' => __( 'Testing AJAX Polling...', 'bp-fav-notification' ),
			),
			'system_info' => $this->get_admin_system_info(),
		) );
		
		// Add color picker if needed
		if ( isset( $_GET['page'] ) && $_GET['page'] === 'bpfn-settings' ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-color-picker' );
			
			// Add Chart.js for stats
			wp_enqueue_script(
				'chart-js',
				'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
				array(),
				'3.9.1',
				true
			);
		}
		
		// Tools page specific assets
		if ( isset( $_GET['page'] ) && $_GET['page'] === 'bpfn-tools' ) {
			// Include enhanced testing functionality
			wp_add_inline_script( 'bpfn-admin', $this->get_enhanced_testing_script(), 'after' );
		}
		
		// Allow additional admin assets
		do_action( 'bpfn_enqueue_admin_assets', $hook );
	}

	/**
	 * Get system info for admin
	 */
	private function get_admin_system_info() {
		$realtime_module = bpfn()->get_module( 'realtime' );
		
		return array(
			'php_version' => PHP_VERSION,
			'wp_version' => get_bloginfo( 'version' ),
			'bp_version' => defined( 'BP_VERSION' ) ? BP_VERSION : 'Not installed',
			'plugin_version' => BPFN_VERSION,
			'realtime_available' => $realtime_module !== null,
			'heartbeat_supported' => $realtime_module ? $realtime_module->get_system_status()['heartbeat_available'] : false,
			'sse_supported' => $realtime_module ? $realtime_module->get_system_status()['sse_supported'] : false,
		);
	}

	/**
	 * Get enhanced testing script for tools page
	 */
	private function get_enhanced_testing_script() {
		return '
		// Enhanced testing functionality for BPFN Tools
		window.BPFNTesting = {
			testHeartbeat: function() {
				return new Promise((resolve, reject) => {
					if (typeof wp === "undefined" || !wp.heartbeat) {
						reject(new Error("WordPress Heartbeat not available"));
						return;
					}
					
					var timeout = setTimeout(() => {
						jQuery(document).off(".bpfn-test");
						reject(new Error("Heartbeat test timeout"));
					}, 10000);
					
					jQuery(document).on("heartbeat-send.bpfn-test", function(e, data) {
						data.bpfn_test = { timestamp: Date.now() };
					});
					
					jQuery(document).on("heartbeat-tick.bpfn-test", function(e, data) {
						if (data.bpfn_test_response) {
							clearTimeout(timeout);
							jQuery(document).off(".bpfn-test");
							resolve({ 
								success: true, 
								responseTime: Date.now() - data.bpfn_test_response.timestamp 
							});
						}
					});
					
					jQuery(document).on("heartbeat-error.bpfn-test", function() {
						clearTimeout(timeout);
						jQuery(document).off(".bpfn-test");
						reject(new Error("Heartbeat error occurred"));
					});
					
					wp.heartbeat.connectNow();
				});
			},
			
			testPolling: function() {
				return new Promise((resolve, reject) => {
					var startTime = Date.now();
					
					jQuery.ajax({
						url: ajaxurl,
						type: "POST",
						timeout: 10000,
						data: {
							action: "bpfn_check_notifications",
							last_checked: Math.floor(Date.now() / 1000),
							nonce: bpfnAdmin.nonce
						},
						success: function(response) {
							var responseTime = Date.now() - startTime;
							if (response.success) {
								resolve({ 
									success: true, 
									responseTime: responseTime,
									notifications: response.data.notifications.length 
								});
							} else {
								reject(new Error(response.data?.message || "Polling failed"));
							}
						},
						error: function(xhr, status, error) {
							reject(new Error("AJAX Error: " + error));
						}
					});
				});
			},
			
			testSSE: function() {
				return new Promise((resolve, reject) => {
					if (!window.EventSource) {
						reject(new Error("SSE not supported by browser"));
						return;
					}
					
					var testUrl = ajaxurl + "?action=bpfn_sse_test&nonce=" + encodeURIComponent(bpfnAdmin.nonce);
					var eventSource = new EventSource(testUrl);
					var startTime = Date.now();
					
					var timeout = setTimeout(() => {
						eventSource.close();
						reject(new Error("SSE test timeout"));
					}, 15000);
					
					eventSource.onopen = function() {
						// SSE connection opened successfully
					};
					
					eventSource.addEventListener("close", function(event) {
						clearTimeout(timeout);
						var duration = Date.now() - startTime;
						eventSource.close();
						resolve({ 
							success: true, 
							duration: duration 
						});
					});
					
					eventSource.onerror = function(error) {
						clearTimeout(timeout);
						eventSource.close();
						reject(new Error("SSE connection error"));
					};
				});
			}
		};
		';
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
	 * Check if real-time assets should be loaded with enhanced detection
	 */
	private function should_load_realtime() {
		$user_id = get_current_user_id();
		
		// Quick check - if user has any realtime enabled
		$settings = bpfn_get_user_settings( $user_id );
		
		// Check if any notification type has real-time enabled
		foreach ( $settings as $type => $options ) {
			if ( ! empty( $options['realtime_enabled'] ) ) {
				return true;
			}
		}
		
		// Allow forcing realtime assets for testing
		if ( current_user_can( 'manage_options' ) && isset( $_GET['bpfn_force_realtime'] ) ) {
			return true;
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
	 * Get localized data for scripts with enhanced configuration
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
				'connection_error' => __( 'Connection error occurred', 'bp-fav-notification' ),
				'retry_in' => __( 'Retrying in %d seconds', 'bp-fav-notification' ),
			),
		);
		
		// Enhanced config for better reliability
		$data['config'] = array(
			'debug' => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'bpfn-nonce' ),
			'userId' => get_current_user_id(),
			'componentId' => isset( $bp->favorite_notifier ) ? $bp->favorite_notifier->id : '',
			'version' => BPFN_VERSION,
			'environment' => array(
				'is_admin' => is_admin(),
				'is_buddypress' => function_exists( 'buddypress' ),
				'current_page' => $this->get_current_page_info(),
			),
		);
		
		return apply_filters( 'bpfn_localized_data', $data );
	}

	/**
	 * Get current page information for better asset loading decisions
	 */
	private function get_current_page_info() {
		$page_info = array(
			'is_bp_page' => function_exists( 'bp_is_page' ) ? bp_is_page() : false,
			'is_activity' => function_exists( 'bp_is_activity_component' ) ? bp_is_activity_component() : false,
			'is_notifications' => function_exists( 'bp_is_notifications_component' ) ? bp_is_notifications_component() : false,
			'is_user_profile' => function_exists( 'bp_is_user' ) ? bp_is_user() : false,
		);
		
		return $page_info;
	}

	/**
	 * Add inline styles with theme compatibility
	 */
	public function add_inline_styles( $styles ) {
		if ( ! empty( $styles ) ) {
			wp_add_inline_style( 'bpfn-notifications', $styles );
		}
	}

	/**
	 * Add inline scripts with dependency management
	 */
	public function add_inline_script( $script, $position = 'after' ) {
		if ( ! empty( $script ) ) {
			wp_add_inline_script( 'bpfn-notifications', $script, $position );
		}
	}

	/**
	 * Get asset loading statistics for debugging
	 */
	public function get_asset_stats() {
		global $wp_scripts, $wp_styles;
		
		$stats = array(
			'scripts_loaded' => array(),
			'styles_loaded' => array(),
			'realtime_enabled' => $this->should_load_realtime(),
			'enhanced_enabled' => $this->should_load_enhanced(),
			'total_scripts' => 0,
			'total_styles' => 0,
		);
		
		// Get BPFN scripts
		foreach ( $wp_scripts->registered as $handle => $script ) {
			if ( strpos( $handle, 'bpfn' ) !== false ) {
				$stats['scripts_loaded'][] = $handle;
				$stats['total_scripts']++;
			}
		}
		
		// Get BPFN styles
		foreach ( $wp_styles->registered as $handle => $style ) {
			if ( strpos( $handle, 'bpfn' ) !== false ) {
				$stats['styles_loaded'][] = $handle;
				$stats['total_styles']++;
			}
		}
		
		return $stats;
	}

	/**
	 * Optimize asset loading based on page context
	 */
	public function optimize_asset_loading() {
		// Skip non-essential assets on certain pages
		if ( is_admin() && ! ( isset( $_GET['page'] ) && strpos( $_GET['page'], 'bpfn' ) !== false ) ) {
			remove_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		}
		
		// Conditionally load realtime assets
		if ( ! $this->should_load_realtime() ) {
			add_filter( 'bpfn_load_realtime_assets', '__return_false' );
		}
		
		// Defer non-critical scripts
		add_filter( 'script_loader_tag', array( $this, 'defer_non_critical_scripts' ), 10, 2 );
	}

	/**
	 * Defer non-critical scripts for better performance
	 */
	public function defer_non_critical_scripts( $tag, $handle ) {
		$defer_scripts = array( 'bpfn-realtime', 'bpfn-enhanced' );
		
		if ( in_array( $handle, $defer_scripts ) ) {
			return str_replace( ' src', ' defer src', $tag );
		}
		
		return $tag;
	}
}