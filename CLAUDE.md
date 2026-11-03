<!-- READ FIRST -->
# CLAUDE.md — BuddyPress Favorite Notification

> READ FIRST: This plugin is onboarded. Before grepping, read **`audit/manifest.json`**
> (canonical inventory) and the human reports in `audit/`:
> - `audit/FEATURE_AUDIT.md` — feature-by-feature inventory (STALE: 2026-06-05, predates 2.0.x)
> - `audit/CODE_FLOWS.md` — trigger → handler → output pipelines (STALE: 2026-06-05)
> - `audit/graph.html` — interactive manifest graph (STALE: 2026-06-05)
> - `audit/wppqa-baseline-2026-06-05/SUMMARY.md` — superseded bug baseline; the live bug
>   list is Basecamp; `manifest.json` → `static_analysis` is the audit snapshot
>
> `manifest.json` was delta-updated for 2.2.0 on **2026-10-08** (hooks, AJAX, options, emails).
> The other four artefacts still predate the 2.0.x admin migration — do not trust them on
> admin pages, settings, modules, or hooks. Answer "what does X do / where is Y" from the
> manifest, not a fresh scan.

## What this is
Free Wbcom Designs BuddyPress addon. Sends a BP notification, a BuddyPress Email and (optionally)
a realtime popup when a member's activity/comment is favorited, and renders a "X and N others
liked this" line. Requires BuddyPress 12.0+ (URL API). Version 2.2.0, developed on
`release/2.2.0`, released from `master`.

## Development skill — follow this
All plugin work MUST follow **`/wp-plugin-development`** (canonical Wbcom plugin skill):
backend architecture, REST patterns, DB, security/escaping, **Part 6 Admin UI**, the
**16 critical admin rules**, design tokens, and dev hygiene. UI/CSS/a11y/dark-mode/RTL work
follows **`/ux-foundation`**; audit drift with **`/ux-audit`**. Onboarding artefacts in
`audit/` are owned by **`/wp-plugin-onboard`** — regenerate, never hand-edit entries.

## Architecture (90-second orientation)
- Entry: `bp-favorite-notification.php` - singleton `BP_Favorite_Notification`, `BPFN_` constants.
  `check_dependencies()` (plugins_loaded 5) loads `includes/functions/*` and hooks `init()` only when
  BuddyPress 12.0+ is active. `maybe_upgrade()` (activation + bp_init 20) runs once per version.
- Modules (`includes/modules/class-*.php`, loaded on bp_init 5): notifications, email, realtime,
  assets, admin, settings, favorite_display.
- **One owner per job - do not add a second path:**
  - Notification create/remove/format: `BPFN_Module_Notifications` only. BP reaches the formatter
    through the component's `notification_callback` (`bpfn_compat_format_notifications()`).
  - Email: `BPFN_Module_Email` hooks `bp_activity_add_user_favorite` itself (NOT
    `bpfn_after_add_notification`, or email would depend on the web channel) and sends with
    `bp_send_email()`. Types `bpfn-activity-favorited` / `bpfn-comment-favorited`, installed by
    `BPFN_Module_Email::install()` (never overwrites an owner's edited post; re-run on BP's
    `bp_core_install_emails`). Unsubscribe via `bp_email_get_unsubscribe_type_schema`.
  - Preference type of an activity: `bpfn_get_activity_type()`; of a stored action:
    `BPFN_Module_Notifications::get_type_for_action()`.
  - Member preferences: ONE store, `{prefix}bp_favorite_notification_prefs`, via
    `bpfn_get_user_settings()` / `bpfn_save_user_settings()` (object-cached per user).
- `includes/compat/buddypress-compat.php` only registers the `favorite_notifier` pseudo-component.
- Migration: `includes/migrations/class-favorites-migration.php` (usermeta -> table, batched).
- No REST, no blocks, no shortcodes, no CPTs, no `register_setting`.

## Admin UI
- ONE admin page: submenu **`bpfn-dashboard`** under the shared **WB Plugins hub**
  (`wbcomplugins`), cap `manage_options`, rendered by `BPFN_Admin::render_page()` with the
  card-panel shell (`includes/admin/views/shell.php` + `overview.php` / `display.php` /
  `tools.php` / `discover.php`). Tabs via `BPFN_Admin::get_tabs()` (filter `bpfn_admin_tabs`).
  Activation redirects here once (transient `bpfn_activation_redirect`, skipped for bulk).
- Both forms are hand-rolled POST handlers in `BPFN_Module_Admin` (no Settings API):
  Display (`bpfn_display_settings` nonce) saves mode, icon, realtime switch + interval; Tools
  (`bpfn_cleanup_settings`) saves cleanup switch + retention.
- **Every option has ONE accessor that validates against its allowed set; the save handler, the
  view and the runtime all call it.** Never read these options with a bare `get_option()`:
  `BPFN_Module_Favorite_Display::get_saved_mode()` / `get_saved_icon()`,
  `BPFN_Module_Realtime::is_enabled()` / `get_interval()` (30|60),
  `BPFN_Module_Admin::is_auto_cleanup_enabled()` / `get_retention_days()` (7/15/30/60/90).
- Flash notices are `.bpfn-notice ... notice is-dismissible inline` so core adds the close button
  without moving them out of the shell.

