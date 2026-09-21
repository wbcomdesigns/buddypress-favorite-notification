# Troubleshooting

## The plugin shows "requires BuddyPress" and does nothing

BuddyPress is not active. Install and activate BuddyPress, then confirm its Activity and Notifications components are enabled under **BuddyPress > Settings > Components**.

## Notifications are not being created

Check, in order:

1. **Notifications component.** The plugin only registers its `favorite_notifier` component when the BuddyPress Notifications component is active. Enable it.
2. **Self-favourite.** A member is never notified for favouriting their own content. Test with two different accounts.
3. **Member preference.** The author may have turned the web channel off for that activity type at **Settings > Favorite Notifications**. Turning notifications on there restores them.
4. **Already notified.** Notifications are grouped per activity, so a second favourite of the same activity by another member updates the existing grouped entry rather than adding a new one.

## Emails are not arriving

1. **Email channel preference.** The author may have the email channel off for that type, or answered "No" on the BuddyPress **Settings > Notifications** favourites row. The on-screen notification can still appear while email is off.
2. **Site mail delivery.** Emails are sent with `wp_mail()`. If your site cannot send mail generally, favourite emails will not arrive either. Install and configure an SMTP plugin and test with any WordPress email.
3. **Template not found.** If a custom template override path is wrong, the plugin falls back to a built-in inline message rather than failing, so a broken override does not stop delivery, but it does replace your layout. Verify the theme override path is `your-theme/buddypress/bp-favorite-notification/emails/`.

## The "who liked this" line does not appear

1. **Logged-in only.** The line is shown to logged-in members only, by design. Logged-out visitors never see it.
2. **No favourites yet.** The line renders only when an activity has at least one favourite.
3. **Theme hooks.** The line renders on both a BuddyX/Reign theme action and the core `bp_activity_entry_content` action, so it should appear on any BuddyPress-compatible theme. If a theme heavily customises the activity entry template and drops the core action, the line may not render there.

## The count or list looks stale

Counts and lists are cached for five minutes but are invalidated whenever a favourite is added or removed, so a like or unlike should refresh them immediately. If a persistent object cache is misbehaving, flushing it clears the `bpfn_favorites` cache group and the `bpfn_dashboard_stats` transient.

## The "Clear Old Notifications Now" button reports 0 cleared

This is usually correct, not a fault. Only **read** notifications older than the retention period are removed, so on a quiet site there may be nothing to clear. The result message states the retention window applied and how many remain. A genuine database error is reported as a failure rather than as "0 cleared".

## The migration notice keeps appearing

The notice links to the Tools tab. Run the migration there. Dismissing the notice persists the dismissal; if it returns, the migration has not completed, so run it from the Tools tab and watch the progress. The notice clears itself once the migration is done.

## The automatic cleanup never runs

The cleanup runs on WP-Cron on a custom "monthly" schedule the plugin registers. If your site has WP-Cron disabled or relies on a real system cron, ensure the `bpfn_auto_cleanup_notifications` event can fire. You can always run the cleanup manually from the Tools tab.

## Realtime popups do not show

1. **Realtime preference.** A member who has turned realtime off for every type receives no popups by design.
2. **Heartbeat.** Popups rely on the WordPress Heartbeat API (or its AJAX fallback). A plugin or host configuration that blocks `admin-ajax.php` or Heartbeat will prevent them.
3. **Notifications component.** Realtime assets load only when the BuddyPress Notifications component is active.
