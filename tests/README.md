# Testing CoBudget

Run commands from the app repository with Node 24, installed dependencies
(`npm ci`) and a PHP CLI compatible with your Nextcloud version.

## Regression tests

```sh
npm test
npm run build
```

`npm test` runs backend, static-security and frontend checks. Individual suites
are available as `npm run test:php`, `npm run test:static-security` and
`npm run test:frontend-smoke`.

## Compatibility CI

The [CI workflow](../.github/workflows/ci.yml) checks installation and upgrades on
Nextcloud 34 and 35. Recovery tests cover SQLite, MariaDB and PostgreSQL, with
database and Redis locks. They exercise upgrades from a released version,
backup/restore, concurrent access and recovery after interrupted processes.
The workflow also builds and validates the installable archive.

## Integration tests

Use a separate Nextcloud test instance with sample data. These checks require
CoBudget to be enabled and an existing test administrator:

```sh
php tests/integration/backup-concurrency.php --nextcloud=/path/to/server --user=admin
php tests/integration/backup-roundtrip.php --nextcloud=/path/to/server --user=admin
```

The upgrade harness creates a disposable installation from a Nextcloud template.
It requires Python 3.12+, a compatible PHP CLI and the Git tag `v0.8.5`:

```sh
python3 tests/integration/run-upgrade.py --source /path/to/nextcloud \
  --php /path/to/php --evidence /tmp/cobudget-recovery
```

To test MariaDB or PostgreSQL without Docker, add `--database mysql` or
`--database pgsql` and `--db-bin /path/to/database/bin`. The harness initializes
a private temporary server on a loopback port and removes it afterwards; it does
not connect to an existing database installation. Both database clients and
server binaries must be present. CI continues to use disposable containers.

See `python3 tests/integration/run-upgrade.py --help` for database and log options.
Automated checks do not replace browser tests, real notification delivery or
storage failure tests. For bug reports and test feedback, see
[CONTRIBUTING.md](../CONTRIBUTING.md).

CI also checks production npm dependencies against current security advisories.
Run `npm audit --omit=dev` when updating dependencies.
