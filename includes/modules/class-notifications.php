<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- Legacy file name.
/**
 * Notifications Module for BuddyPress Favorite Notification.
 *
 * The ONLY place a favorite notification is created, removed or formatted.
 * BuddyPress reaches format_notification() through the component's
 * notification_callback (bpfn_compat_format_notifications()).
 *
 * @package BuddyPress_Favorite_Notification
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Notifications Module Class.
 */
// phpcs:ignore Squiz.Commenting.ClassComment.Missing -- Class docblock is above.
class BPFN_Module_Notifications {

	/**
	 * Notification types, keyed by the preference type they belong to.
	 *
	 * @var array
	 */
	private $notification_types = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->notification_types = array(
			'activity_post'    => array(
				'labels'        => array(
					/* translators: %s: User display name. */
					'single'   => __( '%s favorited your activity', 'buddypress-favorite-notification' ),
					/* translators: %d: Number of people. */
					'multiple' => __( '%d people favorited your activity', 'buddypress-favorite-notification' ),
				),
				'action_prefix' => 'fav_notify',
				'css_type'      => 'favorite',
			),
			'activity_comment' => array(
				'labels'        => array(
					/* translators: %s: User display name. */
					'single'   => __( '%s favorited your comment', 'buddypress-favorite-notification' ),
					/* translators: %d: Number of people. */
					'multiple' => __( '%d people favorited your comment', 'buddypress-favorite-notification' ),
				),
				'action_prefix' => 'fav_comment_notify',
				'css_type'      => 'favorite_comment',
			),
		);

		add_action( 'bp_activity_add_user_favorite', array( $this, 'add_favorite_notification' ), 10, 2 );
		add_action( 'bp_activity_remove_user_favorite', array( $this, 'remove_favorite_notification' ), 10, 2 );
	}

	/**
	 * Preference type a stored component_action belongs to.
	 *
	 * @param string $component_action e.g. fav_notify_12 or fav_comment_notify_12.
	 * @return string activity_post|activity_comment
	 */
	public static function get_type_for_action( $component_action ) {
		return 0 === strpos( (string) $component_action, 'fav_comment_notify' ) ? 'activity_comment' : 'activity_post';
	}

	/**
	 * Add notification when activity is favorited.
	 *
	 * @param int $activity_id The activity ID.
	 * @param int $user_id     The user who favorited.
	 */
	public function add_favorite_notification( $activity_id, $user_id ) {
		$activity = new BP_Activity_Activity( $activity_id );
		if ( empty( $activity->id ) || (int) $activity->user_id === (int) $user_id ) {
			return;
		}

		$type = bpfn_get_activity_type( $activity );
		if ( ! bpfn_is_notification_enabled( $activity->user_id, $type, 'web' ) ) {
			return;
		}

		$prefix          = isset( $this->notification_types[ $type ] ) ? $this->notification_types[ $type ]['action_prefix'] : 'fav_notify';
		$notification_id = bp_notifications_add_notification(
			array(
				'user_id'           => $activity->user_id,
				'item_id'           => $activity_id,
				'secondary_item_id' => $user_id,
				'component_name'    => 'favorite_notifier',
				'component_action'  => $prefix . '_' . $activity_id,
				'date_notified'     => bp_core_current_time(),
				'is_new'            => 1,
			)
		);

		if ( $notification_id ) {
			do_action(
				'bpfn_after_add_notification',
				$notification_id,
				array(
					'activity_id'       => $activity_id,
					'user_id'           => $activity->user_id,
					'secondary_item_id' => $user_id,
				),
				$activity,
				$user_id
			);
		}
	}

	/**
	 * Remove notification when activity is unfavorited.
	 *
	 * @param int $activity_id The activity ID.
	 * @param int $user_id     The user who unfavorited.
	 */
	public function remove_favorite_notification( $activity_id, $user_id ) {
		BP_Notifications_Notification::delete(
			array(
				'item_id'           => $activity_id,
				'secondary_item_id' => $user_id,
				'component_name'    => 'favorite_notifier',
			)
		);
	}

	/**
	 * Format a single notification.
	 *
	 * @param string $action            The component action.
	 * @param int    $item_id           The activity ID.
	 * @param int    $secondary_item_id The user who favorited.
	 * @param int    $total_items       Total items.
	 * @param string $format            'string' or 'array'.
	 * @param int    $id                The notification id (for mark-as-read on click).
	 * @return string|array|false The formatted notification.
	 */
	public function format_notification( $action, $item_id, $secondary_item_id, $total_items, $format = 'string', $id = 0 ) {
		$activity = new BP_Activity_Activity( $item_id );
		if ( empty( $activity->id ) ) {
			return false;
		}

		$type   = self::get_type_for_action( $action );
		$config = $this->notification_types[ $type ];
		$name   = bp_core_get_user_displayname( $secondary_item_id );
		$name   = $name ? $name : __( 'Someone', 'buddypress-favorite-notification' );

		$text = $total_items > 1
			? sprintf( $config['labels']['multiple'], $total_items )
			: sprintf( $config['labels']['single'], $name );

		// `rid` lets BuddyPress core mark this notification read when the
		// recipient opens the activity (bp_activity_screen_single_activity_permalink).
		$link = bp_activity_get_permalink( $item_id );
		if ( $id > 0 ) {
			$link = add_query_arg( 'rid', (int) $id, $link );
		}

		if ( 'string' === $format ) {
			return apply_filters( 'bpfn_notification_string', '<a href="' . esc_url( $link ) . '">' . esc_html( $text ) . '</a>', $item_id, $secondary_item_id, $total_items );
		}

		return apply_filters(
			'bpfn_notification_array',
			array(
				'text'              => $text,
				'link'              => $link,
				'notification_type' => $config['css_type'],
				'activity_id'       => $item_id,
				'activity_excerpt'  => wp_trim_words( wp_strip_all_tags( $activity->content ), 20, '...' ),
				'activity_type'     => $activity->type,
				'user_id'           => $secondary_item_id,
				'user_name'         => $name,
				'user_link'         => bp_members_get_user_url( $secondary_item_id ),
				'user_avatar'       => bp_core_fetch_avatar(
					array(
						'item_id' => $secondary_item_id,
						'type'    => 'thumb',
						'width'   => 50,
						'height'  => 50,
					)
				),
			),
			$activity,
			$secondary_item_id
		);
	}

	/**
	 * Register custom notification type (public API: bpfn_register_notification_type()).
	 *
	 * @param string $type The notification type key.
	 * @param array  $args The notification type configuration.
	 */
	public function register_notification_type( $type, $args ) {
		$this->notification_types[ $type ] = wp_parse_args(
			$args,
			array(
				'labels'        => array(
					'single'   => '',
					'multiple' => '',
				),
				'action_prefix' => $type . '_notify',
				'css_type'      => $type,
			)
		);
	}
}
