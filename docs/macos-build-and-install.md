# macOS Build And Internal Install Guide

## Build `.app`

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/dgt-system/dgt_macos_app
flutter build macos --release --dart-define=APP_BASE_URL=https://YOUR-DOMAIN.com
```

Output:

```text
dgt_macos_app/build/macos/Build/Products/Release/KIUQ SYSTEM.app
```

## Optional `.dmg`

Create a folder with `DGT System.app` and a shortcut to `/Applications`, then package it with your preferred DMG tool. A simple command-line option:

```bash
hdiutil create -volname "KIUQ SYSTEM" \
  -srcfolder "build/macos/Build/Products/Release/KIUQ SYSTEM.app" \
  -ov -format UDZO "KIUQ-SYSTEM-1.0.0.dmg"
```

For a polished drag-to-Applications DMG, use a dedicated DMG layout tool later.

## Internal Staff Install

1. Staff receive the `.app` or `.dmg` from a private company link or AirDrop.
2. Drag `KIUQ SYSTEM.app` into `/Applications`.
3. Open the app and sign in with the same KIUQ SYSTEM account used on the website.
4. Allow macOS notifications when prompted.

This is private company software. No App Store publishing is prepared.

## Gatekeeper Note

Unsigned private builds may show a macOS warning. For smoother installs, sign and notarize with an Apple Developer ID before sharing broadly.
