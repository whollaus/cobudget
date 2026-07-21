# Changelog

All notable changes to CoBudget are documented in this file.

## [0.2.11] - 2026-07-21

### Fixed

- Corrected the payer tooltip for shared-area payments in personal finance views so it shows the member who actually paid instead of the owner of the personal allocation.

## [0.2.10] - 2026-07-19

### Fixed

- Switched App Store screenshots to direct, revisioned GitHub raw URLs so Nextcloud's image proxy cannot keep serving stale or empty cached images after a screenshot update.
- Added dedicated `small-thumbnail` images for the integrated Nextcloud app list while retaining full-size screenshots for the detailed App Store view and GitHub documentation.

## [0.2.9] - 2026-07-18

### Changed

- Reworked the English and German App Store descriptions into clearer Markdown sections with a shorter early-alpha notice.
- Replaced the legacy App Store thumbnails with dedicated, proxy-friendly preview assets while retaining the full-size screenshots for project documentation.
- Split the app icon treatment into a dark App Store icon and a dedicated navigation icon for reliable contrast across Nextcloud surfaces.

### Fixed

- Fixed low-contrast CoBudget icons in the top navigation and selected administrator navigation entries.

## [0.2.8] - 2026-07-18

### Changed

- Renamed the optional payment text field to "Payment reference or note" and removed its dated example placeholder for a calmer, future-proof payment form.
- Refined the English and German App Store descriptions to better explain personal budgeting, shared expenses, flexible areas and the early alpha status.
- Added optimized App Store thumbnails while retaining the full-size screenshots for the detailed app listing.
- Updated the public project documentation to reflect the official App Store availability, supported Nextcloud versions, signed release workflow and current alpha support policy.

## [0.2.7] - 2026-07-18

### Added

- Added conservative category suggestions based on repeated payment-partner choices by the current user in the same workspace, area and payment type.

### Changed

- Streamlined payment and template entry with compact date and amount fields, payment-partner-first selection and clearer placement of descriptions and labels.
- Added direct, color-aware area choices for short area lists while retaining the dropdown for larger lists, and standardized area colors across payment and area views.
- Improved shared-area allocation wording, optional-detail status hints and focus styling throughout the payment form.
- Kept desktop amount autofocus while preventing the mobile keyboard from opening automatically with a new payment.

### Fixed

- Empty areas are now permanently deleted instead of appearing under archived areas; areas with payments, settlement history or budget references remain protected.
- Fixed amount input validation and leading-minus handling so valid calculator expressions no longer produce browser console warnings.

## [0.2.6] - 2026-07-17

### Changed

- Made area assignment directly visible in payment and template forms, placed it below the description, and simplified area option labels.
- Clarified shared-area allocation wording so the default split and full allocation to one member are easier to understand.
- Grouped receipts with recurrence and reminders as optional details, while giving receipt uploads their own clearly structured panel.

## [0.2.5] - 2026-07-16

### Changed

- Reworked category and payment-partner selection so routine choices open a scrollable list without immediately showing the mobile keyboard. New values can still be added explicitly and are created when the payment or template is saved.
- Gave the purpose or description field the full form width and moved labels to a separate row for a calmer payment and template entry layout.

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
