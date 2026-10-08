# Frequently Asked Questions

## Does this plugin require BuddyPress?

Yes. BuddyPress 12.0 or later must be installed and active, with the Activity and Notifications components enabled. The plugin extends those components and does nothing if BuddyPress is absent, showing an admin notice that explains why.

## Do I need to configure anything?

No. Once the plugin is active, authors are notified when their activity or comment is favourited, and the "who liked this" line appears under activities for logged-in members. The admin page under **WB Plugins > Favorite Notifications** is for statistics, the display style, and maintenance.

## Will I receive notifications for my own favourites?

No. A member is never notified for favouriting their own content.

## Are notifications grouped?

Yes, per activity. When several members favourite the same activity, the entries collapse into one "N people favorited your activity" notification. If one member favourites several different activities of yours, you get a separate notification for each activity.

## Can members control their notifications?

Yes. Each member has two screens in their BuddyPress profile. **Settings > Email** has two rows, "A member favorites your activity" and "A member favorites your comment". **Settings > Favorite Notifications** has Web and Real-time switches per type (activity posts, comments). Everything is on by default; turning one off stops it.

## How do realtime notifications work?

Realtime popups are off for new installs. Switch them on under **WB Plugins > Favorite Notifications > Display**, and pick a check interval of 30 or 60 seconds. They use the WordPress Heartbeat API only. A new favourite appears as a popup without a page refresh.

## Can I customise the emails?

Yes. Favourite emails are BuddyPress emails. Edit the subject and body in **Dashboard > Emails**, the same as any other BuddyPress email. See [Email Alerts](../features/email-alerts.md).

## Can I change the email sender name and address?

Yes, in your BuddyPress and WordPress email settings. The emails use the site's BuddyPress email template and From settings. The plugin has no sender setting of its own.

## Does the email have an unsubscribe link?

Yes. Every email has a working unsubscribe link. Clicking it turns that email off for the member.

## Who can see the "who liked this" display?

Logged-in members only. Logged-out visitors do not see the favourite line under activities. The member list only returns data for activities the member is allowed to read, so favourites on hidden or private group activity stay private.

## How many members does the "View all" list show?

The full list pages through with a **Load more** control, loading 20 members at a time by default. There is no fixed cap: members can page through the whole list. Change the page size with `bpfn_favorites_modal_per_page`, or set a hard ceiling with `bpfn_who_favorited_limit` (default 0, meaning no limit), in which case anything beyond the ceiling shows as a "+N more" line.

## Can I change how the favourite line looks?

Yes, on the **Display** tab. Choose inline usernames, an icon with the count, or an icon and count that opens the full list, and pick the icon (heart, star, bookmark, thumbs up, or none). The icon applies to the "favorited by" line only, not the BuddyPress Favorite button. See [Display Settings](../usage/display-settings.md).

## Will old notifications pile up in my database?

Not if you switch on the automatic cleanup. It is off for new installs, and sites upgraded from before 2.2.0 keep it on. Enable it on the Tools tab and it runs monthly, removing old read notifications. Choose a retention period of 7, 15, 30, 60 or 90 days (default 30). On the Tools tab you can also run it on demand and see the next scheduled run. Unread notifications are never deleted.

## I have an existing site with lots of favourites. Do I need to do anything?

The plugin moves existing favourites into its own indexed table. On large sites this runs in the background in batches so it does not time out, and you can watch its progress on the Tools tab.

## Does it work with BuddyPress groups?

Yes. The plugin works with all BuddyPress activity types, including group activity, blog posts, and comments.

## Is it translation ready?

Yes. The plugin ships a POT file in `languages/` and includes German, Spanish, French, Italian, and Portuguese (Brazil) translations, plus RTL support.

## Does the plugin collect any personal data?

No. It does not collect, store, or share personal data outside your WordPress installation. Notification and favourite data is stored in your own database, and emails go through your site's configured mail system.
