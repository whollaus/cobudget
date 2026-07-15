#!/usr/bin/env bash

set -Eeuo pipefail

# Prevent macOS tar from adding AppleDouble metadata to release archives.
export COPYFILE_DISABLE=1

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
WORKSPACE_DIR="$(cd "$APP_DIR/.." && pwd)"
ARCHIVE="$WORKSPACE_DIR/cobudget.tar.gz"
DETACHED_SIGNATURE="$ARCHIVE.signature"
CHECKSUM_FILE="$WORKSPACE_DIR/SHA256SUMS"

MODE="release"
DRY_RUN=0
SIGN_OCC=""
SIGN_KEY=""
SIGN_CERTIFICATE=""

SIGNED_READY=0
TAG_CREATED=0
BRANCH_PUSHED=0
TAG_PUSHED=0
ARTIFACTS_UPLOADED=0
REMOTE_TAG_EXISTS=0

usage() {
	printf '%s\n' \
		'CoBudget release assistant' \
		'' \
		'Usage:' \
		'  npm run release:assistant' \
		'  npm run release:assistant -- --check' \
		'  npm run release:assistant -- --dry-run' \
		'  npm run release:assistant -- --sign <nextcloud-occ> <private-key> <certificate>' \
		'  npm run release:assistant -- --upload-only' \
		'' \
		'Options:' \
		'  --check        Check versions, Git state, tag state and existing artifacts.' \
		'  --dry-run      Show the planned release steps without changing anything.' \
		'  --sign         Build and sign with the existing Nextcloud release helper.' \
		'  --upload-only  Upload already signed artifacts to the existing draft release.' \
		'  -h, --help     Show this help.' \
		'' \
		'Environment:' \
		'  PHP_BIN        PHP executable used by occ while signing (default: php).' \
		'' \
		'The assistant never overwrites an existing tag and never publishes a GitHub' \
		'draft automatically. Every commit, tag, push and artifact upload requires an' \
		'explicit confirmation.'
}

fail() {
	printf '\n[FEHLER] %s\n' "$*" >&2
	exit 1
}

info() {
	printf '[INFO] %s\n' "$*"
}

ok() {
	printf '[OK] %s\n' "$*"
}

warn() {
	printf '[WARNUNG] %s\n' "$*" >&2
}

require_command() {
	command -v "$1" >/dev/null 2>&1 || fail "Erforderlicher Befehl nicht gefunden: $1"
}

confirm() {
	local question="$1"
	local default_answer="${2:-no}"
	local hint="[j/N]"
	local answer=""

	if [ "$default_answer" = "yes" ]; then
		hint="[J/n]"
	fi

	if [ ! -t 0 ]; then
		fail "Interaktive Bestätigung erforderlich: $question (für CI bitte --check verwenden)"
	fi

	printf '\n%s\nAntwort %s: ' "$question" "$hint" >&2
	IFS= read -r answer
	if [ -z "$answer" ]; then
		[ "$default_answer" = "yes" ]
		return
	fi

	case "$answer" in
		j|J|ja|JA|Ja|y|Y|yes|YES|Yes) return 0 ;;
		*) return 1 ;;
	esac
}

read_with_default() {
	local question="$1"
	local default_value="$2"
	local answer=""

	printf '\n%s\nVorgabe [%s]: ' "$question" "$default_value" >&2
	IFS= read -r answer
	if [ -z "$answer" ]; then
		printf '%s' "$default_value"
	else
		printf '%s' "$answer"
	fi
}

extract_info_version() {
	sed -n 's#.*<version>[[:space:]]*\([^<]*\)[[:space:]]*</version>.*#\1#p' "$APP_DIR/appinfo/info.xml" | head -n 1
}

local_tag_commit() {
	git rev-list -n 1 "$1" 2>/dev/null || true
}

