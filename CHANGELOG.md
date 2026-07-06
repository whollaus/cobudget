# Changelog

All notable changes to CoBudget will be documented in this file.

This project follows semantic versioning as far as practical during the alpha phase.

## [0.3.5] - 2026-07-06

### Added

- Added GitHub-ready project documentation, including README, feature overview, security notes, screenshots and alpha warnings.
- Added personal and shared payment tracking with workspaces, shared areas, configurable area splits and settlement history.
- Added categories, payment partners, labels and free-form `#tags` in payment descriptions.
- Added receipts stored in Nextcloud Files with configurable receipt handling.
- Added payment templates, recurring payments and reminders.
- Added budget goals with flexible criteria, forecasts and analytics signals.
- Added analytics for trends, forecasts, focus tables, labels, shared areas, budget signals, missing receipts and print output.
- Added user-scoped and full backup/restore workflows, including OCC commands, safety backups and user mapping for server transfers.
- Added integrity checks and repair helpers for data-quality issues.
- Added light, dark and system theme modes.

### Changed

- Renamed the app from the earlier CoFinance prototype to CoBudget and reset technical identifiers to `cobudget`.
- Reworked payment terminology, shared areas, payment partners and labels for a more household-focused product language.
- Reworked large parts of the UI for reusable headers, buttons, modals, tables and shared design tokens.
- Improved mobile layout, touch targets, table cards and modal behavior.
- Kept release ZIP packaging limited to runtime app files; repository-only screenshots and development files are excluded.

### Fixed

- Fixed multiple dark-mode readability issues in forms, tables, modals, settings, analytics and empty states.
- Fixed workspace, shared-area and backup/restore edge cases found during multi-user testing.
- Fixed duplicate category/payment-partner handling and added safeguards for future duplicates.
- Fixed table grouping, pagination styling and month/year summary calculations.
- Fixed recurring entry behavior so active recurrence moves to the newest generated entry.

### Security

- Hardened shared-area permissions so only the creator can manage members and settle an area.
- Hardened workspace deletion, backup restore scope, backup import column handling and error responses.
- Added and extended backend tests for permissions, backup/restore, workspaces, attachments, budgets and settlements.

### Notes

- CoBudget is still in an early alpha test phase.
- Features, database structures and workflows may still change.
- Updates, test resets or manual experiments may still require direct database corrections during alpha.

## [0.1.0] - Internal Alpha

### Added

- Initial CoBudget reset from the earlier prototype.
- Payments, workspaces, shared areas, labels, budgets, analytics, receipts, backups and restore workflows.
