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

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants
define( 'BPFN_VERSION', '1.2.3' );
define( 'BPFN_PLUGIN_FILE', __FILE__ );
define( 'BPFN_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BPFN_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'BPFN_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'BPFN_ASSETS_URL', BPFN_PLUGIN_URL . 'assets/' );
define( 'BPFN_INCLUDES_PATH', BPFN_PLUGIN_PATH . 'includes/' );
define( 'BPFN_TEMPLATES_PATH', BPFN_PLUGIN_PATH . 'templates/' );

/**
 * Main plugin class
 */
class BP_Favorite_Notification {

	/**
	 * Instance of this class
	 */
	private static $instance = null;

	/**
	 * Modules container
	 */
	private $modules = array();

	/**
	 * Component ID
	 */
	public $component_id = 'favorite_notifier';

	/**
	 * Component slug
	 */
	public $component_slug = 'favorite_notification';

	/**
	 * Get instance of the class
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		// Check dependencies
		add_action( 'plugins_loaded', array( $this, 'check_dependencies' ), 5 );
		
		// Initialize plugin
		add_action( 'bp_loaded', array( $this, 'init' ), 10 );
		
		// Load textdomain
		add_action( 'init', array( $this, 'load_textdomain' ), 5 );
		
		// Activation/Deactivation hooks
		register_activation_hook( BPFN_PLUGIN_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( BPFN_PLUGIN_FILE, array( $this, 'deactivate' ) );
	}

	/**
	 * Check dependencies
	 */
	public function check_dependencies() {
		if ( ! class_exists( 'BuddyPress' ) ) {
			add_action( 'admin_notices', array( $this, 'admin_notice_buddypress_required' ) );
			return false;
		}
		
		// Load core files
		$this->load_dependencies();
		
		return true;
	}

	/**
	 * Load dependencies
	 */
	private function load_dependencies() {
		// Load compatibility layer first
		require_once BPFN_INCLUDES_PATH . 'compat/buddypress-compat.php';
		
		// Core function files
		$files = array(
			'functions/core-functions.php',
			'functions/template-functions.php',
			'functions/api-functions.php',
			'functions/integration-functions.php',
		);

		foreach ( $files as $file ) {
			$path = BPFN_INCLUDES_PATH . $file;
			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}
	}

	/**
	 * Initialize plugin
	 */
	public function init() {
		// Load modules - let compat layer handle component setup
		add_action( 'bp_init', array( $this, 'load_modules' ), 5 );
		
		// Allow developers to hook into initialization
		do_action( 'bpfn_init', $this );
	}

	/**
	 * Load modules
	 */
	public function load_modules() {
		$modules = array(
			'notifications' => 'class-notifications.php',
			'email' => 'class-email.php',
			'realtime' => 'class-realtime.php',
			'settings' => 'class-settings.php',
			'assets' => 'class-assets.php',
			'admin' => 'class-admin.php',
			'debug' => 'class-debug.php', // Add debug module
		);

		foreach ( $modules as $key => $file ) {
			$path = BPFN_INCLUDES_PATH . 'modules/' . $file;
			if ( file_exists( $path ) ) {
				require_once $path;
				
				$class_name = 'BPFN_Module_' . ucfirst( $key );
				if ( class_exists( $class_name ) ) {
					$this->modules[ $key ] = new $class_name();
				}
			}
		}

		// Allow additional modules
		do_action( 'bpfn_load_modules', $this );
	}

	/**
	 * Notification callback
	 */
	public function notification_callback( $action, $item_id, $secondary_item_id, $total_items, $format = 'string' ) {
		if ( isset( $this->modules['notifications'] ) ) {
			return $this->modules['notifications']->format_notification( $action, $item_id, $secondary_item_id, $total_items, $format );
		}
		return false;
	}

	/**
	 * Admin notice for BuddyPress requirement
	 */
	public function admin_notice_buddypress_required() {
		?>
		<div class="error">
			<p><?php printf( 
				__( '%1$s is ineffective now as it requires %2$s to be installed and active.', 'bp-fav-notification' ), 
				'<strong>' . __( 'BuddyPress Favorite Notification', 'bp-fav-notification' ) . '</strong>', 
				'<strong>' . __( 'BuddyPress', 'bp-fav-notification' ) . '</strong>' 
			); ?></p>
		</div>
		<?php
	}

	/**
	 * Load textdomain
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'bp-fav-notification', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Plugin activation
	 */
	public function activate() {
		$this->create_tables();
		update_option( 'bpfn_version', BPFN_VERSION );
		do_action( 'bpfn_activate' );
	}

	/**
	 * Plugin deactivation
	 */
	public function deactivate() {
		do_action( 'bpfn_deactivate' );
	}

	/**
	 * Create database tables
	 */
	private function create_tables() {
		global $wpdb;
		
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		
		$charset_collate = $wpdb->get_charset_collate();
		$table_name = $wpdb->prefix . 'bp_favorite_notification_prefs';
		
		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			notification_type varchar(50) NOT NULL,
			is_enabled tinyint(1) DEFAULT 1,
			email_enabled tinyint(1) DEFAULT 1,
			realtime_enabled tinyint(1) DEFAULT 1,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY user_notification_type (user_id, notification_type),
			KEY user_id (user_id)
		) $charset_collate;";
		
		dbDelta( $sql );
		
		do_action( 'bpfn_create_tables', $wpdb, $charset_collate );
	}

	/**
	 * Get a module instance
	 */
	public function get_module( $module_name ) {
		return isset( $this->modules[ $module_name ] ) ? $this->modules[ $module_name ] : null;
	}

	/**
	 * Register a new module
	 */
	public function register_module( $module_name, $module_instance ) {
		$this->modules[ $module_name ] = $module_instance;
	}
}

/**
 * Get plugin instance
 */
function bpfn() {
	return BP_Favorite_Notification::get_instance();
}

// Initialize plugin
bpfn();