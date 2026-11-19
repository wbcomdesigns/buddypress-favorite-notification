# Installation

Install BuddyPress 12.0 or later first and confirm its Activity and Notifications components are active. Then install this plugin.

## Install the plugin

1. Download the plugin ZIP file.
2. Go to **Plugins > Add New > Upload Plugin**.
3. Choose the ZIP file and click **Install Now**.
4. Click **Activate Plugin**.

You can also unzip it and upload the `buddypress-favorite-notification` folder to `wp-content/plugins/` over SFTP.

The plugin has no update checker of its own. To update, upload the newer ZIP on **Plugins > Add New > Upload Plugin** and choose **Replace current with uploaded**; your settings and data are kept.

## What activation does

On activation the plugin:

- Creates two custom database tables, `{prefix}bp_favorite_notification_prefs` (member notification preferences) and `{prefix}bp_activity_favorites` (favourite tracking). See the [Database Tables](../developer-guide/database.md) page.
- Creates the two BuddyPress emails if they are missing. It never overwrites an email you have edited.
- Stores the current plugin version in the `bpfn_version` option.
- Sends you once to the plugin's dashboard, at **WB Plugins > Favorite Notifications**, after you activate it. This is skipped when you activate several plugins at once.
- Checks whether any pre-existing favourites need migrating into the new table, and if so raises an admin notice linking to the Tools tab. See [Tools and Maintenance](../usage/tools-and-maintenance.md).

## After activation

There is nothing to switch on. Once BuddyPress is active and the plugin is activated:

- Favouriting an activity or comment notifies its author.
- The "who liked this" line appears under activities for logged-in members.

To review what is happening on your site:

1. Open **WB Plugins > Favorite Notifications > Overview** for favourite statistics and trending activities.
2. Use the **Display** tab to choose how the favourite line renders and which icon it uses. You can also switch on realtime popups there.
3. Use the **Tools** tab to run the favourites migration, switch on the automatic cleanup and set its retention period, or run a cleanup now.
4. Edit the favourite emails in **Dashboard > Emails**.
5. Members can review their own preferences at **Settings > Email** and **Settings > Favorite Notifications** in their BuddyPress profile.

## If BuddyPress is not active

The plugin loads no functionality and shows an admin notice: "BuddyPress Favorite Notification is ineffective now as it requires BuddyPress to be installed and active." Activate BuddyPress to resolve it.

## Removing the plugin and your data

Deactivating or deleting the plugin does not remove anything. The two tables (`{prefix}bp_favorite_notification_prefs` and `{prefix}bp_activity_favorites`), the `bpfn_*` options and the scheduled cleanup event stay, so reinstalling restores your data. BuddyPress notifications already created stay in BuddyPress's own table. The two emails stay in **Dashboard > Emails**.

To delete the data yourself, drop the two tables and delete the `bpfn_*` options, for example with WP-CLI, after you delete the plugin.

## What the plugin stores about members

- Which member favourited which activity, in `{prefix}bp_activity_favorites`.
- Each member's web and real-time choices, in `{prefix}bp_favorite_notification_prefs`.
- Notifications, in BuddyPress's notifications table, and email choices as BuddyPress user meta.

The plugin does not add itself to the WordPress Export Personal Data and Erase Personal Data tools, so those tools do not include or remove this data.
