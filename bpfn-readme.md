# BuddyPress Favorite Notification Plugin

A highly extendable notification plugin for BuddyPress that sends notifications when activities are favorited.

## 📁 File Structure

```
bp-favorite-notification/
├── bp-favorite-notification.php     # Main plugin file
├── assets/
│   ├── css/
│   │   ├── notifications.css        # Core notification styles
│   │   ├── realtime.css            # Real-time notification styles
│   │   ├── enhanced.css            # Enhanced notification styles
│   │   └── admin.css               # Admin panel styles
│   ├── js/
│   │   ├── notifications.js        # Core notification scripts
│   │   ├── realtime.js            # Real-time notification scripts
│   │   └── admin.js               # Admin panel scripts
│   └── images/
│       └── logo.png               # Plugin logo
├── includes/
│   ├── functions/
│   │   ├── core-functions.php     # Core utility functions
│   │   ├── template-functions.php # Template helper functions
│   │   └── api-functions.php      # Public API functions
│   └── modules/
│       ├── class-notifications.php # Notifications module
│       ├── class-email.php        # Email notifications module
│       ├── class-realtime.php     # Real-time notifications module
│       ├── class-settings.php     # User settings module
│       ├── class-admin.php        # Admin panel module
│       └── class-assets.php       # Asset management module
├── templates/
│   ├── emails/
│   │   ├── base.php              # Base email template
│   │   ├── activity-favorited.php # Activity favorited email
│   │   └── comment-favorited.php  # Comment favorited email
│   ├── notifications/
│   │   └── notification-item.php  # Notification item template
│   └── settings/
│       └── notifications.php      # User notification settings
└── languages/
    └── bp-fav-notification.pot    # Translation template
```

## 🚀 Key Features

### 1. **Modular Architecture**
- Each feature is contained in its own module
- Easy to extend or disable specific features
- Clean separation of concerns

### 2. **Multiple Notification Channels**
- **Web Notifications**: Standard BuddyPress notifications
- **Email Notifications**: Customizable HTML emails
- **Real-time Notifications**: Live updates using WordPress Heartbeat API

### 3. **User Control**
- Per-user notification preferences
- Granular control over notification types and channels
- Settings integrated into BuddyPress user settings

### 4. **Developer Friendly**
- 50+ action and filter hooks
- Comprehensive API functions
- Well-documented code
- Template override system

## 🔧 API Reference

### Actions

```php
// Core actions
do_action( 'bpfn_init', $plugin_instance );
do_action( 'bpfn_after_add_notification', $notification_id, $notification_data, $activity, $user_id );
do_action( 'bpfn_after_send_email', $sent, $email_data );
do_action( 'bpfn_module_registered', $module_name, $module_instance );

// Module-specific actions
do_action( 'bpfn_notifications_setup_hooks', $module );
do_action( 'bpfn_email_setup_hooks', $module );
do_action( 'bpfn_realtime_setup_hooks', $module );
```

### Filters

```php
// Notification filters
apply_filters( 'bpfn_notification_types', $types );
apply_filters( 'bpfn_notification_text', $text, $item_id, $secondary_item_id, $total_items );
apply_filters( 'bpfn_notification_link', $link, $item_id, $secondary_item_id, $total_items );

// Email filters
apply_filters( 'bpfn_email_templates', $templates );
apply_filters( 'bpfn_email_subject', $subject, $tokens );
apply_filters( 'bpfn_email_message', $message, $email_data );

// Settings filters
apply_filters( 'bpfn_default_user_settings', $settings );
apply_filters( 'bpfn_settings_notification_types', $types );
```

### Public Functions

```php
// Register custom notification type
bpfn_register_notification_type( $type, $args );

// Add a notification
bpfn_add_notification( $args );

// Get user notifications
bpfn_get_notifications( $user_id, $args );

// Check if feature is enabled
bpfn_is_feature_enabled( $feature );

// Get/save user settings
bpfn_get_user_settings( $user_id );
bpfn_save_user_settings( $user_id, $settings );
```

## 📝 Examples

### Register a Custom Notification Type

