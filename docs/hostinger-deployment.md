# Hostinger Production Deployment

## Required Production Values

Use HTTPS. Do not point staff apps at `127.0.0.1` or a local IP.

```env
APP_NAME="KIUQ SYSTEM"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://YOUR-DOMAIN.com

DB_CONNECTION=mysql
DB_HOST=YOUR_HOSTINGER_DB_HOST
DB_PORT=3306
DB_DATABASE=YOUR_DATABASE
DB_USERNAME=YOUR_DATABASE_USER
DB_PASSWORD=YOUR_DATABASE_PASSWORD

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
FILESYSTEM_DISK=local

CORS_ALLOWED_ORIGINS=https://YOUR-DOMAIN.com
SANCTUM_STATEFUL_DOMAINS=YOUR-DOMAIN.com
```

## Deploy Steps

1. Upload the Laravel app from `/Applications/XAMPP/xamppfiles/htdocs/dgt-system` to Hostinger.
2. Point the domain document root to Laravel's `public/` folder.
3. Install dependencies:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
4. Run migrations:
   ```bash
   php artisan migrate --force
   ```
5. Build frontend assets:
   ```bash
   npm ci
   npm run build
   ```
6. Link storage:
   ```bash
   php artisan storage:link
   ```
7. Cache production config:
   ```bash
   php artisan optimize
   ```
8. Ensure writable permissions for:
   - `storage/`
   - `bootstrap/cache/`

## Queues And Cron

If Hostinger supports cron, run Laravel's scheduler every minute:

```bash
* * * * * cd /path/to/dgt-system && php artisan schedule:run >> /dev/null 2>&1
```

If long-running queue workers are unavailable on shared hosting, use database queues with a cron-triggered worker:

```bash
* * * * * cd /path/to/dgt-system && php artisan queue:work --stop-when-empty --tries=3 >> /dev/null 2>&1
```

## API Verification

```bash
curl https://YOUR-DOMAIN.com/api/me \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN"
```
