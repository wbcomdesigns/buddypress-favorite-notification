# Wrapper Audit — buddypress-favorite-notification

**Branch:** 2.0.0  
**Date:** 2026-06-05  
**Wrapper type:** NONE (standalone custom admin page, no card-panel shell)  
**Auditor:** AutoVAP read-only pass per WRAPPER-AUDIT-BRIEF.md §"For NON-migrated plugins"

---

## 1. NONE Classification — Still Accurate? YES

Evidence:
- No `includes/admin/views/shell.php` exists.
- No `includes/admin/wbcom/`, `includes/shared-admin/`, or any reference to the legacy `wbcomplugins` parent slug anywhere in the plugin source.
- No `[wbcom_admin_setting_header]` shortcode reference found.
- The single active admin page is `bpfn-dashboard`, registered as a **top-level** `add_menu_page()` with `dashicons-heart` at position 30 — this is the plugin's own standalone page, not a submenu under the shared Wbcom hub.

NONE classification is **confirmed correct**.

---

## 2. Option Wiring — End-to-End

### 2a. Settings API option: `bpfn_options` (field `enable_enhanced_notifications`)

| Check | Status | Evidence |
|-------|--------|----------|
| Registered | PASS | `register_setting('bpfn_settings', 'bpfn_options', …)` — `class-admin.php:758`. Single registration (the dead `class-settings.php:250` duplicate never runs at runtime). |
| Rendered | PASS | `BPFN_Module_Admin::field_checkbox()` emits `<input name="bpfn_options[enable_enhanced_notifications]">` inside a `settings_fields('bpfn_settings')` form on the Settings section of `bpfn-dashboard`. Reads current value via `get_option('bpfn_options', [])`. |
| Saved + sanitized | PASS | `sanitize_options()` at `class-admin.php:871` runs on save. Sanitization casts to int 1 (present) or omits the key (absent). Nonce + capability handled by WordPress Settings API. |
| Read back on reload | PASS | `field_checkbox()` calls `get_option('bpfn_options', [])` and applies `checked()` — field reflects saved value correctly. |
| Consumed by feature | PASS | `bpfn_is_feature_enabled('enhanced_notifications')` at `api-functions.php:243` reads `$options['enable_enhanced_notifications']`; consumed by `templates/notifications/notification-item.php:43` via `bpfn_use_enhanced_template` filter. Full wire intact. |

Verdict: **WIRED CORRECTLY end-to-end**.

Minor note: `sanitize_options()` in `class-admin.php` stores the value as bare `1` (integer), while `class-settings.php` (dead) would have stored `absint()`. Both produce equivalent results; not a functional gap.

---

### 2b. Standalone options (custom POST handler in Tools section)

| Option key | Registered | Rendered | Saved + sanitized | Read back | Verdict |
|------------|-----------|----------|------------------|-----------|---------|
| `bpfn_auto_cleanup_enabled` | N/A (standalone `update_option`) | `class-admin.php:655` — checkbox, reads `get_option('bpfn_auto_cleanup_enabled', 'yes')` | `class-admin.php:806` — nonce `bpfn_cleanup_settings` + `current_user_can('manage_options')` + checkbox-presence pattern → `'yes'/'no'` | `class-admin.php:655` field + `setup_automatic_cleanup():1078` + `run_automatic_cleanup():1089` | PASS |
| `bpfn_auto_cleanup_days` | N/A | `class-admin.php:676` — `<select>` with five hard-coded values, reads `get_option('bpfn_auto_cleanup_days', 30)` | `class-admin.php:809` — `absint()` + floor at 7 | `class-admin.php:676` select + `run_automatic_cleanup():1095` | PASS |
| `bpfn_last_auto_cleanup` | N/A (state, write-only from cron) | `class-admin.php:700` — read-only display block | `run_automatic_cleanup():1106` — array with date/deleted/remaining | `class-admin.php:700` | PASS (read-only display, no user input) |

---

### 2c. State options (not user-editable)

`bpfn_version`, `bpfn_show_migration_notice`, `bpfn_favorites_migrated`, `bpfn_migration_status`, `bpfn_migration_log` — all written by activation or migration code, read by admin display and migration logic. No user-facing form fields; no wiring gaps to verify.

---

### 2d. Multi-tab data-loss guard

Not applicable. The Settings API form writes one option (`bpfn_options`). The cleanup form writes two separate standalone keys via `update_option()`. There is no multi-tab merging scenario.

---

### 2e. Duplicate `register_setting` issue (low risk, still worth fixing)