remote_tag_commit() {
	local tag="$1"
	local output=""
	local peeled=""
	local direct=""
	local ssh_command="${GIT_SSH_COMMAND:-ssh -o BatchMode=yes -o ConnectTimeout=5 -o ConnectionAttempts=1}"

	if [[ "$REMOTE_URL" == git@* || "$REMOTE_URL" == ssh://* ]]; then
		if ! output="$(GIT_TERMINAL_PROMPT=0 GIT_SSH_COMMAND="$ssh_command" git ls-remote --tags origin "refs/tags/$tag" "refs/tags/$tag^{}" 2>/dev/null)"; then
			return 2
		fi
	elif ! output="$(GIT_TERMINAL_PROMPT=0 git -c http.lowSpeedLimit=1 -c http.lowSpeedTime=10 ls-remote --tags origin "refs/tags/$tag" "refs/tags/$tag^{}" 2>/dev/null)"; then
		return 2
	fi

	peeled="$(printf '%s\n' "$output" | awk '$2 ~ /\^\{\}$/ { print $1; exit }')"
	direct="$(printf '%s\n' "$output" | awk '$2 !~ /\^\{\}$/ { print $1; exit }')"
	if [ -n "$peeled" ]; then
		printf '%s' "$peeled"
	else
		printf '%s' "$direct"
	fi
}

github_repo_slug() {
	local remote_url="$1"
	printf '%s' "$remote_url" \
		| sed -E 's#^git@github\.com:##; s#^https://github\.com/##; s#\.git$##'
}

verify_archive() {
	local require_signature="$1"
	local archive_list=""
	local archive_xml=""
	local archive_version=""

	[ -s "$ARCHIVE" ] || return 1
	"$SCRIPT_DIR/verify-release-archive.sh" "$ARCHIVE" >/dev/null || return 1
	archive_list="$(tar -tzf "$ARCHIVE")" || return 1

	archive_xml="$(tar -xOf "$ARCHIVE" cobudget/appinfo/info.xml)" || return 1
	archive_version="$(printf '%s\n' "$archive_xml" | sed -n 's#.*<version>[[:space:]]*\([^<]*\)[[:space:]]*</version>.*#\1#p' | head -n 1)"
	[ "$archive_version" = "$VERSION" ] || return 1

	if [ "$require_signature" -eq 1 ]; then
		grep -qx 'cobudget/appinfo/signature.json' <<< "$archive_list" || return 1
		[ -s "$DETACHED_SIGNATURE" ] || return 1
		[ -s "$CHECKSUM_FILE" ] || return 1
		if command -v sha256sum >/dev/null 2>&1; then
			(cd "$WORKSPACE_DIR" && sha256sum -c "$(basename "$CHECKSUM_FILE")" >/dev/null) || return 1
		else
			(cd "$WORKSPACE_DIR" && shasum -a 256 -c "$(basename "$CHECKSUM_FILE")" >/dev/null) || return 1
		fi
	fi

	return 0
}

verify_signed_artifact_metadata() {
	local archive_xml=""
	local archive_version=""

	[ -s "$ARCHIVE" ] || return 1
	[ -s "$DETACHED_SIGNATURE" ] || return 1
	[ -s "$CHECKSUM_FILE" ] || return 1

	archive_xml="$(tar -xOf "$ARCHIVE" cobudget/appinfo/info.xml 2>/dev/null)" || return 1
	archive_version="$(printf '%s\n' "$archive_xml" | sed -n 's#.*<version>[[:space:]]*\([^<]*\)[[:space:]]*</version>.*#\1#p' | head -n 1)"
	[ "$archive_version" = "$VERSION" ] || return 1

	if command -v sha256sum >/dev/null 2>&1; then
		(cd "$WORKSPACE_DIR" && sha256sum -c "$(basename "$CHECKSUM_FILE")" >/dev/null 2>&1) || return 1
	else
		(cd "$WORKSPACE_DIR" && shasum -a 256 -c "$(basename "$CHECKSUM_FILE")" >/dev/null 2>&1) || return 1
	fi

	return 0
}

refresh_remote_tag_state() {
	local remote_commit=""
	REMOTE_TAG_EXISTS=0
	if remote_commit="$(remote_tag_commit "$TAG")"; then
		if [ -n "$remote_commit" ]; then
			REMOTE_TAG_EXISTS=1
			if [ "$remote_commit" != "$HEAD_COMMIT" ]; then
				fail "Remote-Tag $TAG zeigt auf $remote_commit, HEAD aber auf $HEAD_COMMIT. Tags werden nicht überschrieben."
			fi
		fi
	else
		warn "Remote-Tags konnten derzeit nicht geprüft werden."
	fi
}

wait_for_draft_release() {
	local attempt=1
	while [ "$attempt" -le 12 ]; do
		if gh release view "$TAG" >/dev/null 2>&1; then
			return 0
		fi
		if [ "$attempt" -eq 1 ]; then
			info "Warte auf den von GitHub Actions erzeugten Draft-Release ..."
		fi
		sleep 10
		attempt=$((attempt + 1))
	done
	return 1
}

print_summary() {
	local release_url=""

	printf '\n============================================================\n'
	printf 'CoBudget Release-Zusammenfassung\n'
	printf '============================================================\n'
	printf 'Version:       %s\n' "$VERSION"
	printf 'Tag:           %s\n' "$TAG"
	printf 'Branch:        %s\n' "$BRANCH"
	printf 'Commit:        %s\n' "$HEAD_COMMIT"
	printf 'Signiert:      %s\n' "$([ "$SIGNED_READY" -eq 1 ] && printf 'ja' || printf 'nein')"
	printf 'Branch-Push:   %s\n' "$([ "$BRANCH_PUSHED" -eq 1 ] && printf 'erledigt' || printf 'offen')"
	printf 'Tag-Push:      %s\n' "$([ "$TAG_PUSHED" -eq 1 ] && printf 'erledigt' || printf 'offen')"
	printf 'GitHub-Assets: %s\n' "$([ "$ARTIFACTS_UPLOADED" -eq 1 ] && printf 'hochgeladen' || printf 'offen')"

	if [ -n "$REPO_SLUG" ]; then
		release_url="https://github.com/$REPO_SLUG/releases/tag/$TAG"
		printf '\nGitHub-Release: %s\n' "$release_url"
	fi

	printf '\nDanach noch manuell prüfen/erledigen:\n'
	if [ "$BRANCH_PUSHED" -ne 1 ]; then
		printf '  [ ] Branch pushen: git push origin %s\n' "$BRANCH"
	fi
	if [ "$TAG_CREATED" -ne 1 ]; then
		printf '  [ ] Tag erstellen: git tag -a %s -m "CoBudget %s"\n' "$TAG" "$VERSION"
	fi
	if [ "$TAG_PUSHED" -ne 1 ]; then
		printf '  [ ] Tag pushen: git push origin %s\n' "$TAG"
	fi
	if [ "$SIGNED_READY" -ne 1 ]; then
		printf '  [ ] Exakt diesen Tag mit Nextcloud-Zertifikat und privatem Schlüssel signieren.\n'
		printf '      Lokal möglich mit:\n'
		printf '      PHP_BIN=/pfad/zu/php npm run release:assistant -- --sign /pfad/zu/occ /pfad/zu/cobudget.key /pfad/zu/cobudget.crt\n'
	fi
	if [ "$SIGNED_READY" -eq 1 ] && [ "$ARTIFACTS_UPLOADED" -ne 1 ]; then
		printf '  [ ] cobudget.tar.gz, cobudget.tar.gz.signature und SHA256SUMS in den Draft hochladen.\n'
		printf '      Danach: npm run release:assistant -- --upload-only\n'
	fi
	printf '  [ ] GitHub Actions (CI und Release) vollständig grün prüfen.\n'
	printf '  [ ] Draft-Text und drei signierte Assets kontrollieren.\n'
	printf '  [ ] Draft erst danach als Alpha-Prerelease veröffentlichen.\n'
	printf '  [ ] Für den App Store Asset-URL und Inhalt von cobudget.tar.gz.signature verwenden.\n'
}

while [ "$#" -gt 0 ]; do
	case "$1" in
		--check)
			MODE="check"
			shift
			;;
		--dry-run)
			DRY_RUN=1
			shift
			;;
		--upload-only)
			MODE="upload"
			shift
			;;
		--sign)
			[ "$#" -ge 4 ] || fail "--sign benötigt <nextcloud-occ> <private-key> <certificate>."
			SIGN_OCC="$2"
			SIGN_KEY="$3"
			SIGN_CERTIFICATE="$4"
			shift 4
			;;
		-h|--help)
			usage
			exit 0
			;;
		*)
			usage >&2
			fail "Unbekannte Option: $1"
			;;
	esac
