<?php
/**
 * BuddyPress Favorite Notification.
 *
 * @since    1.0.0
 * @author   Wbcom Designs
 * @package  BuddyPress_Favorite_Notification
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

	add_action( 'bp_setup_globals', 'favorite_notifier_setup_globals' );

	/**
	 * Setup new global notification object for the menu
	 *
	 * @since 1.0.0
	 * @author   Wbcom Designs
	 */
function favorite_notifier_setup_globals() {
	global $bp;
	$bp->favorite_notifier                        = new stdClass();
	$bp->favorite_notifier->id                    = 'favorite_notifier'; // I asume others are not going to use this is.
	$bp->favorite_notifier->slug                  = 'favorite_notification';
	$bp->favorite_notifier->notification_callback = 'favorite_notifier_format_notifications'; // show the notification.
	/* Register this in the active components array */
	$bp->active_components[ $bp->favorite_notifier->id ] = $bp->favorite_notifier->id;
	do_action( 'favorite_notifier_setup_globals' );
}

	add_action( 'bp_activity_add_user_favorite', 'add_notification_mark_fav', 0, 2 );
    

/**
 * Add the notification on marking activity as favorite use "bp_activity_add_user_favorite" hook
 *
 * @since 1.0.0
 * @param string $activity_id The activity id.
 * @param int    $user_id The user id.
 * @author   Wbcom Designs
 */
function add_notification_mark_fav( $activity_id, $user_id ) {
	global $bp;
    $original_activity = new BP_Activity_Activity($activity_id);
    $activity_type = bpfn_get_activity_type($activity_id);
    $settings = bpfn_get_notification_settings($original_activity->user_id);
    // Only continue if the activity author is not the same as the user who favorited
    if ($original_activity->user_id !== $user_id) {
        
        if($activity_type == 'activity_comment' && $settings[$activity_type]['is_enabled']){
             // Add standard BP notification
            if (bp_is_active('notifications')) {
                $arg = array(
                    'user_id'           => $original_activity->user_id,
                    'item_id'           => $activity_id,
                    'secondary_item_id' => $user_id,
                    'component_name'    => $bp->favorite_notifier->id,
                    'component_action'  => 'fav_notify_' . $activity_id,
                    'date_notified'     => bp_core_current_time(),
                    'is_new'            => 1,
                );
                bp_notifications_add_notification($arg);
                // Send email notification
                bpfn_send_email_notification($activity_id, $user_id, $original_activity->user_id);
            }

        }elseif($activity_type == 'activity_post' && $settings[$activity_type]['is_enabled']){
             // Add standard BP notification
            if (bp_is_active('notifications')) {
                $arg = array(
                    'user_id'           => $original_activity->user_id,
                    'item_id'           => $activity_id,
                    'secondary_item_id' => $user_id,
                    'component_name'    => $bp->favorite_notifier->id,
                    'component_action'  => 'fav_notify_' . $activity_id,
                    'date_notified'     => bp_core_current_time(),
                    'is_new'            => 1,
                );
                bp_notifications_add_notification($arg);
                // Send email notification
                bpfn_send_email_notification($activity_id, $user_id, $original_activity->user_id);
            }

        }else{
             bpfn_send_email_notification($activity_id, $user_id, $original_activity->user_id);
        }
    }
}

/**
 * Function to display text and link in the top notification and in the notification area
 *
 * @since 1.0.0
 * @param string $action Action.
 * @param int    $item_id Item id.
 * @param int    $secondary_item_id Secondary Item id.
 * @param int    $total_items Total items.
 * @param string $format Format.
 * @author   Wbcom Designs
 */
