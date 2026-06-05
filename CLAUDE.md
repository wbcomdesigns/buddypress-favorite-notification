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
- ONE active admin page: top-level **`bpfn-dashboard`** (cap `manage_options`, hook
  `toplevel_page_bpfn-dashboard`), rendered by `BPFN_Module_Admin::admin_page`, three inline
  sections (Overview / Settings / Tools). Uses classic WP `postbox`/`wp-list-table` markup —
  NOT the modern Wbcom card shell; a future UI pass should align it to `/ux-foundation`.
- A SECOND options page (`bpfn-settings`, `BPFN_Module_Settings`) exists in code but is
  **DEAD** — the module is never loaded.

## Settings / options
- Settings API: option `bpfn_options` (group `bpfn_settings`), single field
  `enable_enhanced_notifications`.
- Standalone options: `bpfn_auto_cleanup_enabled`, `bpfn_auto_cleanup_days`,
  `bpfn_last_auto_cleanup`, `bpfn_version`, `bpfn_show_migration_notice`,
  `bpfn_favorites_migrated`, `bpfn_migration_status`, `bpfn_migration_log`.
- Per-user prefs: `{prefix}bp_favorite_notification_prefs` via `bpfn_get/save_user_settings`.

## Known issues (baseline 2026-06-05)
1. `BPFN_Module_Settings` is dead code (unloaded) — its admin page + BP Settings subnav +
   front-end per-user save handler never run.
2. `assets/js/admin.js:223,332,415` use native `confirm()` — banned by admin-UX Rule 10.
3. `wp_ajax_bpfn_dismiss_migration_notice` posted by inline JS has no PHP handler (dead).
4. N+1 in `get_users_who_favorited` (limit 999 modal) and `render_trending_activities`.

## Conventions
- Prefix everything `bpfn_` / `BPFN_`. Text domain `buddypress-favorite-notification`.
- Custom-table queries are deliberately direct (`$wpdb`) with object caching + inline
  `phpcs:ignore`. Public favorite-display AJAX is `nopriv` by design (read-only).
- Commit/PR per global rules: branch off, no co-author/footer lines.

## Big-site checklist reminders (per global CLAUDE.md)
- Who-liked modal and trending dashboard are the rows-at-scale surfaces — fix N+1 (batch
  `WP_User_Query` with `include`) and paginate before claiming big-site readiness.
