# BUILD-LOG — BuddyPress Favorite Notification

**Branch:** 2.0.0 · **Version:** 2.0.1 (header = `BPFN_VERSION` = readme Stable tag — no drift)
**Date:** 2026-06-05
**Scope:** Card-panel admin migration (NONE → wbcomplugins hub) + 3 audit bug fixes.
**Reference pattern:** `buddypress-contact-me` (wbcom-pro), playbook `references/wbcom-wrapper-migration.md`.

---

## Files created (8)

| File | Purpose |
|------|---------|
| `includes/admin/class-bpfn-admin.php` | Card-panel controller. Owns the WB Plugins submenu, the single `bpfn_options` Settings API registration, asset enqueue (screen-scoped), notice suppression, hub-landing takeover, migration nag, and the `bpfn_dismiss_migration_notice` AJAX handler. |
| `includes/admin/views/shell.php` | Page shell: header + `wp-header-end` marker + sidebar nav + body slot. Wraps the Settings tab in the `options.php` form. |
| `includes/admin/views/hub.php` | Shared "WB Plugins" hub landing (card grid, wrapper-helper-slug filter). |
| `includes/admin/views/overview.php` | Overview tab — reskinned stat cards + recent + trending tables (same queries as the old `get_dashboard_stats`). |
| `includes/admin/views/settings-general.php` | Settings tab — the `bpfn_options[enable_enhanced_notifications]` checkbox. |
| `includes/admin/views/tools.php` | Tools tab — migration card + cleanup form (nonce `bpfn_cleanup_settings`) + manual cleanup. |
| `assets/css/admin.css` | Token-driven card CSS, ported from contact-me (`bcm-`→`bpfn-`, `--bcm-`→`--bpfn-`). 0 leftover `bcm-` refs. |
| `BUILD-LOG.md` | This file. |

> `assets/js/admin.js` and `assets/css/admin.css` already existed; admin.css was overwritten with the card CSS, admin.js was edited (see below).

## Files modified (5)

| File | Change |
|------|--------|
| `bp-favorite-notification.php` | (a) Added `'settings' => 'class-settings.php'` to `load_modules()` (loads the dead module). (b) Require + instantiate `BPFN_Admin` in `load_dependencies()` under `is_admin()`. |
| `includes/modules/class-admin.php` | Stripped to a legacy **service** class: removed the standalone top-level `add_menu_page`, the whole admin-page UI (`admin_page` + 3 render_* + `get_dashboard_stats` + 2 table renderers), the `bpfn_options` `register_setting`/sections/fields/`field_checkbox`/`section_general`/`sanitize_options`, `enqueue_admin_assets`, `migration_notice`, `add_action_links`, and `display_admin_notices`. **Kept** (all reachable): 4 AJAX handlers, the Tools cleanup save handler (redirect now points at `&tab=tools`), and the monthly cron register+runner. Class docblock rewritten to its post-migration scope. |
| `includes/modules/class-settings.php` | Made **front-end only**: removed the `admin_menu`/`admin_init` hooks and the `add_options_page`/`register_setting('bpfn_options')`/sections/fields/`admin_page`/`section_general`/`field_checkbox`/`sanitize_options` methods. Kept the BP subnav (`setup_nav`), per-user save (`handle_settings_save`), `notification_settings`, screen renderers, and `get_notification_types`. |
| `assets/js/admin.js` | Ported `bpfnToast()` + promise-based `bpfnConfirm()` helpers (from contact-me). Replaced the 3 native `confirm()` calls (migrate / clear-old / bulk-update) with `bpfnConfirm().then()` — each body split into a `run*` method. |
| `readme.txt` / `README.txt` | Changelog stub added to the 2.0.1 entry (same file on case-insensitive FS). |

## Files deleted (0)

This was a **NONE-class** plugin (no `admin/wbcom/`, no legacy wrapper dir, no `[wbcom_admin_setting_header]`). Nothing to delete. The dead admin-page render and dead `bpfn-settings` options page were *removed in place* from the two module classes rather than deleting whole files (the files still hold live code).

