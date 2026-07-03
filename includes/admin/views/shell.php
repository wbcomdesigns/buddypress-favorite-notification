<?php
/**
 * Admin page shell: page header, sidebar nav, body slot.
 *
 * Receives from BPFN_Admin::render_page():
 *
 * @var array  $bpfn_tabs           Tab registry keyed by slug.
 * @var string $active              Active tab slug.
 * @var string $page_url            Base URL (admin.php?page=bpfn-dashboard).
 * @var array  $settings            bpfn_options option.
 * @var string $view                View slug (e.g. 'overview', 'settings-general').
 * @var string $view_path           Absolute path to the partial.
 * @var bool   $in_settings_group   True when the active tab is the Settings API tab.
 * @var string $settings_form_group register_setting() group to pass to settings_fields().
 *
 * @package BuddyPress_Favorite_Notification
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

$version = defined( 'BPFN_VERSION' ) ? BPFN_VERSION : '';
?>
<div class="wrap bpfn-admin">

	<header class="bpfn-page-header">
		<div class="bpfn-page-header__title">
			<span class="dashicons dashicons-heart" aria-hidden="true"></span>
			<div>
				<h1><?php esc_html_e( 'BuddyPress Favorite Notification', 'buddypress-favorite-notification' ); ?></h1>
				<p class="bpfn-page-header__subtitle"><?php esc_html_e( 'Notify members when their activity is favorited, with email, real-time, and a "X and N others liked this" display.', 'buddypress-favorite-notification' ); ?></p>
			</div>
		</div>
		<div class="bpfn-page-header__actions">
			<?php if ( $version ) : ?>
				<span class="bpfn-version-pill">v<?php echo esc_html( $version ); ?></span>
			<?php endif; ?>
		</div>
	</header>

	<?php
	/*
	 * Without this marker, core's common.js re-parents every .notice to sit
	 * right after the first <h1> it finds, which slots the "Settings saved"
	 * banner between our title and its subtitle instead of below the whole
	 * header. See playbook Part 10.2.
	 */
	?>
	<hr class="wp-header-end">

	<div class="bpfn-settings-layout">

		<aside class="bpfn-settings-sidebar">
			<div class="bpfn-settings-sidebar-brand">
				<span class="bpfn-settings-brand-icon" aria-hidden="true">
					<span class="dashicons dashicons-heart"></span>
				</span>
				<div class="bpfn-settings-brand-text">
					<p class="bpfn-settings-brand-name"><?php esc_html_e( 'Favorite Notifications', 'buddypress-favorite-notification' ); ?></p>
					<p class="bpfn-settings-brand-sub"><?php esc_html_e( 'Plugin', 'buddypress-favorite-notification' ); ?></p>
				</div>
			</div>
			<nav class="bpfn-settings-sidebar-nav" aria-label="<?php esc_attr_e( 'Favorite Notification navigation', 'buddypress-favorite-notification' ); ?>">
				<?php
				$printed_groups = array();
				$group_labels   = array(
					'settings' => esc_html__( 'Configuration', 'buddypress-favorite-notification' ),
				);
				foreach ( $bpfn_tabs as $slug => $bpfn_tab ) {
					$group = isset( $bpfn_tab['group'] ) ? $bpfn_tab['group'] : 'main';
					if ( 'main' !== $group && ! in_array( $group, $printed_groups, true ) ) {
						echo '<div class="bpfn-snav-divider" role="separator"></div>';
						if ( isset( $group_labels[ $group ] ) ) {
							echo '<p class="bpfn-snav-section-label">' . esc_html( $group_labels[ $group ] ) . '</p>';
						}
						$printed_groups[] = $group;
					}
					$classes  = 'bpfn-snav-link';
					$classes .= $active === $slug ? ' bpfn-snav-link--active' : '';
					echo '<a href="' . esc_url( $page_url . '&tab=' . $slug ) . '" class="' . esc_attr( $classes ) . '">';
					echo '<span class="dashicons ' . esc_attr( $bpfn_tab['icon'] ) . '" aria-hidden="true"></span>';
					echo esc_html( $bpfn_tab['label'] );
					echo '</a>';
				}
				?>

				<div class="bpfn-snav-divider" role="separator"></div>
				<p class="bpfn-snav-section-label"><?php esc_html_e( 'Resources', 'buddypress-favorite-notification' ); ?></p>
				<a href="https://docs.wbcomdesigns.com/docs/buddypress-favorite-notification/" class="bpfn-snav-link" target="_blank" rel="noopener noreferrer">
					<span class="dashicons dashicons-book" aria-hidden="true"></span>
					<?php esc_html_e( 'Documentation', 'buddypress-favorite-notification' ); ?>
					<span class="dashicons dashicons-external bpfn-snav-link__ext" aria-hidden="true"></span>
					<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'buddypress-favorite-notification' ); ?></span>
				</a>
			</nav>
		</aside>

		<div class="bpfn-settings-main">
			<?php
			/*
			 * Render settings notices inside the content column so the banner
			 * aligns with the panel chrome (playbook Part 10.2).
			 */
			settings_errors();
			?>
			<?php if ( $in_settings_group ) : ?>
				<form method="post" action="options.php" id="bpfn-settings-form">
					<?php settings_fields( $settings_form_group ); ?>
					<?php
					if ( file_exists( $view_path ) ) {
						include $view_path;
					}
					?>
					<div class="bpfn-save-bar">
						<?php submit_button( __( 'Save Settings', 'buddypress-favorite-notification' ), 'primary bpfn-btn bpfn-btn-primary', 'submit', false ); ?>
					</div>
				</form>
			<?php else : ?>
				<?php
				if ( file_exists( $view_path ) ) {
					include $view_path;
				}
				?>
			<?php endif; ?>
		</div>

	</div>
</div>
