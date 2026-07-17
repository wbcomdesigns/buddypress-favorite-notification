<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- Legacy file name.
/**
 * Clean Assets Module for BuddyPress Favorite Notification.
 *
 * @package BuddyPress_Favorite_Notification
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clean Assets Module Class.
 */
// phpcs:ignore Squiz.Commenting.ClassComment.Missing -- Class docblock is above.
class BPFN_Module_Assets {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Setup hooks.
	 */
	private function setup_hooks() {
		// Frontend assets. Admin assets are owned by BPFN_Admin::enqueue_assets()
		// (includes/admin/class-bpfn-admin.php) — do not duplicate them here.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}

	/**
	 * Enqueue frontend assets.
	 */
	public function enqueue_frontend_assets() {
		// Check if we should load assets.
		if ( ! $this->should_load_assets() ) {
			return;
		}

		// Core styles (shared --bpfn-* tokens + count badge / loading state).
		wp_enqueue_style(
			'bpfn-notifications',
			BPFN_ASSETS_URL . 'css/notifications.css',
			array(),
			BPFN_VERSION
		);

		// Real-time notifications.
		if ( $this->should_load_realtime() ) {
			$this->enqueue_realtime_assets();
		}

		// Favorite display assets.
		$this->enqueue_favorite_display_assets();

		// Member "Settings > Favorite Notifications" screen styles
		// (templates/settings/notifications.php, BPFN_Module_Settings).
		if ( function_exists( 'bp_is_settings_component' ) && bp_is_settings_component() && bp_is_current_action( 'notifications' ) ) {
			wp_enqueue_style(
				'bpfn-settings',
				BPFN_ASSETS_URL . 'css/settings.css',
				array( 'bpfn-notifications' ),
				BPFN_VERSION
			);
		}
	}

	/**
	 * Enqueue favorite display assets.
	 */
	private function enqueue_favorite_display_assets() {
		// Favorite display styles.
		wp_enqueue_style(
			'bpfn-favorite-display',
			BPFN_ASSETS_URL . 'css/favorite-display.css',
			array( 'bpfn-notifications' ),
			BPFN_VERSION
		);

		// Favorite display scripts.
		wp_enqueue_script(
			'bpfn-favorite-display',
			BPFN_ASSETS_URL . 'js/favorite-display.js',
			array( 'jquery' ),
			BPFN_VERSION,
			true
		);

		// Localize favorite display script.
		wp_localize_script(
			'bpfn-favorite-display',
			'bpfnFavorites',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'bpfn-favorite-nonce' ),
				'strings'  => array(
					'loading'    => esc_html__( 'Loading…', 'buddypress-favorite-notification' ),
					'loadFailed' => esc_html__( 'Failed to load favorites list.', 'buddypress-favorite-notification' ),
					'loadError'  => esc_html__( 'An error occurred while loading favorites.', 'buddypress-favorite-notification' ),
					'close'      => esc_attr__( 'Close', 'buddypress-favorite-notification' ),
				),
			)
		);
	}

	/**
	 * Enqueue real-time assets.
	 */
	private function enqueue_realtime_assets() {
		// Heartbeat API.
		wp_enqueue_script( 'heartbeat' );

		// Real-time styles.
		wp_enqueue_style(
			'bpfn-realtime',
			BPFN_ASSETS_URL . 'css/realtime.css',
			array( 'bpfn-notifications' ),
			BPFN_VERSION
		);

		// Real-time scripts.
		wp_enqueue_script(
			'bpfn-realtime',
			BPFN_ASSETS_URL . 'js/realtime.js',
			array( 'jquery', 'heartbeat' ),
			BPFN_VERSION,
			true
		);

		// Get realtime configuration.
		$realtime_module = bpfn()->get_module( 'realtime' );
		$polling_config  = $realtime_module ? $realtime_module->get_polling_config() : $this->get_fallback_realtime_config();

		// Localize real-time script.
		wp_localize_script(
			'bpfn-realtime',
			'BPFNRealtime',
			array(
				'ajax_url'         => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'bpfn_realtime_nonce' ),
				'checkInterval'    => $polling_config['interval'],
				'position'         => $polling_config['position'],
				'maxNotifications' => $polling_config['max_notifications'],
				'autoDismiss'      => $polling_config['auto_dismiss_time'],
				'strings'          => array(
					'new_notification' => esc_html__( 'New notification', 'buddypress-favorite-notification' ),
					'view_activity'    => esc_html__( 'View Activity', 'buddypress-favorite-notification' ),
					'dismiss'          => esc_html__( 'Dismiss', 'buddypress-favorite-notification' ),
					// Fallbacks realtime.js renders when the server payload has no
					// `time_ago` / `text` of its own. Previously JS-only literals,
					// so they stayed English on every locale.
					'just_now'         => esc_html__( 'just now', 'buddypress-favorite-notification' ),
					'default_message'  => esc_html__( 'Someone favorited your activity', 'buddypress-favorite-notification' ),
				),
			)
		);
	}

	/**
	 * Get fallback realtime configuration.
	 *
	 * @return array Fallback config.
	 */
	private function get_fallback_realtime_config() {
		return array(
			'enabled'           => true,
			'interval'          => 15000,
			'max_notifications' => 5,
			'auto_dismiss_time' => 5000,
			'position'          => 'bottom-right',
		);
	}

	/**
	 * Check if assets should be loaded.
	 *
	 * @return bool Whether to load assets.
	 */
	private function should_load_assets() {
		// Don't load if user is not logged in.
		if ( ! is_user_logged_in() ) {
			return false;
		}

		// Don't load if notifications component is not active.
		if ( ! bp_is_active( 'notifications' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if real-time assets should be loaded.
	 *
	 * @return bool Whether to load realtime assets.
	 */
	private function should_load_realtime() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}

		return true;
	}

}
