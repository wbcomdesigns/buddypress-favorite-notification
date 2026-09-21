# Favourite Notifications

When a member favourites another member's activity or comment, the plugin creates a BuddyPress notification for the author. It appears on the author's notifications screen and in the toolbar notification bubble like any other BuddyPress notification.

## What triggers a notification

The plugin hooks BuddyPress's own favourite actions:

- `bp_activity_add_user_favorite` creates the notification.
- `bp_activity_remove_user_favorite` removes it when the member unfavourites.

Because it uses the core favourite action, the plugin works with all BuddyPress activity types, including activity posts, group activity, blog posts, and comments.

## Notification text

The text depends on the activity type and how many members favourited the same item:

- Single favourite of an activity: "{name} favorited your activity".
- Single favourite of a comment: "{name} favorited your comment".
- Several favourites of the same activity: "{N} people favorited your activity".
- Several favourites of the same comment: "{N} people favorited your comment".

The notification links to the favourited activity. Opening it marks the notification read, because the link carries the notification id as an `rid` parameter that BuddyPress core uses to clear the entry.

## Grouping

Grouping is per activity. All favourites of one activity share a single grouped notification, so a popular post produces one "N people favorited your activity" entry rather than one entry per member. If a single member favourites several different activities of yours, you get a separate notification for each activity.

## No self-notifications

A member is never notified for favouriting their own content. The plugin compares the activity author to the member who favourited and stops if they are the same.

## The favorite_notifier component

Notifications are delivered through a lightweight BuddyPress pseudo-component named `favorite_notifier` (subnav slug `favorite_notification`). The plugin registers it during BuddyPress setup, adds it to the active and registered component lists, and re-registers it defensively on every request if it is ever missing. This is what lets BuddyPress route, format, and mark-as-read the plugin's notifications through its standard notification pipeline.

Notification actions are named `fav_notify_{activity_id}` for activities and `fav_comment_notify_{activity_id}` for comments.

## Respecting member preferences

Before creating a notification, the plugin checks the recipient's saved preference for that activity type and the "web" channel. If the member has turned favourite notifications off for that type, no notification is created. See [Member Notification Preferences](../usage/member-preferences.md).
