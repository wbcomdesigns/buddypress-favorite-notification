# Database Tables

The plugin creates two custom tables on activation via `dbDelta()`. Both are prefixed with the site's table prefix.

## {prefix}bp_favorite_notification_prefs

Per-member notification preferences.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint, auto-increment | Primary key. |
| `user_id` | bigint | The member. |
| `notification_type` | varchar(50) | Preference key, for example `activity_post`, `activity_comment`. |
| `is_enabled` | tinyint(1), default 1 | Web channel on/off. |
| `email_enabled` | tinyint(1), default 1 | Email channel on/off. |
| `realtime_enabled` | tinyint(1), default 1 | Realtime channel on/off. |
| `created_at` | datetime | Row created. |
| `updated_at` | datetime | Row updated (auto). |

Keys: primary key on `id`, unique key on (`user_id`, `notification_type`), index on `user_id`.

Rows are written with `$wpdb->replace()` keyed on the unique (`user_id`, `notification_type`) pair, so a member has at most one row per type. Read and written through `bpfn_get_user_settings()` and `bpfn_save_user_settings()`.

## {prefix}bp_activity_favorites

Favourite tracking, used for the "who liked this" display and the admin statistics.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint, auto-increment | Primary key. |
| `activity_id` | bigint | The favourited activity. |
| `user_id` | bigint | The member who favourited. |
| `favorited_at` | datetime | When the favourite happened. |

Keys: primary key on `id`, index on `activity_id`, index on `user_id`, unique key on (`activity_id`, `user_id`).

The `activity_id` and `user_id` indexes back the count, who-liked, and trending queries; the unique key prevents duplicate rows. Kept in sync with BuddyPress on `bp_activity_add_user_favorite` (insert) and `bp_activity_remove_user_favorite` (delete). Pre-existing favourites are moved in by the Tools-tab migration.

## Preferences are not activity data

The preferences table stores only channel choices, not favourites. Favourites themselves live in `{prefix}bp_activity_favorites` and in BuddyPress's own storage. Notifications live in the BuddyPress notifications table under the `favorite_notifier` component.

## Caching

Counts and who-liked lists are held in the `bpfn_favorites` object-cache group for five minutes, versioned per activity with an incrementor so a favourite change invalidates every cached page for that activity at once. Admin dashboard stats are cached in the `bpfn_dashboard_stats` transient and cleared on any favourite change.

## Uninstall note

The plugin does not drop these tables on deactivation, and there is no uninstall routine that removes them. Removing the plugin leaves the two tables and the `bpfn_*` options in place.
