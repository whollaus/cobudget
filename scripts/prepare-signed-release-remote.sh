#!/usr/bin/env bash

set -euo pipefail

# Prevent macOS tar from adding AppleDouble metadata to release archives.
export COPYFILE_DISABLE=1

usage() {
	cat <<'EOF'
Usage:
  COBUDGET_ALLOW_REMOTE_KEY_COPY=1 prepare-signed-release-remote.sh <ssh-target> <remote-occ> <private-key> <certificate>

Example:
  COBUDGET_ALLOW_REMOTE_KEY_COPY=1 REMOTE_PHP_BIN=/usr/bin/php84 npm run release:signed-remote -- \
    v128703@v128703.kasserver.com \
    /www/htdocs/v128703/cloud.hollaus.it/occ \
    /Users/wolfgang/.nextcloud/certificates/cobudget.key \
    /Users/wolfgang/.nextcloud/certificates/cobudget.crt

Environment:
  COBUDGET_ALLOW_REMOTE_KEY_COPY  Must be set to 1 because the private key is copied to the remote host temporarily.
  REMOTE_PHP_BIN                  PHP executable on the remote host (default: php)
  REMOTE_TMP_BASE                 Temporary directory base on the remote host (default: /tmp)
EOF
}

if [ "$#" -ne 4 ]; then
	usage >&2
	exit 64
fi

if [ "${COBUDGET_ALLOW_REMOTE_KEY_COPY:-0}" != "1" ]; then
	cat >&2 <<'EOF'
Refusing to copy the private signing key to a remote host.

Set COBUDGET_ALLOW_REMOTE_KEY_COPY=1 only when you intentionally want to use the
controlled remote-signing flow. The key is uploaded to a temporary remote folder
and removed at the end, but it still leaves your local machine during signing.
EOF
	exit 64
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
WORKSPACE_DIR="$(cd "$APP_DIR/.." && pwd)"
SSH_TARGET="$1"
REMOTE_OCC_PATH="$2"
PRIVATE_KEY="$3"
CERTIFICATE="$4"
REMOTE_PHP_BIN="${REMOTE_PHP_BIN:-php}"
REMOTE_TMP_BASE="${REMOTE_TMP_BASE:-/tmp}"
ARCHIVE="$WORKSPACE_DIR/cobudget.tar.gz"
DETACHED_SIGNATURE="$ARCHIVE.signature"
CHECKSUM_FILE="$WORKSPACE_DIR/SHA256SUMS"
REMOTE_TMP=""

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

shell_quote() {
	printf '%q' "$1"
}

cleanup() {
	if [ -n "$REMOTE_TMP" ]; then
		ssh "$SSH_TARGET" "rm -rf $(shell_quote "$REMOTE_TMP")" >/dev/null 2>&1 || true
	fi
}
trap cleanup EXIT

require_file "$PRIVATE_KEY"
require_file "$CERTIFICATE"
require_command npm
require_command openssl
require_command tar
require_command ssh
require_command scp

LOCAL_TEMP_DIR="$(mktemp -d "${TMPDIR:-/tmp}/cobudget-remote-release.XXXXXX")"
trap 'rm -rf "$LOCAL_TEMP_DIR"; cleanup' EXIT

openssl x509 -in "$CERTIFICATE" -pubkey -noout > "$LOCAL_TEMP_DIR/certificate.pub"
openssl pkey -in "$PRIVATE_KEY" -pubout > "$LOCAL_TEMP_DIR/private-key.pub"
if ! cmp -s "$LOCAL_TEMP_DIR/certificate.pub" "$LOCAL_TEMP_DIR/private-key.pub"; then
	echo "The certificate does not match the supplied private key." >&2
	exit 65
fi

echo "Running tests..."
(cd "$APP_DIR" && npm run test)

echo "Building unsigned runtime archive..."
(cd "$APP_DIR" && npm run release)
require_file "$ARCHIVE"

REMOTE_TEMPLATE="$REMOTE_TMP_BASE/cobudget-release.XXXXXX"
REMOTE_TMP="$(ssh "$SSH_TARGET" "mktemp -d $(shell_quote "$REMOTE_TEMPLATE")")"

echo "Uploading unsigned archive and signing material to temporary remote folder..."
scp "$ARCHIVE" "$SSH_TARGET:$REMOTE_TMP/unsigned-cobudget.tar.gz" >/dev/null
scp "$PRIVATE_KEY" "$SSH_TARGET:$REMOTE_TMP/private.key" >/dev/null
scp "$CERTIFICATE" "$SSH_TARGET:$REMOTE_TMP/certificate.crt" >/dev/null

