# Changelog

All notable changes to CoBudget will be documented in this file.

This project follows semantic versioning as far as practical during the alpha phase.

## [Unreleased]

## 0.1.5 - 2026-07-10

### Added

- Added immutable per-payment share snapshots with exact percentage and cent allocations for every member of a shared area.

### Changed

- Shared-area balances, settlements, analytics, budgets, notifications, personal exports and reset transfers now use the allocation stored with each payment instead of later area defaults.
- New recurring payments receive a fresh allocation snapshot based on the area settings that apply when the payment is generated.

### Fixed

- Hardened the Nextcloud notification provider so obsolete or incomplete CoBudget notifications no longer break the global notifications endpoint.
- Prevented later member or percentage changes in an area from silently changing historical payment shares.
- Prevented members added later from inheriting a calculated share of older payments.
- Preserved exact personal cents during personal export/import, including deterministic remainder-cent handling.
- Bound new payments to the canonical workspace of their selected shared area and prevented existing payments from changing workspace during edits.
- Preserved other members' settled shares as personal payments when an area creator resets all personal CoBudget data.

### Security

- Pinned DOMPurify to `3.4.11` to include the fix for GHSA-cmwh-pvxp-8882.
- Required the Nextcloud Files owner of system-wide full backups to be an administrator across settings, cron, UI, restore, and OCC flows.
- Bounded backup restore archives by compressed size, uncompressed size, individual JSON size and compression ratio while streaming archive data instead of loading the complete ZIP into PHP memory.
- Neutralized spreadsheet formula prefixes in user-controlled CSV text fields while preserving numeric amount columns for calculations.
- Restricted direct receipt deletion to the Nextcloud Files owner and made automatic file cleanup honor each receipt owner's setting.
- Blocked direct member additions when Nextcloud user enumeration is disabled, preventing guessed user IDs from bypassing the search policy.
- Made payment deletion, receipt metadata changes, and budget snapshot mutations transactional; physical receipt cleanup now runs only after a successful database commit.
- Bounded payment list pagination and added native Nextcloud user rate limits to payment search, CSV export, analytics, user search and personal-export inspection.
- Streamed filtered payment aggregates instead of materializing the complete result set for every paginated dashboard response.
- Enforced unique area memberships at the database layer and deterministically removed existing duplicate member rows during upgrade.

## 0.1.3 - 2026-07-09

### Changed

- Added localized English and German app descriptions to the Nextcloud app metadata.
- Changed the Nextcloud app category metadata to `office`.

## 0.1.2 - 2026-07-09

### Fixed

- Fixed Nextcloud 34 admin settings registration so CoBudget no longer blocks the global admin settings area.
- Improved CoBudget navigation styling for the updated Nextcloud 34 sidebar, including active, hover and mobile states.
- Fixed CoBudget admin navigation icon contrast in header and sidebar contexts.

### Changed

- Updated release metadata and CI checks for the Nextcloud 33/34 target range.
- Refined release packaging so GitHub releases continue to ship the installable `cobudget.tar.gz` archive only.

## 0.1.1 - 2026-07-09

### Added

- Initial public alpha baseline for CoBudget.
- Personal payments, shared areas, configurable splits and settlements.
- Workspaces for isolated data pools.
- Categories, payment partners, labels and free-form `#tags` in payment descriptions.
- Receipts stored in Nextcloud Files.
- Payment templates, recurring payments and reminders.
- Budget goals with flexible criteria, forecasts and analytics.
- Personal exports and admin full backups with restore workflows.
- Integrity checks, repair helpers and admin tooling.
- Light, dark and system theme modes.
- GitHub release archive preparation for later Nextcloud App Store submission.

### Security

- Added workspace/user isolation and shared-area permission checks.
- Restricted shared-area administration and settlement to the area creator.
- Added backup/restore safeguards, upload validation and safer error responses.
- Added backend/security tests for critical permission, backup, attachment and data-integrity paths.

### Notes

- Early alpha: features, database structures and workflows may still change.
- This release squashes the internal pre-release migration history into a fresh first-install schema.
- Existing internal test installations should be reset/reinstalled before using this public baseline.
- Supported Nextcloud versions for the first App Store target are 33 and 34.
