# CoBudget Features

> [!WARNING]
> CoBudget is an early alpha version. Features, data structures and workflows may still change at any time.
> During the alpha phase, updates or test data resets may require manual database corrections.

CoBudget is a Nextcloud app for personal and shared household budgeting.

## Payments

- Create income and expense entries.
- Add date, amount, description, category and payment partner.
- Review change history for edited payments, including changed fields and previous values.
- Mark entries with labels for important payments, payments to review, fixed costs, subscriptions, children and tax relevant payments.
- Add free-form `#tags` directly in the description field.
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
- Add trusted Nextcloud users as members.
- Define the default percentage split for each member.
- Assign payments to an area.
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
- Development charts.
- Forecasts for the selected period.
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
- Create personal exports and full backups via OCC commands.
- Restore full admin backups with confirmation.
- Create an automatic full safety backup before full restore.
- Preserve receipt paths without embedding the files.
- Support user mapping for full server transfers.
- Keep personal exports export-only so they cannot overwrite shared data in existing installations.

## Admin Settings

- Manage global categories and payment partners.
- Run integrity checks.
- Repair supported data issues.
- Review data quality warnings.

## Theme

- Follow the system theme.
- Force light mode.
- Force dark mode.
