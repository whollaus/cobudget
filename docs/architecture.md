# CoBudget Architecture

This document describes CoBudget's architecture, data invariants and operational
behavior for contributors and coding assistants. Paths and commands refer to the
app repository root unless stated otherwise.

Read the [development guide](development.md) for builds and tests, and the
[design system](design-system.md) for UI conventions.

## App

- Visible app name: `CoBudget`
- Nextcloud app id: `cobudget`
- PHP namespace: `OCA\CoBudget`
- This is a technical reset. New installs use only `cobudget` identifiers and `cobudget_*` database tables.
- CoBudget 1.0: Nextcloud min `34`, max `35`, with a 34/35 test matrix.
- Current app version: see `appinfo/info.xml`

## Stack

- Frontend: Vue 3, Vue Router 4, `@nextcloud/vue`, Webpack
- Import Nextcloud Vue components from direct component paths, for example `@nextcloud/vue/components/NcButton`, instead of the root barrel import.
- Keep heavyweight UI/runtime dependencies out of the initial `main` entry where this does not weaken the native Nextcloud layout. Route views, the payment modal, and icon picker should stay lazy-loaded where possible.
- Count navigation badges in SQL over the same authorized payment query as the
  overview. Keep de-duplication for attachment/tag joins; ordinary summary reads
  can retain index ordering because membership/share joins are unique per user.
- Wait for the payment editor chunk to load before polling for its mounted Vue
  instance. A slow network must not discard the action that opened the editor.
- Keep the app shell on native Nextcloud layout components (`NcContent`, `NcAppNavigation`, `NcAppContent`) unless there is a confirmed reason to replace them. The native shell preserves sidebar toggling and Nextcloud content sizing.
- Backend: PHP 8, Nextcloud AppFramework controllers, `IDBConnection`
- Database changes live in `lib/Migration`
- API routes live in `appinfo/routes.php`
- The authenticated sync endpoint returns the installed CoBudget version when
  requested with `checkAppVersion=1`. The first version check becomes due 24
  hours after loading the app. The next regular sync request in a visible tab
  includes the version check, at most once per 24 hours. Only the update
  notice's reload action replaces the page for a version change.

## Nextcloud compatibility

