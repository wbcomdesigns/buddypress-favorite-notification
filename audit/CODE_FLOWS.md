# Code Flows — BuddyPress Favorite Notification

## Bootstrap
```
plugins_loaded(5) → check_dependencies() → require functions/* + compat
bp_loaded(10)    → init() → adds bp_init(5) → load_modules() (6 modules)
                          → init_migration_hooks() → do_action('bpfn_init')
```
Module map (bp-favorite-notification.php:159): notifications, email, realtime, assets, admin, favorite_display.
NOTE: `settings` (BPFN_Module_Settings) is NOT in this map → never instantiated.

## Flow A — User favorites an activity → notification + email
```
BP fires bp_activity_add_user_favorite($activity_id,$user_id)
  → Notifications::add_favorite_notification()
      ensure $bp->favorite_notifier set → bp_notifications_add_notification()
      do_action('bpfn_after_add_notification', $notif_id, $data, $activity, $user_id)
          → Email::send_email_notification() (prio 20)
              bpfn_is_notification_enabled(author, type, 'email')?
              resolve template → get_email_message() (theme override → token parse)
              wp_mail() → bpfn_log_event('email_sent') → do_action('bpfn_after_send_email')
  → Favorite_Display::sync_favorite_add() → INSERT {prefix}bp_activity_favorites → clear_cache()
```

## Flow B — Activity stream favorite display (frontend)
```
bp_activity_before_post_footer_content → Favorite_Display::display_favorite_count()
  logged-in + activity component active → get_favorite_count() (object cache)
  → get_users_who_favorited(limit 3) [N+1: get_userdata per user]
  → format_favorite_text() → "A, B and N others" (data-activity-id)
JS favorite-display.js click ".bpfn-others-count"
  → AJAX bpfn_get_all_favorites (nonce bpfn-favorite-nonce, nopriv allowed)
      → get_users_who_favorited(limit 999) [N+1 hot path] → modal HTML
```

## Flow C — Realtime
```
heartbeat tick (15s) → heartbeat_received(): verify bpfn_realtime_nonce, per-user enabled?
  → get_new_notifications() (direct query BP notifications table, LIMIT 5)
  → response['bpfn_realtime_notifications']
realtime.js renders toast; dismiss → AJAX bpfn_dismiss_notification (ownership check) → mark read
```

## Flow D — Admin dashboard
```
admin_menu → add_menu_page('bpfn-dashboard', cap manage_options) → admin_page()
  render_dashboard_section(): get_dashboard_stats() (custom-table COUNTs + BP notif COUNTs)
      render_trending_activities(): per-row bp_activity_get_specific()+get_userdata() [N+1, ~20/page]
  render_settings_section(): Settings API form (group bpfn_settings / option bpfn_options)
  render_tools_section(): migration status + cleanup settings form (own nonce bpfn_cleanup_nonce)
```

## Flow E — Tools: cleanup settings save
```
admin_init → handle_cleanup_settings_save()
  $_POST['bpfn_save_cleanup_settings'] set? verify bpfn_cleanup_nonce + manage_options
  update_option bpfn_auto_cleanup_enabled / bpfn_auto_cleanup_days
  schedule/unschedule bpfn_auto_cleanup_notifications (monthly) → redirect ?settings_updated=true
```

## Flow F — Migration
```
Tools "Run Migration" → admin.js confirm() [BANNED] → AJAX bpfn_migrate_favorites
  >100 users → start_migration() → wp_schedule_single_event('bpfn_process_migration_batch')
               batch 50 (usermeta bp_favorite_activities LIMIT/OFFSET) → re-chain +5s
  <=100 users → run_migration() synchronously
Progress polled via AJAX bpfn_migration_progress.
Migration nag dismiss → inline JS POST action 'bpfn_dismiss_migration_notice' → NO PHP HANDLER (dead)
```

## Key files
| Concern | File |
|---|---|
| Bootstrap / tables / activation | bp-favorite-notification.php |
| BP component shim | includes/compat/buddypress-compat.php |
| Functions (api/core/template/integration) | includes/functions/*.php |
| Notifications | includes/modules/class-notifications.php |
| Email | includes/modules/class-email.php |
| Realtime | includes/modules/class-realtime.php |
| Favorite display + custom table | includes/modules/class-favorite-display.php |
| Admin dashboard/tools | includes/modules/class-admin.php |
| Assets/enqueue | includes/modules/class-assets.php |
| Settings module (DEAD — unloaded) | includes/modules/class-settings.php |
| Migration | includes/migrations/class-favorites-migration.php |

## AJAX chain table
| Action | Nonce | Cap | Handler |
|---|---|---|---|
| bpfn_clear_old_notifications | bpfn-admin-nonce | manage_options | Admin::ajax_clear_old_notifications |
| bpfn_get_stats | bpfn-admin-nonce | manage_options | Admin::ajax_get_stats |
| bpfn_migrate_favorites | bpfn-admin-nonce | manage_options | Admin::ajax_migrate_favorites |
| bpfn_migration_progress | bpfn-admin-nonce | manage_options | Admin::ajax_migration_progress |
| bpfn_check_notifications | bpfn-nonce/realtime | logged-in | Realtime::ajax_check_notifications |
| bpfn_dismiss_notification | bpfn-nonce/realtime | logged-in+owner | Realtime::ajax_dismiss_notification |
| bpfn_get_all_favorites (+nopriv) | bpfn-favorite-nonce | public | Favorite_Display::ajax_get_all_favorites |
| bpfn_refresh_favorite_display (+nopriv) | bpfn-favorite-nonce | public | Favorite_Display::ajax_refresh_favorite_display |
| bpfn_dismiss_migration_notice | bpfn-dismiss-notice | — | (NONE — dead listener) |

## Required settings / dependencies
- BuddyPress active (activity + notifications components). Settings component for per-user prefs UI (note: that UI is owned by the unloaded settings module).
