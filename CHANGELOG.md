# Changelog

All notable changes to CoBudget are documented in this file.

## [0.8.8] - 2026-11-13

- Fence personal/full backup and restore against concurrent CoBudget requests, jobs, user deletion and data-management commands using Nextcloud's shared/exclusive locks. Existing operations prevent maintenance startup; new requests receive HTTP 503 during maintenance, and jobs defer their work.
- Keep the exclusive lock through validation, safety backup, replacement and cleanup; release it on normal failure. Recheck workspace ownership after request admission and preserve native lock-cache correctness across repeated cron operations.
- Add multi-process concurrency regression tests using the real Nextcloud locking provider, alongside backup roundtrip/rollback tests.
- Handle Nextcloud Files adapters that close streamed input; avoid falsely failing an otherwise stored backup. Preserve deleted-goal snapshots in integrity checks instead of offering their deletion as a repair.
- Extend released-0.8.5 upgrade fixtures with shared splits, settlements, deleted participants, recurrence, user mapping and actual Nextcloud Files archives. Add a disposable recovery runner with deterministic SIGKILL during migration and restore, snapshot recovery, lock expiry and generated-ID checks.
- Configure and verify six Nextcloud 34/35 recovery CI combinations with SQLite, MariaDB 10.11 and PostgreSQL 16, plus native database and Redis locks. Retain test logs without database/configuration artifacts.
- Clear Nextcloud's user-preference cache after transactional replacement and rollback, preventing stale settings during account mapping or repeated restores. Keep PostgreSQL sequences monotonic across failed restores; sequence errors now fail the restore instead of being ignored.
- Confirm the backup boundary: CoBudget archives preserve receipt paths; receipt files are protected and restored with the administrator's existing Nextcloud Files backup solution.

## [0.8.7] - 2026-11-13

- Fix full restore rejecting retained history of deleted budget goals. Preserve detached budget history in personal exports/imports too, without reconnecting it to future goal IDs.
- Check ZIP writes/finalization and stored backup size; stream archives into Files and clean up partial files before retention pruning. Reject missing manifest-listed tables, missing settings and invalid table inventories.
- Add a real ZIP/database backup roundtrip suite with rollback, legacy deleted-goal history and corrupt-archive/storage-failure cases; include it in compatibility CI.
- Fix payment CSV yes/no values always appearing in German; export them in the selected language, with cents and formula protection covered by regression tests.
- Extend translation coverage checks across frontend literals and all PHP services/controllers, including multiline calls. Add missing due-date and receipt-permission translations in German, French and Spanish.
- Refresh household-focused GitHub and App Store descriptions in English, German, French and Spanish, with README language links, backup limits and an explanation of CoBudget’s payment/share-based structure and lack of bank-account synchronization.
- Record the 1.0 readiness review and open validation gates. Plan Nextcloud 34 as the minimum for 1.0; this beta retains Nextcloud 33–35 compatibility.

## [0.8.6] - 2026-11-12

- Shorten notification settings copy to essential calendar behavior and a contextual missing-email hint; move channel and mail setup details to the operating guide.

- Add independent, opt-in email notifications for personal reminders, due planned/recurring payments, new shared-area payments and settlements. Use the current Nextcloud account email address and the administrator-configured mail transport.
- Retry failed reminder emails independently of Push/Talk and track planned-payment email delivery per recipient. Preserve delivery state and preferences in backups/reset; migrate existing delivered reminders without replaying them.
- Show email channel controls in Settings, including a hint when the account has no valid email address. Email contains personal reminder text or a brief event summary and a link, without payment descriptions or receipts.

## [0.8.5] - 2026-11-11

This release includes the changes developed in 0.8.0–0.8.4 since the 0.7.0 release.
CoBudget remains beta; 1.0.0 is intended to be the first stable product release.

