# Favourite Count Display

The plugin adds a "who liked this" line under each activity, in the style of a social feed. It is shown to logged-in members only. Logged-out visitors do not see it.

## Display modes

The line has three modes, chosen on the [Display tab](../usage/display-settings.md) and stored in the `bpfn_display_mode` option:

- **Inline usernames** (`inline`, the default): reads "John", "John and Jane", or "John, Jane, and 10 others". The first two names link to profiles, and the "N others" part is a link that opens the full list.
- **Icon and count only** (`counter`): a non-interactive icon plus the count, for example a heart and "12". This mode is display-only and is not focusable or clickable.
- **Icon and count, opens the full list** (`modal`): the same icon and count rendered as a button that opens the full list of members who favourited.

Only the modal mode and the inline "N others" link open the full list. The counter mode is deliberately static so there is no dead control in the tab order.

## Favourite icon

You can choose the icon that precedes the display, stored in the `bpfn_favorite_icon` option: **heart**, **star**, **bookmark**, **thumbs up**, or **none**. A heart reads as the Like reaction to many members, so a star or bookmark helps keep favourites visually distinct.

## The "View all" list

The full member list opens in a modal, loaded over AJAX. It shows a page of members at a time with a **Load more** control, so it stays usable on activities with thousands of likes. Each row links to the member's profile.

- The page size defaults to 20 members and can be changed with the `bpfn_favorites_modal_per_page` filter.
- By default there is no ceiling on how many members can be paged through. A site can set a hard ceiling with the `bpfn_who_favorited_limit` filter (default 0, meaning no limit); anything beyond a positive ceiling is shown as a "+N more" line.

## Live updates and caching

The line updates over AJAX as members favourite and unfavourite. Favourite counts and the who-liked lists are object-cached for five minutes. The cache is versioned per activity, so a favourite or unfavourite invalidates every cached page for that activity at once, whatever offset produced it, and the line never serves a stale count.

## Rendering

The display renders through a single code path used both for the server-side render and the post-favourite AJAX refresh, so a format or icon change never reverts when a member clicks like. It is emitted on two hooks so it shows across themes: the BuddyX/Reign theme action `bp_activity_before_post_footer_content` and the core action `bp_activity_entry_content`. A per-activity guard prevents a double render if a theme fires both.

Developers can reshape or fully replace the markup with the display filters. See the [Hooks Reference](../developer-guide/hooks-reference.md).