```php
add_action( 'bpfn_init', function() {
    bpfn_register_notification_type( 'custom_like', array(
        'labels' => array(
            'single' => __( '%s liked your post', 'textdomain' ),
            'multiple' => __( '%d people liked your post', 'textdomain' ),
        ),
        'action_prefix' => 'like_notify',
        'icon' => 'dashicons-thumbs-up',
        'settings_label' => __( 'Post likes', 'textdomain' ),
        'settings_description' => __( 'Notify me when someone likes my posts', 'textdomain' ),
    ) );
} );
```

### Customize Notification Text

```php
add_filter( 'bpfn_notification_text', function( $text, $item_id, $secondary_item_id, $total_items ) {
    if ( $total_items > 10 ) {
        return sprintf( __( '🔥 Your activity is on fire! %d people favorited it!', 'textdomain' ), $total_items );
    }
    return $text;
}, 10, 4 );
```

### Add Custom Email Template

```php
add_filter( 'bpfn_email_templates', function( $templates ) {
    $templates['custom_notification'] = array(
        'subject' => __( 'You have a new notification', 'textdomain' ),
        'template' => 'emails/custom-notification.php',
    );
    return $templates;
} );
```

### Override Templates

Copy template files to your theme:
```
/your-theme/buddypress/bp-favorite-notification/notifications/notification-item.php
/your-theme/buddypress/bp-favorite-notification/emails/activity-favorited.php
```

## 🎨 Customization

### CSS Variables

```css
/* Customize colors in your theme */
:root {
    --bpfn-primary-color: #your-color;
    --bpfn-secondary-color: #your-color;
    --bpfn-border-width: 4px;
    --bpfn-animation-duration: 0.5s;
}
```

### JavaScript API

```javascript
// Initialize with custom options
BPFN.init({
    debug: true,
    settings: {
        refresh_interval: 30000, // 30 seconds
        animation_duration: 3000
    }
});

// Show custom notification
BPFN.showNotification('Custom message', 'success', {
    duration: 5000,
    position: 'top-center'
});

// Register event handler
BPFN.on('notification:added', function(data) {
    console.log('New notification:', data);
});
```

## 🔌 Module System

### Creating a Custom Module

```php
class BPFN_Module_Custom {
    public function __construct() {
        $this->setup_hooks();
    }
    
    private function setup_hooks() {
        // Add your hooks here
    }
}

// Register the module
add_action( 'bpfn_load_modules', function( $plugin ) {
    $plugin->register_module( 'custom', new BPFN_Module_Custom() );
} );
```

## 🛠️ Hooks Reference

### Priority Hooks (Run Early)
- `bpfn_init` - Plugin initialized (priority: 10)
- `bpfn_load_modules` - Modules loading (priority: 10)
- `bpfn_setup_globals` - BuddyPress globals setup (priority: 10)

### Data Modification Hooks
- `bpfn_notification_data` - Modify notification data before saving
- `bpfn_email_data` - Modify email data before sending
- `bpfn_user_settings` - Modify user settings before saving

### Display Hooks
- `bpfn_before_notification` - Before notification display
- `bpfn_after_notification` - After notification display
- `bpfn_notification_actions` - Add custom notification actions

## 🔒 Security

- All user inputs are sanitized
- Nonces used for all forms and AJAX requests
- Capability checks for admin functions
- SQL queries use prepared statements

## 🌐 Translation Ready

The plugin is fully translation ready. Use the provided POT file to create translations:

```bash
wp i18n make-pot . languages/bp-fav-notification.pot
```

## 📈 Performance

- Optimized database queries
- Asset loading only when needed
- Efficient caching mechanisms
- Minimal DOM manipulation

## 🤝 Contributing

Contributions are welcome! Please follow these guidelines:

1. Create feature branches
2. Follow WordPress coding standards
3. Add appropriate hooks for extensibility
4. Update documentation
5. Test with latest BuddyPress version

## 📄 License

GPL v2 or later

## 🆘 Support

- Documentation: https://wbcomdesigns.com/docs/
- Support Forum: https://wordpress.org/support/plugin/bp-favorite-notification/
- GitHub Issues: https://github.com/wbcomdesigns/bp-favorite-notification/issues