---

## Bug-fix proof

### Bug 1 (HIGH) — dead settings module + duplicate `register_setting`

- **Loaded:** `load_modules()` map now contains `'settings' => 'class-settings.php'`. Class-name derivation `'BPFN_Module_' . ucfirst('settings')` = `BPFN_Module_Settings` (verified at runtime). The module now instantiates on `bp_init`, so the BP member **Settings → Favorite Notifications** subnav (`setup_nav`), the per-user save handler (`handle_settings_save` on `bp_actions`), and `notification_settings` now fire.
- **Per-user save path intact:** template `templates/settings/notifications.php` posts `bpfn[type][web|email|realtime]` + `bpfn_save_settings` + nonce `bpfn_settings_nonce` → `handle_settings_save()` checks exactly those → calls `bpfn_save_user_settings($user_id, $settings)` → `$wpdb->replace` into `{prefix}bp_favorite_notification_prefs`. Users can now opt out per channel.
- **Dedup proof:** `grep -rn "register_setting" --include=*.php .` returns **exactly one** call — `includes/admin/class-bpfn-admin.php:144` (`register_setting('bpfn_settings','bpfn_options', …)`). The old registrations in `class-admin.php:758` and `class-settings.php:250` are both removed. Reflection confirms `BPFN_Module_Settings` no longer exposes `admin_menu`/`admin_init`/`sanitize_options`. Single sanitizer = `BPFN_Admin::sanitize_settings()` (`isset(enable_enhanced_notifications) → 1`).

### Bug 2 (MED) — dead dismiss AJAX

`BPFN_Admin::register()` adds `wp_ajax_bpfn_dismiss_migration_notice` → `ajax_dismiss_migration_notice()`: cap check (`manage_options`) → `check_ajax_referer('bpfn-dismiss-notice','nonce')` → `delete_option('bpfn_show_migration_notice')`. The notice (now rendered by the controller) posts that exact action + nonce. Dismissal persists across reloads.

### Bug 3 (MED) — native `confirm()` ×3

`grep -nE "[^.a-zA-Z](confirm|alert)\(" assets/js/admin.js` (excluding `bpfnConfirm`/`bpfnToast`) → **NONE**. All three replaced with the accessible `bpfnConfirm()` modal (Escape/Enter/backdrop, focus management). Localized strings added in the controller's `wp_localize_script`.

---

## Option-wiring preservation (old → new)

| Option | Registered | Rendered | Sanitized/Saved | Read back |
|--------|-----------|----------|-----------------|-----------|
| `bpfn_options` (`enable_enhanced_notifications`) | `BPFN_Admin::register_settings()` group `bpfn_settings` | `views/settings-general.php` `name="bpfn_options[enable_enhanced_notifications]"` inside `settings_fields('bpfn_settings')` form | `BPFN_Admin::sanitize_settings()` (Settings API nonce/cap) | `get_option('bpfn_options')` in shell → view via `$settings`; consumed by `bpfn_is_feature_enabled('enhanced_notifications')` (unchanged) |
| `bpfn_auto_cleanup_enabled` | standalone | `views/tools.php` checkbox | `class-admin.php::handle_cleanup_settings_save` (nonce `bpfn_cleanup_settings` + `manage_options`) | `get_option(..,'yes')` in view + cron runner |
| `bpfn_auto_cleanup_days` | standalone | `views/tools.php` `<select>` | same handler (`absint`, floor 7) | view + `run_automatic_cleanup` |
| `bpfn_last_auto_cleanup` | state (cron write) | `views/tools.php` read-only block | `run_automatic_cleanup()` | view |
| `bpfn_version`, `bpfn_show_migration_notice`, `bpfn_favorites_migrated`, `bpfn_migration_status`, `bpfn_migration_log` | state | n/a | activation / migration code (unchanged) | migration logic + nag |

