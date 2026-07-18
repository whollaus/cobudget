# CoBudget Features

CoBudget's current `0.2.x` release line is available through the official [Nextcloud App Store](https://apps.nextcloud.com/apps/cobudget) and as signed packages on [GitHub Releases](https://github.com/whollaus/cobudget/releases).

> [!WARNING]
> CoBudget is an early alpha version. Features, data structures and workflows may still change at any time.
> During the alpha phase, updates or test data resets may require manual database corrections.

CoBudget is a Nextcloud app for personal and shared household budgeting.

## Payments

- Create income and expense entries.
- Add date, amount, payment reference or note, category and payment partner.
- Suggest a category from safe, repeated payment-partner habits without overwriting a manual selection.
- Review change history for edited payments, including changed fields and previous values.
- Mark entries with labels for important payments, payments to review, fixed costs, subscriptions, children and tax relevant payments.
- Add free-form `#tags` directly in the payment note field.
- Attach receipts and invoices.
- Use recurring payments and reminders.
- Filter, search, paginate and export payment lists, including tags when available.

## Categories And Payment Partners

- Manage personal categories and payment partners.
- Use global categories and payment partners provided by an admin.
- Hide global entries that are not relevant for your own workflow.
- Use area-specific categories and payment partners for shared areas.

## Shared Areas

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
- Track personal share only.
- Review progress, forecast and buffer.
- Use budget insights in analytics.

## Analytics

Analytics show where money is going and how financial trends develop.

- Current year, current month, last 12 months and year-based views.
- Income, expense and balance summaries.
- Personal metrics use the user's exact materialized share, regardless of whether a payment originated personally or in a shared area.
- Development charts.
- Forecasts for the selected period.
- Planned shared recurrences derive the user's stored allocation from the shared source without double-counting personal projections.
- Focus tables by category, payment partner, label and area.
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

## Templates

- Create reusable payment templates.
- Use templates from the payment form.
- Sort templates by usage.

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
