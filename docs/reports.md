# Report calculations and exports

This reference explains what CoBudget Reports counts and how forecasts, comparisons
and exports differ. Start with the [feature overview](../FEATURES.md#reports) for
navigation and [Architecture](architecture.md) for storage and permission invariants.

## Dates and filters

The period and its breakdown are independent. Combine multiple calendar years
with any selected months, or choose inclusive custom dates. Each month applies to
every selected year. Skipped months and years contribute neither amounts nor days
to calculations. Relative and All time bookmarks remain supported.

Display the selection by quarters, months, ISO weeks or days. Edge buckets stay
inside the selected dates; changing the breakdown does not widen the period.
Calendar boundaries use the browser timezone, including daylight-saving changes.
Reports allow up to 3,660 columns; use a coarser breakdown for longer selections.
Custom dates initially choose days up to 45 days, weeks up to 120, otherwise months.

Search matches descriptions, category names/numbers and payment-partner names.
Categories, partners, areas, labels, tags and inclusive amount bounds apply to the
selected financial perspective. Alternatives in one filter match any selected
value; different filters intersect. Bookmarks, detail pages and exports retain
these selections. Legacy annual and Statistics links map compatible selections
to Reports, including the original ISO week-year meaning of old week links.

## Financial scope

| View | Amounts counted |
| --- | --- |
| All payments | The current user's personal payments, including solo areas and stored shared-area shares. |
| Without area | Personal payments with no area assignment. |
| One area, Personal share | Stored personal payments/shares for that area. |
| One shared area, Entire area | The full shared source payments the user is authorized to see. |
| Multiple areas | Personal amounts for the selected areas, optionally including Without area. |

Stored allocations, rather than today's member percentages, determine personal
amounts. Independently edited settled payments retain their personal values.
Shared sources and their personal copies never contribute twice to one view.
Amount filters, totals, comparisons, forecasts and exports use the same perspective.

In All payments, the Areas comparison has its own Personal share/Full amounts and
income/expense/balance controls. Those affect that table independently of the main
report. Archived areas remain when they contain matching historical payments;
Without area stays personal in both perspectives. Details and exports identify
the selected metric and perspective explicitly.

## Grouping and details

Group by categories, payment partners, or labels and tags. Equal category/partner
names combine across areas, ignoring capitalization and repeated whitespace;
category parent paths and income/expense types remain distinct. Expand the origin
areas to inspect subtotals. This changes presentation, not stored records.

Categories retain subcategories and direct assignments to their parent. Unassigned
categories and partners remain visible. Label/tag rows may overlap: each payment
counts only once in the corresponding financial total. In Labels & tags, payments
without enabled labels or matching tags do not enter that grouped population.

Open a total, row title or amount for details. Composition rows continue into an
intersection such as Food → Supermarket, retaining dates, filters and perspective.
Back returns one detail level. Saved-payment rows open the existing editor with
its normal permissions and history; projections and estimates are not saved rows.

Top 10 tabs rank the largest matching **saved** income or expense payments.
Ungenerated recurrences and statistical estimates are excluded. The normal
period table remains the initial view.

## Development and known future payments

Development starts each new visit to Reports. A chosen Forecast tab remains
selected through filters, date changes and detail navigation within that visit;
leaving Reports and returning, or reloading the page, starts with Development.
Overview and detail choices are separate. Section visibility retains its existing
account-specific browser preference.

Development supports individual period amounts or a running total, with selectable
income, expense and balance curves. Exact integer-cent totals match the tables.
Mouse, touch and keyboard inspection expose individual values; line patterns help
distinguish curves without relying on color.

Periods containing today default to Payments up to today. Include future payments
adds saved future payments and upcoming recurring occurrences through the selected
end. Past periods are complete; entirely future periods include future payments.

Recurring projections use the job's schedule/end date and the series head's exact
stored personal allocation, or full source amounts in Entire area. Personal shared
projections require a positive share and belong to the persisted personal workspace.
Areas with former participants have no new shared projections. Forecast reads never
create payments, modify allocations or change rounding balances. Generated saved
payments replace their projections as the series advances.

Year and filter choices come from saved payments; a forecast alone does not add a
new selectable year or filter value. Saved-payment lists contain only saved rows.

## Period-end forecast

Forecast separates actual payments, known future plans and additional estimates.
It describes income minus expenses during the selected period, not a bank account's
opening or current balance. Known plans are included even when Development is set
to Payments up to today.

For ongoing periods:

- Additional expenses use the average variable spending per elapsed calendar day,
  including empty days and today. Fixed-cost labels and identifiable recurring
  payments are excluded. Known future expenses reduce the expected remainder in
  their own category, never below zero or at another category's expense.
- Without observed variable spending, the expense estimate is unavailable; an
  observed zero is a valid zero. No additional whole expense days remain on the
  final day of the period.
- Additional income needs at least four equal actual payments with matching partner,
  category and area in a recent regular rhythm. When both partner and category are
  absent, an identical nonempty description is required. Authorized history from
  the preceding five years may provide evidence outside the selected period.
- Supported inferred rhythms are weekly, fortnightly, four-weekly, or every one,
  two, three, six or twelve calendar months. They allow one day of weekly variation
  or four days for monthly rhythms. Inferred month-end dates clamp to the target
  month's last day; this inference rule is separate from stored recurrence rules.
- Changed amounts and interrupted patterns are not continued. Known future
  payments near an inferred date take precedence, even if their amount differs.
  Inference never creates a recurring payment rule.

Entirely future periods show known plans only. Completed periods have no Forecast
tab. One-off expenses can distort the estimate; historical recurrences with detached
series links can only be recognized through retained fixed-cost labels. Forecast
provides no confidence interval or guarantee.

The chart separates solid actual development from dashed future continuation and
labels actual, planned and estimated values. Estimated expenses are distributed
in integer cents over remaining local calendar days, preserving residual cents
through clipped periods, leap days and time changes. Running-total endpoints
reconcile with the summary and breakdown. Statistical estimates never enter normal
Development totals, comparisons or saved payments.

## Averages and comparisons

Empty time buckets count in averages. With Payments up to today, annual averages
use all started buckets, including the current bucket. For example, 324 euros from
January through October is 32.40 euros per month. With future payments included,
all twelve months count, giving 27 euros per month for that same amount.

Detail comparisons offer the previous period and corresponding period last year,
plus applicable annual or earlier-period average benchmarks. A custom preceding
range has the same calendar-day length. Ongoing periods compare matching elapsed
dates and times; including future payments uses the complete period. Unmatched
leap days or ISO week 53 have no prior-year reference.

A weekly mean derived from a month/quarter divides its total by calendar days and
multiplies by seven. Sources are relative to the opened period and retain filters
and financial scope. Unavailable or unfinished reference sources are omitted.
Zero is a valid baseline, but no percentage change is calculated from it; trend
indicators require a change beyond ±5%.

Average chart lines show the full reference amount as a horizontal benchmark in
both individual and running-total modes. A 32.40 euro monthly mean remains 32.40
on every visible day; it is neither divided by days nor accumulated.

## Printing, CSV and clipboard

Printing uses an isolated white A4 landscape document with the report context,
active Development/Forecast chart and selected curves, active table tabs and open
sections. Closed sections and controls are omitted. Save PDF through the browser.
Wide tables split into blocks of at most twelve period columns, repeating labels
and totals; daily reports are grouped by month. Long tables repeat their headings.

Detail printing loads **all matching saved-payment pages** without changing the
on-screen page or sort. A changed selection, inconsistent page/count or loading
failure cancels printing rather than silently omitting records. Projected or
estimated payments do not appear as saved payments.

| Export | Content |
| --- | --- |
| Whole overview CSV | Current section views, including collapsed sections and all subcategories, with titles and report context. |
| Section CSV/clipboard | The active section's displayed columns and ordered rows, expanded children and current saved-payment page, without a report-context block. |
| Print | The currently open sections and active tabs; detail saved payments include every matching page. |

All exports identify the chosen dates, filters and perspective as appropriate to
their scope. CSV uses semicolons; clipboard data uses tabs. Whole-overview CSV uses
numeric amounts with decimal commas and blank empty cells. Section exports retain
displayed dates, currency formatting and empty-cell dashes. User-provided text is
protected against spreadsheet formulas.

Export to a device or a chosen Nextcloud Files folder. Existing Files exports are
preserved using numbered filename suffixes; closing the folder picker cancels saving.
