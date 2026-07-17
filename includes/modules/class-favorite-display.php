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

		$cache_key = 'count_' . $activity_id;
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

		$cache_key = 'users_' . $activity_id . '_' . $limit . '_' . $offset;
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

		$count = $this->get_favorite_count( $activity_id );

		if ( 0 === $count ) {
			return; // Don't show anything if no favorites.
		}

		$users_data = $this->get_users_who_favorited( $activity_id, 3 );
		$text       = $this->format_favorite_text( $users_data, $activity_id );
		?>
		<div class="bpfn-favorite-display" data-activity-id="<?php echo esc_attr( $activity_id ); ?>">
			<span class="bpfn-favorite-icon">&#10084;</span>
			<span class="bpfn-favorite-text">
				<?php
				echo wp_kses(
					$text,
					array(
						'a'    => array(
							'href'             => array(),
							'class'            => array(),
							'data-activity-id' => array(),
						),
						'span' => array(
							'class' => array(),
						),
					)
				);
				?>
			</span>
		</div>
		<?php
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

		/**
		 * Filter the maximum number of users loaded into the "who favorited" modal.
		 *
		 * Bounds the fetch instead of pulling every row on a hot activity. The
		 * modal shows up to this many avatars; any beyond it are surfaced as a
		 * "+N more" count derived from the COUNT(*) total.
		 *
		 * @param int $limit       Maximum users to load. Default 50.
		 * @param int $activity_id The activity ID.
		 */
		$modal_limit = (int) apply_filters( 'bpfn_who_favorited_limit', 50, $activity_id );
		if ( $modal_limit < 1 ) {
			$modal_limit = 50;
		}

		$users_data = $this->get_users_who_favorited( $activity_id, $modal_limit );

		ob_start();
		?>
		<div class="bpfn-favorites-modal-content">
			<h3>
				<?php
				printf(
					/* translators: %d: Number of likes. */
					esc_html( _n( '%d Like', '%d Likes', $users_data['total'], 'buddypress-favorite-notification' ) ),
					(int) $users_data['total']
				);
				?>
			</h3>
			<ul class="bpfn-favorites-user-list">
				<?php foreach ( $users_data['users'] as $user ) : ?>
					<li class="bpfn-favorite-user-item">
						<a href="<?php echo esc_url( $user['link'] ); ?>">
							<img src="<?php echo esc_url( $user['avatar'] ); ?>"
								alt="<?php echo esc_attr( $user['name'] ); ?>"
								class="bpfn-user-avatar"
								width="40"
								height="40">
							<span class="bpfn-user-name"><?php echo esc_html( $user['name'] ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php if ( ! empty( $users_data['remaining'] ) ) : ?>
				<p class="bpfn-favorites-more">
					<?php
					printf(
						/* translators: %s: Number of additional users who favorited. */
						esc_html( _n( '+%s more', '+%s more', (int) $users_data['remaining'], 'buddypress-favorite-notification' ) ),
						esc_html( number_format_i18n( (int) $users_data['remaining'] ) )
					);
					?>
				</p>
			<?php endif; ?>
		</div>
		<?php
		$html = ob_get_clean();

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * Clear cache for activity.
	 *
	 * @param int $activity_id The activity ID.
	 */
	private function clear_cache( $activity_id ) {
		wp_cache_delete( 'count_' . $activity_id, $this->cache_group );

		// Clear user list caches (we cache first 3 users).
		for ( $i = 0; $i < 10; $i++ ) {
			wp_cache_delete( 'users_' . $activity_id . '_3_' . $i, $this->cache_group );
		}

		// Clear the bounded "who favorited" modal cache (filterable limit, offset 0).
		$modal_limit = (int) apply_filters( 'bpfn_who_favorited_limit', 50, $activity_id );
		if ( $modal_limit < 1 ) {
			$modal_limit = 50;
		}
		wp_cache_delete( 'users_' . $activity_id . '_' . $modal_limit . '_0', $this->cache_group );

		// Clear legacy full-list cache key from pre-2.0.1 (limit 999) builds.
		wp_cache_delete( 'users_' . $activity_id . '_999_0', $this->cache_group );

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

		// Generate the HTML.
		$users_data = $this->get_users_who_favorited( $activity_id, 3 );
		$text       = $this->format_favorite_text( $users_data, $activity_id );

		ob_start();
		?>
		<div class="bpfn-favorite-display" data-activity-id="<?php echo esc_attr( $activity_id ); ?>">
			<span class="bpfn-favorite-icon">&#10084;</span>
			<span class="bpfn-favorite-text">
				<?php
				echo wp_kses(
					$text,
					array(
						'a'    => array(
							'href'             => array(),
							'class'            => array(),
							'data-activity-id' => array(),
						),
						'span' => array(
							'class' => array(),
						),
					)
				);
				?>
			</span>
		</div>
		<?php
		$html = ob_get_clean();

		wp_send_json_success(
			array(
				'count' => $count,
				'html'  => $html,
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