function favorite_notifier_format_notifications( $action, $item_id, $secondary_item_id, $total_items, $format = 'string' ) {
	global $bp;
	// Get activity information
    $activity = new BP_Activity_Activity($item_id);
    if (empty($activity->id)) {
        return false;
    }
	$link      = bp_activity_get_permalink( $item_id );
	$amount    = 'single';
	$ac_action = 'fav_notify_' . $item_id;
	if ( $action === $ac_action ) {
        $activity_type = bpfn_get_activity_type($activity->id);
        if($activity_type == 'activity_post'){
		 // Multiple users case
            if ((int) $total_items > 1) {
                /* translators: %s: Number of members who favorited */
                $text = sprintf(__('%1$d members favorited your activity', 'bp-fav-notification'), (int) $total_items);
                $amount = 'multiple';
                
                // Format for string or array output
                if ('string' === $format) {
                    return apply_filters(
                        'bp_favorite_' . $amount . '_' . $ac_action . 's_notification',
                        '<a href="' . $link . '" title="' . __('Activity favorited', 'bp-fav-notification') . '">' . $text . '</a>',
                        $link,
                        $total_items,
                        $text,
                        $item_id,
                        $secondary_item_id
                    );
                } else {
                    // Enhanced content for array format
                    $activity_excerpt = wp_trim_words(wp_strip_all_tags($activity->content), 10, '...');
                    
                    return apply_filters(
                        'bp_favorite_' . $amount . '_' . $ac_action . '_notification',
                        array(
                            'link' => $link,
                            'text' => $text,
                            'activity_excerpt' => $activity_excerpt,
                            'activity_type' => $activity->type,
                            'timestamp' => strtotime($activity->date_recorded),
                            'notification_type' => 'favorite',
                        ),
                        $link,
                        $total_items,
                        $text,
                        $item_id,
                        $secondary_item_id
                    );
                }
            } else {
                // Single user case
                $user_fullname = bp_core_get_user_displayname($secondary_item_id);
                $user_link = bp_core_get_user_domain($secondary_item_id);
                
                /* translators: %s: User name */
                $text = sprintf(__('%s favorited your activity', 'bp-fav-notification'), $user_fullname);
                
                // Format for string or array output
                if ('string' === $format) {
                    return apply_filters(
                        'bp_favorite_' . $amount . '_' . $ac_action . 's_notification',
                        '<a href="' . $link . '" title="' . __('Activity favorited', 'bp-fav-notification') . '">' . $text . '</a>',
                        $link,
                        $total_items,
                        $text,
                        $item_id,
                        $secondary_item_id
                    );
                } else {
                    // Get user avatar
                    $avatar = bp_core_fetch_avatar(array(
                        'item_id' => $secondary_item_id,
                        'type' => 'thumb',
                        'width' => 50,
                        'height' => 50,
                        'html' => true,
                    ));
                    
                    // Get activity excerpt
                    $activity_excerpt = wp_trim_words(wp_strip_all_tags($activity->content), 10, '...');
                    
                    // Enhanced content for array format
                    return apply_filters(
                        'bp_favorite_' . $amount . '_' . $ac_action . '_notification',
                        array(
                            'link' => $link,
                            'text' => $text,
                            'user_link' => $user_link,
                            'user_name' => $user_fullname,
                            'user_avatar' => $avatar,
                            'activity_excerpt' => $activity_excerpt,
                            'activity_type' => $activity->type,
                            'timestamp' => strtotime($activity->date_recorded),
                            'notification_type' => 'favorite',
                        ),
                        $link,
                        $total_items,
                        $text,
                        $item_id,
                        $secondary_item_id
                    );
                }
            }
        }elseif($activity_type == 'activity_comment'){
            // Multiple users case
            if ((int) $total_items > 1) {
                /* translators: %s: Number of members who favorited */
                $text = sprintf(__('%1$d members favorited your comment', 'bp-fav-notification'), (int) $total_items);
                $amount = 'multiple';
                
                // Format for string or array output
                if ('string' === $format) {
                    return apply_filters(
                        'bp_favorite_' . $amount . '_' . $ac_action . 's_notification',
                        '<a href="' . $link . '" title="' . __('Comment favorited', 'bp-fav-notification') . '">' . $text . '</a>',
                        $link,
                        $total_items,
                        $text,
                        $item_id,
                        $secondary_item_id
                    );
                } else {
                    // Enhanced content for array format
                    $activity_excerpt = wp_trim_words(wp_strip_all_tags($activity->content), 10, '...');
                    
                    return apply_filters(
                        'bp_favorite_' . $amount . '_' . $ac_action . '_notification',
                        array(
                            'link' => $link,
                            'text' => $text,
                            'activity_excerpt' => $activity_excerpt,
                            'activity_type' => $activity->type,
                            'timestamp' => strtotime($activity->date_recorded),
                            'notification_type' => 'favorite',
                        ),
                        $link,
                        $total_items,
                        $text,
                        $item_id,
                        $secondary_item_id
                    );
                }
            } else {
                // Single user case
                $user_fullname = bp_core_get_user_displayname($secondary_item_id);
                $user_link = bp_core_get_user_domain($secondary_item_id);
                
                /* translators: %s: User name */
                $text = sprintf(__('%s favorited your comment', 'bp-fav-notification'), $user_fullname);
                
                // Format for string or array output
                if ('string' === $format) {
                    return apply_filters(
                        'bp_favorite_' . $amount . '_' . $ac_action . 's_notification',
                        '<a href="' . $link . '" title="' . __('Comment favorited', 'bp-fav-notification') . '">' . $text . '</a>',
                        $link,
                        $total_items,
                        $text,
                        $item_id,
                        $secondary_item_id
                    );
                } else {
                    // Get user avatar
                    $avatar = bp_core_fetch_avatar(array(
                        'item_id' => $secondary_item_id,
                        'type' => 'thumb',
                        'width' => 50,
                        'height' => 50,
                        'html' => true,
                    ));
                    
                    // Get activity excerpt
                    $activity_excerpt = wp_trim_words(wp_strip_all_tags($activity->content), 10, '...');
                    
                    // Enhanced content for array format
                    return apply_filters(
                        'bp_favorite_' . $amount . '_' . $ac_action . '_notification',
                        array(
                            'link' => $link,
                            'text' => $text,
                            'user_link' => $user_link,
                            'user_name' => $user_fullname,
                            'user_avatar' => $avatar,
                            'activity_excerpt' => $activity_excerpt,
                            'activity_type' => $activity->type,
                            'timestamp' => strtotime($activity->date_recorded),
                            'notification_type' => 'favorite',
                        ),
                        $link,
                        $total_items,
                        $text,
                        $item_id,
                        $secondary_item_id
                    );
                }
            }

        }
	}
	return false;
}

	add_filter('bp_notifications_get_notifications_for_user', 'bpfn_filter_notification_content', 20, 2);
