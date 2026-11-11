# dgt-mobile — KIUQ SYSTEM iOS & iPadOS App

Native iOS and iPadOS mobile application for **KIUQ SYSTEM**, featuring an **Apple iOS 26 Liquid Glass** interface inspired by Apple Music on iOS 26.

## Key Capabilities
- **Apple iOS 26 Floating Liquid Glass Bottom Navigation** on iPhone with translucent optical blur, specular rim reflections, sliding active glass pill, detached Hub orb, and subtle scroll-down minimization.
- **Adaptive iPadOS 26 Liquid Glass Sidebar** (`shortestSide >= 600`) supporting expanded grouped workspace navigation and a compact icon rail in portrait and landscape.
- **App Hub & Instant Module Search** synced dynamically with live KIUQ SYSTEM role permissions, unread chat/notification badges, and Light / Dark / Neon themes.
- **Native iOS Integrations**:
  - Authenticated file downloads & exports presented natively via iOS **QuickLook (`QLPreviewController`)** and **Share Sheet (`UIActivityViewController`)**
  - Real-time Pusher & Chat local notifications (`UNUserNotificationCenter`)
  - OS-level connectivity monitoring (`NWPathMonitor`) with non-destructive offline banner & automatic recovery
  - Safe cache clearing that preserves active login sessions

## Production Server
Default verified server URL:
`https://lightcyan-weasel-711536.hostingersite.com`

## Build & Run Instructions

### 1. Install Flutter & CocoaPods Dependencies
```bash
cd dgt-mobile
flutter pub get
cd ios && pod install && cd ..
```

### 2. Run Static Analysis & Automated Tests
```bash
flutter analyze
flutter test
```

### 3. Run on an iPhone or iPad Simulator / Connected Device
List available iOS simulators or connected devices:
```bash
flutter devices
```
Launch on a target device:
```bash
flutter run -d <device-id>
```

### 4. Build for Release (Xcode Archive / TestFlight / AltStore)
To verify an unsigned release build locally:
```bash
flutter build ios --release --no-codesign
```
To build a signed `.ipa` archive for TestFlight or App Store Connect:
```bash
flutter build ipa --release
```
Output `.ipa` location:
`build/ios/ipa/dgt_mobile_app.ipa`

### 5. Customizing the KIUQ Server URL (Optional)
Pass a build-time `--dart-define` override without modifying source files:
```bash
flutter run --dart-define=APP_BASE_URL=https://lightcyan-weasel-711536.hostingersite.com
```
