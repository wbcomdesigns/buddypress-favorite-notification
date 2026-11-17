# BuddyPress Favorite Notification — Capabilities

> Verified against code on 2026-11-17 (2.2.1). Roll-up of what this plugin does, derived from [`audit/manifest.json`](audit/manifest.json) (the canonical machine-readable inventory). Keep this in sync at release time via `/wp-plugin-onboard --refresh`. Every capability below is shipped and active; feature maturity lives in the manifest's structured `status` fields, not in prose.

- **Version:** 2.2.1
- **Slug:** `buddypress-favorite-notification` · **Text domain:** `buddypress-favorite-notification` · **Prefix:** `BPFN_` / `bpfn_` (no PHP namespace)
- **Requires:** WordPress 6.5+ · PHP 8.1+ · BuddyPress 12.0+ (hard dependency, declared via `Requires Plugins: buddypress`, refused below 12.0 at load)
- **Author:** Wbcom Designs

## At a glance

| Surface | Count |
|---|---|
| Admin pages | 2 (own submenu + the shared WB Plugins hub landing) |
| Admin tabs | 4 (Overview, Display, Tools, Discover) |
| AJAX actions | 7 (all logged-in only) |
| Options | 13 (display mode and icon, 2 real-time, 2 cleanup, rest state) |
| Custom DB tables | 2 |
| Cron hooks | 2 (1 recurring monthly cleanup + 1 self-chaining migration batch) |
| Public API functions | 10 (`includes/functions/api-functions.php`) |
| Plugin-own hooks fired | 39 (16 actions, 23 filters) |
| Email templates | 2 (BuddyPress Emails) |
| Service modules | 7 |
| REST routes / blocks / shortcodes / CPTs / taxonomies | 0 (none, by design) |

## What it does

### Favorite notifications
When a member favorites an activity or an activity comment, the author is notified. Delivery runs on three independent channels — a BuddyPress notification, an email, and a real-time in-page ping — each individually switchable per member. The plugin registers itself as a `favorite_notifier` pseudo-component so BuddyPress renders and formats its notifications natively.

### Favorite display in the activity stream
A "who favorited this" line renders under each activity. As of 2.1.0 the site owner chooses its shape in **Display Mode**: inline usernames, an icon with a count, or an icon and count that opens the full member list in a dialog. A separate **Favorite Icon** setting (heart, star, bookmark, thumbs up, or none) lets favorites be told apart from a theme's own Like reaction. Existing sites stay on inline usernames until the setting is changed, so updating alters nothing on its own.

The member list paginates with a **Load more** control rather than stopping at the first 50. Counts are cached per activity and invalidated as a versioned set, so a like no longer leaves a stale count behind for up to five minutes. The stream markup and its post-favorite refresh render from one code path — previously they were two copies, so any format or icon change reverted the moment a member clicked like.

### Per-member preferences
Members control their own delivery under **Settings → Favorite Notifications**, stored in `{prefix}bp_favorite_notification_prefs` (one row per user per notification type, with independent web / email / real-time flags). The row BuddyPress renders on its own notification-settings screen writes through to the same table.

### Real-time notifier
Off on new installs; the site owner switches it on in the Display tab and picks a 30 or 60 second check. Transport is the WordPress Heartbeat API only (`heartbeat_received` / `heartbeat_settings`), and the scripts load only for members who have popups on. Toasts are compact, theme-aware cards.

### Emails
Two emails, `bpfn-activity-favorited` and `bpfn-comment-favorited`, are sent through BuddyPress Emails with `bp_send_email()`. Site owners edit them under Dashboard > Emails, members get a working unsubscribe link, and each member's choice is mirrored into the plugin's preferences table. Wording says "liked" since 2.2.1.

### Favorites data + migration
`{prefix}bp_activity_favorites` tracks which member favorited which activity, indexed on both lookup columns with a `UNIQUE (activity_id, user_id)` constraint. It is populated live from `bp_activity_add_user_favorite` / `bp_activity_remove_user_favorite`, and backfilled from legacy usermeta by a batched background migration (50 rows per batch, self-chaining, used when more than 100 users have favorites).

### Admin
One submenu under the shared **WB Plugins** hub, rendered as a card-panel layout (`includes/admin/views/layout.php`) with four tabs. Overview reports status; Display owns display mode, favorite icon and real-time popups (the icon defaults to the theme's Like style, thumb on Reign and BuddyX, star elsewhere, and sites upgraded from before 2.2.1 keep the heart); Tools owns notification retention (a monthly cleanup cron, off on new installs, plus a manual "Clear Old Notifications Now"); Discover lists the wider Wbcom ecosystem. All admin writes are `manage_options` + nonce gated.

### Extension points
10 documented public functions cover registering notification types and modules, and adding, reading, deleting, and marking notifications read. 2.1.0 added `bpfn_favorite_icon_html`, `bpfn_favorite_display_format`, `bpfn_favorite_display_html`, `bpfn_display_modes`, `bpfn_favorite_icons`, and `bpfn_favorites_modal_per_page`. `bpfn_who_favorited_limit` now defaults to 0 (no limit) and acts as a ceiling on the paginated list. 2.2.0 added `bpfn_email_tokens`; 2.2.1 added `bpfn_default_favorite_icon`.

## Not present, by design

No REST routes, no blocks, no shortcodes, no custom post types, no taxonomies, and no `register_setting()` call — the two settings screens use hand-rolled, nonce-verified POST handlers. Verified as 0 hits each across all non-vendor PHP.

## Release gates at 2.2.1

| Gate | Result |
|---|---|
| PHP lint | clean |
| PHPStan (level 5) | `[OK] No errors` (re-run 2026-11-17) |
| Contract audit | baseline `.contract-audit-baseline.json` (5 entries verified false positives at 2.1.0; not re-run in this refresh) |
| Documentation truth | `audit/manifest.json` re-verified finding by finding against shipped code |
