<?php
/**
 * Tools tab: migration + database maintenance (cleanup).
 *
 * Reskins the legacy "Tools & Maintenance" section into the card-panel
 * layout. The cleanup form preserves the exact field names + nonce so the
 * legacy save handler (BPFN_Module_Admin::handle_cleanup_settings_save,
 * nonce `bpfn_cleanup_settings`, cap manage_options) keeps working.
 *
 * @package BuddyPress_Favorite_Notification
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

require_once BPFN_INCLUDES_PATH . 'migrations/class-favorites-migration.php';
$bpfn_migration = new BPFN_Favorites_Migration();
$bpfn_mstats    = $bpfn_migration->get_migration_stats();

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flash check.
$bpfn_saved = isset( $_GET['settings_updated'] ) && 'true' === sanitize_text_field( wp_unslash( $_GET['settings_updated'] ) );

$bpfn_auto_enabled = get_option( 'bpfn_auto_cleanup_enabled', 'yes' );
$bpfn_cleanup_days = (int) get_option( 'bpfn_auto_cleanup_days', 30 );
$bpfn_last_cleanup = get_option( 'bpfn_last_auto_cleanup', array() );
$bpfn_next_cleanup = wp_next_scheduled( 'bpfn_auto_cleanup_notifications' );
?>

<?php if ( $bpfn_saved ) : ?>
	<div class="bpfn-notice bpfn-notice--success">
		<p><?php esc_html_e( 'Settings saved successfully!', 'buddypress-favorite-notification' ); ?></p>
	</div>
<?php endif; ?>

<div class="bpfn-card">
	<div class="bpfn-card__head">
		<p class="bpfn-card__title"><?php esc_html_e( 'Migrate Favorites', 'buddypress-favorite-notification' ); ?></p>
		<p class="bpfn-card__desc"><?php esc_html_e( 'Move legacy usermeta favorites into the optimized custom table.', 'buddypress-favorite-notification' ); ?></p>
	</div>
	<div class="bpfn-card__body">
		<?php if ( $bpfn_mstats['migrated'] ) : ?>
			<div class="bpfn-notice bpfn-notice--success">
				<p><strong><?php esc_html_e( 'Migration completed.', 'buddypress-favorite-notification' ); ?></strong></p>
			</div>
			<?php
			$bpfn_log = $bpfn_migration->get_migration_log();
			if ( ! empty( $bpfn_log ) ) :
				?>
				<p>
					<?php
					printf(
						/* translators: 1: Number of users, 2: Number of favorites. */
						esc_html__( 'Processed %1$d users and migrated %2$d favorites.', 'buddypress-favorite-notification' ),
						isset( $bpfn_log['users_processed'] ) ? (int) $bpfn_log['users_processed'] : 0,
						isset( $bpfn_log['favorites_added'] ) ? (int) $bpfn_log['favorites_added'] : 0
					);
					?>
				</p>
			<?php endif; ?>
		<?php elseif ( $bpfn_mstats['migration_pending'] ) : ?>
			<p>
				<?php
				printf(
					/* translators: 1: Number of users, 2: Number of favorites. */
					esc_html__( 'Found %1$d users with %2$d favorites to migrate.', 'buddypress-favorite-notification' ),
					(int) $bpfn_mstats['users_with_favorites'],
					(int) $bpfn_mstats['meta_favorites_count']
				);
				?>
			</p>
			<div class="bpfn-save-bar">
				<button type="button" class="bpfn-btn bpfn-btn-primary" id="bpfn-migrate-favorites">
					<?php esc_html_e( 'Run Migration', 'buddypress-favorite-notification' ); ?>
				</button>
			</div>
			<div id="bpfn-migrate-result" style="margin-top: 12px;"></div>
		<?php else : ?>
			<p><?php esc_html_e( 'No favorites found to migrate.', 'buddypress-favorite-notification' ); ?></p>
		<?php endif; ?>
	</div>
</div>

