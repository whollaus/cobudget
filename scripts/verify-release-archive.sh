#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
WORKSPACE_DIR="$(cd "$APP_DIR/.." && pwd)"
ARCHIVE="${1:-$WORKSPACE_DIR/cobudget.tar.gz}"

if [ ! -s "$ARCHIVE" ]; then
	echo "Release archive not found or empty: $ARCHIVE" >&2
	exit 66
fi

LIST_FILE="$(mktemp "${TMPDIR:-/tmp}/cobudget-archive-list.XXXXXX")"
trap 'rm -f "$LIST_FILE"' EXIT

if ! tar -tzf "$ARCHIVE" > "$LIST_FILE"; then
	echo "Release archive cannot be read: $ARCHIVE" >&2
	exit 65
fi

if ! grep -qx 'cobudget/appinfo/info.xml' "$LIST_FILE"; then
	echo "Release archive is missing cobudget/appinfo/info.xml." >&2
	exit 65
fi

if ! grep -q '^cobudget/js/' "$LIST_FILE"; then
	echo "Release archive does not contain the built frontend assets." >&2
	exit 65
fi

if grep -Ev '^cobudget(/|$)' "$LIST_FILE" | grep -q .; then
	echo "Release archive contains files outside the cobudget directory." >&2
	exit 65
fi

if grep -Eq '(^|/)(screenshots|tests|\.github|node_modules|\.git|README\.md|FEATURES\.md)(/|$)' "$LIST_FILE"; then
	echo "Release archive contains repository-only files." >&2
	exit 65
fi

if grep -Eq '(^|/)(\._[^/]+|\.DS_Store)(/|$)' "$LIST_FILE"; then
	echo "Release archive contains macOS AppleDouble or Finder metadata." >&2
	exit 65
fi

echo "Release archive verified: $ARCHIVE"
