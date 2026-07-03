#!/usr/bin/env bash
set -euo pipefail

APP_BASE_URL="${1:-}"
VERSION="${2:-1.0.0}"

if [[ -z "$APP_BASE_URL" ]]; then
  echo "Usage: ./build_staff_dmg.sh https://your-domain.com [version]" >&2
  exit 1
fi

if [[ "$APP_BASE_URL" != https://* ]]; then
  echo "APP_BASE_URL must use HTTPS for staff builds." >&2
  exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_NAME="KIUQ SYSTEM"
APP_PATH="$SCRIPT_DIR/build/macos/Build/Products/Release/$APP_NAME.app"
DMG_DIR="$SCRIPT_DIR/build/dmg"
DMG_PATH="$DMG_DIR/KIUQ-SYSTEM-$VERSION.dmg"

cd "$SCRIPT_DIR"

flutter build macos --release --dart-define=APP_BASE_URL="$APP_BASE_URL"

rm -rf "$DMG_DIR"
mkdir -p "$DMG_DIR"

hdiutil create \
  -volname "$APP_NAME" \
  -srcfolder "$APP_PATH" \
  -ov \
  -format UDZO \
  "$DMG_PATH"

echo "$DMG_PATH"
