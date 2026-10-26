# CoBudget Features

CoBudget beta releases are available through the official [Nextcloud App Store](https://apps.nextcloud.com/apps/cobudget) and as signed packages on [GitHub Releases](https://github.com/whollaus/cobudget/releases).

> [!WARNING]
> CoBudget is a beta version. Features, data structures and workflows may still change at any time.
> During the beta phase, updates or test data resets may require manual database corrections.

CoBudget is a Nextcloud app for personal and shared household budgeting.

- Compatible with Nextcloud 33, 34 and 35.
- English, German, French and Spanish interface text, including Reports, notifications and administration messages. Calendar headings follow the interface language; numeric dates and amounts follow your Nextcloud locale.

## Navigation

- Switch quickly between finances, areas and Reports through an always-visible mobile bottom navigation with active-section highlighting and device safe-area support.
- Open the unified Reports page from the app menu or mobile bottom navigation, using the line-chart icon formerly used for Statistics. Tapping the active mobile item preserves the opened details and filters. Old mobile Report/Statistics preferences no longer select a different view.
- Place Reports below Budget goals in the sidebar; Reports stays available when Budget goals is disabled.
- Keep the current page title and its primary actions visible in a sticky mobile header while scrolling.
- Find areas in the same natural A–Z order in the sidebar, My areas and area selectors throughout the app. Sorting follows the interface language, ignores case and spacing, and puts numbers such as 2 before 10. My finances stays first in selectors; archived areas remain in their separate, alphabetically sorted section under My areas.

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
- Create categories and payment partners directly in the payment form using a quiet action after the existing suggestions. Scroll within the menu on short screens; the creation input and confirmation stay in view while typing.
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
- Keep budget status and history in this independent module.

## Reports

The Reports page replaces the annual overview and the separate Statistics view with an independent period and breakdown. Open the period selector directly in the page title, then display its amounts by Quarters, Months, Weeks or Days. Months remain the default annual breakdown:

| Period | Included dates | Initial breakdown |
| --- | --- | --- |
| Current year / Last year | The corresponding calendar year. | Months |
| Choose year | A selected calendar year. The picker offers years with visible saved payments and retains an explicitly selected empty year. | Months |
| Current month / Last month | The complete corresponding calendar month. | Days |
| Last 12 months | The current calendar month and the eleven preceding months. | Months |
| Custom period | Any inclusive start and end date, including a vacation or a period spanning several years. | Days for up to 45 days, weeks for up to 120 days, otherwise months |

Changing the breakdown preserves the exact dates, financial view and payment filters. Weeks follow ISO calendar weeks, while months and quarters follow the calendar. The first and final buckets are clipped to the selected dates: changing from days to weeks or months never includes payments outside the selected period. Single-year overview table headings and chart labels omit repeated years because the title already names the year. Cross-year headings identify their year, and week tooltips show the actual included dates. Detail totals continue to name their year explicitly. Reports allow up to 3,660 columns; longer periods can use a coarser breakdown.

The title shows the actual applied period: a full calendar year as “Reports 2026”, a full calendar month as “Reports October 2026”, and other periods as precise inclusive dates. This also recognizes complete months and years chosen through Custom period. Month names follow the interface language; numeric dates follow the Nextcloud locale. On mobile, years stay beside Reports while months and date ranges use a full-width second title line, aligned with Reports below the header actions.

Open the title selector for quick periods, Choose year or Custom period. Year selection and From date / To date fields stay inside this popup and apply only after selecting Apply period. Closing or cancelling the popup discards pending edits. The end date is inclusive. Applying changed dates selects a useful initial breakdown; a later manual breakdown choice survives reloads and payment drilldowns. Calendar boundaries use the browser timezone, matching displayed payment dates, including daylight-saving transitions.

Existing annual, monthly, quarterly, weekly and daily report links remain supported. Legacy week-year links retain their original ISO week-year dates. New year reports always use the selected calendar year, including when displayed by weeks. Old `/analytics` bookmarks redirect to Reports, retaining compatible period selections, report filters and detail queries. Previous Statistics period keys, including selected years, map to the corresponding Reports periods. Existing annual-overview links and browser/account section preferences remain valid.

### Period totals

A compact summary above the development chart shows income, expenses and balance for the applied period and filters. It uses the same exact-cent totals as the tables, including the selected financial view and future-payment setting; overlapping label and tag rows never add payments twice. Disabling income management leaves only expenses. The figures remain readable on mobile and wrap when large amounts need more space.

Select a figure to open its detail page with a development chart, payment counts, averages and the matching saved payments. Dates, financial view and filters are retained. Changing the daily, weekly, monthly or quarterly breakdown leaves these totals unchanged. Summary figures are hidden while a changed report is loading or cannot be loaded.

### Development chart

The chart below the report controls follows the selected months, quarters, weeks or days and the same financial scope, filters and future-payment setting as the tables. Income, expenses and balance share one plot when income management is enabled; otherwise it shows expenses alone.

- Individual amounts shows each column's amount independently.
- Running total is the default and adds each time unit to the preceding ones, starting at the beginning of the selected period. Calculations retain integer cents and use the report totals, so overlapping label and tag rows do not duplicate payments.
- All weekly and daily values remain plotted. Centered axis labels adapt to the available width, with space at both ends, rather than dropping or combining values. Use the month selection to inspect a daily report in more detail.
- Inspect exact values with a mouse or touch. The chart has one keyboard focus stop; Left/Right, Home and End inspect time units, and Escape dismisses the values. Income, expenses and balance have distinct line patterns as well as colors.
- Toggle income, expenses and balance using the legend with mouse, touch, Enter or Space. The scale and value inspection follow the visible curves. Hidden curves remain listed with crossed-out labels so they can be restored; at least one curve stays visible.
- Periods after the report cutoff have no plotted value. Enabling future payments includes the same saved and projected amounts as the tables.

The chart can be collapsed through its section menu, and remembers this choice per account in the browser. The chart mode and curve selection remain selected while changing the report controls. Printing includes the active chart and its selected curves when the section is open. Exports include the active chart view with its selected curves and mode, together with the active report tables described below.

### Financial scope and grouping

- My finances counts only the user's personal payments and saved personal area shares. Selecting an area starts with Personal share. Shared areas offer an additional Entire area option for full source amounts; solo areas use their personal payments without an extra control. Personal reports use the stored payment amounts, including historical allocations that differ from today's area percentages, zero/full shares and independently edited settled payments. Settled payments remain included, without counting shared sources and personal copies together.
- Switching the amount perspective updates summaries, charts, tables, detail comparisons, saved payments and exports together. Report and detail links, reloads and return navigation retain the selected perspective. Selecting another area starts with Personal share again; amount limits apply to the chosen personal or full values.
- Group by categories, payment partners, or labels and description tags. Category rows expand into subcategories and a Main category row for payments assigned directly to the parent. Payments without a category or payment partner have their own row.
- Labels & tags includes only enabled labels and `#tags` used in the period. A payment appears in every matching row, while income, expense and balance totals count each qualifying payment once. Untagged payments and payments carrying only disabled labels stay out of these totals.
- Filter by category, payment partner, inclusive amount range, label and description tag before grouping. Several values in one filter match any of them; different filters apply together. The filter duplicating the grouping is hidden, while description tags remain available. Choices come from saved payments in the selected period and financial view, including unassigned values. Category and payment-partner choices distinguish expenses and income when income management is enabled.
- Filters remain in the address, detail comparisons, print header and matching payment list. Changing filters does not remove years from the year dropdown.

### Area comparison

In My finances, a collapsible Areas table below Total compares areas across the same quarters, months, weeks or days. It starts open and remembers its state per account in this browser. Selecting a specific area hides this comparison.

- Expenses and Personal share are the defaults. Local selectors offer Income and Balance when income management is enabled, and Full amounts per area for the complete authorized area amounts. These selectors affect this table independently of the personal figures above it.
- Personal amounts use stored historical allocations and settled personal payments. Full-area amounts use shared sources once, alongside personal payments from solo areas. Without area retains unassigned personal payments in both views. The personal footer reconciles to the corresponding My finances total; the full footer explicitly identifies its metric and amount perspective.
- Areas follow the same natural A–Z order as the sidebar, with Without area first. Archived areas remain included when they have matching historical payments. Sort by name or amount and open row titles or cells for the selected area and exact period. Return navigation restores the comparison, its controls and scroll position; bookmarked details retain their financial scope.
- Dates, payment filters and future-payment choices follow the main report. Labels and tags never multiply totals. Cross-workspace description-tag filters resolve equivalent normalized tags for full shared amounts and their detail links. Planned shared recurrences use stored personal allocations in Personal share and full source amounts in the full view, including zero-share areas accessible to the user.
- CSV includes the loaded comparison with its metric and perspective. Printing includes the expanded comparison with the same explicit caption and omits its interactive selectors.

### Future payments

Periods containing today default to Payments up to today. Include future payments adds saved future payments and upcoming occurrences of visible recurring series through the period end. Past periods are complete; future periods include future payments automatically.

Recurring forecasts follow the recurring job's schedule and series end date. My finances includes personal and solo-area series, plus the series head's stored exact-cent personal allocation of shared payments, using the stored allocation snapshot. Shared forecasts belong to the member's persisted personal workspace and require a positive stored share. Personal share restricts these forecasts to the selected area; Entire area uses the full source amounts. Amount filters use the selected personal or full value; tag rows and filters use the corresponding workspace's tag IDs. Areas with former participants have no new shared forecasts, matching the recurring job. Forecast reads do not create payments or change snapshots or rounding balances. Once an occurrence is generated and the series advances, its saved personal payment replaces the forecast. The payment list opened from a report contains saved payments only. Year and filter choices also come from saved payments, so a forecast alone does not make a new year or filter choice selectable.

### Top 10 saved payments

Income and Expenses each offer a Top 10 tab alongside their normal period table. The period table is the initial view; switching tabs preserves its sorting and expanded categories. Both tabs share the existing section collapse preference. Rankings load only when opened and refresh when the report changes.

The compact table lists at most ten saved payments, ordered by the selected amount descending. Its columns follow the payment overview: Date, Description, Category, From / to and Amount. Description and payment partner remain separate, including when a description is empty. Equal amounts use the newest payment ID first. The entire selected date range, payment type, filters, amount limits and personal/full-area perspective apply before limiting the result. Stored personal cents determine both ranking and display; historical allocations are not recalculated. Explicit future-payment mode includes saved future payments. Ungenerated recurring occurrences and statistical estimates are not saved payments and do not enter the ranking. Fewer matching payments leave a shorter list.

Top 10 has its own keyboard-accessible horizontal scroller; mouse and touch scrolling do not move the surrounding period tables. Amounts stay pinned on the right. On mobile, day/month dates stay pinned on the left and descriptions move below category and payment partner. Text columns have a readable minimum width and wrap long values, including unbroken words; a vertical rule separates category and partner. Category icons stay on desktop only. Mobile dates are horizontally centered; dates and amounts align with the first text line, including payments with descriptions. Full dates remain in date tooltips and accessible names, in print reports and whole-overview exports; section exports use the displayed compact dates and retain five separate payment fields. Income/expense and Development tabs stay visually compact and unfilled after touch, with a full touch target and native keyboard focus. Breakdown tabs retain their larger targets when wrapping across rows.

Click a row, or focus it and press Enter or Space, to open the familiar payment sidebar under the same ownership, locked-projection and settlement restrictions as the saved-payment detail list. Every cell uses the pointer cursor. Rows share one hover highlight without a separate cell button; an opened payment stays marked with the payment overview's primary-tinted background and, on desktop, blue lines along its top and bottom edges. Closing the sidebar clears the selection. Editing a shared source through its personal projection preserves the original selected row. Printing uses the active expanded table and the same five columns; Top 10 prints once without calendar-column splitting. Section and whole-overview exports use the ranking when Top 10 is selected, and the grouped period table when its overview tab is selected.

### Development and period-end forecast

Development and Forecast share a collapsible section in the overview and detail pages. Their tab strip aligns with the other section headings. Income, expenses and balance appear below it, above the selected chart; detail subjects show only applicable figures and disabling income leaves expenses only. Development is the initial view. The chosen tab and common collapse state are remembered per account in this browser, separately for overview and detail pages and shared across detail subjects. Selecting a tab opens the section. Past periods show Development only, without overwriting the saved tab choice.

- Development retains the report's cutoff and known-future-payment setting. Its overview summary figures open the corresponding existing details. Forecast shows expected income, expenses and period balance, plus a breakdown into payments up to today, known future payments and additionally estimated amounts. Forecast figures describe the selected period's income-minus-expenses balance, not an opening/current bank balance.
- The compact Forecast tab has no date suffix or information icon. Calculation rules are documented here rather than displayed in an in-app information panel.
- Forecast breakdown is the table's first column heading, without a repeated caption above it. The chart keeps its income, expense and balance legend; solid and projected line styles are explained in this documentation.
- Known future amounts include saved future payments and upcoming recurring occurrences, using the same stored personal allocations or full-area source amounts as the report. Forecast always includes these plans independently of the normal table's future-payment setting.
- For ongoing periods, additional expenses use the daily average so far, including empty calendar days and today. Fixed-cost-labelled payments and identifiable recurring payments are excluded from that pace. Linked personal payments also recognize their source series. Each category's expected remainder is reduced by its known future expenses, with a floor of zero, so a plan cannot reduce another category's estimate. No variable-spending data produces an unavailable expense estimate; an observed zero remains a valid zero.
- Additional income requires at least four equal actual payments with the same payment partner, category and area, in a recent regular rhythm. Without both partner and category, an identical nonempty description is required. Authorized income history from the preceding five years may provide evidence outside the opened month or week. Supported patterns are weekly, fortnightly or four-weekly and every one, two, three, six or twelve calendar months. Allow one calendar day of weekly date variation or four days for monthly rhythms. Month-end dates clamp to the target month's last day, including leap years. Identifiable recurring series use their known schedule instead. Changed amounts and interrupted patterns are not continued; a known future payment near an inferred date takes precedence even if its amount changes. Inferred income remains explicitly estimated and does not create payment automation.
- Forecast uses the same exact calendar buckets as the selected chart. Its default is Running total; Development and Forecast retain separate chart modes and visible-series choices while the section is mounted. Actual development has a solid line; buckets containing future dates have a shaded background and a dashed forecast continuation. Tooltips and keyboard descriptions separate actual, planned and estimated amounts. Integer-cent allocation of estimated expenses over remaining local calendar days preserves residual cents across clipped periods, daylight-saving transitions and leap days. The running-total endpoints reconcile with every box and the breakdown table.
- On the final day no additional whole days of expenses remain. Entirely future periods show known plans only; completed periods have no forecast. Dates, payment filters, parent/direct category scope, unassigned-area details and personal/full-area perspectives follow the opened subject. One-off purchases can distort the expense pace, and historical recurring payments whose source/series link has been detached are identifiable only through retained fixed-cost labels. No confidence score or statistical interval is implied.
- Forecast reads never create or alter payments, allocation snapshots or rounding balances. Statistical estimates do not enter Development, normal tables, comparisons or saved payments. Exporting the active Forecast tab includes its clearly labelled projected values and actual/planned/estimated breakdown.

### Tables and payment details

- Open the three-dot menu directly after each section heading or final tab for Collapse/Expand first, followed by a separator and CSV download, saving in Nextcloud and copying to the clipboard. Exports follow the active view, including Top 10, Forecast, selected chart curves and modes, and the area metric and amount perspective. The whole overview offers the same three export actions and uses these active section views. Clipboard data uses tabs for pasting into spreadsheets; CSV keeps decimal commas, exact amounts and protection against spreadsheet formulas. Section exports copy the displayed cells, including currency symbols, locale formatting, empty-value dashes, compact period headings, sorting and expanded rows. Top 10 keeps exactly its five displayed columns; saved-payment sections copy the current page. Each export captures its active data at the click and saves a new numbered file if its name already exists. Section exports start directly with column headers and data, without section titles; the whole-overview export additionally includes report dates, filters and amount perspective. The mobile overview places its filter row directly below the title, avoiding duplicate header spacing and retaining 44 px touch targets, including when month or custom-period headings wrap. On desktop, view options sit on the right; on mobile, they form a left-aligned row below the heading and its menu. This includes chart display and comparison controls, area metrics and amount perspectives, and detail income/expense choices. Compare and chart-display controls share neutral gray hover/open/focus fills, consistent keyboard focus and matching heights: 36 px on desktop and 44 px on mobile. Headings do not toggle visibility directly. Tabs keep the active choice in bold, switch the displayed view directly and open a collapsed section. Collapsing and reopening a section retains its selected tab and loaded content; existing per-account visibility preferences remain in use. Section menus close on clicks outside their trigger and menu, or when another section menu is opened, including mouse/trackpad interaction on iOS. Menu actions and native keyboard navigation, Escape and focus handling remain available. Menus are omitted from print reports.
- Income and expense tables each end with a clickable total row. A separate Total section shows income, expenses and balance. Disabling income management leaves only expenses and their total; disabling areas selects My finances and clears saved area filters. The overall Total section is hidden when no payments match.
- Income and expense rows start sorted by their total for the selected period, highest amount first. Sort by title (A–Z) or switch the total-amount direction through the table headers. Subcategories stay with their parent. Collapse income, expense and total sections independently; all start open and their state is remembered per account in this browser.
- Mobile report tables use the full available screen width with square table and cell corners; section headings and controls retain their page gutter. This includes period tables, Top 10, forecast and detail tables. Saved payments use the normal inset mobile card layout, retaining the page gutter for date headings, payment content, actions and pagination. The desktop layout keeps its rounded corners. Fixed-width amount columns scroll between pinned labels and totals on narrow screens. Headings and truncated values expose their full text as tooltips; day headings show short dates and week headings provide their Monday-to-Sunday range.
- Row titles, amounts and summary figures open a detail page within Report. Titles and total amounts cover the whole selected period; other cells cover their exact column dates. Detail links can be shared or reloaded and resolve names and values from the current authorized report. Back to report and Browser Back restore the overview selection, sorting, expanded categories, chart settings and vertical/horizontal scroll while the view remains open.
- Detail pages have separate collapsible Development/Forecast, Key figures, Averages, Comparisons, Composition and Saved payments sections, initially all open. Mobile detail headers keep Back, the wrapping title and Print in the top navigation row. The period and financial context occupy separate full-width lines below it, sharing the left text edge with section tabs and chart controls; long titles do not move the navigation buttons. Each section remembers its state per account in this browser across all categories, partners and periods; overview preferences remain separate. Closing a section preserves its loaded values and payment pagination. Overview tables, Development and detail sections share the same heading height and compact collapsed spacing. Charts start in Individual amounts mode and saved payments remain paginated and sortable. A category includes its subcategories; a direct Main category row or an individual subcategory includes only its own payments. Payment partners, labels, tags and totals retain their exact scope and filters. A single income or expense row shows its own curve; balance shows the available financial series. Selecting a single column loads an appropriate finer breakdown for that column's exact dates.
- Metrics label the selected total with its year, month, quarter, ISO week-year or exact dates, independently of the finer chart breakdown. They also include the exact payment count, share and average payment amount. Year, month, week and quarter details also show the subject's calendar-year total, labelled with the year and following the same filters, financial perspective and payment cutoff. Whole-range details show an average per displayed month, quarter, week or day, including empty buckets and using started buckets for a partial current period. Opened calendar buckets show the corresponding annual mean for that subject. Edge buckets count as one displayed unit. Balance details show income and expenses separately.
- Detail comparisons cover income, expense and balance totals as well as categories, payment partners, labels and tags. Calendar months show the previous month and the same month last year. Other calendar units use their corresponding periods; whole years offer the previous year. Free ranges offer the immediately preceding range of equal calendar length and the same dates last year.
- Reference rows show the baseline amount, exact difference and percentage where meaningful. References without available history are omitted from the menu and table. A genuine zero reference amount remains available and has no percentage. Trend arrows and colors appear only beyond ±5%.
- Use Compare beside Individual amounts to overlay any combination of available references; the average is selected initially and other references are optional. Select or deselect each reference individually in the compact menu, grouped into year averages, averages from previous periods and direct period comparisons. Reference curves have their own line patterns, legend entries and actual date tooltips. Average lines use stronger categorical accents from CoCalculator, adjusted to the active text color for contrast in light and dark themes. Longer reference months retain their extra days; shorter months never gain invented dates.
- Opening a month compares that subject with its monthly average across the selected calendar year; a week, quarter or day uses its corresponding annual weekly, quarterly or daily mean. This reference follows the original overview bucket even when the detail chart uses finer daily data. With payments up to today, all started annual buckets count, including the current month/week and empty buckets: 324 euros across January to October produces 32.40 euros per month. With future payments enabled, all twelve months count and the same sum produces 27 euros per month. Monthly presets load the same annual reference, with the selected financial perspective and payment filters. The table and chart share the same amounts, without averages across the entire stored history or progress captions.
- Additional annual benchmarks show averages per quarter, month and week, labelled with their evaluated year; daily details also retain their daily annual mean. Weekly details can compare with weekly means from the full previous month and quarter, monthly details with the monthly mean from the previous quarter, and daily details with daily means from the previous week, month and quarter. Sources are relative to the opened period, not today, and their actual month, quarter or ISO week-year is named in the table, menu and chart legend. For a weekly mean from a month or quarter, divide its total by its calendar days and multiply by seven, accounting for partial edge weeks, leap days and daylight-saving transitions. Empty days and months count; unknown or unfinished previous sources are omitted. All references retain payment filters, category scope and the selected personal or area amount perspective.
- The matching average curve is enabled by default and can be deselected in Compare. Whole-year and free-range details retain their average across the displayed buckets. The average is a horizontal benchmark at the exact full amount shown in the table, including in finer daily charts and Running total mode. A monthly average of 32.40 euros stays at 32.40 euros on every included day; it is neither divided by days nor accumulated. Weekly and quarterly references follow the same rule, including clipped periods, unequal month lengths and daylight-saving transitions. Ongoing period comparisons use matching elapsed dates and clock times; enabling future payments compares the full selected period, including its existing recurring forecast, with the corresponding historical reference. Unmatched leap days or ISO week 53 have no prior-year reference.
- Composition appears above saved payments as its own collapsible section, open initially and remembered across detail pages. Its heading exposes Payment partners, Categories, Areas, Labels and Tags as tabs instead of a grouping dropdown, with section actions after the last tab and income/expense choices on the right on desktop or in a left-aligned row below the tabs on mobile. Tabs wrap on mobile, support arrow/Home/End keys and reopen collapsed content when selected. Switch between applicable category, payment-partner, label, description-tag and area breakdowns; the subject’s own dimension and unused label/tag choices stay hidden. Category details start with payment partners, payment-partner details with categories. Sort the compact table by name, amount or payment count and read each row’s share of the selected income or expense total. Balance details offer separate expense and income views. Areas are offered when personal finances contain more than one area/personal group. Categories list direct assignments with their parent context, and unassigned payments remain visible. All rows retain the exact opened dates, filters, personal or full-area amounts and known recurring forecasts. Multiple tags or labels can overlap; the footer always counts each qualifying payment once, and a note appears only for overlapping assignments. Composition covers all matching payments independently of payment-list pagination. Partner tables use From / to, Payments, Share and Amount (currency), with compact numeric columns, centered counts, signed amounts and red negative totals. Empty composition sections stay hidden; balance details retain any available income or expense breakdown. Open a row or its amount to continue into that exact combination, including unassigned choices. The title shows the selection path, with names resolved from authorized data after navigation or reload. Each step intersects the existing subject and all payment filters; development, averages, comparisons, forecast, saved payments, printing and section exports use the same combination. Back returns one detail level, retaining open views and their scroll position.
- Saved payments appear below the other detail sections and can be opened in the familiar editing sidebar, subject to existing ownership and settlement restrictions. Payment history remains available. Personal views retain stored personal amounts, including independently editable released payments; Entire area shows source payments. When future payments are included, a note distinguishes projected recurring occurrences in charts and metrics from saved payments in the list. Label/tag totals exclude unlabelled payments and count each saved payment once.

### Printing and CSV export

The printer action opens an isolated A4 landscape report with a compact context header and supports saving as PDF through the browser. It includes the active Development or Forecast chart, chosen curves and display mode, summary figures, and the currently selected period-table or Top 10 tabs. Open area comparisons retain their own metric and amount perspective. Chart colors and line patterns are preserved on white paper; chart geometry is adapted for printing independently of the screen width. Hover indicators, controls and closed sections are omitted. A visible section that is still loading cannot silently disappear from the report.

Detail pages have their own printer action for the opened subject and exact dates. Open key figures, averages, comparisons, composition and saved payments are included. The saved-payment section loads every matching page without changing the on-screen page or sorting. Personal/full-area amounts follow the report, and area, payer and payment-type columns appear where relevant. Ungenerated recurrences and statistical estimates remain outside the saved-payment list. A changed selection, duplicate page, missing page or changing payment count cancels the export.

Long tables repeat headings. Wide overview tables use blocks of at most 12 period columns, repeating the label column and total on each new block. Daily reports print month by month, including partial months, with each month split into those blocks as needed. Top 10 and detail tables print without calendar-column splitting. Long labels and descriptions wrap rather than being clipped.

The download menu offers CSV export to this device, a selected Nextcloud Files folder, or the clipboard. Each section's three-dot menu offers these actions for that section, while the page menu combines the current section views, including collapsed sections. Exports follow the active tabs: grouped period tables or Top 10, Development or Forecast, the selected chart curves and display mode, and each area's metric and amount perspective. Section tables copy the displayed rows in their current order, including only expanded subcategories and the current saved-payment page. Whole-overview exports retain all subcategories. Whole-overview exports include a context block recording exact selected dates, financial scope, amount perspective, currency, payment cutoff, grouping/breakdown and named payment filters. Exports from section menus start directly with the selected section's column headers and data, without section titles, report context or settings. Tables without column headers start with their first data row. CSV uses semicolons; clipboard data uses tabs for spreadsheet pasting. Section exports preserve the displayed column labels, dates, currency symbols, locale spacing, amount formatting and dashes for empty cells. Whole-overview exports keep numeric amounts with decimal commas and blank empty cells. Negative amounts retain their sign, and user-provided text is protected against spreadsheet formulas. Printing saved payments includes all matching pages; copying their section exports the current displayed page. Filenames identify the selected period and section; existing Files exports are preserved with `-1`, `-2`, and further numbered suffixes. Closing the folder picker cancels saving.

### Related workflows

Budget goals, including their status and history, remain in the independent Budget goals module. They are not duplicated in Reports. Receipt checks, reminders and planned-payment lists belong to the payment workflows; shared-area settlement and member-payment details remain in their areas. Reports include known future amounts and forecasts, while Top 10 and explicit comparisons replace the old Statistics page's automatic findings. No automatic anomaly or trend-warning cards are shown.

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

Implementation rules and intentional variants are documented in the
[CoBudget Design System](docs/design-system.md).

- Shared form and button styles with visible keyboard focus, neutral secondary actions and larger mobile fields. Compact settings forms, report controls and the compound payment amount field retain their dedicated layouts. Area cards can also be opened using the keyboard.
- Dialogs share title, close-button, radius and shadow styles. Settings reuse one expense/income selector; custom menus share spacing and visible keyboard focus. Report text tabs, hierarchical category selection, payment sidebars and mobile full-screen dialogs retain their dedicated layouts.
- Cards share borders, radii and standard/compact padding. Lists, settings sections and table headers use shared spacing and theme colors, retaining compact payment rows, financial status colors, horizontal summary cards and mobile report tables with pinned columns.
- Member-search results support keyboard selection and Escape. Narrow settings columns place actions below names and badges. Small selection menus and expandable form groups share hover/focus styling; mobile Report tabs, filter options and icon choices use 44 px touch targets.
- Payment labels, area choices and the receipts/planning header provide mouse/trackpad hover feedback. Selected areas use a solid area-color fill with contrasting text and icons; My finances and default areas use the active Nextcloud blue. Unselected labels and areas use Nextcloud’s light accent palette. Area names match payment-label typography, and lookup headings match form labels. Opening receipts, recurrence and reminders brings the section into view and moves focus to it without opening a mobile keyboard.
- Focused form fields use one contrast border within their existing bounds, avoiding clipped double frames in the payment sidebar. Buttons and menus retain their keyboard focus indicators.
- Typography uses shared page, section, card, label and metric roles. Metadata badges share a readable style, while payment labels retain their compact size and semantic colors. Action, inline-status and close icons use separate consistent sizes; show/hide actions use paired eye icons.
- Follow the system theme.
- Force light mode.
- Force dark mode.