The supported range is declared in `appinfo/info.xml`; keep new API usage
compatible with every supported release. The [README](../README.md#requirements)
lists runtime requirements and [tests/README.md](../tests/README.md#compatibility-ci)
describes the automated server matrix.

- Use public auth/router modules instead of removed JavaScript globals and
  private request-token aliases; `fetchJson.js` uses `getRequestToken()` from
  `@nextcloud/auth`, while the main HTTP client uses `@nextcloud/axios`.
- Keep Symfony command return types (`execute(): int`, `configure(): void`)
  compatible with Console 7 and preserve registration on Nextcloud 34/35.
- Use `OCP\DB\ISchemaWrapper` in migrations; do not depend on concrete Doctrine
  table/column types. Preserve published migrations as described below.
- Recheck database-specific query behavior and newly used Nextcloud APIs for
  each supported version; a SQLite smoke test does not establish compatibility
  with every database engine or a populated server upgrade.

## Workspace Rules

- Normal app data is workspace-scoped.
- The active workspace is selected through `X-Workspace-Id`.
- The backend may only accept `X-Workspace-Id` values that belong to the authenticated user.
- Entries, projects, categories, payment partners, and templates must not accept foreign workspace IDs.
- The personal reminders organizer is another explicit user-global scope. It uses
  only the authenticated account, not `X-Workspace-Id`; linked payment visibility
  still requires personal ownership or active shared-area membership.
- Workspace administration is user-global and must not send or depend on `X-Workspace-Id`.
- Workspace list, create, rename, and delete are global per authenticated user.

## Security And Data Rules

- Always require an authenticated user for user data APIs.
- Mutating endpoints must validate that referenced IDs belong to the active workspace or are globally available where intended.
- Keep error responses compatible: `{ "error": "message" }` with an appropriate HTTP status.
- Critical multi-step operations should be transactional.
- Delete and update statements should include user/workspace scope, not rely only on a previous read check.
- Store and process money internally through `amount_cents` integer cents.
- The legacy `amount` decimal column may still exist for compatibility, but backend calculations and new writes must use `amount_cents` as the canonical source.
- Shared payments store immutable per-member allocation snapshots in `cobudget_entry_shares`. Area default percentages apply only when a payment is created or its allocation-affecting fields are explicitly edited.
- Fractional cent residuals are balanced cumulatively per area and separately for expenses and income. Every share write must reverse the previous snapshot residual before applying the new one in the same transaction; never assign every remainder cent permanently by member order.
- Shared area payments are canonical `entry_kind = shared` source rows. Every participant with a positive exact-cent allocation has exactly one `entry_kind = personal` projection linked through `source_entry_id` and `cobudget_entry_shares.personal_entry_id`.
- Open personal projections are locked. Source edits must transactionally rebuild their values, hashtags, exact cent allocations and receipt copies. Direct edits or deletion of locked personal rows are forbidden.
- Destructive payment operations must resolve the complete source/projection deletion graph through `EntryProjectionService::prepareEntryDeletion()` in the same transaction. Never delete a shared source, locked projection, or reverse `personal_entry_id` reference in isolation.
- An open shared payment may be deleted only by its original creator (`created_by`) or the area owner. Deleting it must remove the source and every locked personal projection atomically. Solo-area personal payments remain freely deletable by their owner.
- Personal projections live in the participant's persisted Basis workspace, use the allocated cents as their full personal amount, and never carry recurrence or reminder automation.
- Settlement must atomically snapshot balances/transfers, mark shared sources settled, unlock personal projections, clear `source_entry_id`/`allocation_basis_points` and reverse `personal_entry_id` links, and detach receipt copies from their source. Personal rows keep `project_id`, `settlement_id` and `settled_at` as origin metadata, but remain `entry_kind = personal`, owned and assigned 100 percent to their user, and are independently editable.
- Every source edit must write the corresponding value changes to each affected personal projection's own `cobudget_entry_history` rows using personal cents and one shared editor/time/change-group context. Never expose shared total-amount history as a personal amount history.
- Personal overview, analytics, budgets and personal exports must count only the authenticated user's personal rows. Area views and settlement calculations use shared source rows. Never count both representations on one financial surface.
- Area members, default shares and archival state may change only when no open shared source payments exist. Removing a settled member must detach their personal rows and clone area-only categories/payment partners into their personal Basis workspace.
- Shared receipts are physical per-user Files copies. While open they follow the source attachment; after settlement each personal copy is independent. Receipt access requires a matching owner, relative path and positive Files ID. Downloads and copies use the verified stream. Physical deletion rechecks the ID inside Files locking. New backup manifests authenticate receipt references with an instance-specific MAC. Legacy, foreign and remapped references retain metadata until the file is reattached.
- Deleted Nextcloud accounts that still occur in shared financial data are represented by random `former:` tombstone IDs stored in `cobudget_deleted_users`. Never preserve or reuse the deleted login user ID as the historical participant key.
- Before anonymizing an area member, materialize every missing open personal projection from the stored exact-cent share snapshot. Never recalculate an existing snapshot during account deletion.
- Preserve every project-linked personal payment and its persisted personal Basis workspace when a Nextcloud account is deleted. Only standalone personal data may be removed with the account.
- Former participants remain in area splits and immutable history, but must not be selectable as payer, direct single-user target, notification recipient, or new area owner.
- Areas with former participants may keep and settle their existing shared payments, but must reject new payments, new members, split changes, and recurring generations until the former participant is removed.
- After all open payments are settled, the area owner may remove a former participant from the future split. Settled personal payments, settlement snapshots, and history must remain intact.
- Deleting a Nextcloud account must transactionally transfer owned shared areas to an active member and remap all surviving financial references before the account disappears. Cleanup failures must abort the Nextcloud deletion.
- Workspace deletion must reject workspaces referenced as another member's persisted personal workspace and must never remove a shared area owned by another user. Validate the remaining projection graph before committing workspace deletion or a personal reset.
- Full restore must validate the complete workspace/project/member/source/share/projection graph before deleting current data and again after insertion but before commit. Any mismatch must roll back the restore.
- Settled share snapshots are immutable history. A settled positive share may deliberately remain without `personal_entry_id` after that user's personal reset, and historical settlement users do not need to remain active area members.

## Recurring Entry Rules

- Recurring payment series use `recurrence_series_id` to keep history linked.
- Only the newest/current series head should carry active recurrence fields: `recurrence_interval`, `recurrence_multiplier`, `recurrence_next_date`, and `recurrence_end_date`.
- When `RecurringEntriesJob` creates the next entry, it must do so transactionally, deactivate the previous series head, and transfer the active recurrence fields to the newly created entry.
- Historical entries may keep `recurrence_series_id` and `recurrence_parent_id`, but must not keep `recurrence_next_date`.
- Recurring entries are generated for due timestamps at or before the current cron run, normalized to 06:00 local server time. Existing heads saved at 09:00 are due from 06:00 on their saved server-calendar day. Series end dates include their entire calendar day.

## Backups

- `occ cobudget:backup:create <userId>` creates a personal export with `scope: user`, `type: personal_export`, and `restore_supported: true` in the manifest.
- Personal exports can be created manually or regularly from the user settings. They preserve the user's own financial perspective and can only be restored into an otherwise empty CoBudget user state.
- `occ cobudget:backup:restore <userId> <fileName> --force` restores a personal export into that empty target state, remaps the source account to the target account, and creates a safety export first.
- `occ cobudget:backup:create-full --user <storageAdminUserId>` creates a system-scoped full backup with `scope: system` in the manifest and stores it in the specified Nextcloud administrator's Files. Non-admin storage accounts must be rejected.
- `occ cobudget:backup:restore-full --user <storageAdminUserId> --file <fileName> --force` restores a system-scoped full backup and replaces all CoBudget app tables/settings. The Files owner must still be a Nextcloud administrator.
- Full restore supports repeated `--map-user oldUser:newUser` options for server transfers where user IDs changed.
- Full backups export the financial CoBudget tables and the portable CoBudget user settings for all referenced users. Calendar runtime mappings and calendar connection settings are excluded. Attachment files are not embedded; only their stored paths are exported.
- Budget snapshots are self-contained historical values and survive normal goal deletion. Export/restore detaches a missing goal reference using `budget_goal_id = 0` (the immutable schema is non-nullable). This preserves the snapshot without accidentally attaching it to a newly generated goal ID after restore. Live goal references are still remapped for personal imports; detached history remains outside the live-goal history view. Integrity checks must not offer to delete snapshots merely because their goal no longer exists.
- Transactional preference replacement clears the public `IUserConfig` cache after direct SQL deletion and after rollback, so subsequent restores and callers cannot reuse stale imported settings. PostgreSQL sequences are advanced monotonically: `setval` is not transactional, so never lower a sequence to the archive maximum. Sequence failures must abort restoration.
- Archives must contain `settings.json` and every table listed in their manifest, including zero-row tables. Additive tables absent from an older manifest remain optional. ZIP writes/finalization and the stored byte count must succeed before retention pruning; failed storage writes remove the partial file.
- `AppDataLock` admits normal HTTP requests (including GET normalization), recurrence/reminder/snapshot jobs, user deletion and data-management OCC commands under a shared native Nextcloud lock. Personal/full backups and restores, including scheduled backups, take the exclusive lock before collecting or replacing data. A competing admitted operation causes fail-fast rejection before financial work; no unsafe shared-to-exclusive upgrade is permitted inside an admitted operation. Nested safety backups reuse the exclusive owner.
- `AppDataLockMiddleware` returns a localized HTTP 503 with `code: cobudget_busy` and `Retry-After: 5`. Jobs skip a blocked run and retry on their normal schedule. Workspace ownership is revalidated after admission, because constructors may have read it before a restore replaced the data. Controller constructors must not mutate app data.
- The admission key is a complete 64-character SHA-256 digest, shared across users and server nodes through `ILockingProvider`. Exclusive admission acquires a shared lock and atomically changes its type; this clears the database provider's cached shared state before subsequent operations in a long-running process. Request/job/command boundaries release in `finally` or middleware cleanup. User deletion retains admission from `BeforeUserDeletedEvent` through `UserDeletedEvent`, including Nextcloud account removal; an aborted core deletion retains the native lock until request-end cleanup. Never release all native locks from app code.
- Nextcloud file locking must be enabled for backup/restore; otherwise these operations fail closed. Native lock TTL/recovery and a coherently configured distributed provider remain server requirements. Keep the lock TTL longer than the longest request/job/restore; never clear a lock while its owner is running. The older restore-specific lock remains an additional restore guard. Direct SQL edits or uncoordinated external writers are outside the admission protocol.
- Receipt binaries remain in Nextcloud Files and are intentionally excluded from app archives. Use the installation's existing Nextcloud Files backup solution for their backup and recovery; preserve the matching stored paths.
- Keep personal exports and full backups as separate filename families: `cobudget-personal-export-...zip` and `cobudget-full-backup-...zip`. Legacy `cobudget-backup-...zip` files may remain listable/downloadable for compatibility.
- `occ cobudget:integrity:check` is read-only unless `--repair`, `--merge-category`, or `--merge-payment-partner` is supplied. Projection errors remain manual repairs and make the command fail.
- `occ cobudget:reset-all --confirm=RESET-COBUDGET` deletes every CoBudget table row, all CoBudget user preferences, and only the explicitly owned global CoBudget settings. It must never delete all app config values because Nextcloud stores activation/version metadata under the same app ID.

## Background Jobs

- Recurring entries and reminders are Nextcloud `TimedJob`s in `lib/Cron`.
- Automatic backups are created by `BackupJob` after the fixed 03:00 server-time slot. If cron does not run exactly at 03:00, the next cron run after 03:00 must catch up.
- Backup creation must use the per-user `backup_running_since` lock so a second cron run cannot start another backup for the same user while the previous backup is still running.
- The jobs are listed in `appinfo/info.xml`, but Nextcloud only inserts those jobs into `oc_jobs` during app install/update.
- Because users often deploy this app by replacing the ZIP without a version bump, `Application::boot()` also ensures the jobs exist through `IJobList` in an idempotent way.
- Notification providers must be registered with `IRegistrationContext::registerNotifierService(Notifier::class)`. Do not use the older `registerNotifier()` call.
- WebCron executes only one background job per HTTP call. Freshly registered CoBudget jobs that have never run are therefore prioritized once by resetting `last_checked` while `last_run = 0`.
- Keep recurring entries and reminders on the 5-minute interval unless the external cron cadence changes.
- If background job registration changes, bump `appinfo/info.xml` so Nextcloud runs the app update path and refreshes `oc_jobs`.

## Migration baseline

The clean installation baseline starts at `0.2.0`. All unpublished `0.1.x` migration history was consolidated into `Version000001Date20260713000000`; old development test installations must be reset/reinstalled instead of upgraded through removed migrations.

After `0.2.0` is published, never edit or replace that initial migration. Every later schema change must use a new additive migration and preserve the supported upgrade path.

## Additional security controls

Security reporting and support policy live in [SECURITY.md](../SECURITY.md).
Operational restore limits and Talk setup are in [Operating CoBudget](operations.md).

### User Enumeration

Shared-area user search and direct member additions both honor Nextcloud's `shareapi_allow_share_dialog_user_enumeration` system setting. When enumeration is disabled, CoBudget does not accept guessed user IDs through the member API and does not disclose whether a submitted account exists.

### Atomic Mutations

Multi-row payment and budget mutations use database transactions. Notifications are sent only after a successful commit. Nextcloud Files operations cannot participate in database transactions, so CoBudget stores or removes receipt metadata atomically, cleans up failed uploads, and performs physical file deletion only after the related database commit.

### Request Limits

CoBudget bounds payment list page sizes and offsets on the server. Expensive authenticated endpoints such as payment search, CSV export, analytics, user search, and personal-export inspection use Nextcloud's native per-user rate limiting and return HTTP 429 when the configured request budget is exceeded.

Filtered payment totals and date-group summaries are streamed from the database and are not returned as an unbounded hidden result set alongside paginated rows.

### Bulk import

The browser validates the complete file in sequential dry-run packets before enabling confirmation.
Preview and commit resolve the same account currency as the payment form. Both validate the resulting timestamp against the signed SQL INTEGER limit after applying the request timezone and the existing rule for today.
Each binary PUT carries at most 50 payments and 192 KiB. The endpoint bounds its stream read before JSON decoding.
A file contains up to 5 MiB and 5,000 rows. The JSON readers preserve fractional-cent amounts for validation before floating-point conversion can round them. Exact numeric cents retain their existing digest. The ordered payment array identifies the import. Each original row
has a unique nullable import key scoped to the importing account, workspace, area, file digest and absolute row
position. CoBudget stores the key in the same transaction as the payment, shares, projections and lookup writes.
Each shared commit packet creates at most 100 member allocations. Manual payments
and projections have no import key. The preview excludes existing keys. Personal exports discard source-scope keys.
A deleted original can be imported again.

### Automatic folder imports

The personal `enable_payment_imports` preference defaults to off and gates the manual packet endpoint, folder rule API and worker. Switching it off preserves rules and journal checkpoints for later runs. Archived areas retain their folder reservations and checkpoints. Their rules are excluded from settings, activity counts and scheduling until the area is restored. Shared-area imports hold a shared area lock through file processing. Archiving takes the exclusive lock before checking open payments. The five-minute Nextcloud job checks due rules. An authenticated POST can run one bounded check immediately for an active rule in the caller’s workspace. Scheduled and on-demand folder imports require Nextcloud file locking. Disabling it preserves the schedule and all checkpoints. The manual action checks the current membership under the rule lock and uses the same destination lock, journal and packet limits as cron. Files without read or delete permission stay in the inbox and consume no file or packet allowance. Each scan reports at most one such file. Each belongs to one user and workspace and binds a Files folder ID and path to a personal or shared destination. The runner checks the account and resolves folder access on each run. Each preview and commit applies the manual import permissions and split. Users with upload access to the inbox can submit payments on the rule owner’s behalf.

The nullable unique reservation stores the Files node ID as soon as a folder is configured, including for paused rules. An upgrade or full restore retains the existing reservation for legacy conflicts, otherwise the oldest rule keeps the folder. Conflicting rows retain their preferences and require a new folder selection.

The rule selects which results to report (none, errors or all). The owner selects delivery channels per account. Nextcloud is enabled by default, email and personal Talk are opt-in. The notification settings row is visible when any of the owner’s workspaces has an active rule.

The worker validates the complete delivery across bounded preview packets. The first successful preview checkpoint pins the currency for all packets. For older unpinned partial writes the worker recovers the currency from a surviving original payment in the destination. A per-rule filename cursor resumes a bounded inbox scan. Before checking waiting and fully booked sources in journal ID order, the worker selects up to twelve eligible inbox files by name, Files node ID, ETag and a bounded content hash. The parser verifies the selected hash against the bytes it reads under the Files lock, including on storage with stale or missing ETags. When source checks span ticks, the same database update saves the selected versions, the next filename and the source cursor. New uploads join a later selection and cannot restart an existing pass. Only matching versions may enter the journal after reconciliation. Legacy cursors without a saved selection restart at the first source. A database transaction commits each write packet together with its progress. A persistent, indexed journal identifies deliveries by account, workspace, destination, filename and ordered payment digest. The transition from preview to writing also saves the validated input (at most 5 MiB). After an interruption the worker finishes that snapshot, checks the source filename before new deliveries and remembers conflicting payloads in the destination. If a validated write packet loses a required lookup, the transaction rolls back that packet. The journal keeps the snapshot, cursor and a per-file message. The worker retries one waiting delivery per run, oldest retry first, and retains the remaining packet budget for other work. The runner records the result timestamp, message and error flag together. Results recorded before 1.1.6 have an unknown error flag. Settings display outstanding messages independently of the latest result. The last commit releases the snapshot. Re-uploading completed data, including under a different filename or with reordered object fields, leaves existing payments alone. Under the exclusive Files move locks, the worker records the intended archive filename and reserves any changed payment data in the journal before moving the booked file. The reservation survives removal or relocation of the archive. Formatting changes and reordered payment fields still identify the same delivery. The normal worker and crash recovery compare the archived payment digest under a shared Files lock with the journal before completing the report. Recovery first locates the source by its Files node ID. If that ID is no longer visible to the owner, recovery can identify the saved destination by a bounded SHA-256 fingerprint recorded under the exclusive Files locks immediately before the move. The normal worker and recovery verify that fingerprint even when the node ID matches. An oversized replacement stays in the inbox for a fresh check. Older journal entries without a stored fingerprint retain the node-ID fallback. A differing payload receives a durable conflict reservation before other inbox deliveries can run. The file goes to `Fehler` and the original booking remains intact. Replays use the same verification without sending a second success notification. External channels may receive a repeat if a process dies between dispatch and acknowledgment. A completed filename with different data goes to `Fehler`. The conflict reservation for that payload takes precedence over older preview records under any filename. If the old filename is occupied, reconciliation follows the saved Files node ID. A renamed input is bound to the completed delivery before either filename can be processed as new. When a booked source changes, the worker checks its contents under a shared Files lock before admitting new deliveries in that inbox. Read permission suffices to record a conflicting payload even when the archive move lacks delete permission. An unresolved read or lock on the changed source defers the inbox check. Ordinary blocked files and inaccessible error archives leave other deliveries eligible. To resume in a replacement inbox, the rule owner selects the new Files node at the saved path. Rule edits and scheduled runs cannot implicitly confirm the replacement. Successfully handled files move to `Verarbeitet`. The Files move checks destination vacancy under its exclusive source and destination locks before recording the archive name. Moves to `Fehler` also compare the source bytes with the rejected version under those locks. A replacement remains in place for a fresh check. Personal exports omit rules and the journal. Full backups preserve both and restore rules paused until the user confirms the folder.


### Membership Integrity

Each Nextcloud user can occur only once in an area. The `0.2.0` install schema contains a database-level unique index on `(project_id, user_id)` that protects concurrent member-add requests.

### Optional Talk Delivery

Personal Talk delivery is opt-in per authenticated account. Setup endpoints never accept another user ID or an arbitrary destination token. The native Talk adapter creates a private, unlisted, read-only conversation with one regular user participant and the CoBudget bot. Before every send it checks the room ownership marker, visibility, federation/SIP settings, all attendees (including dormant guests) and bot bindings. Talk's account/group restrictions are respected. Missing or changed destinations are not recreated during payment creation.

New-payment messages go only to active shared-area members other than the creator. Due reminders go to the personal payment owner or every active shared-area member, including the creator, with independent Nextcloud (default on) and Talk (default off) reminder preferences. Reminder delivery does not depend on the area feature switches. Both Talk opt-ins share the same private conversation and locking/validation path. Descriptions and receipts are excluded; explicit reminder text is included for opted-in reminder recipients; user-controlled names are escaped to avoid chat mentions/Markdown injection. No external webhook or user app password is used. The app-owned bot has no receive/webhook feature. Talk messages are separate retained copies; turning off notifications, resetting CoBudget or deleting a payment does not remove existing Talk history. Transport failures do not roll back financial writes and logs omit payment contents.

### Planned payment notifications

`RemindersJob` also processes committed planned-payment activations through
`PlannedPaymentNotificationService`. Future originals are armed at creation (or
when an ordinary payment is moved to the future); each generated recurrence is
armed separately. Shared personal projections never generate another event.
Migration arms only existing future, unsettled originals, avoiding historical
notification floods. The two channel preferences default to off and do not depend
on area feature switches. Recipients are the personal owner or active shared-area
members including the creator. Descriptions and receipts are not copied.

An entry lock serializes cron delivery. `planned_delivery` snapshots channel
eligibility using hashed recipient keys; each successful channel is persisted
before processing the next. Removed accounts/members and opted-out channels are
skipped. `planned_notified` completes the event. A later opt-in does not replay a
processed event. Full backup account remapping also remaps these hashed keys.
Financial edits are independent from delivery failures. As with any external
transport without an idempotency key, a process crash after delivery but before
persisting its result can still cause a duplicate on retry.

### Personal reminder organizer

`ReminderService` stores one personal `cobudget_reminders` row per account/payment,
plus independent rows with a null payment ID. Existing payment reminder fields
are adopted lazily and idempotently, preserving delivery flags. Shared payment
title/time remain canonical on the payment; notes, action labels, deadlines,
completion, archival and dismissal belong only to the recipient. Source changes
invalidate stale drafts. Payment references are immutable after creation and
must never grant access to a projection or another user's personal payment.
No organizer mutation changes financial amounts, allocations or ownership.

A payment reminder's new time clears prior personal completion/archive/dismissal.
Removing its payment, reminder field or membership removes the personal copy.
Deleting an independent item removes its row; deleting a linked personal item
retains a dismissal marker to prevent lazy recreation. Ordinary reminders become
archived 14 days after their reminder time; completed reminders use their
`completed_at` timestamp instead. Both require `keep_open` to be disabled.
Completion and archival are independent timestamps; both may be set. Archived
items sort into the archive group while retaining completion. Automatic archival
uses the relevant timestamp plus exactly 14 × 86400 seconds for its effective
date, inclusively at the threshold. It is derived on reads without a database
write or cron dependency; the open overview reevaluates it every minute. Reactivation requires a
future time only when neither completion nor archival remains.
Payment-form reminder details are written inside the payment transaction, under
the same account lock and revision/signature checks as organizer edits. Validation
or a stale draft rolls back the entire payment mutation. Personal fields are
scoped to the authenticated editor, never the selected payer; calendar sync runs
only after commit. Payment-form saves preserve an existing personal automatic-archival choice; only new reminders default to automatic archival.
Quick postponement accepts only open reminders and a future instant later than
the current reminder time. It uses the same revision/signature-protected save,
retains personal metadata and automatic-archive choices, resets delivery flags,
and propagates the canonical time to linked payments and participant calendars.
Legacy linked reminders may retain an empty title when only their time changes.
Account locks and revision checks serialize personal edits. Reminder instants are
stored as UTC timestamps; the editor converts to/from the browser's local time.

Backups include personal organizer data and remap payment references. Personal
exports detach references absent from that export while retaining personal notes.
Reset/account deletion remove personal organizer rows. Operational calendar links
remain excluded from backups and retained for external cleanup. No Tasks objects
or bidirectional external completion are implemented.

### Calendar reminders

`CalendarReminderService` synchronizes opted-in accounts after committed data
changes via `DataChangeService`, and `RemindersJob` reconciles failures and deletions.
The canonical personal owner or active shared members are eligible; projections
never create additional events. New past reminders are not backfilled on opt-in.
Existing managed events remain synchronized even after their reminder time.
The organizer API adds per-row `calendar_sync_pending` from account-scoped links,
target and content fingerprints without contacting DAV. Failed native writes
invalidate the stored fingerprint but retain the event identity; stale-target or
disabled-channel links remain pending until cleanup succeeds. Intentionally
skipped historical reminders are not errors. The authenticated, CSRF-protected,
rate-limited row retry verifies reminder ownership and reconciles the current
account configuration without writing preferences or reminder drafts.

`ReminderCalendarBackend` enumerates writable local VEVENT calendars through
`OCP\Calendar\IManager`. The DAV adapter isolates native update/delete APIs;
recheck account access before each operation. Only tracked random object URIs with
matching UID and `X-COBUDGET-REMINDER` markers may be changed or removed. Events use
UTC instants and a DISPLAY VALARM at PT0S for active reminders, no attendees,
payment amounts or receipts. Only explicit reminder descriptions and deadlines
are copied into DESCRIPTION; payment descriptions are not automatically copied.
Completed/archived reminders retain existing events without alarms. Migration
adopts existing calendar links by payment ID without replacing their event UID. Private, unshared calendar edits are imported with a three-way field merge for
SUMMARY/title, DESCRIPTION/personal description and DTSTART/remind_at. Shared
calendars are export-only; calendar sharing never delegates payment editing.
`sync_state` stores the common baseline, conflict values/token and deletion tombstone.
Missing baselines require explicit choices for differences. Conflicts never use
last-writer-wins; resolution validates the current revision and event ETag.
DAV reads bypass its request-local object cache, lock the calendar object in the
same transaction as import/write, and verify the ETag before writing (SQLite uses
its transaction conflict handling). Nested organizer operations share a counted
account lock; never acquire the same native exclusive lock twice.
Only events known to have been published can trigger personal archival when
missing/soft-deleted. Unavailable/deleted calendars and failed ACL checks are errors,
not event deletions. Keep a tombstone to suppress recreation until explicit future
reactivation. Date changes preserve personal closed states, including those of other
shared-payment recipients. Past imports do not replay notifications. Closed events
have no alarm. Unsupported recurring events or attendees remain pending unchanged.
All-day dates map to 09:00 in the account timezone; UTC/TZID instants are preserved.
Opening/focusing the overview performs a protected POST refresh; cron reconciles
before reminder delivery. Broadcast only after commit and only the reminder domain
to avoid recursive synchronization. Calendar operations never change financial data.

`cobudget_calendar_links` is an operational mapping to external calendar data,
not part of financial backups. Retain cleanup links across payment deletion,
workspace deletion, financial restore and reset so the next job can remove stale
events. Server-local calendar preferences are cleared on financial restore and
personal reset; setup must be explicit again. Full reset also retries cleanup.
Lost permissions leave cleanup pending rather than bypassing calendar ACLs.
Account deletion attempts cleanup then removes the mapping to avoid retaining the
login ID; already inaccessible shared-calendar copies cannot be removed.

### Native lock keys

Nextcloud's database locking provider stores keys verbatim in `file_locks.key`
(`VARCHAR(64)`). Hash the complete namespace plus account identity into a
64-character SHA-256 key; never prepend a namespace to a full SHA-256 digest.
SQLite does not enforce the declared length, so notification integration fixtures
add a temporary, transaction-scoped length guard to exercise the production limit.

### Optional email delivery

`EmailNotificationService` resolves the current enabled account's primary email
address at send time and uses public `IMailer` with the configured server sender.
All four `notify_*_email` choices default off and are account-wide, independently
of Push/Talk. Former/deleted/disabled accounts and invalid addresses are skipped;
shared events retain membership, actor exclusion and feature checks. No arbitrary
recipient can be supplied. Messages use recipient language and plain text, include
only personal reminder text or event summaries, and omit payment descriptions and
receipts. Logs exclude addresses, content and transport exception messages.

Reminders persist `email_notified` separately. Delivery holds the organizer account
lock, rereads authorized active rows and marks only successful or skipped deliveries;
mail failures retry without resetting Push/Talk. Date changes rearm the flag;
past calendar imports and old delivered reminders/backups do not replay emails.
Planned payment delivery adds an email channel to the existing hashed-recipient
snapshot. Shared-area creation/settlement mail is best effort after commit. A process
crash between SMTP acceptance and persistence can still duplicate a retry. Backup
and reset include the four preferences and reminder delivery flag; restoring an old
archive derives the flag from its original `notified` value. Mail server configuration
and account addresses remain owned by Nextcloud and are never copied into CoBudget
backups. Previously delivered email copies cannot be retracted.

### Personal API keys

Both the global administrator `enable_api_access` app setting and the personal `enable_api_access` preference default to off (`no`). Changing the global administrator switch (`AdminApiController::saveSettings`), enabling personal API access, and creating, updating, pausing or deleting personal API keys require Nextcloud password confirmation (`#[PasswordConfirmationRequired]`) and emit secret-free structured audit logs (`LoggerInterface::info`). Disabling blocks key use and creation while pause and revocation remain available. Key management serializes writes and area cleanup per owner. The settings form sends its last observed key revision and receives HTTP 409 on a concurrent edit. Users manage personal API keys in `cobudget_api_tokens` through the CSRF-protected
settings endpoints (`ApiTokenController`), which apply `Cache-Control: no-store, private` to both success and error responses. Secrets are generated from 32 random bytes
(`cb_` + 64 hex characters), displayed once upon creation, and stored only as
64-character SHA-256 hashes alongside a short display prefix. Each key is bound to one owned workspace, either a per-target payment action matrix
(`project_mode = 'matrix'` storing `{ "personal": [...], "<areaId>": [...] }` with
actions `read`, `write`, `create`, `delete` in `project_ids_json`) or legacy target
flags (`allow_personal`, `project_mode` in `none`/`selected`/`all`, `project_ids_json`),
global action scopes (`reports:read`, `settings:read`, `settings:write`), and an
optional expiry timestamp. `reports:read` requires `read` access on both personal
payments and all active areas in the workspace (`enable_projects` enabled) so
workspace-wide analytics never leak hidden area totals. API key hashes and `enable_api_access` are excluded from personal exports and full
backups, and are cleared on restore, personal/full reset, account deletion, workspace
deletion, and area removal (narrowing or pausing affected keys without ever widening
access).

### External REST API v1 (`/apps/cobudget/api/v1/...`)

- **Stateless token authentication (`ApiV1Controller`, `ApiV1AuthService`)**: All 14 v1 controller actions take zero PHP parameters (preventing Nextcloud's `Dispatcher` from pre-decoding request bodies or mixing JSON keys into `{id}` parameters) and carry `#[PublicPage]`, `#[NoCSRFRequired]`, `#[NoSameSiteCookieRequired]`, and `#[NoTwoFactorRequired]` without `#[CORS]`. Callers authenticate solely via `Authorization: Bearer cb_...` or `X-CoBudget-Token: cb_...`; authenticated Nextcloud web sessions (`ISession` `user_id`), Nextcloud login cookies (`nc_session_id`, `nc_token`, `nc_username`), `Basic` auth, AppAPI, `REMOTE_USER` / non-Bearer `REDIRECT_HTTP_AUTHORIZATION`, dual auth headers, and query-string tokens (keys or values) are rejected, while Nextcloud's anonymous session/SameSite cookies (`oc_sessionPassphrase`, `oc<instanceid>`, `nc_sameSiteCookie*`) emitted on API responses are tolerated for HTTP clients with cookie jars. (Note: Nextcloud core `OC::handleLogin()` intercepts `Authorization: Basic` prior to app routing.)
- **Brute-force & rate limiting (`ApiV1AuthService`, `AppDataLockMiddleware`)**: `IThrottler::sleepDelayOrThrowOnMax()` runs before recording failed credential or forbidden-header attempts so blocked IP subnets receive `429` without writing further brute-force rows, while valid active 256-bit keys authenticate under per-IP and per-token (`read`/`write`/`reports`) `ILimiter` quotas without collateral CGNAT/IPv6-`/56` brute-force lockouts, and paused/expired stored keys return `401` without incrementing the IP brute-force counter. After entering the shared app-data lock, the controller looks up the hash again and resolves the current account, workspace and grants before dispatch. `last_used_at` updates via `touchLastUsed()` are throttled to at most once every 60 seconds per key and write strictly to `last_used_at`/`updated_at` without touching entry data. All JSON bodies are read bounded (64 KiB max) post-auth with explicit `Content-Type: application/json`, and responses enforce `X-User-Id: ''`, `Cache-Control: no-store, private`, `Pragma: no-cache`, `Vary: Authorization, X-CoBudget-Token`, `X-Content-Type-Options: nosniff`, and `Referrer-Policy: no-referrer` (alongside Nextcloud `Response` defaults). `AppDataLockMiddleware` converts `AppBusyException` on `ApiV1Controller` into HTTP `503` JSON with `Retry-After: 5`.
- **Resource & scope isolation (`ApiV1EntriesService`, `ApiV1ReportsService`, `ApiV1SettingsService`)**: Services never inject `IUserSession` or `WorkspaceAwareTrait`. Every key operates strictly inside its bound `workspace_id`, resolves permissions dynamically against active workspace areas (honoring `project_mode = 'matrix'` and legacy flags, excluding archived areas or areas disabled via `enable_projects`), exposes released settled personal `#501` entries only under personal scope (`GET`/`PATCH`/`DELETE`), rejects cross-area/reassigned `project_id` moves (`409`), enforces `can_use_reports` + non-wildcard budget goal filtering in reports, and restricts `settings` reads/writes to an explicit allowlist (`PATCH` returns only status and updated keys and logs an audit entry).


Payment creates and updates return only `entry.id` when the key lacks read permission for the actual target. Starting or expanding a series requires create permission for the same target. With no grants for a target, an individual payment returns 404. The key information endpoint includes account currency and feature switches only with `settings:read`. Area payment mutations and settlement serialize on the project row before modifying entries. Report scans page by increasing ID in blocks of 512, bounded by the initial maximum ID. Budget goals share a single scan with indexed rules and deduplicate overlapping matches. See the [external API request limit](api-request-limits.md) for the ingress requirement.