- Refresh desktop and mobile navigation, payment tables, editors, filters, reports and settings with consistent Nextcloud controls, theme support and keyboard shortcuts.
- Improve planned and recurring payments, searchable category/payment-partner selection, and payment filtering and summaries.
- Add a personal reminder organizer with standalone and payment-linked reminders, completion, automatic archival, postponement, action labels and an upcoming-reminder summary on Payments.
- Add independent Nextcloud and Talk notification preferences for reminders and due planned or recurring payments, with per-recipient delivery tracking and retries.
- Add optional calendar synchronization, including bidirectional private-calendar edits, explicit conflict resolution and safe handling of deleted events; shared calendars remain export-only.
- Include reminder data and notification preferences in backup/reset workflows and apply the required additive database migrations. Calendar connections remain server-local and require setup after financial restore.
- Fix namespaced account locking for calendar and Talk synchronization and expand regression coverage and contributor documentation.

## [0.8.4] - 2026-11-05

- Show a leading reminder summary on Payments when open reminders are overdue or due within the next ten calendar days. Display the total and two earliest reminders, linking to the reminder overview on desktop and mobile.

- Add a personal Reminders switch under Settings → Features. Hide reminder navigation and payment controls without changing existing reminders, notifications or calendar synchronization.

- Synchronize reminder titles, descriptions and times bidirectionally with private calendars. Merge independent edits and offer explicit choices for conflicting fields, including existing calendar changes during upgrade.
- Archive the personal reminder when its calendar event is deleted, retaining its contents and preventing automatic event recreation. Calendar outages, revoked access and deleted calendars do not archive reminders.
- Keep shared calendars export-only, preserve personal completion/archive state, and expose non-default calendar times in the reminder editor. Add a synchronization-state migration.

## [0.8.3] - 2026-11-05

- Automatically archive completed reminders 14 days after completion when automatic archival is enabled; preserve completion and existing archive dates.

- Match reminder deletion confirmations to Payments with native dialog spacing and a destructive action button.

- Match mobile reminder row backgrounds to Payments and give the mobile editor a New reminder/Edit reminder header, with the date and remaining fields below it.

- Match mobile reminders to Payments with date-group headings, title/description rows, a leading completion checkbox and right-aligned status. Hide the mobile three-dot menu; editing and deletion remain in the sidebar.

- Compact the mobile reminder list: title-first rows, one-line description previews, wrapping date/status metadata and consistently aligned checkbox/menu controls.

- Add Remind me later to open reminders: Tomorrow, In one week (09:00 local time), or Choose date in the editor. Preserve personal metadata, protect unsaved drafts and propagate the new time to linked payments and calendars.

- Show a calendar warning and tooltip beside reminders awaiting calendar synchronization or cleanup, with a direct retry action and accurate per-entry status. Preserve calendar preferences, event identities and unsaved reminder drafts.

- Add Today and N days ago beneath open reminder dates and subtly highlight overdue open rows, preserving completed/archived colors and the editing selection.

- Highlight the reminder being edited like the selected payment row. Preserve personal automatic-archive choices when saving linked payments; enable automatic archival only by default for new reminders.

- Group expanded payment recurrence and reminder fields in matching rounded, borderless boxes using light/dark theme colors, with even inner padding and no extra gap below their disclosure rows; place Show reminder below the box with the standard field gap.

- Open payment recurrence/reminder sections collapsed by default. Remove payment reminders by clearing their date and saving, without a separate removal button.

- Indicate configured payment recurrences and reminders with the shared blue active dot, including when their details are collapsed.

- Match Repeat to the Reminder disclosure button. Opening/collapsing preserves recurrence settings; the interval selector offers Once to disable repeating.

- Unify payment reminder fields with the reminder editor: date, title, description and action labels. A disclosure arrow replaces the enable switch; collapsing preserves the reminder. Save personal details atomically with the payment, preserving the automatic-archive choice (enabled for new reminders) with stale-draft protection.

- Order the reminder editor fields as Remind me on, Title and Description, with the date beside the close action.

- Preserve the literal year during keyboard entry in payment, reminder and recurrence-end date fields instead of rewriting early digits into the 1900s; align Show reminder spacing with the form grid.

- Keep the reminder date-range filter in the Filter menu only and use multiple switches for reminder action labels, defaulting to All labels.

