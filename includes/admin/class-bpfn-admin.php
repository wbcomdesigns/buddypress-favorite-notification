<?php
/**
 * BPFN Admin: menu, enqueue, settings registration, page rendering.
 *
 * Replaces the legacy standalone top-level `bpfn-dashboard` page with a
 * card-panel admin attached to the shared `wbcomplugins` hub. Follows the
 * wp-plugin-development skill Part 6 card-panel pattern and the
 * references/wbcom-wrapper-migration.md playbook (Parts 5, 6, 15).
 *
 * As of 2.0.0 the plugin has no Settings API options page: the former
 * "Enhanced Notifications" toggle (option `bpfn_options`) was removed because
 * its enhanced template could never render — BuddyPress escapes notification
 * descriptions through wp_kses() allowing only `<a href class>`, so the rich
 * markup was stripped on every surface (member screen, toolbar).
 *
 * @package BuddyPress_Favorite_Notification
 * @since 2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BPFN_Admin
 *
 * @since 2.0.0
 */
class BPFN_Admin {

	/**
	 * Menu slug for the plugin's single submenu page.
	 *
	 * @var string
	 */
	const MENU_SLUG = 'bpfn-dashboard';

	/**
	 * All sidebar tabs rendered inside the one admin page.
	 *
	 * @return array<string, array{label:string, icon:string, group:string}>
	 */
	public static function get_tabs() {
		$tabs = array(
			'overview' => array(
				'label' => __( 'Overview', 'buddypress-favorite-notification' ),
				'icon'  => 'dashicons-chart-bar',
				'group' => 'main',
			),
			'tools'    => array(
				'label' => __( 'Tools', 'buddypress-favorite-notification' ),
				'icon'  => 'dashicons-admin-tools',
				'group' => 'settings',
			),
		);
		return apply_filters( 'bpfn_admin_tabs', $tabs );
	}

