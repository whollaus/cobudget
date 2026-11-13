# CoBudget

**English** · [Deutsch](docs/readme.de.md) · [Français](docs/readme.fr.md) · [Español](docs/readme.es.md)

**Know what you spend. Share costs fairly. Keep the overview in Nextcloud.**

CoBudget is a household budget app for individuals, couples, families and
housemates. Record everyday income and expenses, plan upcoming payments and see
your own share of shared costs. Use shared areas for your household, a trip or a
small group, with receipts and reminders close to the payments they belong to.

> [!WARNING]
> **Beta — current development version: 0.8.8.** Keep regular backups and do not
> use CoBudget as the only record of critical financial data. Development is
> AI-assisted; automated tests do not replace an independent security audit,
> which has not yet been performed. See [SECURITY.md](SECURITY.md).

## Everyday finances, alone or together

- **Keep track:** income, expenses, categories, payment partners, labels and tags.
- **Plan ahead:** recurring payments, budget goals, reports, comparisons and forecasts.
- **Share fairly:** record who paid, choose the split and settle outstanding
  balances. Your personal overview counts your share; shared areas show the total.
- **Keep the context:** store receipts in Nextcloud Files and manage independent
  or payment-linked reminders, including completion and an archive.
- **Choose your notifications:** Nextcloud, a private CoBudget Talk chat and optional
  email, with separate choices for supported events. Reminders can also sync with
  a calendar; private calendar edits sync back to CoBudget.
- **Use your data:** print reports, export CSV, copy tables to a spreadsheet and
  create personal exports or administrator backups.
- **Make it yours:** separate data pools with workspaces, use desktop or mobile
  layouts and choose a light, dark or system appearance.

The interface is available in English, German, French and Spanish.
[Features](FEATURES.md) explains the workflows and limits;
[report calculations](docs/reports.md) explains amounts, forecasts and exports.

## How CoBudget is organized

CoBudget is built around payments, personal shares and shared areas. Your personal
household book shows your own costs; a shared area keeps the total, who paid and
what members owe each other. CoBudget does not connect to bank accounts or
synchronize bank transactions. Its reports reflect the payments you record and
their allocation, rather than a reconciled bank account balance.
Read more about [the CoBudget approach](docs/concept.md).

## Requirements and the road to 1.0

The current beta supports **Nextcloud 33–35**. **CoBudget 1.0 will require
Nextcloud 34 or newer; Nextcloud 33 support ends with the 0.x series.** The planned
1.0 test matrix is Nextcloud 34 and 35. There is no committed release date.

Use the PHP version supported by your Nextcloud release (8.2 or newer for 33/34,
8.3 or newer for 35), a modern browser and working Nextcloud background jobs.
Talk, calendar synchronization and email are optional; email uses the address in
your Nextcloud account and requires configured outgoing mail on the server.

The [1.0 readiness review](docs/release-readiness-1.0.md) records findings and open
release gates. [Tests](tests/README.md) describes what the automated checks cover.

## Install or update

Install CoBudget through Nextcloud's Apps administration from the
[App Store](https://apps.nextcloud.com/apps/cobudget), or use `occ app:install cobudget`.
For manual installation:

1. Download the signed `cobudget.tar.gz` from [GitHub Releases](https://github.com/whollaus/cobudget/releases).
2. Extract it into Nextcloud's `apps/` or `custom_apps/` directory as `cobudget`.
3. Enable the app and complete any pending update.

GitHub's automatic source archives are not installable app packages. Back up your
data before updating. Configure system cron to run Nextcloud's `cron.php` every
five minutes as the web server user. See [Operating CoBudget](docs/operations.md)
for jobs, notifications and recovery.

## What a backup contains

A **personal export** preserves your financial perspective and can be imported
only into an empty CoBudget user state. Shared amounts become independent personal
payments. An **administrator full backup** preserves all CoBudget financial data
and portable user settings; restoring it replaces the app's current data.

**Neither archive contains receipt files.** It stores their paths. Back up the
associated Nextcloud Files with your existing Nextcloud Files backup solution.
Receipt-file backup is deliberately handled there. These archives do not replace a
complete Nextcloud backup. CoBudget temporarily blocks app access during backup and
restore. If an operation is already running, retry once it has finished. Reload open
CoBudget tabs after a restore. See the
[recovery procedure](docs/operations.md#backup-and-recovery).

## Feedback and support

Bug reports and test results are especially helpful during beta. CoBudget is
primarily maintained by one person; please discuss code contributions first.
See [CONTRIBUTING.md](CONTRIBUTING.md), [GitHub Issues](https://github.com/whollaus/cobudget/issues)
and the [changelog](CHANGELOG.md). Report suspected vulnerabilities using
[SECURITY.md](SECURITY.md).

Support development through [GitHub Sponsors](https://github.com/sponsors/whollaus)
or [Stripe](https://donate.stripe.com/aFaeVdfARbPNe897bu5J600).

## Development

- [Development guide](docs/development.md): builds and local tests.
- [Architecture](docs/architecture.md): data, permissions, backups and migrations.
- [Design system](docs/design-system.md): UI conventions and verification.
- [AGENTS.md](AGENTS.md): coding-assistant instructions.
- [Release process](RELEASING.md): packaging and signing.

## Screenshots

<details>
<summary>Show screenshots from an earlier beta</summary>

These illustrate the app's purpose; the current interface has changed substantially.

![Payment overview](screenshots/screenshot-v0.2.10-1.jpg)
![Shared areas](screenshots/screenshot-v0.2.10-2.jpg)
![Reports](screenshots/screenshot-v0.2.10-3.jpg)
![Settings](screenshots/screenshot-v0.2.10-4.jpg)

</details>

## License

GNU Affero General Public License v3.0 or later. See [LICENSE](LICENSE).