- Match reminder status indicators to Files-style tags with a single thin outline, state icons, soft blue/green/gray backgrounds and corresponding text/border colors while preserving dated tooltips.

- Display reminder action labels as outlined Nextcloud Files-style tags with matching icons, separate from inherited payment labels.

- Clarify navigation between linked payments and reminders with Show payment/Show reminder arrow buttons and remove the reminder form’s explanatory payment text.

- Show inherited payment labels as read-only icons only in the reminder table. The form offers only Cancel contract, Renew, Review and Pay using payment-style icon/text checkbox buttons.

- Hide reminder month headings when the visible table page contains only one calendar month.

- Clarify reminder payment-link filters and allow combining independent switches for no payment, payments without an area and payments from an area.

- Add a payment-style reminder row menu for editing and deleting, preserving unsaved-draft protection and deletion confirmation on desktop and mobile.

- Add payment-style month/year headings to reminders while preserving status priority and pagination.

- Use payment-style independent status switches for reminders; show open and completed items by default and hide archived items, including completed archived reminders.

- Give reminder statuses theme-aware blue, green and neutral badges; combined completion/archival keeps both badges and dated tooltips.

- Use a fixed reminder order, centered completion column and a separate Status column with dated tooltips. Preserve completion and archival independently, including both states at once.

- Keep completed reminders visible in the default overview and remove the automatic-archive helper text.

- Use compact reminder status switches: Archive automatically, Completed and Reminder archived. Show completion/archive only for existing items and save status changes with the form.

- Simplify reminder creation by removing payment and deadline selectors; retain existing deadlines and provide a fixed, workspace-aware link for payment reminders.

- Open a new reminder automatically on desktop module entry, preserving direct links; keep the Payments New action visible while editing or creating.

- Align the reminder editor with the payment sidebar: title-first header, native fields, compact spacing and shared footer actions.

- Align reminder table columns, empty states and spacing with Payments; use date-only editing at 09:00 for new or rescheduled reminders.
- Match the Reminders title navigation and responsive filter toolbar to Payments, including active filter chips.
- Add a personal Reminders overview and sidebar for standalone and payment-linked reminders, descriptions, deadlines and action labels.
- Add personal completion, archive and keep-open states; ordinary reminders archive after 14 days.
- Preserve existing calendar identities, synchronize descriptions and remove alarms on completion; no Nextcloud Tasks integration.
- Include personal reminder data in backups/reset, preserve payment history for linked title/time edits, and add organizer-aware keyboard shortcuts.

## [0.8.2] - 2026-11-04

- Fix calendar and Talk synchronization on database-backed locking: hash the complete namespaced account key to respect Nextcloud’s 64-character lock-key limit.
- Log safe diagnostic context for calendar settings/retry failures, including operation and exception source locations, without payment contents or SQL values.

- Add optional calendar synchronization for reminders, with a writable target calendar per account and an alert at the reminder's exact timestamp.
- Show the Calendar channel only when a writable event calendar exists. Keep Calendar, Nextcloud and Talk choices independent.
- Synchronize reminder text/time changes and removals after payment commits, with background retries, stable event identities and per-member preferences for shared areas.
- Keep calendar connection settings and operational event mappings local to the server; require setup again after financial restore and clean up managed events after disabling/resetting.

## [0.8.1] - 2026-11-04

- Add separate Nextcloud and Talk preferences for payment reminders, available without areas. Reuse the private CoBudget chat, preserve each shared-area member’s channel choices, and include the settings in backups and reset.

- Notify opted-in users when a future payment becomes due or a recurring payment is generated, independently through Nextcloud and/or the private CoBudget Talk chat. Both new channels default to off.
- Store activation delivery progress per recipient and channel, retry failed channels, and exclude shared personal projections and historical payments during upgrade.
- Replace separate notification switches with a compact event/channel table, accessible on desktop and mobile. Hide the Talk column when Talk is unavailable for the account. Keep reminder and shared-area preferences independent.
- Add the activation delivery schema and preserve notification preferences/status in backups and reset workflows.

