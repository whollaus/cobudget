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

The [CI workflow](../.github/workflows/ci.yml) defines this server matrix:

| Nextcloud | PHP | Database |
| --- | --- | --- |
| 33 | 8.2 | SQLite |
| 34 | 8.2 | SQLite |
| 35 | 8.3 | SQLite |

Each server job installs Nextcloud, enables CoBudget to run migrations, loads its
OCC commands, checks background-job registration and data integrity, and disables/
re-enables the app. A separate job runs tests, builds frontend assets and verifies
the runtime archive. These are configured checks, not a claim that a particular
remote run has passed.

The server smoke matrix does not cover browser interaction, populated server
upgrades or every supported database engine. Older local results and their limits
are retained in [Verification history](../docs/verification-history.md).

## Controller and database integration

These suites bootstrap a real local Nextcloud with CoBudget enabled and use an
existing local test account. Their fixture database writes are rolled back,
including on failure. Run them on each relevant supported server version with
that server's PHP configuration and database extensions.

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
