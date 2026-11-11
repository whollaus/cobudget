# Historical verification records

This consolidates the September 2026 Nextcloud 35 review and October 2026 feature
review. **Results apply only to the versions and test environments recorded below.**
They are not current test results or a release checklist. Current commands and
coverage boundaries live in [tests/README.md](../tests/README.md); maintained
behavior belongs in [Features](../FEATURES.md), [Reports](reports.md) and
[Architecture](architecture.md).

## 2026-09-16 — CoBudget 0.4.0 compatibility review

The review introduced Nextcloud 35 support while retaining 33/34 compatibility.
It checked the removal of JavaScript aliases, Symfony Console return types,
schema wrappers and affected backend APIs. The request-token helper moved to
public `@nextcloud/auth`. The review used the
[Nextcloud 35 critical changes](https://docs.nextcloud.com/server/35/developer_manual/release_notes/critical_changes.html)
and version-specific server requirements as references.

Recorded local results:

| Server | PHP | Checks recorded as passed |
| --- | --- | --- |
| Nextcloud 33.0.9 | 8.2.32 | Fresh installation/migrations, seven OCC registrations, four background-job checks, integrity and app re-enable. |
| Nextcloud 34.0.4 | 8.2.32 | The same checks. |
| Nextcloud 35.0.0 | 8.4.23 for install; 8.3.32 for later CLI/browser checks | The same checks, plus saving a EUR 12.34 payment, overview/analytics totals and a 500 px mobile layout without horizontal overflow. |

The local servers used SQLite and Nextcloud's `CI=true` installation mode on
macOS. The new Ubuntu CI workflow had not run remotely at the time of the review.
The PHP suite recorded 134 tests and 3,019 assertions on PHP 8.2, 8.3 and 8.4;
static-security, frontend/token checks and the Node 24 build passed, with two
bundle-size warnings.

The 0.3.9 → 0.4.0 app update passed on all three servers, preserving the contents
of all 17 then-existing CoBudget tables. Job/integrity checks and the signed
archive's app signature, detached signature and checksum passed.

Not covered by that review: a populated Nextcloud 34 → 35 server upgrade,
complete shared-area/backup/receipt workflows, browser regressions on 33/34,
or PostgreSQL/MariaDB/MySQL integration.

## 2026-10-21 — CoBudget 0.6.0 feature review

The review compared the working tree with the local `v0.5.0` tag and prepared
0.6.0. The then-separate Annual overview and Statistics views have since been
replaced by Reports; their old UI instructions are intentionally not retained.

Recorded results:

- Annual, quarterly, monthly, weekly and daily views were opened in Nextcloud 35;
  the 2026 daily overview retained 365 day columns and the calendar-year total.
- Real controller/database tests passed on Nextcloud 34 and 35 for personal,
  solo/shared scopes, settlements, category hierarchy, filters and pagination.
- A missing shared personal recurrence forecast was fixed. The regression
  fixture counted two EUR 100.01 occurrences as EUR 40.01 personal shares each:
  EUR 80.02 personally and EUR 200.02 for the full area. Generating a saved
  occurrence retained both totals without double counting.
- The annual print document was generated with twelve months and totals; the
  maintainer separately confirmed daily-overview printing. Native PDF output was
  not saved during the automated review.
- Annual CSV generation was checked. A real Nextcloud Files export was not written.
- The mobile report layout was checked at 390 × 844, including daily-column
  scrolling without horizontal page overflow.
- PHP recorded 153 tests and 3,349 assertions; static-security/frontend checks
  and the production build passed with two bundle-size warnings. Both local
  instances updated to 0.6.0. Report integration passed with 174 assertions per
  server; Insurance integration passed with 16 per server. Fixtures were rolled back.

Nextcloud 35 reported missing core indexes `oc_job_classes_registry.class_index`
and `oc_federated_invites.UNIQ_A2C17D5A5F37A13B`; they were left for separate
server maintenance. These are historical observations, not a statement about
current server health.

That review did not run Nextcloud 33, other database engines, real Talk delivery
or a real Files export. Passing these earlier checks does not establish printing,
notifications or data integrity for later revisions.
