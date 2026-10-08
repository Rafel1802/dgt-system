#!/usr/bin/env bash
set -euo pipefail

export PATH="$PATH:/Users/soporadararin/development/flutter/bin"
APP_BASE_URL="${1:-https://lightcyan-weasel-711536.hostingersite.com}"

echo "1. Generating App Icons..."
flutter pub get
flutter pub run flutter_launcher_icons

echo "2. Building iOS App (No Codesign)..."
flutter build ios --release --no-codesign --dart-define=APP_BASE_URL="$APP_BASE_URL"

echo "3. Packaging into AltStore IPA..."
BUILD_DIR="build/ios/iphoneos"
APP_BUNDLE="Runner.app"
PAYLOAD_DIR="build/ios/Payload"
IPA_PATH="build/ios/KIUQ-SYSTEM-IOS-1.0.6.ipa"

if [ -d "build/ios/iphoneos/Runner.app" ]; then
    BUILD_DIR="build/ios/iphoneos"
elif [ -d "build/ios/Release-iphoneos/Runner.app" ]; then
    BUILD_DIR="build/ios/Release-iphoneos"
else
    echo "Error: Runner.app not found in build/ios/iphoneos or build/ios/Release-iphoneos"
    exit 1
fi

rm -rf "$PAYLOAD_DIR"
mkdir -p "$PAYLOAD_DIR"
cp -R "$BUILD_DIR/$APP_BUNDLE" "$PAYLOAD_DIR/"

rm -f "$IPA_PATH" "build/ios/KIUQ-SYSTEM.ipa"
cd build/ios
zip -qr KIUQ-SYSTEM-IOS-1.0.6.ipa Payload
cp KIUQ-SYSTEM-IOS-1.0.6.ipa KIUQ-SYSTEM.ipa
cd ../..

rm -rf "$PAYLOAD_DIR"

mkdir -p ../public/downloads
cp "build/ios/KIUQ-SYSTEM-IOS-1.0.6.ipa" "../public/downloads/KIUQ-SYSTEM-IOS-1.0.6.ipa"
cp "build/ios/KIUQ-SYSTEM.ipa" "../public/downloads/KIUQ-SYSTEM.ipa"

echo "Done! Your IPA is ready at: ../public/downloads/KIUQ-SYSTEM-IOS-1.0.6.ipa"
