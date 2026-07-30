<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- Legacy file name.
/**
 * Favorite Display Module for BuddyPress Favorite Notification.
 *
 * @package BuddyPress_Favorite_Notification
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Favorite Display Module Class.
 */
// phpcs:ignore Squiz.Commenting.ClassComment.Missing -- Class docblock is above.
class BPFN_Module_Favorite_Display {

	/**
	 * Database table name.
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Cache group.
	 *
	 * @var string
	 */
	private $cache_group = 'bpfn_favorites';

	/**
	 * Cache expiration (5 minutes).
	 *
	 * @var int
	 */
	private $cache_expiration = 300;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'bp_activity_favorites';

		$this->setup_hooks();
	}

	/**
	 * Setup hooks.
	 */
	private function setup_hooks() {
		// Display favorite count on activities.
		// `bp_activity_before_post_footer_content` is a BuddyX/BuddyX Pro/Reign
		// theme action, so on every other theme the count never rendered. Also
		// hook the core `bp_activity_entry_content` action (fired by BuddyPress
		// Nouveau and Legacy templates) so the count shows on all themes; a
		// per-activity guard in display_favorite_count() prevents a double
		// render if a theme happens to fire both.
		add_action( 'bp_activity_before_post_footer_content', array( $this, 'display_favorite_count' ), 10 );
		add_action( 'bp_activity_entry_content', array( $this, 'display_favorite_count' ), 20 );

		// Sync with BuddyPress favorite actions.
		add_action( 'bp_activity_add_user_favorite', array( $this, 'sync_favorite_add' ), 10, 2 );
		add_action( 'bp_activity_remove_user_favorite', array( $this, 'sync_favorite_remove' ), 10, 2 );

		// AJAX handlers.
		add_action( 'wp_ajax_bpfn_get_all_favorites', array( $this, 'ajax_get_all_favorites' ) );
		add_action( 'wp_ajax_nopriv_bpfn_get_all_favorites', array( $this, 'ajax_get_all_favorites' ) );
		add_action( 'wp_ajax_bpfn_refresh_favorite_display', array( $this, 'ajax_refresh_favorite_display' ) );
		add_action( 'wp_ajax_nopriv_bpfn_refresh_favorite_display', array( $this, 'ajax_refresh_favorite_display' ) );
	}

	/**
	 * Sync favorite add to our table.
	 *
	 * @param int $activity_id The activity ID.
	 * @param int $user_id     The user ID.
	 */
	public function sync_favorite_add( $activity_id, $user_id ) {
		global $wpdb;

		// Insert into our table.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert.
		$wpdb->insert(
			$this->table_name,
			array(
				'activity_id' => $activity_id,
				'user_id'     => $user_id,
			),
			array( '%d', '%d' )
		);

		// Clear cache for this activity.
		$this->clear_cache( $activity_id );
	}

	/**
	 * Sync favorite remove from our table.
	 *
	 * @param int $activity_id The activity ID.
	 * @param int $user_id     The user ID.
	 */
	public function sync_favorite_remove( $activity_id, $user_id ) {
		global $wpdb;

		// Delete from our table.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete.
		$wpdb->delete(
			$this->table_name,
			array(
				'activity_id' => $activity_id,
				'user_id'     => $user_id,
			),
			array( '%d', '%d' )
		);

		// Clear cache for this activity.
		$this->clear_cache( $activity_id );
	}

	/**
	 * Get favorite count for activity.
	 *
	 * @param int $activity_id The activity ID.
	 * @return int Favorite count.
	 */
	public function get_favorite_count( $activity_id ) {
		global $wpdb;

		$cache_key = 'count_' . $activity_id . '_' . $this->get_cache_incrementor( $activity_id );
		$count     = wp_cache_get( $cache_key, $this->cache_group );

		if ( false === $count ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table with object cache.
			$count = (int) $wpdb->get_var(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from property.
					"SELECT COUNT(*) FROM {$this->table_name} WHERE activity_id = %d",
					$activity_id
				)
			);

			wp_cache_set( $cache_key, $count, $this->cache_group, $this->cache_expiration );
		}

		return max( 0, $count );
	}

	/**
	 * Get users who favorited activity.
	 *
	 * @param int $activity_id The activity ID.
	 * @param int $limit       Number of users to return.
	 * @param int $offset      Offset for pagination.
	 * @return array Users data with total and remaining counts.
	 */
	public function get_users_who_favorited( $activity_id, $limit = 3, $offset = 0 ) {
		global $wpdb;

		$cache_key = 'users_' . $activity_id . '_' . $limit . '_' . $offset . '_' . $this->get_cache_incrementor( $activity_id );
		$cached    = wp_cache_get( $cache_key, $this->cache_group );

		if ( false !== $cached ) {
			return $cached;
		}

		// Get total count.
		$total = $this->get_favorite_count( $activity_id );

		if ( 0 === $total ) {
			$result = array(
				'users'     => array(),
				'total'     => 0,
				'remaining' => 0,
			);
			wp_cache_set( $cache_key, $result, $this->cache_group, $this->cache_expiration );
			return $result;
		}

		// Get user IDs (bounded by LIMIT/OFFSET, never an unbounded fetch).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table with object cache.
		$user_ids = $wpdb->get_col(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from property.
				"SELECT user_id FROM {$this->table_name}
			WHERE activity_id = %d
			ORDER BY favorited_at DESC
			LIMIT %d OFFSET %d",
				$activity_id,
				$limit,
				$offset
			)
		);

		// Batch-prime the user cache with a single query so the loop below
		// resolves each get_userdata() from cache instead of one query per row
		// (eliminates the N+1 on hot activities / the "show all" modal).
		if ( ! empty( $user_ids ) ) {
			cache_users( $user_ids );
		}

		// Get user data (user objects now served from the primed cache).
		$users = array();
		foreach ( $user_ids as $user_id ) {
			$user = get_userdata( $user_id );
			if ( $user ) {
				$users[] = array(
					'id'     => $user_id,
					'name'   => $user->display_name,
					'avatar' => bp_core_fetch_avatar(
						array(
							'item_id' => $user_id,
							'type'    => 'thumb',
							'width'   => 32,
							'height'  => 32,
							'html'    => false,
						)
					),
					'link'   => function_exists( 'bp_members_get_user_url' ) ?
								bp_members_get_user_url( $user_id ) :
								bp_core_get_user_domain( $user_id ),
				);
			}
		}

		$result = array(
			'users'     => $users,
			'total'     => $total,
			'remaining' => max( 0, $total - $limit - $offset ),
		);

		wp_cache_set( $cache_key, $result, $this->cache_group, $this->cache_expiration );

		return $result;
	}

	/**
	 * Format favorite text (Facebook style).
	 *
	 * @param array $users_data  Users data array.
	 * @param int   $activity_id The activity ID.
	 * @return string Formatted HTML text.
	 */
	public function format_favorite_text( $users_data, $activity_id = 0 ) {
		$total     = $users_data['total'];
		$users     = $users_data['users'];
		$remaining = $users_data['remaining'];

		if ( 0 === $total ) {
			return '';
		}

		if ( 1 === $total && isset( $users[0] ) ) {
			return sprintf(
				'<a href="%s" class="bpfn-user-link">%s</a>',
				esc_url( $users[0]['link'] ),
				esc_html( $users[0]['name'] )
			);
		}

		if ( 2 === $total && isset( $users[0], $users[1] ) ) {
			return sprintf(
				/* translators: 1: Link to the first person who favorited, 2: Link to the second person who favorited. */
				__( '%1$s and %2$s', 'buddypress-favorite-notification' ),
				sprintf(
					'<a href="%s" class="bpfn-user-link">%s</a>',
					esc_url( $users[0]['link'] ),
					esc_html( $users[0]['name'] )
				),
				sprintf(
					'<a href="%s" class="bpfn-user-link">%s</a>',
					esc_url( $users[1]['link'] ),
					esc_html( $users[1]['name'] )
				)
			);
		}

		// More than 2 users.
		$names = array();
		$shown = min( 2, count( $users ) );

		// The "and N others" count must reflect the names actually shown (2),
		// not the fetch LIMIT (3). Deriving it from the fetch limit produced an
		// off-by-one ("user1, user2 and 1" for 4 likes) and, when the total was
		// exactly 3, dropped the third liker into a plain "user1 and user2".
		$remaining = max( 0, $total - $shown );

		for ( $i = 0; $i < $shown; $i++ ) {
			if ( isset( $users[ $i ] ) ) {
				$names[] = sprintf(
					'<a href="%s" class="bpfn-user-link">%s</a>',
					esc_url( $users[ $i ]['link'] ),
					esc_html( $users[ $i ]['name'] )
				);
			}
		}

		if ( $remaining > 0 ) {
			// The whole "N others" phrase is one _n() string (not a number
			// concatenated to a bare "other"/"others"), so translators control
			// word order and plural form. It is then placed into the link, and
			// the link into a second full-phrase string.
			$others_label = sprintf(
				/* translators: %s: Number of additional people who favorited. */
				_n( '%s other', '%s others', $remaining, 'buddypress-favorite-notification' ),
				number_format_i18n( $remaining )
			);

			$others_link = sprintf(
				'<a href="#" class="bpfn-others-count" data-activity-id="%d">%s</a>',
				$activity_id,
				esc_html( $others_label )
			);

			return sprintf(
				/* translators: 1: Comma-separated links to the people who favorited, 2: Link reading "N others". */
				__( '%1$s and %2$s', 'buddypress-favorite-notification' ),
				implode( ', ', $names ),
				$others_link
			);
		}

		$last = array_pop( $names );
		if ( ! empty( $names ) ) {
			return sprintf(
				/* translators: 1: Comma-separated links to the people who favorited, 2: Link to the last person who favorited. */
				__( '%1$s and %2$s', 'buddypress-favorite-notification' ),
				implode( ', ', $names ),
				$last
			);
		}

		return $last;
	}

	/**
	 * Get the registered display modes.
	 *
	 * @return array Mode slug => human label.
	 */
	public static function get_display_modes() {
		/**
		 * Filter the available favorite display modes.
		 *
		 * @since 2.1.0
		 *
		 * @param array $modes Mode slug => label.
		 */
		return apply_filters(
			'bpfn_display_modes',
			array(
				'inline'  => __( 'Inline usernames', 'buddypress-favorite-notification' ),
				'counter' => __( 'Icon and count only', 'buddypress-favorite-notification' ),
				'modal'   => __( 'Icon and count, opens the full list', 'buddypress-favorite-notification' ),
			)
		);
	}

	/**
	 * Get the registered icon choices.
	 *
	 * Values are HTML entities rather than literal glyphs so the source file
	 * stays ASCII-safe and the entity survives the translation pipeline.
	 *
	 * @return array Icon slug => array( label, entity ).
	 */
	public static function get_icon_choices() {
		/**
		 * Filter the selectable favorite icons.
		 *
		 * @since 2.1.0
		 *
		 * @param array $icons Icon slug => array( 'label' => string, 'entity' => string ).
		 */
		return apply_filters(
			'bpfn_favorite_icons',
			array(
				'heart'    => array(
					'label'  => __( 'Heart', 'buddypress-favorite-notification' ),
					'entity' => '&#10084;',
				),
				'star'     => array(
					'label'  => __( 'Star', 'buddypress-favorite-notification' ),
					'entity' => '&#9733;',
				),
				'bookmark' => array(
					'label'  => __( 'Bookmark', 'buddypress-favorite-notification' ),
					'entity' => '&#128278;',
				),
				'thumb'    => array(
					'label'  => __( 'Thumbs up', 'buddypress-favorite-notification' ),
					'entity' => '&#128077;',
				),
				'none'     => array(
					'label'  => __( 'No icon', 'buddypress-favorite-notification' ),
					'entity' => '',
				),
			)
		);
	}

	/**
	 * Resolve the display mode for an activity.
	 *
	 * @param int   $activity_id The activity ID.
	 * @param int   $count       Favorite count.
	 * @param array $users_data  Users data array.
	 * @return string One of the registered mode slugs.
	 */
	public function get_display_mode( $activity_id, $count = 0, $users_data = array() ) {
		$mode  = get_option( 'bpfn_display_mode', 'inline' );
		$modes = self::get_display_modes();

		if ( ! isset( $modes[ $mode ] ) ) {
			$mode = 'inline';
		}

		/**
		 * Filter the favorite display format for a single activity.
		 *
		 * @since 2.1.0
		 *
		 * @param string $mode        Current mode: 'inline', 'counter' or 'modal'.
		 * @param int    $activity_id The activity ID.
		 * @param int    $count       Favorite count.
		 * @param array  $users_data  Users who favorited.
		 */
		$mode = apply_filters( 'bpfn_favorite_display_format', $mode, $activity_id, $count, $users_data );

		return isset( $modes[ $mode ] ) ? $mode : 'inline';
	}

	/**
	 * Get the icon markup for the display.
	 *
	 * @param int $activity_id The activity ID.
	 * @param int $count       Favorite count.
	 * @return string Icon HTML, or empty string when the icon is disabled.
	 */
	public function get_icon_html( $activity_id, $count = 0 ) {
		$icons  = self::get_icon_choices();
		$choice = get_option( 'bpfn_favorite_icon', 'heart' );

		if ( ! isset( $icons[ $choice ] ) ) {
			$choice = 'heart';
		}

		$entity = isset( $icons[ $choice ]['entity'] ) ? $icons[ $choice ]['entity'] : '';

		/**
		 * Filter the favorite icon markup.
		 *
		 * Return an empty string to render no icon at all.
		 *
		 * @since 2.1.0
		 *
		 * @param string $entity      Icon HTML entity.
		 * @param int    $activity_id The activity ID.
		 * @param int    $count       Favorite count.
		 */
		$entity = apply_filters( 'bpfn_favorite_icon_html', $entity, $activity_id, $count );

		if ( '' === $entity ) {
			return '';
		}

		// aria-hidden: the icon is decorative, the adjacent text/label carries
		// the meaning for screen readers.
		return '<span class="bpfn-favorite-icon" aria-hidden="true">' . $entity . '</span>';
	}

	/**
	 * Allowed HTML for the rendered display.
	 *
	 * Applied to the final markup — including anything returned by the
	 * `bpfn_favorite_display_html` override — so a third-party filter cannot
	 * inject unescaped markup into the activity stream.
	 *
	 * @return array wp_kses allow list.
	 */
	private function get_allowed_display_html() {
		$attrs = array(
			'href'             => array(),
			'class'            => array(),
			'title'            => array(),
			'type'             => array(),
			'role'             => array(),
			'data-activity-id' => array(),
			'aria-hidden'      => array(),
			'aria-haspopup'    => array(),
			'aria-label'       => array(),
		);

		return array(
			'div'    => $attrs,
			'span'   => $attrs,
			'a'      => $attrs,
			'button' => $attrs,
		);
	}

	/**
	 * Build the favorite display markup for an activity.
	 *
	 * Single source of truth for BOTH the server-side render
	 * (display_favorite_count) and the post-favorite AJAX refresh
	 * (ajax_refresh_favorite_display). These were previously two copies of
	 * the same markup, which is why the icon had to be changed in two places
	 * and why any format change reverted to the old layout the moment a
	 * member clicked like.
	 *
	 * @param int $activity_id The activity ID.
	 * @return string Display HTML, or empty string when there is nothing to show.
	 */
	public function render_display( $activity_id ) {
		$activity_id = (int) $activity_id;
		$count       = $this->get_favorite_count( $activity_id );

		if ( 0 === $count ) {
			return '';
		}

		$mode = $this->get_display_mode( $activity_id, $count );

		// Inline mode needs the first few names; counter/modal only need the
		// count, so skip the user query entirely on those.
		$users_data = ( 'inline' === $mode )
			? $this->get_users_who_favorited( $activity_id, 3 )
			: array(
				'users'     => array(),
				'total'     => $count,
				'remaining' => 0,
			);

		// Re-resolve now that the user data is known, so filters that inspect
		// the liker list see it.
		$mode = $this->get_display_mode( $activity_id, $count, $users_data );

		/**
		 * Filter the entire favorite display markup.
		 *
		 * Return a non-empty string to bypass the default rendering.
		 *
		 * @since 2.1.0
		 *
		 * @param string $html        Empty by default.
		 * @param int    $activity_id The activity ID.
		 * @param int    $count       Favorite count.
		 * @param array  $users_data  Users who favorited.
		 */
		$override = $this->normalize_override(
			apply_filters( 'bpfn_favorite_display_html', '', $activity_id, $count, $users_data )
		);
		if ( '' !== $override ) {
			return wp_kses( $override, $this->get_allowed_display_html() );
		}

		$icon = $this->get_icon_html( $activity_id, $count );

		if ( 'inline' === $mode ) {
			$body = '<span class="bpfn-favorite-text">'
				. $this->format_favorite_text( $users_data, $activity_id )
				. '</span>';
		} else {
			$body = $this->render_counter( $activity_id, $count, $mode, $icon );
			// The counter markup carries its own icon so it sits inside the
			// button hit area; don't emit it a second time alongside.
			$icon = '';
		}

		$html = sprintf(
			'<div class="bpfn-favorite-display bpfn-favorite-display--%1$s" data-activity-id="%2$d">%3$s%4$s</div>',
			esc_attr( $mode ),
			$activity_id,
			$icon,
			$body
		);

		return wp_kses( $html, $this->get_allowed_display_html() );
	}

	/**
	 * Coerce a display-HTML filter return value to a usable string.
	 *
	 * `bpfn_favorite_display_html` is public API, so a third-party callback
	 * can return anything. Anything that is not a string is discarded rather
	 * than handed to wp_kses(), which would fatal on an array. Deliberately
	 * untyped: the point is to accept whatever a filter actually returned.
	 *
	 * @param mixed $override Raw filter return value.
	 * @return string Override markup, or '' to fall through to default rendering.
	 */
	private function normalize_override( $override ) {
		return is_string( $override ) ? $override : '';
	}

	/**
	 * Build the counter markup for 'counter' and 'modal' modes.
	 *
	 * @param int    $activity_id The activity ID.
	 * @param int    $count       Favorite count.
	 * @param string $mode        Display mode.
	 * @param string $icon        Icon HTML (may be empty).
	 * @return string Counter HTML.
	 */
	private function render_counter( $activity_id, $count, $mode, $icon ) {
		// The visible number is the count; the accessible name spells out what
		// it counts, so a screen reader announces "12 people liked this"
		// rather than a bare "12".
		$label = sprintf(
			/* translators: %s: Number of people who favorited. */
			_n( '%s person liked this', '%s people liked this', $count, 'buddypress-favorite-notification' ),
			number_format_i18n( $count )
		);

		$inner = $icon . '<span class="bpfn-favorite-count">' . esc_html( number_format_i18n( $count ) ) . '</span>';

		// 'counter' is display-only. Only 'modal' is interactive, so only
		// 'modal' gets a button — rendering a dead button in counter mode
		// would put a focusable, clickable-looking control in the tab order
		// with nothing behind it.
		if ( 'modal' !== $mode ) {
			// role="img" so the aria-label is actually announced: a bare <span>
			// has no role, and an aria-label on a roleless generic element is
			// not reliably exposed. The role also makes the icon+number read as
			// one unit ("22 people liked this") instead of a stray "22".
			return sprintf(
				'<span class="bpfn-favorite-counter" role="img" aria-label="%s">%s</span>',
				esc_attr( $label ),
				$inner
			);
		}

		return sprintf(
			'<button type="button" class="bpfn-view-all-favorites" data-activity-id="%1$d" aria-haspopup="dialog" aria-label="%2$s">%3$s</button>',
			$activity_id,
			esc_attr( $label ),
			$inner
		);
	}

	/**
	 * Display favorite count on activity.
	 */
	public function display_favorite_count() {
		// Only show to logged-in users.
		if ( ! is_user_logged_in() ) {
			return;
		}

		if ( ! bp_is_active( 'activity' ) ) {
			return;
		}

		$activity_id = bp_get_activity_id();
		if ( ! $activity_id ) {
			return;
		}

		// Render the count once per activity per request, even if more than one
		// supported hook fires for it (theme footer action + core entry action).
		static $rendered = array();
		if ( isset( $rendered[ $activity_id ] ) ) {
			return;
		}
		$rendered[ $activity_id ] = true;

		// Already escaped through wp_kses in render_display().
		echo $this->render_display( $activity_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * AJAX handler to get all favorites for modal.
	 */
	public function ajax_get_all_favorites() {
		check_ajax_referer( 'bpfn-favorite-nonce', 'nonce' );

		$activity_id = isset( $_POST['activity_id'] ) ? absint( $_POST['activity_id'] ) : 0;

		if ( ! $activity_id ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid activity ID', 'buddypress-favorite-notification' ) ) );
		}

		$page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		if ( $page < 1 ) {
			$page = 1;
		}

		/**
		 * Filter how many users each page of the "who favorited" modal loads.
		 *
		 * @since 2.1.0
		 *
		 * @param int $per_page    Users per page. Default 20.
		 * @param int $activity_id The activity ID.
		 */
		$per_page = (int) apply_filters( 'bpfn_favorites_modal_per_page', 20, $activity_id );
		if ( $per_page < 1 ) {
			$per_page = 20;
		}

		/**
		 * Filter a hard ceiling on how many users the modal will ever load.
		 *
		 * Before 2.1.0 this defaulted to 50 and was the modal's only bound:
		 * everything past it was an un-clickable "+N more" line. The modal now
		 * paginates, so the default is 0 (no ceiling) and members can page
		 * through the whole list. A site that returns a positive number here
		 * still gets that as a maximum, with the remainder shown as "+N more".
		 *
		 * @param int $ceiling     Maximum users loadable in total. 0 for no limit. Default 0.
		 * @param int $activity_id The activity ID.
		 */
		$ceiling = (int) apply_filters( 'bpfn_who_favorited_limit', 0, $activity_id );
		if ( $ceiling < 0 ) {
			$ceiling = 0;
		}

		$offset = ( $page - 1 ) * $per_page;
		$limit  = $per_page;

		// Trim the final page so it never reads past a configured ceiling.
		if ( $ceiling > 0 ) {
			$limit = min( $per_page, max( 0, $ceiling - $offset ) );
		}

		$users_data = ( $limit > 0 )
			? $this->get_users_who_favorited( $activity_id, $limit, $offset )
			: array(
				'users'     => array(),
				'total'     => $this->get_favorite_count( $activity_id ),
				'remaining' => 0,
			);

		$total     = (int) $users_data['total'];
		$reachable = ( $ceiling > 0 ) ? min( $total, $ceiling ) : $total;
		$loaded    = $offset + count( $users_data['users'] );
		$has_more  = $loaded < $reachable;

		$items = $this->render_modal_items( $users_data['users'] );

		// Subsequent pages return list items only — the JS appends them into
		// the open modal rather than re-rendering (and re-scrolling) it.
		if ( $page > 1 ) {
			wp_send_json_success(
				array(
					'items'    => $items,
					'page'     => $page,
					'total'    => $total,
					'has_more' => $has_more,
				)
			);
		}

		ob_start();
		?>
		<div class="bpfn-favorites-modal-content">
			<h3>
				<?php
				printf(
					/* translators: %s: Number of likes. */
					esc_html( _n( '%s Like', '%s Likes', $total, 'buddypress-favorite-notification' ) ),
					esc_html( number_format_i18n( $total ) )
				);
				?>
			</h3>
			<ul class="bpfn-favorites-user-list">
				<?php
				// Escaped per field in render_modal_items().
				echo $items; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</ul>
			<?php if ( $has_more ) : ?>
				<div class="bpfn-favorites-pagination">
					<button type="button"
						class="bpfn-load-more-favorites"
						data-activity-id="<?php echo esc_attr( (string) $activity_id ); ?>"
						data-next-page="2">
						<?php esc_html_e( 'Load more', 'buddypress-favorite-notification' ); ?>
					</button>
				</div>
			<?php elseif ( $ceiling > 0 && $total > $reachable ) : ?>
				<p class="bpfn-favorites-more">
					<?php
					$bpfn_beyond = $total - $reachable;
					printf(
						/* translators: %s: Number of additional users who favorited. */
						esc_html( _n( '+%s more', '+%s more', $bpfn_beyond, 'buddypress-favorite-notification' ) ),
						esc_html( number_format_i18n( $bpfn_beyond ) )
					);
					?>
				</p>
			<?php endif; ?>
		</div>
		<?php
		$html = ob_get_clean();

		wp_send_json_success(
			array(
				'html'     => $html,
				'items'    => $items,
				'page'     => 1,
				'total'    => $total,
				'has_more' => $has_more,
			)
		);
	}

	/**
	 * Render the modal's user list items.
	 *
	 * @param array $users Users array from get_users_who_favorited().
	 * @return string List item HTML.
	 */
	private function render_modal_items( $users ) {
		if ( empty( $users ) ) {
			return '';
		}

		ob_start();
		foreach ( $users as $user ) :
			?>
			<li class="bpfn-favorite-user-item">
				<a href="<?php echo esc_url( $user['link'] ); ?>">
					<img src="<?php echo esc_url( $user['avatar'] ); ?>"
						alt=""
						class="bpfn-user-avatar"
						width="40"
						height="40"
						loading="lazy">
					<span class="bpfn-user-name"><?php echo esc_html( $user['name'] ); ?></span>
				</a>
			</li>
			<?php
		endforeach;

		return ob_get_clean();
	}

	/**
	 * Get the cache incrementor for an activity.
	 *
	 * Every count/user-list cache key for an activity carries this value, so
	 * bumping it in clear_cache() invalidates ALL of that activity's entries
	 * at once — whatever limit/offset combination produced them.
	 *
	 * This replaces the previous approach of deleting a hand-maintained list
	 * of key shapes (the first-3 loop, the modal limit, a legacy 999 key).
	 * That list could only ever cover the limits someone remembered to add:
	 * the moment the modal paginated with arbitrary offsets, every page past
	 * the first would have survived a like and served a stale list for the
	 * full 5-minute TTL.
	 *
	 * @param int $activity_id The activity ID.
	 * @return string Incrementor value.
	 */
	private function get_cache_incrementor( $activity_id ) {
		$key = 'inc_' . $activity_id;
		$inc = wp_cache_get( $key, $this->cache_group );

		if ( false === $inc ) {
			$inc = (string) round( microtime( true ) * 10000 );
			// No expiry: the incrementor must outlive the entries it versions.
			wp_cache_set( $key, $inc, $this->cache_group );
		}

		return (string) $inc;
	}

	/**
	 * Clear cache for activity.
	 *
	 * @param int $activity_id The activity ID.
	 */
	private function clear_cache( $activity_id ) {
		// Bump the incrementor — every key built from it is now unreachable.
		// microtime (not time) so two favorites in the same second still
		// produce distinct versions.
		wp_cache_set(
			'inc_' . $activity_id,
			(string) round( microtime( true ) * 10000 ),
			$this->cache_group
		);

		// Invalidate the admin dashboard stats transient so trending / recent
		// counts reflect this favorite change on the next dashboard load.
		delete_transient( 'bpfn_dashboard_stats' );
	}

	/**
	 * AJAX handler to refresh favorite display.
	 */
	public function ajax_refresh_favorite_display() {
		check_ajax_referer( 'bpfn-favorite-nonce', 'nonce' );

		$activity_id = isset( $_POST['activity_id'] ) ? absint( $_POST['activity_id'] ) : 0;

		if ( ! $activity_id ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid activity ID', 'buddypress-favorite-notification' ) ) );
		}

		$count = $this->get_favorite_count( $activity_id );

		if ( 0 === $count ) {
			wp_send_json_success(
				array(
					'count' => 0,
					'html'  => '',
				)
			);
			return;
		}

		// Same renderer as the server-side pass, so a refresh cannot fall back
		// to a different icon or format than the page was rendered with.
		wp_send_json_success(
			array(
				'count' => $count,
				'html'  => $this->render_display( $activity_id ),
			)
		);
	}

	/**
	 * Get table name.
	 *
	 * @return string Table name.
	 */
	public function get_table_name() {
		return $this->table_name;
	}
}
