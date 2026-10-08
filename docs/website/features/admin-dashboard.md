# Admin Dashboard

The plugin adds one admin page, **Favorite Notifications**, as a submenu under the shared **WB Plugins** menu. It requires the `manage_options` capability. The page has four tabs.

## Overview tab

The Overview tab shows favourite statistics and trending content:

- **Stat cards**: total favourites, notifications sent, active users in the last 7 days, and the most liked activity with its favourite count. Each card also shows a 7-day trend where available.
- **Recent Favorites**: a table of favourites from the last 7 days, with the member, a link to the activity, and how long ago it happened. Up to 10 rows.
- **Trending Activities (Last 7 Days)** and **Trending Activities (Last 30 Days)**: the top 10 activities by favourite count in each window, with rank, a link to the activity, the favourite count, the author, and a content preview.
- **Quick Actions**: a shortcut to the Tools tab.

The stats are computed from the plugin's own favourites table and the BuddyPress notifications table, cached in a short-TTL transient (five minutes by default, filterable), and invalidated whenever a favourite is added or removed. The recent and trending tables batch-fetch their activities and users in one query each, so the page stays fast at scale.

## Display tab

Choose how the "who liked this" line renders (inline usernames, icon and count, or a count that opens the full list) and which icon it uses. See [Display Settings](../usage/display-settings.md).

## Tools tab

Run the favourites migration, configure the automatic cleanup of old read notifications, or run a cleanup on demand. See [Tools and Maintenance](../usage/tools-and-maintenance.md).

## Discover tab

A set of cards linking to other free Wbcom Designs tools for BuddyPress communities.

## Tabs are filterable

The tab list is passed through the `bpfn_admin_tabs` filter, so a developer can add or reorder tabs. See the [Hooks Reference](../developer-guide/hooks-reference.md).

## No standalone settings page

There is no WordPress Settings API options page. The Display and Tools tabs persist their options through their own nonce-protected, capability-checked POST handlers, and the values are validated against the allowed sets rather than merely sanitised.
