# Architecture

BuddyPress Favourite Notification is a modular plugin built around a singleton and a set of feature modules. This page orients a developer before extending it.

## Entry point

`bp-favorite-notification.php` defines the `BP_Favorite_Notification` singleton and the `BPFN_` constants (`BPFN_VERSION`, `BPFN_PLUGIN_PATH`, `BPFN_PLUGIN_URL`, `BPFN_ASSETS_URL`, `BPFN_INCLUDES_PATH`, `BPFN_TEMPLATES_PATH`).

Boot sequence:

1. On `plugins_loaded` (priority 5) it checks for BuddyPress. If `BuddyPress` is not present, it shows an admin notice and stops.
2. It loads the compatibility layer and the procedural helper files, and, in the admin, the `BPFN_Admin` controller.
3. On `bp_loaded` it schedules module loading and registers migration hooks.
4. On `bp_init` (priority 5) it loads the feature modules.

## Modules

Modules live in `includes/modules/` and are instantiated as `BPFN_Module_{Name}`:

| Module | Class | Responsibility |
| --- | --- | --- |
| notifications | `BPFN_Module_Notifications` | Create, remove, and format favourite notifications. |
| email | `BPFN_Module_Email` | Send the HTML email on `bpfn_after_add_notification`. |
| realtime | `BPFN_Module_Realtime` | Heartbeat and AJAX-fallback popups. |
| assets | `BPFN_Module_Assets` | Enqueue front-end CSS and JS. |
| admin | `BPFN_Module_Admin` | Admin AJAX (clear/migrate/progress), Tools and Display save handlers, auto-cleanup cron. |
| settings | `BPFN_Module_Settings` | Front-end member preference screen and BuddyPress notification-settings row. |
| favorite_display | `BPFN_Module_Favorite_Display` | The "who liked this" display and its AJAX endpoints. |

Access a module from the singleton: `bpfn()->get_module( 'notifications' )` or the helper `bpfn_get_module( 'notifications' )`. Register your own with `bpfn_register_module()`.

## Compatibility layer

`includes/compat/buddypress-compat.php` registers the `favorite_notifier` pseudo-component on `bp_setup_globals`, adds it to `bp_notifications_get_registered_components`, provides the notification-formatting callback, and runs a safety-net favourite handler on `bp_activity_add_user_favorite` (priority 15) in case the module's handler (priority 10) does not fire.

## Procedural helpers

`includes/functions/` holds the public-facing procedural API:

- `core-functions.php`: user settings, activity-type mapping, the enabled check, notification counts, event logging.
- `api-functions.php`: notification and module helpers.
- `template-functions.php`: template output helpers and template-part loading.
- `integration-functions.php`: the old-notification cleanup routine.

See [Helper Functions](helper-functions.md).

## Admin controller

`includes/admin/class-bpfn-admin.php` (`BPFN_Admin`) owns the single admin page under the shared `wbcomplugins` hub, the tab list, asset enqueue, the migration notice, and the notice-dismissal AJAX handler. Tab views live in `includes/admin/views/`.

## Migration

`includes/migrations/class-favorites-migration.php` (`BPFN_Favorites_Migration`) moves pre-existing favourites into the `{prefix}bp_activity_favorites` table in batches.

## What the plugin does not use

There is no REST API, no Gutenberg blocks, no shortcodes, no custom post types, and no WordPress Settings API options page. Admin options are persisted through hand-rolled, nonce-checked POST handlers.
