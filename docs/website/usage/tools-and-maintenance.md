# Tools and Maintenance

The Tools tab handles two housekeeping jobs: migrating existing favourites into the plugin's table, and cleaning up old read notifications. Open **WB Plugins > Favorite Notifications > Tools**.

## Favourites migration

The plugin keeps favourites in its own indexed table (`{prefix}bp_activity_favorites`) for fast lookups. On a site that had favourites before the table existed, those need to be moved in.

- On activation, the plugin checks whether any favourites need migrating and, if so, shows an admin notice linking to this tab.
- The migration runs in chunks so a large site does not time out. Sites with more than 100 members with favourites run the migration in the background in batches, with progress tracking and a log. Smaller sites run it in one pass.
- You can watch progress on the Tools tab while it runs.

The migration is a one-time step. New favourites are written to the table automatically as members favourite content.

## Automatic notification cleanup

Old read notifications are removed automatically so they do not pile up in the database.

- **Automatic cleanup** is off for new installs. Tick **Enable automatic monthly cleanup** and save to turn it on. It then runs monthly through WP-Cron.
- Sites upgraded from before 2.2.0 keep it on, unless you had saved it off.
- **Retention period**: choose 7, 15, 30, 60 or 90 days. The default is 30 days. Any other stored value falls back to 30.
- The first automatic run happens one month after you enable it, not immediately.
- The tab shows the last cleanup result and the next scheduled run.

Only **read** notifications older than the retention period are removed. Unread notifications are never deleted, so a member never loses a notification they have not seen. The retention window is measured in UTC, so it does not depend on the MySQL server clock.

If an automatic run fails, the Tools tab shows a warning, "Last automatic cleanup failed: ...", with the date and the error. It does not report a failed run as "deleted 0".

## Manual cleanup

The **Clear Old Notifications Now** button runs the cleanup on demand, using the same retention period as the automatic job. The result message states how many were cleared, the retention window applied, and how many remain. Because only read, past-retention notifications are removed, "cleared 0" is a normal result on a quiet site rather than a sign that the button did nothing. A genuine database error is reported as a failure rather than shown as zero.

## Capabilities and security

Every Tools action requires the `manage_options` capability. The cleanup settings form is protected by the `bpfn_cleanup_settings` nonce, and the migration and cleanup AJAX actions each verify the `bpfn-admin-nonce` nonce.