done

require_command git
require_command node
require_command npm
require_command tar

cd "$APP_DIR"
git rev-parse --is-inside-work-tree >/dev/null 2>&1 || fail "Kein Git-Repository: $APP_DIR"

VERSION="$(extract_info_version)"
PACKAGE_VERSION="$(node -p "require('./package.json').version")"
LOCK_VERSION="$(node -p "require('./package-lock.json').version")"
LOCK_ROOT_VERSION="$(node -p "require('./package-lock.json').packages[''].version")"

[ -n "$VERSION" ] || fail "Version konnte nicht aus appinfo/info.xml gelesen werden."
[ "$VERSION" = "$PACKAGE_VERSION" ] || fail "Versionskonflikt: info.xml=$VERSION, package.json=$PACKAGE_VERSION"
[ "$VERSION" = "$LOCK_VERSION" ] || fail "Versionskonflikt: info.xml=$VERSION, package-lock.json=$LOCK_VERSION"
[ "$VERSION" = "$LOCK_ROOT_VERSION" ] || fail "Versionskonflikt im Root-Paket des package-lock.json: $LOCK_ROOT_VERSION"
grep -Fq "## [$VERSION]" "$APP_DIR/CHANGELOG.md" || fail "CHANGELOG.md enthält keinen Abschnitt für $VERSION."

