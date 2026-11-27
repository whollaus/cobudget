# Releasing CoBudget

Releases are handled by the maintainer. This document records the portable release
requirements and the signing helper included in this repository.

## Release checklist

- Update [CHANGELOG.md](CHANGELOG.md) and relevant public documentation.
- Match the version in `appinfo/info.xml`, `package.json`, both root version
  entries in `package-lock.json`, and the intended `vX.Y.Z` tag.
- Run `npm test`, `npm run build` and `git diff --check`; include the generated
  `js/` assets in the reviewed release commit on `main`.
- Run the fresh-install payment check below on the final archive.
- Complete relevant upgrade and browser checks on supported
  Nextcloud versions. See [tests/README.md](tests/README.md).
- Ensure the signed package corresponds to the release commit and tag; inspect
  the archive and its checks before publishing.

## Fresh installation of the release package

From the CoBudget repository, run the following command with the actual release archive.

```sh
python3 tests/integration/run-fresh-install.py /path/to/cobudget.tar.gz
```

The test reads the supported Nextcloud major versions from the archive. For each
major it needs a local server distribution at `../local-nextcloud/<major>/server`.
Use `--templates DIR` for another parent directory and `--php PATH` to select a
compatible PHP CLI with SQLite. Use Python 3.12 or later.

Each run creates a temporary Nextcloud configuration, data directory, SQLite
database and two accounts. It enables CoBudget from the supplied archive and
checks its internal signature when present. A regular user creates an expense,
an income and a shared payment through the app controllers. Another PHP process
reads all three payments and checks both members' shares and linked personal
entries. The test also checks background job registration, data integrity and
that Nextcloud has left maintenance mode with no pending database upgrade.

All supported versions must pass before publication. Keep the archive SHA-256
and the version-specific results with the release. Temporary instances are
removed on success and on test failure. Continue testing upgrades against
existing data, including an upgrade from the last published release.

## Signing and archive requirements

A public release needs two signatures:

1. Nextcloud's `occ integrity:sign-app` creates `appinfo/signature.json` inside
   the packaged app.
2. A detached SHA-512 signature covers the final `cobudget.tar.gz`; its Base64
   value is submitted to the Nextcloud App Store.

Use the Nextcloud certificate for CoBudget and the private key that matches its
certificate signing request. Protect and retain the key for future updates.
Never commit keys, certificates, CSRs or generated release artifacts.

The archive must contain a top-level `cobudget/` directory and only runtime files.
Exclude development dependencies, tests, screenshots and macOS metadata. Use
[scripts/tar-create.sh](scripts/tar-create.sh) for **GNU tar format**, including
long filenames: PAX path headers can cause Nextcloud to truncate JavaScript chunk
names during installation. The [archive verifier](scripts/verify-release-archive.sh)
rejects PAX archives.

Any modification after signing requires rebuilding and signing again. GitHub's
automatic source archives are not installable Nextcloud app packages.

## Repository signing helper

For a standalone checkout, install dependencies with `npm ci`, then run from the
repository root using an installed Nextcloud, its compatible PHP executable,
OpenSSL, and the matching CoBudget signing files:

```sh
PHP_BIN=/path/to/php npm run release:signed -- \
  /path/to/nextcloud/occ \
  /path/to/cobudget.key \
  /path/to/cobudget.crt
```

The helper runs tests, builds the frontend, signs a temporary app copy and checks
its runtime archive. Ensure `php` on `PATH` also supports the test suite;
`PHP_BIN` selects the executable used for `occ`.

It writes `cobudget.tar.gz`, `cobudget.tar.gz.signature` and `SHA256SUMS` to the
repository's parent directory, replacing files with those names. Keep the three
artifacts together. This helper prepares files locally; publication belongs to
the maintainer's release workflow.

## Release channels

The App Store derives its distribution channel from the version: a numeric
version such as `1.0.0` uses the stable channel, while `1.0.0-rc.1` is a
prerelease. GitHub’s prerelease flag does not select the App Store channel.

CoBudget 1.0.0 is published as a stable release on GitHub and in the App Store.
