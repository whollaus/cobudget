# CoBudget Features

CoBudget beta releases are available through the official [Nextcloud App Store](https://apps.nextcloud.com/apps/cobudget) and as signed packages on [GitHub Releases](https://github.com/whollaus/cobudget/releases).

> [!WARNING]
> CoBudget is a beta version. Features, data structures and workflows may still change at any time.
> During the beta phase, updates or test data resets may require manual database corrections.

CoBudget is a Nextcloud app for personal and shared household budgeting.

- Compatible with Nextcloud 33, 34 and 35.
- English, German, French and Spanish interface text, including analytics, notifications and administration messages. Calendar headings follow the interface language; numeric dates and amounts follow your Nextcloud locale.

## Navigation

- Switch quickly between finances, areas and analytics through an always-visible mobile bottom navigation with active-section highlighting and device safe-area support.
- Mobile Analytics opens Annual overview by default and offers an Annual overview / Statistics switch in both reports. The last mobile report is remembered per account in the current browser. Both reports highlight Analytics in the bottom navigation, and tapping the active item preserves its filters. Desktop sidebar entries stay separate.
- Keep the current page title and its primary actions visible in a sticky mobile header while scrolling.

## Payments

- Create income and expense entries.
- Add date, amount, payment reference or note, category and payment partner.
- Match dashboard month/year totals and payment table subtotals to the displayed payment dates using the browser timezone, including payments near midnight at calendar boundaries.
- Create and edit income and expenses in a responsive right sidebar with mode-specific titles, guarded unsaved changes, and support for consecutive desktop entries. The main new-payment action remains available while reviewing existing payments and is hidden only while creating a new payment.
- Suggest a category from safe, repeated payment-partner habits without overwriting a manual selection.
- Review change history for edited payments, including changed fields and previous values.
- Mark entries with labels for important payments, payments to review, fixed costs, subscriptions, children, insurance and tax relevant payments.
- Add free-form `#tags` directly in the payment note field.
- Copy an existing personal or area payment while retaining its area and defaulting the new payment to today's date.
- Attach receipts and invoices.
- Use recurring payments and reminders.
- Filter, search, paginate and export payment lists, including tags when available.

## Categories And Payment Partners

- Manage personal categories and payment partners.
- Use global categories and payment partners provided by an admin.
- Hide global entries that are not relevant for your own workflow.
- Use area-specific categories and payment partners for shared areas.
- Let area owners hide or show global categories for the entire area.
- Assign an optional free-text number or code to a category and find it by that value when entering or searching payments.
- Organize categories into one optional level of main categories and subcategories.
- See the category hierarchy in personal, area and administrator settings as well as while choosing a category for a payment.
- Store optional internal person, company, address, contact, bank and note details for personal, area-specific and global payment partners.
- Keep payment-partner creation name-only and maintain the additional master data in grouped, collapsible edit sections without exposing the remaining internal master data in payment search or payment lists.
- Enable advanced personal category and payment-partner editing when needed; the personal feature is disabled by default, while area and administrator master-data editing remains available.
- Show payment-partner numbers in settings and payment selectors, support selecting by number, open filled master-data groups initially and validate email-address formats before saving.

## Shared Areas

- Enable areas and shared areas under Settings → Features, directly above Budget goals. Shared-area notification preferences have their own Notifications section, visible only when both area features are enabled.
- Optional personal Talk messages for new payments created by other members, independently of Nextcloud bell notifications. Off by default; opting in creates a private, read-only CoBudget conversation automatically when Talk is available.
- Notifications include the creator, area, amount, currency and area link, excluding descriptions and receipts. Delivery validates conversation privacy and stops if its participants, visibility or bot configuration change.

Areas are used for shared spending such as household costs, trips or family budgets.

- Create personal or shared areas.
- Store payments in a one-member area directly as ordinary personal payments linked to that area.
- Hide settlement and member-balance controls for one-member areas because there is nothing to split.
- Label one-member areas as personal in the area overview without showing a meaningless settlement balance.
- Require a one-member area to be empty before adding its first additional member and switching it to shared payment handling.
- Hide member and split controls while payments block structural changes, and explain whether payments must be removed or the shared area settled first.
- Add trusted Nextcloud users as members.
- Define the default percentage split for each member.
- Distribute indivisible remainder cents fairly over time, including unequal splits and areas with more than two members.
- Assign payments to an area.
- Choose an area when creating or editing payments: up to five areas and My finances use two columns with at most three rows; six or more areas use a dropdown.
- Keep the member allocation of every payment as an exact historical snapshot, independent of later area-default changes.
- Create one exact personal payment for every member with a positive share and store it in that member's Basis workspace.
- Keep personal payments locked and synchronized while the shared payment is open.
- Release personal payments as independent records when the area is settled, while retaining the area and settlement as origin metadata.
- Keep a real per-user change history with exact personal amounts for every materialized personal payment, including edits made through its open shared source.
- Copy shared receipts physically into every active member's own Nextcloud Files and release those copies at settlement.
- Require open payments to be settled before changing members, default shares or archiving an area.
- Convert area-specific categories and payment partners into personal entries when a settled member leaves the area.
- Keep deleted Nextcloud accounts as non-login former members so their historical shares, balances and settlements remain correct.
- Pause new payments and structural split changes while an area contains a former member, then allow the area owner to remove that former member after settlement without losing history.
- Transfer area ownership to another active member manually or automatically when the current owner is deleted.
- Prevent former members from being selected as payer or direct target for new payments while retaining them in established area splits.
- Record who paid or received the money.
- Settle open payments.
- Review settlement history and repayment suggestions.

## Workspaces

Workspaces separate data into isolated pools, for example private and business data.

- Create multiple workspaces.
- Switch between visible workspaces.
- Hide workspaces from the quick switcher.
- Keep workspace administration global for the current user.

## Budget Goals

- Create flexible or fixed budget goals.
- Define criteria using area, category and label combinations.
- Distinguish main categories and subcategories when selecting category criteria.
- Track personal share only.
- Review progress, forecast and buffer.
- Use budget insights in analytics.

## Annual Overview

Open the dedicated sidebar page to compare income, expenses and balance in My finances or an individual area. The page title switches between five views:

| View | Columns and total |
| --- | --- |
| Annual | Twelve calendar months and the calendar-year total. |
| Quarterly | Four quarters and the calendar-year total. |
| Weekly | All ISO weeks of the selected week-year and their total. Days around New Year follow the ISO week-year, so this total can differ from the calendar-year total. |
| Monthly | Each day of the selected month and that month's total. Choosing All returns to the annual view. |
| Daily | Every calendar day of the selected year and the year total, or just the days and total of a selected month. All retains the whole year. |

Changing the view retains the selected year. The year dropdown offers only years with visible saved payments in the selected financial view, including future-dated payments. If necessary, it selects the latest available year up to the current year, or the nearest future year. Empty financial views have no selectable year; empty months remain selectable. Calendar boundaries use the browser timezone, matching displayed payment dates.

### Financial scope and grouping

- My finances counts only the user's personal payments and saved personal area shares. A shared area shows its full source amounts; a solo area shows its personal payments. Settled payments remain included, without counting shared sources and personal copies together.
- Group by categories, payment partners, or labels and description tags. Category rows expand into subcategories and a Main category row for payments assigned directly to the parent. Payments without a category or payment partner have their own row.
- Labels & tags includes only enabled labels and `#tags` used in the period. A payment appears in every matching row, while income, expense and balance totals count each qualifying payment once. Untagged payments and payments carrying only disabled labels stay out of these totals.
- Filter by category, payment partner, inclusive amount range, label and description tag before grouping. Several values in one filter match any of them; different filters apply together. The filter duplicating the grouping is hidden, while description tags remain available. Choices come from saved payments in the selected period and financial view, including unassigned values. Category and payment-partner choices distinguish expenses and income when income management is enabled.
- Filters remain in the address, detail comparisons, print header and matching payment list. Changing filters does not remove years from the year dropdown.

### Future payments

Periods containing today default to Payments up to today. Include future payments adds saved future payments and upcoming occurrences of visible recurring series through the period end. Past periods are complete; future periods include future payments automatically.

Recurring forecasts follow the recurring job's schedule and series end date. My finances includes personal and solo-area series, plus the series head's stored exact-cent personal allocation of shared payments, matching Statistics. Shared forecasts belong to the member's persisted personal workspace and require a positive stored share. Amount filters use that personal share; tag rows and filters use the personal workspace's tag IDs. Area reports retain full shared source amounts. Areas with former participants have no new shared forecasts, matching the recurring job. Forecast reads do not create payments or change snapshots or rounding balances. Once an occurrence is generated and the series advances, its saved personal payment replaces the forecast. The payment list opened from a report contains saved payments only. Year and filter choices also come from saved payments, so a forecast alone does not make a new year or filter choice selectable.

### Tables and payment details

- Income and expense tables each end with a clickable total row. A separate Total section shows income, expenses and balance. Disabling income management leaves only expenses and their total; disabling areas selects My finances and clears saved area filters. The overall Total section is hidden when no payments match.
- Sort income and expense rows by title (A–Z initially) or total amount. Subcategories stay with their parent. Collapse income, expense and total sections independently; all start open and their state is remembered per account in this browser.
- Fixed-width amount columns scroll between pinned labels and totals on narrow screens. Headings and truncated values expose their full text as tooltips; day headings show short dates and week headings provide their Monday-to-Sunday range.
- Click an amount for a detail dialog with the financial view, period, exact payment count, share and average payment amount. Total columns show an average per calendar month, quarter, week or day, including empty buckets and using elapsed buckets for a partial current period. Balance details show income and expenses separately.
- Detail dialogs compare months, quarters, weeks or days with the previous period and the corresponding period last year, where history exists. Ongoing periods use matching elapsed dates. Differences show the percentage and exact euro amount; arrows and trend colors appear only beyond ±5%. A zero baseline has no percentage, and unmatched leap days or ISO week 53 have no prior-year comparison.
- Show payments opens the exact saved payments, retaining filters and including all subcategories for a parent total. Balance opens both payment types. Row labels open matching payments without a date range. Returning restores the report selection. Close a detail dialog with its Close action, Escape or a click outside.

### Printing and CSV export

The printer action opens an isolated A4 landscape report; the browser print dialog also supports saving as PDF. The report includes the period, financial view, grouping, filters and applicable cutoff. Sorting, expanded categories, enabled features and open sections follow the displayed tables; collapsed sections are omitted. Long tables repeat headings. Wide views use blocks of at most 12 period columns, repeating the label column and total on each new block. Whole-year daily reports print month by month, with each month split into those blocks as needed.

The download menu offers CSV export to this device or a selected Nextcloud Files folder. The CSV uses the selected period, financial view, grouping, payment filters and recurring forecast. It lists all subcategories in title order, including collapsed rows and sections; it does not follow the on-screen amount sort. Semicolons separate columns, amounts use a decimal comma, and empty payment cells stay blank. Existing Files exports are preserved by adding `-1`, `-2`, and further numbered suffixes to the new filename.

## Analytics

Analytics show where money is going and how financial trends develop.

- Select the analytics period from a compact dropdown on mobile while retaining quick period buttons on larger screens.
- Read monthly chart axes through localized month abbreviations while full period labels remain available in tooltips and value tables.
- Compare income, expenses and balance in a consistent three-card summary with embedded averages and booking counts, shown as a readable single column on mobile.
- Current year, current month, previous month, last 12 months, previous year and year-based views.
- Income, expense and balance summaries.
- Personal metrics use the user's exact materialized share, regardless of whether a payment originated personally or in a shared area.
- Development charts.
- Forecasts for the selected period.
- Planned shared recurrences derive the user's stored allocation from the shared source without double-counting personal projections.
- Focus tables by category, payment partner, label and area.
- Open a daily or monthly development chart from any category, payment partner, label, `#tag` or area focus row, with an expandable table of exact period values and booking counts.
- Category focus tables show direct main-category bookings, each subcategory and a group total without double-counting the overall total.
- Shared-area summaries.
- Budget signals.
- Missing receipt signals for review and tax relevant payments.
- Print-optimized report view.

## Receipts

- Store receipt references in CoBudget.
- Save receipt files in Nextcloud Files.
- Configure the receipt folder.
- Optionally group receipts by year or month.
- Optionally delete receipt files when deleting a payment.

## Backup And Restore

- Create personal exports from the settings UI.
- Schedule regular personal exports in Nextcloud Files.
- Create personal exports and administrator-owned full backups via OCC commands.
- Restore full admin backups with confirmation.
- Create an automatic full safety backup before full restore.
- Preserve receipt paths without embedding the files.
- Support user mapping for full server transfers.
- Restore personal exports only into an empty CoBudget user state, remapping the source account and importing shared-area amounts solely as independent personal shares.

## Admin Settings

- Manage global categories and payment partners.
- Run integrity checks.
- Repair supported data issues.
- Review data quality warnings.

## Theme

- Follow the system theme.
- Force light mode.
- Force dark mode.
