# Development and installation tests

CoBudget is primarily maintained by one person. Read [CONTRIBUTING.md](../CONTRIBUTING.md)
before proposing a pull request; bug reports and test results need no prior agreement.
All paths below refer to the app repository unless stated otherwise.

## Build and test

Use a separate Nextcloud development instance and sample financial data.

```sh
npm ci
npm test
npm run build
```

See [tests/README.md](../tests/README.md) for dependencies, coverage and integration
checks, [Architecture](architecture.md) for technical invariants, and the
[design system](design-system.md) for UI conventions. Coding assistants start
with [AGENTS.md](../AGENTS.md).

Production bundles are committed in `js/`. To create an unsigned archive for local
packaging checks, run `npm run release`; it writes `cobudget.tar.gz` in the parent
directory. Public releases use the maintainer's [signed release process](../RELEASING.md).
Repository documentation, screenshots, tests and development tools are excluded
from installable packages.

## Reset CoBudget for a fresh-install test

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
