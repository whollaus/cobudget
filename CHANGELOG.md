# Changelog

All notable changes to CoBudget are documented in this file.

## [0.4.1] - 2026-09-18

### Fixed

- Create release archives in GNU tar format on macOS and Linux. Nextcloud ignores PAX long-path headers, which truncated lazy JavaScript chunk filenames during App Store installation and left payment views blank. Reject PAX archives during release verification.

## [0.4.0] - 2026-09-16

### Added

- Added Nextcloud 35 to the supported server versions while retaining Nextcloud 33 and 34.
- Added CI installation, migration and OCC smoke checks against Nextcloud 33/PHP 8.2, 34/PHP 8.2 and 35/PHP 8.3.

### Changed

- Updated the currently unused `fetchJson` helper to read CSRF tokens through `@nextcloud/auth`, including token updates in long-lived tabs, instead of accessing private or removed global aliases. Active requests already use the supported Axios integration.
- Documented version-specific PHP requirements and the Nextcloud 35 compatibility review.
- Preserve existing GitHub releases when the tag workflow runs, allowing the local release publisher to upload and publish without being reset to a draft.
- Document the optional local App Store publishing flow and the distinction between GitHub prereleases and App Store version channels.

## [0.3.9] - 2026-09-11

### Added

- Added `occ cobudget:background-jobs:check` to diagnose background-job class loading and missing queue registrations directly, including underlying exception messages. Its optional `--repair` restores missing registrations only after all CoBudget jobs load successfully, without executing jobs or resetting existing ones.

### Fixed

- Replaced deprecated controller security annotations with Nextcloud PHP attributes while preserving endpoint permissions and existing CSRF exemptions.
- Avoided unnecessary workspace backfill UPDATEs during normal dashboard reads, preventing the resulting Nextcloud "dirty table reads" debug messages when no legacy rows need assignment.
- Fixed PostgreSQL grouping errors when loading dashboard payments, payment lists, CSV exports and shared or upcoming analytics entries, while preserving deduplication for joined records.

## [0.3.8.1] - 2026-08-31

### Fixed

- Area overview balances now use the same stored exact-cent share snapshots as area details, preventing small rounding differences in owed amounts; related dashboard and analytics totals use the same calculation path.

## [0.3.8] - 2026-08-31

### Changed

- Expanded the mobile creation buttons for payments, areas and budget goals with explicit labels at the top of each page; after a deliberate scroll they smoothly collapse to the plus icon and expand again only at the top.
- Simplified mobile payment browsing in My Finances and area overviews: each now shows only its first configured page with a quiet text action aligned vertically with the floating creation button, which opens a table-only Payments view on the next unseen server-side page without summary cards or a creation button.
- Preserved all active payment filters, sorting and page size in both personal and area-specific Payments routes, including their current page during navigation and reloads; returning keeps the same filtered result context.
- Added bounded server-side pagination to both area settlement history and the payments inside each opened settlement; both provide Previous and Next controls, page indicators and keyboard navigation, while payment pages honor the configured table size and load only on demand.
- Aligned the settlement page header with other area subpages by moving its Back action beside the concise “Settlements” title.

### Fixed

- Removed the competing previous/next controls from mobile area overviews by moving page navigation into the dedicated area Payments view.
- Restored rounded corners and balanced horizontal padding for payment tables embedded in expanded settlements on mobile screens.
- Removed the duplicated mobile inset around the settlement header so its Back action aligns with other area subpages and the sticky header spans the full content width.

## [0.3.7] - 2026-08-30

### Changed

- Removed the explanatory subtitle from the analytics page header for a cleaner, more compact presentation.
- Updated the direct Nextcloud URL and Webpack build dependencies to the current major versions: `@nextcloud/router` 3.x and `@nextcloud/webpack-vue-config` 7.x.

### Fixed

- Restored full-width mobile payment pagination in My Finances and area details, with Previous and Next at the outer edges and the page count centered, while the floating creation action now lifts into a reserved lane when the page controls enter view.

## [0.3.6] - 2026-08-29

### Added