`class-settings.php:250` also calls `register_setting('bpfn_settings', 'bpfn_options', …)` with a slightly different sanitizer (`absint` vs bare `1`). Because `BPFN_Module_Settings` is never instantiated this registration never fires at runtime — but the duplicate definition is a maintenance hazard: if the module is ever loaded by accident both sanitizers would run sequentially on the same `admin_init` hook, with the `class-settings.php` version winning. **This is a latent bug, not an active one.**

---

## 3. Dead Code Confirmation

### 3a. `BPFN_Module_Settings` — CONFIRMED DEAD

The `load_modules()` map at `bp-favorite-notification.php:159–166` lists exactly six keys:
`notifications`, `email`, `realtime`, `assets`, `admin`, `favorite_display`.

`class-settings.php` is **not** in that list. The class-name derivation is `'BPFN_Module_' . ucfirst($key)`, so `BPFN_Module_Settings` is never instantiated. Confirmed at runtime by the absence of `class-settings.php` require anywhere outside its own file.

**Consequences (all currently silent):**

1. The `add_options_page('bpfn-settings', …)` call at `class-settings.php:237` never runs — no duplicate Settings → BP Favorite Notification page appears in WP admin.
2. `bp_core_new_subnav_item(…'slug' => 'notifications'…)` at `class-settings.php:63` never runs — **the BuddyPress member Settings > Favorite Notifications subnav does not exist**.
3. `handle_settings_save()` at `class-settings.php:117` (hooked on `bp_actions`) never runs — **per-user web/email/realtime toggle saves via the subnav UI are not processed**.
4. `notification_settings()` at `class-settings.php:169` (hooked on `bp_notification_settings`) never runs — the integration into BP's built-in Notifications settings table is not rendered.
5. The duplicate `register_setting()` at `class-settings.php:250` never runs — latent sanitizer conflict is dormant.

**Impact level: HIGH.** The per-user notification preference UI is entirely absent. Users cannot control whether they receive web, email, or realtime notifications for their activity being favorited. The preference table (`wp_bp_favorite_notification_prefs`) exists but is never populated via the intended UI path.

Note: `bpfn_get_user_settings()` returns defaults (`is_enabled=1`, `email_enabled=1`, `realtime_enabled=1`) for all users who have never explicitly saved — so notifications fire by default. The feature still works; users simply have no control.

---

### 3b. `bpfn_dismiss_migration_notice` AJAX — CONFIRMED DEAD

The inline `<script>` block at `class-admin.php:1060–1066` posts `action: 'bpfn_dismiss_migration_notice'` when the admin clicks the migration notice's dismiss button. A search across all PHP files finds **zero** `add_action('wp_ajax_bpfn_dismiss_migration_notice', …)` registrations.

