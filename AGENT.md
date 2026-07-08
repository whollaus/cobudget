# CoBudget Agent Notes

## App

- Visible app name: `CoBudget`
- Nextcloud app id: `cobudget`
- PHP namespace: `OCA\CoBudget`
- This is a technical reset. New installs use only `cobudget` identifiers and `cobudget_*` database tables.
- Nextcloud target versions: min `27`, max `33`
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

The release script is equivalent to packaging the runtime app files from the workspace root:

```sh
tar --exclude="*.map" -czf /private/tmp/cobudget.tar.gz \
  cobudget/appinfo \
  cobudget/css \
  cobudget/img \
  cobudget/js \
  cobudget/lib \
  cobudget/l10n \
  cobudget/templates \
  cobudget/composer.json
mv /private/tmp/cobudget.tar.gz cobudget.tar.gz
```

The release archive is `cobudget.tar.gz` for manual testing, GitHub releases, and future Nextcloud App Store releases.
The release archive must keep the top-level `cobudget/` folder. Repository-only assets such as `screenshots/`, tests, GitHub metadata, and development dependencies must not be included in release archives.

GitHub releases are created only from pushed tags matching `v*`, for example `v0.1.0`. The tag version must match `appinfo/info.xml`.

## Version Rule

Do not bump `appinfo/info.xml` for ordinary frontend, PHP controller, CSS, documentation, or build changes.

Bump the app version only when a database migration, install schema, repair step, or upgrade behavior changes.

The public alpha baseline starts at `0.1.0`. The internal pre-release migration history was squashed into the first install schema before publication, so old internal test installations should be reset/reinstalled instead of upgraded through those removed migrations.

## Backups

- `occ cobudget:backup:create <userId>` creates a personal export with `scope: user`, `type: personal_export`, and `restore_supported: false` in the manifest.
- Personal exports can be created manually or regularly from the user settings. They preserve the user's own perspective and are not restored into existing CoBudget data.
- `occ cobudget:backup:restore <userId> <fileName> --force` is intentionally disabled for personal exports and points users to Admin full restore instead.
- `occ cobudget:backup:create-full --user <storageUserId>` creates a system-scoped full backup with `scope: system` in the manifest and stores it in the specified user's Nextcloud Files.
- `occ cobudget:backup:restore-full --user <storageUserId> --file <fileName> --force` restores a system-scoped full backup and replaces all CoBudget app tables/settings.
- Full restore supports repeated `--map-user oldUser:newUser` options for server transfers where user IDs changed.
- Full backups export every CoBudget table and the CoBudget user settings for all referenced users. Attachment files are not embedded; only their stored paths are exported.
- Keep personal exports and full backups as separate filename families: `cobudget-personal-export-...zip` and `cobudget-full-backup-...zip`. Legacy `cobudget-backup-...zip` files may remain listable/downloadable for compatibility.

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
