<?php
/**
 * Settings Module for BuddyPress Favorite Notification
 *
 * @package BuddyPress_Favorite_Notification
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings Module Class
 */
class BPFN_Module_Settings {

	/**
	 * Settings slug
	 */
	private $slug = 'notifications';

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Setup hooks
	 */
	private function setup_hooks() {
		// Add settings navigation
		add_action( 'bp_setup_nav', array( $this, 'setup_nav' ), 100 );
		
		// Handle settings save
		add_action( 'bp_actions', array( $this, 'handle_settings_save' ) );
		
		// Add to notification settings
		add_action( 'bp_notification_settings', array( $this, 'notification_settings' ) );
		
		// Admin settings
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		
		// Custom hooks
		do_action( 'bpfn_settings_setup_hooks', $this );
	}

	/**
	 * Setup BuddyPress navigation
	 */
	public function setup_nav() {
		if ( ! bp_is_active( 'settings' ) || ! is_user_logged_in() ) {
			return;
		}
		
		// Add sub-nav item under Settings
		bp_core_new_subnav_item( array(
			'name'            => __( 'Favorite Notifications', 'bp-fav-notification' ),
			'slug'            => $this->slug,
			'parent_url'      => trailingslashit( bp_displayed_user_domain() . bp_get_settings_slug() ),
			'parent_slug'     => bp_get_settings_slug(),
			'screen_function' => array( $this, 'settings_screen' ),
			'position'        => 30,
			'user_has_access' => bp_core_can_edit_settings(),
		) );
	}

	/**
	 * Settings screen
	 */
	public function settings_screen() {
		// Check access
		if ( ! bp_is_my_profile() && ! bp_current_user_can( 'bp_moderate' ) ) {
			return;
		}
		
		// Add title and content
		add_action( 'bp_template_title', array( $this, 'settings_screen_title' ) );
		add_action( 'bp_template_content', array( $this, 'settings_screen_content' ) );
		
		// Load template
		bp_core_load_template( apply_filters( 'bp_core_template_plugin', 'members/single/plugins' ) );
	}

	/**
	 * Settings screen title
	 */
	public function settings_screen_title() {
		echo '<h2 class="bp-screen-title">' . __( 'Favorite Notification Settings', 'bp-fav-notification' ) . '</h2>';
	}

	/**
	 * Settings screen content
	 */
	public function settings_screen_content() {
		$user_id = bp_displayed_user_id();
		$settings = bpfn_get_user_settings( $user_id );
		
		// Get notification types
		$notification_types = $this->get_notification_types();
		
		// Load settings template
		include BPFN_TEMPLATES_PATH . 'settings/notifications.php';
	}

	/**
	 * Handle settings save
	 */
	public function handle_settings_save() {
		if ( ! bp_is_settings_component() || ! bp_is_current_action( $this->slug ) ) {
			return;
		}
		
		if ( ! isset( $_POST['bpfn_save_settings'] ) ) {
			return;
		}
		
		// Check nonce
		check_admin_referer( 'bpfn_settings_nonce' );
		
		// Check permissions
		if ( ! bp_is_my_profile() && ! bp_current_user_can( 'bp_moderate' ) ) {
			return;
		}
		
		$user_id = bp_displayed_user_id();
		$settings = array();
		
		// Get notification types
		$notification_types = $this->get_notification_types();
		
		// Process form data
		foreach ( $notification_types as $type => $config ) {
			$settings[ $type ] = array(
				'is_enabled' => isset( $_POST['bpfn'][ $type ]['web'] ) ? 1 : 0,
				'email_enabled' => isset( $_POST['bpfn'][ $type ]['email'] ) ? 1 : 0,
				'realtime_enabled' => isset( $_POST['bpfn'][ $type ]['realtime'] ) ? 1 : 0,
			);
		}
		
		// Save settings
		$saved = bpfn_save_user_settings( $user_id, $settings );
		
		// Add feedback message
		if ( $saved ) {
			bp_core_add_message( __( 'Settings saved successfully.', 'bp-fav-notification' ) );
		} else {
			bp_core_add_message( __( 'There was a problem saving your settings.', 'bp-fav-notification' ), 'error' );
		}
		
		// Redirect to prevent resubmission
		bp_core_redirect( bp_displayed_user_domain() . bp_get_settings_slug() . '/' . $this->slug . '/' );
	}

