# Testing CoBudget

Run commands from the app repository root. Use Node 24, installed frontend
dependencies (`npm ci`) and a PHP CLI compatible with the target Nextcloud.
Use separate local test instances and invented financial data for integration
and browser checks. See the [development guide](../docs/development.md) for setup,
building and guarded fresh-install resets.

## Fast regression suites

| Command | Purpose |
| --- | --- |
| `npm test` | Run the PHP, static-security and frontend suites below. |
| `npm run test:php` | Run `tests/php/run.php`, the lightweight backend regression runner. |
| `npm run test:static-security` | Check source-level security and workspace contracts. |
| `npm run test:frontend-smoke` | Run the frontend suites listed in `package.json`. |

The PHP runner tests controller/service behavior and contracts without a full
Nextcloud bootstrap. Coverage includes workspace/authentication guards, exact cents,
shared allocations and settlements, recurrence, reports, backup/restore and
migration rules. Static checks detect missing safeguards but do not prove runtime
security.

Frontend checks cover routing/lazy bundles, rendering and state transitions,
request-token/timezone behavior, report calculations and navigation, localization,
forms, filters, dialogs, mobile rows, settings and keyboard shortcuts. They use
simulated DOM/network boundaries where appropriate; they do not replace browser
verification. `npm run build` and archive validation are separate checks.

## Compatibility CI

The [CI workflow](../.github/workflows/ci.yml) defines these server jobs:

| Suite | Nextcloud | PHP | Database / lock provider |
| --- | --- | --- | --- |
| Fresh-install compatibility | 33, 34 | 8.2 | SQLite / database |
| Fresh-install compatibility | 35 | 8.3 | SQLite / database |
| Released upgrade and recovery | 34, 35 | 8.3 | SQLite, MariaDB 10.11, PostgreSQL 16 / database and Redis 7 |

Each compatibility job installs Nextcloud, enables CoBudget to run migrations,
checks its OCC commands, jobs and data integrity, disables/re-enables the app,
and runs backup concurrency and ZIP roundtrip/rollback tests. The six recovery
combinations additionally run the disposable upgrade harness below. Services are
created for each Linux job and discarded afterwards; no local database servers or
Herd Pro are required. Service database credentials are isolated test credentials.

Recovery artifacts contain logs and a success marker, never database dumps,
configuration files or user Files. A separate job runs tests, builds frontend
assets and verifies the runtime archive. CI can also be started manually through
`workflow_dispatch` after the workflow is present on GitHub. Configured checks are
not evidence that a particular remote run has passed.

The matrix does not cover browser interaction, every supported engine version,
real storage quota exhaustion or every possible crash location. Results and limits
are recorded in the [readiness review](../docs/release-readiness-1.0.md).

For the first stable 1.0 release, remove Nextcloud 33 from this matrix and raise
`appinfo/info.xml` to a minimum of 34 in the same change. The beta keeps 33 until then.

## Controller and database integration

Except for the disposable upgrade harness, these suites bootstrap a real local
Nextcloud with CoBudget enabled and use an existing local test account. Their
fixture database writes are rolled back, including on failure. Run them on each relevant supported server version with
that server's PHP configuration and database extensions.

### Concurrent backup and restore

```sh
php tests/integration/backup-concurrency.php --nextcloud=/path/to/server --user=admin
```

Starts separate local PHP processes to hold native shared/exclusive locks. Checks
HTTP 503 and retry metadata, overlapping ordinary operations, rejection of a
restore while work is active, deferred jobs, nested safety-backup ownership,
exception cleanup, workspace revalidation, lock-mode transitions and disabled
server locking. User-deletion fixtures mock cleanup and retain admission until the
after-deletion event; they do not delete the real account. No financial writes or
external messages are made. This is included in compatibility CI.

### Released-version upgrade fixture

Run the harness from the app repository with Python 3.12+ and an appropriate PHP
CLI. It requires the local Git tag `v0.8.5`. For example, using an existing local
Nextcloud as a **source template**:

```sh
python3 tests/integration/run-upgrade.py \
  --source ../local-nextcloud/35/server \
  --php "/Users/wolfgang/Library/Application Support/Herd/bin/php83" \
  --evidence /tmp/cobudget-recovery-nc35
```

