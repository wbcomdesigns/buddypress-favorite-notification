# Realtime Popups

Realtime popups show a favourite notification on screen the moment it happens, without a page refresh. They appear for logged-in members who have a page open on the site.

## Turning popups on

Popups are a site owner setting. Go to **WB Plugins > Favorite Notifications > Display** and find the **Real-time Popups** card:

- **Real-time Popups**: tick "Show members a popup when their activity is favorited, without a page refresh".
- **Check Every**: 30 or 60 seconds. The default is 30. A longer interval puts less load on a busy site.

Popups are off for new installs. Sites upgraded from before 2.2.0 keep popups on. The settings are stored in the `bpfn_realtime_enabled` (`yes` or `no`) and `bpfn_realtime_interval` (`30` or `60`) options.

## How it works

The plugin uses the WordPress Heartbeat API only. While a member has a page open:

1. The browser sends a heartbeat to the server at the interval you chose.
2. The server checks for new favourite notifications for that member since the last check.
3. Any new notifications are returned in the heartbeat response, and the front-end script renders them as popups.

There is no other transport. If Heartbeat is blocked, popups do not appear.

## What a popup shows

Each popup carries the notification text, a relative timestamp ("5 mins ago"), and a link to the activity. Popups appear at the bottom-right of the screen, up to five at a time, and auto-dismiss after five seconds. A member can also dismiss a popup manually, which marks that notification read through the `bpfn_dismiss_notification` action.

## Per-member control

Popups honour the member's "realtime" channel preference. The popup scripts load only for members who have realtime on for at least one notification type. A member who turns it off for every type receives no popups. The Real-time column on the member's settings screen only appears while you have popups switched on. See [Member Notification Preferences](../usage/member-preferences.md).

## Security

The heartbeat check verifies a nonce (`bpfn_realtime_nonce`) and confirms the member is logged in before returning any data. The dismiss action accepts the `bpfn-nonce` or `bpfn_realtime_nonce` nonce and confirms the notification belongs to the current member before marking it read.

## Requirements

Realtime popups need the BuddyPress Notifications component active. If it is off, the realtime assets are not loaded and no popups run.
