# Overview

BuddyPress Favourite Notification closes the feedback loop in a BuddyPress community. When a member favourites (likes) another member's activity or comment, the author is told about it right away, and everyone browsing the stream can see who liked a post.

The plugin delivers this through four surfaces:

- **BuddyPress notification** to the author, on the BuddyPress notifications screen and toolbar bubble.
- **Optional HTML email** with separate templates for a favourited activity and a favourited comment.
- **Realtime on-screen popup** that appears without a page refresh, powered by the WordPress Heartbeat API with an AJAX fallback.
- **A "who liked this" line** under each activity, in the style of a social feed, for logged-in members.

Site owners also get an admin dashboard under the shared **WB Plugins** menu with favourite statistics, trending activities, a display-style setting, and database maintenance tools.

## How it works at a glance

1. A member favourites an activity. BuddyPress fires its `bp_activity_add_user_favorite` action.
2. The plugin records the favourite in its own indexed table, creates a BuddyPress notification for the activity author (unless the author favourited their own content), and sends an email if that channel is enabled.
3. The author sees the notification on their next page load, and a realtime popup if they have a page open.
4. The "who liked this" line under the activity updates over AJAX.

## Key characteristics

- **Zero configuration to start.** Once BuddyPress and the plugin are active, favouriting notifies the author immediately. There is nothing to switch on.
- **No self-notifications.** A member is never notified for favouriting their own content.
- **Grouped notifications.** When several members favourite the same activity, the entries collapse into one "N people favourited your activity" notification.
- **Members stay in control.** Each member has a per-type, per-channel preference screen in their BuddyPress profile settings.
- **Built for scale.** Favourites live in a custom indexed table, counts and who-liked queries are object-cached, and the admin dashboard and who-liked modal are paginated and batch-fetched.

BuddyPress is a hard requirement. If BuddyPress is not active, the plugin does nothing and shows an admin notice explaining why.
