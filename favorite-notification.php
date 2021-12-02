<?php
/**
 * Plugin Name: BuddyPress Favorite Notification
 * Plugin URI: http://www.wbcomdesigns.com/
 * Description: Adds notification for the activity Favorite for the activity user.
 * Version: 1.2.0
 * Text Domain: bp-fav-notification
 * Author: Wbcom Designs<admin@wbcomdesigns.com>
 * Author URI: http://www.wbcomdesigns.com/
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package BuddyPress_Favorite_Notification
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! defined( 'WB_BP_FAV_NOTIFICATION_NAME' ) ) {
	define( 'WB_BP_FAV_NOTIFICATION_NAME', 'Buddypress Favorite Notification' );
}
if ( ! defined( 'WB_BP_FAV_NOTIFICATION_VERSION' ) ) {
	define( 'WB_BP_FAV_NOTIFICATION_VERSION', '1.2.0' );
}
if ( ! defined( 'WB_BP_FAV_NOTIFICATION_PLUGIN_PATH' ) ) {
	define( 'WB_BP_FAV_NOTIFICATION_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'WB_BP_FAV_NOTIFICATION_PLUGIN_URL' ) ) {
	define( 'WB_BP_FAV_NOTIFICATION_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'WB_BP_FAV_NOTIFICATION_UPDATER_ID' ) ) {
	define( 'WB_BP_FAV_NOTIFICATION_UPDATER_ID', 200 );
}

	// Activation Hook.
	register_activation_hook( __FILE__, 'wb_bp_fav_notify_activate' );
	// Deactivation Hook.
	register_deactivation_hook( __FILE__, 'wb_bp_fav_notify_deactivate' );

	/**
	 * Activation Hook to add default option values
	 *
	 * @author   Wbcom Designs
	 * @package   BuddyPress Add Notification
	 * @since    1.0.0
	 */
function wb_bp_fav_notify_activate() {
	if ( ! in_array( 'buddypress/bp-loader.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
		// Buddypress Plugin is inactive, hence deactivate this plugin.
		deactivate_plugins( plugin_basename( __FILE__ ) );
	} else {
		update_option( 'wb-bp-fav-notification-version', WB_BP_FAV_NOTIFICATION_VERSION );
		update_option( 'wb-bp-fav-notification-updater-id', WB_BP_FAV_NOTIFICATION_UPDATER_ID );
	}
}

	/**
	 * Deactivation Hook to remove default option values if user has marked to delete them
	 *
	 * @author   Wbcom Designs
	 * @since    1.0.0
	 * @package   BuddyPress Add Notification
	 */
function wb_bp_fav_notify_deactivate() {
	delete_option( 'wb-bp-fav-notification-version' );
	delete_option( 'wb-bp-fav-notification-updater-id' );
}

if ( ! function_exists( 'bp_fav_noti_plugin_files' ) ) {
	add_action( 'plugins_loaded', 'bp_fav_noti_plugin_files' );

		/**
		 * Include require files
		 *
		 * @author   Wbcom Designs
		 * @since    1.0.0
		 * @package   BuddyPress Add Notification
		 */
	function bp_fav_noti_plugin_files() {
		if ( class_exists( 'BuddyPress' ) ) {
			$include_files = array(
				'include/bpfn-notification.php',
				'include/class-bpfn-functions.php',
				'include/class-bpfn-admin-feedback.php',
			);
			foreach ( $include_files as $include_file ) {
				include $include_file;
			}
		}
	}
}


if ( ! function_exists( 'bp_fav_noti_check_requre_plugin' ) ) {
	add_action( 'admin_init', 'bp_fav_noti_check_requre_plugin' );

	/**
	 * This function check if buddypress is activated or not and print a notice for admin.
	 */
	function bp_fav_noti_check_requre_plugin() {
		if ( ! class_exists( 'BuddyPress' ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			add_action( 'admin_notices', 'bp_fav_noti_admin_notice' );
		}
	}
}


if ( ! function_exists( 'bp_fav_noti_admin_notice' ) ) {
	/**
	 * Message print as admin notice.
	 *
	 * @return void
	 */
	function bp_fav_noti_admin_notice() {
		$plugin            = esc_html__( 'BuddyPress Favorite Notification', 'bp-fav-notification' );
		$buddypress_plugin = esc_html__( 'BuddyPress', 'bp-fav-notification' );

		echo '<div class="error"><p>';
		/* translators: %s: */
		echo sprintf( esc_html__( '%1$s is ineffective now as it requires %2$s to be installed and active.', 'bp-fav-notification' ), '<strong>' . esc_html( $plugin ) . '</strong>', '<strong>' . esc_html( $buddypress_plugin ) . '</strong>' );
		echo '</p></div>';
		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		}

	}
}
