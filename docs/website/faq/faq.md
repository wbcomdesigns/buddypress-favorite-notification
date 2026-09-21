# Frequently Asked Questions

## Does this plugin require BuddyPress?

Yes. BuddyPress must be installed and active, with the Activity and Notifications components enabled. The plugin extends those components and does nothing if BuddyPress is absent, showing an admin notice that explains why.

## Do I need to configure anything?

No. Once the plugin is active, authors are notified when their activity or comment is favourited, and the "who liked this" line appears under activities for logged-in members. The admin page under **WB Plugins > Favorite Notifications** is for statistics, the display style, and maintenance.

## Will I receive notifications for my own favourites?

No. A member is never notified for favouriting their own content.

## Are notifications grouped?

Yes, per activity. When several members favourite the same activity, the entries collapse into one "N people favorited your activity" notification. If one member favourites several different activities of yours, you get a separate notification for each activity.

## Can members control their notifications?

Yes. Each member has a **Settings > Favorite Notifications** screen in their BuddyPress profile with a switch per notification type (activity posts, comments) and per channel (web, email, realtime). Everything is on by default; turning one off stops it.

## How do realtime notifications work?

Realtime popups use the WordPress Heartbeat API, which the plugin sets to check every 15 seconds, with a plain AJAX fallback if Heartbeat is unavailable. A new favourite appears as a popup without a page refresh.

## Can I customise the email templates?

Yes. Copy the templates from the plugin's `templates/emails/` directory into `your-theme/buddypress/bp-favorite-notification/emails/`, and your copies are used instead. See [Email Template Overrides](../developer-guide/email-template-overrides.md).

## Can I change the email sender name and address?

Yes, with the `bpfn_email_from_name` and `bpfn_email_from_email` filters. There is no admin screen for this; by default emails come from your site name and your site's admin email address.

## Who can see the "who liked this" display?

Logged-in members only. Logged-out visitors do not see the favourite line under activities.

## How many members does the "View all" list show?

The full list pages through with a **Load more** control, loading 20 members at a time by default. There is no fixed cap: members can page through the whole list. Change the page size with `bpfn_favorites_modal_per_page`, or set a hard ceiling with `bpfn_who_favorited_limit` (default 0, meaning no limit), in which case anything beyond the ceiling shows as a "+N more" line.

## Can I change how the favourite line looks?

Yes, on the **Display** tab. Choose inline usernames, an icon with the count, or an icon and count that opens the full list, and pick the icon (heart, star, bookmark, thumbs up, or none). See [Display Settings](../usage/display-settings.md).

## Will old notifications pile up in my database?

No. An automatic cleanup runs monthly and removes old read notifications. Set the retention period (minimum 7 days, default 30) on the Tools tab, where you can also disable it, run it on demand, and see the next scheduled run. Unread notifications are never deleted.

## I have an existing site with lots of favourites. Do I need to do anything?

The plugin moves existing favourites into its own indexed table. On large sites this runs in the background in batches so it does not time out, and you can watch its progress on the Tools tab.

## Does it work with BuddyPress groups?

Yes. The plugin works with all BuddyPress activity types, including group activity, blog posts, and comments.

## Is it translation ready?

Yes. The plugin ships a POT file in `languages/` and includes German, Spanish, French, Italian, and Portuguese (Brazil) translations, plus RTL support.

## Does the plugin collect any personal data?

No. It does not collect, store, or share personal data outside your WordPress installation. Notification and favourite data is stored in your own database, and emails go through your site's configured mail system.
