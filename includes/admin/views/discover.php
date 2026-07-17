<?php
/**
 * Discover tab: ecosystem cross-promotion (read-only display view).
 *
 * Rendered inside shell.php. Pure presentation — product cards with
 * outbound links to other free Wbcom Designs tools. No forms, no settings,
 * no options, no AJAX.
 *
 * @package BuddyPress_Favorite_Notification
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

$bpfn_ecosystem_img = BPFN_ASSETS_URL . 'images/ecosystem/';

/*
 * Each product: brand mark (shipped in assets/images/ecosystem/), name, a
 * short admin-specific blurb (worded differently from readme.txt), and the
 * wbcomdesigns.com download URL.
 */
$bpfn_ecosystem = array(
	array(
		'name' => __( 'BuddyNext', 'buddypress-favorite-notification' ),
		'logo' => 'buddynext.svg',
		'desc' => __( 'A full WordPress social network with feeds, profiles, groups, and private messaging.', 'buddypress-favorite-notification' ),
		'url'  => 'https://wbcomdesigns.com/downloads/buddynext/',
	),
	array(
		'name' => __( 'Jetonomy', 'buddypress-favorite-notification' ),
		'logo' => 'jetonomy.svg',
		'desc' => __( 'Self-moderating forums and Q&A that stay fast into six-figure topic counts.', 'buddypress-favorite-notification' ),
		'url'  => 'https://wbcomdesigns.com/downloads/jetonomy/',
	),
	array(
		'name' => __( 'Mediaverse', 'buddypress-favorite-notification' ),
		'logo' => 'mediaverse.svg',
		'desc' => __( 'A photo and video community with AI moderation built in.', 'buddypress-favorite-notification' ),
		'url'  => 'https://wbcomdesigns.com/downloads/mediaverse/',
	),
	array(
		'name' => __( 'Listora', 'buddypress-favorite-notification' ),
		'logo' => 'listora.svg',
		'desc' => __( 'Searchable directories with ten ready-made listing types.', 'buddypress-favorite-notification' ),
		'url'  => 'https://wbcomdesigns.com/downloads/listora/',
	),
	array(
		'name' => __( 'Learnomy', 'buddypress-favorite-notification' ),
		'logo' => 'learnomy.svg',
		'desc' => __( 'A free LMS with courses, quizzes, and certificates.', 'buddypress-favorite-notification' ),
		'url'  => 'https://wbcomdesigns.com/downloads/learnomy/',
	),
);
?>

<div class="bpfn-card">
	<div class="bpfn-card__head">
		<p class="bpfn-card__title"><?php esc_html_e( 'More Free Tools from Wbcom Designs', 'buddypress-favorite-notification' ); ?></p>
		<p class="bpfn-card__desc"><?php esc_html_e( 'Favorite Notification keeps members coming back when their activity gets liked. These free Wbcom Designs plugins give them more worth coming back to, from the network itself to forums, media, directories, and courses.', 'buddypress-favorite-notification' ); ?></p>
	</div>
	<div class="bpfn-card__body">
		<div class="bpfn-discover-grid">
			<?php foreach ( $bpfn_ecosystem as $bpfn_product ) : ?>
				<div class="bpfn-discover-card">
					<span class="bpfn-discover-card__logo" aria-hidden="true">
						<img src="<?php echo esc_url( $bpfn_ecosystem_img . $bpfn_product['logo'] ); ?>" alt="<?php echo esc_attr( $bpfn_product['name'] ); ?>" width="52" height="52" loading="lazy" />
					</span>
					<h3 class="bpfn-discover-card__title"><?php echo esc_html( $bpfn_product['name'] ); ?></h3>
					<p class="bpfn-discover-card__desc"><?php echo esc_html( $bpfn_product['desc'] ); ?></p>
					<a class="bpfn-btn bpfn-btn-secondary bpfn-discover-card__cta" href="<?php echo esc_url( $bpfn_product['url'] ); ?>" target="_blank" rel="noopener">
						<?php esc_html_e( 'Get it free', 'buddypress-favorite-notification' ); ?>
						<span class="dashicons dashicons-external" aria-hidden="true"></span>
						<span class="screen-reader-text">
							<?php
							/* translators: %s: product name. */
							echo esc_html( sprintf( __( '%s (opens in a new tab)', 'buddypress-favorite-notification' ), $bpfn_product['name'] ) );
							?>
						</span>
					</a>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</div>
