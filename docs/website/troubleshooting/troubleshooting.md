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

1. **Email preference.** The author may have answered "No" on the favourites rows of BuddyPress **Settings > Email**, or clicked the unsubscribe link in an earlier email. The on-screen notification can still appear while email is off.
2. **Self-favourite.** A member is never emailed for favouriting their own content. Test with two different accounts.
3. **Email missing or edited.** Check **Dashboard > Emails** for the two favourite emails. If one is missing or broken, use **BuddyPress > Tools > Reinstall emails** to recreate them. That resets every BuddyPress email to its default text.
4. **Site mail delivery.** Emails go through BuddyPress and WordPress mail. If your site cannot send mail generally, favourite emails will not arrive either. Install and configure an SMTP plugin and test with any WordPress email.

The email does not depend on web notifications. It is sent even if the member turned web notifications off.

## The Email tab or Favorite Notifications tab looks wrong

The plugin's own tab is **Settings > Favorite Notifications** (slug `favorite-notifications`). It holds the Web and Real-time switches only. Email alerts are on BuddyPress's own **Settings > Email** tab. Before 2.2.0 the plugin tab wrongly replaced the Email tab. Update to 2.2.0 or later to restore it.

## The "who liked this" line does not appear

1. **Logged-in only.** The line is shown to logged-in members only, by design. Logged-out visitors never see it. The member list is also refused for activities the member cannot read, such as hidden group activity.
2. **No favourites yet.** The line renders only when an activity has at least one favourite.
3. **Theme hooks.** The line renders on both a BuddyX/Reign theme action and the core `bp_activity_entry_content` action, so it should appear on any BuddyPress-compatible theme. If a theme heavily customises the activity entry template and drops the core action, the line may not render there.

## The count or list looks stale

Counts and lists are cached for five minutes but are invalidated whenever a favourite is added or removed, so a like or unlike should refresh them immediately. If a persistent object cache is misbehaving, flushing it clears the `bpfn_favorites` cache group and the `bpfn_dashboard_stats` transient.

## The "Clear Old Notifications Now" button reports 0 cleared

This is usually correct, not a fault. Only **read** notifications older than the retention period are removed, so on a quiet site there may be nothing to clear. The result message states the retention window applied and how many remain. A genuine database error is reported as a failure rather than as "0 cleared".

## The migration notice keeps appearing

The notice links to the Tools tab. Run the migration there. Dismissing the notice persists the dismissal; if it returns, the migration has not completed, so run it from the Tools tab and watch the progress. The notice clears itself once the migration is done.

## The automatic cleanup never runs

1. **Switched off.** Automatic cleanup is off for new installs. Tick **Enable automatic monthly cleanup** on the Tools tab and save.
2. **First run.** The first automatic run happens one month after you enable it, not immediately.
3. **WP-Cron.** The cleanup runs on a custom "monthly" schedule the plugin registers. If your site has WP-Cron disabled or relies on a real system cron, ensure the `bpfn_auto_cleanup_notifications` event can fire.
4. **Failed run.** The Tools tab shows "Last automatic cleanup failed: ..." with the error when a run fails.

You can always run the cleanup manually from the Tools tab.

## Realtime popups do not show

1. **Owner setting.** Popups are off for new installs. Switch them on under **WB Plugins > Favorite Notifications > Display**.
2. **Realtime preference.** A member who has turned realtime off for every type receives no popups by design.
3. **Heartbeat.** Popups rely on the WordPress Heartbeat API only. There is no fallback. A plugin or host configuration that blocks `admin-ajax.php` or Heartbeat will prevent them.
4. **Notifications component.** Realtime assets load only when the BuddyPress Notifications component is active.