echo "Creating Nextcloud app signature on remote host..."
REMOTE_ENV="REMOTE_TMP=$(shell_quote "$REMOTE_TMP") REMOTE_OCC_PATH=$(shell_quote "$REMOTE_OCC_PATH") REMOTE_PHP_BIN=$(shell_quote "$REMOTE_PHP_BIN")"
ssh "$SSH_TARGET" "$REMOTE_ENV bash -s" <<'REMOTE_SCRIPT'
set -euo pipefail
export COPYFILE_DISABLE=1

require_file() {
	if [ ! -f "$1" ]; then
		echo "Required remote file not found: $1" >&2
		exit 66
	fi
}

require_command() {
	if ! command -v "$1" >/dev/null 2>&1; then
		echo "Required remote command not found: $1" >&2
		exit 69
	fi
}

require_file "$REMOTE_OCC_PATH"
require_file "$REMOTE_TMP/unsigned-cobudget.tar.gz"
require_file "$REMOTE_TMP/private.key"
require_file "$REMOTE_TMP/certificate.crt"
require_command tar
require_command openssl

if [ ! -x "$REMOTE_PHP_BIN" ] && ! command -v "$REMOTE_PHP_BIN" >/dev/null 2>&1; then
	echo "Required remote PHP executable not found: $REMOTE_PHP_BIN" >&2
	exit 69
fi

mkdir -p "$REMOTE_TMP/package"
tar -xzf "$REMOTE_TMP/unsigned-cobudget.tar.gz" -C "$REMOTE_TMP/package"
require_file "$REMOTE_TMP/package/cobudget/appinfo/info.xml"
find "$REMOTE_TMP/package/cobudget" -type f \( -name '._*' -o -name '.DS_Store' \) -delete
rm -f "$REMOTE_TMP/package/cobudget/appinfo/signature.json"

"$REMOTE_PHP_BIN" "$REMOTE_OCC_PATH" integrity:sign-app \
	--privateKey="$REMOTE_TMP/private.key" \
	--certificate="$REMOTE_TMP/certificate.crt" \
	--path="$REMOTE_TMP/package/cobudget"
require_file "$REMOTE_TMP/package/cobudget/appinfo/signature.json"

tar \
	--exclude='._*' \
	--exclude='*/._*' \
	--exclude='.DS_Store' \
	--exclude='*/.DS_Store' \
	-C "$REMOTE_TMP/package" \
	-czf "$REMOTE_TMP/cobudget.tar.gz" \
	cobudget

openssl dgst -sha512 -sign "$REMOTE_TMP/private.key" "$REMOTE_TMP/cobudget.tar.gz" \
	| openssl base64 -A > "$REMOTE_TMP/cobudget.tar.gz.signature"
printf '\n' >> "$REMOTE_TMP/cobudget.tar.gz.signature"
REMOTE_SCRIPT

echo "Downloading signed release files..."
scp "$SSH_TARGET:$REMOTE_TMP/cobudget.tar.gz" "$ARCHIVE" >/dev/null
scp "$SSH_TARGET:$REMOTE_TMP/cobudget.tar.gz.signature" "$DETACHED_SIGNATURE" >/dev/null

if command -v sha256sum >/dev/null 2>&1; then
	(cd "$WORKSPACE_DIR" && sha256sum "$(basename "$ARCHIVE")" > "$(basename "$CHECKSUM_FILE")")
else
	(cd "$WORKSPACE_DIR" && shasum -a 256 "$(basename "$ARCHIVE")" > "$(basename "$CHECKSUM_FILE")")
fi

"$SCRIPT_DIR/verify-release-archive.sh" "$ARCHIVE"
tar -tzf "$ARCHIVE" | grep -q '^cobudget/appinfo/signature.json$'

echo
echo "Signed release prepared:"
echo "  Archive:            $ARCHIVE"
echo "  Detached signature: $DETACHED_SIGNATURE"
echo "  Checksum:           $CHECKSUM_FILE"
echo
echo "Manual next steps:"
echo "  1. Upload the three files above to the matching GitHub release."
echo "  2. Copy the detached signature value, for example:"
echo "       pbcopy < $DETACHED_SIGNATURE"
echo "  3. Use the GitHub release download URL and the copied signature in the Nextcloud App Store release form."
