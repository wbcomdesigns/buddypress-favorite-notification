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
		// Add settings navigation.
		add_action( 'bp_setup_nav', array( $this, 'setup_nav' ), 100 );

		// Handle settings save.
		add_action( 'bp_actions', array( $this, 'handle_settings_save' ) );

		// Add to notification settings.
		add_action( 'bp_notification_settings', array( $this, 'notification_settings' ) );

		// Admin settings.
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );

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

	/**
	 * Admin menu.
	 */
	public function admin_menu() {
		add_options_page(
			esc_html__( 'BP Favorite Notification', 'buddypress-favorite-notification' ),
			esc_html__( 'BP Favorite Notification', 'buddypress-favorite-notification' ),
			'manage_options',
			'bpfn-settings',
			array( $this, 'admin_page' )
		);
	}

	/**
	 * Admin init.
	 */
	public function admin_init() {
		register_setting(
			'bpfn_settings',
			'bpfn_options',
			array(
				'sanitize_callback' => array( $this, 'sanitize_options' ),
			)
		);

		// Add sections and fields.
		add_settings_section(
			'bpfn_general',
			esc_html__( 'General Settings', 'buddypress-favorite-notification' ),
			array( $this, 'section_general' ),
			'bpfn-settings'
		);

		add_settings_field(
			'enable_enhanced_notifications',
			esc_html__( 'Enable Enhanced Notifications', 'buddypress-favorite-notification' ),
			array( $this, 'field_checkbox' ),
			'bpfn-settings',
			'bpfn_general',
			array(
				'name'  => 'enable_enhanced_notifications',
				'label' => esc_html__( 'Display enhanced notification content with activity excerpts and user avatars', 'buddypress-favorite-notification' ),
			)
		);

		do_action( 'bpfn_admin_init_settings' );
	}

	/**
	 * Admin page.
	 */
	public function admin_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'BuddyPress Favorite Notification Settings', 'buddypress-favorite-notification' ); ?></h1>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'bpfn_settings' );
				do_settings_sections( 'bpfn-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * General section description.
	 */
	public function section_general() {
		echo '<p>' . esc_html__( 'Configure global settings for BuddyPress Favorite Notifications.', 'buddypress-favorite-notification' ) . '</p>';
	}

	/**
	 * Checkbox field.
	 *
	 * @param array $args Field arguments.
	 */
	public function field_checkbox( $args ) {
		$options = get_option( 'bpfn_options', array() );
		$value   = isset( $options[ $args['name'] ] ) ? $options[ $args['name'] ] : 0;

		?>
		<label>
			<input type="checkbox" name="bpfn_options[<?php echo esc_attr( $args['name'] ); ?>]" value="1" <?php checked( $value, 1 ); ?> />
			<?php echo esc_html( $args['label'] ); ?>
		</label>
		<?php
	}

	/**
	 * Sanitize plugin options.
	 *
	 * @param array $input Raw input options.
	 * @return array Sanitized options.
	 */
	public function sanitize_options( $input ) {
		$sanitized = array();

		if ( isset( $input['enable_enhanced_notifications'] ) ) {
			$sanitized['enable_enhanced_notifications'] = absint( $input['enable_enhanced_notifications'] );
		}

		return apply_filters( 'bpfn_sanitize_options', $sanitized, $input );
	}
}
