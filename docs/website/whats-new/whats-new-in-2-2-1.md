# What's New in 2.2.1

Version 2.2.1 brings the plugin's wording in line with the Like button, makes the member pages faster and more accessible, and fixes the logged-out Settings tab. It is a safe update with no settings to change. Read the update notes below, then run the short retest.

## Update notes

- **Requirements are unchanged:** BuddyPress 12.0 or later, PHP 8.1 or later, WordPress 6.5 or later.
- **Edited emails keep your wording.** On a site updating from 2.2.0 the two favourite emails switch to the "liked" wording only if you had not edited them. If you edited one, change its wording yourself in **Dashboard > Emails**.
- **Your icon is kept.** A site updating from an earlier version keeps its heart icon.
- **Translations** for German, French, Spanish, Italian and Brazilian Portuguese are updated for the new wording.

## Wording that matches the Like button

Notifications, emails, real-time pop-ups, email unsubscribe messages and the member notification settings now say "liked" instead of "favorited", so they match the Like button members click. On sites updating from 2.2.0, the two emails are updated too, unless you edited them.

## An icon that matches your theme

Until you choose an icon on the **Display** tab, the liked-by line uses the same icon as your theme's Like button: a thumbs-up on Reign, BuddyX and BuddyX Pro, a star on other themes. Sites updating from an earlier version keep the heart icon they had.

## Faster activity pages

Activity pages no longer send one extra request per liked post after they load, so busy streams load faster.

## Accessibility

- Screen readers announce "Liked by" before the names in the liked-by line.
- The liked-by list is announced by its heading, and pressing Escape returns focus to the button that opened it.
- The real-time pop-up's close button is larger and no longer covers the message.
- With **No icon**, the count reads as text, for example "12 Likes", instead of a bare number.
- The liked-by list returns focus to its button even after a like or unlike refreshed it, and it has a name while it loads.

## Fixes

- A logged-out visitor opening Settings > Favorite Notifications goes to the login screen, like the other Settings tabs, instead of a page-not-found error.
- Liking an activity no longer stops with an error when the BuddyPress Notifications component is off. Emails still go out, the member Favorite Notifications tab is hidden, and the plugin's admin screens explain what is paused.
- **Clear Old Notifications Now** says why it cannot run while the Notifications component is off, and stays disabled until it is on.
- The **Settings > Favorite Notifications** tab shows a heart icon on Reign and BuddyX Pro.
- The admin layout file was renamed so the plugin zip installs on hosts whose firewalls block the old file name.

## Retest after updating

1. Like an activity from a second account and confirm the notification, the email and the popup say "liked".
2. Open the activity page and confirm the liked-by line shows the icon you expect.
3. Log out and open **Settings > Favorite Notifications**; you should reach the login screen.
4. If your site has the BuddyPress Notifications component off, like an activity and confirm it works and the plugin screens explain what is paused.

## For developers

The new filter `bpfn_default_favorite_icon` sets the default icon when the site owner has not picked one. See the [Hooks Reference](../developer-guide/hooks-reference.md).

See the full list in the plugin changelog.
