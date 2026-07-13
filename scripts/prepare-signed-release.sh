#!/usr/bin/env bash

set -euo pipefail

# Prevent macOS tar from adding AppleDouble metadata to release archives.
export COPYFILE_DISABLE=1

usage() {
	cat <<'EOF'
Usage:
  prepare-signed-release.sh <nextcloud-occ> <private-key> <certificate>

Environment:
  PHP_BIN  PHP executable used to run occ (default: php)
EOF
}

if [ "$#" -ne 3 ]; then
	usage >&2
	exit 64
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
WORKSPACE_DIR="$(cd "$APP_DIR/.." && pwd)"
OCC_PATH="$1"
PRIVATE_KEY="$2"
CERTIFICATE="$3"
PHP_BIN="${PHP_BIN:-php}"
ARCHIVE="$WORKSPACE_DIR/cobudget.tar.gz"
DETACHED_SIGNATURE="$ARCHIVE.signature"
CHECKSUM_FILE="$WORKSPACE_DIR/SHA256SUMS"

require_file() {
	if [ ! -f "$1" ]; then
		echo "Required file not found: $1" >&2
		exit 66
	fi
}

require_command() {
	if ! command -v "$1" >/dev/null 2>&1; then
		echo "Required command not found: $1" >&2
		exit 69
	fi
}

require_file "$OCC_PATH"
require_file "$PRIVATE_KEY"
require_file "$CERTIFICATE"
require_command "$PHP_BIN"
require_command npm
require_command openssl
require_command tar

TEMP_DIR="$(mktemp -d "${TMPDIR:-/tmp}/cobudget-release.XXXXXX")"
trap 'rm -rf "$TEMP_DIR"' EXIT

openssl x509 -in "$CERTIFICATE" -pubkey -noout > "$TEMP_DIR/certificate.pub"
openssl pkey -in "$PRIVATE_KEY" -pubout > "$TEMP_DIR/private-key.pub"
if ! cmp -s "$TEMP_DIR/certificate.pub" "$TEMP_DIR/private-key.pub"; then
	echo "The certificate does not match the supplied private key." >&2
	exit 65
fi

echo "Running tests..."
(cd "$APP_DIR" && npm run test)

echo "Building unsigned runtime archive..."
(cd "$APP_DIR" && npm run release)
require_file "$ARCHIVE"

mkdir -p "$TEMP_DIR/package"
tar -xzf "$ARCHIVE" -C "$TEMP_DIR/package"
require_file "$TEMP_DIR/package/cobudget/appinfo/info.xml"
rm -f "$TEMP_DIR/package/cobudget/appinfo/signature.json"

echo "Creating Nextcloud app signature..."
"$PHP_BIN" "$OCC_PATH" integrity:sign-app \
	--privateKey="$PRIVATE_KEY" \
	--certificate="$CERTIFICATE" \
	--path="$TEMP_DIR/package/cobudget"
require_file "$TEMP_DIR/package/cobudget/appinfo/signature.json"

echo "Packing signed release archive..."
tar \
	--exclude='._*' \
	--exclude='*/._*' \
	--exclude='.DS_Store' \
	--exclude='*/.DS_Store' \
	-C "$TEMP_DIR/package" \
	-czf "$ARCHIVE" \
	cobudget

echo "Creating detached App Store signature..."
openssl dgst -sha512 -sign "$PRIVATE_KEY" "$ARCHIVE" \
	| openssl base64 -A > "$DETACHED_SIGNATURE"
printf '\n' >> "$DETACHED_SIGNATURE"

if command -v sha256sum >/dev/null 2>&1; then
	(cd "$WORKSPACE_DIR" && sha256sum "$(basename "$ARCHIVE")" > "$(basename "$CHECKSUM_FILE")")
else
	(cd "$WORKSPACE_DIR" && shasum -a 256 "$(basename "$ARCHIVE")" > "$(basename "$CHECKSUM_FILE")")
fi

tar -tzf "$ARCHIVE" | grep -q '^cobudget/appinfo/info.xml$'
tar -tzf "$ARCHIVE" | grep -q '^cobudget/appinfo/signature.json$'
tar -tzf "$ARCHIVE" | grep -q '^cobudget/js/'
if tar -tzf "$ARCHIVE" | grep -Eq '(^|/)(screenshots|tests|\.github|node_modules|\.git|README\.md|FEATURES\.md)(/|$)'; then
	echo "Release archive contains repository-only files." >&2
	exit 65
fi
if tar -tzf "$ARCHIVE" | grep -Eq '(^|/)(\._[^/]+|\.DS_Store)(/|$)'; then
	echo "Release archive contains macOS metadata files." >&2
	exit 65
fi

echo
echo "Signed release prepared:"
echo "  Archive:            $ARCHIVE"
echo "  Detached signature: $DETACHED_SIGNATURE"
echo "  Checksum:           $CHECKSUM_FILE"
echo
echo "The detached signature file contains the value required by the Nextcloud App Store."
