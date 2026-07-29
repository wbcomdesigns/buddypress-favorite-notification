<!-- READ FIRST -->
# CLAUDE.md — BuddyPress Favorite Notification

> READ FIRST: This plugin is onboarded. Before grepping, read **`audit/manifest.json`**
> (canonical inventory) and the human reports in `audit/`:
> - `audit/FEATURE_AUDIT.md` — feature-by-feature inventory (STALE: 2026-06-05, predates 2.0.x)
> - `audit/CODE_FLOWS.md` — trigger → handler → output pipelines (STALE: 2026-06-05)
> - `audit/graph.html` — interactive manifest graph (STALE: 2026-06-05)
> - `audit/wppqa-baseline-2026-06-05/SUMMARY.md` — superseded bug baseline; the live bug
>   list is `manifest.json` → `static_analysis` + "Known issues" below
>
> `manifest.json` was fully rescanned against shipped code on **2026-07-16** and is current.
> The other four artefacts still predate the 2.0.x admin migration — do not trust them on
> admin pages, settings, modules, or hooks. Answer "what does X do / where is Y" from the
> manifest, not a fresh scan.

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
  creates 2 custom tables on activation, loads 7 modules on `bp_init`.
- Modules (`includes/modules/class-*.php`): notifications, email, realtime, assets, admin,
  settings, favorite_display.
- BP pseudo-component `favorite_notifier` registered in `includes/compat/buddypress-compat.php`.
- Procedural helpers in `includes/functions/` (api/core/template/integration).
- Migration: `includes/migrations/class-favorites-migration.php` (usermeta → table, batched).
- No REST, no blocks, no shortcodes, no CPTs, no `register_setting` (all verified 0 hits, 2026-07-16).

## Admin UI
- ONE admin page: submenu **`bpfn-dashboard`** under the shared **WB Plugins hub**
  (`wbcomplugins`), cap `manage_options`, rendered by `BPFN_Admin::render_page()`
  (`includes/admin/class-bpfn-admin.php`) with the modern card-panel shell
  (`includes/admin/views/shell.php` + `overview.php` / `display.php` / `tools.php` /
  `discover.php`). Four tabs (`BPFN_Admin::get_tabs()`, filter `bpfn_admin_tabs`):
  **Overview** (stats, trending, quick actions), **Display** (favorite display mode +
  icon), **Tools** (migration, cleanup), **Discover** (ecosystem cards).
- There is still NO Settings API options page — `register_setting()` is used nowhere.
  The Display tab persists via a hand-rolled POST handler like Tools does:
  `BPFN_Module_Admin::handle_display_settings_save()`, nonce `bpfn_display_settings`,
  cap `manage_options`. Both values are validated against
  `BPFN_Module_Favorite_Display::get_display_modes()` / `::get_icon_choices()` rather
  than merely sanitized, so a crafted POST cannot persist a mode the renderer has no
  branch for.
- The former Settings tab's only field ("Enhanced Notifications", option `bpfn_options`)
  was removed on branch 2.0.0: its enhanced template could never render — BuddyPress
  kses-strips notification descriptions to `<a href class>` on every surface.
- `BPFN_Module_Settings` (`includes/modules/class-settings.php`) is **front-end only**
  and IS loaded: BP member **Settings → Favorite Notifications** subnav + per-user
  save handler (template `templates/settings/notifications.php`, styles
  `assets/css/settings.css`).

## Settings / options
- Standalone options: `bpfn_auto_cleanup_enabled`, `bpfn_auto_cleanup_days`,
  `bpfn_last_auto_cleanup`, `bpfn_version`, `bpfn_show_migration_notice`,
  `bpfn_favorites_migrated`, `bpfn_migration_status`, `bpfn_migration_log`,
  `bpfn_display_mode` (default `inline`), `bpfn_favorite_icon` (default `heart`).
- `bpfn_options` (group `bpfn_settings`) is RETIRED — never registered or written. One stale
  reader survives: `includes/functions/integration-functions.php:562` dumps it into
  `bpfn_get_diagnostics()`. Harmless (defaults to `array()`), but it is a read-never-written key.
- There is NO `register_setting()` anywhere. The Tools tab persists its two options via a
  hand-rolled POST handler (`class-admin.php:80-128`, nonce `bpfn_cleanup_settings`).
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

## Known issues (updated 2026-07-16)
All four baseline (2026-06-05) issues are FIXED on branch 2.0.0: (1) the settings
module is loaded (front-end only), (2) native `confirm()` replaced by the
`bpfnConfirm` modal, (3) `bpfn_dismiss_migration_notice` has a real handler in
`BPFN_Admin`, (4) who-favorited/trending N+1 eliminated (batched, capped, cached).
The 2026-07-03 audit's Blocker (dead "Enhanced Notifications" toggle) and Major
findings (dead legacy admin.js + notifications.js, inline member-settings styles,
untokenized realtime.css, Reign dark-mode contrast) are also fixed.

