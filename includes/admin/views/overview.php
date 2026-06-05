<?php
/**
 * Overview dashboard partial: rendered inside shell.php.
 *
 * Reskins the legacy postbox dashboard (stats + recent + trending) into the
 * card-panel layout. Carries over the same stat queries from the old
 * BPFN_Module_Admin::get_dashboard_stats().
 *
 * @package BuddyPress_Favorite_Notification
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Parent view provides $settings.
 *
 * @var array $settings bpfn_options already loaded by render_page().
 */

global $wpdb, $bp;

$bpfn_stats = array(
	'total_favorites'           => 0,
	'favorites_last_7_days'     => 0,
	'total_notifications'       => 0,
	'notifications_last_7_days' => 0,
	'active_users_7_days'       => 0,
	'most_liked_activity'       => 0,
	'most_liked_count'          => 0,
	'recent_activities'         => array(),
	'trending_7_days'           => array(),
	'trending_30_days'          => array(),
);

$favorites_table = $wpdb->prefix . 'bp_activity_favorites';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin stats query.
$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $favorites_table ) );

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Admin stats with custom table.
if ( $table_exists === $favorites_table ) {
	$bpfn_stats['total_favorites'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$favorites_table}" );

	$bpfn_stats['favorites_last_7_days'] = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$favorites_table} WHERE favorited_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
	);

	$bpfn_stats['active_users_7_days'] = (int) $wpdb->get_var(
		"SELECT COUNT(DISTINCT user_id) FROM {$favorites_table} WHERE favorited_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
	);

	$bpfn_most_liked = $wpdb->get_row(
		"SELECT activity_id, COUNT(*) as count FROM {$favorites_table} GROUP BY activity_id ORDER BY count DESC LIMIT 1"
	);
	if ( $bpfn_most_liked ) {
		$bpfn_stats['most_liked_activity'] = (int) $bpfn_most_liked->activity_id;
		$bpfn_stats['most_liked_count']    = (int) $bpfn_most_liked->count;
	}

	$bpfn_stats['recent_activities'] = $wpdb->get_results(
		"SELECT activity_id, user_id, favorited_at FROM {$favorites_table}
		WHERE favorited_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
		ORDER BY favorited_at DESC LIMIT 10"
	);

	$bpfn_stats['trending_7_days'] = $wpdb->get_results(
		"SELECT activity_id, COUNT(*) as favorite_count FROM {$favorites_table}
		WHERE favorited_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
		GROUP BY activity_id ORDER BY favorite_count DESC LIMIT 10"
	);

	$bpfn_stats['trending_30_days'] = $wpdb->get_results(
		"SELECT activity_id, COUNT(*) as favorite_count FROM {$favorites_table}
		WHERE favorited_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
		GROUP BY activity_id ORDER BY favorite_count DESC LIMIT 10"
	);
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

if ( function_exists( 'bp_is_active' ) && bp_is_active( 'notifications' ) && isset( $bp->notifications ) ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from BP.
	$bpfn_notifications_table = $bp->notifications->table_name;
	$bpfn_component           = isset( $bp->favorite_notifier ) ? $bp->favorite_notifier->id : 'favorite_notifier';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin stats query.
	$bpfn_stats['total_notifications'] = (int) $wpdb->get_var(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from BP.
			"SELECT COUNT(*) FROM {$bpfn_notifications_table} WHERE component_name = %s",
			$bpfn_component
		)
	);

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin stats query.
	$bpfn_stats['notifications_last_7_days'] = (int) $wpdb->get_var(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from BP.
			"SELECT COUNT(*) FROM {$bpfn_notifications_table} WHERE component_name = %s AND date_notified >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
			$bpfn_component
		)
	);
}

$bpfn_enhanced_on  = ! empty( $settings['enable_enhanced_notifications'] );
$bpfn_settings_url = admin_url( 'admin.php?page=' . BPFN_Admin::MENU_SLUG . '&tab=settings' );
$bpfn_tools_url    = admin_url( 'admin.php?page=' . BPFN_Admin::MENU_SLUG . '&tab=tools' );
?>

