<?php
/**
 * Email Module for BuddyPress Favorite Notification
 *
 * @package BuddyPress_Favorite_Notification
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email Module Class
 */
class BPFN_Module_Email {

	/**
	 * Email templates
	 */
	private $templates = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->register_templates();
		$this->setup_hooks();
	}

	/**
	 * Register email templates
	 */
	private function register_templates() {
		$this->templates = apply_filters( 'bpfn_email_templates', array(
			'activity_favorited' => array(
				'subject' => __( '[{site_name}] {user_name} favorited your activity', 'bp-fav-notification' ),
				'template' => 'emails/activity-favorited.php',
			),
			'comment_favorited' => array(
				'subject' => __( '[{site_name}] {user_name} favorited your comment', 'bp-fav-notification' ),
				'template' => 'emails/comment-favorited.php',
			),
		) );
	}

	/**
	 * Setup hooks
	 */
	private function setup_hooks() {
		// Send email when notification is added
		add_action( 'bpfn_after_add_notification', array( $this, 'send_email_notification' ), 10, 4 );
		
		// Email customization hooks
		add_filter( 'bpfn_email_headers', array( $this, 'set_email_headers' ), 10, 2 );
		add_filter( 'bpfn_email_subject', array( $this, 'parse_email_tokens' ), 10, 2 );
		add_filter( 'bpfn_email_message', array( $this, 'parse_email_tokens' ), 10, 2 );
	}

	/**
	 * Send email notification
	 */
	public function send_email_notification( $notification_id, $notification_data, $activity, $user_id ) {
		// Check if email notifications are enabled
		$activity_type = bpfn_get_activity_type( $activity->id );
		if ( ! bpfn_is_notification_enabled( $activity->user_id, $activity_type, 'email' ) ) {
			return;
		}
		
		// Get recipient data
		$recipient = get_userdata( $activity->user_id );
		if ( ! $recipient || ! $recipient->user_email ) {
			return;
		}
		
		// Get sender data
		$sender = get_userdata( $user_id );
		if ( ! $sender ) {
			return;
		}
		
		// Determine template
		$template_key = ( $activity->type === 'activity_comment' ) ? 'comment_favorited' : 'activity_favorited';
		$template_key = apply_filters( 'bpfn_email_template_key', $template_key, $activity, $user_id );
		
		if ( ! isset( $this->templates[ $template_key ] ) ) {
			return;
		}
		
		// Prepare email data
		$email_data = array(
			'to' => $recipient->user_email,
			'subject' => $this->templates[ $template_key ]['subject'],
			'template' => $this->templates[ $template_key ]['template'],
			'tokens' => array(
				'site_name' => get_bloginfo( 'name' ),
				'site_url' => home_url(),
				'user_name' => $sender->display_name,
				'recipient_name' => $recipient->display_name,
				'activity_content' => wp_trim_words( wp_strip_all_tags( $activity->content ), 20, '...' ),
				'activity_link' => bp_activity_get_permalink( $activity->id ),
				'settings_link' => $this->get_settings_link( $activity->user_id ),
				'favorited_by' => $sender->display_name,
				'favorited_by_link' => bp_core_get_user_domain( $user_id ),
			),
		);
		
		// Allow customization
		$email_data = apply_filters( 'bpfn_email_data', $email_data, $activity, $user_id );
		
		// Send email
		$this->send_email( $email_data );
	}

	/**
	 * Send email
	 */
	private function send_email( $email_data ) {
		// Parse subject
		$subject = $this->parse_email_tokens( $email_data['subject'], $email_data['tokens'] );
		$subject = apply_filters( 'bpfn_email_subject', $subject, $email_data );
		
		// Get message
		$message = $this->get_email_message( $email_data['template'], $email_data['tokens'] );
		$message = apply_filters( 'bpfn_email_message', $message, $email_data );
		
		// Set headers
		$headers = apply_filters( 'bpfn_email_headers', array(
			'Content-Type: text/html; charset=UTF-8',
		), $email_data );
		
		// Send email
		$sent = wp_mail( $email_data['to'], $subject, $message, $headers );
		
		// Log event
		bpfn_log_event( 'email_sent', array(
			'to' => $email_data['to'],
			'subject' => $subject,
			'sent' => $sent,
		) );
		
		// Trigger action
		do_action( 'bpfn_after_send_email', $sent, $email_data );
		
		return $sent;
	}

	/**
	 * Get email message from template
	 */
	private function get_email_message( $template, $tokens ) {
		$template_path = BPFN_TEMPLATES_PATH . $template;
		
		// Check theme override
		$theme_template = get_stylesheet_directory() . '/buddypress/bp-favorite-notification/' . $template;
		if ( file_exists( $theme_template ) ) {
			$template_path = $theme_template;
		}
		
		// Allow filtering template path
		$template_path = apply_filters( 'bpfn_email_template_path', $template_path, $template );
		
		if ( ! file_exists( $template_path ) ) {
			return '';
		}
		
		// Extract tokens as variables
		extract( $tokens );
		
		// Start output buffering
		ob_start();
		include $template_path;
		$message = ob_get_clean();
		
		// Parse any remaining tokens
		$message = $this->parse_email_tokens( $message, $tokens );
		
		return $message;
	}

	/**
	 * Parse email tokens
	 */
	public function parse_email_tokens( $content, $tokens ) {
		foreach ( $tokens as $token => $value ) {
			$content = str_replace( '{' . $token . '}', $value, $content );
		}
		
		return $content;
	}

	/**
	 * Set email headers
	 */
	public function set_email_headers( $headers, $email_data ) {
		// Add From header
		$from_name = apply_filters( 'bpfn_email_from_name', get_bloginfo( 'name' ) );
		$from_email = apply_filters( 'bpfn_email_from_email', get_option( 'admin_email' ) );
		
		$headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
		
		return $headers;
	}

	/**
	 * Get settings link
	 */
	private function get_settings_link( $user_id ) {
		if ( ! bp_is_active( 'settings' ) ) {
			return '';
		}
		
		return trailingslashit( bp_core_get_user_domain( $user_id ) . bp_get_settings_slug() ) . 'notifications/';
	}

	/**
	 * Register custom email template
	 */
	public function register_template( $key, $config ) {
		$this->templates[ $key ] = $config;
	}

	/**
	 * Get all registered templates
	 */
	public function get_templates() {
		return $this->templates;
	}
}