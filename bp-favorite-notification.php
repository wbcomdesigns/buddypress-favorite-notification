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
		// Load required files FIRST
		$this->load_dependencies();
		
		// Load admin module early for menu registration
		if ( is_admin() ) {
			$this->load_admin_module();
		}
		
		// THEN setup hooks
		add_action( 'plugins_loaded', array( $this, 'early_init' ), 20 );
		add_action( 'bp_loaded', array( $this, 'init' ), 10 );
		
		// Activation/Deactivation hooks
		register_activation_hook( BPFN_PLUGIN_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( BPFN_PLUGIN_FILE, array( $this, 'deactivate' ) );
	}

	/**
	 * Load admin module early
	 */
	private function load_admin_module() {
		$admin_path = BPFN_INCLUDES_PATH . 'modules/class-admin.php';
		if ( file_exists( $admin_path ) ) {
			require_once $admin_path;
			if ( class_exists( 'BPFN_Module_Admin' ) ) {
				$this->modules['admin'] = new BPFN_Module_Admin();
			}
		}
	}

	/**
	 * Early initialization - for things that need to run before init
	 */
	public function early_init() {
		// Check dependencies
		if ( ! class_exists( 'BuddyPress' ) ) {
			add_action( 'admin_notices', array( $this, 'admin_notice_buddypress_required' ) );
			return false;
		}
		
		return true;
	}

	/**
	 * Load dependencies
	 */
	private function load_dependencies() {
		// Core files that are always needed
		$core_files = array(
			'functions/core-functions.php',
			'functions/template-functions.php',
			'functions/api-functions.php',
			'functions/integration-functions.php',
		);

		foreach ( $core_files as $file ) {
			if ( file_exists( BPFN_INCLUDES_PATH . $file ) ) {
				require_once BPFN_INCLUDES_PATH . $file;
			}
		}
	}

	/**
	 * Initialize plugin
	 */
	public function init() {
		// Check dependencies first
		if ( ! $this->check_dependencies() ) {
			return;
		}
		
		// Load textdomain - now at the proper time
		$this->load_textdomain();
		
		// Initialize modules after BuddyPress loads
		add_action( 'bp_include', array( $this, 'load_modules' ) );
		
		// Setup BuddyPress integration
		add_action( 'bp_setup_globals', array( $this, 'setup_globals' ) );
		
		// Allow developers to hook into initialization
		do_action( 'bpfn_init', $this );
	}

	/**
	 * Load modules
	 */
	public function load_modules() {
		// Core module files (skip admin if already loaded)
		$module_files = array(
			'modules/class-notifications.php',
			'modules/class-email.php',
			'modules/class-realtime.php',
			'modules/class-settings.php',
			'modules/class-assets.php',
		);
		
		// Add admin if not already loaded
		if ( ! isset( $this->modules['admin'] ) ) {
			array_unshift( $module_files, 'modules/class-admin.php' );
		}

		// Load each module
		foreach ( $module_files as $file ) {
			$path = BPFN_INCLUDES_PATH . $file;
			if ( file_exists( $path ) ) {
				require_once $path;
				
				// Get class name from file
				$class_name = $this->get_class_name_from_file( $file );
				if ( class_exists( $class_name ) ) {
					$module_key = strtolower( str_replace( 'BPFN_Module_', '', $class_name ) );
					if ( ! isset( $this->modules[ $module_key ] ) ) {
						$this->modules[ $module_key ] = new $class_name();
					}
				}
			}
		}

		// Allow additional modules to be loaded
		do_action( 'bpfn_load_modules', $this );
	}

	/**
	 * Get class name from file path
	 */
	private function get_class_name_from_file( $file ) {
		$filename = basename( $file, '.php' );
		$class_parts = explode( '-', $filename );
		$class_name = 'BPFN_Module';
		
		foreach ( $class_parts as $part ) {
			if ( $part !== 'class' ) {
				$class_name .= '_' . ucfirst( $part );
			}
		}
		
		return $class_name;
	}

	/**
	 * Setup BuddyPress globals
	 */
	public function setup_globals() {
		if ( ! bp_is_active( 'notifications' ) ) {
			return;
		}

		global $bp;
		
		// Create component object
		$bp->{$this->component_id} = new stdClass();
		$bp->{$this->component_id}->id = $this->component_id;
		$bp->{$this->component_id}->slug = $this->component_slug;
		$bp->{$this->component_id}->notification_callback = array( $this, 'notification_callback' );
		
		// Register component
		$bp->active_components[ $this->component_id ] = $this->component_id;
		
		// Allow customization
		do_action( 'bpfn_setup_globals', $this );
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
	 * Check dependencies
	 */
	public function check_dependencies() {
		if ( ! class_exists( 'BuddyPress' ) ) {
			return false;
		}
		
		return apply_filters( 'bpfn_dependencies_met', true );
	}

	/**
	 * Admin notice for BuddyPress requirement
	 */
	public function admin_notice_buddypress_required() {
		// Only show notice after init when translations are loaded
		if ( ! did_action( 'init' ) ) {
			return;
		}
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
		// Create database tables
		$this->create_tables();
		
		// Set default options
		update_option( 'bpfn_version', BPFN_VERSION );
		
		// Allow modules to run activation routines
		do_action( 'bpfn_activate' );
	}

	/**
	 * Plugin deactivation
	 */
	public function deactivate() {
		// Allow modules to run deactivation routines
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
		
		// Verify table was created
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name;
		if ( ! $table_exists ) {
			error_log( 'BPFN: Failed to create preferences table' );
		}
		
		// Allow additional tables to be created
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