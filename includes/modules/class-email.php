<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- Legacy file name.
/**
 * Email Module for BuddyPress Favorite Notification.
 *
 * @package BuddyPress_Favorite_Notification
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email Module Class.
 */
// phpcs:ignore Squiz.Commenting.ClassComment.Missing -- Class docblock is above.
class BPFN_Module_Email {

	/**
	 * Email templates.
	 *
	 * @var array
	 */
	private $templates = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->register_templates();
		$this->setup_hooks();
	}

	/**
	 * Register email templates.
	 */
	private function register_templates() {
		$this->templates = apply_filters(
			'bpfn_email_templates',
			array(
				'activity_favorited' => array(
					'subject'  => __( '[{site_name}] {user_name} favorited your activity', 'buddypress-favorite-notification' ),
					'template' => 'emails/activity-favorited.php',
				),
				'comment_favorited'  => array(
					'subject'  => __( '[{site_name}] {user_name} favorited your comment', 'buddypress-favorite-notification' ),
					'template' => 'emails/comment-favorited.php',
				),
			)
		);
	}

	/**
	 * Setup hooks.
	 */
	private function setup_hooks() {
		// Send email when notification is added - use later priority.
		add_action( 'bpfn_after_add_notification', array( $this, 'send_email_notification' ), 20, 4 );

		// Email customization hooks.
		add_filter( 'bpfn_email_headers', array( $this, 'set_email_headers' ), 10, 2 );
		add_filter( 'bpfn_email_subject', array( $this, 'parse_email_tokens' ), 10, 2 );
		add_filter( 'bpfn_email_message', array( $this, 'parse_email_tokens' ), 10, 2 );
	}

	/**
	 * Check and send email from favorite action.
	 *
	 * Note: Not currently hooked to avoid duplicate emails.
	 * Email is sent via bpfn_after_add_notification action instead.
	 *
	 * @param int $activity_id The activity ID.
	 * @param int $user_id     The user ID.
	 */
	public function check_and_send_email( $activity_id, $user_id ) {
		// Get activity.
		$activity = new BP_Activity_Activity( $activity_id );
		if ( empty( $activity->id ) || (int) $activity->user_id === (int) $user_id ) {
			return;
		}

		// Check if email notifications are enabled.
		$activity_type = bpfn_get_activity_type( $activity->id );
		if ( ! bpfn_is_notification_enabled( $activity->user_id, $activity_type, 'email' ) ) {
			return;
		}

		// Trigger email notification.
		$this->send_email_notification( 0, array(), $activity, $user_id );
	}

	/**
	 * Send email notification.
	 *
	 * @param int                  $notification_id   The notification ID.
	 * @param array                $notification_data The notification data.
	 * @param BP_Activity_Activity $activity          The activity object.
	 * @param int                  $user_id           The user who favorited.
	 */
	public function send_email_notification( $notification_id, $notification_data, $activity, $user_id ) {
		// Check if email notifications are enabled.
		$activity_type = bpfn_get_activity_type( $activity->id );
		if ( ! bpfn_is_notification_enabled( $activity->user_id, $activity_type, 'email' ) ) {
			return;
		}

		// Get recipient data.
		$recipient = get_userdata( $activity->user_id );
		if ( ! $recipient || ! $recipient->user_email ) {
			return;
		}

		// Get sender data.
		$sender = get_userdata( $user_id );
		if ( ! $sender ) {
			return;
		}

		// Determine template.
		$template_key = ( 'activity_comment' === $activity->type ) ? 'comment_favorited' : 'activity_favorited';
		$template_key = apply_filters( 'bpfn_email_template_key', $template_key, $activity, $user_id );

		if ( ! isset( $this->templates[ $template_key ] ) ) {
			return;
		}

		// Get proper display name for sender.
		$sender_name = $this->get_proper_display_name( $sender );

		// Use bp_members_get_user_url() instead of deprecated function.
		$favorited_by_link = function_exists( 'bp_members_get_user_url' ) ?
			bp_members_get_user_url( $user_id ) :
			bp_core_get_user_domain( $user_id );

		$settings_link = function_exists( 'bp_members_get_user_url' ) ?
			bp_members_get_user_url( $activity->user_id ) . bp_get_settings_slug() . '/notifications/' :
			$this->get_settings_link( $activity->user_id );

		// Prepare email data.
		$email_data = array(
			'to'       => $recipient->user_email,
			'subject'  => $this->templates[ $template_key ]['subject'],
			'template' => $this->templates[ $template_key ]['template'],
			'tokens'   => array(
				'site_name'         => get_bloginfo( 'name' ),
				'site_url'          => home_url(),
				'user_name'         => $sender_name,
				'recipient_name'    => $recipient->display_name,
				'activity_content'  => wp_trim_words( wp_strip_all_tags( $activity->content ), 20, '...' ),
				'activity_link'     => bp_activity_get_permalink( $activity->id ),
				'settings_link'     => $settings_link,
				'favorited_by'      => $sender_name,
				'favorited_by_link' => $favorited_by_link,
				'secondary_item_id' => $user_id,
			),
		);

		// Allow customization.
		$email_data = apply_filters( 'bpfn_email_data', $email_data, $activity, $user_id );

		// Send email.
		$this->send_email( $email_data );
	}

	/**
	 * Get proper display name for user.
	 *
	 * @param WP_User $user The user object.
	 * @return string Display name.
	 */
	private function get_proper_display_name( $user ) {
		// Check if display name is the generic "User X" format.
		if ( preg_match( '/^User\s+\d+$/', $user->display_name ) ) {
			// Try first + last name.
			if ( ! empty( $user->first_name ) || ! empty( $user->last_name ) ) {
				$name = trim( $user->first_name . ' ' . $user->last_name );
				if ( ! empty( $name ) ) {
					return $name;
				}
			}
			// Use login name as fallback.
			return $user->user_login;
		}

		return $user->display_name;
	}

	/**
	 * Send test email (public method for testing).
	 *
	 * @param array $email_data Email data.
	 * @return bool Whether the email was sent.
	 */
	public function send_test_email( $email_data ) {
		// Add test flag.
		$email_data['is_test'] = true;

		// Ensure we have all required data.
		$defaults = array(
			'to'       => '',
			'subject'  => '[Test] Favorite Notification',
			'template' => 'emails/activity-favorited.php',
			'tokens'   => array(),
		);

		$email_data = wp_parse_args( $email_data, $defaults );

		// Send the email.
		return $this->send_email( $email_data );
	}

	/**
	 * Send email.
	 *
	 * @param array $email_data Email data.
	 * @return bool Whether the email was sent.
	 */
	private function send_email( $email_data ) {
		// Parse subject.
		$subject = $this->parse_email_tokens( $email_data['subject'], $email_data['tokens'] );
		$subject = apply_filters( 'bpfn_email_subject', $subject, $email_data['tokens'] );

		// Get message.
		$message = $this->get_email_message( $email_data['template'], $email_data['tokens'] );
		$message = apply_filters( 'bpfn_email_message', $message, $email_data['tokens'] );

		// Set headers.
		$headers = apply_filters(
			'bpfn_email_headers',
			array(
				'Content-Type: text/html; charset=UTF-8',
			),
			$email_data
		);

		// Add From header.
		$from_name  = apply_filters( 'bpfn_email_from_name', get_bloginfo( 'name' ) );
		$from_email = apply_filters( 'bpfn_email_from_email', get_option( 'admin_email' ) );
		$headers[]  = 'From: ' . $from_name . ' <' . $from_email . '>';

		// Send email.
		$sent = wp_mail( $email_data['to'], $subject, $message, $headers );

		// Log event.
		bpfn_log_event(
			'email_sent',
			array(
				'to'      => $email_data['to'],
				'subject' => $subject,
				'sent'    => $sent,
				'is_test' => ! empty( $email_data['is_test'] ),
			)
		);

		// Trigger action.
		do_action( 'bpfn_after_send_email', $sent, $email_data );

		return $sent;
	}

	/**
	 * Get email message from template.
	 *
	 * @param string $template The template path.
	 * @param array  $tokens   The email tokens.
	 * @return string Email HTML.
	 */
	private function get_email_message( $template, $tokens ) {
		$template_path = BPFN_TEMPLATES_PATH . $template;

		// Check theme override.
		$theme_template = get_stylesheet_directory() . '/buddypress/bp-favorite-notification/' . $template;
		if ( file_exists( $theme_template ) ) {
			$template_path = $theme_template;
		}

		// Allow filtering template path.
		$template_path = apply_filters( 'bpfn_email_template_path', $template_path, $template );

		if ( ! file_exists( $template_path ) ) {
			// If template doesn't exist, use a simple fallback.
			return $this->get_fallback_email_message( $tokens );
		}

		// Extract tokens as variables for use in template.
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Template variable extraction.
		extract( $tokens );

		// Set default values for commonly used variables.
		$email_subject      = ! empty( $tokens['subject'] ) ? $tokens['subject'] : '';
		$header_color       = '#ff7b00';
		$accent_color       = '#ff7b00';
		$button_color       = '#ff7b00';
		$button_hover_color = '#ff9a33';
		$link_color         = '#1d84b5';

		// Start output buffering.
		ob_start();
		include $template_path;
		$message = ob_get_clean();

		// Parse any remaining tokens.
		$message = $this->parse_email_tokens( $message, $tokens );

		return $message;
	}

	/**
	 * Get fallback email message when template is not found.
	 *
	 * @param array $tokens Email tokens.
	 * @return string Fallback HTML email.
	 */
	private function get_fallback_email_message( $tokens ) {
		$html = '<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<style>
		body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background: #f4f4f4; margin: 0; padding: 0; }
		.container { max-width: 600px; margin: 20px auto; background: #fff; }
		.header { background: #ff7b00; color: white; padding: 20px; text-align: center; }
		.content { padding: 30px; }
		.button { display: inline-block; padding: 12px 30px; background: #ff7b00; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
		.footer { background: #f4f4f4; padding: 20px; text-align: center; font-size: 12px; }
	</style>
</head>
<body>
	<div class="container">
		<div class="header">
			<h1>' . esc_html( $tokens['site_name'] ?? get_bloginfo( 'name' ) ) . '</h1>
		</div>
		<div class="content">
			' . /* translators: %s: Recipient name. */
			'<h2>' . sprintf( esc_html__( 'Hi %s!', 'buddypress-favorite-notification' ), esc_html( $tokens['recipient_name'] ?? 'there' ) ) . '</h2>
			' . /* translators: %s: Name of user who favorited. */
			'<p>' . sprintf( esc_html__( '%s favorited your activity.', 'buddypress-favorite-notification' ), esc_html( $tokens['favorited_by'] ?? 'Someone' ) ) . '</p>';

		if ( ! empty( $tokens['activity_content'] ) ) {
			$html .= '<blockquote style="background: #f8f9fa; border-left: 4px solid #ff7b00; padding: 15px; margin: 20px 0;">' .
					esc_html( $tokens['activity_content'] ) .
					'</blockquote>';
		}

		$html .= '<p style="text-align: center;">
				<a href="' . esc_url( $tokens['activity_link'] ?? home_url() ) . '" class="button">' .
				esc_html__( 'View Activity', 'buddypress-favorite-notification' ) . '</a>
			</p>
		</div>
		<div class="footer">
			<p>' . esc_html__( 'You received this email because you have notifications enabled.', 'buddypress-favorite-notification' ) . '</p>
			<p><a href="' . esc_url( $tokens['settings_link'] ?? home_url() ) . '">' .
				esc_html__( 'Manage Notifications', 'buddypress-favorite-notification' ) . '</a></p>
		</div>
	</div>
</body>
</html>';

		return $html;
	}

	/**
	 * Parse email tokens.
	 *
	 * @param string       $content The email content.
	 * @param string|array $tokens  The tokens to replace.
	 * @return string Parsed content.
	 */
	public function parse_email_tokens( $content, $tokens ) {
		// If tokens is not an array, return content as is.
		if ( ! is_array( $tokens ) ) {
			return $content;
		}

		// Replace each token.
		foreach ( $tokens as $token => $value ) {
			// Only replace string values.
			if ( is_string( $value ) || is_numeric( $value ) ) {
				$content = str_replace( '{' . $token . '}', $value, $content );
			}
		}

		return $content;
	}

	/**
	 * Set email headers.
	 *
	 * @param array $headers    Email headers.
	 * @param array $email_data Email data.
	 * @return array Modified headers.
	 */
	public function set_email_headers( $headers, $email_data ) {
		// Add From header.
		$from_name  = apply_filters( 'bpfn_email_from_name', get_bloginfo( 'name' ) );
		$from_email = apply_filters( 'bpfn_email_from_email', get_option( 'admin_email' ) );

		$headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';

		return $headers;
	}

	/**
	 * Get settings link.
	 *
	 * @param int $user_id User ID.
	 * @return string Settings URL.
	 */
	private function get_settings_link( $user_id ) {
		if ( ! bp_is_active( 'settings' ) ) {
			return home_url();
		}

		// Use bp_members_get_user_url() if available.
		if ( function_exists( 'bp_members_get_user_url' ) ) {
			return bp_members_get_user_url( $user_id ) . bp_get_settings_slug() . '/notifications/';
		}

		// Fallback to deprecated function.
		return trailingslashit( bp_core_get_user_domain( $user_id ) . bp_get_settings_slug() ) . 'notifications/';
	}

	/**
	 * Register custom email template.
	 *
	 * @param string $key    Template key.
	 * @param array  $config Template config.
	 */
	public function register_template( $key, $config ) {
		$this->templates[ $key ] = $config;
	}

	/**
	 * Get all registered templates.
	 *
	 * @return array Templates.
	 */
	public function get_templates() {
		return $this->templates;
	}
}
