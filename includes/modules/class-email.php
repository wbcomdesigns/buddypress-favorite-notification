<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- Legacy file name.
/**
 * Email Module for BuddyPress Favorite Notification.
 *
 * Sends through BuddyPress Emails, so site owners edit the wording in
 * Dashboard > Emails and the message uses the site's BuddyPress email template.
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
	 * BuddyPress email type per preference type.
	 */
	const TYPES = array(
		'activity_post'    => 'bpfn-activity-favorited',
		'activity_comment' => 'bpfn-comment-favorited',
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Own hook, not bpfn_after_add_notification: email must not depend on the
		// member also having web notifications on.
		add_action( 'bp_activity_add_user_favorite', array( $this, 'send' ), 20, 2 );
		add_filter( 'bp_email_get_unsubscribe_type_schema', array( __CLASS__, 'add_unsubscribe_schema' ) );
		// BuddyPress Tools > Reinstall emails wipes every email, then fires this.
		add_action( 'bp_core_install_emails', array( __CLASS__, 'install' ) );
	}

	/**
	 * Email the activity author when a member favorites it.
	 *
	 * @param int $activity_id The activity ID.
	 * @param int $user_id     The member who favorited.
	 */
	public function send( $activity_id, $user_id ) {
		$activity = new BP_Activity_Activity( $activity_id );
		if ( empty( $activity->id ) || (int) $activity->user_id === (int) $user_id ) {
			return;
		}

		$type = bpfn_get_activity_type( $activity );
		if ( ! isset( self::TYPES[ $type ] ) || ! bpfn_is_notification_enabled( $activity->user_id, $type, 'email' ) ) {
			return;
		}

		$email_type = self::TYPES[ $type ];

		bp_send_email(
			$email_type,
			(int) $activity->user_id,
			array(
				'tokens' => apply_filters(
					'bpfn_email_tokens',
					array(
						'activity.url'     => esc_url( bp_activity_get_permalink( $activity->id ) ),
						'activity.content' => wp_trim_words( wp_strip_all_tags( $activity->content ), 20, '...' ),
						'favoriter.name'   => bp_core_get_user_displayname( $user_id ),
						'favoriter.url'    => esc_url( bp_members_get_user_url( $user_id ) ),
						'unsubscribe'      => esc_url(
							bp_email_get_unsubscribe_link(
								array(
									'user_id'           => (int) $activity->user_id,
									'notification_type' => $email_type,
								)
							)
						),
					),
					$activity,
					$user_id
				),
			)
		);
	}

	/**
	 * The two emails, in bp_core_install_emails() shape.
	 *
	 * @return array
	 */
	public static function get_schema() {
		return array(
			'bpfn-activity-favorited' => array(
				/* translators: do not remove {} brackets or translate its contents. */
				'post_title'   => __( '[{{{site.name}}}] {{favoriter.name}} liked your update', 'buddypress-favorite-notification' ),
				/* translators: do not remove {} brackets or translate its contents. */
				'post_content' => __( "<a href=\"{{{favoriter.url}}}\">{{favoriter.name}}</a> liked your update:\n\n<blockquote>&quot;{{activity.content}}&quot;</blockquote>\n\n<a href=\"{{{activity.url}}}\">View the update</a>.", 'buddypress-favorite-notification' ),
				/* translators: do not remove {} brackets or translate its contents. */
				'post_excerpt' => __( "{{favoriter.name}} liked your update:\n\n\"{{activity.content}}\"\n\nView the update: {{{activity.url}}}", 'buddypress-favorite-notification' ),
			),
			'bpfn-comment-favorited'  => array(
				/* translators: do not remove {} brackets or translate its contents. */
				'post_title'   => __( '[{{{site.name}}}] {{favoriter.name}} liked your comment', 'buddypress-favorite-notification' ),
				/* translators: do not remove {} brackets or translate its contents. */
				'post_content' => __( "<a href=\"{{{favoriter.url}}}\">{{favoriter.name}}</a> liked your comment:\n\n<blockquote>&quot;{{activity.content}}&quot;</blockquote>\n\n<a href=\"{{{activity.url}}}\">View the conversation</a>.", 'buddypress-favorite-notification' ),
				/* translators: do not remove {} brackets or translate its contents. */
				'post_excerpt' => __( "{{favoriter.name}} liked your comment:\n\n\"{{activity.content}}\"\n\nView the conversation: {{{activity.url}}}", 'buddypress-favorite-notification' ),
			),
		);
	}

	/**
	 * The emails exactly as 2.2.0 installed them, before the wording moved to
	 * "liked". Only used to tell an untouched email from one the owner edited.
	 * Kept translatable so a site installed in another language matches the
	 * translated text it was given.
	 *
	 * @return array Same shape as get_schema().
	 */
	private static function get_legacy_schema() {
		return array(
			'bpfn-activity-favorited' => array(
				/* translators: Old 2.2.0 email subject, used only to detect unedited emails. Keep the existing translation. Do not remove {} brackets or translate its contents. */
				'post_title'   => __( '[{{{site.name}}}] {{favoriter.name}} favorited your update', 'buddypress-favorite-notification' ),
				/* translators: Old 2.2.0 email body, used only to detect unedited emails. Keep the existing translation. Do not remove {} brackets or translate its contents. */
				'post_content' => __( "<a href=\"{{{favoriter.url}}}\">{{favoriter.name}}</a> favorited your update:\n\n<blockquote>&quot;{{activity.content}}&quot;</blockquote>\n\n<a href=\"{{{activity.url}}}\">View the update</a>.", 'buddypress-favorite-notification' ),
				/* translators: Old 2.2.0 plain-text email, used only to detect unedited emails. Keep the existing translation. Do not remove {} brackets or translate its contents. */
				'post_excerpt' => __( "{{favoriter.name}} favorited your update:\n\n\"{{activity.content}}\"\n\nView the update: {{{activity.url}}}", 'buddypress-favorite-notification' ),
			),
			'bpfn-comment-favorited'  => array(
				/* translators: Old 2.2.0 email subject, used only to detect unedited emails. Keep the existing translation. Do not remove {} brackets or translate its contents. */
				'post_title'   => __( '[{{{site.name}}}] {{favoriter.name}} favorited your comment', 'buddypress-favorite-notification' ),
				/* translators: Old 2.2.0 email body, used only to detect unedited emails. Keep the existing translation. Do not remove {} brackets or translate its contents. */
				'post_content' => __( "<a href=\"{{{favoriter.url}}}\">{{favoriter.name}}</a> favorited your comment:\n\n<blockquote>&quot;{{activity.content}}&quot;</blockquote>\n\n<a href=\"{{{activity.url}}}\">View the conversation</a>.", 'buddypress-favorite-notification' ),
				/* translators: Old 2.2.0 plain-text email, used only to detect unedited emails. Keep the existing translation. Do not remove {} brackets or translate its contents. */
				'post_excerpt' => __( "{{favoriter.name}} favorited your comment:\n\n\"{{activity.content}}\"\n\nView the conversation: {{{activity.url}}}", 'buddypress-favorite-notification' ),
			),
		);
	}

	/**
	 * Whether a stored email post still holds the given default text.
	 *
	 * Whitespace is collapsed so line-ending changes made on save do not count
	 * as an edit; any change to the words does.
	 *
	 * @param WP_Post $post    Email post.
	 * @param array   $shipped Default title, content and excerpt.
	 * @return bool
	 */
	private static function is_unedited( $post, $shipped ) {
		foreach ( $shipped as $field => $text ) {
			if ( preg_replace( '/\s+/', ' ', trim( (string) $post->$field ) ) !== preg_replace( '/\s+/', ' ', trim( $text ) ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Descriptions and unsubscribe settings, in bp_email_get_type_schema() shape.
	 *
	 * The unsubscribe meta keys are the ones BPFN_Module_Settings mirrors into the
	 * member's preferences, so the email footer link turns the email off.
	 *
	 * @return array
	 */
	public static function get_type_schema() {
		$meta = bpfn_email_meta_keys();
		return array(
			'bpfn-activity-favorited' => array(
				'description'      => __( 'A member liked an activity update the recipient posted.', 'buddypress-favorite-notification' ),
				'named_salutation' => true,
				'unsubscribe'      => array(
					'meta_key' => $meta['activity_post'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Schema key, not a query.
					'message'  => __( 'You will no longer receive emails when someone likes your updates.', 'buddypress-favorite-notification' ),
				),
			),
			'bpfn-comment-favorited'  => array(
				'description'      => __( 'A member liked an activity comment the recipient wrote.', 'buddypress-favorite-notification' ),
				'named_salutation' => true,
				'unsubscribe'      => array(
					'meta_key' => $meta['activity_comment'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Schema key, not a query.
					'message'  => __( 'You will no longer receive emails when someone likes your comments.', 'buddypress-favorite-notification' ),
				),
			),
		);
	}

	/**
	 * Register our types with BuddyPress's unsubscribe handler and link builder.
	 *
	 * @param array $schema Email type schema.
	 * @return array
	 */
	public static function add_unsubscribe_schema( $schema ) {
		return array_merge( $schema, self::get_type_schema() );
	}

	/**
	 * Create any of our email posts that do not exist yet, and bring an existing
	 * one up to the current wording only while it still holds the old shipped
	 * text. An email the owner edited is never touched.
	 *
	 * Runs on every version change (BP_Favorite_Notification::maybe_upgrade()).
	 */
	public static function install() {
		if ( ! function_exists( 'bp_get_email' ) ) {
			return;
		}

		// Email posts live on the root blog.
		$switched = ! bp_is_root_blog();
		if ( $switched ) {
			switch_to_blog( bp_get_root_blog_id() );
		}
		$taxonomy = bp_get_email_tax_type();
		$types    = self::get_type_schema();
		$legacy   = self::get_legacy_schema();

		foreach ( self::get_schema() as $email_type => $email ) {
			$existing = bp_get_email( $email_type );
			if ( ! is_wp_error( $existing ) ) {
				$post = $existing->get_post_object();
				if ( isset( $legacy[ $email_type ] ) && self::is_unedited( $post, $legacy[ $email_type ] ) ) {
					wp_update_post( wp_slash( array( 'ID' => $post->ID ) + $email ) );
				}
				continue;
			}

			$post_id = wp_insert_post(
				array_merge(
					$email,
					array(
						'post_status' => 'publish',
						'post_type'   => bp_get_email_post_type(),
					)
				)
			);
			if ( ! $post_id ) {
				continue;
			}

			foreach ( (array) wp_set_object_terms( $post_id, $email_type, $taxonomy ) as $tt_id ) {
				$term = get_term_by( 'term_taxonomy_id', (int) $tt_id, $taxonomy );
				if ( $term ) {
					wp_update_term( (int) $term->term_id, $taxonomy, array( 'description' => $types[ $email_type ]['description'] ) );
				}
			}
		}

		if ( $switched ) {
			restore_current_blog();
		}
	}
}
