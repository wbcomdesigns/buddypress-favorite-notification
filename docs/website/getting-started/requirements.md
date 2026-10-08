# Requirements

BuddyPress Favourite Notification is a BuddyPress add-on. It cannot run on its own.

## Minimum versions

| Requirement | Minimum |
| --- | --- |
| WordPress | 6.5 |
| PHP | 8.0 |
| BuddyPress | Installed and active |

These values come from the plugin header (`Requires at least: 6.5`, `Requires PHP: 8.0`, `Requires Plugins: buddypress`) and are enforced by WordPress at install time. The plugin has been tested up to WordPress 7.0.

## BuddyPress components

The plugin extends two BuddyPress components:

- **Activity** is required. Favouriting happens on activity items, and the "who liked this" display renders inside the activity stream.
- **Notifications** is required for the notification, realtime, and email surfaces. The plugin registers a pseudo-component named `favorite_notifier` during BuddyPress setup and only proceeds if the Notifications component is active. If Notifications is disabled, no notifications are created, the realtime popups do not run, and the front-end assets are not loaded.
- **Settings** is used for the member preference screen. If the Settings component is off, the "Favorite Notifications" subnav is not added and the email links fall back to the site home URL.

Enable these under **BuddyPress > Settings > Components**.

## Themes

The plugin follows the BuddyPress Nouveau and Legacy template standards, so it works with any BuddyPress-compatible theme. Its front-end colours chain through the active theme palette (BuddyX design tokens first, then block-theme colour presets, then built-in fallbacks), and it has been tested with the BuddyX and Reign themes.

The "who liked this" line renders on two hooks so it appears on all themes: the BuddyX/Reign theme action `bp_activity_before_post_footer_content` and the core action `bp_activity_entry_content`.

## No dependencies beyond BuddyPress

The plugin registers no external services, collects no personal data outside your WordPress install, and requires no API keys. Emails are sent through your site's configured mail system with `wp_mail()`.
