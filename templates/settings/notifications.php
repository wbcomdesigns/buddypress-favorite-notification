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
?>

<form method="post" action="" class="bpfn-settings-form">

	<div class="bpfn-settings-intro">
		<p><?php esc_html_e( 'Choose how you want to receive favorite notifications:', 'buddypress-favorite-notification' ); ?></p>
	</div>

	<table class="bpfn-notification-settings">
		<thead>
			<tr>
				<th class="icon"></th>
				<th class="title"><?php esc_html_e( 'Notification Type', 'buddypress-favorite-notification' ); ?></th>
				<th class="channel"><?php esc_html_e( 'Web', 'buddypress-favorite-notification' ); ?></th>
				<th class="channel"><?php esc_html_e( 'Email', 'buddypress-favorite-notification' ); ?></th>
				<th class="channel"><?php esc_html_e( 'Real-time', 'buddypress-favorite-notification' ); ?></th>
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
					<td class="channel email">
						<label class="bpfn-toggle">
							<input type="checkbox"
									name="bpfn[<?php echo esc_attr( $notif_type ); ?>][email]"
									value="1"
									<?php checked( $settings[ $notif_type ]['email_enabled'] ?? 1, 1 ); ?> />
							<span class="bpfn-toggle-slider"></span>
							<span class="bp-screen-reader-text">
								<?php
								/* translators: %s: Notification type label. */
								printf( esc_html__( 'Enable email notifications for %s', 'buddypress-favorite-notification' ), esc_html( $config['label'] ) );
								?>
							</span>
						</label>
					</td>
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