- Added a persistent, app-style mobile bottom navigation for quick access to finances, areas and analytics, including active states and device safe-area spacing.
- Kept the shared mobile page header and its primary actions visible while the page content scrolls underneath it, with an opaque lower edge and stronger theme-aware elevation appearing only after scrolling.
- Added separate personal settings for showing the Nextcloud header on desktop and mobile, applied immediately with desktop-visible and mobile-hidden defaults.

### Changed

- Updated `@nextcloud/dialogs` to the supported 7.x line and bundled its required toast styles for both application entry points.
- Renamed and simplified the Nextcloud-header setting to a clear "Show Nextcloud header" section with the two concise choices Desktop and Mobile.
- Reworked mobile payment rows into compact, day-grouped transaction lists with clearer descriptions, secondary metadata and fewer decorative chips.
- Moved the mobile creation actions for payments, areas and budget goals into larger, safe-area-aware floating buttons above the quick navigation and strengthened the active navigation indicator across light and dark themes.
- Replaced the mobile My Finances overflow menu with direct export and search icons in app-style order, and expanded the desktop search trigger into a labeled, borderless toolbar button matching the area settings action.
- Restored horizontal swiping for the mobile My Finances summaries with content-sized cards that keep every amount on one line, omit the redundant balance label in month and year cards and isolate the gesture from Nextcloud's navigation drawer.
- Replaced the wrapped analytics period buttons with a compact native dropdown on mobile while retaining the quick period switch on larger screens.
- Shortened monthly analytics chart axes to localized month names while keeping the full month and year in chart tooltips and value tables.
- Consolidated the five analytics summary tiles into consistent income, expense and balance cards with embedded averages and booking counts, plus a single-column mobile layout.

### Fixed

- Prevented the sticky mobile page header from shifting upward relative to Nextcloud's hamburger navigation button when scrolling.
- Removed the unused header-height strip below CoBudget by resizing both the outer Nextcloud content container and its nested Vue app shell when the header is hidden.
- Balanced the headerless desktop layout with the same Nextcloud container spacing on its top, right, bottom and left edges.

## [0.3.5] - 2026-08-22

### Changed

- Moved background-job normalization out of request-time app boot and into a Nextcloud repair step.

### Fixed

- App upgrades now transactionally remove duplicate CoBudget background jobs registered with legacy empty-array arguments while retaining one canonical job per task.
- Background-job normalization failures are now logged with their exception details and propagated instead of being silently ignored.
- Removed obsolete writes to Nextcloud's internal theming cache-buster that could cause an app-config type conflict during upgrades.

## [0.3.4] - 2026-08-20

### Added

- Open payment, area, settlement, budget, and analytics overviews now stay up to date when payments, areas, settlements, or budgets change elsewhere—for example, in another browser or device, by another area member, or during recurring-payment background processing. Configured installations can receive updates through `notify_push`; every client also checks for changes every 30 seconds while visible and whenever a tab resumes.

### Fixed

- Corrected shared-area payment tooltips in My Finances so already settled entries no longer appear as unsettled and use a multi-user check icon.

## [0.3.3] - 2026-08-14

### Added

- Added leading current-month and current-year summary cards with localized month names, balances, income, and expenses.

### Changed

- Removed all planned 30-day detail lines from the summary cards, including the dedicated planned card.
- Updated the current-month and current-year icons to inherit the active Nextcloud primary color.

### Fixed

- Untouched new-payment forms now refresh their automatically selected date after midnight and when returning to a long-open browser tab.

## [0.3.2] - 2026-08-05

### Changed

- Consecutive desktop payment entry now confirms the saved payment without additionally announcing that another empty form was prepared.

### Fixed

- Main app views now load correctly from custom app directories and Nextcloud webroots, including Docker and reverse-proxy installations, instead of remaining blank when lazy frontend files were requested from a hard-coded `/apps` path.

## [0.3.1] - 2026-08-03

### Added

- Added dedicated analytics periods for the previous calendar month and previous calendar year.
- Category, payment-partner, label, `#tag` and area focus rows now open a daily or monthly development chart with an expandable table of exact period amounts and booking counts.

### Fixed

- Analytics headings now render special characters in dynamic names correctly and visually distinguish the selected period with quotation marks.

## [0.3.0] - 2026-08-02

### Changed

