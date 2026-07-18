# CoBudget Agent Notes

## App

- Visible app name: `CoBudget`
- Nextcloud app id: `cobudget`
- PHP namespace: `OCA\CoBudget`
- This is a technical reset. New installs use only `cobudget` identifiers and `cobudget_*` database tables.
- Nextcloud target versions: min `33`, max `34`
- Current app version: see `appinfo/info.xml`

## Stack

- Frontend: Vue 3, Vue Router 4, `@nextcloud/vue`, Webpack
- Import Nextcloud Vue components from direct component paths, for example `@nextcloud/vue/components/NcButton`, instead of the root barrel import.
- Keep heavyweight UI/runtime dependencies out of the initial `main` entry where this does not weaken the native Nextcloud layout. Route views, the payment modal, and icon picker should stay lazy-loaded where possible.
- Keep the app shell on native Nextcloud layout components (`NcContent`, `NcAppNavigation`, `NcAppContent`) unless there is a confirmed reason to replace them. The native shell preserves sidebar toggling and Nextcloud content sizing.
- Backend: PHP 8, Nextcloud AppFramework controllers, `IDBConnection`
- Database changes live in `lib/Migration`
- API routes live in `appinfo/routes.php`

## Documentation Sources

- Local Codex skill: `/Users/wolfgang/.codex/skills/nextcloud-plugin/skills/nextcloud-development/SKILL.md`
- Official docs: `https://docs.nextcloud.com/server/latest/developer_manual/`
- Nextcloud design docs: `https://docs.nextcloud.com/server/latest/developer_manual/design/index.html`

Use the local Nextcloud skill when changing Nextcloud-specific PHP, routing, Vue integration, app packaging, or design-system behavior.

## GitHub And Public Documentation

- Treat GitHub-facing files as part of every user-visible change:
  - `README.md`
  - `FEATURES.md`
  - `CHANGELOG.md`
  - `SECURITY.md`
  - `.github/` files, when present
  - `screenshots/` documentation assets, when screenshots are changed
- Update `CHANGELOG.md` for notable feature, behavior, security, data model, backup/restore, migration, release-packaging, or public documentation changes.
- Update `README.md` and `FEATURES.md` when functionality, terminology, requirements, warnings, screenshots, or user-facing workflows change.
- Keep GitHub documentation in English.
- Keep installable release archives free of repository-only files such as `screenshots/`, `.github/`, tests, and development metadata.

## Workspace Rules

- Normal app data is workspace-scoped.
- The active workspace is selected through `X-Workspace-Id`.
- The backend may only accept `X-Workspace-Id` values that belong to the authenticated user.
- Entries, projects, categories, payment partners, and templates must not accept foreign workspace IDs.
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
- Shared receipts are physical per-user Files copies. While open they follow the source attachment; after settlement each personal copy is independent.
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
- Recurring entries are generated for due timestamps at or before the current cron run, normalized to 09:00 local server time.

## Build And Release

From `cobudget/`:

```sh
npm run build
npm run release:tar
```

Or rebuild and package in one step:

```sh
npm run release
```

These commands create an unsigned archive for local packaging checks. Public GitHub and App Store releases must use the signed process in `RELEASING.md`:

```sh
PHP_BIN=/path/to/php npm run release:signed -- \
  /path/to/nextcloud/occ \
  /path/to/cobudget.key \
  /path/to/cobudget.crt
```

The private key must be the exact key used for the certificate signing request. Never commit the key, certificate, CSR, `appinfo/signature.json`, detached signatures, checksums, or release archives.

The signed release produces `cobudget.tar.gz`, `cobudget.tar.gz.signature`, and `SHA256SUMS` in the workspace root.
The release archive must keep the top-level `cobudget/` folder. Repository-only assets such as `screenshots/`, tests, GitHub metadata, and development dependencies must not be included in release archives.

GitHub release drafts are created only from pushed tags matching `v*`, for example `vX.Y.Z`. The tag version must match `appinfo/info.xml`, `package.json`, and `package-lock.json`. The tag workflow must never publish or attach the unsigned CI archive. Upload the locally signed artifacts and inspect them before publishing the draft.

Any modification after signing invalidates both signatures and requires a complete rebuild and re-sign.

