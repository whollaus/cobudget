# Testing CoBudget

Run commands from the app repository with Node 24, installed dependencies
(`npm ci`) and a PHP CLI compatible with your Nextcloud version.

## Regression tests

```sh
npm test
npm run build
```

`npm test` runs backend, static-security and frontend checks, plus the bulk-import regressions. Individual suites
are available as `npm run test:php`, `npm run test:static-security` and
`npm run test:frontend-smoke`.

Run `npm run test:bulk-import-example-date` to check the JSON examples across
local midnight. The import form and format dialog stay mounted while the test
clock crosses New Year in Vienna. It checks every payment date in the placeholder,
displayed example, clipboard text and downloaded JSON on both sides of midnight.

Run the import regression on its own with `npm run test:bulk-import-resume`.
It drives the import view and PHP controller against an in-memory SQLite database.
With three members, the test drops the confirmation after payment 933, reopens the
same file, and checks that the next packet contains the remaining 17 rows of that
preview block. It verifies all 5,000 originals and 15,000 personal allocations,
including each member’s cent amount and a replay of the unacknowledged packet.

Run `npm run test:bulk-import-member` to check a non-owner's permissions in a
shared area. An unknown category or payment partner in the second preview packet
rejects the whole file. The test checks that no payments, shares or lookup
records were written, then verifies the saved assignments for a valid file
with existing names and omitted optional fields.

Run `npm run test:bulk-import-admin` for the area owner. Repeated lookup names
across packets, a lost confirmation, resumption and replay must produce a single
area category and payment partner referenced by every imported payment.

## Compatibility CI

The [CI workflow](../.github/workflows/ci.yml) checks installation and upgrades on
Nextcloud 34 and 35. Recovery tests cover SQLite, MariaDB and PostgreSQL, with
database and Redis locks. They exercise upgrades from a released version,
backup/restore, concurrent access and recovery after interrupted processes.
The same database matrix compares the payment form’s locale-derived currency
with the stored import and verifies both sides of the January 2038 timestamp
boundary during preview and commit.
The folder-import regression creates rules as an ordinary member and kills the PHP process after
33 of 50 shared payments have committed. A new process verifies the remaining 17 payments,
all 150 shares and personal records, and the unchanged identities after same-name and renamed
re-uploads. It also exercises a reused filename with different data, whole-file rejection of
unknown lookups in the second packet, and a personal folder rule.
Two independent PHP processes also select the same folder for different shared areas
with both imports paused. Both pass the availability check before either writes.
The suite covers creating rules and updating existing rules. It checks that one
request succeeds, the other returns a folder conflict, and only the winner owns the folder.
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

The folder import regressions cover a rename with a replacement at the old path, an exclusive lock held by a second PHP process, and an unusable error archive followed by a valid delivery. Raw JSON and browser preview tests verify that sub-cent amounts cannot round into valid payments.

The disposable upgrade suite interrupts a three-member folder import after 33 writes and a second worker immediately after the archive move. While the cron worker holds the rule lock, a manual start must return 409. With Nextcloud file locking disabled, a due cron run and a manual start must both leave the interrupted delivery, a fresh upload and the schedule intact. A separate fixture verifies that twelve files without delete permission leave the thirteenth file eligible and that restoring permissions books every delivery once. After locking is restored, a manual start resumes the remaining 17 payments and 51 shares. It also renames a delivery after 33 writes, verifies that both names reject changed data, and replaces the input folder at that checkpoint. Explicitly reselecting the new node must complete the remaining 17 payments and 51 shares from the snapshot. The suite also checks a replacement under two names, reordered fields, account-wide notification visibility and all shared allocations. A second three-member regression renames a category after 33 of 50 bookings. It checks the German status alongside a successful independent file, restores the category, and verifies all payment and allocation identities after a repeat upload. A separate worker with a real exclusive Files lock verifies that copies of a changed source stay unbooked until it can be read. A missing-delete-permission fixture checks that readable copies are rejected while independent payments continue. Both check all original, share and projection identities after repair and replay. Queue regressions cover a renamed and edited delivery while an older archive move is blocked, a valid file behind twelve invalid deliveries with a blocked error folder, and a currency change after preview or after the first 33 bookings. They also exercise partially booked deliveries from before the currency migration. The queue fixture edits the first of 13 unarchived sources between checks, replays a conflicting payload from a previously rejected preview and compares every original, allocation and personal entry ID after repair and re-upload.

Pass `--inbox-load` to drain 5,000 separate one-payment Files deliveries with a 64 MiB PHP limit.

A two-process Files test pauses the worker between source reconciliation and file admission. A second process edits a booked source, uploads a copy, edits selected files and replaces a selected node. The test compares payment, share and personal entry IDs after repair and replay. A separate test adds a delivery before each of six checks while thirteen completed files await archiving. It checks progress during the uploads as well as final counts.

The archive race tests synchronize two PHP processes at the Files move. They edit a fully booked file with an unchanged ETag and kill a worker immediately after the move with a changed ETag. Recovery cases replace the archived node or move it to a monthly folder before uploading the changed payments under a fresh name. The tests check that the conflict exists in the journal when the worker stops and that replay with reordered JSON fields is also rejected. Separate recovery tests overwrite the processed file with an independent payment, preserving or replacing its Files ID. Both keep the archive intact and book the fresh delivery and its three shares exactly once. Each archive race checks the collision-safe archive name, the disposition of the changed source and its copies, and all 50 payments, 150 allocations and 150 personal entry IDs after replay.