The harness clones the server into a newly generated `cobudget-upgrade-*`
directory, removes copied configuration and linked workspace apps **before any PHP
runs**, and installs a fresh database and test accounts. It never runs OCC against
the source installation. Server databases require an explicitly supplied disposable
Docker service container and mapped port (`--database=mysql|pgsql`,
`--db-container`, `--db-port`); database names are generated by the harness. Use the
CI service definitions rather than pointing these options at existing databases.
`--redis-port` additionally tests a disposable Redis service on localhost.

The persistent PHP fixture refuses installations without the harness marker,
matching database/data directory and test administrator. It is not rollback-only:

1. Install released `v0.8.5`, seed personal/shared payments, odd-cent splits,
   settlement balances/transfers, a deleted participant, recurrence, an already
   delivered reminder and retained goal history. Write a full archive through real
   Nextcloud Files. Save the isolated database, Files and configuration, preserving
   PostgreSQL table/sequence ownership and grants for Nextcloud’s database role.
2. Copy current app code into the temporary directory and add a test-only barrier
   immediately after migration 13's data update. Kill `occ upgrade` with SIGKILL
   at that barrier. Restore the saved database/Files/config and old code; verify
   the old schema and entire financial graph. Repository migrations are untouched.
3. Upgrade normally. Verify exact graph preservation, mail defaults, restoration
   of the released archive, account mapping including personal preferences,
   retained former participants and generated IDs above imported IDs.
4. Run backup roundtrip/storage-error and separate-process concurrency suites.
5. For database locks and, when supplied, Redis, create a backup then commit newer
   rows/settings. Kill a real restore **after replacement writes and sequence
   synchronization but before commit**. Check that all previous rows/settings
   survive and the killed owner's lock still fences access. Wait for the actual
   15-second test lock TTL, run native expired-lock cleanup where applicable,
   simulate expiry of CoBudget's additional six-hour restore marker, then verify
   new IDs cannot collide and the restore can be retried successfully.
6. Recover the old database/Files/config and matching code again, verify it, and
   delete the temporary installation and generated database even on test failure.

The short TTL and simulated marker age apply only to the disposable test. No live
lock is manually removed. Production TTL must exceed the longest operation, and
abandoned database locks need Nextcloud's normal cleanup. The suite tests one
migration and one transactional restore crash boundary; it does not simulate a
whole database-server crash, full disk or every possible interrupted Files write.
Local SQLite results do not establish MariaDB/PostgreSQL/Redis success.

### Backups and restore

```sh
php tests/integration/backup-roundtrip.php --nextcloud=/path/to/server --user=admin
```

Use an isolated local server and an existing administrator. The suite writes real
ZIP archives, performs full replacement and empty-state personal restore against
the actual database inside an outer rollback transaction, and substitutes an
in-memory Nextcloud Files boundary. It checks exact cents/Unicode, all collected
tables/settings, deleted-goal history (including older archives), regenerated goal
references, populated-target rejection and rollback after a post-insert failure.
It also rejects damaged archives and injects ZIP/storage write failures before
retention pruning, including an adapter that closes the input stream. No receipt files, calendar events or email are created.

This is not a real Files/quota test or a concurrent-writer test. Test real quota/
storage failures and larger datasets on separate restoration targets before 1.0.
User mapping and other database engines are included in the configured recovery
matrix above. Receipt binaries remain outside app backups; see the
[readiness review](../docs/release-readiness-1.0.md).

### Reports

```sh
php tests/integration/annual-overview.php --nextcloud=/path/to/server --user=admin
```

Exercises real queries for personal/shared/solo scopes, exact-cent allocations,
settlements, multi-page payments, hierarchy, filters, date boundaries and foreign
access. Recurrence checks include residual cents, persisted personal workspaces,
normalized tags, zero shares, former participants and replacement of projected
occurrences by saved payments without double counting. Drilldowns contain saved
payments only; forecasts do not create rows or modify allocation state.

### Shared payments

```sh
php tests/integration/shared-payment-lifecycle.php --nextcloud=/path/to/server --user=admin --other-user=testuser
```

