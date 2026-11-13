# Operating CoBudget

This guide covers background jobs, optional Talk delivery and restore limits.
For installation, start with the [README](../README.md). For backup commands and
data invariants, see [Architecture](architecture.md#backups).

## Background jobs

CoBudget uses Nextcloud background jobs for recurring payments, reminders, automatic backups and budget snapshots. Configure system cron to call Nextcloud's `cron.php` every five minutes as the web server user; see the [Nextcloud background-job documentation](https://docs.nextcloud.com/server/33/admin_manual/configuration_server/background_jobs_configuration.html#cron).

If the log reports `failed to create instance of background job`, check the CoBudget jobs from the Nextcloud directory, using the same PHP installation and user as your cron setup:

```sh
php occ cobudget:background-jobs:check
```

The command prints each job's loaded file, constructor/dependency errors and queue registration. It does not execute, reserve or remove jobs. A successful check confirms that the jobs can be constructed and are registered; it does not verify their actual execution or the system cron schedule. A web-only problem can still require checking the web server's PHP configuration and deployed app files.

After resolving any loading errors, restore missing registrations with:

```sh
php occ cobudget:background-jobs:check --repair
```

Repair checks all four jobs before adding missing registrations. Existing registrations, including legacy jobs with empty-array arguments, keep their schedule and state. Jobs are run later by the configured Nextcloud scheduler. The command must be installed on the server along with the updated CoBudget files before it is available.

## Personal Talk notifications

In **Settings → Notifications**, choose **Reminders via Nextcloud** and **Reminders via Talk** independently. Nextcloud reminders default to on; Talk reminders default to off. These controls are available even without areas. Nextcloud delivers the bell notification; mobile push also depends on the Nextcloud notification app, registered devices and device preferences. Talk push follows Talk/device preferences.

A due reminder is processed on the next background-job run. Personal payments remind their owner; shared source payments remind every active area member, including the creator, using each recipient's preferences. Former/missing accounts are excluded. With both channels disabled, the due reminder is processed without sending; later opt-in does not replay it. Changing/rescheduling the reminder follows the payment editor's normal reminder-reset behavior.

Shared-area notifications remain separate: **Personal Talk notifications** covers new payments by other area members and appears only with shared areas enabled. Its opt-in is independent of both reminder channels and the existing bell preferences for new payments and settlements.

Nextcloud Talk must be installed and enabled for your account, including permission to create conversations. Enabling either Talk option creates or reuses the same private, unlisted, read-only **CoBudget** conversation and native bot. Only its recipient belongs to the conversation and cannot add participants. No app password, external webhook or manual room token is needed. The adapter targets Talk 23–25 on Nextcloud 33–35.

Payment-created messages contain creator, area, amount, currency and area link. Reminder messages contain the explicitly entered reminder text (or a generic title), amount, due date and CoBudget/area link. Payment descriptions and receipts are not copied to Talk. Reminder text and names are escaped to prevent mentions/Markdown injection. Chat history remains after payments are changed or deleted. Settlements, edits and automatic recurrence creation do not themselves generate Talk messages; a due reminder on a payment is handled independently.

If you leave/delete the chat or its access settings change, delivery stops. Use **Reconnect personal chat** to create a replacement without changing the other Talk preference. A disabled bot must first be re-enabled by an administrator. Delivery never recreates a destination automatically. Talk failures are logged without message contents and do not suppress an already generated bell notification; Talk delivery is best-effort, without a separate retry queue. A bell failure retains the existing reminder-job retry behavior and delays that recipient's Talk attempt until the bell succeeds. A partial shared-reminder failure can therefore repeat delivery to earlier recipients on retry; there is no per-recipient delivery ledger.

Backups preserve both reminder preferences but do not export Talk history or conversation tokens; reconnect after moving to another server. Resetting CoBudget clears the preferences without deleting existing Talk history.

## Restore resource limits

CoBudget limits compressed backup size, total uncompressed size, individual JSON size and ZIP compression ratio before restoring an archive. Administrators can override the conservative defaults in Nextcloud's `config.php` when larger legitimate backups are required:

```php
'cobudget.restore_max_archive_bytes' => 33554432,
'cobudget.restore_max_uncompressed_bytes' => 67108864,
'cobudget.restore_max_json_bytes' => 33554432,
'cobudget.restore_max_compression_ratio' => 200,
```

Only positive integer values are accepted; invalid values fall back to the defaults shown above.

Full backups contain data for all referenced accounts and must be stored in a
Nextcloud administrator's Files. Personal exports contain only the exporting
user's normalized personal perspective. Neither archive embeds receipt files;
back up the associated Nextcloud Files separately.

### Planned payment activation messages

In Settings → Notifications, the Planned payments row enables Nextcloud and Talk
independently; both start off. The first future payment and each generated
repetition become eligible at their saved due time, and are announced by the next
reminder cron run. Generation of repetitions still uses 06:00 server time.
Messages show payment type, total amount, currency and date, with a CoBudget link.
They confirm an entry becoming due, not a bank transfer. Shared originals notify
active members according to their own choices, without duplicate messages from
personal projections. Reminder messages remain a separate event.

Delivery progress is persisted per recipient/channel, so a failed channel can be
retried without re-sending successful channels. External delivery is not exactly
once: a crash between transport success and persistence can cause a duplicate.
Existing past payments are excluded on upgrade; newly enabled channels do not
replay already processed activations. Standard reminder/Talk failure behavior
above remains unchanged.

### Calendar reminders

The optional Calendar channel applies to Reminders. It appears only if the account
has a writable local event calendar. After enabling, explicitly select the target;
future existing reminders and new reminders are then synchronized with an alert
at their exact timestamp. Nextcloud's calendar background jobs and synchronized
clients deliver calendar alerts according to their own settings.

CoBudget owns these generated events: changing/removing the reminder in CoBudget
updates/removes its event. Disabling the channel removes its managed events.
Changing calendars moves them; if access to the old calendar was revoked, cleanup
remains pending, while the new writable target can still receive the reminders.
Changes to title, description and start time in private calendars are imported
with field-level conflict checks. Shared calendars remain export-only. A deleted
event archives its personal reminder; the event stays deleted until explicit future
reactivation in CoBudget. In shared areas, title/time remain common payment fields,
while descriptions, completion and archival remain personal. Synchronization runs
on opening/focusing the overview, after saves, and through the background job.
Conflicting values must be chosen explicitly using the calendar warning in the
overview. Unavailable/deleted calendars never imply deletion of every reminder.
The reminder text and link are visible according to the
chosen calendar's sharing settings; no payment description or receipts are copied.

Financial exports do not contain calendar events or server-specific calendar
connections. After a financial restore, select and enable the calendar again.
Calendar sync failure never rolls back a saved payment; settings show pending
synchronization and the reminder job retries it. Calendar and Nextcloud reminders
can each alert, so enabling both can produce two notifications.

Calendar failures log `CoBudget calendar synchronization pending`. The context
includes `operation` (`settings-read`, `settings-save` or `synchronize`),
`exceptionType` and source locations for the exception chain. Exception messages,
SQL parameter values and reminder contents are deliberately omitted. The retry
button synchronizes immediately; a persistent error is not normal cron delay.

### Notification channels and email

Select each channel independently in Settings → Notifications. Mobile push also
depends on Nextcloud and device notification settings. Talk uses the private
CoBudget chat described above.

Email uses the current address in the recipient's Nextcloud account. An administrator
must configure outgoing mail in Nextcloud. Without a valid account address, the
Email controls cannot be enabled; add the address in the personal Nextcloud settings.
Email is optional for reminders, planned/recurring payment activations, new payments
in shared areas and settlements. All email preferences start disabled.

## Backup and recovery

CoBudget provides three different ways to take data out of the app:

| Output | Purpose | Restore behavior |
| --- | --- | --- |
| CSV, clipboard and printed reports | Analysis, sharing or a readable record of the selected data. | Not a CoBudget backup format. Filters, section scope and amount perspective matter. |
| Personal export ZIP | Move or preserve one user's personal financial perspective. Shared amounts become independent personal payments. | Import into an empty CoBudget user state only; local IDs and the source user are remapped. |
| Administrator full backup ZIP | Preserve CoBudget financial tables and portable settings across users. | Replaces the app's current tables/settings; existing target accounts or explicit account mappings are required. |

Neither ZIP includes receipt binaries, Talk history or calendar events/connections.
The attachment paths need matching Nextcloud Files after restoration. Use your
existing backup solution for Nextcloud Files to protect and recover those files;
receipt-file backup is intentionally outside CoBudget. Keep a separate complete
Nextcloud backup (database, configuration and data) and a copy
outside the instance. A ZIP stored only in the same Nextcloud cannot recover a
lost server by itself.

### Controlled full restore

1. Rehearse on a separate target first. Record the CoBudget/Nextcloud versions,
   account IDs, receipt locations, row counts and a few expected balances/splits.
2. CoBudget takes an exclusive app-data lock for backups and restores. Ordinary
   requests and background jobs hold shared admission for their complete work, so
   an already running operation prevents maintenance from starting. Retry once it
   finishes. While maintenance owns the lock, new app requests receive HTTP 503
   with a short retry hint; jobs defer until their next scheduled run. This applies
   to the web interface and CoBudget's OCC commands. Other Nextcloud apps remain
   available. Direct database modifications must still be stopped separately.
3. Preserve an independent full Nextcloud backup before replacing any data. Check
   free storage for both the restore archive and CoBudget's automatic safety backup.
4. Run the CoBudget OCC backup/restore commands as the normal Nextcloud web user,
   with the PHP version/extensions used by the instance. The storage account must
   be an administrator. Use `occ help cobudget:backup:restore-full` and the
   [documented commands](architecture.md#backups); verify any `--map-user` pairs.
   The app guard does not require Nextcloud-wide maintenance mode. App OCC
   commands may be unavailable while Nextcloud itself is in maintenance mode.
5. Run `occ cobudget:integrity:check` without repair options. Verify counts, exact
   balances, settlements, personal/shared perspectives and actual receipt access.
   Reconnect calendar/Talk destinations where needed. On a separate test server,
   keep outgoing notifications disabled to avoid replaying due events to real users.
6. Reload open CoBudget browser tabs after restoration before editing the restored
   data. The app lock is released automatically on success or normal failure. If
   restore fails, inspect the failure and current state before retrying; a database rollback does
   not roll back external Files operations. Keep the independent recovery copy.

Deleted budget goals can leave historical snapshots. Export and restore retain
these values with a detached goal reference; they are not resurrected as live goals
or added to a new goal's history.

### Server locking requirements

Nextcloud file locking must be enabled (`filelocking.enabled`). Backup/restore
refuses to run without it. The database locking provider and the configured native
locking cache are used through Nextcloud's public API; multi-node installations
must share a coherent locking provider. Configure `filelocking.ttl` longer than the
longest request, background job or restore. Native locks can expire after that
interval, so a too-short TTL defeats mutual exclusion. Normal failures release the
lock; hard process failures follow Nextcloud's native lock recovery, plus the
existing restore-specific stale-lock timeout. After a hard process kill, database
locks require Nextcloud's expired-lock cleanup; Redis locks expire in Redis.
CoBudget's additional restore marker can delay another restore for up to six hours.
Normal exceptions release both guards immediately. The test harness shortens the
native TTL and simulates the marker's age only inside disposable installations.
Never manually clear a lock while its owner is still running.

The guard protects CoBudget entry points, not external SQL writers or changes to
Files made independently of the app. Coordinate a complete server recovery with
your Nextcloud backup solution. A successful app-data restore alone does not prove
that the separately protected receipt files have been recovered.

### Before relying on scheduled backups

Check the archive's existence and size, download a copy and rehearse restoration.
The writer checks ZIP operations and stored size, but this is not a checksum-based
verification of every storage backend. Test quota/full-disk failures on a disposable
target. Confirm that old backups are only pruned after the new archive has been
stored, and monitor failed background jobs. Raising restore resource limits can
increase PHP memory requirements: test with representative data before changing them.