TAG="v$VERSION"
BRANCH="$(git branch --show-current)"
HEAD_COMMIT="$(git rev-parse HEAD)"
REMOTE_URL="$(git remote get-url origin 2>/dev/null || true)"
REPO_SLUG="$(github_repo_slug "$REMOTE_URL")"

[ -n "$BRANCH" ] || fail "Detached HEAD wird für Releases nicht unterstützt."
[ "$BRANCH" = "main" ] || fail "Releases müssen vom Branch main erstellt werden (aktuell: $BRANCH)."
[ -n "$REMOTE_URL" ] || fail "Git-Remote origin fehlt."

LOCAL_TAG_COMMIT="$(local_tag_commit "$TAG")"
if [ -n "$LOCAL_TAG_COMMIT" ] && [ "$LOCAL_TAG_COMMIT" != "$HEAD_COMMIT" ]; then
	fail "Version $VERSION ist bereits durch $TAG am Commit $LOCAL_TAG_COMMIT vergeben. HEAD ist $HEAD_COMMIT. Bitte die App-Version erhöhen und den Changelog ergänzen; bestehende Tags bleiben unverändert."
fi
if [ -n "$LOCAL_TAG_COMMIT" ]; then
	TAG_CREATED=1
fi

refresh_remote_tag_state
if [ "$REMOTE_TAG_EXISTS" -eq 1 ]; then
	TAG_PUSHED=1
fi

if [ "$MODE" = "check" ]; then
	if verify_signed_artifact_metadata; then
		SIGNED_READY=1
	fi
elif [ "$DRY_RUN" -eq 0 ] && verify_archive 1; then
	SIGNED_READY=1
fi

printf '\nCoBudget Release-Assistent\n'
printf '  Version: %s\n' "$VERSION"
printf '  Tag:     %s\n' "$TAG"
printf '  Branch:  %s\n' "$BRANCH"
printf '  Commit:  %s\n' "$HEAD_COMMIT"
printf '  Remote:  %s\n\n' "$REMOTE_URL"
info "Der Assistent ist interaktiv. Bei Rückfragen mit j/ja, n/nein oder Enter für die Vorgabe antworten."

if [ "$MODE" = "check" ]; then
	git status --short --branch
	[ "$TAG_CREATED" -eq 1 ] && ok "Lokaler Tag zeigt auf HEAD." || info "Lokaler Tag ist noch nicht erstellt."
	[ "$TAG_PUSHED" -eq 1 ] && ok "Remote-Tag zeigt auf HEAD." || info "Remote-Tag ist noch nicht vorhanden oder konnte nicht geprüft werden."
	[ "$SIGNED_READY" -eq 1 ] && ok "Signierte Artefakte sind vollständig und konsistent." || info "Keine gültigen signierten Artefakte für $VERSION gefunden."
	exit 0
fi

if [ "$DRY_RUN" -eq 1 ]; then
	printf '%s\n' \
		'Geplanter Ablauf (ohne Änderungen):' \
		'  1. npm ci, Tests, Frontend-Build und Laufzeitarchiv prüfen' \
		'  2. Git-Änderungen anzeigen und nach Bestätigung committen' \
		"  3. annotierten Tag $TAG nach Bestätigung erstellen" \
		'  4. optional mit Nextcloud-Zertifikat signieren' \
		"  5. main und $TAG jeweils nach Bestätigung pushen" \
		'  6. signierte Artefakte nach Bestätigung in den GitHub-Draft hochladen' \
		'  7. verbleibende manuelle Prüfungen ausgeben'
	exit 0
fi

if [ "$MODE" = "upload" ]; then
	[ "$TAG_CREATED" -eq 1 ] || fail "Lokaler Tag $TAG fehlt."
	[ "$TAG_PUSHED" -eq 1 ] || fail "Remote-Tag $TAG fehlt."
	[ -z "$(git status --porcelain)" ] || fail "Das Repository ist nicht sauber."
	verify_archive 1 || fail "Signierte Artefakte fehlen, haben eine andere Version oder sind inkonsistent."
	SIGNED_READY=1
