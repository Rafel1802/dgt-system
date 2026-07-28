# DGT System macOS Desktop App Setup

## Architecture

- Laravel remains the only backend.
- MySQL remains the only database.
- The existing website continues to use web routes and sessions.
- The macOS app is a Flutter frontend in `dgt_macos_app/`.
- The app uses Sanctum bearer tokens and calls `https://YOUR-DOMAIN.com/api`.
- Notifications are stored in Laravel's existing `notifications` table and polled by the app.

## Local Backend Setup

1. Install PHP dependencies:
   ```bash
   composer install
   ```

2. Configure `.env`:
   ```env
   APP_URL=http://localhost:8000
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=dgt_system
   DB_USERNAME=root
   DB_PASSWORD=
   CORS_ALLOWED_ORIGINS=http://localhost:8000
   SANCTUM_STATEFUL_DOMAINS=localhost,localhost:8000,127.0.0.1,127.0.0.1:8000
   ```

3. Run migrations. This adds Sanctum's `personal_access_tokens` table:
   ```bash
   php artisan migrate
   ```

4. Start Laravel:
   ```bash
   php artisan serve
   ```

## Local Flutter Setup

1. Install Flutter dependencies:
   ```bash
   cd dgt_macos_app
   flutter pub get
   ```

2. Run the app against local Laravel:
   ```bash
   flutter run -d macos --dart-define=API_BASE_URL=http://localhost:8000/api
   ```

3. Run production-style:
   ```bash
   flutter run -d macos --dart-define=API_BASE_URL=https://YOUR-DOMAIN.com/api
   ```

## API Authentication

The app calls:

- `POST /api/login`
- `POST /api/logout`
- `GET /api/me`

The bearer token is stored in macOS Keychain via `flutter_secure_storage`.

## Notification Polling

- Active app polling interval: 5 seconds.
- Background polling interval: 20 seconds.
- Shown notification IDs are cached locally to prevent duplicate popups.
- Clicking a notification opens the related app section when `related_module` is present.

