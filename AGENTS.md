---
uid: ec7a6a11-fa20-4aef-ae2c-01261e9d18ca
---
# CoBudget agent instructions

This is the central entry point for coding-assistant instructions in CoBudget.
Read [docs/architecture.md](docs/architecture.md) before changing app code; its
data, permission, recurrence, backup and migration rules are mandatory. Keep
technical invariants there and development/release instructions in this file.

## Documentation sources

- CoBudget design contract: [docs/design-system.md](docs/design-system.md). Read it before UI changes; it defines shared components, tokens, intentional variants and verification requirements.
- Local Codex skill: `/Users/wolfgang/.codex/skills/nextcloud-plugin/skills/nextcloud-development/SKILL.md`
- Official docs: `https://docs.nextcloud.com/server/latest/developer_manual/`
- Nextcloud design docs: `https://docs.nextcloud.com/server/latest/developer_manual/design/index.html`

Use the local Nextcloud skill when changing Nextcloud-specific PHP, routing, Vue integration, app packaging, or design-system behavior.

## UI and design changes

Before changing UI or adding a module, read [docs/design-system.md](docs/design-system.md).
Reuse its shared components and role tokens, preserve its documented CoBudget
variants, and follow its verification checklist. Record new shared rules or
intentional exceptions there in the same change; do not treat incidental local
CSS differences as approved variants.

## Public documentation

- Treat GitHub-facing files as part of every user-visible change:
  - `README.md`
  - `FEATURES.md`
  - `CHANGELOG.md`
  - `SECURITY.md`
  - `.github/` files, when present
  - `screenshots/` documentation assets, when screenshots are changed
- Update `CHANGELOG.md` for notable feature, behavior, security, data model, backup/restore, migration, release-packaging, or public documentation changes.
- Update `README.md` and `FEATURES.md` when functionality, terminology, requirements, warnings, screenshots, or user-facing workflows change.
- Keep the primary README and technical documentation in English. Maintain German, French and Spanish introductions in `docs/readme.de.md`, `docs/readme.fr.md` and `docs/readme.es.md`, with language links at the top of every README. Keep their features, requirements and warnings aligned; update localized App Store descriptions in `appinfo/info.xml` too.
- For the first stable 1.0 release, drop Nextcloud 33: set the minimum to 34 and update the CI matrix, requirements and support policy together. The 0.x beta series retains its existing 33–35 range.
- Keep installable release archives free of repository-only files such as `screenshots/`, `.github/`, tests, and development metadata.

## Default release handoff

The user prefers to run the shared publishing script personally. For future
release preparation, update the version metadata and changelog, complete the
required local checks, then give the user this command from the workspace root:

```sh
./scripts/create-signed-release.sh cobudget --appstore
```

Do not run the publishing script automatically. A request to prepare a release
or change its version means preparation and handoff by default. Only publish
when the user explicitly asks the assistant to perform that publication; prior
authorization for an earlier release does not authorize future releases.

## Version bumps

Without an explicitly requested release, do not bump the version for ordinary
PHP, Vue, CSS, documentation or build changes. Bump it when database, migration,
upgrade, app-metadata or bootstrap changes require Nextcloud to register the
update. Follow the immutable migration baseline in
[docs/architecture.md](docs/architecture.md#migration-baseline).

When the user asks to increase the CoBudget version without naming a target,
increase the current semantic version by one patch release.

Update all of these manual version sources:

1. `appinfo/info.xml`: the `<version>` value.
2. `package.json`: the root `"version"` value.
3. `package-lock.json`: both the top-level `"version"` value and
   `packages[""].version`.
4. `CHANGELOG.md`: add the new version as the first release section using
   `## [VERSION] - YYYY-MM-DD` and summarize the release changes.

Do not edit an embedded version in generated JavaScript by hand. Run
`npm run build` after updating the manual sources; the build writes the package
version into `js/cobudget-main.js` and refreshes the tracked frontend bundles.

After every version bump:

1. Run `npm test`.
2. Run `npm run build`.
3. Run `git diff --check`.
4. Search the repository for both the new and previous version, excluding
   `.git`, `node_modules` and `vendor`.
5. Confirm that the active new version occurs in `CHANGELOG.md`,
   `appinfo/info.xml`, `package.json`, both root locations in
   `package-lock.json`, and `js/cobudget-main.js`.
6. Confirm that the previous version remains only where it is historical,
   normally in `CHANGELOG.md`. Older version strings in screenshot filenames
   and URLs are intentional unless the screenshots are being replaced.

Do not create a commit, tag, release archive, signature, push or GitHub release
unless the user explicitly requests that additional release action.

## Build and packaging

Packaging is a separate, explicitly requested action. For the repository signing
helper, required artifacts, GNU tar format and key protection, follow
[RELEASING.md](RELEASING.md). For unsigned local packaging checks, use the
[development guide](docs/development.md#build-and-test). Do not create archives
for routine linked-instance testing.

The maintainer's shared publishing workflow is documented in `scripts/README.md`
at the **workspace root**, outside this app repository. Its versioned output
folders differ from the repository helper's parent-directory output. Follow the
default release handoff above; never infer publication authorization from a
request to prepare or test a release.

## Verification

Before handing off a build:

- Run `php -l` for changed PHP files.
- Run `npm run test:php` after PHP controller, route, workspace, money, template, project, or cron changes.
- Run `php tests/static-security.php` after security- or workspace-related changes.
- Run `npm run test:frontend-smoke` after Vue routing, bundle splitting, or deep-selector changes.
- Run `npm run build`.
- Repack `cobudget.tar.gz` with `npm run release` when preparing a user-test or store-style archive.
- Manually verify Workspaces, entries, projects, categories/payment partners, templates, and recurring payments in Nextcloud when possible.

For code changes in the multi-app workspace, run
`./scripts/update-local.sh cobudget` from the workspace root to build and update
the linked Nextcloud 34/35 instances; add `--test` to include app tests. Do not
start publication for local testing. For documentation-only changes, check links,
references and `git diff --check`; an app build or local update is not required.
