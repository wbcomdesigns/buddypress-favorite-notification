<?php
/**
 * Settings tab: General (bpfn_options Settings API).
 *
 * Rendered INSIDE the <form action="options.php"> in shell.php, which
 * already emits settings_fields( 'bpfn_settings' ) and the Save button.
 * This view only outputs the option fields.
 *
 * @package BuddyPress_Favorite_Notification
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Parent view provides $settings.
 *
 * @var array $settings bpfn_options array.
 */

$bpfn_enhanced_on = ! empty( $settings['enable_enhanced_notifications'] );
?>

<div class="bpfn-card">
	<div class="bpfn-card__head">
		<p class="bpfn-card__title"><?php esc_html_e( 'Notification Display', 'buddypress-favorite-notification' ); ?></p>
		<p class="bpfn-card__desc"><?php esc_html_e( 'Control how favorite notifications appear to your members.', 'buddypress-favorite-notification' ); ?></p>
	</div>
	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'Enhanced Notifications', 'buddypress-favorite-notification' ); ?></th>
			<td>
				<label>
					<input type="checkbox"
						name="bpfn_options[enable_enhanced_notifications]"
						value="1"
						<?php checked( $bpfn_enhanced_on ); ?> />
					<?php esc_html_e( 'Display enhanced notification content with activity excerpts and richer formatting', 'buddypress-favorite-notification' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'When on, favorite notifications use the enhanced template instead of the plain BuddyPress default.', 'buddypress-favorite-notification' ); ?>
				</p>
			</td>
		</tr>
	</table>
</div>