<div class="bpfn-card">
	<div class="bpfn-card__head">
		<p class="bpfn-card__title"><?php esc_html_e( 'Database Maintenance', 'buddypress-favorite-notification' ); ?></p>
		<p class="bpfn-card__desc"><?php esc_html_e( 'Remove read notifications older than the retention period to keep the database lean.', 'buddypress-favorite-notification' ); ?></p>
	</div>
	<div class="bpfn-card__body">
		<form method="post" action="">
			<?php wp_nonce_field( 'bpfn_cleanup_settings', 'bpfn_cleanup_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="bpfn_auto_cleanup_enabled"><?php esc_html_e( 'Automatic Cleanup', 'buddypress-favorite-notification' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox"
								name="bpfn_auto_cleanup_enabled"
								id="bpfn_auto_cleanup_enabled"
								value="yes"
								<?php checked( $bpfn_auto_enabled, 'yes' ); ?> />
							<?php esc_html_e( 'Enable automatic monthly cleanup', 'buddypress-favorite-notification' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Automatically remove old read notifications once per month via WP Cron.', 'buddypress-favorite-notification' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="bpfn_auto_cleanup_days"><?php esc_html_e( 'Retention Period', 'buddypress-favorite-notification' ); ?></label>
					</th>
					<td>
						<select name="bpfn_auto_cleanup_days" id="bpfn_auto_cleanup_days">
							<?php foreach ( array( 7, 15, 30, 60, 90 ) as $bpfn_days_opt ) : ?>
								<option value="<?php echo esc_attr( (string) $bpfn_days_opt ); ?>" <?php selected( $bpfn_cleanup_days, $bpfn_days_opt ); ?>>
									<?php
									printf(
										/* translators: %d: number of days. */
										esc_html__( '%d days', 'buddypress-favorite-notification' ),
										(int) $bpfn_days_opt
									);
									?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Only read/dismissed notifications older than this will be deleted.', 'buddypress-favorite-notification' ); ?></p>
					</td>
				</tr>
			</table>
			<div class="bpfn-save-bar">
				<button type="submit" name="bpfn_save_cleanup_settings" class="bpfn-btn bpfn-btn-primary">
					<?php esc_html_e( 'Save Settings', 'buddypress-favorite-notification' ); ?>
				</button>
			</div>
		</form>

		<?php if ( ! empty( $bpfn_last_cleanup ) ) : ?>
			<div class="bpfn-notice bpfn-notice--info" style="margin-top: 16px;">
				<p>
					<strong><?php esc_html_e( 'Last automatic cleanup:', 'buddypress-favorite-notification' ); ?></strong><br>
					<?php
					printf(
						/* translators: 1: Date, 2: Number deleted, 3: Number remaining. */
						esc_html__( '%1$s — deleted %2$d notifications, %3$d remaining.', 'buddypress-favorite-notification' ),
						isset( $bpfn_last_cleanup['date'] ) ? esc_html( $bpfn_last_cleanup['date'] ) : esc_html__( 'N/A', 'buddypress-favorite-notification' ),
						isset( $bpfn_last_cleanup['deleted'] ) ? (int) $bpfn_last_cleanup['deleted'] : 0,
						isset( $bpfn_last_cleanup['remaining'] ) ? (int) $bpfn_last_cleanup['remaining'] : 0
					);
					?>
				</p>
			</div>
		<?php endif; ?>

		<?php if ( $bpfn_next_cleanup && 'yes' === $bpfn_auto_enabled ) : ?>
			<p style="margin-top: 8px;">
				<small>
					<?php
					printf(
						/* translators: %s: Next cleanup date/time. */
						esc_html__( 'Next automatic cleanup: %s', 'buddypress-favorite-notification' ),
						esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $bpfn_next_cleanup ) )
					);
					?>
				</small>
			</p>
		<?php endif; ?>
	</div>
</div>

<div class="bpfn-card">
	<div class="bpfn-card__head">
		<p class="bpfn-card__title"><?php esc_html_e( 'Manual Cleanup', 'buddypress-favorite-notification' ); ?></p>
		<p class="bpfn-card__desc"><?php esc_html_e( 'Run cleanup immediately without waiting for the automatic schedule.', 'buddypress-favorite-notification' ); ?></p>
	</div>
	<div class="bpfn-card__body">
		<div class="bpfn-save-bar">
			<button type="button" class="bpfn-btn bpfn-btn-secondary" id="bpfn-clear-old-notifications">
				<?php esc_html_e( 'Clear Old Notifications Now', 'buddypress-favorite-notification' ); ?>
			</button>
		</div>
		<div id="bpfn-clear-result" style="margin-top: 12px;"></div>
	</div>
</div>