	/**
	 * Bootstrap hooks.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		// Priority 999 runs after every other plugin has registered. Used to
		// reclaim the hub landing render when a legacy wbcom-wrapper plugin
		// registered the wbcomplugins top-level first. See playbook Part 15.
		add_action( 'admin_menu', array( $this, 'takeover_hub_landing' ), 999 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'in_admin_header', array( $this, 'suppress_foreign_notices' ), 1 );

		// Persist migration-notice dismissal (was a dead AJAX listener pre-2.0.0).
		add_action( 'wp_ajax_bpfn_dismiss_migration_notice', array( $this, 'ajax_dismiss_migration_notice' ) );

		// Plugin action links.
		add_filter( 'plugin_action_links_' . BPFN_PLUGIN_BASENAME, array( $this, 'add_action_links' ) );

		// Migration admin nag.
		add_action( 'admin_notices', array( $this, 'migration_notice' ) );
	}

	/**
	 * Attach as a single submenu under the shared WB Plugins hub.
	 */
	public function add_menu() {
		$cap = 'manage_options';

		// First Wbcom plugin to load creates the shared WB Plugins hub.
		if ( empty( $GLOBALS['admin_page_hooks']['wbcomplugins'] ) ) {
			add_menu_page(
				esc_html__( 'WB Plugins', 'buddypress-favorite-notification' ),
				esc_html__( 'WB Plugins', 'buddypress-favorite-notification' ),
				$cap,
				'wbcomplugins',
				array( $this, 'render_hub' ),
				'dashicons-lightbulb',
				59
			);
		}

		add_submenu_page(
			'wbcomplugins',
			esc_html__( 'BuddyPress Favorite Notification', 'buddypress-favorite-notification' ),
			esc_html__( 'Favorite Notifications', 'buddypress-favorite-notification' ),
			$cap,
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Force the shared hub's landing page to render our clean card-panel
	 * dashboard, overriding whatever a legacy wbcom-wrapper plugin may have
	 * registered. See references/wbcom-wrapper-migration.md Part 15.
	 */
	public function takeover_hub_landing() {
		global $admin_page_hooks;
		if ( empty( $admin_page_hooks['wbcomplugins'] ) ) {
			return;
		}
		remove_all_actions( 'toplevel_page_wbcomplugins' );
		add_action( 'toplevel_page_wbcomplugins', array( $this, 'render_hub' ) );
	}

	/**
	 * Enqueue admin assets only on our screen + the shared hub landing.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_assets( $hook_suffix ) {
		unset( $hook_suffix );
		$screen = get_current_screen();
		if ( ! $screen || ! $this->is_our_screen( $screen ) ) {
			return;
		}

		$version = defined( 'BPFN_VERSION' ) ? BPFN_VERSION : '1.0.0';

		wp_enqueue_style(
			'bpfn-admin',
			BPFN_ASSETS_URL . 'css/admin.css',
			array(),
			$version
		);

		wp_enqueue_script(
			'bpfn-admin',
			BPFN_ASSETS_URL . 'js/admin.js',
			array( 'jquery' ),
			$version,
			true
		);

		wp_localize_script(
			'bpfn-admin',
			'bpfnAdmin',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'bpfn-admin-nonce' ),
				'strings'  => array(
					'clearing'              => __( 'Clearing...', 'buddypress-favorite-notification' ),
					/* translators: %s: Number of notifications cleared. */
					'cleared'               => __( 'Successfully cleared %s old notifications.', 'buddypress-favorite-notification' ),
					/* translators: %s: Number of notifications remaining. */
					'remaining'             => __( '%s notifications remaining', 'buddypress-favorite-notification' ),
					'clear_failed'          => __( 'Failed to clear notifications.', 'buddypress-favorite-notification' ),
					'migrating'             => __( 'Starting migration...', 'buddypress-favorite-notification' ),
					'migrate_failed'        => __( 'Migration failed.', 'buddypress-favorite-notification' ),
					/* translators: 1: Number of users processed, 2: Number of favorites added. */
					'migration_complete'    => __( 'Migration completed! Processed %1$s users and added %2$s favorites.', 'buddypress-favorite-notification' ),
					/* translators: %s: Migration status keyword (e.g. failed, cancelled). */
					'migration_status'      => __( 'Migration %s', 'buddypress-favorite-notification' ),
					'users'                 => __( 'users', 'buddypress-favorite-notification' ),
					'error_generic'         => __( 'An error occurred:', 'buddypress-favorite-notification' ),
					'confirm_continue'      => __( 'Continue', 'buddypress-favorite-notification' ),
					'confirm_cancel'        => __( 'Cancel', 'buddypress-favorite-notification' ),
					'confirm_danger'        => __( 'Are you sure? This cannot be undone.', 'buddypress-favorite-notification' ),
					'confirm_migrate_title' => __( 'Run migration?', 'buddypress-favorite-notification' ),
					'confirm_migrate'       => __( 'This will migrate all existing favorites to the new optimized table. Continue?', 'buddypress-favorite-notification' ),
					'confirm_clear_title'   => __( 'Clear old notifications?', 'buddypress-favorite-notification' ),
					'confirm_clear_old'     => __( 'Are you sure you want to clear all read notifications older than the retention period? This cannot be undone.', 'buddypress-favorite-notification' ),
				),
			)
		);
	}

	/**
	 * Suppress 3rd-party admin notices on our screen.
	 */
	public function suppress_foreign_notices() {
		$screen = get_current_screen();
		if ( ! $screen || ! $this->is_our_screen( $screen ) ) {
			return;
		}
		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );
	}

	/**
	 * True if the current screen is the plugin's admin page OR the shared
	 * hub landing (our callback owns the hub render too, so our CSS/JS
	 * must load there).
	 *
	 * @param WP_Screen $screen Current screen.
	 * @return bool
	 */
	private function is_our_screen( $screen ) {
		if ( empty( $screen->id ) ) {
			return false;
		}
		return (bool) preg_match( '/_page_' . preg_quote( self::MENU_SLUG, '/' ) . '$/', $screen->id )
			|| 'toplevel_page_wbcomplugins' === $screen->id;
	}

	/**
	 * Render the shared WB Plugins hub landing page.
	 */
	public function render_hub() {
		$view = BPFN_INCLUDES_PATH . 'admin/views/hub.php';
		if ( file_exists( $view ) ) {
			include $view;
		}
	}

	/**
	 * Render the single admin page. Routes to the active tab.
	 */
	public function render_page() {
		$bpfn_tabs = self::get_tabs();
		$tab_slugs = array_keys( $bpfn_tabs );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab routing.
		$active = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : $tab_slugs[0];
		if ( ! isset( $bpfn_tabs[ $active ] ) ) {
			$active = $tab_slugs[0];
		}

		$page_url = admin_url( 'admin.php?page=' . self::MENU_SLUG );

		$view_map = array(
			'overview' => 'overview',
			'tools'    => 'tools',
		);

		$view = isset( $view_map[ $active ] ) ? $view_map[ $active ] : 'overview';

		$view_path = BPFN_INCLUDES_PATH . 'admin/views/' . $view . '.php';
		$shell     = BPFN_INCLUDES_PATH . 'admin/views/shell.php';

		include $shell;
	}

	/**
	 * Add plugin action links pointing at the new card-panel page.
	 *
	 * @param array $links Plugin action links.
	 * @return array
	 */
	public function add_action_links( $links ) {
		$action_links = array(
			'settings' => '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG ) ) . '">' . esc_html__( 'Settings', 'buddypress-favorite-notification' ) . '</a>',
		);
		return array_merge( $action_links, $links );
	}

	/**
	 * Show the favorites-migration nag, scoped to admins.
	 */
	public function migration_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! get_option( 'bpfn_show_migration_notice', false ) ) {
			return;
		}

		require_once BPFN_INCLUDES_PATH . 'migrations/class-favorites-migration.php';
		$migration = new BPFN_Favorites_Migration();
		$stats     = $migration->get_migration_stats();

		// Don't show if migration is complete.
		if ( $stats['migrated'] || ! $stats['migration_pending'] ) {
			delete_option( 'bpfn_show_migration_notice' );
			return;
		}

		$tools_url = admin_url( 'admin.php?page=' . self::MENU_SLUG . '&tab=tools' );
		?>
		<div class="notice notice-info is-dismissible" data-dismissible="bpfn-migration-notice">
			<p>
				<strong><?php esc_html_e( 'BuddyPress Favorite Notification:', 'buddypress-favorite-notification' ); ?></strong>
				<?php
				printf(
					wp_kses(
						/* translators: 1: Number of users, 2: Number of favorites, 3: Tools page link. */
						__( 'Found %1$d users with %2$d favorites that need to be migrated to the new optimized system. <a href="%3$s">Run migration now</a>', 'buddypress-favorite-notification' ),
						array( 'a' => array( 'href' => array() ) )
					),
					(int) $stats['users_with_favorites'],
					(int) $stats['meta_favorites_count'],
					esc_url( $tools_url )
				);
				?>
			</p>
			<script>
			jQuery( document ).on( 'click', '[data-dismissible="bpfn-migration-notice"] .notice-dismiss', function () {
				jQuery.post( ajaxurl, {
					action: 'bpfn_dismiss_migration_notice',
					nonce: '<?php echo esc_js( wp_create_nonce( 'bpfn-dismiss-notice' ) ); ?>'
				} );
			} );
			</script>
		</div>
		<?php
	}

	/**
	 * AJAX: persist migration-notice dismissal.
	 *
	 * Pre-2.0.0 the inline dismiss script POSTed this action but no handler
	 * existed, so the notice reappeared on every reload. This handler deletes
	 * the `bpfn_show_migration_notice` option so the nag stays dismissed.
	 */
	public function ajax_dismiss_migration_notice() {
		// Capability check first (defense in depth), then nonce.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions', 'buddypress-favorite-notification' ) ), 403 );
		}

		check_ajax_referer( 'bpfn-dismiss-notice', 'nonce' );

		delete_option( 'bpfn_show_migration_notice' );

		wp_send_json_success();
	}
}
