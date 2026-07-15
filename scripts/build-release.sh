#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST_DIR="${1:-$ROOT_DIR/dist}"
PACKAGE_DIR="$DIST_DIR/kursorganizer-wp-plugin"
ZIP_FILE="$DIST_DIR/kursorganizer-wp-plugin.zip"

rm -rf "$DIST_DIR"
mkdir -p "$PACKAGE_DIR/includes"

cp "$ROOT_DIR/kursorganizer-wp-plugin.php" "$PACKAGE_DIR/"
cp "$ROOT_DIR/README.md" "$ROOT_DIR/CHANGELOG.md" "$PACKAGE_DIR/"
cp -R "$ROOT_DIR/assets" "$PACKAGE_DIR/"
cp "$ROOT_DIR/includes/class-kursorganizer-api.php" "$PACKAGE_DIR/includes/"
cp "$ROOT_DIR/includes/class-plugin-updater.php" "$PACKAGE_DIR/includes/"
cp "$ROOT_DIR/includes/class-shortcode-url-builder.php" "$PACKAGE_DIR/includes/"
cp "$ROOT_DIR/includes/class-validation-state.php" "$PACKAGE_DIR/includes/"

(
    cd "$DIST_DIR"
    zip -qr "$(basename "$ZIP_FILE")" "$(basename "$PACKAGE_DIR")"
)

if unzip -Z1 "$ZIP_FILE" | grep -Eq '(^|/)(Konfigurationsdaten-|tests/|vendor/|\.git|\.github/|deploy\.sh|package\.json|composer\.(json|lock))'; then
    echo "Release-ZIP enthält nicht erlaubte Dateien." >&2
    exit 1
fi

echo "$ZIP_FILE"
