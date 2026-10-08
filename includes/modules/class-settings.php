<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- Legacy file name.
/**
 * Settings Module for BuddyPress Favorite Notification.
 *
 * @package BuddyPress_Favorite_Notification
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings Module Class.
 */
// phpcs:ignore Squiz.Commenting.ClassComment.Missing -- Class docblock is above.
class BPFN_Module_Settings {

	/**
	 * Settings slug.
	 *
	 * @var string
	 */
	private $slug = 'favorite-notifications'; // Must differ from BP's own Email tab ('notifications').

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Setup hooks.
	 */
	private function setup_hooks() {
		// Add settings navigation (BP member Settings > Favorite Notifications).
		add_action( 'bp_setup_nav', array( $this, 'setup_nav' ), 100 );

		// Handle per-user settings save.
		add_action( 'bp_actions', array( $this, 'handle_settings_save' ) );

		// Add to BP notification settings table.
		add_action( 'bp_notification_settings', array( $this, 'notification_settings' ) );

		// BuddyPress writes our email rows (Settings > Notifications) and our email
		// unsubscribe links to user meta. Mirror both into the prefs table.
		add_action( 'added_user_meta', array( $this, 'mirror_email_meta' ), 10, 4 );
		add_action( 'updated_user_meta', array( $this, 'mirror_email_meta' ), 10, 4 );

		// NOTE: This module no longer registers an admin options page or the
		// `bpfn_options` Settings API option. Those are owned solely by
		// BPFN_Admin (includes/admin/class-bpfn-admin.php) as of 2.0.0. This
		// class is now FRONT-END ONLY (per-user notification preferences).

		// Custom hooks.
		do_action( 'bpfn_settings_setup_hooks', $this );
	}

	/**
	 * Setup BuddyPress navigation.
	 */
	public function setup_nav() {
		if ( ! bp_is_active( 'settings' ) || ! is_user_logged_in() ) {
			return;
		}

		// Add sub-nav item under Settings.
		bp_core_new_subnav_item(
			array(
				'name'            => esc_html__( 'Favorite Notifications', 'buddypress-favorite-notification' ),
				'slug'            => $this->slug,
				'parent_url'      => bp_displayed_user_url( bp_members_get_path_chunks( array( bp_get_settings_slug() ) ) ),
				'parent_slug'     => bp_get_settings_slug(),
				'screen_function' => array( $this, 'settings_screen' ),
				'position'        => 30,
				'user_has_access' => bp_core_can_edit_settings(),
			)
		);
	}

	/**
	 * Settings screen.
	 */
	public function settings_screen() {
		// Check access.
		if ( ! bp_is_my_profile() && ! bp_current_user_can( 'bp_moderate' ) ) {
			return;
		}

		// Add title and content.
		add_action( 'bp_template_title', array( $this, 'settings_screen_title' ) );
		add_action( 'bp_template_content', array( $this, 'settings_screen_content' ) );

		// Load template.
		bp_core_load_template( apply_filters( 'bp_core_template_plugin', 'members/single/plugins' ) );
	}

	/**
	 * Settings screen title.
	 */
	public function settings_screen_title() {
		echo '<h2 class="bp-screen-title">' . esc_html__( 'Favorite Notification Settings', 'buddypress-favorite-notification' ) . '</h2>';
	}

	/**
	 * Settings screen content.
	 */
	public function settings_screen_content() {
		$user_id  = bp_displayed_user_id();
		$settings = bpfn_get_user_settings( $user_id );

		$notification_types = $this->get_notification_types();
		$show_realtime      = BPFN_Module_Realtime::is_enabled();

		// Load settings template.
		include BPFN_TEMPLATES_PATH . 'settings/notifications.php';
	}

	/**
	 * Handle settings save.
	 */
	public function handle_settings_save() {
		if ( ! bp_is_settings_component() || ! bp_is_current_action( $this->slug ) ) {
			return;
		}

		if ( ! isset( $_POST['bpfn_save_settings'] ) ) {
			return;
		}

		// Check nonce.
		check_admin_referer( 'bpfn_settings_nonce' );

		// Check permissions.
		if ( ! bp_is_my_profile() && ! bp_current_user_can( 'bp_moderate' ) ) {
			return;
		}

		$user_id  = bp_displayed_user_id();
		$settings = array();

		// Get notification types.
		$notification_types = $this->get_notification_types();

		$current     = bpfn_get_user_settings( $user_id );
		$realtime_on = BPFN_Module_Realtime::is_enabled();

		foreach ( $notification_types as $type => $config ) {
			$settings[ $type ] = array(
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Checkbox presence check only.
				'is_enabled'       => isset( $_POST['bpfn'][ $type ]['web'] ) ? 1 : 0,
				// Email lives in BuddyPress's Email tab (notification_settings()), not here.
				'email_enabled'    => $current[ $type ]['email_enabled'],
				// The column is hidden while the owner has real-time off; keep the stored
				// choice instead of reading the absent checkbox as "off".
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Checkbox presence check only.
				'realtime_enabled' => $realtime_on ? ( isset( $_POST['bpfn'][ $type ]['realtime'] ) ? 1 : 0 ) : $current[ $type ]['realtime_enabled'],
			);
		}

		// Save settings.
		$saved = bpfn_save_user_settings( $user_id, $settings );

		// Add feedback message.
		if ( $saved ) {
			bp_core_add_message( esc_html__( 'Settings saved successfully.', 'buddypress-favorite-notification' ) );
		} else {
			bp_core_add_message( esc_html__( 'There was a problem saving your settings.', 'buddypress-favorite-notification' ), 'error' );
		}

		// Redirect to prevent resubmission.
		bp_core_redirect( bp_displayed_user_url( bp_members_get_path_chunks( array( bp_get_settings_slug(), $this->slug ) ) ) );
	}

