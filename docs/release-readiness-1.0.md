# CoBudget 1.0 readiness review

**2026-11-13 — engineering assessment: 70/100, keep beta; no stable-release recommendation yet.**
The score is a prioritization aid, not a measured reliability percentage. The main
remaining risks are unexecuted MariaDB/PostgreSQL/Redis CI combinations, real
storage failure behavior and large-data scenarios. Concurrent app access is now fenced.

Scope: the local working tree preparing **0.8.8**, including the pending 0.8.6
email changes, compared with the last local release commit `05cefe5` (0.8.5).
No publication, independent security audit or external source-code scanning was
performed. Nextcloud 34.0.4 and 35.0.0 were tested locally with PHP 8.3 and SQLite.

## Findings corrected in this review

| Priority | Reproduction / evidence | Correction and regression coverage |
| --- | --- | --- |
| P1 | Delete a budget goal after a snapshot exists, create a full backup, then restore it. `BudgetController::destroy` retains snapshots, but `BackupService::assertBackupInternalReferences` rejected their missing goal IDs. Reproduced on the local database before the fix. | Export/restore detaches missing goal IDs to zero while retaining snapshot values. Personal exports now retain these snapshots too. Real ZIP/database tests cover full and personal restore, an older ZIP containing the deleted ID, live-goal ID remapping and exact amounts. |
| P1 | `readBackupArchive` treated settings and all table files as optional. A missing zero-row table listed in the manifest could pass the count check, while missing settings could become defaults. | Require settings, a baseline table inventory and every manifest-listed table file, even when its count is zero. Keep additive tables absent from older manifests optional. Tests reject missing files, invalid JSON, unknown tables, empty inventory and traversal entries. |
| P1 | ZIP entry writes and finalization returned success flags that were ignored. Files storage read the entire archive into a string and did not verify the stored size before pruning. | Check ZIP results, stream the file, compare stored byte count and remove failed partial writes before pruning. Tests inject ZIP-write failure, storage exceptions and silent short writes. The isolated upgrade test additionally exercises actual Nextcloud Files. It exposed adapters closing the input stream; the writer now checks before closing it again. Real quota/full-disk behavior remains a release gate. |
| P2 | Three literal messages were absent from all translated catalogs: `Due date` and two receipt-permission errors. The scanner only covered controllers and missed multiline calls. | Added German, French and Spanish translations. Coverage now scans frontend `tx` literals and PHP translation/error calls throughout `lib`, including both quote styles and multiline calls. |
| P2 | Payment CSV used hardcoded German `Ja`/`Nein` in every language. | Translate boolean labels with the export language. A generated CSV regression checks four languages, exact signed cents and formula neutralization. |
| P2 | Public descriptions lagged behind reminders/email/calendar behavior, and the feature guide contradicted itself about private-calendar synchronization. Backup wording could imply that receipts were included. | Updated descriptions, corrected the calendar statement and documented archive boundaries. Added four linked README languages and four App Store descriptions, focused on individuals, couples, families and housemates. |

## Maintenance boundary and backup scope — implemented

`AppDataLock` now admits regular HTTP requests, jobs, relevant OCC commands and
account deletion under shared native locks. Backup and restore use exclusive
admission throughout collection, validation, safety backup, replacement and cleanup.
An already running operation makes maintenance fail before financial work starts;
new HTTP requests receive a localized 503 while maintenance is active. Jobs defer
until their next scheduled run. Workspace authorization is checked again after
admission. Account deletion keeps admission until Nextcloud removes the account.

