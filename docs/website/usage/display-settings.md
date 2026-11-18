# Display Settings

The Display tab controls how the "who liked this" line under activities looks, and whether real-time popups are on. Open **WB Plugins > Favorite Notifications > Display**.

## Display Mode

Choose one of three modes:

- **Inline usernames**: the line reads with member names, for example "John, Jane, and 10 others". This is the default, and existing sites keep it on update unless changed.
- **Icon and count only**: a static icon plus the favourite count, with no link. Use this when you want the count visible but not clickable.
- **Icon and count, opens the full list**: the icon and count render as a button that opens the full list of members who favourited.

The chosen mode is stored in the `bpfn_display_mode` option and applied to every activity.

## Favorite Icon

Choose the icon shown before the display:

- Heart
- Star
- Bookmark
- Thumbs up
- No icon

With **No icon**, the count in the two count modes is shown as text, for example "12 Likes", so it still reads as a like count. The **Current appearance** preview shows the same.

Until you pick one, the icon matches the like button your members see: a thumbs-up on Reign, BuddyX and BuddyX Pro, a star on other themes. Sites upgrading from a version before 2.2.1 keep the heart they already showed. The choice is stored in the `bpfn_favorite_icon` option.

The icon applies to the "liked by" line under each activity only. The BuddyPress Favorite button keeps the icon that BuddyPress and your theme give it.

## Real-time Popups

The **Real-time Popups** card turns on popups that tell members, without a page refresh, when their activity is favourited.

- **Real-time Popups**: tick "Show members a popup when their activity is favorited, without a page refresh". Off for new installs. Sites upgraded from before 2.2.0 keep popups on.
- **Check Every**: 30 or 60 seconds. The default is 30.

See [Realtime Popups](../features/realtime-popups.md).

## Saving

All settings save together on the Display tab through a nonce-protected form (`bpfn_display_settings`) that requires the `manage_options` capability. The submitted mode, icon and interval are validated against the allowed values, so only a real option can be stored. After saving, the tab reloads with a success message.

## Effect on members

Changing the mode or icon changes what every logged-in member sees under activities on the next load. Because the server render and the live AJAX refresh share one renderer, the new mode and icon also apply the moment a member favourites or unfavourites, with no revert to the old layout.

## Overriding per site

Developers can override the mode or icon per activity, or replace the markup entirely, with the display filters (`bpfn_favorite_display_format`, `bpfn_favorite_icon_html`, `bpfn_favorite_display_html`, `bpfn_display_modes`, `bpfn_favorite_icons`). See the [Hooks Reference](../developer-guide/hooks-reference.md).
