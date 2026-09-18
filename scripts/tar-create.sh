#!/usr/bin/env bash
set -euo pipefail
# Nextcloud's Archive_Tar ignores PAX path headers. GNU long-name records work
# with both GNU tar and Nextcloud, including lazy JS chunk names over 100 bytes.
export COPYFILE_DISABLE=1
case "$(tar --version)" in
	*bsdtar*) format=gnutar ;;
	*) format=gnu ;;
esac
exec tar --format="$format" "$@"