- Replaced the payment modal with a responsive native Nextcloud sidebar. It uses mode-specific titles for creating, editing, copying and planning income or expenses, guards unsaved changes and stays ready for consecutive entry on unfiltered desktop payment pages.
- Reworked desktop payment views into an inset work surface with bounded row scrolling, sticky table headings, fixed pagination and compact responsive columns.
- Payment rows now open the shared sidebar, support keyboard activation and visibly mark the selected payment with the same strong focus treatment used by form controls.
- Simplified payment creation to one New payment action and one Save action. The main action remains available while reviewing an existing payment and is hidden only while creating a new one.
- Removed payment templates completely, including their user interface, API, setting, backup and restore handling, integrity checks and database schema.

### Fixed

- Copied payments now default to today's date instead of reusing the source payment date.
- Open area payments can now be copied from personal payment views; the canonical shared payment data and area are retained in the new payment form.

### Upgrade Note

- Upgrading to `0.3.0` permanently removes all stored payment templates and the related personal preference. Record any template details you still need before updating.

## [0.2.16] - 2026-07-27

### Added

- Added optional internal master data for personal, area-specific and global payment partners: free-text numbers, person and company data, addresses, contact details, bank details and notes.
- Added a disabled-by-default personal setting for advanced category and payment-partner master-data editing.
- Personal exports, full backups, account transfers and area-to-personal conversion workflows now preserve the additional payment-partner master data.

### Changed

- Creating a payment partner remains a lightweight name-only action; the optional master data appears only while editing an existing partner.
- Payment-partner edit dialogs now use the established CoBudget form styling, place number and display name in the first row and group the remaining fields into collapsible sections.
- In personal settings, the new feature switch controls advanced category-number and payment-partner-detail editing; area and administrator editing remains available independently of that personal preference.
- Saved payment-partner numbers now appear above the display name in personal, area and administrator settings as well as in payment selectors, where they can also be searched directly.
- Collapsible payment-partner sections containing saved data now open initially, while their disclosure arrows remain aligned with the section titles.

### Fixed

- Payment-partner email addresses are now checked for a valid format in both the edit form and the backend before they are saved.

## [0.2.15] - 2026-07-26

### Changed

- Replaced the full-width income and expense toggle in payment and template forms with a compact selector inside the amount control, while keeping the currency separate and preserving the existing defaults, reset behavior and red/green states.
- Gave the payment reference or note field the full form width and moved payment labels to their own full-width row below it.

### Fixed

- Refined the combined amount control so its segments no longer show isolated hover or focus treatments, its label text is not clipped and the complete control aligns with the date field.

## [0.2.14] - 2026-07-26

### Added

- Area settings now list global categories alongside area-specific categories and let the area owner hide or show global categories for the entire area.

### Changed

- Simplified category hierarchy labels throughout settings, payment choices, budget goals and analytics, and changed grouped analytics totals to “Total - Category”.
- Selected payment categories now use the same compact name-only display for main categories and subcategories, while the open category list retains its visual hierarchy without bold main-category labels.
- Reordered administrator settings so category and payment-partner management appears before data quality and full backups.

### Fixed

- Payment editing and category selection now retain and display the exact selected subcategory by its stable category ID.
- Analytics category labels now decode HTML entities correctly.

## [0.2.13] - 2026-07-26

### Added

- Added one optional hierarchy level for personal, area-specific and global categories: every category can remain a main category or be assigned to another main category of the same type.
- Category edit dialogs now offer only valid main categories as parents and prevent deeper nesting.
- Analytics now show direct bookings on a main category, its individual subcategories and a final total for the complete category group.

### Changed

- Category lists, payment category choices and budget-goal category choices now make main categories and subcategories visually distinct.
- Personal exports, full backups, integrity checks, area projections and account-transfer workflows now preserve and validate category hierarchy relationships.

## [0.2.12] - 2026-07-26

### Added

- Added optional free-text numbers or codes to personal, area-specific and global categories.
- Category numbers can now be used to find categories while entering payments and to find matching payments in the payment list.

### Changed

- Category edit dialogs now place the number before the name, while settings lists, payment category dropdown options and selected values display the number in a compact first line above the category name.

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
