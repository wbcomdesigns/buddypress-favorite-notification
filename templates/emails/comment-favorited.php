<?php
/**
 * Email template for comment favorited notification
 *
 * Available variables:
 * - $user_name: Recipient's display name
 * - $favorited_by: Name of user who favorited
 * - $activity_content: The comment content
 * - $activity_link: Link to the comment
 * - $settings_link: Link to notification settings
 * - $site_name: Site name
 * - $site_url: Site URL
 * - $secondary_item_id: ID of user who favorited
 * - $recipient_name: Name of recipient
 *
 * @package BuddyPress_Favorite_Notification
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get user who favorited from secondary_item_id (this is the user_id who performed the favorite action)
$favoriter_id = $secondary_item_id ?? 0;
$favoriter = get_userdata( $favoriter_id );
$favoriter_avatar = '';

if ( $favoriter ) {
	$favoriter_avatar = bp_core_fetch_avatar( array(
		'item_id' => $favoriter->ID,
		'type' => 'full',
		'width' => 60,
		'height' => 60,
		'html' => false,
	) );
}

// Get parent activity if this is a comment
$parent_activity = null;
if ( ! empty( $activity ) && $activity->type === 'activity_comment' ) {
	$parent_activity = new BP_Activity_Activity( $activity->item_id );
}

// Prepare email content
ob_start();
?>

<h2 style="margin: 0 0 20px; color: #333;">
	<?php echo esc_html( sprintf( __( 'Hi %s!', 'bp-fav-notification' ), $recipient_name ?? $user_name ) ); ?>
</h2>

<p style="font-size: 16px; line-height: 1.6; margin-bottom: 25px;">
	<?php _e( 'Your comment just got some love! 💬', 'bp-fav-notification' ); ?>
</p>

<!-- User Info Section -->
<div class="user-info" style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
	<table cellpadding="0" cellspacing="0" border="0" width="100%">
		<tr>
			<td width="70" valign="top">
				<?php if ( $favoriter_avatar ) : ?>
					<img src="<?php echo esc_url( $favoriter_avatar ); ?>" 
					     alt="<?php echo esc_attr( $favorited_by ); ?>" 
					     style="width: 60px; height: 60px; border-radius: 50%; display: block;">
				<?php endif; ?>
			</td>
			<td valign="middle" style="padding-left: 15px;">
				<div style="font-size: 18px; font-weight: bold; color: #333; margin-bottom: 5px;">
					<?php echo esc_html( $favorited_by ); ?>
				</div>
				<div style="color: #666; font-size: 14px;">
					<?php _e( 'favorited your comment', 'bp-fav-notification' ); ?>
				</div>
			</td>
		</tr>
	</table>
</div>

<!-- Comment Context -->
<?php if ( $parent_activity && ! empty( $parent_activity->content ) ) : ?>
	<div style="margin-bottom: 20px;">
		<h3 style="color: #666; font-size: 14px; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px;">
			<?php _e( 'On this activity:', 'bp-fav-notification' ); ?>
		</h3>
		<div style="background: #f0f0f0; padding: 15px; border-radius: 5px; color: #666; font-size: 14px;">
			<?php echo wp_kses_post( wp_trim_words( $parent_activity->content, 20, '...' ) ); ?>
		</div>
	</div>
<?php endif; ?>

<!-- Comment Preview -->
<?php if ( ! empty( $activity_content ) ) : ?>
	<div style="margin-bottom: 30px;">
		<h3 style="color: #666; font-size: 14px; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px;">
			<?php _e( 'Your Comment:', 'bp-fav-notification' ); ?>
		</h3>
		<div class="activity-excerpt" style="background: #f8f9fa; border-left: 4px solid #1d84b5; padding: 15px 20px; font-style: italic; color: #555; position: relative;">
			<span style="position: absolute; top: -10px; left: 20px; font-size: 30px; color: #1d84b5; line-height: 1;">"</span>
			<?php echo wp_kses_post( wp_trim_words( $activity_content, 30, '...' ) ); ?>
		</div>
	</div>
<?php endif; ?>

<!-- Call to Action -->
<div style="text-align: center; margin: 35px 0;">
	<a href="<?php echo esc_url( $activity_link ); ?>" 
	   class="email-button" 
	   style="display: inline-block; padding: 14px 30px; background-color: #1d84b5; color: #ffffff; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px;">
		<?php _e( 'View Conversation', 'bp-fav-notification' ); ?>
	</a>
</div>

<!-- Stats Section -->
<div style="background: #e8f4fd; padding: 20px; border-radius: 8px; margin-top: 30px; text-align: center;">
	<h4 style="margin: 0 0 15px; color: #1d84b5; font-size: 16px;">
		📊 <?php _e( 'Your Comment Stats', 'bp-fav-notification' ); ?>
	</h4>
	<table cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width: 300px; margin: 0 auto;">
		<tr>
			<td style="padding: 10px; text-align: center;">
				<div style="font-size: 24px; font-weight: bold; color: #1d84b5;">
					<?php 
					// Get total favorites for this user's comments
					// Using the recipient's user ID from the activity
					$recipient_user_id = isset( $activity ) ? $activity->user_id : 0;
					$total_favorites = apply_filters( 'bpfn_user_comment_favorites_count', 0, $recipient_user_id );
					echo number_format_i18n( $total_favorites );
					?>
				</div>
				<div style="font-size: 12px; color: #666; text-transform: uppercase;">
					<?php _e( 'Total Favorites', 'bp-fav-notification' ); ?>
				</div>
			</td>
			<td style="padding: 10px; text-align: center; border-left: 1px solid #d1e9f9;">
				<div style="font-size: 24px; font-weight: bold; color: #1d84b5;">
					<?php 
					// Get total comments by user
					$total_comments = apply_filters( 'bpfn_user_comments_count', 0, $recipient_user_id );
					echo number_format_i18n( $total_comments );
					?>
				</div>
				<div style="font-size: 12px; color: #666; text-transform: uppercase;">
					<?php _e( 'Comments', 'bp-fav-notification' ); ?>
				</div>
			</td>
		</tr>
	</table>
</div>

<!-- Tip -->
<div style="margin-top: 30px; padding: 15px; background: #fffbf0; border: 1px solid #ffe4b5; border-radius: 5px;">
	<p style="margin: 0; color: #666;">
		<strong style="color: #ff7b00;">💡 <?php _e( 'Pro tip:', 'bp-fav-notification' ); ?></strong>
		<?php _e( 'Engaging comments that add value to discussions tend to receive more favorites. Keep contributing thoughtfully!', 'bp-fav-notification' ); ?>
	</p>
</div>

<?php
$email_content = ob_get_clean();

// Set specific colors for comment notifications
$header_color = '#1d84b5';
$accent_color = '#1d84b5';
$button_color = '#1d84b5';
$button_hover_color = '#166b94';

// Include base template
include BPFN_TEMPLATES_PATH . 'emails/base.php';
?>