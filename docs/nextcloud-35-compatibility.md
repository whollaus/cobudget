# Nextcloud 35 compatibility review

Reviewed on 2026-09-16. The supported server range is **Nextcloud 33–35**.

## Changes relevant to CoBudget

The [Nextcloud 35 critical changes](https://docs.nextcloud.com/server/35/developer_manual/release_notes/critical_changes.html) introduce these compatibility requirements:

| Change in Nextcloud 35 | CoBudget assessment and action |
| --- | --- |
| Apps must declare version 35 support | Raised `appinfo/info.xml` maximum to 35; minimum remains 33. Updated the metadata guard and documentation. |
| PHP minimum rises to 8.3 | Keep CoBudget compatible with PHP 8.2 on Nextcloud 33/34. Use PHP 8.3 or later, within Nextcloud's supported range, for 35. |
| Legacy JavaScript aliases and globally provided libraries are removed | Modernized the currently unused `fetchJson` helper to use `getRequestToken()` from `@nextcloud/auth`, declared as a direct dependency. Active requests already use `@nextcloud/axios` and its auth integration. No uses of the other removed globals were found in app sources. |
| Symfony Console 7 requires typed command return values | All seven CoBudget commands already declare `execute(...): int` and `configure(): void`. Keep the existing registration and Symfony command API for 33/34 compatibility; the new attribute-based command API is optional. |
| Schema APIs wrap Doctrine DBAL objects | All six migrations use `OCP\DB\ISchemaWrapper`. No Doctrine-typed table/column helpers, `Type::lookupName()`, `setOptions()` or `setType()` calls need conversion. Preserve the published migrations. |
| phpseclib 3 and removal of deprecated backend APIs | No affected phpseclib, Remote, preview-provider registration, Calendar resource/room, autocomplete-event or root-folder hook usage was found. CoBudget has no cloud-federation notification provider. |
| MariaDB/MySQL support and query behavior change | Server administrators need MariaDB 10.11+ or MySQL 8.4+ within the supported release list. CoBudget has no SQL `MD5()` or `CAST()`/alias grouping expressions of the affected form. New database releases still require runtime coverage. |

The [Hub 26 Summer announcement](https://nextcloud.com/blog/nextcloud-hub26-summer/) also introduces interface/search refinements, team workspaces and expanded automation. These do not require changes to CoBudget's own workspaces or shared areas. Teams ownership and Flow integration would be separate features.

PHP 8.2 remains available on [Nextcloud 33](https://docs.nextcloud.com/server/33/admin_manual/installation/system_requirements.html) and [34](https://docs.nextcloud.com/server/34/admin_manual/installation/system_requirements.html). CoBudget's Composer requirement does not need to be raised to 8.3: the installed Nextcloud release enforces its own runtime requirement.

## Validation

- The existing PHP, static-security and frontend suites cover CoBudget's contracts and behavior.
- `tests/fetch-json.mjs` runs the actual HTTP service with the installed Nextcloud auth/router libraries and simulated browser/network boundaries. It checks token lookup without private aliases, token rotation, explicit token overrides, workspace isolation and missing tokens.
- CI installs real Nextcloud 33/PHP 8.2, 34/PHP 8.2 and 35/PHP 8.3 instances using SQLite. It enables CoBudget to execute migrations, loads the OCC commands, checks background-job registration and data integrity, then disables/re-enables the app.
- The app metadata validates against Nextcloud 35's official `resources/app-info.xsd`.

Local results on 2026-09-16:

| Server | PHP | Result |
| --- | --- | --- |
| 33.0.9 | 8.2.32 | Fresh installation and migrations, all seven OCC registrations, four background-job checks, data integrity and app re-enable passed. |
| 34.0.4 | 8.2.32 | Same checks passed. |
| 35.0.0 | 8.4.23 for installation; 8.3.32 for subsequent CLI/browser checks | Same checks passed. In the browser, saved a EUR 12.34 payment, verified overview and analytics totals, and inspected the mobile layout at 500 CSS pixels without horizontal overflow. No browser console errors/warnings or CoBudget server errors appeared. |

The local instances use SQLite and Nextcloud's `CI=true` installation mode on macOS; they are disposable test instances. The new Ubuntu CI workflow has not yet run remotely. The PHP suite passes on PHP 8.2, 8.3 and 8.4 (134 tests, 3,019 assertions per run). Static-security, frontend and token checks pass. The production build passes on Node 24 with two bundle-size warnings.

Release validation for 0.4.0: the app update from 0.3.9 to 0.4.0 passed on all three local Nextcloud versions. All 17 CoBudget tables retained their contents, including the test payment on Nextcloud 35; background-job and data-integrity checks passed after each update. The signed release archive passed Nextcloud's app-integrity check, detached SHA-512 signature verification and the SHA-256 checksum check.

Remaining coverage: a populated 34→35 server upgrade, complete shared-area/backup/receipt workflows, browser regressions on 33/34, and PostgreSQL/MariaDB/MySQL integration tests. The completed smoke tests are not an exhaustive end-to-end compatibility certification.

## Release scope

These changes are prepared for **CoBudget 0.4.0**, supporting Nextcloud 33–35. No new database migration is required. Release preparation includes updating all version metadata, rebuilding the frontend and creating a signed package. Publishing the release to GitHub and the App Store is a separate step; existing App Store installations receive the compatibility update after publication.