## [0.8.0] - 2026-11-04

This beta release brings together the extensive interface and workflow rebuild
since 0.7.0. It replaces the unpublished 0.7.1 development notes with a summary
of the resulting behavior. Version 0.8.0 is a stabilization milestone toward
1.0.0, which is intended to be the first stable CoBudget release.

### Navigation and interface

- Rebuild the desktop and mobile experience around native Nextcloud controls and the Files app's interaction patterns: consistent headings, fields, buttons, menus, tables, avatars, icons, pagination and feedback.
- Simplify payment navigation to Payments, Income, Expenses and Planned. Provide the same title navigation across payments, areas, budget goals, reports and settings, with contextual area/goal lists.
- Move payment creation beside the desktop page title while retaining mobile creation actions. Add clear empty states and hide redundant area controls when no active areas exist.
- Unify responsive filter toolbars across payments, area payments and reports. Keep frequently used filters visible when space permits and all applicable choices available in the compact menu.
- Show removable active-filter chips with icons above the affected table or report. Mark active filters with accent dots and use the current year as the default period caption.
- Add Ctrl/Command+S to save the open payment, Ctrl/Command+N or Alt+N to create a payment, and Ctrl/Command+P to invoke CoBudget printing in Reports. List the shortcuts with native keycaps before Reset all in Settings; preserve validation and unsaved-change confirmations.
- Make CoBudget's independent light/dark appearance apply consistently to sidebars, menus, dialogs, Files folder pickers, notifications, tooltips, form controls and selected text, even when Nextcloud uses another theme.
- Preserve keyboard focus and scroll position after dialogs and refreshes. Improve native sidebar/dialog transitions, delayed hover hints and reduced-motion behavior.

### Payments and planned payments

- Rework payment tables with compact Files-style headers and separators, adaptive columns, period totals, payer avatars and accessible label indicators. Use an area's color as the left row marker; unassigned payments continue the row background.
- Improve mobile payment rows with a clearer category/partner/description hierarchy and icons directly before amounts. Remove the mobile three-dot menu; keep editing through the row and history in the editor or a direct action for read-only payments.
- Rebuild the payment sidebar with consistent native fields, amount autofocus, compact mobile Save/Close actions, visible validation errors and draft-preserving confirmations. Changing list filters, sorting or pagination no longer resets the editor.
- Keep receipts, recurrence and reminders directly discoverable as compact rows. Show details when enabled, collapse saved receipts behind their count, and retain pending files and failed drafts for retry.
- Add searchable category/payment-partner choices with hierarchy and optional numbers. Allow new names to be entered from the lookup and create them only when the payment is saved.
- Support combined category, payment-partner, area, label and tag filters, inclusive amount bounds and a distinct Without area scope. Keep rows, totals, drilldowns and CSV exports aligned with the selected filters.
- Show the actual future first payment in Planned before the next recurrence. Preserve its original date when editing, and use the correct first date for filtering, sorting and summaries.
- Generate recurring payments from 06:00 server time on the next cron run, including series previously scheduled for 09:00. Keep calendar progression across time changes, include the selected final day, and catch up missed runs without duplicating occurrences in repeated runs.
- Correct local date parsing and first-payment previews. Deleting a future first payment also removes its recurrence; stopping the planned continuation of an already booked payment retains the original payment.

### Reports

- Start Reports with Development on each visit instead of restoring Forecast from browser storage; retain the selected tab while changing filters or periods within Reports.

- Bring report filters in line with payment filters, including category hierarchy, income/expense groups, payment partners, labels/tags, free-text search and mixed area/Without area selections.
- Allow multiple years and non-adjacent months, with month choices in two columns, alongside preset, all-time and custom date ranges. Preserve selections in bookmarks, detail pages, payment links and exports; exclude unselected gaps from calculations.
- Combine equally named categories and payment partners in reports, with expandable origin-area subtotals and retained category hierarchy, without merging stored records.
- Align period, breakdown, forecast, comparison and detail tables with payment-table typography and spacing. Add synchronized horizontal dragging and scroll controls while retaining pinned columns and touch scrolling.
- Reuse the payment table for the largest saved payments, including sharing indicators, editing permissions and mobile behavior. Keep personal shares and full shared-area amounts distinct throughout totals, comparisons, forecasts and exports.