Separate-process tests exercise both contention directions, concurrent ordinary
requests, nested ownership, errors, job deferral, user-deletion lifetime and native
lock-mode transitions. Server locking must be enabled, coherent across nodes and
configured with a TTL longer than the operation. External SQL writers are outside
this protocol. See [server requirements](operations.md#server-locking-requirements).

**Receipt-file backup is deliberately outside CoBudget.** Use the existing backup
solution for Nextcloud Files. CoBudget preserves the stored attachment paths, which
must match the separately restored Files. Embedding receipt binaries is not a 1.0
release requirement. Actual CoBudget archive storage in Files is tested separately
from receipt-file recovery.

The upgrade fixture also exposed integrity checks offering deletion of legitimate
snapshots whose goal had been deleted. That goal reference is now treated as
optional historical context, including the detached zero reference after restore;
workspace integrity checks remain active.

## Release gates still open

1. **P1 — execute the expanded database matrix and test real storage failures.**
   CI now configures six recovery combinations (Nextcloud 34/35 × SQLite,
   MariaDB 10.11 and PostgreSQL 16), each with database and Redis locks. Fixtures
   include exact shares, settlements, deleted participants, account mapping,
   generated IDs and deterministic process-kill recovery. Local execution passed
   only with SQLite/native database locks; no remote MariaDB/PostgreSQL/Redis
   result is claimed. Docker/database services are absent locally. Real storage
   quota exhaustion, unavailable storage and interrupted Files writes remain
   separate checks; injected write exceptions and short writes are covered.
2. **P1 — obtain cross-database upgrade/recovery evidence.** The released
   0.8.5 → 0.8.8 path passes locally on isolated Nextcloud 34/35 with the expanded
   shared financial graph and actual pre-upgrade Files archives. SIGKILL after
   migration 13's update is recovered using saved database/Files/configuration
   and matching released code. SIGKILL before restore commit preserves previous
   rows/settings; a retry works after native lock expiry and simulated expiry of
   the additional restore marker. Remaining evidence is the configured server
   database/Redis matrix, not another implementation of the SQLite fixture.
   These tests cover selected crash boundaries, not every interrupted migration.
   Replacing PHP files alone remains an invalid schema rollback strategy.
3. **P2 — test representative large exports/backups.** CSV has a 50,000-payment
   limit and clipboard export a 1,000-payment limit. Restore applies size/ratio
   limits, but archive collection and JSON still use memory. Measure peak memory,
   runtime and maintenance duration with large fixtures; verify lock TTL and
   failure/retention behavior on production-shaped storage.
4. **P2 — finish language and presentation acceptance.** All three translated
   catalogs now have 1,734 matching keys. Automated checks do not prove the quality
   of every sentence. Review key screens, CSV, email and calendar text in all four
   languages, including fluent French/Spanish review. Replace the 0.2.10 screenshots
   before stable publication; current README screenshots are labeled historical.
5. **P2 — complete the manual release matrix.** Check two-user permissions,
   receipts, settlement, real mail/Talk/calendar delivery, narrow screens, keyboard
   interaction and themes on the final release candidate. Reload existing browser
   tabs after restore before editing. External notification transports remain mocked
   in regression fixtures and remote CI was not run here. An independent security
   review remains desirable; do not market internal checks as one.

## Backup and export conclusions

A personal export is a portable **personal view**, not a reversible copy of shared
area collaboration. Full backups preserve CoBudget financial tables and portable
preferences; they exclude receipt binaries and calendar/Talk runtime data.
The [recovery guide](operations.md#backup-and-recovery) now explains the distinction
between CSV, personal exports, full app backups and complete Nextcloud backups.

The reviewed CSV/report code applies text-cell formula protection, uses exact
stored cents for personal/shared perspectives and carries selected report context.
Existing frontend checks cover dangerous text, quoting, pagination, clipboard
limits, cancellation and personal versus shared totals. Runtime report fixtures
exercise scopes and filters. No new arithmetic defect was found in these checks;
spreadsheet-application interoperability and maximum-size performance were not
established by this review.

## Evidence collected

- `npm test` via the shared local-update script: PHP backend, static security and
  frontend suites passed. After the CSV correction, the PHP suite has **199 tests
  and 4,073 assertions**. The final local update reran the required checks successfully.
- Production frontend build passed. Webpack reports the existing asset/entrypoint
  size warnings; these remain a performance consideration, not a failed build.
- Both linked local instances updated to **0.8.8**. Nextcloud 35 reports missing
  indexes in `oc_job_classes_registry` and `oc_federated_invites`; these are outside
  CoBudget's tables and were not changed by this review.
- The new backup roundtrip suite passes **23 assertions per server** with real ZIP
  files and the actual SQLite database, virtual Files storage and an outer rollback.
  It also injects a failure after replacement inserts and verifies that the prior
  tables/settings survive the rollback. Compatibility CI now includes this and the 27-assertion multi-process concurrency suite;
  no remote CI result is claimed.
- The disposable recovery runner passed on Nextcloud 34.0.4 and 35.0.0 with
  SQLite/PHP 8.3: **19 upgrade/restore/mapping assertions**, **7 hard-kill restore
  recovery assertions**, and **4 old-version recovery assertions twice** (after
  an interrupted migration and after the completed run), plus the 23-assertion
  ZIP roundtrip and 27-assertion concurrency suites. The fixture now includes
  shared odd-cent splits, settlements, actual account deletion/former participants,
  recurrence and explicit high imported IDs. Test processes were killed at
  deterministic barriers; temporary installations were removed after testing.
- These tests reproduced stale Nextcloud user-config cache values after full
  restore/account mapping. The service now clears the public user-config cache
  after transactional deletion and rollback. A failed-restore fixture also
  changes currency before restoring, proving cached imported values cannot escape
  rollback.
- PostgreSQL sequence review found a nontransactional rewind risk: `setval` survives
  rollback. Sequence synchronization now keeps at least the existing sequence
  position and propagates errors. The crash fixture creates a newer committed row
  than the backup and verifies generated IDs remain above it after rollback.
  PostgreSQL execution is still pending CI; see the
  [PostgreSQL sequence contract](https://www.postgresql.org/docs/16/functions-sequence.html).
- Additional rollback-only integration suites passed on **both Nextcloud 34/35**:
  reports (468), shared-payment lifecycle (152), recurrence (109), master data (39),
  budget criteria (16), Insurance (16), reminders (108), planned notifications (36)
  and email (24 assertions). No external messages were sent.
- App metadata validates against the supplied Nextcloud schema. PHP syntax, local
  documentation links, version consistency and `git diff --check` are checked.

Primary code reviewed: `BackupService`, `BackupController`, full-backup commands,
`BudgetController`, `BudgetSnapshotService`, `EntryController`, `CsvCellSanitizer`,
report-export helpers, localization tests/catalogs, migration/CI metadata and the
architecture/operations documentation. Tests supplement this targeted review;
they are not a line-by-line audit of the entire app.

## Compatibility decision for 1.0

**Nextcloud 33 support ends with the 0.x series.** At the first stable release:

- Set `appinfo/info.xml` to minimum Nextcloud **34** and keep the tested upper bound.
- Remove the Nextcloud 33 CI job; run install, populated-upgrade and restore checks
  on 34 and 35 using their supported PHP versions.
- Update installation guidance, App Store descriptions and the security support
  policy together. Do not unnecessarily remove working compatibility code.
- Remove beta wording only after the release gates are met, refresh screenshots
  and complete the usual signed-package checks before publication.

The current 0.8.8 beta deliberately retains the 33–35 metadata/CI range.
Its metadata version was advanced to register the new middleware/event bootstrap;
this is not a declaration of stable maturity.

**Next action:** run the same concurrency, populated restore and upgrade checks on
MariaDB and PostgreSQL in disposable CI/test installations, then expand the upgrade
fixture to shared settlements and account remapping.
