<?php
/**
 * User notification settings template.
 *
 * @package BuddyPress_Favorite_Notification
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Provided by BPFN_Module_Settings::notification_settings() before this template
// is included. Declared here so a missing value (and static analysis) fall back
// to an empty list instead of a foreach over an undefined variable.
$notification_types = isset( $notification_types ) ? $notification_types : array();
$show_realtime      = ! empty( $show_realtime );
?>

<form method="post" action="" class="bpfn-settings-form">

	<div class="bpfn-settings-intro">
		<p>
			<?php
			esc_html_e( 'Choose which favorite notifications you get on the site.', 'buddypress-favorite-notification' );
			if ( bp_is_active( 'settings' ) ) {
				echo ' ';
				printf(
					/* translators: %s: link to the member's Email settings tab. */
					esc_html__( 'Email alerts are set in your %s.', 'buddypress-favorite-notification' ),
					'<a href="' . esc_url( bp_members_get_user_url( bp_displayed_user_id(), bp_members_get_path_chunks( array( bp_get_settings_slug(), 'notifications' ) ) ) ) . '">' . esc_html__( 'Email settings', 'buddypress-favorite-notification' ) . '</a>'
				);
			}
			?>
		</p>
	</div>

	<table class="bpfn-notification-settings">
		<thead>
			<tr>
				<th class="icon"></th>
				<th class="title"><?php esc_html_e( 'Notification Type', 'buddypress-favorite-notification' ); ?></th>
				<th class="channel"><?php esc_html_e( 'Web', 'buddypress-favorite-notification' ); ?></th>
				<?php if ( $show_realtime ) : ?>
					<th class="channel"><?php esc_html_e( 'Real-time', 'buddypress-favorite-notification' ); ?></th>
				<?php endif; ?>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $notification_types as $notif_type => $config ) : ?>
				<tr class="notification-type-<?php echo esc_attr( $notif_type ); ?>">
					<td class="icon">
						<?php bpfn_notification_icon( $notif_type ); ?>
					</td>
					<td class="title">
						<strong><?php echo esc_html( $config['label'] ); ?></strong>
						<p class="description"><?php echo esc_html( $config['description'] ); ?></p>
					</td>
					<td class="channel web">
						<label class="bpfn-toggle">
							<input type="checkbox"
									name="bpfn[<?php echo esc_attr( $notif_type ); ?>][web]"
									value="1"
									<?php checked( $settings[ $notif_type ]['is_enabled'] ?? 1, 1 ); ?> />
							<span class="bpfn-toggle-slider"></span>
							<span class="bp-screen-reader-text">
								<?php
								/* translators: %s: Notification type label. */
								printf( esc_html__( 'Enable web notifications for %s', 'buddypress-favorite-notification' ), esc_html( $config['label'] ) );
								?>
							</span>
						</label>
					</td>
					<?php if ( $show_realtime ) : ?>
					<td class="channel realtime">
						<label class="bpfn-toggle">
							<input type="checkbox"
									name="bpfn[<?php echo esc_attr( $notif_type ); ?>][realtime]"
									value="1"
									<?php checked( $settings[ $notif_type ]['realtime_enabled'] ?? 1, 1 ); ?> />
							<span class="bpfn-toggle-slider"></span>
							<span class="bp-screen-reader-text">
								<?php
								/* translators: %s: Notification type label. */
								printf( esc_html__( 'Enable real-time notifications for %s', 'buddypress-favorite-notification' ), esc_html( $config['label'] ) );
								?>
							</span>
						</label>
					</td>
					<?php endif; ?>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<div class="bpfn-settings-actions">
		<?php wp_nonce_field( 'bpfn_settings_nonce' ); ?>
		<input type="hidden" name="bpfn_save_settings" value="1" />
		<button type="submit" class="button button-primary">
			<?php esc_html_e( 'Save Settings', 'buddypress-favorite-notification' ); ?>
		</button>
	</div>

</form>
