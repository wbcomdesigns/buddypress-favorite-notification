<?php
/**
 * Plugin Name: BuddyPress Favorite Notification
 * Plugin URI: http://www.wbcomdesigns.com/
 * Description: Adds notification for the activity Favorite for the activity user.
 * Version: 1.2.3
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
	define( 'WB_BP_FAV_NOTIFICATION_VERSION', '1.2.3' );
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
	bpfn_create_notification_preferences_table();
}

	/**
	 * Create user notification preferences table on plugin activation
	 */
function bpfn_create_notification_preferences_table() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'bp_favorite_notification_prefs';
	
	if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
		$charset_collate = $wpdb->get_charset_collate();
		
		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			notification_type varchar(50) NOT NULL,
			is_enabled tinyint(1) DEFAULT 1,
			email_enabled tinyint(1) DEFAULT 1,
			realtime_enabled tinyint(1) DEFAULT 1,
			PRIMARY KEY (id),
			UNIQUE KEY user_notification_type (user_id, notification_type)
		) $charset_collate;";
		
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
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
		echo sprintf( esc_html__( '%1$s is currently inactive because it requires %2$s to be installed and activated.', 'bp-fav-notification' ), '<strong>' . esc_html( $plugin ) . '</strong>', '<strong>' . esc_html( $buddypress_plugin ) . '</strong>' );
		echo '</p></div>';

	}
}