/**
 * Filter the notification content to use our enhanced notifications
 *
 * @param string $content The notification content
 * @param object $notification The notification object
 * @return string The filtered content
 */
function bpfn_filter_notification_content($content, $notification) {
    global $bp;
    
    // Check if this is a favorite notification
    if ($notification->component_name !== $bp->favorite_notifier->id) {
        return $content;
    }
    
    // Get notification data in array format
    $notification_data = favorite_notifier_format_notifications(
        $notification->component_action,
        $notification->item_id,
        $notification->secondary_item_id,
        1,
        'array'
    );
    
    if (empty($notification_data)) {
        return $content;
    }
	if ( ! function_exists( 'bpfn_render_enhanced_notification' ) ) {
		$file = WB_BP_FAV_NOTIFICATION_PLUGIN_PATH . '/include/bpfn-notification-template.php';
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}

    // Render enhanced notification
    $enhanced_content = bpfn_render_enhanced_notification($notification_data);
    
    if ($enhanced_content) {
        return $enhanced_content;
    }
    
    return $content;
}

/**
 * Add Favorite Notifications settings tab to BuddyPress settings
 */
function bpfn_add_settings_nav_tab() {
    if (!bp_is_active('settings')) {
        return;
    }
    
    $settings_link = trailingslashit(bp_loggedin_user_domain() . bp_get_settings_slug());
    
    bp_core_new_subnav_item(array(
        'name'            => __('Favorite Notifications', 'bp-fav-notification'),
        'slug'            => 'favorite-notifications',
        'parent_url'      => $settings_link,
        'parent_slug'     => bp_get_settings_slug(),
        'screen_function' => 'bpfn_settings_screen',
        'position'        => 65,
        'user_has_access' => bp_is_my_profile(),
    ));
}
add_action('bp_setup_nav', 'bpfn_add_settings_nav_tab', 100);


