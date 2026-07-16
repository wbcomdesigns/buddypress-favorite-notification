<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- Legacy file name.
/**
 * Plugin Name: BuddyPress Favorite Notification
 * Plugin URI: http://www.wbcomdesigns.com/
 * Description: Adds notification for the activity Favorite for the activity user.
 * Version: 2.0.1
 * Requires at least: 6.1
 * Requires PHP: 7.4
 * Requires Plugins: buddypress
 * Text Domain: buddypress-favorite-notification
 * Author: Wbcom Designs<admin@wbcomdesigns.com>
 * Author URI: http://www.wbcomdesigns.com/
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package BuddyPress_Favorite_Notification
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'BPFN_VERSION', '2.0.1' );
define( 'BPFN_PLUGIN_FILE', __FILE__ );
define( 'BPFN_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BPFN_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'BPFN_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'BPFN_ASSETS_URL', BPFN_PLUGIN_URL . 'assets/' );
define( 'BPFN_INCLUDES_PATH', BPFN_PLUGIN_PATH . 'includes/' );
define( 'BPFN_TEMPLATES_PATH', BPFN_PLUGIN_PATH . 'templates/' );

/**
 * Load the plugin text domain for translation.
 *
 * WordPress only auto-loads language packs that exist on
 * translate.wordpress.org; it never reads a plugin's own languages/ folder.
 * This plugin ships its own translations, so the domain must be registered
 * here. load_plugin_textdomain() checks the WordPress.org pack first and falls
 * back to the bundled file, so both paths work.
 *
 * Runs on init: loading a text domain earlier triggers
 * _load_textdomain_just_in_time on WordPress 6.7+.
 *
 * @since 2.0.1
 * @return void
 */
function bpfn_load_textdomain() {
	load_plugin_textdomain(
		'buddypress-favorite-notification',
		false,
		basename( BPFN_PLUGIN_PATH ) . '/languages/'
	);
}
add_action( 'init', 'bpfn_load_textdomain' );

/**
 * Main plugin class
 */
class BP_Favorite_Notification {

	/**
	 * Instance of this class.
	 *
	 * @var BP_Favorite_Notification|null
	 */
	private static $instance = null;

	/**
	 * Modules container.
	 *
	 * @var array
	 */
	private $modules = array();

	/**
	 * Component ID.
	 *
	 * @var string
	 */
	public $component_id = 'favorite_notifier';

	/**
	 * Component slug.
	 *
	 * @var string
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
		// Check dependencies.
		add_action( 'plugins_loaded', array( $this, 'check_dependencies' ), 5 );

		// Initialize plugin.
		add_action( 'bp_loaded', array( $this, 'init' ), 10 );

		// Load textdomain.
		// Textdomain is auto-loaded by WordPress since 4.6 for plugins hosted on .org.

		// Activation/Deactivation hooks.
		register_activation_hook( BPFN_PLUGIN_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( BPFN_PLUGIN_FILE, array( $this, 'deactivate' ) );
	}

	/**
	 * Check dependencies.
	 *
	 * @return bool True if dependencies are met, false otherwise.
	 */
	public function check_dependencies() {
		if ( ! class_exists( 'BuddyPress' ) ) {
			add_action( 'admin_notices', array( $this, 'admin_notice_buddypress_required' ) );
			return false;
		}

		// Load core files.
		$this->load_dependencies();

		return true;
	}

	/**
	 * Load dependencies.
	 */
	private function load_dependencies() {
		// Load compatibility layer first.
		require_once BPFN_INCLUDES_PATH . 'compat/buddypress-compat.php';

		// Core function files.
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

		// Card-panel admin controller (menu + enqueue + render).
		// Owns the WB Plugins hub submenu. Admin context only.
		if ( is_admin() ) {
			$admin_controller = BPFN_INCLUDES_PATH . 'admin/class-bpfn-admin.php';
			if ( file_exists( $admin_controller ) ) {
				require_once $admin_controller;
				$panel = new BPFN_Admin();
				$panel->register();
			}
		}
	}

	/**
	 * Initialize plugin.
	 */
	public function init() {
		// Load modules - let compat layer handle component setup.
		add_action( 'bp_init', array( $this, 'load_modules' ), 5 );

		// Initialize migration hooks.
		$this->init_migration_hooks();

		// Allow developers to hook into initialization.
		do_action( 'bpfn_init', $this );
	}