**FIXED 2026-07-17 (branch 2.0.0) — the preference blocker.** All three causes were
fixed together and verified on a pristine WP 7.0 + BP 14.5 install with `DOING_AJAX`
defined, going through `bp_activity_add_user_favorite()` (preference ON -> 1
notification; OFF -> web/email false, 0 notifications; compat-only ON -> 1, OFF -> 0):
1. `bpfn_is_notification_enabled()` no longer short-circuits on `DOING_AJAX`. **Do not
   reintroduce that branch** — BP favoriting always posts via admin-ajax, so it made the
   function return true for every real favorite and the preference read below it was dead
   code. There is a comment in place saying so.
2. `bpfn_compat_add_notification()` (`buddypress-compat.php`, prio 15) now resolves the
   type the same way `BPFN_Module_Notifications` does and honours the preference. It
   still fires as a safety net when the module does not — verified both ways.
3. `bpfn_get_activity_type()` mapped `activity_update` (a normal BP post, the most common
   type) to an `activity_update` preference key that the settings screen never renders or
   saves, so the lookup hit the default `1` and **email** ignored the member's choice. The
   map now sends it to `activity_post`. **Keep `bpfn_get_activity_type()`'s map in step
   with `BPFN_Module_Settings::get_notification_types()`** — they must agree, or a channel
   silently checks a key nothing writes.
- The two production-path `error_log()` calls in `core-functions.php` are removed. The one
  at `class-notifications.php:104` remains: it only fires when the component failed to
  initialise. `bpfn_compat_verify_registration()` is already `WP_DEBUG`-guarded.

**OPEN — needs a card, not yet fixed:**
- **Major — dead UI.** `BPFN_Module_Settings::notification_settings()` (`class-settings.php:44`)
  renders radios named `notifications[favorite_activity]` (`:194`, `:200`); BP core saves
  that to user meta `favorite_activity`, a key the plugin never reads. This is a second,
  separate settings surface from the working **Settings → Favorite Notifications** screen.

`audit/manifest.json` was fully rescanned and is current as of 2026-07-16.
`AUDIT-VERDICT.md` still predates the 2.0.x removals — treat it as stale.

## Favorite display (`includes/modules/class-favorite-display.php`)
- **One renderer, two callers.** `render_display()` is the ONLY place the activity-stream
  markup is built. Both `display_favorite_count()` (server render) and
  `ajax_refresh_favorite_display()` (post-favorite refresh) call it. Before 2.1.0 these
  were two verbatim copies, so the icon lived in two places and any format change
  reverted the instant a member clicked like. **Do not re-inline markup into either
  caller** — a change that lands in one and not the other is invisible until someone
  favorites something.
- Three modes (`bpfn_display_mode`): `inline` (names), `counter` (static `<span
  role="img">`), `modal` (a `<button>` opening the paginated list). `counter` must stay
  non-interactive — rendering a button there puts a dead control in the tab order.
- Public display filters: `bpfn_favorite_icon_html`, `bpfn_favorite_display_format`,
  `bpfn_favorite_display_html` (full override), `bpfn_display_modes`,
  `bpfn_favorite_icons`, `bpfn_favorites_modal_per_page`. Everything the renderer emits
  — including a third-party `bpfn_favorite_display_html` return — goes through
  `wp_kses( …, get_allowed_display_html() )`. New attributes (e.g. `role`) must be added
  to that allow list or they are silently stripped.
- **Cache is versioned, not enumerated.** Every count/user-list key carries
  `get_cache_incrementor( $activity_id )`; `clear_cache()` bumps that one value.
  Do NOT go back to deleting a hand-maintained list of key shapes — the modal paginates
  with arbitrary offsets, so any such list is incomplete by construction and serves a
  stale list for the full 5-minute TTL.
- **The counter is a `<button>`, so BuddyPress styles it.**
  `.buddypress .buddypress-wrap button` (0,2,1) sets a white box + grey border and
  buddypress.min.css loads after ours. Counter CSS selectors must EXCEED that
  specificity, not tie — BP wins ties on load order.

## Conventions
- Prefix everything `bpfn_` / `BPFN_`. Text domain `buddypress-favorite-notification`.
- Custom-table queries are deliberately direct (`$wpdb`) with object caching + inline
  `phpcs:ignore`. Public favorite-display AJAX is `nopriv` by design (read-only).
- Commit/PR per global rules: branch off, no co-author/footer lines.

## Big-site checklist reminders (per global CLAUDE.md)
- Who-liked modal and trending dashboard are the rows-at-scale surfaces — fix N+1 (batch
  `WP_User_Query` with `include`) and paginate before claiming big-site readiness.
