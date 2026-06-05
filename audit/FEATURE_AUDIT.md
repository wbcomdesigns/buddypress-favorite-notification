# Feature Audit — BuddyPress Favorite Notification

- Version: 2.0.1 | Branch: 2.0.0 | Type: free Wbcom BuddyPress addon
- Hard dependency: BuddyPress (plugin self-disables with admin notice if absent)
- Architecture: singleton `BP_Favorite_Notification` (bp-favorite-notification.php) loads 6 `BPFN_Module_*` classes on `bp_init`. Procedural function libraries in `includes/functions/`. BP pseudo-component (`favorite_notifier`) wired in `includes/compat/buddypress-compat.php`.

## 1. Features

### F1 — Favorite notifications (core)
- Module: `BPFN_Module_Notifications` (class-notifications.php).
- Listens: `bp_activity_add_user_favorite` / `bp_activity_remove_user_favorite` → adds/removes a BP notification on the activity author.
- Formats via `bp_notifications_get_notifications_for_user` filter; supports activity-post and comment favorites.
- Display-name hardening filter on `bp_core_get_user_displayname` (recursion-guarded).
- Extensible: `bpfn_notification_string`, `bpfn_notification_array` filters; `bpfn_after_add_notification` action.

### F2 — Email notifications
- Module: `BPFN_Module_Email` (class-email.php), hooked on `bpfn_after_add_notification` (prio 20).
- Templates: `templates/emails/{activity-favorited,comment-favorited,base}.php` with theme override at `<stylesheet>/buddypress/bp-favorite-notification/`. HTML fallback built in if template missing.
- Token engine (`{site_name}`, `{user_name}`, `{activity_link}`, …). Filters: `bpfn_email_templates`, `bpfn_email_data`, `bpfn_email_subject/message/headers`, `bpfn_email_from_name/email`, `bpfn_email_template_path/key`.

### F3 — Realtime notifications
- Module: `BPFN_Module_Realtime` (class-realtime.php). WP Heartbeat (`heartbeat_received`/`heartbeat_settings`, 15s interval) + AJAX fallback.
- AJAX: `bpfn_check_notifications` (poll), `bpfn_dismiss_notification` (mark read, ownership-checked). Both nonce-verified (`bpfn-nonce` or `bpfn_realtime_nonce`) and logged-in gated.

### F4 — Facebook-style favorite display
- Module: `BPFN_Module_Favorite_Display` (class-favorite-display.php).
- Renders "X, Y and N others" under each activity (`bp_activity_before_post_footer_content`), logged-in only.
- Maintains custom `{prefix}bp_activity_favorites` table (synced on add/remove), object-cached counts/user-lists (5 min).
- Public AJAX (priv + nopriv): `bpfn_get_all_favorites` (who-liked modal), `bpfn_refresh_favorite_display`. Nonce `bpfn-favorite-nonce`.

### F5 — Admin dashboard / tools (single page)
- Module: `BPFN_Module_Admin` (class-admin.php). Top-level menu `bpfn-dashboard` (cap `manage_options`, dashicons-heart).
- One page, three sections: Overview (stats cards, recent + trending activities), Settings (Settings API `bpfn_options`), Tools (migration + cleanup).
- Admin AJAX (nonce `bpfn-admin-nonce`, cap `manage_options`): `bpfn_clear_old_notifications`, `bpfn_get_stats`, `bpfn_migrate_favorites`, `bpfn_migration_progress`.

### F6 — Favorites migration (usermeta → table)
- `BPFN_Favorites_Migration` (includes/migrations/). Sync (<100 users) or background batches of 50 via `bpfn_process_migration_batch` single-event re-chaining.
- Admin nag (`bpfn_show_migration_notice`) on Tools page.

### F7 — Automatic cleanup (cron)
- `bpfn_auto_cleanup_notifications` monthly cron (`BPFN_Module_Admin::run_automatic_cleanup`); retention `bpfn_auto_cleanup_days` (7-90, default 30); toggle `bpfn_auto_cleanup_enabled`.

## 2. AJAX handlers
See `audit/manifest.json#/ajax` (8 live) and `#/ajax_dead_listeners` (1 dead: `bpfn_dismiss_migration_notice`).

## 3. REST endpoints
None.

## 4. Admin pages & settings
- ACTIVE: `bpfn-dashboard` (top-level, `BPFN_Module_Admin`).
- DEAD: `bpfn-settings` (options page, `BPFN_Module_Settings`) — module never loaded.
- Persisted admin setting: `bpfn_options.enable_enhanced_notifications`. Standalone options: `bpfn_auto_cleanup_enabled`, `bpfn_auto_cleanup_days`, plus state options. Full list in `manifest.json#/settings`.

## 5. Shortcodes / 6. Content types / 9. Blocks
None.

## 7. JS modules
`notifications.js`, `favorite-display.js`, `realtime.js` (frontend, logged-in + BP notifications active), `admin.js` (dashboard, contains 3 banned `confirm()` calls).

## 8. CSS modules
`notifications.css`, `favorite-display.css`, `realtime.css`, `admin.css`.

## 10. Cron jobs
`bpfn_auto_cleanup_notifications` (monthly), `bpfn_process_migration_batch` (single, re-chains).

## 11. DB tables
`{prefix}bp_favorite_notification_prefs`, `{prefix}bp_activity_favorites` (both indexed; see manifest).

## 12. Integrations
BuddyPress (required) — activity, notifications, settings components; pseudo-component `favorite_notifier`.

## Known issues (see CODE_FLOWS + wppqa baseline)
1. Dead module `BPFN_Module_Settings` — front-end per-user settings UI likely never renders.
2. `confirm()` ban violations (admin.js:223/332/415).
3. Dead AJAX listener `bpfn_dismiss_migration_notice`.
4. N+1 in who-liked modal (limit 999) and trending dashboard.
