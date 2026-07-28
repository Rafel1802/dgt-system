# Private Update System

The first update system is intentionally simple and Hostinger-friendly.

## Appcast URL

The macOS app checks:

```text
https://YOUR-DOMAIN.com/appcast/latest-mac.json
```

Sample file lives at:

```text
public/appcast/latest-mac.json
```

## JSON Format

```json
{
  "version": "1.0.1",
  "download_url": "https://YOUR-DOMAIN.com/downloads/DGT-System-1.0.1.dmg",
  "release_notes": "Bug fixes and notification improvements"
}
```

## Release Workflow

1. Build the app:
   ```bash
   flutter build macos --dart-define=API_BASE_URL=https://YOUR-DOMAIN.com/api
   ```
2. Package a `.dmg`.
3. Upload it to:
   ```text
   public/downloads/DGT-System-x.y.z.dmg
   ```
4. Update `public/appcast/latest-mac.json`.
5. Staff click `Settings / Check for Updates` in the app.

## Later Upgrade

For automatic background updates, add Sparkle with Developer ID signing and notarization.

