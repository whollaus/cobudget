# CoBudget

> [!WARNING]
> CoBudget is a beta version. Features, data structures and workflows may still change at any time.
> Development is AI-assisted. The app has automated and targeted security and integrity tests, but it has not been independently audited or fully reviewed line by line. Use it only with regular backups and do not rely on it as the only source for critical financial records.
> During the beta phase, updates or test data resets may require manual database corrections.

CoBudget is a Nextcloud app for personal and shared household budgeting.

It helps you track income, expenses, budgets, receipts and shared areas directly inside your Nextcloud instance. The app is designed for private households, families, couples and small trusted groups who want to understand daily spending, split shared costs and keep their financial data under their own control.

## Project Status

CoBudget is available in the official [Nextcloud App Store](https://apps.nextcloud.com/apps/cobudget) and remains under active beta development.

- The supported release line is `0.6.x`; see the [changelog](CHANGELOG.md) for the latest changes.
- Nextcloud 33, 34 and 35 are supported.
- The interface is available in English, German, French and Spanish. Calendar headings follow the interface language; numeric dates and amounts follow your Nextcloud locale.
- App Store releases are signed and mirrored as installable assets on [GitHub Releases](https://github.com/whollaus/cobudget/releases).
- Features, data structures and upgrade behavior may still change before `1.0.0`.
- Backups are strongly recommended before every update, especially for shared areas and restore workflows.
- Bugs and feature requests are tracked through [GitHub Issues](https://github.com/whollaus/cobudget/issues).

## Personal Talk Notifications

In **Settings → Notifications**, enable **Personal Talk notifications** to receive new payments created by other shared-area members in a private **CoBudget** conversation. The section appears when areas and shared areas are enabled under **Settings → Features**, directly above Budget goals. The Talk option is off by default and independent of the existing Nextcloud bell notifications. Disabling it stops future messages and retains the Talk history.

Nextcloud Talk must be installed and enabled for your account, including permission to create conversations. CoBudget creates the read-only conversation and registers its native bot on opt-in; no app password, external webhook, manual room token or separate bot command is required. The conversation contains only its recipient, who cannot add participants. The adapter targets Talk 23–25 on Nextcloud 33–35.

Messages contain the creator, area, amount, currency and area link. Descriptions and receipts are omitted. Chat history remains in Talk after payments are changed or deleted; Talk/device settings control push notifications. Only newly created shared payments use this channel; settlements, edits, reminders and automatically generated recurring payments are not included.

If you leave/delete the chat or its access settings change, delivery stops. Use **Reconnect personal chat** in CoBudget to create a replacement. A disabled bot must first be re-enabled by an administrator. Delivery failures are logged without financial contents and never prevent saving a payment; messages are not queued or retried. Backups preserve the opt-in but do not export Talk history or conversation tokens; reconnect after moving to another server. Resetting CoBudget disables the integration without deleting Talk history.

## Screenshots

The screenshots below illustrate the app UI and may differ from the current beta release.

![CoBudget payment overview](screenshots/screenshot-v0.2.10-1.jpg)

![CoBudget shared areas](screenshots/screenshot-v0.2.10-2.jpg)

![CoBudget analytics](screenshots/screenshot-v0.2.10-3.jpg)

![CoBudget settings](screenshots/screenshot-v0.2.10-4.jpg)

## Features

- **Reports:** Select the period directly in the title, which shows a year, month or exact date range. Choose the current or previous month or year, a selected year, the last twelve months, or a custom inclusive date range such as a ten-day vacation. Read income, expenses and balance at a glance, with income and expense tables ordered by their total for the selected period, highest amount first. Open a row title or amount for a detail page with collapsible sections for Development, period-labelled key figures, averages, comparisons, composition and saved payments. All sections start open and remember their state per account in this browser across detail pages. Use visible tabs to break down the selected subject by payment partner, category, area, label or tag with amounts, shares and payment counts. Open a composition row to inspect its exact combination, such as Food → Supermarket, with the same dates, area, amount perspective and filters. The title shows the selection path; Back returns one detail level and retains the previous view. Edit matching payments and return to the previous overview. View the same dates by quarters, months, weeks or days, with months as the default annual breakdown; calendar buckets at the edges stay inside the selected dates. Compare personal finances or individual areas; shared areas start with Personal share and can switch to Entire area. Summaries, details, comparisons, payments and exports follow the chosen perspective and stored historical allocations. Group and filter payments, see the subject's calendar-year total in year, month, week and quarter details, compare detail totals with previous periods, and optionally overlay reference curves or averages for different units and source periods in the development chart. Yearly quarterly, monthly and weekly means let a week be compared with an average month or a month with an average quarter. Earlier-period benchmarks include weekly means from the previous month or quarter and daily means from the previous week. Opened months and weeks default to the subject’s monthly or weekly average in the selected year, through the current bucket for payments up to today or over the full year with future payments. The metric and default-enabled curve share the full reference amount, shown as a horizontal line in finer daily charts and both display modes. Empty buckets count, and unavailable comparisons stay hidden. Compare areas side by side below Total, using personal shares by default or full area amounts, with income, expense and balance choices and matching period details. Switch between Development and Forecast in one collapsible section, with income, expense and balance boxes above the chart. Forecast separates actual payments, known future plans and additional estimates with the same dates, filters and personal/full-area amounts. Its projected chart reconciles to the boxes and breakdown table. Exclude fixed costs and identifiable recurring payments from the expense pace and offset known plans per category. Estimate additional income only after at least four matching recent payments in a regular rhythm; earlier authorized history can provide the basis and known plans always take precedence. Completed periods hide the forecast; future periods show plans only. Reports also include known recurring forecasts, CSV export to this device or Nextcloud Files, and A4 landscape printing/PDF. See the [report details](FEATURES.md#reports) for period boundaries, forecast limits and export behavior.

- Track income and expenses
- Calculate dashboard month/year totals and payment table subtotals in the browser timezone, matching the displayed payment dates
- Switch between finances, areas and Reports through a persistent app-style mobile bottom navigation
- Use one Reports entry with the line-chart icon on desktop and mobile; old Statistics links redirect with compatible periods and filters, and budget goals remain in their own module
- Find Reports below Budget goals in the sidebar; mobile report tables use the full available screen width with square corners while section headings keep their page gutter
- Keep page titles and primary actions visible in a sticky mobile header while scrolling
- Create and edit income and expenses in a responsive sidebar with mode-specific titles, guarded unsaved changes, support for consecutive desktop entries, and a new-payment action that remains available while reviewing existing payments
- Review payment change history with changed fields, previous values and new values
- Organize payments by categories and payment partners, with searchable numbers and creation directly in the payment form, including on short screens. Use one level of main categories and subcategories, and opt-in advanced personal address, validated contact and bank master-data editing for payment partners
- Combine area-specific categories with global categories that area owners can hide or show for the entire area
- Add labels such as important, review, fixed costs, subscriptions, children, insurance and tax relevant
- Add free-form `#tags` directly in the payment note
- Copy an existing personal or area payment while retaining its area and starting the new payment with today's date
- Create shared areas for household costs, trips or other shared budgets
- Find areas in a consistent natural A–Z order across navigation, My areas and area selectors, with My finances first and archived areas kept separate
- Choose payment areas in two columns with My finances for up to five areas (at most three rows), and a dropdown from six areas onward
- Use one-member areas as simple personal groupings without balances or settlements
- Add the first additional member only while an area has no personal payments, avoiding ambiguous retroactive splits
- Split shared area payments by configurable member percentages
- Preserve each shared payment's original member split as exact percentages and cents, even when area defaults change later
- Balance indivisible remainder cents cumulatively so the same member is not systematically favored across repeated shared payments
- Materialize every positive member share as a personal payment in that member's Basis workspace
- Keep personal shares synchronized and locked until the area is settled, then release them as independent personal payments with their own exact-value history
- Store a physical receipt copy in each active member's own Nextcloud Files and release those copies at settlement
- Preserve former members and their historical shares when a Nextcloud account is deleted, pause new shared payments until the area is resolved, and retain settled history without keeping a reusable login user ID
- Transfer area ownership manually or automatically to another active member
- Settle shared areas and keep settlement history
- Attach receipts and invoices stored in Nextcloud Files
- Define flexible budget goals with hierarchy-aware category choices
- Open Top 10 tabs beside Report income and expenses, with independent horizontal scrolling and pinned amounts; mobile dates are compact, centered and pinned, wider text columns wrap without category icons, descriptions sit below category and payment partner, dates and amounts align with the first line, and rows open payment details
- Collapse or expand Report sections through the three-dot menu after their headings or tabs, with view options on the right on desktop and in a left-aligned row below the heading on mobile; retain the selected view and remembered section visibility
- Keep mobile Report filters close to the title, matching the finance overview's content spacing; use matching neutral-gray Compare and chart-display controls with consistent heights and keep mobile saved-payment cards inset from the screen edges
- Read mobile report-detail dates and financial context in two aligned, full-width lines below navigation; long titles wrap while Back and Print remain aligned at the top
- Close section menus by clicking outside or opening another section menu, with consistent mouse and touch behavior
- Print the current Report overview or detail page with charts, figures, selected table tabs and all matching saved payments; download sections as CSV, save them in Nextcloud or copy them into a spreadsheet using the active tab's displayed column labels, currency formatting and visible rows; section exports start with column headers and data, while whole-overview exports also include section titles and report settings
- Use workspaces to separate independent data pools
- Export payments as CSV
- Create personal exports and administrator-owned full backups/restore
- Support light, dark and system theme modes
- Use consistent form and button states with visible keyboard focus and keyboard-accessible area cards, while retaining compact settings forms and dedicated report/payment controls
- Dialogs share title, close-button, radius and shadow styles. Settings reuse one expense/income selector; custom menus share spacing and visible keyboard focus. Report text tabs, hierarchical category selection, payment sidebars and mobile full-screen dialogs retain their dedicated layouts.
- Cards share borders, radii and standard/compact padding. Lists, settings sections and table headers use shared spacing and theme colors, retaining compact payment rows, financial status colors, horizontal summary cards and mobile report tables with pinned columns.
- Member-search results support keyboard selection and Escape. Narrow settings columns place actions below names and badges. Small selection menus and expandable form groups share hover/focus styling; mobile Report tabs, filter options and icon choices use 44 px touch targets.
- Payment labels, area choices and the receipts/planning header provide mouse/trackpad hover feedback. Selected areas use a solid area-color fill with contrasting text and icons; My finances and default areas use the active Nextcloud blue. Unselected labels and areas use Nextcloud’s light accent palette. Area names match payment-label typography, and lookup headings match form labels. Opening receipts, recurrence and reminders brings the section into view and moves focus to it without opening a mobile keyboard.
- Focused form fields use one contrast border within their existing bounds, avoiding clipped double frames in the payment sidebar. Buttons and menus retain their keyboard focus indicators.
- Typography uses shared page, section, card, label and metric roles. Metadata badges share a readable style, while payment labels retain their compact size and semantic colors. Action, inline-status and close icons use separate consistent sizes; show/hide actions use paired eye icons.

See [FEATURES.md](FEATURES.md) for a more detailed overview.

## Requirements

- Nextcloud 33, 34 or 35
- A PHP version supported by the installed Nextcloud release: at least PHP 8.2 for Nextcloud 33/34, or PHP 8.3 for Nextcloud 35
- A user account with access to the CoBudget app
- Browser with modern JavaScript support

See the [Nextcloud 35 compatibility review](docs/nextcloud-35-compatibility.md) for the API audit, server requirements and validation scope.

## Installation

### Nextcloud App Store

Install or update CoBudget from the Apps section of your Nextcloud administration. The official listing is available at [apps.nextcloud.com/apps/cobudget](https://apps.nextcloud.com/apps/cobudget).

Administrators can also install it with OCC:

```sh
occ app:install cobudget
```

### Manual installation

If the App Store is unavailable, install a signed GitHub release manually:

1. Download the `cobudget.tar.gz` asset from [GitHub Releases](https://github.com/whollaus/cobudget/releases).
2. Extract or upload it so the app folder is named `cobudget`.
3. Place it in the Nextcloud `apps/` or `custom_apps/` directory.
4. Enable the app in Nextcloud.

Only the signed `cobudget.tar.gz` release asset is an installable app package. GitHub's automatically generated source archives are repository snapshots and must not be installed as Nextcloud apps.

## Background jobs

CoBudget uses Nextcloud background jobs for recurring payments, reminders, automatic backups and budget snapshots. Configure system cron to call Nextcloud's `cron.php` every five minutes as the web server user; see the [Nextcloud background-job documentation](https://docs.nextcloud.com/server/33/admin_manual/configuration_server/background_jobs_configuration.html#cron).

If the log reports `failed to create instance of background job`, check the CoBudget jobs from the Nextcloud directory, using the same PHP installation and user as your cron setup:

```sh
php occ cobudget:background-jobs:check
```

The command prints each job's loaded file, constructor/dependency errors and queue registration. It does not execute, reserve or remove jobs. A successful check confirms that the jobs can be constructed and are registered; it does not verify their actual execution or the system cron schedule. A web-only problem can still require checking the web server's PHP configuration and deployed app files.

After resolving any loading errors, restore missing registrations with:

```sh
php occ cobudget:background-jobs:check --repair
```

Repair checks all four jobs before adding missing registrations. Existing registrations, including legacy jobs with empty-array arguments, keep their schedule and state. Jobs are run later by the configured Nextcloud scheduler. The command must be installed on the server along with the updated CoBudget files before it is available.

## Development

For UI changes and new modules, follow the [CoBudget Design System](docs/design-system.md)
for shared components, tokens, deliberate CoBudget variants and validation.
See [CONTRIBUTING.md](CONTRIBUTING.md) for the development workflow.

### Reset CoBudget for a fresh-install test

The standalone `scripts/development/reset-installation.php` tool removes CoBudget's database installation state so that enabling the app runs its migrations as a fresh installation. Deleting only its tables is insufficient: Nextcloud also stores the installed version and completed migrations. The existing `occ cobudget:reset-all` command clears app data but retains the installation state.

**The reset permanently deletes CoBudget data for all users. Create a backup first.** It drops tables whose names start with the configured Nextcloud database prefix followed by `cobudget_`, including obsolete app tables. It also removes CoBudget's user preferences, app configuration, migration records and background-job registrations. Other apps' entries in shared Nextcloud tables are preserved. App code, receipt/backup files in Nextcloud Files and existing logs or activity history remain; this is an installation test reset, not a complete personal-data erasure.

The tool supports PostgreSQL and SQLite. Deletion requires a disabled CoBudget app, Nextcloud maintenance mode and the exact confirmation below. If a CoBudget job is still reserved, the reset stops; let the running job finish before retrying. Table and metadata changes run in one database transaction and are rolled back if an operation fails. External foreign-key dependencies block deletion instead of being removed with `CASCADE`.

This development tool is intentionally excluded from installable CoBudget release archives. For a deliberate fresh-install test, copy `scripts/development/reset-installation.php` and `scripts/development/InstallationResetService.php` from the source repository into a temporary directory in the installed app. Remove both files from the server when the test is complete. The following commands assume they were copied to `custom_apps/cobudget/scripts/development/`, use the standard Nextcloud AIO paths and execute PHP as the web server user.

Preview the affected tables and row counts without deleting data:

```sh
docker exec -u www-data nextcloud-aio-nextcloud \
  php /var/www/html/custom_apps/cobudget/scripts/development/reset-installation.php \
  --nextcloud=/var/www/html
```

After checking the preview and creating a backup, run these commands in order. Continue only if each command succeeds:

```sh
docker exec -u www-data nextcloud-aio-nextcloud php /var/www/html/occ app:disable cobudget
docker exec -u www-data nextcloud-aio-nextcloud php /var/www/html/occ maintenance:mode --on
docker exec -u www-data nextcloud-aio-nextcloud \
  php /var/www/html/custom_apps/cobudget/scripts/development/reset-installation.php \
  --nextcloud=/var/www/html --confirm=DELETE-COBUDGET-INSTALLATION
```

Only after the reset reports success, end maintenance mode and enable CoBudget to install it again:

```sh
docker exec -u www-data nextcloud-aio-nextcloud php /var/www/html/occ maintenance:mode --off
docker exec -u www-data nextcloud-aio-nextcloud php /var/www/html/occ app:enable cobudget
```

The reset is standalone because Nextcloud does not load app-provided OCC commands while maintenance mode is enabled. It leaves maintenance mode under the operator's control, including after a failure. Resetting the installation enables a fresh-install test; it does not establish the cause of an earlier background-job class-loading error. Because `scripts/` is not part of the release archive, normal installations do not contain this destructive maintenance tool.

### Build and test

Install frontend dependencies:

```sh
npm ci
```

Build production assets:

```sh
npm run build
```

Create an unsigned archive for local development checks:

```sh
npm run release
```

This builds the frontend and creates an unsigned test archive in the workspace root:

- `cobudget.tar.gz` for local packaging checks

The release archive intentionally contains only runtime app files. Documentation assets such as `screenshots/` are kept in the GitHub repository but are not included in installable archives.

Public releases must use the local signed release process described in [RELEASING.md](RELEASING.md).

## GitHub Releases

Regular pushes to `main` only run checks. Pushing a version tag runs all checks and creates a draft prerelease. It intentionally does not publish an unsigned archive.

Before creating a tag, make sure the version matches in:

- `appinfo/info.xml`
- `package.json`
- the tag name, for example `vX.Y.Z`

Release command flow:

```sh
VERSION="$(node -p "require('./package.json').version")"

git add .
git commit -m "Prepare CoBudget $VERSION release"
git push

git tag -a "v$VERSION" -m "CoBudget $VERSION"
git push origin "v$VERSION"
```

After the draft exists, the maintainer builds and signs the final archive locally with the Nextcloud certificate and its matching private key. The signed archive, detached App Store signature, and checksum are uploaded before the draft is published. See [RELEASING.md](RELEASING.md) for the complete procedure.

Run the available test checks:

```sh
npm run test
```

## Release Notes

This project follows semantic versioning as far as practical during the beta phase.

- Patch releases should contain fixes.
- Minor releases may add or change features.
- Breaking changes are possible during beta and will be documented in the changelog.

See [CHANGELOG.md](CHANGELOG.md).

## Security

Please report security issues through GitHub Issues for now.

See [SECURITY.md](SECURITY.md) for details.

## Support

If CoBudget helps you, you can support development through [GitHub Sponsors](https://github.com/sponsors/whollaus) or [Stripe](https://donate.stripe.com/aFaeVdfARbPNe897bu5J600).

## License

CoBudget is licensed under the GNU Affero General Public License v3.0 or later.

See [LICENSE](LICENSE).
