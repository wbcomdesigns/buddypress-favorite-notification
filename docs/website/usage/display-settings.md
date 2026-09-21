# Display Settings

The Display tab controls how the "who liked this" line under activities looks. Open **WB Plugins > Favorite Notifications > Display**.

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

A heart can read as the platform's Like reaction, so a star or bookmark helps members tell favourites apart from likes. The choice is stored in the `bpfn_favorite_icon` option.

## Saving

Both settings save together on the Display tab through a nonce-protected form (`bpfn_display_settings`) that requires the `manage_options` capability. The submitted mode and icon are validated against the registered options, so only a real mode and a real icon can be stored. After saving, the tab reloads with a success message.

## Effect on members

Changing the mode or icon changes what every logged-in member sees under activities on the next load. Because the server render and the live AJAX refresh share one renderer, the new mode and icon also apply the moment a member favourites or unfavourites, with no revert to the old layout.

## Overriding per site

Developers can override the mode or icon per activity, or replace the markup entirely, with the display filters (`bpfn_favorite_display_format`, `bpfn_favorite_icon_html`, `bpfn_favorite_display_html`, `bpfn_display_modes`, `bpfn_favorite_icons`). See the [Hooks Reference](../developer-guide/hooks-reference.md).
