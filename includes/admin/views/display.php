<?php
/**
 * Display tab: how the "who favorited this" line renders in the activity stream.
 *
 * Persists two standalone options via the hand-rolled POST handler in
 * BPFN_Module_Admin::handle_display_settings_save() (nonce
 * `bpfn_display_settings`, cap manage_options) — matching how the Tools tab
 * saves. This plugin registers no Settings API groups.
 *
 * @package BuddyPress_Favorite_Notification
 * @since   2.1.0
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flash check.
$bpfn_saved = isset( $_GET['settings_updated'] ) && 'true' === sanitize_text_field( wp_unslash( $_GET['settings_updated'] ) );

$bpfn_modes = BPFN_Module_Favorite_Display::get_display_modes();
$bpfn_icons = BPFN_Module_Favorite_Display::get_icon_choices();

$bpfn_mode          = BPFN_Module_Favorite_Display::get_saved_mode();
$bpfn_icon          = BPFN_Module_Favorite_Display::get_saved_icon();
$bpfn_realtime_on   = BPFN_Module_Realtime::is_enabled();
$bpfn_realtime_secs = BPFN_Module_Realtime::get_interval();

$bpfn_mode_help = array(
	'inline'  => __( 'Shows the first names inline, for example "John, Mary and 2 others". Familiar, but the line grows with long usernames and takes more width on mobile.', 'buddypress-favorite-notification' ),
	'counter' => __( 'Shows only the icon and the total. Lightest option, and nothing is clickable.', 'buddypress-favorite-notification' ),
	'modal'   => __( 'Shows the icon and the total as a button. Members click it to open the full list, which loads a page at a time.', 'buddypress-favorite-notification' ),
);
?>

<?php if ( $bpfn_saved ) : ?>
	<?php // .notice.is-dismissible gets core's close button; .inline stops core moving it above the shell. ?>
	<div class="bpfn-notice bpfn-notice--success notice is-dismissible inline">
		<p><?php esc_html_e( 'Settings saved successfully!', 'buddypress-favorite-notification' ); ?></p>
	</div>
<?php endif; ?>

<form method="post" action="">
<?php wp_nonce_field( 'bpfn_display_settings', 'bpfn_display_nonce' ); ?>
<div class="bpfn-card">
	<div class="bpfn-card__head">
		<p class="bpfn-card__title"><?php esc_html_e( 'Activity Stream Display', 'buddypress-favorite-notification' ); ?></p>
		<p class="bpfn-card__desc"><?php esc_html_e( 'Choose how the list of members who favorited an activity appears under each post.', 'buddypress-favorite-notification' ); ?></p>
	</div>
	<div class="bpfn-card__body">
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="bpfn_display_mode"><?php esc_html_e( 'Display Mode', 'buddypress-favorite-notification' ); ?></label>
					</th>
					<td>
						<select name="bpfn_display_mode" id="bpfn_display_mode">
							<?php foreach ( $bpfn_modes as $bpfn_mode_key => $bpfn_mode_label ) : ?>
								<option value="<?php echo esc_attr( $bpfn_mode_key ); ?>" <?php selected( $bpfn_mode, $bpfn_mode_key ); ?>>
									<?php echo esc_html( $bpfn_mode_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php
							foreach ( $bpfn_mode_help as $bpfn_help_key => $bpfn_help_text ) {
								if ( $bpfn_help_key === $bpfn_mode ) {
									echo esc_html( $bpfn_help_text );
								}
							}
							?>
						</p>
						<p class="description">
							<?php esc_html_e( 'Existing sites keep inline usernames until this is changed.', 'buddypress-favorite-notification' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="bpfn_favorite_icon"><?php esc_html_e( 'Favorite Icon', 'buddypress-favorite-notification' ); ?></label>
					</th>
					<td>
						<select name="bpfn_favorite_icon" id="bpfn_favorite_icon">
							<?php
							foreach ( $bpfn_icons as $bpfn_icon_key => $bpfn_icon_data ) :
								$bpfn_icon_label = isset( $bpfn_icon_data['label'] ) ? $bpfn_icon_data['label'] : $bpfn_icon_key;
								?>
								<option value="<?php echo esc_attr( $bpfn_icon_key ); ?>" <?php selected( $bpfn_icon, $bpfn_icon_key ); ?>>
									<?php echo esc_html( $bpfn_icon_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'The icon shown in the "favorited by" line under each activity. A heart can read as the "Like" reaction, so a star or bookmark keeps favorites distinct. The Favorite button itself is drawn by BuddyPress and your theme.', 'buddypress-favorite-notification' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<div class="bpfn-card__preview">
				<p class="bpfn-card__preview-label"><?php esc_html_e( 'Current appearance', 'buddypress-favorite-notification' ); ?></p>
				<div class="bpfn-preview-swatch">
					<?php
					$bpfn_icon_entity = isset( $bpfn_icons[ $bpfn_icon ]['entity'] ) ? $bpfn_icons[ $bpfn_icon ]['entity'] : '';
					if ( '' !== $bpfn_icon_entity ) {
						echo '<span class="bpfn-preview-icon">' . esc_html( html_entity_decode( $bpfn_icon_entity, ENT_QUOTES, 'UTF-8' ) ) . '</span>';
					}
					if ( 'inline' === $bpfn_mode ) {
						echo '<span class="bpfn-preview-text">' . esc_html__( 'John, Mary and 2 others', 'buddypress-favorite-notification' ) . '</span>';
					} else {
						echo '<span class="bpfn-preview-text">12</span>';
					}
					?>
				</div>
				<p class="description">
					<?php esc_html_e( 'Save to apply, then reload an activity stream to see the change.', 'buddypress-favorite-notification' ); ?>
				</p>
			</div>

	</div>
</div>

<div class="bpfn-card">
	<div class="bpfn-card__head">
		<p class="bpfn-card__title"><?php esc_html_e( 'Real-time Popups', 'buddypress-favorite-notification' ); ?></p>
		<p class="bpfn-card__desc"><?php esc_html_e( 'Tell members the moment their activity is favorited, without a page refresh.', 'buddypress-favorite-notification' ); ?></p>
	</div>
	<div class="bpfn-card__body">
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="bpfn_realtime_enabled"><?php esc_html_e( 'Real-time Popups', 'buddypress-favorite-notification' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="bpfn_realtime_enabled" id="bpfn_realtime_enabled" value="yes" <?php checked( $bpfn_realtime_on ); ?> />
							<?php esc_html_e( 'Show members a popup when their activity is favorited, without a page refresh', 'buddypress-favorite-notification' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'While a page is open, each logged-in member\'s browser checks the server at the interval below. Members can still turn popups off in their own settings.', 'buddypress-favorite-notification' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="bpfn_realtime_interval"><?php esc_html_e( 'Check Every', 'buddypress-favorite-notification' ); ?></label>
					</th>
					<td>
						<select name="bpfn_realtime_interval" id="bpfn_realtime_interval">
							<?php foreach ( BPFN_Module_Realtime::INTERVALS as $bpfn_secs ) : ?>
								<option value="<?php echo esc_attr( (string) $bpfn_secs ); ?>" <?php selected( $bpfn_realtime_secs, $bpfn_secs ); ?>>
									<?php
									/* translators: %d: number of seconds. */
									printf( esc_html__( '%d seconds', 'buddypress-favorite-notification' ), (int) $bpfn_secs );
									?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'A longer interval puts less load on a busy site.', 'buddypress-favorite-notification' ); ?></p>
					</td>
				</tr>
			</table>

			<div class="bpfn-save-bar">
				<button type="submit" name="bpfn_save_display_settings" class="bpfn-btn bpfn-btn-primary">
					<?php esc_html_e( 'Save Settings', 'buddypress-favorite-notification' ); ?>
				</button>
			</div>
	</div>
</div>
</form>
