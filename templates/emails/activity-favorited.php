<?php
/**
 * Email template for activity favorited notification
 */
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <title><?php echo get_bloginfo('name'); ?></title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background-color: #f6f6f6;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #fff; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1);">
        <div style="text-align: center; margin-bottom: 20px;">
            <h1 style="color: #1d84b5; margin: 0;"><?php echo get_bloginfo('name'); ?></h1>
        </div>
        
        <p><?php echo sprintf(__('Hi %s,', 'bp-fav-notification'), $user_name); ?></p>
        
        <p>
            <?php echo sprintf(
                __('%s favorited your activity: "%s"', 'bp-fav-notification'),
                '<strong>' . $favorited_by . '</strong>',
                '<em>' . wp_trim_words($activity_content, 10, '...') . '</em>'
            ); ?>
        </p>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="<?php echo $activity_link; ?>" style="display: inline-block; background-color: #1d84b5; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
                <?php _e('View Activity', 'bp-fav-notification'); ?>
            </a>
        </div>
        
        <p style="color: #777; font-size: 0.9em; text-align: center; margin-top: 40px;">
            <?php _e('You received this email because you enabled email notifications for activity favorites.', 'bp-fav-notification'); ?>
            <br>
            <a href="<?php echo $settings_link; ?>" style="color: #1d84b5;">
                <?php _e('Change your notification settings', 'bp-fav-notification'); ?>
            </a>
        </p>
    </div>
</body>
</html>