**Consequence:** Dismissing the migration notice hides it in the DOM immediately (via WP's native `.notice-dismiss` handler), but `bpfn_show_migration_notice` in the database is never deleted. On the next page load the notice reappears as if it was never dismissed. The nonce `bpfn-dismiss-notice` is created but never validated because no handler receives the POST.

**Impact level: MEDIUM.** Admin experience only; functional migration is unaffected. The notice vanishes only once `delete_option('bpfn_show_migration_notice')` is called from `migration_notice()` itself when migration completes, not from dismiss.

---

## 4. Path-Constant Check — PASS

All six constants are defined in `bp-favorite-notification.php` (root file, `__FILE__` = plugin root):

```
BPFN_PLUGIN_FILE  = plugin root file
BPFN_PLUGIN_URL   = plugin_dir_url(__FILE__)    → https://…/buddypress-favorite-notification/
BPFN_PLUGIN_PATH  = plugin_dir_path(__FILE__)   → …/buddypress-favorite-notification/
BPFN_ASSETS_URL   = BPFN_PLUGIN_URL . 'assets/'
BPFN_INCLUDES_PATH = BPFN_PLUGIN_PATH . 'includes/'
BPFN_TEMPLATES_PATH = BPFN_PLUGIN_PATH . 'templates/'
```

No secondary `__FILE__`-based path definitions exist in any `includes/` file. All view includes and asset enqueue calls use these constants correctly. No 404-producing mis-resolved path found.

---

## 5. Hygiene Checks

### 5a. native `confirm()` in admin JS — FAIL (3 instances)

| Line | Context |
|------|---------|
| `assets/js/admin.js:223` | Migration: `confirm('This will migrate all existing favorites…')` |
| `assets/js/admin.js:332` | Clear old notifications: `confirm('Are you sure you want to clear all read notifications older than 30 days?…')` |
| `assets/js/admin.js:415` | Bulk update: `confirm('This will update notification settings for all users…')` |

Native `confirm()` is banned by admin-UX Rule 10. These must be replaced with the Wbcom modal helper (or WP's native dialog approach). No native `alert()` calls found.

### 5b. Enqueue screen-scoping — PASS (with caveat)

`enqueue_admin_assets()` at `class-admin.php:108` guards on `$this->admin_hooks` (the hook suffix from `add_menu_page`) OR a `$_GET['page']` containing `'bpfn'`. The hook array is populated correctly from the single `add_menu_page` call. Assets do not load on every admin page.

Caveat: The `$_GET['page']` fallback substring check (`strpos(…, 'bpfn')`) is a broad guard — a third-party page slug containing `'bpfn'` would accidentally load these assets. Low real-world risk but not precise.

### 5c. Admin CSS tokens — PARTIAL FAIL

`assets/css/admin.css` was not audited line-by-line here, but `templates/settings/notifications.php` embeds inline CSS with:
- `background-color: var(--bpfn-primary-color, #ff7b00)` — CSS variable fallback (acceptable pattern)
- `background-color: #ccc` — raw hex (token violation)
- `background-color: white` — bare color name (token violation)
- `color: #666` — raw hex (token violation)

These are in the dead settings template (never rendered), but the pattern would carry over to any future activation.

### 5d. Tap targets — not audited (page not rendered in browser during this audit)

---

## 6. Severity-Ranked Findings

| # | Severity | Finding | File:line |
|---|----------|---------|-----------|
| 1 | HIGH | `BPFN_Module_Settings` never instantiated — BP Settings subnav, per-user web/email/realtime preference form, and `bp_notification_settings` integration are all non-functional at runtime. Users have no UI to opt out of notification channels. | `bp-favorite-notification.php:159–166` (missing from module map); `includes/modules/class-settings.php` (entire file) |
| 2 | MEDIUM | `bpfn_dismiss_migration_notice` AJAX action has no PHP handler. Migration notice cannot be permanently dismissed; it reappears on every page load until migration completes. | `includes/modules/class-admin.php:1060–1066` (inline JS); no PHP registration anywhere |
| 3 | MEDIUM | Three native `confirm()` calls in admin JS violate admin-UX Rule 10. | `assets/js/admin.js:223, 332, 415` |
| 4 | LOW | Latent duplicate `register_setting()` — if `BPFN_Module_Settings` is ever loaded the sanitizer in `class-settings.php` will silently win over the one in `class-admin.php`. | `includes/modules/class-settings.php:250` vs `includes/modules/class-admin.php:758` |
| 5 | LOW | `$_GET['page']` substring fallback in enqueue guard is over-broad (`strpos(…, 'bpfn')`). | `includes/modules/class-admin.php:113` |
| 6 | LOW | Raw hex/color tokens in the dead settings template inline CSS (`#ccc`, `white`, `#666`, `#ff7b00` fallback). Will need token-replacement if the template is ever activated. | `templates/settings/notifications.php:109–167` |

---

## 7. Recommendation — Should this plugin adopt the card-panel wrapper?

**Yes, recommended as a future polish pass (low urgency, medium payoff).**

The `bpfn-dashboard` page uses native WP `postbox`/`<div class="wrap">` markup, which is functional but visually inconsistent with the card-panel pages in the Wbcom portfolio (buddypress-contact-me and migrated peers). Adopting the wrapper would require: (1) adding `includes/admin/views/shell.php` + per-section view files, (2) registering the page under `wbcomplugins` parent, (3) moving the three inline sections into tab views. Effort: ~3–4 hours. Payoff: visual consistency in the WP admin, tab-URL persistence, and it resolves finding #5 (screen guard) as a side-effect. Not blocking for shipping; classify as a future UI-consistency task.

---

## Overall Verdict

**FIX-NEEDED**

Must-fix before claiming the plugin is fully functional:
1. **Finding #1 (HIGH)** — Load `BPFN_Module_Settings` in `load_modules()` or explicitly document that per-user prefs are out-of-scope and remove the dead class. The current state silently ships a plugin where users cannot control their notification preferences despite a fully-built UI existing in the codebase.
2. **Finding #2 (MEDIUM)** — Register `wp_ajax_bpfn_dismiss_migration_notice` handler to persist notice dismissal.
3. **Finding #3 (MEDIUM)** — Replace `confirm()` calls with the Wbcom modal helper (Rule 10).

Findings #4–6 are maintenance/polish items that can be deferred.
