<!-- READ FIRST -->
# CLAUDE.md — BuddyPress Favorite Notification

> READ FIRST: This plugin is onboarded. Before grepping, read **`audit/manifest.json`**
> (canonical inventory) and the human reports in `audit/`:
> - `audit/FEATURE_AUDIT.md` — feature-by-feature inventory
> - `audit/CODE_FLOWS.md` — trigger → handler → output pipelines
> - `audit/graph.html` — interactive manifest graph
> - `audit/wppqa-baseline-2026-06-05/SUMMARY.md` — current bug baseline
>
> Answer "what does X do / where is Y" from the manifest, not a fresh scan.

## What this is
Free Wbcom Designs BuddyPress addon. Sends BP + email + realtime notifications when a
member's activity/comment is favorited, and renders a Facebook-style "X and N others liked
this" display. Hard dependency on BuddyPress. Current version 2.0.1, dev branch `2.0.0`.

## Development skill — follow this
All plugin work MUST follow **`/wp-plugin-development`** (canonical Wbcom plugin skill):
backend architecture, REST patterns, DB, security/escaping, **Part 6 Admin UI**, the
**16 critical admin rules**, design tokens, and dev hygiene. UI/CSS/a11y/dark-mode/RTL work
follows **`/ux-foundation`**; audit drift with **`/ux-audit`**. Onboarding artefacts in
`audit/` are owned by **`/wp-plugin-onboard`** — regenerate, never hand-edit entries.

## Architecture (90-second orientation)
- Entry: `bp-favorite-notification.php` — singleton `BP_Favorite_Notification`, `BPFN_` constants,
  creates 2 custom tables on activation, loads 6 modules on `bp_init`.
- Modules (`includes/modules/class-*.php`): notifications, email, realtime, assets, admin, favorite_display.
- BP pseudo-component `favorite_notifier` registered in `includes/compat/buddypress-compat.php`.
- Procedural helpers in `includes/functions/` (api/core/template/integration).
- Migration: `includes/migrations/class-favorites-migration.php` (usermeta → table, batched).
- No REST, no blocks, no shortcodes, no CPTs.

## Admin UI
- ONE admin page: submenu **`bpfn-dashboard`** under the shared **WB Plugins hub**
  (`wbcomplugins`), cap `manage_options`, rendered by `BPFN_Admin::render_page()`
  (`includes/admin/class-bpfn-admin.php`) with the modern card-panel shell
  (`includes/admin/views/shell.php` + `overview.php` / `tools.php`). Two tabs:
  **Overview** (stats, trending, quick actions) and **Tools** (migration, cleanup).
- There is NO Settings API options page. The former Settings tab's only field
  ("Enhanced Notifications", option `bpfn_options`) was removed on branch 2.0.0: its
  enhanced template could never render — BuddyPress kses-strips notification
  descriptions to `<a href class>` on every surface.
- `BPFN_Module_Settings` (`includes/modules/class-settings.php`) is **front-end only**
  and IS loaded: BP member **Settings → Favorite Notifications** subnav + per-user
  save handler (template `templates/settings/notifications.php`, styles
  `assets/css/settings.css`).

## Settings / options
- Standalone options: `bpfn_auto_cleanup_enabled`, `bpfn_auto_cleanup_days`,
  `bpfn_last_auto_cleanup`, `bpfn_version`, `bpfn_show_migration_notice`,
  `bpfn_favorites_migrated`, `bpfn_migration_status`, `bpfn_migration_log`.
- `bpfn_options` (group `bpfn_settings`) is RETIRED — no longer registered or read.
- Per-user prefs: `{prefix}bp_favorite_notification_prefs` via `bpfn_get/save_user_settings`.

## Frontend assets / design tokens
- Shared `--bpfn-*` design tokens live in `assets/css/notifications.css` (the style
  dependency of favorite-display.css, realtime.css, and settings.css). Light values
  consume BuddyX `--bx-color-*` vars with light fallbacks; `[data-bx-mode="dark"]`
  and the `auto` + `prefers-color-scheme: dark` blocks redeclare them with
  DARK-appropriate fallback literals because **Reign 8.0.3 sets `data-bx-mode` but
  does not define `--bx-color-*`** — the fallback literal is what renders there.
- `assets/js/notifications.js` was deleted on 2.0.0 (100% dead: wrong selectors,
  AJAX actions without handlers). Frontend JS is favorite-display.js + realtime.js.

## Known issues (updated 2026-07-03)
All four baseline (2026-06-05) issues are FIXED on branch 2.0.0: (1) the settings
module is loaded (front-end only), (2) native `confirm()` replaced by the
`bpfnConfirm` modal, (3) `bpfn_dismiss_migration_notice` has a real handler in
`BPFN_Admin`, (4) who-favorited/trending N+1 eliminated (batched, capped, cached).
The 2026-07-03 audit's Blocker (dead "Enhanced Notifications" toggle) and Major
findings (dead legacy admin.js + notifications.js, inline member-settings styles,
untokenized realtime.css, Reign dark-mode contrast) are also fixed. No known open
issues. `audit/manifest.json` and `AUDIT-VERDICT.md` predate these removals —
refresh via `/wp-plugin-onboard --refresh` at next release.

## Conventions
- Prefix everything `bpfn_` / `BPFN_`. Text domain `buddypress-favorite-notification`.
- Custom-table queries are deliberately direct (`$wpdb`) with object caching + inline
  `phpcs:ignore`. Public favorite-display AJAX is `nopriv` by design (read-only).
- Commit/PR per global rules: branch off, no co-author/footer lines.

## Big-site checklist reminders (per global CLAUDE.md)
- Who-liked modal and trending dashboard are the rows-at-scale surfaces — fix N+1 (batch
  `WP_User_Query` with `include`) and paginate before claiming big-site readiness.
