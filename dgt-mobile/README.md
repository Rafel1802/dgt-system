# dgt-mobile — KIUQ SYSTEM iOS App

A Flutter-based iOS mobile wrapper for the KIUQ SYSTEM CRM.

## Features
- Full WebView of the live CRM at `https://rosybrown-baboon-228003.hostingersite.com`
- File upload support (photos, camera, documents) via native iOS picker
- External links open in Safari

## Getting Started

### 1. Install dependencies
```bash
flutter pub get
cd ios && pod install && cd ..
```

### 2. Run on a connected iPhone/Simulator
```bash
flutter run -d <device-id>
```
Use `flutter devices` to list available devices.

### 3. Build for Release (for App Store / TestFlight)
```bash
flutter build ipa --release
```
The `.ipa` file will be at:
`build/ios/ipa/dgt_mobile_app.ipa`

## Changing the Server URL
Edit `lib/main.dart` and update the `configuredAppBaseUrl` default value, or pass it at build time:
```bash
flutter build ipa --dart-define=APP_BASE_URL=https://your-domain.com
```