All keys referenced in code post-migration (verified by grep). Option-group/name/nonce strings all byte-identical to pre-migration.

---

## Verification

- `php -l` — clean on all 9 changed/new PHP files.
- WPCS (`wpcs` MCP) — **0 errors** on every changed/new file; the only warnings (array-arrow / equals alignment) were auto-fixed with `wpcs_fix_file`. Final re-check: no violations.
- `node --check assets/js/admin.js` — OK.
- Exactly **one** `register_setting('bpfn_options')` (proof above).
- Path constants (`BPFN_INCLUDES_PATH`, `BPFN_ASSETS_URL`, `BPFN_PLUGIN_PATH`) resolve from the plugin-root file via `__FILE__`; all new includes/enqueues use them.
- No native `alert`/`confirm` left in admin JS.
- Cross-layer wiring: both new view buttons (`#bpfn-migrate-favorites`, `#bpfn-clear-old-notifications`) have JS handlers → registered AJAX actions; cleanup form nonce matches handler.
- Runtime static load test: `BPFN_Module_Settings` + `BPFN_Admin` load; settings module exposes only front-end methods.
- Menu label: WB Plugins submenu shows **"Favorite Notifications"** (`add_submenu_page` menu_title); internal slug kept as `bpfn-dashboard` so existing `?page=bpfn-dashboard` bookmarks + the `Settings` action link still resolve.

**Not done (per brief): no commit/push, no activate/deactivate, no DB writes.** Plugin is currently inactive on this site; static + reflection verification used in place of browser testing.

---

## TODOs deferred (out of scope — wrapper + 3 named bugs only)