<div class="bpfn-stats-grid">
	<div class="bpfn-stat">
		<p class="bpfn-stat__label"><?php esc_html_e( 'Total Favorites', 'buddypress-favorite-notification' ); ?></p>
		<p class="bpfn-stat__value"><?php echo esc_html( number_format_i18n( $bpfn_stats['total_favorites'] ) ); ?></p>
		<p class="bpfn-stat__trend">
			<?php
			if ( $bpfn_stats['favorites_last_7_days'] > 0 ) {
				echo esc_html(
					sprintf(
						/* translators: %s: Number of new favorites. */
						__( '+%s in last 7 days', 'buddypress-favorite-notification' ),
						number_format_i18n( $bpfn_stats['favorites_last_7_days'] )
					)
				);
			} else {
				esc_html_e( 'All time', 'buddypress-favorite-notification' );
			}
			?>
		</p>
	</div>
	<div class="bpfn-stat">
		<p class="bpfn-stat__label"><?php esc_html_e( 'Notifications Sent', 'buddypress-favorite-notification' ); ?></p>
		<p class="bpfn-stat__value"><?php echo esc_html( number_format_i18n( $bpfn_stats['total_notifications'] ) ); ?></p>
		<p class="bpfn-stat__trend">
			<?php
			if ( $bpfn_stats['notifications_last_7_days'] > 0 ) {
				echo esc_html(
					sprintf(
						/* translators: %s: Number of new notifications. */
						__( '+%s in last 7 days', 'buddypress-favorite-notification' ),
						number_format_i18n( $bpfn_stats['notifications_last_7_days'] )
					)
				);
			} else {
				esc_html_e( 'All time', 'buddypress-favorite-notification' );
			}
			?>
		</p>
	</div>
	<div class="bpfn-stat">
		<p class="bpfn-stat__label"><?php esc_html_e( 'Active Users', 'buddypress-favorite-notification' ); ?></p>
		<p class="bpfn-stat__value"><?php echo esc_html( number_format_i18n( $bpfn_stats['active_users_7_days'] ) ); ?></p>
		<p class="bpfn-stat__trend"><?php esc_html_e( 'Last 7 days', 'buddypress-favorite-notification' ); ?></p>
	</div>
	<div class="bpfn-stat">
		<p class="bpfn-stat__label"><?php esc_html_e( 'Most Liked Activity', 'buddypress-favorite-notification' ); ?></p>
		<p class="bpfn-stat__value"><?php echo esc_html( number_format_i18n( $bpfn_stats['most_liked_count'] ) ); ?></p>
		<p class="bpfn-stat__trend">
			<?php
			if ( $bpfn_stats['most_liked_activity'] ) {
				printf(
					/* translators: %d: Activity ID. */
					esc_html__( 'Activity #%d', 'buddypress-favorite-notification' ),
					(int) $bpfn_stats['most_liked_activity']
				);
			} else {
				esc_html_e( 'No data yet', 'buddypress-favorite-notification' );
			}
			?>
		</p>
	</div>
</div>

<div class="bpfn-card">
	<div class="bpfn-card__head">
		<p class="bpfn-card__title"><?php esc_html_e( 'Recent Favorites (Last 7 Days)', 'buddypress-favorite-notification' ); ?></p>
	</div>
	<div class="bpfn-card__body">
		<?php if ( empty( $bpfn_stats['recent_activities'] ) ) : ?>
			<p><?php esc_html_e( 'No favorites in the last 7 days.', 'buddypress-favorite-notification' ); ?></p>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'User', 'buddypress-favorite-notification' ); ?></th>
						<th><?php esc_html_e( 'Activity', 'buddypress-favorite-notification' ); ?></th>
						<th><?php esc_html_e( 'Date', 'buddypress-favorite-notification' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $bpfn_stats['recent_activities'] as $bpfn_activity ) :
						$bpfn_user      = get_userdata( $bpfn_activity->user_id );
						$bpfn_user_name = $bpfn_user ? $bpfn_user->display_name : __( 'Unknown User', 'buddypress-favorite-notification' );
						$bpfn_link      = function_exists( 'bp_activity_get_permalink' ) ? bp_activity_get_permalink( $bpfn_activity->activity_id ) : '#';
						?>
						<tr>
							<td><?php echo esc_html( $bpfn_user_name ); ?></td>
							<td>
								<a href="<?php echo esc_url( $bpfn_link ); ?>" target="_blank" rel="noopener noreferrer">
									<?php
									printf(
										/* translators: %d: Activity ID. */
										esc_html__( 'Activity #%d', 'buddypress-favorite-notification' ),
										(int) $bpfn_activity->activity_id
									);
									?>
								</a>
							</td>
							<td>
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: human-readable time difference. */
										__( '%s ago', 'buddypress-favorite-notification' ),
										human_time_diff( strtotime( $bpfn_activity->favorited_at ) )
									)
								);
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>

<?php
/**
 * Render a trending-activities table for a window of N days.
 *
 * @param array $rows Result rows with activity_id + favorite_count.
 */