/**
 * Settings screen for favorite notifications
 */
function bpfn_settings_screen() {
    add_action('bp_template_title', 'bpfn_settings_screen_title');
    add_action('bp_template_content', 'bpfn_settings_screen_content');
    bp_core_load_template(apply_filters('bp_core_template_plugin', 'members/single/plugins'));
}

function bpfn_settings_screen_title() {
    echo '<h2>' . __('Favorite Notification Preferences', 'bp-fav-notification') . '</h2>';
}

function bpfn_settings_screen_content() {
    // Check if form was submitted
    if (isset($_POST['bpfn_save_settings'])) {
        // Security check
        check_admin_referer('bpfn_settings_form');
        
        // Process form submission
        bpfn_process_settings_form();
        
        // Show success message
        echo '<div class="bp-feedback success"><span class="bp-icon" aria-hidden="true"></span><p>';
        _e('Your notification preferences have been saved.', 'bp-fav-notification');
        echo '</p></div>';
    }
    
    // Get current settings
    $settings = bpfn_get_notification_settings(get_current_user_id());
    // Display settings form
    ?>
    <form action="" method="post" class="standard-form">
        <table class="notification-settings">
            <thead>
                <tr>
                    <th class="icon"></th>
                    <th class="title"><?php _e('Notification Type', 'bp-fav-notification'); ?></th>
                    <th class="yes"><?php _e('Web', 'bp-fav-notification'); ?></th>
                    <th class="yes"><?php _e('Email', 'bp-fav-notification'); ?></th>
                    <th class="yes"><?php _e('Real-time', 'bp-fav-notification'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><i class="dashicons dashicons-heart"></i></td>
                    <td><?php _e('When someone favorites my activity post', 'bp-fav-notification'); ?></td>
                    <td class="yes">
                        <input type="checkbox" name="bpfn_activity_post_enabled" value="1" <?php checked($settings['activity_post']['is_enabled'], 1); ?>>
                    </td>
                    <td class="yes">
                        <input type="checkbox" name="bpfn_activity_post_email" value="1" <?php checked($settings['activity_post']['email_enabled'], 1); ?>>
                    </td>
                    <td class="yes">
                        <input type="checkbox" name="bpfn_activity_post_realtime" value="1" <?php checked($settings['activity_post']['realtime_enabled'], 1); ?>>
                    </td>
                </tr>
                <tr>
                    <td><i class="dashicons dashicons-heart"></i></td>
                    <td><?php _e('When someone favorites my comment', 'bp-fav-notification'); ?></td>
                    <td class="yes">
                        <input type="checkbox" name="bpfn_activity_comment_enabled" value="1" <?php checked($settings['activity_comment']['is_enabled'], 1); ?>>
                    </td>
                    <td class="yes">
                        <input type="checkbox" name="bpfn_activity_comment_email" value="1" <?php checked($settings['activity_comment']['email_enabled'], 1); ?>>
                    </td>
                    <td class="yes">
                        <input type="checkbox" name="bpfn_activity_comment_realtime" value="1" <?php checked($settings['activity_comment']['realtime_enabled'], 1); ?>>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <?php wp_nonce_field('bpfn_settings_form'); ?>
        <input type="submit" name="bpfn_save_settings" value="<?php esc_attr_e('Save Settings', 'bp-fav-notification'); ?>" class="button">
    </form>
    <?php
}


/**
 * Get notification settings for a user
 *
 * @param int $user_id The user ID
 * @return array The user's notification settings
 */
function bpfn_get_notification_settings($user_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bp_favorite_notification_prefs';
    
    // Default settings
    $default_settings = array(
        'activity_post' => array(
            'is_enabled' => 1,
            'email_enabled' => 1,
            'realtime_enabled' => 1,
        ),
        'activity_comment' => array(
            'is_enabled' => 1,
            'email_enabled' => 1,
            'realtime_enabled' => 1,
        ),
    );
    
    // Get user settings from database
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT notification_type, is_enabled, email_enabled, realtime_enabled 
             FROM $table_name 
             WHERE user_id = %d",
            $user_id
        ),
        ARRAY_A
    );
    
		
    // If no settings are found, return defaults
    if (empty($results)) {
        return $default_settings;
    }
    
    // Build settings array from database results
    $settings = $default_settings;
	// 
    foreach ($results as $row) {
        $type = $row['notification_type'];
        if (isset($settings[$type])) {
            $settings[$type]['is_enabled'] = (int) $row['is_enabled'];
            $settings[$type]['email_enabled'] = (int) $row['email_enabled'];
            $settings[$type]['realtime_enabled'] = (int) $row['realtime_enabled'];
        }
    }
    return $settings;
}


