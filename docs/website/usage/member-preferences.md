# Member Notification Preferences

Every member controls their own favourite notifications from their BuddyPress profile. Everything is on by default, so a member only visits these screens if they want fewer.

## Where to find it

Favourite alerts are set in two places under **Settings** in the member's own BuddyPress profile:

- **Settings > Email** is BuddyPress's own email tab. It holds the email switches.
- **Settings > Favorite Notifications** is the plugin's tab (URL slug `favorite-notifications`). It holds the web and real-time switches, and links to the Email settings.

The plugin tab needs the BuddyPress Settings component to be active. A member can only edit their own settings, or a moderator with the `bp_moderate` capability can edit any.

## Email alerts

On **Settings > Email**, the Favorites table has two rows, each with Yes and No:

- **A member favorites your activity**
- **A member favorites your comment**

Choosing No stops that email. The unsubscribe link in each email does the same. See [Email Alerts](../features/email-alerts.md).

## Web and real-time alerts

The **Settings > Favorite Notifications** tab has one row per notification type:

- **Activity post favorites**: someone favourites the member's activity posts.
- **Comment favorites**: someone favourites the member's comments.

Each row has a switch per channel:

- **Web**: the on-screen BuddyPress notification.
- **Real-time**: the on-screen popup. This column only appears when the site owner has switched real-time popups on.

Turning a channel off stops that notification for that type. All channels are on by default, so a new member receives everything until they change it.

## How the preference is applied

Each surface checks its own channel before firing:

- A web notification is created only if the member's "web" preference for that type is on.
- An email is sent only if the member's email preference for that type is on. This does not depend on the web channel.
- Popups run only if the site owner has them on and the member has "real-time" on for at least one type.

Preferences are stored in the plugin's own `{prefix}bp_favorite_notification_prefs` table, keyed by member and notification type. BuddyPress writes the email choices as user meta (`favorite_activity` and `favorite_activity_comment`), and the plugin keeps the two in step, so the Email tab, the unsubscribe link and the stored preference never disagree.