### Categories, payment partners and settings

- Replace separate category/payment-partner lists with availability tables across Without area and individual areas. Keep income/expenses and parent/child categories clearly separated.
- Use explicit Save/Cancel dialogs for shared identity and availability changes. Save multi-area changes atomically, preserve hidden identities and enforce global/area edit permissions.
- Move area-specific numbers and payment-partner contact/bank details into an Advanced settings dialog with isolated drafts, separate saving and unsaved-change markers. Explain locked fields and blocked deletion actions in context.
- Persist area-specific payment-partner visibility and preserve it through backup validation and restore remapping.
- Save general preferences, folder choices and backup schedules automatically with validation, serialized writes and inline retry. Retain explicit saving where changes belong to a dialog or a new record.
- Unify workspace management, personal exports and administrator backup controls with native fields, Files folder selection and guarded confirmations. Keep exact RESET/RESTORE safeguards and existing access restrictions.

### Areas, settlements and budget goals

- Introduce a dedicated area-creation page with draft members and share previews. Refine area lists, settings, member selection, split editing, archival controls and empty states.
- Show member balances and suggested repayments in consistent responsive tables with native avatars. Present settlement history in an overview with detail dialogs, saved currencies and immutable totals.
- Show the complete open shared-payment count and total before settlement, independently of the current table filters and pagination.
- Clarify shared-payment editing: open shared sources keep their area and editable allocation controls; released personal shares keep their original area, hide payer/split controls and explain that edits affect only that participant.
- Make shared totals visible without opening the editor: show a group icon and a tooltip with the area, original total, personal share and unsettled status. Keep deletion rights separate from edit rights.
- Rework budget-goal overview, creation and editing with native controls, compact mobile criteria, validation and retry. Add Without area as a distinct criterion and preserve it through calculations, snapshots, exports and restore.

### Reliability and upgrade notes

- Shorten the README, refresh the feature overview and security reporting policy, move operational/development/report references into focused documents, and remove the obsolete sidebar design audit. Keep `RELEASING.md` focused on the release checklist and portable signing requirements; document the maintainer’s publication workflow in the central workspace guide. Consolidate dated verification reports into a historical record, refresh the test guide and replace duplicated packaging instructions with links to their maintained sources.

- Consolidate the design-system guide by topic, remove superseded interface rules and duplicated implementation notes, and link technical and verification details to their maintained sources.

- Clarify the single-maintainer contribution model: prioritize bug reports, feedback and testing, and require prior agreement for pull requests.

- Consolidate coding-assistant instructions in `AGENTS.md` and move technical invariants to `docs/architecture.md`, with updated contributor and workspace references.

- Guard pending operations against repeated submissions, preserve drafts after failed requests, and ignore stale responses after navigation or dialog closure.
- Expand frontend interaction, accessibility and backend regression coverage, including rollback-only integration tests on Nextcloud 34 and 35 for shared-payment lifecycles, recurring payments and exact-cent allocations.
- This update adds the area payment-partner visibility field through migration `Version000008Date20261027000000`. Let Nextcloud complete the app upgrade; keep a backup before updating from 0.7.0.
- CoBudget remains beta. The 1.0.0 milestone depends on upgrade/restore validation, shared-payment and recurrence regression testing, supported-version checks and resolution of release-blocking issues; this release does not claim that work is complete.

## [0.7.0] - 2026-10-26

Statistics has been completely rebuilt as **Reports**, bringing the former
statistics and report views together in one module.

### Added

