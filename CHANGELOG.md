# Changelog

All notable changes to CoBudget will be documented in this file.

This project follows semantic versioning as far as practical during the alpha phase.

## [Unreleased]

- Nothing yet.

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
