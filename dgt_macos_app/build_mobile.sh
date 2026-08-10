#!/usr/bin/env bash
set -euo pipefail

APP_BASE_URL="${1:-}"

if [[ -z "$APP_BASE_URL" ]]; then
  echo "Usage: ./build_mobile.sh https://your-domain.com" >&2
  exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

echo "Patching flutter_inappwebview_android to fix Gradle 8+ compatibility..."
find ~/.pub-cache/hosted/pub.dev/flutter_inappwebview_android-*/android -name "build.gradle" -exec sed -i '' 's/proguard-android.txt/proguard-android-optimize.txt/g' {} + || true

echo "Building Android APK..."
flutter build apk --release --dart-define=APP_BASE_URL="$APP_BASE_URL"
APK_PATH="build/app/outputs/flutter-apk/app-release.apk"

if [ -f "$APK_PATH" ]; then
    echo "Android APK built successfully at: $APK_PATH"
    echo "You can upload this to Hostinger manually, or modify this script to scp it."
else
    echo "Failed to build Android APK."
fi

echo ""
echo "==========================================="
echo "To build for iOS, you must first open the Xcode workspace:"
echo "open ios/Runner.xcworkspace"
echo "And configure your Apple Developer Team and Provisioning Profiles."
echo "Then, you can run:"
echo "flutter build ipa --release --dart-define=APP_BASE_URL=\"$APP_BASE_URL\""
echo "==========================================="
