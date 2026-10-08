# Helper Functions

The plugin exposes a procedural API in `includes/functions/`. All functions are prefixed `bpfn_`. The functions below exist in the source.

## Plugin and module access

| Function | Returns | Notes |
| --- | --- | --- |
| `bpfn()` | `BP_Favorite_Notification` | The singleton. |
| `bpfn_get_module( $name )` | object or null | A module instance, for example `notifications`, `email`, `favorite_display`. |
| `bpfn_register_module( $name, $instance )` | bool | Register a custom module. |
| `bpfn_get_version()` | string | The plugin version. |
| `bpfn_get_plugin_url( $path = '' )` | string | Plugin URL, optionally with a path appended. |
| `bpfn_get_plugin_path( $path = '' )` | string | Plugin filesystem path. |

## Member preferences

| Function | Returns | Notes |
| --- | --- | --- |
| `bpfn_get_user_settings( $user_id )` | array | Per-type, per-channel preferences, merged with defaults. |
| `bpfn_save_user_settings( $user_id, $settings )` | bool | Write preferences to the plugin table. |
| `bpfn_is_notification_enabled( $user_id, $type, $channel = 'web' )` | bool | Whether a member wants a notification for a type on a channel (`web`, `email`, `realtime`). |
| `bpfn_get_activity_type( $activity_id )` | string | Map a BuddyPress activity to a preference key (`activity_post` or `activity_comment`). |

## Notifications

| Function | Returns | Notes |
| --- | --- | --- |
| `bpfn_add_notification( $args )` | int or false | Add a favourite-component notification. |
| `bpfn_delete_notifications( $args )` | bool | Delete matching notifications. |
| `bpfn_mark_notifications_read( $args )` | bool | Mark matching notifications read. |
| `bpfn_get_notifications( $user_id = 0, $args = array() )` | array | Formatted notifications for a member. |
| `bpfn_get_notification_count( $user_id, $args = array() )` | int | Count of new favourite notifications. |
| `bpfn_format_notification_data( $notification )` | array or false | Formatted data for one notification object. |
| `bpfn_register_notification_type( $type, $args )` | bool | Register a custom notification type on the notifications module. |

## Cleanup

| Function | Returns | Notes |
| --- | --- | --- |
| `bpfn_clear_old_notifications( $days = 30 )` | array | Delete read favourite notifications older than `$days`. Returns `count`, `remaining`, `days`, or an `error` key on failure. Only read notifications are removed. |

## Template output

| Function | Notes |
| --- | --- |
| `bpfn_notification_icon( $type = 'favorite' )` | Echo an icon for a notification type. |
| `bpfn_get_notification_count_html( $count, $classes = '' )` | Return count badge HTML. |
| `bpfn_notification_count( $count, $classes = '' )` | Echo the count badge. |
| `bpfn_get_settings_field( $type, $name, $value, $args )` | Return a form field (checkbox, radio, select). |
| `bpfn_settings_field( $type, $name, $value, $args )` | Echo a form field. |
| `bpfn_get_template_part( $template, $args = array() )` | Load a template part, theme override first. |
| `bpfn_should_show_notifications()` | Whether the current page should render notification UI. |

## Favourite display module methods

Get the module with `bpfn_get_module( 'favorite_display' )`:

- `get_favorite_count( $activity_id )`: cached favourite count.
- `get_users_who_favorited( $activity_id, $limit = 3, $offset = 0 )`: paginated liker list with `users`, `total`, `remaining`.
- `render_display( $activity_id )`: the full display markup, `wp_kses()`-filtered.
- `get_display_modes()` / `get_icon_choices()`: the registered modes and icons (static).
- `get_table_name()`: the favourites table name.
