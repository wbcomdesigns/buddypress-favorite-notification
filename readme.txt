=== BuddyPress Favorite Notification ===
Contributors: wbcomdesigns, vapvarun
Donate link: https://wbcomdesigns.com/donate/
Tags: buddypress, notifications, favorites, activity, realtime
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Tested with BuddyPress: 14.5.2
Requires BuddyPress: 12.0
Requires Plugins: buddypress
Stable tag: 2.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: buddypress-favorite-notification
Domain Path: /languages

Notify BuddyPress members when someone favorites their activity or comment, with realtime popups and email alerts.

== Description ==

**BuddyPress Favorite Notification** closes the feedback loop in your community. When a member favorites an activity or a comment, the author is told about it right away through BuddyPress notifications, an optional email, and a realtime popup that appears without a page refresh.

It also adds a Facebook-style "who liked this" line under each activity, and gives site owners a dashboard showing what is trending. BuddyPress is required.

= What you get =

**Notifications when content is appreciated**
* A BuddyPress notification to the author whenever their activity or comment is favorited.
* Optional realtime on-screen popups powered by the WordPress Heartbeat API, so a member sees the favorite as it happens. Switched on by the site owner on the Display tab, checking every 30 or 60 seconds.
* Email alerts for favorited activities and favorited comments, sent through BuddyPress Emails. Edit the wording in Dashboard > Emails like any other BuddyPress email, and every email carries a working unsubscribe link.
* No notification when you favorite your own content.
* Works with all BuddyPress activity types, including group activities, blog posts, and comments.

**Favorite count display**
* Three display modes, chosen in Settings: inline usernames ("John, Jane, and 10 others"), an icon with the count, or an icon and count that opens the full list.
* A choice of icon for the "favorited by" line - heart, star, bookmark, thumbs up, or none - so favorites are not mistaken for the Like reaction. The Favorite button itself keeps the icon BuddyPress and your theme give it.
* A "View all" modal listing the members who favorited an activity, with clickable profile links. It loads a page at a time with a Load more control, so it stays usable on activities with thousands of likes. Set the page size with the `bpfn_favorites_modal_per_page` filter.
* Updates over AJAX as members like and unlike, with a 5-minute cache so the line loads instantly.
* The favorite display is shown to logged-in members only.

**Admin dashboard**
* Overview stats: total favorites, notifications sent, active users, and the most liked activity.
* Recent favorites from the last 7 days.
* Trending activities over the last 7 days and the last 30 days, top 10 each, with rank, content preview, author, and a direct link.

**Tools and maintenance**
* Optional automatic monthly cleanup of old read notifications, with a retention period of 7, 15, 30, 60, or 90 days. Off until the site owner turns it on.
* A manual cleanup button, the last cleanup result, and the next scheduled run.
* Chunked background migration of existing favorites, processed in batches so large sites do not time out, with progress tracking and a log.

**Built for larger communities**
* A custom, indexed favorites table for fast lookups instead of scanning user meta.
* Object caching on the favorite counts and the who-liked queries.

**Member preferences**
* Members choose favorite emails in their BuddyPress **Settings > Email** tab, next to every other BuddyPress email.
* A **Settings > Favorite Notifications** tab turns web notifications and realtime popups on or off per activity type (posts, comments).
* Everything is on by default, so members only visit these screens if they want less.

**Developer friendly**
* Action and filter hooks throughout, including `bpfn_notification_string` and `bpfn_email_tokens`.
* Display hooks for full control of the favorite line: `bpfn_favorite_icon_html`, `bpfn_favorite_display_format`, `bpfn_favorite_display_html`, `bpfn_display_modes`, and `bpfn_favorite_icons`.
* Modular architecture with separate notification, email, realtime, settings, admin, and favorite display modules.
* Translation ready with an included POT file, and RTL support.

= Perfect For =

* BuddyPress communities that want members to know when their posts land well
* Social networks where engagement and return visits matter
* Membership sites that use the activity stream as the main feed
* Any BuddyPress site that wants to see what content is trending

= Premium Support =

Our support team is ready to help with setup, configuration, and troubleshooting. Reach us through the links below.

= Documentation =

