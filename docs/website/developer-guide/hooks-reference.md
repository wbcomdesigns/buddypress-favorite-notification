# Hooks Reference

Every hook below is present in the plugin source. Signatures list the value being filtered first.

## Display filters

Reshape the "who liked this" line.

| Filter | Since | Purpose |
| --- | --- | --- |
| `bpfn_display_modes` | 2.1.0 | Filter the available display modes: `array( slug => label )`. |
| `bpfn_favorite_icons` | 2.1.0 | Filter the selectable icons: `array( slug => array( 'label', 'entity' ) )`. |
| `bpfn_favorite_display_format` | 2.1.0 | Override the mode for one activity. Args: `$mode, $activity_id, $count, $users_data`. |
| `bpfn_favorite_icon_html` | 2.1.0 | Override the icon markup. Return an empty string for no icon. Args: `$entity, $activity_id, $count`. |
| `bpfn_favorite_display_html` | 2.1.0 | Return a non-empty string to replace the entire display markup. Args: `$html, $activity_id, $count, $users_data`. The return value is passed through `wp_kses()` with the plugin's allow list. |
| `bpfn_favorites_modal_per_page` | 2.1.0 | Members loaded per page in the "View all" modal. Default 20. Args: `$per_page, $activity_id`. |
| `bpfn_who_favorited_limit` | 2.1.0 | Hard ceiling on how many members the modal will ever load. Default 0 (no limit). Args: `$ceiling, $activity_id`. |

Any markup a `bpfn_favorite_display_html` callback returns is filtered through `wp_kses()`. The allow list permits `div`, `span`, `a`, and `button` with a fixed set of attributes (`href`, `class`, `title`, `type`, `role`, `data-activity-id`, `aria-hidden`, `aria-haspopup`, `aria-label`). Any other tag or attribute is stripped.

## Notification filters

| Filter | Purpose |
| --- | --- |
| `bpfn_notification_string` | The notification HTML in string format. Args: `$html, $item_id, $secondary_item_id, $total_items`. |
| `bpfn_notification_array` | The notification data in array format. Args: `$array, $activity, $secondary_item_id`. |
| `bpfn_activity_type_map` | Map of BuddyPress activity type to preference key. Keep in step with `bpfn_settings_notification_types`. |
| `bpfn_settings_notification_types` | The notification types shown on the member preference screen. |
| `bpfn_default_user_settings` | Default per-type, per-channel preferences used when a member has none saved. |
| `bpfn_get_user_settings` | The resolved settings array for a member. Args: `$settings, $user_id`. |
| `bpfn_before_save_user_settings` | The settings array just before it is written. Args: `$settings, $user_id`. |

## Email filters

| Filter | Purpose |
| --- | --- |
| `bpfn_email_from_name` | The From name. Defaults to the site name. |
| `bpfn_email_from_email` | The From address. Defaults to `admin_email`. |
| `bpfn_email_templates` | The registered email templates (subject and template path per key). |
| `bpfn_email_template_key` | The template key chosen for an email. Args: `$key, $activity, $user_id`. |
| `bpfn_email_template_path` | The resolved template file path. Args: `$path, $template`. |
| `bpfn_email_data` | The full email data array before send. Args: `$email_data, $activity, $user_id`. |
| `bpfn_email_subject` | The parsed subject. Args: `$subject, $tokens`. |
| `bpfn_email_message` | The parsed message body. Args: `$message, $tokens`. |
| `bpfn_email_headers` | The email headers array. Args: `$headers, $email_data`. |

## Admin and general filters

| Filter | Purpose |
| --- | --- |
| `bpfn_admin_tabs` | The admin tab list: `array( slug => array( 'label', 'icon', 'group' ) )`. |
| `bpfn_dashboard_stats_ttl` | TTL for the cached dashboard stats transient. Default 5 minutes. |
| `bpfn_notification_icons` | Icon markup map used by `bpfn_notification_icon()`. |
| `bpfn_notification_icon_html` | The icon HTML for a type. Args: `$icon, $type`. |
| `bpfn_should_show_notifications` | Whether notification UI should render on the current page. |
| `bpfn_enable_logging` | Return true to enable the internal event log. Default false. |
| `bpfn_get_notifications` | The formatted notifications array from `bpfn_get_notifications()`. |
| `bpfn_format_notification_data` | The formatted data for a single notification. |

## Actions

| Action | Fires | Args |
| --- | --- | --- |
| `bpfn_init` | Plugin initialised | `$plugin` |
| `bpfn_load_modules` | After modules load | `$plugin` |
| `bpfn_after_add_notification` | After a favourite notification is created (email hooks here at priority 20) | `$notification_id, $data, $activity, $user_id` |
| `bpfn_after_send_email` | After an email is sent | `$sent, $email_data` |
| `bpfn_after_save_user_settings` | After member preferences are saved | `$user_id, $settings, $success` |
| `bpfn_notification_settings` | Inside the BuddyPress notification-settings table | none |
| `bpfn_settings_setup_hooks` | Settings module hooks set up | `$module` |
| `bpfn_registered_notification_type` | A custom notification type is registered | `$type, $args` |
| `bpfn_notification_added` | A notification is added via `bpfn_add_notification()` | `$notification_id, $args` |
| `bpfn_notifications_deleted` | Notifications deleted via `bpfn_delete_notifications()` | `$args` |
| `bpfn_marked_notifications_read` | Notifications marked read via the helper | `$notifications, $args` |
| `bpfn_module_registered` | A module is registered via `bpfn_register_module()` | `$module_name, $module_instance` |
| `bpfn_activate` / `bpfn_deactivate` | Activation / deactivation | none |
| `bpfn_create_tables` | After the custom tables are created | `$wpdb, $charset_collate` |
| `bpfn_log_event` | An internal event is logged (when logging is enabled) | `$log_data` |
| `bpfn_template_not_found` | A template part could not be located | `$template, $args` |
