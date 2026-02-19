<?php
/**
 * Email template for activity favorited notification.
 *
 * Available variables:
 * - $user_name: Recipient's display name.
 * - $favorited_by: Name of user who favorited.
 * - $activity_content: The activity content.
 * - $activity_link: Link to the activity.
 * - $settings_link: Link to notification settings.
 * - $site_name: Site name.
 * - $site_url: Site URL.
 *
 * @package BuddyPress_Favorite_Notification
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get user who favorited.
$favoriter        = get_userdata( $secondary_item_id ?? 0 );
$favoriter_avatar = bp_core_fetch_avatar(
	array(
		'item_id' => $favoriter->ID ?? 0,
		'type'    => 'full',
		'width'   => 60,
		'height'  => 60,
		'html'    => false,
	)
);

// Prepare email content.
ob_start();
?>

<h2 style="margin: 0 0 20px; color: #333;">
	<?php
	/* translators: %s: Recipient name. */
	echo esc_html( sprintf( __( 'Hi %s!', 'buddypress-favorite-notification' ), $user_name ) );
	?>
</h2>

<p style="font-size: 16px; line-height: 1.6; margin-bottom: 25px;">
	<?php esc_html_e( 'Great news! Someone just favorited your activity.', 'buddypress-favorite-notification' ); ?>
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
					<?php esc_html_e( 'favorited your activity', 'buddypress-favorite-notification' ); ?>
				</div>
			</td>
		</tr>
	</table>
</div>

<!-- Activity Preview -->
<?php if ( ! empty( $activity_content ) ) : ?>
	<div style="margin-bottom: 30px;">
		<h3 style="color: #666; font-size: 14px; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px;">
			<?php esc_html_e( 'Your Activity:', 'buddypress-favorite-notification' ); ?>
		</h3>
		<div class="activity-excerpt" style="background: #f8f9fa; border-left: 4px solid #ff7b00; padding: 15px 20px; font-style: italic; color: #555;">
			<?php echo wp_kses_post( wp_trim_words( $activity_content, 30, '...' ) ); ?>
		</div>
	</div>
<?php endif; ?>

<!-- Call to Action -->
<div style="text-align: center; margin: 35px 0;">
	<a href="<?php echo esc_url( $activity_link ); ?>"
		class="email-button"
		style="display: inline-block; padding: 14px 30px; background-color: #ff7b00; color: #ffffff; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px;">
		<?php esc_html_e( 'View Your Activity', 'buddypress-favorite-notification' ); ?>
	</a>
</div>

<!-- Additional Info -->
<div style="background: #fef7f1; padding: 20px; border-radius: 8px; margin-top: 30px;">
	<h4 style="margin: 0 0 10px; color: #ff7b00; font-size: 16px;">
		&#128161; <?php esc_html_e( 'Did you know?', 'buddypress-favorite-notification' ); ?>
	</h4>
	<p style="margin: 0; color: #666; line-height: 1.6;">
		<?php esc_html_e( 'Activities with more favorites get better visibility in the community. Keep creating engaging content!', 'buddypress-favorite-notification' ); ?>
	</p>
</div>

<!-- Social Links (optional) -->
<?php
$social_links = apply_filters( 'bpfn_email_social_links', array() );
if ( ! empty( $social_links ) ) :
	?>
	<div style="text-align: center; margin-top: 30px; padding-top: 30px; border-top: 1px solid #eee;">
		<p style="color: #999; margin-bottom: 15px;"><?php esc_html_e( 'Connect with us:', 'buddypress-favorite-notification' ); ?></p>
		<?php foreach ( $social_links as $platform => $url ) : ?>
			<a href="<?php echo esc_url( $url ); ?>" style="display: inline-block; margin: 0 10px;">
				<img src="<?php echo esc_url( BPFN_ASSETS_URL . 'images/social/' . $platform . '.png' ); ?>"
					alt="<?php echo esc_attr( ucfirst( $platform ) ); ?>"
					style="width: 32px; height: 32px;">
			</a>
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<?php
$email_content = ob_get_clean();

// Include base template.
require BPFN_TEMPLATES_PATH . 'emails/base.php';
?>