* **[Documentation and Support](https://docs.wbcomdesigns.com/)** - Setup walkthrough and usage guides for every Wbcom plugin

= Translations =

* English (default)
* Ready for translation in your language with the included POT file
* RTL language support included

= Links =

* [Plugin Homepage](https://wbcomdesigns.com/downloads/buddypress-favourite-notification/)
* [Documentation](https://docs.wbcomdesigns.com/)
* [Support](https://wbcomdesigns.com/support/)
* [Request Features](https://wbcomdesigns.com/contact/)

= Compatibility =

* WordPress 6.5 and higher
* PHP 8.0 and higher
* BuddyPress required - the plugin deactivates itself and explains why if BuddyPress is not active
* Compatible with BuddyPress Nouveau and Legacy templates
* Works with modern WordPress themes, including BuddyX and Reign

= What's New in 2.1.0 =

The "who liked this" line is now yours to shape. A new Display tab lets you keep the familiar inline usernames, swap them for a compact icon and count, or make that count open the full list of members. You can also change the icon itself: a heart reads as the Like reaction to many members, so a star or bookmark keeps favorites visually distinct.

The full list now pages through with a Load more control instead of stopping at the first 50 members, which matters once an activity collects thousands of likes. Nothing changes on update unless you change the setting: existing sites keep inline usernames.

= What's New in 2.0.1 =

Member notification preferences now actually work. Until this release a member who turned favorite notifications off still received them on every channel: the check that was meant to read their choice returned "enabled" for any request made through admin-ajax, which is exactly how BuddyPress favoriting works. Two further paths ignored the preference as well. If your members have complained that switching notifications off does nothing, this is the release that fixes it.

The admin is also rebuilt on the unified Wbcom card-panel interface under the shared "WB Plugins" menu, frontend colours follow your theme palette instead of fixed literals, and every surface mirrors correctly under RTL. The dead "Enhanced Notifications" toggle has been removed: BuddyPress strips notification markup down to a plain link, so the option could never change what members saw. German, Spanish, French, Italian and Portuguese (Brazil) translations are included, and the text domain is now registered so they load at all.

== More Free Tools from Wbcom Designs ==

Favorite Notification closes the loop the second a member's activity gets liked, turning small moments of appreciation into the real-time nudges that pull people back to your site. That retention loop pays off most when there is a community worth returning to, and these other free tools from Wbcom Designs help you build one:

* **[BuddyX](https://wbcomdesigns.com/downloads/buddyx-theme/)** - A free, fast community theme for BuddyPress, BuddyBoss and PeepSo with a modern layout and dark mode.
* **[BuddyNext](https://wbcomdesigns.com/downloads/buddynext/)** - Stand up a complete WordPress community with activity streams, member spaces, profiles, direct messaging, and built-in moderation.
* **[Jetonomy](https://wbcomdesigns.com/downloads/jetonomy/)** - Add forums, question-and-answer boards, and idea spaces that stay tidy through trust-based auto-moderation even past 100,000 topics.
* **[Mediaverse](https://wbcomdesigns.com/downloads/mediaverse/)** - Let members build photo and video albums, react, follow each other, and message privately while AI moderation keeps things clean.
* **[Eventonomy](https://wbcomdesigns.com/downloads/eventonomy/)** - Run community events with RSVPs, calendars, and front-end submissions.
* **[WB Gamification](https://wbcomdesigns.com/downloads/wordpress-gamification-plugin/)** - Reward members with points, badges, and leaderboards to keep engagement high.
* **[Listora](https://wbcomdesigns.com/downloads/listora/)** - Publish searchable directories across ten listing types with reviews, maps, and member-submitted entries from the front end.
* **[WP Career Board](https://wbcomdesigns.com/downloads/wp-career-board/)** - Add a job board with front-end listings, applications, and employer profiles.
* **[Learnomy](https://wbcomdesigns.com/downloads/learnomy/)** - Build and sell online courses, auto-grade quizzes, collect payments, and award certificates when learners finish.

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin dashboard
2. Navigate to Plugins > Add New
3. Search for "BuddyPress Favorite Notification"
4. Click "Install Now" and then "Activate"
5. Notifications start working right away, with no configuration needed

= Manual Installation =

1. Download the plugin ZIP file
2. Log in to your WordPress admin dashboard
3. Navigate to Plugins > Add New > Upload Plugin
4. Choose the downloaded ZIP file and click "Install Now"
5. Click "Activate Plugin"

= Post-Installation Setup =

There is nothing to switch on. Once BuddyPress is active and the plugin is activated, favoriting an activity notifies its author, and the "who liked this" line appears under activities for logged-in members.

To review what is happening on your site:

1. Visit **WB Plugins > Favorite Notifications > Overview** for favorite statistics and trending activities
2. Open the **Tools** tab to run the favorites migration, set the automatic cleanup retention period, or run a cleanup now
3. Members can review their own notification preferences at **Settings > Favorite Notifications** in their BuddyPress profile
4. See the [documentation](https://docs.wbcomdesigns.com/) for detailed instructions

= Requirements =

* WordPress 6.5 or higher
* PHP 8.0 or higher
* BuddyPress installed and active

== Frequently Asked Questions ==

= Does this plugin require BuddyPress? =

Yes. BuddyPress must be installed and active. The plugin extends the BuddyPress activity and notification components, and deactivates itself with an explanation if BuddyPress is not available.

= Do I need to configure anything? =

No. Once the plugin is active, authors are notified and emailed when their activity or comment is favorited. The admin page, **WB Plugins > Favorite Notifications**, has an Overview tab for statistics, a Display tab for the favorite line and realtime popups, a Tools tab for cleanup and migration, and a Discover tab.

= Will this work with my theme? =

Yes. The plugin works with any theme that supports BuddyPress, and follows BuddyPress Nouveau and Legacy template standards. Its styles adapt to your theme's colour palette, and it has been tested with BuddyX and Reign.

= How do realtime notifications work? =

Turn them on in the **Real-time Popups** card on the Display tab. They use the WordPress Heartbeat API, which checks for new notifications every 30 or 60 seconds while a page is open. When a new favorite is detected, a popup appears on screen without a page refresh. Popups are off on new installs, because every logged-in member's browser makes a request at that interval.

= Will I receive notifications for my own favorites? =

No. Members are not notified when they favorite their own content.

= Are notifications grouped? =

Yes, when several members favorite the same activity. Those favorites collapse into one notification that reads "%d people favorited your activity" instead of one entry per member. Grouping is per activity, so if one member favorites several different activities of yours, you get a separate notification for each of them.

= Can members control their notifications? =

Yes. Emails are set in the BuddyPress **Settings > Email** tab, which has a row for favorited activities and one for favorited comments. Web notifications and realtime popups are set in **Settings > Favorite Notifications**. Everything is on by default; turning one off stops it.

= Can I customize the emails? =

Yes. Go to **Dashboard > Emails** and edit "favorited your update" or "favorited your comment" like any other BuddyPress email. They use your BuddyPress email design and sender settings, and your edits are kept when the plugin updates.

= Who can see the "who liked this" display? =

Logged-in members only. Logged-out visitors do not see the favorite count line under activities.

= How many members does the "View all" modal show? =

Everyone who favorited the activity, a page at a time with a Load more control. Developers can set the page size with `bpfn_favorites_modal_per_page`, or a maximum with `bpfn_who_favorited_limit`.

= Will old notifications pile up in my database? =

Only if you want them to. Turn on automatic cleanup on the Tools tab and it removes read notifications older than the retention period you choose (7, 15, 30, 60, or 90 days) once a month, starting a month after you enable it. You can also run it on demand and see the next scheduled run. Unread notifications are never removed.

= I have an existing site with lots of favorites. Do I need to do anything? =

The plugin moves existing favorites into its own indexed table. On large sites this runs in the background in batches so it does not time out, and you can watch its progress on the Tools tab.

= Does it work with BuddyPress Groups? =

Yes. The plugin works with all BuddyPress activity types, including group activities, blog posts, comments, and custom activity types.

= Is it translation ready? =

Yes. The plugin is fully internationalized and ships a POT file in the `languages/` directory, plus RTL support.

= Can developers extend this plugin? =

Yes. The plugin has a modular architecture and provides action and filter hooks throughout, including `bpfn_notification_string`, `bpfn_who_favorited_limit`, `bpfn_admin_tabs`, and `bpfn_email_tokens`.

= Where can I get support? =

* [Documentation](https://docs.wbcomdesigns.com/) - Free guides for every Wbcom plugin
* [Support](https://wbcomdesigns.com/support/) - Get help from our team
* [Contact us](https://wbcomdesigns.com/contact/) - Report bugs and request features

= Does the plugin collect any personal data? =

No. The plugin does not collect, store, or share personal data outside your WordPress installation. Notification data is stored in your own database, and emails go through your site's configured mail system.

== Screenshots ==

1. Admin dashboard with favorite and notification statistics.
2. Tools tab - favorites migration and database maintenance options.
3. Activity favorite (heart) control showing active and inactive states.
4. Notifications screen listing a favorite notification.
5. Discover - more free tools from Wbcom Designs.

== Changelog ==

= 2.2.0 - October 2026 =

Site owners now control every behaviour that costs them data or server load, and emails move into BuddyPress Emails.

* New      - Emails are sent through BuddyPress Emails, so you can edit them in Dashboard > Emails and members get a working unsubscribe link.
* New      - Added a Real-time Popups setting on the Display tab to switch popups on or off and check every 30 or 60 seconds.
* New      - Members choose favorite emails in the BuddyPress Settings > Email tab, with separate rows for activities and comments.
* New      - The plugin now redirects to its dashboard after activation.
* Improve  - Automatic cleanup and realtime popups are off on new installs. Sites updating from an earlier version keep their current behaviour.
* Improve  - The Recent Favorites table on the Overview tab shows the actual date and time as well as how long ago.
* Improve  - The Settings saved notice can be dismissed.
* Improve  - Realtime popups use the WordPress Heartbeat API only, and load only for members who have them switched on.
* Improve  - Realtime popups are compact cards that use your theme's surface colours, show at most two at a time (one on phones), and no longer cover the page when many favorites arrive together.
* Improve  - On phones the who-favorited list opens as a bottom sheet sized to the list instead of a mostly empty full-screen panel.
* Improve  - The admin settings sidebar no longer makes the plugin pages scroll sideways on phones.
* Improve  - Every count now uses proper plural forms and brand names stay out of translatable text, so the plugin translates correctly into any language.
* Improve  - German, French, Spanish, Italian, and Brazilian Portuguese translations are complete.
* Fix      - The plugin's member settings tab replaced the BuddyPress Email tab and its own Web and Real-time switches never appeared.
* Fix      - Emails were not sent to members who had turned web notifications off.
* Fix      - Closing a realtime popup did not mark the notification read, and opening it from the popup did not either.
* Fix      - Favorited comments showed as "Activity not found" in the Overview trending tables.
* Fix      - The Tools tab kept asking to migrate favorites that were already in the favorites table.
* Fix      - Automatic cleanup could delete read notifications hours before the retention period ended when the database clock differed from WordPress.
* Fix      - Favorite times on the Overview tab were shifted by the database server's time zone.
* Fix      - Enabling automatic cleanup ran it straight away instead of a month later.
* Fix      - A failed automatic cleanup was shown as a successful run that deleted nothing.
* Fix      - A retention period outside 7, 15, 30, 60, or 90 days could be saved and the Tools tab then showed a different value.
* Security - The who-favorited list no longer reveals who favorited activity in hidden or private groups.
* Security - Realtime popup text is escaped before it reaches the page.
* Dev      - Removed the plugin email templates and the bpfn_email_templates, bpfn_email_template_key, bpfn_email_template_path, bpfn_email_data, bpfn_email_subject, bpfn_email_message, bpfn_email_headers, bpfn_email_from_name, and bpfn_email_from_email filters. Use Dashboard > Emails or the new bpfn_email_tokens filter.
* Dev      - Removed the bpfn_log_event action, the bpfn_enable_logging filter, and the bpfn_check_notifications AJAX action.
* Dev      - Requires BuddyPress 12.0 or later.

= 2.1.0 - July 2026 =

Choose how the "who favorited this" line looks, and page through the full list of members.

* New      - Added a Display tab with a Display Mode setting: inline usernames, icon and count only, or icon and count that opens the full list.
* New      - Added a Favorite Icon setting (heart, star, bookmark, thumbs up, or none) so favorites can be told apart from the Like reaction.
* New      - The who-favorited list now loads a page at a time with a Load more control, instead of stopping at the first 50 members.
* New      - Added the bpfn_favorite_icon_html, bpfn_favorite_display_format, and bpfn_favorite_display_html filters for full control over the display markup.
* New      - Added the bpfn_display_modes, bpfn_favorite_icons, and bpfn_favorites_modal_per_page filters.
* Improve  - Existing sites keep inline usernames until the new setting is changed, so updating changes nothing on its own.
* Improve  - Counter and Load more controls meet the 40px minimum tap target and keep keyboard focus inside the open dialog, returning focus to the counter on close.
* Fix      - The favorite display and its post-favorite refresh were two separate copies of the same markup, so any format or icon change reverted the moment a member clicked like. Both now render from one code path.
* Fix      - Favorite counts could stay stale for up to five minutes after a like. Cache entries are now versioned per activity and invalidated together, whatever page of the list produced them.
* Dev      - bpfn_who_favorited_limit now defaults to 0 (no limit) and acts as a ceiling on the paginated list. Sites filtering it to a positive number keep that maximum.

= 2.0.1 - July 2026 =

* New      - Added German, Spanish, French, Italian and Portuguese (Brazil) translations.
* Improve  - Rebuilt the admin into the unified Wbcom card-panel UI under the shared "WB Plugins" menu, with Overview, Tools, and Discover tabs.
* Improve  - Removed the "Enhanced Notifications" toggle. BuddyPress escapes notification descriptions down to a plain link, so the setting could never change what members saw.
* Improve  - Frontend design tokens now chain through the active theme palette (BuddyX --bx-color-*, then block-theme colour presets) so the heart, notification count badge, and realtime toasts pick up the site's brand colours instead of fixed literals.
* Improve  - Added a shared --bpfn-primary / secondary / tertiary accent scale and defined the realtime toast stacking z-index token, so activity, comment, and mention toasts keep three distinct hues on every theme.
* Improve  - Converted directional CSS to logical properties (margin-inline, inset, text-align:start) across the frontend and admin so every surface mirrors correctly under RTL.
* Improve  - Admin action buttons now meet the 40px minimum tap target and show a disabled/processing state while their request is running, preventing double submits.
* Improve  - Added keyboard focus-visible rings to the who-liked list links, realtime toast action links, and the permission role chips.
* Fix      - The plugin never registered its text domain, so bundled translations could never load. Added the Domain Path header, which WordPress 6.7+ needs to find them.
* Fix      - The 'and N others' summary and the 'x ago' timestamp were assembled from separate fragments, which cannot be translated correctly in every language. Each is now a single translatable phrase.
* Fix      - Toast and screen-reader strings in the realtime notifier had no translatable source.
* Fix      - Activity-favorite email now uses one coherent orange accent for header, excerpt, and button instead of a blue header paired with orange buttons.
* Fix      - Per-user notification preferences (member Settings > Favorite Notifications) now load and save correctly - the settings module was previously never loaded.
* Fix      - Migration-notice dismissal now persists (added the missing AJAX handler).
* Fix      - Replaced native browser confirm() dialogs in the admin with an accessible confirm modal.
* Fix      - Removed a duplicate registration of the bpfn_options setting and corrected the text domain to match the plugin slug.
* Fix      - Added a sanitize callback for register_setting and removed the deprecated load_plugin_textdomain call.
* Fix      - Per-user notification preferences now take effect. A member who turned favorite notifications off still received them on every channel: the enabled-check returned true for any request made through admin-ajax, which is how BuddyPress favoriting works, so the saved preference was never read.
* Fix      - A safety-net handler re-added notifications without checking the member's preference, undoing the choice even once the check above was honoured.
* Fix      - Email notifications ignored the preference for ordinary activity posts, because the type was mapped to a preference key the settings screen never writes.
* Fix      - The Tools tab "Clear Old Notifications Now" button appeared to do nothing; its result notice now renders in the visible Manual Cleanup card instead of a hidden header node, the loading spinner is styled, and a real database error is reported instead of silently shown as "0 cleared".
* Dev      - WordPress Coding Standards compliance across all PHP files.
* Dev      - Removed two debug log entries that were written on every request.
* Dev      - Resolved 12 PHPStan level 5 findings (undefined template variables, always-true checks) with defensive guards.
* Dev      - Removed dead admin CSS (unused role-grid, license, and legacy button-spinner blocks), dead admin JS (unused toast helper and globals), and two unused component properties; added the missing spinner, badge, preview, and migration-progress styles.
* Compat   - Tested up to WordPress 7.0.

= 2.0.0 - 2025-11-24 =

Major release with a favorite count display, an analytics dashboard, an indexed favorites table, and critical bug fixes.

* New      - Favorite count display: a Facebook-style "who liked this" line on activities, formatted as "John", "John and Jane", or "John, Jane, and 10 others".
* New      - A "View all" modal for 3+ favorites, listing members with clickable profile links.
* New      - Real-time AJAX updates on the favorite display when members like and unlike, with a 5-minute cache.
* New      - Custom wp_bp_activity_favorites table with B-tree indexes on activity_id and user_id, a unique constraint against duplicates, and a favorited_at timestamp.
* New      - Chunked background migration for existing favorites: 50 users per batch at 5-second intervals, auto-enabled past 100 users, with AJAX progress tracking and a log.
* New      - Admin analytics dashboard: overview stat cards, recent favorites from the last 7 days, and top 10 trending activities over 7 and 30 days with rank, preview, author, and direct links.
* New      - Automatic monthly cleanup of old read notifications, with a retention period setting, an enable/disable toggle, last-run stats, the next scheduled run, and a manual cleanup button.
* New      - Unified admin interface on a single consolidated page.
* Improve  - Simplified asset loading logic.
* Improve  - Email module cleanup: removed duplicate hooks.
* Improve  - Admin dashboard uses WordPress native postbox styling, with consistent section spacing and clickable activity links throughout.
* Improve  - Object caching with a 5-minute TTL for favorite counts, and indexed database queries in place of user-meta scans.
* Fix      - Duplicate email notifications: members were receiving two identical emails for each favorite action.
* Fix      - Realtime popup notifications now work correctly.
* Fix      - Updated the deprecated bp_core_get_user_domain() to bp_members_get_user_url().
* Dev      - New modules: BPFN_Module_Favorite_Display and BPFN_Favorites_Migration.

= 1.2.3 - 2024-11-23 =
* Improve  - Code quality assessment and documentation.

= 1.2.0 - 2024-11-20 =
* New      - User notification preferences UI.
* New      - Activity type detection.
* Improve  - Notification formatting.

= 1.1.0 - 2024-11-15 =
* New      - Email notification support.
* New      - Customizable email templates.
* New      - Notification grouping.
* Improve  - Performance improvements.

= 1.0.6 - 2024-11-10 =
* New      - Initial release: favorite notifications, BuddyPress integration, and user preferences.

== Upgrade Notice ==

= 2.2.0 =
Emails move to Dashboard > Emails, realtime popups get an on/off switch, and several cleanup and time zone bugs are fixed. Custom email template files in your theme are no longer used; edit the emails in Dashboard > Emails instead. Requires BuddyPress 12.0.

= 2.1.0 =
Adds a Display tab to choose how the favorite line renders (inline usernames, icon and count, or a count that opens the full list) and which icon it uses. The member list now pages instead of stopping at 50. Existing sites keep inline usernames until you change the setting.

= 2.0.1 =
Rebuilt admin under the shared WB Plugins menu, theme-aware colours, RTL fixes, and working member notification preferences. The Enhanced Notifications toggle is removed; BuddyPress stripped its markup, so it never had an effect.

= 2.0.0 =
Major update with the favorite count display, analytics dashboard, an indexed favorites table, and critical bug fixes.

= 1.2.3 =
Maintenance update with code quality improvements. Recommended for all users.

= 1.2.0 =
Adds user notification preferences. Recommended upgrade for a better member experience.

= 1.1.0 =
Adds email notification support. Recommended for users who want email alerts for favorites.
