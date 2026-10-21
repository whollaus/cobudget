# CoBudget feature verification — 2026-10-21

This review compares the current working tree with `v0.5.0`, the latest local
release tag. The existing `0.5.1` changelog section is retained. The review
consolidates Unreleased from 41 entries to 17, and aligns the annual-overview
documentation in `FEATURES.md` and `README.md` with the implementation.
The reviewed changes are included in the prepared `0.6.0` release.

## Feature checks

| Feature | Result and evidence |
| --- | --- |
| Annual, quarterly, weekly, monthly and daily views | Implemented in the report view, controller and service; automated period-boundary tests pass. All five views were opened in Nextcloud 35. The whole-year daily view has 365 day columns plus the total in 2026 and preserves the calendar-year amount. |
| Personal finances, solo/shared areas and exact cents | Controller integration tests pass in Nextcloud 34 and 35, covering saved personal shares, full shared source amounts, settlements, more than one page of payments, timezone boundaries and unauthorized access. |
| Categories, subcategories, payment partners, labels and tags | Implemented and covered by backend/frontend tests, including unassigned payments and counting tagged payments once in totals. |
| Combined filters and navigation | Implemented and tested for amount ranges, multiple values, scope isolation and return context. A monthly expense total was opened in the browser, followed through Show payments and returned to the annual overview. |
| Detail counts, shares, averages and comparisons | Automated tests pass for monthly, quarterly, weekly and daily periods, partial periods, missing history, leap dates and exact ±5% trend thresholds. A monthly detail dialog and its previous-month comparison were checked in the browser. |
| Future payments and recurring forecasts | Implemented for personal/solo-area series, full shared-area series and stored personal shares of shared recurrences. Regression tests cover exact cents, workspace isolation, personal tag IDs, amount filters, series end dates, zero shares, former participants and replacement by saved payments. See the fix below. |
| Printing and PDF action | Isolated landscape print documents, column blocks and section handling are implemented and covered by frontend tests. The annual print document was generated in the browser with all 12 months, totals, three sections and no interactive controls. Native PDF output was not saved during this review. |
| CSV download and Nextcloud Files action | Implemented. The browser generated an annual CSV with 14 columns, semicolons, decimal commas and the expected total. Frontend tests cover the Files request and filename conflicts; a real Files write was not performed. CSV exports include collapsed sections and subcategories in title order. |
| Mobile reports | Checked at 390 × 844 in Nextcloud 35: Annual overview/Statistics switching, remembered selection and horizontally scrolling daily columns. The whole-year daily view did not cause horizontal page overflow. |
| Insurance label | Present in the form, settings, filters, summaries, recurrence/projection handling, CSV and backup support. Its rollback-only integration test passes in Nextcloud 34 and 35. |
| Personal Talk notifications | Settings, opt-in, delivery adapter and privacy checks are present. Fast tests verify authenticated, workspace-independent settings routes. Live conversation creation and message delivery were not exercised. |

The maintainer successfully tested printing the daily overview and confirmed
that it works. This resolves the earlier incomplete automation check; it is not
an open printing defect. The annual report with twelve month columns was also
generated successfully during the automated browser review.

## Shared recurrence forecast fix

The missing personal forecasts are now included in **My finances**. The report
uses the shared series head's schedule and the authenticated user's stored
exact-cent allocation, matching Statistics. Shared series are included only in
the member's persisted personal workspace and only with a positive stored share.
Area reports continue to use full shared source amounts.

The change is limited to
[`EntryController::annualProjectedRecurrenceRows()`](../lib/Controller/EntryController.php).
Amount filters use the personal amount. Tags are matched by normalized name and
mapped to IDs and display names from the report workspace, so personal tag
filters and drilldowns continue to work across different source workspaces.
Forecast reads do not create payments or update allocation snapshots, rounding
balances or tags. Areas with former participants remain paused.

A rollback-only regression fixture verifies two €100.01 shared occurrences with
stored personal allocations of €40.01 each, including a previously balanced
rounding cent. My finances includes the resulting €80.02, plus its separate
saved personal payments. The area report shows €200.02. Advancing the series
and inserting a saved personal occurrence preserves both totals without double
counting. The same fixture also covers labels, tags and all five report periods.

Other forecast boundaries are intentional: payment drilldowns contain saved
payments only, and selectable years/filter values come from saved payments.
A future occurrence alone does not create a new year or filter choice.

## Validation

- `./scripts/update-local.sh cobudget --test` completed successfully: 153 PHP
  tests and 3,349 assertions, static security checks, frontend/route/rendering,
  timezone, localization and mobile-report suites, and a production build.
- Nextcloud 34 and 35 were both ready after the local update. The build emitted
  two asset/entrypoint size warnings.
- Release preparation updated both local installations to CoBudget `0.6.0`.
  App metadata validates against both Nextcloud schemas, all manual version
  sources and the built frontend agree, and PHP syntax checks pass for all 22
  changed or new PHP files.
- `tests/integration/annual-overview.php` passed with 174 assertions on each
  server. Its old expectation excluding planned recurrences was updated, and its
  recurrence fixture now uses the actual `month` interval.
- `tests/integration/insurance.php` passed with 16 assertions on each server.
- Integration fixtures were rolled back, including all new shared-forecast checks.
- The maintainer confirmed successful printing of the daily overview.
- Historical changelog sections from `0.5.1` onward were preserved byte for byte.

Nextcloud 35 additionally reported missing core indexes:
`oc_job_classes_registry.class_index` and
`oc_federated_invites.UNIQ_A2C17D5A5F37A13B`. The CoBudget upgrade and both
integration suites still completed successfully; these indexes were left for
separate Nextcloud maintenance.

The server checks used the existing local SQLite installations. Nextcloud 33,
other database engines, real Talk delivery and a real Nextcloud Files export
were not run in this review.