- Explore income, expenses and balances through interactive development charts, period totals, averages, comparisons, period-end forecasts and the largest saved payments. Compare areas using personal shares or full amounts.
- Choose preset or custom date ranges independently of daily, weekly, monthly or quarterly grouping. Apply category, payment-partner, area, label and tag filters, with optional future payments.
- Open shareable detail pages and drill down through categories, payment partners, areas, labels and tags. Keep the selected dates, filters and financial perspective while inspecting charts, comparisons and matching payments, including editing and history.
- Print reports and detail pages with charts and payment tables. Export whole reports or individual sections as CSV, save them in Nextcloud Files or copy them to the clipboard.

### Changed

- Unify forms, buttons, dialogs, menus, tabs, cards, tables, typography, icons and status indicators across modules. Bring selection, hover and focus states closer to Nextcloud, with consistent light/dark themes, keyboard access and mobile layouts, while preserving financial colors and intentional CoBudget variants.
- Improve payment entry with clearer label and area selection, consistent amount-field feedback and automatic focus/scroll when expanding receipts, recurrence and reminders.
- Use a consistent natural alphabetical order for areas throughout the app. Preserve existing report and Statistics links and remember report views and section preferences.

### Fixed

- Keep report totals, averages, comparisons, recurring projections and forecasts consistent across dates, filters and personal/shared perspectives. Refresh results reliably after selection changes and avoid counting shared payments or overlapping labels and tags twice.

## [0.6.1] - 2026-10-22

### Added

- Add a collapsible Development chart below the annual-overview controls. Compare income, expenses and balance, or expenses alone when income management is disabled, using the report's exact-cent totals, filters, recurring forecasts and cutoff.
- Start charts in Running total mode, with Individual amounts as an alternative. Toggle curves through an accessible legend that rescales to visible values, keeps at least one curve visible and retains the selection across report controls.
- Inspect every monthly, quarterly, weekly and daily value with mouse, touch or keyboard. Adapt centered axis labels to the available width, including space at both edges, without dropping financial data.

### Changed

- Keep Annual overview as the page title and offer Months, Quarters, Weeks and Days as its breakdowns. Order the controls by breakdown, year and month. Combine the former monthly view with Days, start newly selected daily views in January and use All days for the whole year. Preserve explicit month choices and old monthly report links, including filters and return navigation.
- Match the chart heading's typography, alignment and collapse behavior to the report sections. Use a borderless layout and the CoCalculator income, expense and balance colors.

### Fixed

- Keep category and payment-partner menus within the visible payment sidebar on short screens and open them upward when needed. Show a normal-weight creation action after the existing entries in the same scrollable list, and keep the creation input and confirmation in view while typing.

## [0.6.0] - 2026-10-21

### Added

- Add a dedicated annual overview for My finances and individual areas, with exact-cent income, expense and balance totals. Switch between yearly month columns, quarterly and ISO-week columns, the days of a selected month, or every day of a year. The daily view can also be limited to one month; ISO week-year totals follow week boundaries and can differ from calendar-year totals.
- Group reports by categories with expandable subcategories, payment partners, or enabled labels and description tags. Payments can appear in several label or tag rows, while totals count each qualifying payment once. Unassigned categories and payment partners remain included.
- Filter reports by category, payment partner, amount range, label and description tag before grouping. Values within a filter match any selection; different filters apply together. Filter choices follow the selected financial view and period, and report links preserve the selected context.
- Open amounts in detail dialogs with payment counts, shares, averages and available comparisons with the previous period and the same period last year. Ongoing periods compare elapsed dates; differences use exact cents and trend arrows appear only beyond ±5%. Open the matching saved payments from the dialog, or use row labels for matching payments without a date restriction.
- Include upcoming recurring occurrences in future-payment reports before they are saved. My finances uses personal and solo-area amounts plus the stored personal shares of shared series; area reports retain full shared amounts.
- Print reports in an isolated A4 landscape layout or save them as PDF through the browser. Wide reports continue in blocks of at most 12 period columns with repeated labels and totals; whole-year daily reports print month by month. Printing follows filters, sorting, expanded categories and open sections.
- Export reports as CSV, either as a download or into a selected Nextcloud Files folder. Exports retain the period, grouping, payment filters and recurring forecast, use semicolons and decimal commas, and preserve existing files by adding a numbered filename suffix.
- Add optional personal Talk notifications for new payments created by other shared-area members, independently of Nextcloud bell notifications. Opting in creates a private, read-only CoBudget conversation. Delivery checks the recipient, privacy and bot binding; descriptions and receipts are omitted, and changed or deleted conversations require reconnection.

