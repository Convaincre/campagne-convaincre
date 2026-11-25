#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
OUT="${1:-$ROOT/dist}"
mkdir -p "$OUT"
OUT="$(cd "$OUT" && pwd)"
VERSION="$(sed -n 's/^ \* Version: //p' "$ROOT/campagne-convaincre.php" | head -n 1)"
test -n "$VERSION"
grep -Fxq ' * Text Domain: campagne-convaincre' "$ROOT/campagne-convaincre.php"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
mkdir -p "$TMP/campagne-convaincre"
for entry in campagne-convaincre.php uninstall.php LICENSE.txt readme.txt assets includes templates; do
  cp -a "$ROOT/$entry" "$TMP/campagne-convaincre/"
done
ARCHIVE="$OUT/campagne-convaincre-$VERSION.zip"
rm -f "$ARCHIVE"
(cd "$TMP" && zip -qr "$ARCHIVE" campagne-convaincre)
echo "$ARCHIVE"
