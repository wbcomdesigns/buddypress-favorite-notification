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
	private $slug = 'notifications';

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
				'parent_url'      => trailingslashit( bp_displayed_user_domain() . bp_get_settings_slug() ),
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

		// Get notification types.
		$notification_types = $this->get_notification_types();

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

		// Process form data.
		foreach ( $notification_types as $type => $config ) {
			$settings[ $type ] = array(
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Checkbox presence check only.
				'is_enabled'       => isset( $_POST['bpfn'][ $type ]['web'] ) ? 1 : 0,
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Checkbox presence check only.
				'email_enabled'    => isset( $_POST['bpfn'][ $type ]['email'] ) ? 1 : 0,
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Checkbox presence check only.
				'realtime_enabled' => isset( $_POST['bpfn'][ $type ]['realtime'] ) ? 1 : 0,
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
		bp_core_redirect( bp_displayed_user_domain() . bp_get_settings_slug() . '/' . $this->slug . '/' );
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
						<input type="radio" name="notifications[favorite_activity]" value="yes" <?php checked( $settings['activity_post']['email_enabled'], 1 ); ?> />
						<label class="bp-screen-reader-text" for="notification-favorite-activity-yes">
							<?php esc_html_e( 'Yes, send email', 'buddypress-favorite-notification' ); ?>
						</label>
					</td>
					<td class="no">
						<input type="radio" name="notifications[favorite_activity]" value="no" <?php checked( $settings['activity_post']['email_enabled'], 0 ); ?> />
						<label class="bp-screen-reader-text" for="notification-favorite-activity-no">
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
