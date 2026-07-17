# wppqa Baseline — buddypress-favorite-notification

- Date: 2026-06-05
- Branch: 2.0.0
- Plugin version: 2.0.1
- Tooling: wp-plugin-qa MCP (3 required checks)

## Per-check result

| Check | Passed | Failed | Skipped |
|---|---|---|---|
| `wppqa_check_plugin_dev_rules` | 4 | 5 | 0 |
| `wppqa_check_rest_js_contract` | 0 | 0 | 1 (no REST routes) |
| `wppqa_check_wiring_completeness` | 0 | 0 | 1 (no templates/ setting reads detected) |

Release-readiness gate: FAILED (`plugin_dev_rules` has 5 high findings). Not release-ready until the 3 real `confirm()` violations are resolved (the 2 nonce findings are false positives — see below).

## plugin_dev_rules findings (5 high) — triaged

### REAL (3) — Rule 10 `confirm()` ban
- `assets/js/admin.js:223` — confirm() before "migrate all favorites".
- `assets/js/admin.js:332` — confirm() before "clear notifications older than 30 days".
- `assets/js/admin.js:415` — confirm() before "update notification settings for all users".

Fix: replace native `confirm()` with a modal confirmation dialog per the ux-foundation / wp-plugin-development admin-UX rulebook (Rule 10).

### FALSE POSITIVE (2) — `nonce-no-cap` on public endpoints
- `includes/modules/class-favorite-display.php:344` — `ajax_get_all_favorites`.
- `includes/modules/class-favorite-display.php:408` — `ajax_refresh_favorite_display`.

Both actions are registered with `wp_ajax_nopriv_` (lines 62-65) as intentionally public, read-only favorite-display endpoints. A `current_user_can()` check would break the documented design (logged-out visitors can see who liked an activity). The checker does not model `nopriv` actions and flags every nonce-only handler. Pre-triaged as false positive per the onboarding environment note. The handlers DO verify the `bpfn-favorite-nonce` nonce and only read aggregate favorite data (no writes, no PII beyond public display names/avatars).

## rest_js_contract
Skipped — plugin registers zero REST routes. No envelope-mismatch risk surface.

## wiring_completeness
Skipped — the checker only inspects `templates/` for setting reads. This plugin's only real persisted admin setting (`bpfn_options.enable_enhanced_notifications`) is consumed in the service/function layer (`includes/functions/*`), not in `templates/`, so the checker has nothing to compare. Manually confirmed `enable_enhanced_notifications` is read via the enhanced-template path — not a half-wired setting.

## Findings the manifest analysis added (not from wppqa)
1. `BPFN_Module_Settings` (class-settings.php) is dead code — never instantiated by `load_modules()`. Its admin options page, BP Settings subnav, and front-end per-user save handler never run.
2. `wp_ajax_bpfn_dismiss_migration_notice` has no PHP handler — dismiss never persists.
3. N+1 in `get_users_who_favorited` (limit 999 modal path) and `render_trending_activities`.
