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

## Recommended: Interactive Release Assistant

The release assistant reads the version from `appinfo/info.xml`, verifies it
against `package.json`, `package-lock.json`, and `CHANGELOG.md`, runs the test
and build pipeline, reviews Git changes, and asks separately before creating a
commit, tag, branch push, tag push, or GitHub asset upload:

```sh
npm run release:assistant
```

Useful modes:

```sh
# Read-only preflight
npm run release:assistant -- --check

# Show the planned flow without changing anything
npm run release:assistant -- --dry-run

# Include local Nextcloud signing
PHP_BIN=/usr/bin/php84 npm run release:assistant -- --sign \
  /absolute/path/to/nextcloud/occ \
  ~/.nextcloud/certificates/cobudget.key \
  ~/.nextcloud/certificates/cobudget.crt

# Resume after signing on another machine and copying the three artifacts back
npm run release:assistant -- --upload-only
```

The assistant is resumable from Git state. If the matching tag already points
to `HEAD`, it does not rebuild and overwrite an existing signed archive. It
never overwrites tags and deliberately leaves the final inspection and
publication of the GitHub draft as manual steps.

The commands below document the equivalent manual fallback.

## 1. Verify And Tag The Release

From the repository root:

```sh
VERSION="$(node -p "require('./package.json').version")"
TAG="v$VERSION"
npm ci
npm run test
git status
git add .
git commit -m "Prepare CoBudget $VERSION alpha release"
git push origin main
git tag -a "$TAG" -m "CoBudget $VERSION"
git push origin "$TAG"
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

Verify that the signed archive contains the required app files and no macOS metadata:

```sh
tar -tzf ../cobudget.tar.gz | grep '^cobudget/appinfo/signature.json$'
if tar -tzf ../cobudget.tar.gz | grep -E '(^|/)(\._[^/]+|\.DS_Store)(/|$)'; then
  echo "Release archive contains macOS metadata" >&2
  exit 1
fi
shasum -a 256 -c ../SHA256SUMS
```

## 3. Upload And Publish The Draft

Upload the locally signed artifacts:

```sh
VERSION="$(node -p "require('./package.json').version")"
TAG="v$VERSION"
gh release upload "$TAG" \
  ../cobudget.tar.gz \
  ../cobudget.tar.gz.signature \
  ../SHA256SUMS \
  --clobber
```

Inspect the draft on GitHub. Confirm that the installable archive contains the top-level `cobudget/` directory and `cobudget/appinfo/signature.json`. Then publish it as an alpha prerelease:

```sh
gh release edit "$TAG" --draft=false --prerelease
```

Alternatively, upload the three files and publish the draft through the GitHub web interface.

Do not install GitHub's automatically generated source archives. Only the attached, signed `cobudget.tar.gz` file is an installable Nextcloud app package.

## 4. Nextcloud App Store

For an App Store release, use the direct HTTPS URL of the signed GitHub release asset and paste the contents of `cobudget.tar.gz.signature` into the App Store signature field.

The downloadable archive URL follows this pattern:

```text
https://github.com/whollaus/cobudget/releases/download/vX.Y.Z/cobudget.tar.gz
```

Keep the private key permanently. Future updates must be signed with the same key and certificate identity.