/**
 * Process settings form submission
 */
function bpfn_process_settings_form() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bp_favorite_notification_prefs';
    $user_id = get_current_user_id();
    
    // Activity post settings
    $activity_post_enabled = isset($_POST['bpfn_activity_post_enabled']) ? 1 : 0;
    $activity_post_email = isset($_POST['bpfn_activity_post_email']) ? 1 : 0;
    $activity_post_realtime = isset($_POST['bpfn_activity_post_realtime']) ? 1 : 0;
    
    // Activity comment settings
    $activity_comment_enabled = isset($_POST['bpfn_activity_comment_enabled']) ? 1 : 0;
    $activity_comment_email = isset($_POST['bpfn_activity_comment_email']) ? 1 : 0;
    $activity_comment_realtime = isset($_POST['bpfn_activity_comment_realtime']) ? 1 : 0;
    
    // Update or insert activity post settings
    $wpdb->replace(
        $table_name,
        array(
            'user_id' => $user_id,
            'notification_type' => 'activity_post',
            'is_enabled' => $activity_post_enabled,
            'email_enabled' => $activity_post_email,
            'realtime_enabled' => $activity_post_realtime,
        ),
        array('%d', '%s', '%d', '%d', '%d')
    );
    
    // Update or insert activity comment settings
    $wpdb->replace(
        $table_name,
        array(
            'user_id' => $user_id,
            'notification_type' => 'activity_comment',
            'is_enabled' => $activity_comment_enabled,
            'email_enabled' => $activity_comment_email,
            'realtime_enabled' => $activity_comment_realtime,
        ),
        array('%d', '%s', '%d', '%d', '%d')
    );
}

/**
 * Send email notification for activity favorite
 *
 * @param int $activity_id The activity ID
 * @param int $user_id The user ID who favorited the activity
 * @param int $activity_author_id The activity author ID
 */
