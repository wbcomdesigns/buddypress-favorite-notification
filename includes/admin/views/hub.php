<?php
/**
 * WB Plugins hub — landing dashboard at ?page=wbcomplugins.
 *
 * Lists every Wbcom plugin that has registered a submenu under the shared
 * wbcomplugins parent. Legacy wbcom-wrapper plugins register four
 * boilerplate helper pages (Our Plugins, Our Themes, Support, License)
 * under this hub; those are filtered out via wbcom_hub_wrapper_helper_slugs.
 * See references/wbcom-wrapper-migration.md Part 15.
 *
 * @package BuddyPress_Favorite_Notification
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

$submenu_entries = isset( $GLOBALS['submenu']['wbcomplugins'] ) && is_array( $GLOBALS['submenu']['wbcomplugins'] )
	? $GLOBALS['submenu']['wbcomplugins']
	: array();

// Slugs of the legacy wbcom-wrapper boilerplate helper pages — not plugins.
$wrapper_helper_slugs = apply_filters(
	'wbcom_hub_wrapper_helper_slugs',
	array(
		'wbcom-plugins-page',
		'wbcom-themes-page',
		'wbcom-support-page',
		'wbcom-license-page',
	)
);

$bpfn_plugins = array();
foreach ( $submenu_entries as $entry ) {
	$slug = isset( $entry[2] ) ? (string) $entry[2] : '';
	if ( '' === $slug || 'wbcomplugins' === $slug ) {
		continue;
	}
	if ( in_array( $slug, $wrapper_helper_slugs, true ) ) {
		continue;
	}
	$bpfn_plugins[] = array(
		'slug'       => $slug,
		'menu_title' => isset( $entry[0] ) ? wp_strip_all_tags( (string) $entry[0] ) : $slug,
		'page_title' => isset( $entry[3] ) ? wp_strip_all_tags( (string) $entry[3] ) : '',
		'url'        => admin_url( 'admin.php?page=' . rawurlencode( $slug ) ),
	);
}

$plugin_count = count( $bpfn_plugins );
?>
<div class="wrap bpfn-admin">
	<header class="bpfn-page-header">
		<div class="bpfn-page-header__title">
			<span class="dashicons dashicons-lightbulb" aria-hidden="true"></span>
			<div>
				<h1>WB Plugins</h1>
				<p class="bpfn-page-header__subtitle">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: number of active plugins, 2: brand name "Wbcom". */
							_n(
								'%1$d %2$s plugin active on this site.',
								'%1$d %2$s plugins active on this site.',
								$plugin_count,
								'buddypress-favorite-notification'
							),
							$plugin_count,
							'Wbcom'
						)
					);
					?>
				</p>
			</div>
		</div>
	</header>

	<?php if ( 0 === $plugin_count ) : ?>
		<div class="bpfn-empty-state">
			<span class="bpfn-empty-state__icon" aria-hidden="true">
				<span class="dashicons dashicons-lightbulb"></span>
			</span>
			<p class="bpfn-empty-state__title">
			<?php
				/* translators: %s: brand name "Wbcom". */
				echo esc_html( sprintf( __( 'No %s plugins attached to this hub yet', 'buddypress-favorite-notification' ), 'Wbcom' ) );
			?>
				</p>
			<p class="bpfn-empty-state__desc">
				<?php
				/* translators: %s: brand name "Wbcom". */
				echo esc_html( sprintf( __( 'Activate one or more %s plugins and they will appear here automatically.', 'buddypress-favorite-notification' ), 'Wbcom' ) );
				?>
			</p>
		</div>
	<?php else : ?>
		<div class="bpfn-hub-grid">
			<?php foreach ( $bpfn_plugins as $p ) : ?>
				<a href="<?php echo esc_url( $p['url'] ); ?>" class="bpfn-hub-card">
					<span class="bpfn-hub-card__icon" aria-hidden="true">
						<span class="dashicons dashicons-admin-plugins"></span>
					</span>
					<span class="bpfn-hub-card__title"><?php echo esc_html( $p['menu_title'] ); ?></span>
					<?php if ( ! empty( $p['page_title'] ) && $p['page_title'] !== $p['menu_title'] ) : ?>
						<span class="bpfn-hub-card__subtitle"><?php echo esc_html( $p['page_title'] ); ?></span>
					<?php endif; ?>
					<span class="bpfn-hub-card__cta">
						<?php esc_html_e( 'Open settings', 'buddypress-favorite-notification' ); ?>
						<span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
					</span>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<div class="bpfn-card" style="margin-top: 20px;">
		<div class="bpfn-card__head">
			<p class="bpfn-card__title">
			<?php
			/* translators: %s: hub menu name "WB Plugins". */
			echo esc_html( sprintf( __( 'About %s', 'buddypress-favorite-notification' ), 'WB Plugins' ) );
			?>
			</p>
		</div>
		<div class="bpfn-card__body">
			<p style="margin: 0 0 8px;">
				<?php
				/* translators: %s: company name "Wbcom Designs". */
				echo esc_html( sprintf( __( 'This hub is the single entry point for every %s plugin installed on your site. Each plugin lives on its own page under this menu and keeps its own settings and data.', 'buddypress-favorite-notification' ), 'Wbcom Designs' ) );
				?>
			</p>
			<p style="margin: 0;">
				<a href="https://wbcomdesigns.com/" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Visit wbcomdesigns.com for more plugins and themes →', 'buddypress-favorite-notification' ); ?>
				</a>
			</p>
		</div>
	</div>
</div>
