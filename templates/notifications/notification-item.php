<?php
/**
 * Notification item template.
 *
 * This template can be overridden by copying it to:
 * /your-theme/buddypress/bp-favorite-notification/notifications/notification-item.php
 *
 * Available variables:
 * - $notification_type: Type of notification (favorite, favorite_comment, etc.).
 * - $text: Notification text.
 * - $link: Link to the activity.
 * - $activity_id: Activity ID.
 * - $activity_excerpt: Activity content excerpt.
 * - $activity_type: Type of activity.
 * - $user_id: ID of user who triggered notification.
 * - $user_name: Name of user who triggered notification.
 * - $user_link: Link to user profile.
 * - $user_avatar: User avatar HTML.
 * - $timestamp: Notification timestamp.
 *
 * @package BuddyPress_Favorite_Notification
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get additional data.
$is_unread = ! empty( $is_new );
// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Used for display only.
$time_ago = ! empty( $timestamp ) ? human_time_diff( $timestamp, current_time( 'timestamp' ) ) : '';

// Notification type icon.
$icon_map   = array(
	'favorite'         => 'dashicons-heart',
	'favorite_comment' => 'dashicons-comment',
	'like'             => 'dashicons-thumbs-up',
);
$icon_class = isset( $icon_map[ $notification_type ] ) ? $icon_map[ $notification_type ] : 'dashicons-star-filled';

// Check if enhanced notifications are enabled.
$enhanced_enabled = apply_filters( 'bpfn_use_enhanced_template', bpfn_is_feature_enabled( 'enhanced_notifications' ) );

if ( $enhanced_enabled ) : ?>

	<!-- Enhanced Notification Template -->
	<div class="bpfn-enhanced-notification <?php echo $is_unread ? 'unread' : ''; ?>"
		data-notification-id="<?php echo esc_attr( $id ?? '' ); ?>"
		data-type="<?php echo esc_attr( $notification_type ); ?>">

		<?php if ( ! empty( $user_avatar ) ) : ?>
			<div class="bpfn-enhanced-avatar">
				<a href="<?php echo esc_url( $user_link ); ?>">
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Avatar HTML from bp_core_fetch_avatar.
					echo $user_avatar;
					?>
				</a>
				<span class="bpfn-type-indicator">
					<i class="dashicons <?php echo esc_attr( $icon_class ); ?>"></i>
				</span>
			</div>
		<?php endif; ?>

		<div class="bpfn-enhanced-content">
			<div class="bpfn-enhanced-header">
				<h4 class="bpfn-enhanced-title">
					<a href="<?php echo esc_url( $link ); ?>">
						<?php echo wp_kses_post( $text ); ?>
					</a>
				</h4>
				<?php if ( $time_ago ) : ?>
					<span class="bpfn-enhanced-time">
						<?php
						/* translators: %s: Time elapsed. */
						echo esc_html( sprintf( __( '%s ago', 'buddypress-favorite-notification' ), $time_ago ) );
						?>
					</span>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $activity_excerpt ) ) : ?>
				<div class="bpfn-activity-preview">
					<div class="bpfn-activity-preview-content">
						<?php echo esc_html( $activity_excerpt ); ?>
					</div>
				</div>
			<?php endif; ?>

			<div class="bpfn-enhanced-actions">
				<a href="<?php echo esc_url( $link ); ?>" class="bpfn-enhanced-action primary">
					<i class="dashicons dashicons-visibility"></i>
					<?php esc_html_e( 'View Activity', 'buddypress-favorite-notification' ); ?>
				</a>

				<?php if ( ! empty( $user_link ) && bp_loggedin_user_domain() !== $user_link ) : ?>
					<a href="<?php echo esc_url( $user_link ); ?>" class="bpfn-enhanced-action secondary">
						<i class="dashicons dashicons-admin-users"></i>
						<?php esc_html_e( 'View Profile', 'buddypress-favorite-notification' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>

		<!-- Quick actions -->
		<div class="bpfn-quick-actions">
			<span class="bpfn-quick-action bpfn-mark-read" title="<?php esc_attr_e( 'Mark as read', 'buddypress-favorite-notification' ); ?>">
				<i class="dashicons dashicons-yes"></i>
			</span>
		</div>
	</div>

<?php else : ?>

	<!-- Standard Notification Template -->
	<div class="bpfn-notification-item <?php echo $is_unread ? 'unread' : ''; ?>"
		data-notification-id="<?php echo esc_attr( $id ?? '' ); ?>"
		data-type="<?php echo esc_attr( $notification_type ); ?>">

		<div class="bpfn-notification-content">
			<?php if ( ! empty( $user_avatar ) ) : ?>
				<div class="bpfn-notification-avatar">
					<a href="<?php echo esc_url( $user_link ); ?>">
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Avatar HTML from bp_core_fetch_avatar.
						echo $user_avatar;
						?>
					</a>
				</div>
			<?php endif; ?>

			<div class="bpfn-notification-body">
				<div class="bpfn-notification-text">
					<i class="dashicons <?php echo esc_attr( $icon_class ); ?>"></i>
					<a href="<?php echo esc_url( $link ); ?>">
						<?php echo wp_kses_post( $text ); ?>
					</a>
				</div>

				<?php if ( ! empty( $activity_excerpt ) ) : ?>
					<div class="bpfn-activity-excerpt">
						<blockquote><?php echo esc_html( $activity_excerpt ); ?></blockquote>
					</div>
				<?php endif; ?>

				<?php if ( $time_ago ) : ?>
					<div class="bpfn-notification-meta">
						<time datetime="<?php echo esc_attr( gmdate( 'c', $timestamp ) ); ?>">
							<?php
							/* translators: %s: Time elapsed. */
							echo esc_html( sprintf( __( '%s ago', 'buddypress-favorite-notification' ), $time_ago ) );
							?>
						</time>
					</div>
				<?php endif; ?>

				<div class="bpfn-notification-actions">
					<a href="<?php echo esc_url( $link ); ?>" class="bpfn-view-activity">
						<?php esc_html_e( 'View Activity', 'buddypress-favorite-notification' ); ?>
					</a>
				</div>
			</div>
		</div>
	</div>

<?php endif; ?>

<?php
// Allow additional content.
do_action( 'bpfn_after_notification_item', $notification_type, $activity_id );
?>
