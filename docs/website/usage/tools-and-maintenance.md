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

- **Automatic cleanup** is on by default and runs monthly through WP-Cron.
- **Retention period**: choose how long read notifications are kept. The minimum is 7 days; the default is 30 days.
- You can disable the automatic cleanup, and see the last cleanup result and the next scheduled run.

Only **read** notifications older than the retention period are removed. Unread notifications are never deleted, so a member never loses a notification they have not seen.

## Manual cleanup

The **Clear Old Notifications Now** button runs the cleanup on demand, using the same retention period as the automatic job. The result message states how many were cleared, the retention window applied, and how many remain. Because only read, past-retention notifications are removed, "cleared 0" is a normal result on a quiet site rather than a sign that the button did nothing. A genuine database error is reported as a failure rather than shown as zero.

## Capabilities and security

Every Tools action requires the `manage_options` capability. The cleanup settings form is protected by the `bpfn_cleanup_settings` nonce, and the migration and cleanup AJAX actions each verify the `bpfn-admin-nonce` nonce.