	/**
	 * Initialize migration hooks.
	 */
	private function init_migration_hooks() {
		require_once BPFN_INCLUDES_PATH . 'migrations/class-favorites-migration.php';
		$migration = new BPFN_Favorites_Migration();
		$migration->register_hooks();
	}

	/**
	 * Load modules.
	 */
	public function load_modules() {
		$modules = array(
			'notifications'    => 'class-notifications.php',
			'email'            => 'class-email.php',
			'realtime'         => 'class-realtime.php',
			'assets'           => 'class-assets.php',
			'admin'            => 'class-admin.php',
			'settings'         => 'class-settings.php',
			'favorite_display' => 'class-favorite-display.php',
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

		// Allow additional modules.
		do_action( 'bpfn_load_modules', $this );
	}

	/**
	 * Notification callback.
	 *
	 * @param string $action            The notification action.
	 * @param int    $item_id           The item ID.
	 * @param int    $secondary_item_id The secondary item ID.
	 * @param int    $total_items       The total number of items.
	 * @param string $format            The notification format.
	 * @return string|false The formatted notification or false.
	 */
	public function notification_callback( $action, $item_id, $secondary_item_id, $total_items, $format = 'string' ) {
		if ( isset( $this->modules['notifications'] ) ) {
			return $this->modules['notifications']->format_notification( $action, $item_id, $secondary_item_id, $total_items, $format );
		}
		return false;
	}

	/**
	 * Admin notice for BuddyPress requirement.
	 */
	public function admin_notice_buddypress_required() {
		?>
		<div class="error">
			<p>
			<?php
			printf(
				/* translators: 1: Plugin name, 2: BuddyPress. */
				esc_html__( '%1$s is ineffective now as it requires %2$s to be installed and active.', 'buddypress-favorite-notification' ),
				'<strong>' . esc_html__( 'BuddyPress Favorite Notification', 'buddypress-favorite-notification' ) . '</strong>',
				'<strong>' . esc_html__( 'BuddyPress', 'buddypress-favorite-notification' ) . '</strong>'
			);
			?>
			</p>
		</div>
		<?php
	}


	/**
	 * Plugin activation.
	 */
	public function activate() {
		$this->create_tables();
		update_option( 'bpfn_version', BPFN_VERSION );

		// Check if migration is needed.
		require_once BPFN_INCLUDES_PATH . 'migrations/class-favorites-migration.php';
		$migration = new BPFN_Favorites_Migration();
		$stats     = $migration->get_migration_stats();

		// Set flag if migration is pending.
		if ( $stats['migration_pending'] ) {
			update_option( 'bpfn_show_migration_notice', true );
		}

		do_action( 'bpfn_activate' );
	}

	/**
	 * Plugin deactivation.
	 */
	public function deactivate() {
		do_action( 'bpfn_deactivate' );
	}

	/**
	 * Create database tables.
	 */
	private function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		// User notification preferences table.
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

		// Activity favorites tracking table.
		$favorites_table = $wpdb->prefix . 'bp_activity_favorites';

		$sql_favorites = "CREATE TABLE $favorites_table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			activity_id bigint(20) NOT NULL,
			user_id bigint(20) NOT NULL,
			favorited_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY activity_id (activity_id),
			KEY user_id (user_id),
			UNIQUE KEY activity_user (activity_id, user_id)
		) $charset_collate;";

		dbDelta( $sql_favorites );

		do_action( 'bpfn_create_tables', $wpdb, $charset_collate );
	}

	/**
	 * Get a module instance.
	 *
	 * @param string $module_name The module name.
	 * @return object|null The module instance or null.
	 */
	public function get_module( $module_name ) {
		return isset( $this->modules[ $module_name ] ) ? $this->modules[ $module_name ] : null;
	}

	/**
	 * Register a new module.
	 *
	 * @param string $module_name     The module name.
	 * @param object $module_instance The module instance.
	 */
	public function register_module( $module_name, $module_instance ) {
		$this->modules[ $module_name ] = $module_instance;
	}
}

// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed -- Legacy architecture.

/**
 * Get plugin instance.
 *
 * @return BP_Favorite_Notification The plugin instance.
 */
function bpfn() {
	return BP_Favorite_Notification::get_instance();
}

// Initialize plugin.
bpfn();

// phpcs:enable Universal.Files.SeparateFunctionsFromOO.Mixed