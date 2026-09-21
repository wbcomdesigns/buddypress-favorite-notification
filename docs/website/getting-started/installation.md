# Installation

Install BuddyPress first and confirm its Activity and Notifications components are active. Then install this plugin.

## Automatic installation

1. Log in to your WordPress admin dashboard.
2. Go to **Plugins > Add New**.
3. Search for "BuddyPress Favorite Notification".
4. Click **Install Now**, then **Activate**.

Notifications start working immediately, with no configuration needed.

## Manual installation

1. Download the plugin ZIP file.
2. Go to **Plugins > Add New > Upload Plugin**.
3. Choose the ZIP file and click **Install Now**.
4. Click **Activate Plugin**.

## What activation does

On activation the plugin:

- Creates two custom database tables, `{prefix}bp_favorite_notification_prefs` (member notification preferences) and `{prefix}bp_activity_favorites` (favourite tracking). See the [Database Tables](../developer-guide/database.md) page.
- Stores the current plugin version in the `bpfn_version` option.
- Checks whether any pre-existing favourites need migrating into the new table, and if so raises an admin notice linking to the Tools tab. See [Tools and Maintenance](../usage/tools-and-maintenance.md).

## After activation

There is nothing to switch on. Once BuddyPress is active and the plugin is activated:

- Favouriting an activity or comment notifies its author.
- The "who liked this" line appears under activities for logged-in members.

To review what is happening on your site:

1. Open **WB Plugins > Favorite Notifications > Overview** for favourite statistics and trending activities.
2. Use the **Display** tab to choose how the favourite line renders and which icon it uses.
3. Use the **Tools** tab to run the favourites migration, set the automatic cleanup retention period, or run a cleanup now.
4. Members can review their own preferences at **Settings > Favorite Notifications** in their BuddyPress profile.

## If BuddyPress is not active

The plugin loads no functionality and shows an admin notice: "BuddyPress Favorite Notification is ineffective now as it requires BuddyPress to be installed and active." Activate BuddyPress to resolve it.
