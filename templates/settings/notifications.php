<?php
/**
 * User notification settings template
 *
 * @package BuddyPress_Favorite_Notification
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<form method="post" action="" class="bpfn-settings-form">
	
	<div class="bpfn-settings-intro">
		<p><?php _e( 'Choose how you want to receive favorite notifications:', 'bp-fav-notification' ); ?></p>
	</div>

	<table class="bpfn-notification-settings">
		<thead>
			<tr>
				<th class="icon"></th>
				<th class="title"><?php _e( 'Notification Type', 'bp-fav-notification' ); ?></th>
				<th class="channel"><?php _e( 'Web', 'bp-fav-notification' ); ?></th>
				<th class="channel"><?php _e( 'Email', 'bp-fav-notification' ); ?></th>
				<th class="channel"><?php _e( 'Real-time', 'bp-fav-notification' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $notification_types as $type => $config ) : ?>
				<tr class="notification-type-<?php echo esc_attr( $type ); ?>">
					<td class="icon">
						<?php bpfn_notification_icon( $type ); ?>
					</td>
					<td class="title">
						<strong><?php echo esc_html( $config['label'] ); ?></strong>
						<p class="description"><?php echo esc_html( $config['description'] ); ?></p>
					</td>
					<td class="channel web">
						<label class="bpfn-toggle">
							<input type="checkbox" 
								   name="bpfn[<?php echo esc_attr( $type ); ?>][web]" 
								   value="1" 
								   <?php checked( $settings[ $type ]['is_enabled'] ?? 1, 1 ); ?> />
							<span class="bpfn-toggle-slider"></span>
							<span class="bp-screen-reader-text">
								<?php printf( __( 'Enable web notifications for %s', 'bp-fav-notification' ), $config['label'] ); ?>
							</span>
						</label>
					</td>
					<td class="channel email">
						<label class="bpfn-toggle">
							<input type="checkbox" 
								   name="bpfn[<?php echo esc_attr( $type ); ?>][email]" 
								   value="1" 
								   <?php checked( $settings[ $type ]['email_enabled'] ?? 1, 1 ); ?> />
							<span class="bpfn-toggle-slider"></span>
							<span class="bp-screen-reader-text">
								<?php printf( __( 'Enable email notifications for %s', 'bp-fav-notification' ), $config['label'] ); ?>
							</span>
						</label>
					</td>
					<td class="channel realtime">
						<label class="bpfn-toggle">
							<input type="checkbox" 
								   name="bpfn[<?php echo esc_attr( $type ); ?>][realtime]" 
								   value="1" 
								   <?php checked( $settings[ $type ]['realtime_enabled'] ?? 1, 1 ); ?> />
							<span class="bpfn-toggle-slider"></span>
							<span class="bp-screen-reader-text">
								<?php printf( __( 'Enable real-time notifications for %s', 'bp-fav-notification' ), $config['label'] ); ?>
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
			<?php _e( 'Save Settings', 'bp-fav-notification' ); ?>
		</button>
	</div>

</form>

<style>
/* Toggle switch styles */
.bpfn-toggle {
	position: relative;
	display: inline-block;
	width: 44px;
	height: 24px;
}

.bpfn-toggle input {
	opacity: 0;
	width: 0;
	height: 0;
}

.bpfn-toggle-slider {
	position: absolute;
	cursor: pointer;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	background-color: #ccc;
	transition: .3s;
	border-radius: 24px;
}

.bpfn-toggle-slider:before {
	position: absolute;
	content: "";
	height: 16px;
	width: 16px;
	left: 4px;
	bottom: 4px;
	background-color: white;
	transition: .3s;
	border-radius: 50%;
}

.bpfn-toggle input:checked + .bpfn-toggle-slider {
	background-color: var(--bpfn-primary-color, #ff7b00);
}

.bpfn-toggle input:checked + .bpfn-toggle-slider:before {
	transform: translateX(20px);
}

/* Table styles */
.bpfn-notification-settings {
	width: 100%;
	border-collapse: collapse;
	margin: 20px 0;
}

.bpfn-notification-settings th,
.bpfn-notification-settings td {
	padding: 15px;
	text-align: left;
	border-bottom: 1px solid #eee;
}

.bpfn-notification-settings th {
	background-color: #f5f5f5;
	font-weight: 600;
}

.bpfn-notification-settings .icon {
	width: 40px;
	text-align: center;
}

.bpfn-notification-settings .channel {
	width: 100px;
	text-align: center;
}

.bpfn-notification-settings .description {
	margin: 5px 0 0;
	font-size: 0.9em;
	color: #666;
}

.bpfn-settings-intro {
	margin-bottom: 20px;
}

.bpfn-settings-actions {
	margin-top: 30px;
	padding-top: 20px;
	border-top: 1px solid #eee;
}
</style>