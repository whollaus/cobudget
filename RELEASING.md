# Releasing CoBudget

CoBudget uses a two-stage Nextcloud signing process:

1. `occ integrity:sign-app` creates `appinfo/signature.json` inside the installable app archive.
2. OpenSSL creates a detached SHA-512 signature for the final `cobudget.tar.gz` archive. This value is required by the Nextcloud App Store.

The private key must be the exact key used to create the certificate signing request for `cobudget.crt`. Never commit the key, certificate, CSR, generated signature, or release archive to this repository.

## Prerequisites

- A clean release commit on `main`
- Matching versions in `appinfo/info.xml`, `package.json`, `package-lock.json`, and the Git tag
- The Nextcloud certificate `cobudget.crt`
- The matching private key used for the CSR
- Access to a Nextcloud installation whose `occ` command can run `integrity:sign-app`
- An authenticated GitHub CLI (`gh`) or access to the GitHub release page

A recommended local location is:

```sh
~/.nextcloud/certificates/cobudget.crt
~/.nextcloud/certificates/cobudget.key
```

Protect the private key:

```sh
chmod 600 ~/.nextcloud/certificates/cobudget.key
```

## 1. Verify And Tag The Release

From the repository root:

```sh
npm ci
npm run test
git status
git add .
git commit -m "Prepare CoBudget 0.2.0 alpha release"
git push origin main
git tag -a v0.2.0 -m "CoBudget 0.2.0"
git push origin v0.2.0
```

The tag workflow verifies the version, tests the app, checks the runtime package, and creates a **draft prerelease without an installable archive**. This prevents an unsigned package from being published accidentally.

## 2. Build And Sign Locally

Run the release helper with the path to `occ`, the private key, and the certificate:

```sh
PHP_BIN=/usr/bin/php84 npm run release:signed -- \
  /absolute/path/to/nextcloud/occ \
  ~/.nextcloud/certificates/cobudget.key \
  ~/.nextcloud/certificates/cobudget.crt
```

Use the correct PHP binary for the target Nextcloud installation. The command runs all tests, builds the frontend, creates an unsigned runtime package in a temporary directory, signs that package, and writes these files next to the `cobudget/` repository folder:

- `cobudget.tar.gz`
- `cobudget.tar.gz.signature`
- `SHA256SUMS`

Do not modify the archive after signing it. Any change requires rebuilding and signing again.

## 3. Upload And Publish The Draft

Upload the locally signed artifacts:

```sh
gh release upload v0.2.0 \
  ../cobudget.tar.gz \
  ../cobudget.tar.gz.signature \
  ../SHA256SUMS \
  --clobber
```

Inspect the draft on GitHub. Confirm that the installable archive contains the top-level `cobudget/` directory and `cobudget/appinfo/signature.json`. Then publish it as an alpha prerelease:

```sh
gh release edit v0.2.0 --draft=false --prerelease
```

Alternatively, upload the three files and publish the draft through the GitHub web interface.

Do not install GitHub's automatically generated source archives. Only the attached, signed `cobudget.tar.gz` file is an installable Nextcloud app package.

## 4. Nextcloud App Store

For an App Store release, use the direct HTTPS URL of the signed GitHub release asset and paste the contents of `cobudget.tar.gz.signature` into the App Store signature field.

The downloadable archive URL follows this pattern:

```text
https://github.com/whollaus/cobudget/releases/download/v0.2.0/cobudget.tar.gz
```

Keep the private key permanently. Future updates must be signed with the same key and certificate identity.