	/**
	 * Mirror BuddyPress's email meta into the prefs table.
	 *
	 * Fires for both the Settings > Notifications rows (BP core saves each posted
	 * `notifications[key]` as user meta) and BP email unsubscribe links (which write
	 * the key from bp_email_get_unsubscribe_type_schema). Only the email channel of
	 * the matching type changes; web and real-time choices are kept.
	 *
	 * @param int    $meta_id    Meta ID.
	 * @param int    $user_id    User ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 */
	public function mirror_email_meta( $meta_id, $user_id, $meta_key, $meta_value ) {
		$type = array_search( $meta_key, bpfn_email_meta_keys(), true );
		if ( false === $type ) {
			return;
		}

		$settings = bpfn_get_user_settings( $user_id );
		$enabled  = 'no' === $meta_value ? 0 : 1;
		if ( (int) $settings[ $type ]['email_enabled'] === $enabled ) {
			return; // Already in step - also stops the write-back in bpfn_save_user_settings() looping.
		}

		$settings[ $type ]['email_enabled'] = $enabled;
		bpfn_save_user_settings( $user_id, array( $type => $settings[ $type ] ) );
	}

	/**
	 * Add to BP notification settings.
	 */
	public function notification_settings() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$user_id  = bp_displayed_user_id();
		$settings = bpfn_get_user_settings( $user_id );

		?>
		<table class="notification-settings" id="favorite-notification-settings">
			<thead>
				<tr>
					<th class="icon"></th>
					<th class="title"><?php esc_html_e( 'Favorites', 'buddypress-favorite-notification' ); ?></th>
					<th class="yes"><?php esc_html_e( 'Yes', 'buddypress-favorite-notification' ); ?></th>
					<th class="no"><?php esc_html_e( 'No', 'buddypress-favorite-notification' ); ?></th>
				</tr>
			</thead>

			<tbody>
				<tr id="favorite-notification-settings-activity">
					<td></td>
					<td><?php esc_html_e( 'A member favorites your activity', 'buddypress-favorite-notification' ); ?></td>
					<td class="yes">
						<input type="radio" id="notification-favorite-activity-yes" name="notifications[favorite_activity]" value="yes" <?php checked( $settings['activity_post']['email_enabled'], 1 ); ?> />
						<label class="bp-screen-reader-text" for="notification-favorite-activity-yes">
							<?php esc_html_e( 'Yes, send email', 'buddypress-favorite-notification' ); ?>
						</label>
					</td>
					<td class="no">
						<input type="radio" id="notification-favorite-activity-no" name="notifications[favorite_activity]" value="no" <?php checked( $settings['activity_post']['email_enabled'], 0 ); ?> />
						<label class="bp-screen-reader-text" for="notification-favorite-activity-no">
							<?php esc_html_e( 'No, do not send email', 'buddypress-favorite-notification' ); ?>
						</label>
					</td>
				</tr>

				<tr id="favorite-notification-settings-comment">
					<td></td>
					<td><?php esc_html_e( 'A member favorites your comment', 'buddypress-favorite-notification' ); ?></td>
					<td class="yes">
						<input type="radio" id="notification-favorite-comment-yes" name="notifications[favorite_activity_comment]" value="yes" <?php checked( $settings['activity_comment']['email_enabled'], 1 ); ?> />
						<label class="bp-screen-reader-text" for="notification-favorite-comment-yes">
							<?php esc_html_e( 'Yes, send email', 'buddypress-favorite-notification' ); ?>
						</label>
					</td>
					<td class="no">
						<input type="radio" id="notification-favorite-comment-no" name="notifications[favorite_activity_comment]" value="no" <?php checked( $settings['activity_comment']['email_enabled'], 0 ); ?> />
						<label class="bp-screen-reader-text" for="notification-favorite-comment-no">
							<?php esc_html_e( 'No, do not send email', 'buddypress-favorite-notification' ); ?>
						</label>
					</td>
				</tr>

				<?php do_action( 'bpfn_notification_settings' ); ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Get notification types for settings.
	 *
	 * @return array Notification types.
	 */
	private function get_notification_types() {
		return apply_filters(
			'bpfn_settings_notification_types',
			array(
				'activity_post'    => array(
					'label'       => esc_html__( 'Activity post favorites', 'buddypress-favorite-notification' ),
					'description' => esc_html__( 'Notify me when someone favorites my activity posts', 'buddypress-favorite-notification' ),
				),
				'activity_comment' => array(
					'label'       => esc_html__( 'Comment favorites', 'buddypress-favorite-notification' ),
					'description' => esc_html__( 'Notify me when someone favorites my comments', 'buddypress-favorite-notification' ),
				),
			)
		);
	}
}