Uses two existing accounts. Both owner/member permutations cover editing, payer
versus creator, allocation/type changes, rounding, personal history, foreign
workspace access, settlement permissions, immutable historical sources/balances,
archived areas, independent released-personal editing/deletion and complete
source/projection deletion. Notifications and live push are suppressed; the suite
does not create receipt files or modify existing payments.

### Recurring payments

```sh
php tests/integration/recurring-payments.php --nextcloud=/path/to/server --user=admin --other-user=testuser
```

Checks planned first/next dates, filters, edit dates, stopping/deleting, 06:00
boundaries for legacy 09:00 heads, duplicate runs, inclusive end days, catch-up,
timezone/DST transitions and exact cents. The optional second account enables
shared-projection checks. Existing series are disabled only within the rolled-back
transaction; notification and push output are suppressed.

### Master data, budget criteria and Insurance

```sh
php tests/integration/master-data.php --nextcloud=/path/to/server --user=admin
php tests/integration/budget-without-area.php --nextcloud=/path/to/server --user=admin
php tests/integration/insurance.php --nextcloud=/path/to/server --user=admin
```

These cover scoped category/partner changes and rollback, Without area budget
criteria, and Insurance label creation/editing, filtering, summaries, CSV,
settings, workspace/authentication guards and older backup rows.

### Reminder channels

```sh
php tests/integration/reminders.php --nextcloud=/path/to/server --user=admin --other-user=testuser
```

Runs real settings controllers, reminder queries and Talk delivery orchestration
with in-memory notification/Talk transports and rolled-back fixtures. Covers
channel defaults/combinations, no-area operation, shared recipients, due dates,
repeat/rescheduled runs, unavailable/changed chats, failed opt-in, transport errors,
authentication and backup/reset preferences. No real chats, pushes or messages are
created; this does not verify device delivery or the Talk adapter itself.

## Live Talk integration

Unlike the rollback-only suites, this creates temporary accounts, payments and
real chats, then removes its fixtures. Use a disposable/local server with Talk
23–25 enabled. The explicit flag authorizes fixture creation within that test.

```sh
php tests/integration/talk.php --nextcloud=/path/to/server --create-test-users
```

