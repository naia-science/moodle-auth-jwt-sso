#!/usr/bin/env bash
#
# Packages this plugin as a Moodle-installable zip.
#
# Moodle expects a single top-level folder named after the plugin's install
# directory (auth/firebase -> "firebase"), containing the plugin files. This
# script stages exactly the git-tracked plugin files under that folder and
# zips them, so the throwaway dev harness (dev/, docker-compose.yml - both
# gitignored) and repo-only files never end up in the release.
#
# Current working-tree content is packaged (git ls-files lists the tracked
# paths; the files copied are whatever is on disk now), so you do NOT need to
# commit first.
#
#   ./package.sh            -> firebase.zip in the repo root
#
set -euo pipefail

cd "$(dirname "$0")"

PLUGINDIR="firebase"   # Moodle install dir for component auth_firebase
OUT="firebase.zip"

command -v zip >/dev/null 2>&1 || { echo "error: 'zip' is not installed" >&2; exit 1; }

STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

# Ship everything git tracks, minus repo-only artifacts. Using git ls-files
# keeps this in sync with .gitignore (dev/ and docker-compose.yml are already
# excluded) with no hand-maintained file list to drift.
while IFS= read -r f; do
  case "$f" in
    .gitignore|package.sh|firebase.zip) continue ;;
  esac
  mkdir -p "$STAGE/$PLUGINDIR/$(dirname "$f")"
  cp "$f" "$STAGE/$PLUGINDIR/$f"
done < <(git ls-files)

rm -f "$OUT"
( cd "$STAGE" && zip -rq package.zip "$PLUGINDIR" )
mv "$STAGE/package.zip" "$OUT"

VERSION="$(sed -n "s/.*\$plugin->version[^0-9]*\([0-9]*\).*/\1/p" version.php)"
echo "Created $OUT (version ${VERSION:-unknown})"
echo "Contents:"
unzip -l "$OUT"