function bpfn_send_email_notification($activity_id, $user_id, $activity_author_id) {
    // Check if the author has email notifications enabled for favorites
    $author_settings = bpfn_get_notification_settings($activity_author_id);
    $activity_type = bpfn_get_activity_type($activity_id);
    
    // Exit if email notifications are disabled
    if (!isset($author_settings[$activity_type]) || !$author_settings[$activity_type]['email_enabled']) {
        return;
    }
    
    // Get activity info
    $activity = new BP_Activity_Activity($activity_id);
    if (empty($activity->id)) {
        return;
    }
    
    // Get user info
    $author = get_userdata($activity_author_id);
    $favorited_by_user = get_userdata($user_id);
    
    if (!$author || !$favorited_by_user) {
        return;
    }
    
    // Prepare email data
    $to = $author->user_email;
    if ($activity_type == 'activity_post') {
        $subject = sprintf(__('[%s] %s favorited your activity', 'bp-fav-notification'), 
        get_bloginfo('name'), 
        $favorited_by_user->display_name
    );
    } elseif($activity_type == 'activity_comment') {
        $subject = sprintf(__('[%s] %s favorited your comment', 'bp-fav-notification'), 
        get_bloginfo('name'), 
        $favorited_by_user->display_name
    );
    }
   
    
    // Get activity content and link
    $activity_content = wp_strip_all_tags($activity->content);
    $activity_link = bp_activity_get_permalink($activity_id);
    $settings_link = trailingslashit(bp_core_get_user_domain($activity_author_id) . bp_get_settings_slug()) . 'favorite-notifications';
    
    // Load appropriate email template
    ob_start();
    $user_name = $author->display_name;
    $favorited_by = $favorited_by_user->display_name;
    
    // Determine which template to use based on activity type
    if ($activity_type == 'activity_post') {
        include WB_BP_FAV_NOTIFICATION_PLUGIN_PATH . 'templates/emails/activity-favorited.php';
    } elseif($activity_type == 'activity_comment') {
        include WB_BP_FAV_NOTIFICATION_PLUGIN_PATH . 'templates/emails/comment-favorited.php';
    }
    
    $message = ob_get_clean();
    
    // Set headers
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
    );
    
    // Send email
    wp_mail($to, $subject, $message, $headers);
}


/**
 * Get activity type (post or comment)
 * 
 * @param int $activity_id The activity ID
 * @return string The activity type (activity_post or activity_comment)
 */
function bpfn_get_activity_type($activity_id) {
    $activity = new BP_Activity_Activity($activity_id);
    
    if (empty($activity->id)) {
        return 'activity_post'; // Default
    }
    
    if ($activity->type == 'activity_comment') {
        return 'activity_comment';
    }
    
    return 'activity_post';
}


/**
 * Enqueue scripts for real-time notifications
 */
function bpfn_enqueue_realtime_scripts() {

	wp_enqueue_style(
        'bpfn-realtime-notifications',
        WB_BP_FAV_NOTIFICATION_PLUGIN_URL. 'assets/css/realtime-notifications.css',
        array(),
        WB_BP_FAV_NOTIFICATION_VERSION 
    );
	wp_enqueue_style(
        'bpfn-style-notifications',
        WB_BP_FAV_NOTIFICATION_PLUGIN_URL. 'assets/css/style-notifications.css',
        array(),
        WB_BP_FAV_NOTIFICATION_VERSION
    );
    // Only enqueue for logged-in users
    if (!is_user_logged_in()) {
        return;
    }
    
    // Get user settings
    $settings = bpfn_get_notification_settings(get_current_user_id());
    
    // Check if any real-time notification is enabled
    $realtime_enabled = false;
    foreach ($settings as $type => $options) {
        if ($options['realtime_enabled'] == 1) {
            $realtime_enabled = true;
            break;
        }
    }
    
    // Only enqueue if real-time notifications are enabled
    if (!$realtime_enabled) {
        return;
    }
    
    // Enqueue scripts
    wp_enqueue_script('heartbeat');
    wp_enqueue_script(
        'bpfn-realtime-notifications',
        WB_BP_FAV_NOTIFICATION_PLUGIN_URL . 'assets/js/realtime-notifications.js',
        array('jquery', 'heartbeat'),
        WB_BP_FAV_NOTIFICATION_VERSION,
        true
    );
    
    // Pass data to script
    wp_localize_script(
        'bpfn-realtime-notifications',
        'BPFNRealtimeData',
        array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bpfn_realtime_nonce'),
            'strings' => array(
                'newFavorite' => __('New favorite notification', 'bp-fav-notification'),
                'viewActivity' => __('View Activity', 'bp-fav-notification'),
                'close' => __('Close', 'bp-fav-notification'),
            )
        )
    );
}
add_action('wp_enqueue_scripts', 'bpfn_enqueue_realtime_scripts');