$bpfn_render_trending = function ( $rows ) {
	if ( empty( $rows ) ) {
		echo '<p>' . esc_html__( 'No trending activities in this window yet.', 'buddypress-favorite-notification' ) . '</p>';
		return;
	}
	echo '<table class="wp-list-table widefat fixed striped">';
	echo '<thead><tr>';
	echo '<th>' . esc_html__( 'Rank', 'buddypress-favorite-notification' ) . '</th>';
	echo '<th>' . esc_html__( 'Activity', 'buddypress-favorite-notification' ) . '</th>';
	echo '<th>' . esc_html__( 'Favorites', 'buddypress-favorite-notification' ) . '</th>';
	echo '<th>' . esc_html__( 'Author', 'buddypress-favorite-notification' ) . '</th>';
	echo '<th>' . esc_html__( 'Preview', 'buddypress-favorite-notification' ) . '</th>';
	echo '</tr></thead><tbody>';
	$rank = 1;
	foreach ( $rows as $trending ) {
		$author_name   = __( 'Unknown', 'buddypress-favorite-notification' );
		$preview       = __( 'Activity not found', 'buddypress-favorite-notification' );
		$activity_link = '#';
		if ( function_exists( 'bp_activity_get_specific' ) ) {
			$activity = bp_activity_get_specific( array( 'activity_ids' => $trending->activity_id ) );
			if ( ! empty( $activity['activities'][0] ) ) {
				$activity_obj  = $activity['activities'][0];
				$author        = get_userdata( $activity_obj->user_id );
				$author_name   = $author ? $author->display_name : __( 'Unknown', 'buddypress-favorite-notification' );
				$content       = wp_strip_all_tags( $activity_obj->content );
				$preview       = mb_strlen( $content ) > 100 ? mb_substr( $content, 0, 100 ) . '…' : $content;
				$activity_link = bp_activity_get_permalink( $trending->activity_id );
			}
		}
		echo '<tr>';
		echo '<td><strong>#' . esc_html( (string) $rank ) . '</strong></td>';
		echo '<td><a href="' . esc_url( $activity_link ) . '" target="_blank" rel="noopener noreferrer">';
		printf(
			/* translators: %d: Activity ID. */
			esc_html__( 'Activity #%d', 'buddypress-favorite-notification' ),
			(int) $trending->activity_id
		);
		echo '</a></td>';
		echo '<td><span class="bpfn-badge">' . esc_html( number_format_i18n( $trending->favorite_count ) ) . '</span></td>';
		echo '<td>' . esc_html( $author_name ) . '</td>';
		echo '<td class="bpfn-preview">' . esc_html( $preview ) . '</td>';
		echo '</tr>';
		++$rank;
	}
	echo '</tbody></table>';
};
?>

<div class="bpfn-card">
	<div class="bpfn-card__head">
		<p class="bpfn-card__title"><?php esc_html_e( 'Trending Activities (Last 7 Days)', 'buddypress-favorite-notification' ); ?></p>
	</div>
	<div class="bpfn-card__body">
		<?php $bpfn_render_trending( $bpfn_stats['trending_7_days'] ); ?>
	</div>
</div>

<div class="bpfn-card">
	<div class="bpfn-card__head">
		<p class="bpfn-card__title"><?php esc_html_e( 'Trending Activities (Last 30 Days)', 'buddypress-favorite-notification' ); ?></p>
	</div>
	<div class="bpfn-card__body">
		<?php $bpfn_render_trending( $bpfn_stats['trending_30_days'] ); ?>
	</div>
</div>

<div class="bpfn-card">
	<div class="bpfn-card__head">
		<p class="bpfn-card__title"><?php esc_html_e( 'Quick Actions', 'buddypress-favorite-notification' ); ?></p>
	</div>
	<div class="bpfn-card__body">
		<div class="bpfn-save-bar" style="flex-wrap: wrap;">
			<a href="<?php echo esc_url( $bpfn_settings_url ); ?>" class="bpfn-btn bpfn-btn-secondary">
				<span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
				<?php esc_html_e( 'Configure Settings', 'buddypress-favorite-notification' ); ?>
			</a>
			<a href="<?php echo esc_url( $bpfn_tools_url ); ?>" class="bpfn-btn bpfn-btn-secondary">
				<span class="dashicons dashicons-admin-tools" aria-hidden="true"></span>
				<?php esc_html_e( 'Tools & Maintenance', 'buddypress-favorite-notification' ); ?>
			</a>
		</div>
	</div>
</div>