## Settings / options
- Owner options: `bpfn_display_mode` (inline), `bpfn_favorite_icon` (heart),
  `bpfn_realtime_enabled` (unset = off), `bpfn_realtime_interval` (30),
  `bpfn_auto_cleanup_enabled` (unset = off), `bpfn_auto_cleanup_days` (30),
  `bpfn_last_auto_cleanup` (`date` + `deleted`/`remaining`, or `date` + `error`), `bpfn_version`,
  migration options. Defaults for realtime/cleanup are OFF for new installs; `maybe_upgrade()`
  writes 'yes' once for sites upgrading from < 2.2.0 (owner decision, 2026-10-08).
- Member UI: BP **Settings > Email** gets two rows (`notifications[favorite_activity]`,
  `notifications[favorite_activity_comment]`); BP saves them - and BP unsubscribe links - as user
  meta, which `BPFN_Module_Settings::mirror_email_meta()` copies into the prefs table, and
  `bpfn_save_user_settings()` writes back (keys in `bpfn_email_meta_keys()`). The plugin's own tab
  is slug **`favorite-notifications`** (never `notifications` - that is BP's Email tab) with Web +
  Real-time only; the Real-time column is hidden, and its stored value kept, while the owner switch
  is off.
- Timestamps: `favorited_at` and BP's `date_notified` are GMT. Compare with `UTC_TIMESTAMP()`,
  never `NOW()` (MySQL server clock). Render with `wp_date()`.

## Frontend assets / design tokens
- Shared `--bpfn-*` design tokens live in `assets/css/notifications.css` (the style
  dependency of favorite-display.css, realtime.css, and settings.css). Light values
  consume BuddyX `--bx-color-*` vars with light fallbacks; `[data-bx-mode="dark"]`
  and the `auto` + `prefers-color-scheme: dark` blocks redeclare them with
  DARK-appropriate fallback literals because **Reign 8.0.3 sets `data-bx-mode` but
  does not define `--bx-color-*`** — the fallback literal is what renders there.
- `assets/js/notifications.js` was deleted on 2.0.0 (100% dead: wrong selectors,
  AJAX actions without handlers). Frontend JS is favorite-display.js + realtime.js.

## Things that look removable but are load-bearing
- `bpfn_is_notification_enabled()` must never short-circuit on `DOING_AJAX`: BP favoriting always
  posts through admin-ajax.
- The two who-favorited AJAX endpoints are members-only and check `bp_activity_user_can_read()`;
  `display_favorite_count()` renders nothing for visitors.
- `realtime.js` concatenates server data into HTML; the server escapes it in
  `BPFN_Module_Realtime::format_realtime_notification()`.
- `bin/check-cleanup.php` (`wp eval-file`) is the regression check for the retention gate and the
  GMT cleanup window. Not shipped (Gruntfile `!bin/**`), excluded from PHPStan.

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

## Handoff sequence — do these IN THIS ORDER, every card

**Verify fixes → commit → push → move card → comment → Slack reply.**

Verification comes FIRST and is not optional. Nothing downstream is allowed to start
until the change is verified in a browser against the state QA will actually pull.

1. **Verify fixes.** Browser-verify every mode/state/viewport the change touches, plus
   the surfaces adjacent to it. Re-run PHPStan + WPCS. Verify against the committed
   tree, not a half-saved working copy — confirm `git status` is clean and
   `git diff HEAD origin/<branch>` is empty, so what you tested is what QA pulls.
   Synthetic checks lie: a programmatic `.click()` does not move focus, so it will pass
   a focus-restore test that a real user fails. Drive the real interaction.
2. **Commit** on a feature branch (never straight to master/main).
3. **Push** to origin. A card at Ready for Testing pointing at an unpushed branch means
   QA tests the OLD code and bounces it — see [[release-zip-must-equal-repo]].
4. **Move the card** to Ready for Testing, then re-fetch it to confirm the move landed.
5. **Comment on the card**: what shipped, what to test (numbered, with the specific
   regression case called out), any correction to the reporter's diagnosis, and every
   known/pre-existing gap. Never let a comment read as a cleaner bill of health than
   the work earned.
6. **Slack reply** in the plugin's thread, tagging the reporter.

Steps 4-6 are team-visible and step 3 is a push: confirm with the owner before the
first one unless already told to run the whole sequence.

**QA cards are entry points, not specs.** The reporter is a tester, not an architect —
their proposed implementation plan can be wrong, including confident claims that
something "already works". Audit every surface the card points at before coding, and
correct the record on the card. (On the 2.1.0 counter+modal card, all three of the
plan's technical claims were wrong and following it verbatim would have shipped a
display that reverted on the first click.)

## Conventions
- Prefix everything `bpfn_` / `BPFN_`. Text domain `buddypress-favorite-notification`.
- Custom-table queries are deliberately direct (`$wpdb`) with object caching + inline
  `phpcs:ignore`. Public favorite-display AJAX is `nopriv` by design (read-only).
- Commit/PR per global rules: branch off, no co-author/footer lines.

## Big-site checklist reminders (per global CLAUDE.md)
- Who-liked modal and trending dashboard are the rows-at-scale surfaces — fix N+1 (batch
  `WP_User_Query` with `include`) and paginate before claiming big-site readiness.