/**
 * Handle heartbeat requests for real-time notifications
 *
 * @param array $response The heartbeat response
 * @param array $data The heartbeat data
 * @return array The modified response
 */
function bpfn_heartbeat_received($response, $data) {
    // Check if this is our request
    if (empty($data['bpfn_realtime_check']) || !is_user_logged_in()) {
        return $response;
    }
    
    // Verify nonce
    if (!isset($data['bpfn_realtime_check']['nonce']) || 
        !wp_verify_nonce($data['bpfn_realtime_check']['nonce'], 'bpfn_realtime_nonce')) {
        return $response;
    }
    
    // Get user settings
    $user_id = get_current_user_id();
    $settings = bpfn_get_notification_settings($user_id);
    
    // Check if any real-time notification is enabled
    $realtime_enabled = false;
    foreach ($settings as $type => $options) {
        if ($options['realtime_enabled'] == 1) {
            $realtime_enabled = true;
            break;
        }
    }
    
    // Return if real-time notifications are disabled
    if (!$realtime_enabled) {
        return $response;
    }
    
    // Get last checked time
    $last_checked = isset($data['bpfn_realtime_check']['last_checked']) 
        ? intval($data['bpfn_realtime_check']['last_checked']) 
        : 0;
    
    // Get new notifications
    $notifications = bpfn_get_new_favorite_notifications($user_id, $last_checked);
    
    // Add notifications to response
    $response['bpfn_realtime_notifications'] = array(
        'notifications' => $notifications,
        'count' => bpfn_get_unread_notification_count($user_id),
    );
    
    return $response;
}
add_filter('heartbeat_received', 'bpfn_heartbeat_received', 10, 2);

/**
 * Get new favorite notifications for real-time display
 *
 * @param int $user_id The user ID
 * @param int $last_checked The timestamp when notifications were last checked
 * @return array New notifications
 */
function bpfn_get_new_favorite_notifications($user_id, $last_checked) {
    global $wpdb, $bp;
    
    // Convert timestamp to MySQL format
    $date_query = date('Y-m-d H:i:s', $last_checked);
    
    // Get new notifications
    $notifications = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$bp->notifications->table_name} 
         WHERE user_id = %d 
         AND component_name = %s 
         AND date_notified > %s 
         AND is_new = 1
         ORDER BY date_notified DESC",
        $user_id,
        $bp->favorite_notifier->id,
        $date_query
    ));
    
    if (empty($notifications)) {
        return array();
    }
    
    $processed_notifications = array();
    
    foreach ($notifications as $notification) {
        // Get notification text
        $text = favorite_notifier_format_notifications(
            $notification->component_action,
            $notification->item_id,
            $notification->secondary_item_id,
            1,
            'array'
        );
        
        if (empty($text)) {
            continue;
        }
        
        // Get user avatar
        $avatar = bp_core_fetch_avatar(array(
            'item_id' => $notification->secondary_item_id,
            'type' => 'thumb',
            'width' => 40,
            'height' => 40,
            'html' => true,
        ));
        
        // Add to processed notifications
        $processed_notifications[] = array(
            'id' => $notification->id,
            'text' => $text['text'],
            'link' => $text['link'],
            'avatar' => $avatar,
            'time' => strtotime($notification->date_notified),
        );
    }
    
    return $processed_notifications;
}


function bpfn_get_unread_notification_count($user_id) {
    if (function_exists('bp_notifications_get_unread_notification_count')) {
        return bp_notifications_get_unread_notification_count($user_id);
    }
    
    return 0;
}
