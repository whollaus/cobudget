# Changelog

All notable changes to CoBudget are documented in this file.

## [0.2.4] - 2026-07-15

### Added

- Added a remote signing helper for hosted Nextcloud installations that need `occ integrity:sign-app` to run on the server.

### Fixed

- Fixed frontend smoke checks for analytics when income tracking is disabled.

### Documentation

- Documented the internal remote signing workflow for future releases.

## [0.2.3] - 2026-07-15

### Fixed

- Hid income-only analytics values in forecast cards and related tooltips when the income module is disabled.
- Kept analytics development charts focused on enabled modules so expense-only setups no longer show income or saldo context.

## [0.2.2] - 2026-07-14

### Fixed

- Fixed the administrator settings bundle so it no longer depends on an asynchronously loaded settings component chunk that can be missing after packaged App Store installs.
- Extended release-archive validation to reject installable packages that reference frontend chunks not included in the archive.

## [0.2.1] - 2026-07-14

### Fixed

- Rebuilt the installable package without macOS AppleDouble and Finder metadata. The polluted `0.2.0` App Store archive made Nextcloud interpret files such as `._BackupController.php` as PHP classes and fail during app bootstrap.
- Centralized release-archive validation for CI, local builds and signed releases so packages containing macOS metadata or repository-only files are rejected before publication.

## [0.2.0] - 2026-07-13

### Initial Alpha Baseline

- Track personal income and expenses across isolated workspaces.
- Organize payments with categories, payment partners, labels, hashtags, templates, reminders, recurrences and receipts stored in Nextcloud Files.
- Create personal and shared areas with exact percentage allocation, materialized personal shares, fair cent rounding, settlements and settlement history.
- Preserve personal financial shares when areas, memberships or Nextcloud users change.
- Analyze personal finances with budget goals, forecasts, trends, filters and CSV exports.
- Create personal exports and administrator-owned full backups with guarded restore workflows and OCC commands.
- Use CoBudget in German or English with responsive layouts, keyboard support and light/dark theme integration.
- Protect workspace isolation, shared-area permissions, file access and critical multi-row mutations with centralized validation and transactions.
- Prepare a certificate-based release pipeline with internal Nextcloud app signing, a detached App Store signature, and draft-only GitHub tag releases.
- Exclude macOS AppleDouble and Finder metadata from installable release archives and reject polluted packages in CI.

### Upgrade Note

`0.2.0` starts with a consolidated fresh-install schema. Unpublished `0.1.x` test installations are not supported as in-place upgrades and must be removed/reset before installing this version.