	/**
	 * Add to BP notification settings
	 */
	public function notification_settings() {
		if ( ! is_user_logged_in() ) {
			return;
		}
		
		$user_id = bp_displayed_user_id();
		$settings = bpfn_get_user_settings( $user_id );
		
		?>
		<table class="notification-settings" id="favorite-notification-settings">
			<thead>
				<tr>
					<th class="icon"></th>
					<th class="title"><?php _e( 'Favorites', 'bp-fav-notification' ); ?></th>
					<th class="yes"><?php _e( 'Yes', 'bp-fav-notification' ); ?></th>
					<th class="no"><?php _e( 'No', 'bp-fav-notification' ); ?></th>
				</tr>
			</thead>

			<tbody>
				<tr id="favorite-notification-settings-activity">
					<td></td>
					<td><?php _e( 'A member favorites your activity', 'bp-fav-notification' ); ?></td>
					<td class="yes">
						<input type="radio" name="notifications[favorite_activity]" value="yes" <?php checked( $settings['activity_post']['email_enabled'], 1 ); ?> />
						<label class="bp-screen-reader-text" for="notification-favorite-activity-yes">
							<?php _e( 'Yes, send email', 'bp-fav-notification' ); ?>
						</label>
					</td>
					<td class="no">
						<input type="radio" name="notifications[favorite_activity]" value="no" <?php checked( $settings['activity_post']['email_enabled'], 0 ); ?> />
						<label class="bp-screen-reader-text" for="notification-favorite-activity-no">
							<?php _e( 'No, do not send email', 'bp-fav-notification' ); ?>
						</label>
					</td>
				</tr>

				<?php do_action( 'bpfn_notification_settings' ); ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Get notification types for settings
	 */
	private function get_notification_types() {
		return apply_filters( 'bpfn_settings_notification_types', array(
			'activity_post' => array(
				'label' => __( 'Activity post favorites', 'bp-fav-notification' ),
				'description' => __( 'Notify me when someone favorites my activity posts', 'bp-fav-notification' ),
			),
			'activity_comment' => array(
				'label' => __( 'Comment favorites', 'bp-fav-notification' ),
				'description' => __( 'Notify me when someone favorites my comments', 'bp-fav-notification' ),
			),
		) );
	}

	/**
	 * Admin menu
	 */
	public function admin_menu() {
		add_options_page(
			__( 'BP Favorite Notification', 'bp-fav-notification' ),
			__( 'BP Favorite Notification', 'bp-fav-notification' ),
			'manage_options',
			'bpfn-settings',
			array( $this, 'admin_page' )
		);
	}

	/**
	 * Admin init
	 */
	public function admin_init() {
		register_setting( 'bpfn_settings', 'bpfn_options' );
		
		// Add sections and fields
		add_settings_section(
			'bpfn_general',
			__( 'General Settings', 'bp-fav-notification' ),
			array( $this, 'section_general' ),
			'bpfn-settings'
		);
		
		add_settings_field(
			'enable_enhanced_notifications',
			__( 'Enable Enhanced Notifications', 'bp-fav-notification' ),
			array( $this, 'field_checkbox' ),
			'bpfn-settings',
			'bpfn_general',
			array(
				'name' => 'enable_enhanced_notifications',
				'label' => __( 'Display enhanced notification content with activity excerpts and user avatars', 'bp-fav-notification' ),
			)
		);
		
		do_action( 'bpfn_admin_init_settings' );
	}

	/**
	 * Admin page
	 */
	public function admin_page() {
		?>
		<div class="wrap">
			<h1><?php _e( 'BuddyPress Favorite Notification Settings', 'bp-fav-notification' ); ?></h1>
			
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
	 * General section description
	 */
	public function section_general() {
		echo '<p>' . __( 'Configure global settings for BuddyPress Favorite Notifications.', 'bp-fav-notification' ) . '</p>';
	}

	/**
	 * Checkbox field
	 */
	public function field_checkbox( $args ) {
		$options = get_option( 'bpfn_options', array() );
		$value = isset( $options[ $args['name'] ] ) ? $options[ $args['name'] ] : 0;
		
		?>
		<label>
			<input type="checkbox" name="bpfn_options[<?php echo esc_attr( $args['name'] ); ?>]" value="1" <?php checked( $value, 1 ); ?> />
			<?php echo esc_html( $args['label'] ); ?>
		</label>
		<?php
	}
}