### Changed

- Combine Annual overview and Statistics under mobile Analytics, starting with Annual overview and remembering the last report per account in this browser. Keep separate desktop entries and place Budget goals directly after Analytics.
- Move area and shared-area switches into Settings → Features and give notification preferences their own section.
- Give overview tables compact rows, fixed-width amount columns, pinned labels and totals, title/amount sorting and collapsible income, expense and total sections. Respect disabled income/area features and limit year choices to years with visible saved payments.
- Align overview terminology and translations across English, German, French and Spanish, including consistent payment-partner names and Main category labels.
- Show My finances and up to five areas in a compact two-column payment picker; use a dropdown for six or more areas.
- Consolidate the changelog and feature documentation around the implemented report behavior and document the limits of recurring forecasts and exports.

### Fixed

- Include the stored exact-cent personal share of upcoming shared-area recurrences in My finances. Apply amount filters to that share, resolve tags in the personal workspace and avoid counting saved occurrences again. Forecasts leave payments, allocation snapshots and rounding balances unchanged.
- Preserve annual-overview filters when opening matching payments through the app router and when returning to the report.
- Hide unnecessary pagination controls on single-page personal and area payment lists, and improve empty overview messages and detail-dialog amount formatting.

## [0.5.1] - 2026-10-19

### Added

- Add the Insurance payment label with an optional sidebar filter, a user settings toggle, payment summaries and filtering. Preserve it in recurring payments, shared personal copies, CSV exports and backups. An additive migration initializes existing payments without the new label.

## [0.5.0] - 2026-10-01

### Added

- Complete French translation of interface text, notifications, analytics, administration and server messages, including French plural forms.
- Complete Spanish translation of interface text, notifications, analytics, administration and server messages, including Spanish plural forms and calendar headings.

### Changed

- Move CoBudget to the beta phase with the 0.5 release line and update App Store descriptions, project documentation, security policy and release guidance accordingly.

### Fixed

- Localize analytics periods, forecasts, fallback names, budget history, workspace validation and export/administration errors instead of returning German text to English users.
- Use the configured Nextcloud locale for numeric dates, times and amounts; calendar headings and chart month labels follow the interface language, avoiding mixed labels such as "Total Oktober" in English or French.
- Correct inconsistent English income labels and forecast wording while retaining German translations.

## [0.4.2] - 2026-10-01

### Fixed

- Use the browser timezone for dashboard month/year totals and payment table subtotals. Payments near midnight at a month or year boundary now count in the same period as their displayed date, including existing payments that previously appeared in October but were included in September totals on UTC servers.

### Changed

- Balanced the mobile New Payment button's right text inset with the plus icon's left inset.

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

- Reworked the English and German App Store descriptions into clearer Markdown sections with a shorter early-development notice.
- Replaced the legacy App Store thumbnails with dedicated, proxy-friendly preview assets while retaining the full-size screenshots for project documentation.
- Split the app icon treatment into a dark App Store icon and a dedicated navigation icon for reliable contrast across Nextcloud surfaces.

### Fixed

- Fixed low-contrast CoBudget icons in the top navigation and selected administrator navigation entries.

## [0.2.8] - 2026-07-18

### Changed

- Renamed the optional payment text field to "Payment reference or note" and removed its dated example placeholder for a calmer, future-proof payment form.
- Refined the English and German App Store descriptions to better explain personal budgeting, shared expenses, flexible areas and the early development status.
- Added optimized App Store thumbnails while retaining the full-size screenshots for the detailed app listing.
- Updated the public project documentation to reflect the official App Store availability, supported Nextcloud versions, signed release workflow and current development support policy.

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

### Initial Development Baseline

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
