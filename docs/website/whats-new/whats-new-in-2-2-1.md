# What's New in 2.2.1

Version 2.2.1 brings the plugin's wording in line with the Like button, makes the member pages faster and more accessible, and fixes the logged-out Settings tab. It is a safe update with no settings to change.

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

## For developers

The new filter `bpfn_default_favorite_icon` sets the default icon when the site owner has not picked one.

See the full list in the plugin changelog.
