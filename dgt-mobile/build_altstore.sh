#!/usr/bin/env bash
set -euo pipefail

APP_BASE_URL="${1:-https://rosybrown-baboon-228003.hostingersite.com}"

echo "1. Generating App Icons..."
flutter pub get
flutter pub run flutter_launcher_icons

echo "2. Building iOS App (No Codesign)..."
flutter build ios --release --no-codesign --dart-define=APP_BASE_URL="$APP_BASE_URL"

echo "3. Packaging into AltStore IPA..."
BUILD_DIR="build/ios/iphoneos"
APP_BUNDLE="Runner.app"
PAYLOAD_DIR="build/ios/Payload"
IPA_PATH="build/ios/KIUQ-SYSTEM.ipa"

if [ ! -d "$BUILD_DIR/$APP_BUNDLE" ]; then
    echo "Error: Runner.app not found in $BUILD_DIR"
    exit 1
fi

rm -rf "$PAYLOAD_DIR"
mkdir -p "$PAYLOAD_DIR"
cp -R "$BUILD_DIR/$APP_BUNDLE" "$PAYLOAD_DIR/"

rm -f "$IPA_PATH"
cd build/ios
zip -qr KIUQ-SYSTEM.ipa Payload
cd ../..

rm -rf "$PAYLOAD_DIR"

echo "Done! Your IPA for AltStore is ready at: dgt-mobile/$IPA_PATH"
