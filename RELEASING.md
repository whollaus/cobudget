# Releasing CoBudget

Releases are handled by the maintainer. This document records the portable release
requirements and the signing helper included in this repository.

In the maintainer's multi-app workspace, **`scripts/README.md` at the workspace
root** is the authoritative guide for the shared release tool, credentials,
GitHub/App Store publication and recovery. Those private tools are not distributed
with this repository. Keep their operational instructions there.

## Release checklist

- Update [CHANGELOG.md](CHANGELOG.md) and relevant public documentation.
- Match the version in `appinfo/info.xml`, `package.json`, both root version
  entries in `package-lock.json`, and the intended `vX.Y.Z` tag.
- Run `npm test`, `npm run build` and `git diff --check`; include the generated
  `js/` assets in the reviewed release commit on `main`.
- Complete relevant installation, upgrade and browser checks on supported
  Nextcloud versions. See [tests/README.md](tests/README.md).
- Ensure the signed package corresponds to the release commit and tag; inspect
  the archive and its checks before publishing.

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

## Release channels and beta status

The App Store derives its distribution channel from the version: a numeric
version such as `0.8.5` uses the stable channel, while `0.8.5-beta.1` is a
prerelease. GitHub's prerelease flag does not select the App Store channel.

This is separate from product maturity: **CoBudget 0.8.5 remains beta**, and
1.0.0 is intended to be the first stable product release. Keep release notes and
app descriptions consistent with that status.