Covers opt-out, idempotent setup, payment creation and actual bot delivery,
independent bell/Talk preferences, actor exclusion, foreign tokens, extra
participants, reconnection, deleted chats and unauthenticated settings endpoints.
No messages are sent to existing accounts. See [Talk operation](../docs/operations.md#personal-talk-notifications)
for delivery behavior and limitations.

## Manual regression priorities

Follow the [design-system verification checklist](../docs/design-system.md#verification-and-maintenance)
for viewport, theme, focus, keyboard, loading/error and touch checks. For financial
flows, record expected amounts using small invented examples:

- Verify isolation with two accounts and workspaces, including foreign IDs,
  workspace deletion and personal versus full shared-area views.
- Edit shared payments as both creator and another member. Check payer/splits,
  delete permissions, settlement and subsequent independent personal changes.
  Exercise real receipt copies separately; SQL rollback tests do not cover Files.
- Check recurrence before/after the due boundary, future first payments, end dates,
  missed runs and daylight-saving transitions with controlled test dates.
- Restore personal/full backups into appropriate test targets, verify relationships
  and receipt paths, and exercise failures without losing existing data.
- Open report details through totals and composition rows; verify the selected
  period, filters and amount perspective, nested Back navigation, editable saved
  payments and history. Details are pages, not the former annual-detail modals.
- Check prior-period/year comparisons, missing versus empty history, zero baselines,
  ±5% thresholds and partial/leap periods against [report rules](../docs/reports.md).
- Select Forecast, change a filter/breakdown, then visit Payments and return:
  Forecast remains during report changes; the new visit starts with Development.
- Print overview and detail pages with narrow/scrolled tables, active tabs, closed
  sections, long labels and multi-page payments. Check A4 landscape/PDF, repeating
  headings and all matching detail payments. Cancelling must preserve screen state.
- Verify CSV, clipboard and an actual Files export, including displayed-section
  versus whole-report scope, formatting, formula-safe text and filename conflicts.
- Check mobile row editing/history, unsaved-draft protection and independent app
  light/dark mode when Nextcloud uses the opposite theme.

Report the revision, server/PHP/database, checks performed, fixture cleanup and
anything not exercised. A passing build or historical report is not proof of
current end-to-end behavior.

## Planned payment notification delivery

Run `php tests/integration/planned-notifications.php --nextcloud=/path/to/server
--user=admin --other-user=testuser` against a local linked Nextcloud installation.
The rollback-only fixture uses in-memory notification/Talk transports and sends
no real messages. It checks default opt-out, first due timestamp, repeat cron,
per-channel failures/retries, private-chat validation, shared recipients,
projection/deletion/settlement exclusions, backup account remapping and log privacy.
The recurring-payments fixture additionally verifies that future originals and
generated repetitions are armed while personal projections remain unarmed.
Actual mobile push/Talk transport requires a separately authorized live test.

### Calendar reminders (native DAV)

`php tests/integration/calendar-reminders.php --nextcloud=/path/to/server --user=admin --other-user=testuser`

Requires the current migration and two existing local users. Uses temporary native
DAV calendars and future reminders inside a rolled-back database transaction.
Covers UTC/DST, start alarms, idempotency, changes, moves, deletion, membership,
foreign-calendar rejection, absent writable calendars, retry and reset cleanup.
Payment-controller checks clear the reminder date and verify automatic removal
from the overview and the actual native calendar objects without calling sync
manually. Cases include open/completed/archived personal reminders and shared
payments edited by either the owner or another member; the payment amount stays
unchanged. Field-update checks separately change date, title, description, clear
the description and change all three together through payment saves. They verify
the overview and native event/alarm data, stable event identities, DST dates and
private descriptions for personal payments and both shared-area editors. They
also verify automatic archival defaults for new reminders, preservation of each
participant’s explicit choice, and re-enabling archival in the reminder editor.
Per-entry sync checks cover failed writes (including unchanged content), successful
siblings, historical skips, disabled-channel cleanup, old-target cleanup, retry
recovery and ownership/authentication without changing reminder revisions or
calendar preferences.
Quick-postponement checks cover independent/personal/shared reminders, legacy empty
titles, retained metadata, delivery re-arming, unchanged finances, native calendar
updates, past/same dates, stale revisions, closed statuses and foreign/anonymous
access. Frontend tests cover dirty-draft guards, duplicate actions and local 09:00
across DST, year and leap-day boundaries.
No attendees or invitations are created. Run on both supported local instances;
actual device notification delivery still depends on Calendar/client settings.

Notification/calendar integration fixtures enforce Nextcloud's 64-character
`file_locks.key` limit with a temporary SQLite trigger. This catches overlong keys
that SQLite would otherwise accept but length-enforcing databases reject. It is
a targeted compatibility guard, not a substitute for a full database-engine matrix.

The reminder integration fixtures also cover the personal organizer: independent
CRUD, ownership, revision conflicts, personal shared-payment states, archival,
reactivation, backup inclusion/reference remapping and delivery. Calendar tests
exercise native DAV event identity, descriptions/deadlines, alarm removal on
completion/archive, deletion and per-member isolation. All fixture data rolls
back and transport messages are captured in memory. `reminder-organizer.mjs`
checks frontend drafts, failures, concurrent submissions, stale search responses
and filtering.

### Bidirectional reminder/calendar synchronization

`tests/integration/calendar-reminders.php` additionally exercises private-calendar
imports, independent-field merges, conflicting edits and stale resolution tokens,
legacy baselines, ETag write guards, event deletion/tombstones/reactivation,
completion preservation, personal/shared-payment isolation, all-day DST conversion,
past-date delivery suppression, deleted calendars and revoked access. All fixtures
and native DAV writes are rolled back; no attendees or invitations are created.
`ReminderCalendarMergeTest.php` covers merge policy; `reminder-organizer.mjs` checks
explicit conflict choices and preservation of imported non-default hours.

### Email notifications

`php tests/integration/email-notifications.php --nextcloud=/path/to/server --user=admin`
uses real Nextcloud mail message objects with an intercepted transport and rollback-only
database fixtures. It checks opt-in, current account addresses, inactive/invalid recipients,
safe message content/logging, rejected recipients, reminder retries, rescheduling,
completion and old-backup delivery defaults. No email is sent externally.
