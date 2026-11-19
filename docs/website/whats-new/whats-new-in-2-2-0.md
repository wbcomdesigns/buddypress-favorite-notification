# What's New in 2.2.0

2.2.0 gives site owners control over every behaviour that costs data or server load, and moves the plugin's emails into BuddyPress Emails. Read the update notes first, because two behaviours and the email customisation method changed.

## Update notes

- **BuddyPress 12.0 or later and PHP 8.1 or later are required.** Tested up to WordPress 7.1. Check your versions before updating.
- **Emails are now BuddyPress emails.** Edit them in **Dashboard > Emails**. Email template files you copied into your theme are no longer used, so move any wording you customised there into the emails in the dashboard. See [Email Alerts](../features/email-alerts.md).
- **Developer hooks were removed.** See For developers below.
- **Sites updating keep their current behaviour.** Automatic cleanup and real-time popups were on by default before 2.2.0, so an updating site keeps both on. New installs start with both off.
- **Nothing else needs configuring.** Your saved settings and favourites carry over.

## What changed for a site owner

- **Emails through BuddyPress Emails.** Members get a working unsubscribe link, and you edit the subject and body like any other BuddyPress email.
- **Members choose emails in BuddyPress.** Settings > Email has a row for likes on activities and a row for likes on comments. The plugin's own **Settings > Favorite Notifications** tab holds the web and real-time switches and no longer replaces the Email tab.
- **Real-time Popups setting.** The Display tab switches popups on or off and sets the check interval to 30 or 60 seconds. Popups use the WordPress Heartbeat API only, load only for members who have them on, show at most two at a time (one on phones), and no longer cover the page when many arrive together.
- **Activation sends you to the plugin's dashboard.**
- **Automatic cleanup is off on new installs.** Turn it on from the Tools tab. Enabling it schedules the first run a month later instead of running at once. A failed run is shown as a failure, and the retention period is limited to 7, 15, 30, 60 or 90 days. Cleanup no longer deletes read notifications early when the database clock differs from WordPress.
- **The Overview tab** shows the actual date and time of each recent favourite as well as how long ago, in your site's time zone, and favourited comments show properly in the trending tables.
- **The Tools tab** no longer keeps asking to migrate favourites that are already in the favourites table.
- **Phones.** The who-favorited list opens as a bottom sheet sized to its content, and the admin pages no longer sit flush against the right edge or scroll sideways.
- **The Settings saved notice** can be dismissed.
- **Privacy.** The who-favorited list no longer reveals who favourited activity in hidden or private groups, and popup text is escaped before it reaches the page.
- **Members who turned web notifications off** now still receive emails, and closing a popup or opening a notification from it marks the notification read.
- **Sites with the Activity component off** no longer crash on pages that show the notifications menu.
- **Translations.** German, French, Spanish, Italian and Brazilian Portuguese are complete.
- **The Documentation link** in the plugin admin opens this plugin's guide.

## Retest after updating

1. Like an activity from a second account and confirm the author gets the notification and the email.
2. Open **Dashboard > Emails** and confirm both favourite emails are present.
3. Open **Settings > Email** as a member and confirm the two likes rows are there, then open **Settings > Favorite Notifications** and confirm the web switches are there.
4. If you use popups, confirm the Display tab shows them on, and test one with two accounts.
5. If you use automatic cleanup, open the Tools tab and confirm the setting and retention period.

## For developers

- Removed the plugin email templates and the filters `bpfn_email_templates`, `bpfn_email_template_key`, `bpfn_email_template_path`, `bpfn_email_data`, `bpfn_email_subject`, `bpfn_email_message`, `bpfn_email_headers`, `bpfn_email_from_name` and `bpfn_email_from_email`. Edit the emails in Dashboard > Emails, or use the `bpfn_email_tokens` filter.
- Removed the `bpfn_log_event` action, the `bpfn_enable_logging` filter and the `bpfn_check_notifications` AJAX action.
- See [Hooks Reference](../developer-guide/hooks-reference.md) for the hooks that remain.