1. **Dead JS branches in `admin.js`** — handlers for `#bpfn-bulk-update`, `#bpfn-export-settings`, `#bpfn-import-settings-file`, `#bpfn-repair-tables`, `#bpfn-run-diagnostics`, `#bpfn-send-test-email`, `#bpfn-send-test-notification` (and their AJAX actions `bpfn_bulk_update_settings`, `bpfn_export_settings`, `bpfn_import_settings`, `bpfn_repair_tables`, `bpfn_run_diagnostics`, `bpfn_send_test_*`, `bpfn_dismiss_notice`) have **no matching button in any rendered view and no PHP handler** — this was already true in the legacy admin page (it only rendered the migrate + clear-old buttons). Not introduced by this migration. Recommend a dead-JS purge in a dedicated hygiene pass.
2. **N+1 (audit findings #4)** — `get_users_who_favorited` (limit-999 modal) and the trending tables (`bp_activity_get_specific` + `get_userdata` per row) remain. Big-site batching deferred.
3. **Front-end settings template inline CSS (audit #6)** — `templates/settings/notifications.php` still has raw hex/color tokens. Now that the module renders, a token pass is warranted but is front-end/UX scope, not this admin migration.
4. **Enqueue guard precision (audit #5)** — the controller now uses precise `is_our_screen()` (`/_page_bpfn-dashboard$/` OR hub), replacing the over-broad `strpos(...,'bpfn')` guard. Resolved as a side effect.

## Needs human eyes

- Browser smoke per playbook Part 13 (activate on a BuddyPress site, click both Tools buttons, save the Settings tab, save the cleanup form, dismiss the migration nag and reload, verify the BP member Settings → Favorite Notifications subnav renders + saves). This site has the plugin inactive, so the live click-through was not performed.

---

## PERF — Big-site N+1 / scale fixes (2026-06-05)

**Scope:** Performance refactor only. No behavioural / output change for realistic sizes; admin card wrapper, dead-module fix, and dismiss-AJAX from the prior pass are untouched. Targets the two N+1 hot paths flagged in `WRAPPER-AUDIT.md` / `audit/manifest.json` (`static_analysis.n_plus_one_risks`).

### Files + lines changed

1. `includes/modules/class-favorite-display.php`
   - `get_users_who_favorited()` (~L190-212): added `cache_users( $user_ids )` single-query prime before the `foreach`, so each `get_userdata()` in the loop is a cache hit instead of one query per row. Order preserved (still iterates the DB-ordered `$user_ids`).
   - `ajax_get_all_favorites()` (~L367-384): replaced the hard `limit 999` with a bounded `apply_filters( 'bpfn_who_favorited_limit', 50, $activity_id )` (floored at 1; default 50). Added a `+N more` footer (`bpfn-favorites-more`) driven by the existing `remaining` value (which derives from the `COUNT(*)` total, not from pulling rows).
   - Modal markup (~L400-413): added the `+N more` `<p>` rendered only when `remaining > 0`.
   - `clear_cache()` (~L430-445): now also deletes the bounded modal cache key (`users_{id}_{limit}_0`) and the legacy `_999_0` key, and calls `delete_transient( 'bpfn_dashboard_stats' )` to invalidate the dashboard stats cache on every favorite add/remove (via the existing `sync_favorite_add` / `sync_favorite_remove` hooks).

2. `includes/admin/views/overview.php`
   - Stats block (~L38-123): wrapped the counts + recent + trending aggregate queries in a short-TTL transient `bpfn_dashboard_stats` (`apply_filters( 'bpfn_dashboard_stats_ttl', 5 * MINUTE_IN_SECONDS )`). Repeated dashboard loads skip the `GROUP BY` scans; invalidated on favorite change (above).
   - Batch-fetch block (~L129-176): before rendering, collect every activity ID (recent + both trending windows) and resolve them with ONE `bp_activity_get( in => $ids, update_meta_cache => false, display_comments => false )` into `$bpfn_activity_map`, then prime all author/favoriter users with ONE `cache_users( $bpfn_user_ids )`.
   - `recent_activities` loop (~L261): `get_userdata()` now served from the prime (cache hit, no per-row query).
   - `$bpfn_render_trending` closure (~L304-326): `use ( $bpfn_activity_map )`; replaced the per-row `bp_activity_get_specific()` with an array lookup into the prebuilt map, and `get_userdata()` is cache-served. Order preserved (still iterates `$rows` in trending rank order).

### Batch / bound / cache approach

- **Bound:** who-favorited fetch limited to a filterable `bpfn_who_favorited_limit` (default 50) instead of 999. Overflow shown as `+N more`, where N = `COUNT(*)` total − shown — no extra rows pulled to compute it.
- **Batch:** modal/footer user list primes via `cache_users()` (1 query for N users). Dashboard primes activities via 1 `bp_activity_get( in => [...] )` and users via 1 `cache_users( [...] )`. Zero per-row `get_userdata()` / `bp_activity_get_specific()` queries remain in any render loop.
- **Cache:** per-request repeated dashboard aggregate queries cached in transient `bpfn_dashboard_stats` (5-min TTL, filterable), invalidated on favorite add/remove through `clear_cache()`.

### Verification

- `php -l` — both files: "No syntax errors detected".
- WPCS (wpcs MCP `wpcs_check_file`) — both files: "No coding standard violations found". (overview.php scope-indent from the new transient `else {` block auto-fixed via `wpcs_fix_file`; re-checked green. No new sniffs.)
- Grep confirms: `bp_activity_get_specific` no longer called (comment only); `999` only in the legacy cache-key cleanup; the bound/batch/cache constructs (`bpfn_who_favorited_limit`, `cache_users`, `bp_activity_get(`, `delete_transient`, `$bpfn_activity_map`) all present.
- Not committed / not pushed (per instruction).

### Needs human eyes (perf)

- Live big-site smoke: seed a hot activity with 500+ favorites + a 1000-row favorites table, load the dashboard, and confirm via Query Monitor that the who-liked modal issues one user query and the dashboard issues one `bp_activity_get` + one `cache_users` (plugin is inactive on this sandbox, so the live query count was not captured here).
