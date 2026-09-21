# Member Notification Preferences

Every member controls their own favourite notifications from their BuddyPress profile. Notifications are on by default, so a member only visits this screen if they want fewer.

## Where to find it

**Settings > Favorite Notifications** in the member's own BuddyPress profile. The screen is available when the BuddyPress Settings component is active, and a member can only edit their own settings (or a moderator with the `bp_moderate` capability can edit any).

## What a member can control

The screen has a switch for each notification type and each channel:

Notification types:

- **Activity post favorites**: someone favourites the member's activity posts.
- **Comment favorites**: someone favourites the member's comments.

Channels, per type:

- **Web**: the on-screen BuddyPress notification.
- **Email**: the HTML email alert.
- **Realtime**: the on-screen popup.

Turning a channel off stops that notification for that type. All channels are on by default, so a new member receives everything until they change it.

## How the preference is applied

Each surface checks the relevant channel before firing:

- A web notification is created only if the member's "web" preference for that type is on.
- An email is sent only if the "email" preference for that type is on.
- Realtime popups run only if the member has "realtime" on for at least one type.

Preferences are stored in the plugin's own `{prefix}bp_favorite_notification_prefs` table, keyed by member and notification type.

## The native BuddyPress notifications row

BuddyPress has its own **Settings > Notifications** screen with a "Favorites" row ("A member favorites your activity", Yes/No). The plugin mirrors that row into the same preference store: choosing "No" turns the email channel off for activity-post favourites, and "Yes" turns it back on, while the web and realtime channels set on the plugin's own screen are preserved. The two screens therefore stay in agreement rather than contradicting each other.
