# DGT System Hosting Optimization Guide

This guide contains the settings you should apply in your shared hosting panel (cPanel, hPanel, etc.) to get maximum performance out of the DGT System.

## 1. Cron Job for Automated Cleanup
To prevent your storage from filling up with old logs and temporary files, add this Cron Job to run once a week:

**Command:**
```bash
/usr/local/bin/php /home/YOUR_USERNAME/public_html/artisan system:cleanup --days=30
```
*(Note: Replace `YOUR_USERNAME` and `public_html` with your actual server path to the project root. On Hostinger, it might look like `/home/u123456789/domains/yourdomain.com/public_html/artisan`)*

**Schedule:**
- `0 0 * * 0` (Runs at midnight on Sundays)

## 2. Optimized `.htaccess` file
Place this at the very top of your `public/.htaccess` file to enable browser caching, GZIP/Brotli compression, and optimize asset delivery.

```apache
<IfModule mod_expires.c>
    ExpiresActive On
    
    # Images
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/gif "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/webp "access plus 1 year"
    ExpiresByType image/svg+xml "access plus 1 year"
    ExpiresByType image/x-icon "access plus 1 year"

    # Video
    ExpiresByType video/webm "access plus 1 year"
    ExpiresByType video/mp4 "access plus 1 year"
    ExpiresByType video/mpeg "access plus 1 year"

    # Fonts
    ExpiresByType font/ttf "access plus 1 year"
    ExpiresByType font/otf "access plus 1 year"
    ExpiresByType font/woff "access plus 1 year"
    ExpiresByType font/woff2 "access plus 1 year"
    ExpiresByType application/font-woff "access plus 1 year"

    # CSS, JavaScript
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType text/javascript "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"

    # Others
    ExpiresByType application/pdf "access plus 1 month"
    ExpiresByType image/vnd.microsoft.icon "access plus 1 year"
</IfModule>

<IfModule mod_deflate.c>
    # Compress HTML, CSS, JavaScript, Text, XML and fonts
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/vnd.ms-fontobject
    AddOutputFilterByType DEFLATE application/x-font
    AddOutputFilterByType DEFLATE application/x-font-opentype
    AddOutputFilterByType DEFLATE application/x-font-otf
    AddOutputFilterByType DEFLATE application/x-font-truetype
    AddOutputFilterByType DEFLATE application/x-font-ttf
    AddOutputFilterByType DEFLATE application/x-javascript
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE font/opentype
    AddOutputFilterByType DEFLATE font/otf
    AddOutputFilterByType DEFLATE font/ttf
    AddOutputFilterByType DEFLATE image/svg+xml
    AddOutputFilterByType DEFLATE image/x-icon
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/javascript
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/xml

    # Remove browser bugs (only needed for really old browsers)
    BrowserMatch ^Mozilla/4 gzip-only-text/html
    BrowserMatch ^Mozilla/4\.0[678] no-gzip
    BrowserMatch \bMSIE !no-gzip !gzip-only-text/html
    Header append Vary User-Agent
</IfModule>
```

## 3. Deployment Script Check
When uploading to your live server using your script, it runs:
```bash
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
```
This is the correct approach. Since we fixed the closure routes, `php artisan optimize` will now correctly cache the routes, config, and views in production, drastically reducing TTFB (Time to First Byte).