## Version Rule

Do not bump `appinfo/info.xml` for ordinary frontend, PHP controller, CSS, documentation, or build changes.

Bump the app version only when a database migration, install schema, repair step, or upgrade behavior changes.

The clean alpha baseline starts at `0.2.0`. All unpublished `0.1.x` migration history was consolidated into `Version000001Date20260713000000`; old alpha test installations must be reset/reinstalled instead of upgraded through removed migrations.

After `0.2.0` is published, never edit or replace that initial migration. Every later schema change must use a new additive migration and preserve the supported upgrade path.

## Backups

- `occ cobudget:backup:create <userId>` creates a personal export with `scope: user`, `type: personal_export`, and `restore_supported: true` in the manifest.
- Personal exports can be created manually or regularly from the user settings. They preserve the user's own financial perspective and can only be restored into an otherwise empty CoBudget user state.
- `occ cobudget:backup:restore <userId> <fileName> --force` restores a personal export into that empty target state, remaps the source account to the target account, and creates a safety export first.
- `occ cobudget:backup:create-full --user <storageAdminUserId>` creates a system-scoped full backup with `scope: system` in the manifest and stores it in the specified Nextcloud administrator's Files. Non-admin storage accounts must be rejected.
- `occ cobudget:backup:restore-full --user <storageAdminUserId> --file <fileName> --force` restores a system-scoped full backup and replaces all CoBudget app tables/settings. The Files owner must still be a Nextcloud administrator.
- Full restore supports repeated `--map-user oldUser:newUser` options for server transfers where user IDs changed.
- Full backups export every CoBudget table and the CoBudget user settings for all referenced users. Attachment files are not embedded; only their stored paths are exported.
- Keep personal exports and full backups as separate filename families: `cobudget-personal-export-...zip` and `cobudget-full-backup-...zip`. Legacy `cobudget-backup-...zip` files may remain listable/downloadable for compatibility.
- `occ cobudget:integrity:check` is read-only unless `--repair`, `--merge-category`, or `--merge-payment-partner` is supplied. Projection errors remain manual repairs and make the command fail.
- `occ cobudget:reset-all --confirm=RESET-COBUDGET` deletes every CoBudget table row, all CoBudget user preferences, and only the explicitly owned global CoBudget settings. It must never delete all app config values because Nextcloud stores activation/version metadata under the same app ID.

## Background Jobs

- Recurring entries and reminders are Nextcloud `TimedJob`s in `lib/Cron`.
- Automatic backups are created by `BackupJob` after the fixed 03:00 server-time slot. If cron does not run exactly at 03:00, the next cron run after 03:00 must catch up.
- Backup creation must use the per-user `backup_running_since` lock so a second cron run cannot start another backup for the same user while the previous backup is still running.
- The jobs are listed in `appinfo/info.xml`, but Nextcloud only inserts those jobs into `oc_jobs` during app install/update.
- Because users often deploy this app by replacing the ZIP without a version bump, `Application::boot()` also ensures the jobs exist through `IJobList` in an idempotent way.
- On Nextcloud 33, notification providers must be registered with `IRegistrationContext::registerNotifierService(Notifier::class)`. Do not use the older `registerNotifier()` call.
- WebCron executes only one background job per HTTP call. Freshly registered CoBudget jobs that have never run are therefore prioritized once by resetting `last_checked` while `last_run = 0`.
- Keep recurring entries and reminders on the 5-minute interval unless the external cron cadence changes.
- If background job registration changes, bump `appinfo/info.xml` so Nextcloud runs the app update path and refreshes `oc_jobs`.

## Verification

Before handing off a build:

- Run `php -l` for changed PHP files.
- Run `npm run test:php` after PHP controller, route, workspace, money, template, project, or cron changes.
- Run `php tests/static-security.php` after security- or workspace-related changes.
- Run `npm run test:frontend-smoke` after Vue routing, bundle splitting, or deep-selector changes.
- Run `npm run build`.
- Repack `cobudget.tar.gz` with `npm run release` when preparing a user-test or store-style archive.
- Manually verify Workspaces, entries, projects, categories/payment partners, templates, and recurring payments in Nextcloud when possible.