else
	if [ "$TAG_CREATED" -eq 1 ]; then
		[ -z "$(git status --porcelain)" ] || fail "Tag $TAG existiert bereits, aber das Repository ist nicht sauber. Erst Änderungen committen oder verwerfen."
		info "Tag $TAG zeigt bereits auf HEAD. Der Assistent setzt den Release fort, ohne das signierte Archiv zu überschreiben."
	else
		[ ! -e "$APP_DIR/appinfo/signature.json" ] || fail "appinfo/signature.json liegt im Quellbaum. Bitte die lokale Signaturdatei vor dem Build entfernen."

		if confirm "Abhängigkeiten reproduzierbar mit npm ci installieren?" yes; then
			npm ci
		else
			warn "npm ci wurde übersprungen."
		fi

		info "Führe Tests aus ..."
		npm run test
		info "Baue das Frontend ..."
		npm run build
		info "Erzeuge und prüfe das unsignierte Laufzeitarchiv ..."
		npm run release:tar
		verify_archive 0 || fail "Das Laufzeitarchiv ist unvollständig, verschmutzt oder hat die falsche Version."
		git diff --check

		if [ -n "$(git status --porcelain)" ]; then
			printf '\nÄnderungen für den Release:\n'
			git status --short
			git diff --stat
			git diff --cached --stat
			if ! confirm "Alle oben gezeigten Änderungen mit git add -A für $TAG übernehmen?" no; then
				fail "Release vor Commit abgebrochen. Es wurde nichts gestaged oder gepusht."
			fi
			git add -A
			printf '\nVorgemerkter Commit:\n'
			git diff --cached --stat
			if ! confirm "Diesen Commit jetzt erstellen?" yes; then
				fail "Release vor Commit abgebrochen. Die Dateien bleiben gestaged."
			fi
			COMMIT_MESSAGE="$(read_with_default "Commit-Nachricht" "Prepare CoBudget $VERSION release")"
			git commit -m "$COMMIT_MESSAGE"
		else
			ok "Keine uncommitteten Änderungen vorhanden."
		fi

		[ -z "$(git status --porcelain)" ] || fail "Das Repository ist nach dem Commit nicht sauber."
		HEAD_COMMIT="$(git rev-parse HEAD)"

		if confirm "Annotierten Tag $TAG am aktuellen Commit erstellen?" yes; then
			git tag -a "$TAG" -m "CoBudget $VERSION"
			TAG_CREATED=1
		else
			fail "Ohne Tag wird kein Release gepusht."
		fi
	fi

	if [ -n "$SIGN_OCC" ]; then
		if confirm "Release jetzt mit Nextcloud-Zertifikat signieren?" yes; then
			"$SCRIPT_DIR/prepare-signed-release.sh" "$SIGN_OCC" "$SIGN_KEY" "$SIGN_CERTIFICATE"
			verify_archive 1 || fail "Die erzeugten signierten Artefakte konnten nicht verifiziert werden."
			SIGNED_READY=1
			[ -z "$(git status --porcelain)" ] || fail "Der Signatur-Build hat den Git-Arbeitsbaum verändert. Nicht pushen; Änderungen zuerst prüfen."
		fi
	elif [ "$SIGNED_READY" -ne 1 ]; then
		info "Keine lokalen Signaturpfade angegeben. Signieren bleibt als manueller Schritt offen."
	fi

	if confirm "Branch $BRANCH jetzt zu origin pushen?" yes; then
		git push origin "$BRANCH"
		BRANCH_PUSHED=1
	fi

	refresh_remote_tag_state
	if [ "$REMOTE_TAG_EXISTS" -eq 1 ]; then
		TAG_PUSHED=1
	elif confirm "Tag $TAG jetzt pushen und den GitHub-Draft anstoßen?" yes; then
		git push origin "$TAG"
		TAG_PUSHED=1
	fi
fi

if [ "$SIGNED_READY" -eq 1 ] && [ "$TAG_PUSHED" -eq 1 ]; then
	if command -v gh >/dev/null 2>&1 && gh auth status >/dev/null 2>&1; then
		if confirm "Die drei signierten Artefakte in den GitHub-Draft hochladen?" yes; then
			if wait_for_draft_release; then
				gh release upload "$TAG" \
					"$ARCHIVE" \
					"$DETACHED_SIGNATURE" \
					"$CHECKSUM_FILE" \
					--clobber
				ARTIFACTS_UPLOADED=1
			else
				warn "Der GitHub-Draft wurde nicht rechtzeitig gefunden. Bitte nach Abschluss des Release-Workflows erneut mit --upload-only starten."
			fi
		fi
	else
		warn "GitHub CLI fehlt oder ist nicht angemeldet. Der Upload bleibt manuell offen."
	fi
fi

print_summary
