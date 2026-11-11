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
