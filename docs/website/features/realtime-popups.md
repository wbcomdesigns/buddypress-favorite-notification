# Realtime Popups

Realtime popups show a favourite notification on screen the moment it happens, without a page refresh. They appear for logged-in members who have a page open on the site.

## How it works

The plugin uses the WordPress Heartbeat API. While a member has a page open:

1. The browser sends a heartbeat to the server on a fixed interval (the plugin sets it to 15 seconds).
2. The server checks for new favourite notifications for that member since the last check.
3. Any new notifications are returned in the heartbeat response, and the front-end script renders them as popups.

If Heartbeat is unavailable, the front-end script falls back to a plain AJAX poll to the `bpfn_check_notifications` action, which returns the same data.

## What a popup shows

Each popup carries the notification text, a relative timestamp ("5 mins ago"), and a link to the activity. Popups appear at the bottom-right of the screen, up to five at a time, and auto-dismiss after five seconds. A member can also dismiss a popup manually, which marks that notification read through the `bpfn_dismiss_notification` action.

## Per-member control

Realtime popups honour the member's "realtime" channel preference. The heartbeat integration is only active for a member who has realtime enabled for at least one notification type. A member who turns realtime off for every type stops receiving popups, and the heartbeat check short-circuits for them. See [Member Notification Preferences](../usage/member-preferences.md).

## Security

Both the heartbeat check and the AJAX fallback verify a nonce (`bpfn_realtime_nonce`, with `bpfn-nonce` also accepted on the fallback) and confirm the member is logged in before returning any data. The dismiss action additionally confirms the notification belongs to the current member before marking it read.

## Requirements

Realtime popups need the BuddyPress Notifications component active. If it is off, the realtime assets are not loaded and no popups run.
