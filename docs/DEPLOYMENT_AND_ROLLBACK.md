# Deployment and Rollback Instructions

This document outlines the zero-downtime deployment and rollback procedures for the DGT Central Website Integration Platform on Hostinger.

---

## 1. Pre-Deployment Verification

Before applying updates to production:
1. Ensure the working tree is clean.
2. Confirm the backup files are preserved in `storage/app/`:
   - `storage/app/pre_integration_schema_backup.sql`
   - `storage/app/pre_integration_websites_data_backup.sql`
3. Run local automated tests to verify passing status:
   ```bash
   php artisan test tests/Feature/WebsiteIntegrationTest.php
   php artisan test tests/Feature/GoogleSearchConsoleTest.php
   ```

---

## 2. Production Deployment Steps (Hostinger)

When using Git or your automated deployment script (`deploy_db.py` / `upload_and_clear.py`):

1. **Pull Code Updates**:
   ```bash
   git pull origin main
   ```

2. **Execute Database Migrations**:
   ```bash
   php artisan migrate --force
   ```
   *New tables created:*
   - `website_integrations`
   - `website_posts`
   - `website_post_analytics_snapshots`
   - `website_sync_logs`

3. **Initialize Integration Records for Existing Sites (First Run)**:
   ```bash
   php artisan db:seed --class=WebsiteIntegrationSeeder --force
   ```

4. **Clear and Rebuild Caches**:
   ```bash
   php artisan optimize:clear
   php artisan optimize
   ```

5. **Verify Scheduled Jobs in Hostinger Cron**:
   Ensure Hostinger hPanel has the standard Laravel scheduler running every minute:
   ```bash
   * * * * * cd /home/u355625773/domains/YOUR_DOMAIN/public_html && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
   ```
   *The scheduler automatically runs `dgt:sync-websites` hourly without overlapping.*

6. **Verify Database Queue Worker (if queue is active)**:
   ```bash
   * * * * * cd /home/u355625773/domains/YOUR_DOMAIN/public_html && /usr/bin/php artisan queue:work --stop-when-empty >> /dev/null 2>&1
   ```

---

## 3. Rollback Procedure

If a failure occurs and a rollback is required:

### Option A: Clean Migration Rollback (Preferred)
To reverse only the integration platform migrations without touching other database tables:
```bash
php artisan migrate:rollback --step=2
```
This safely removes `website_posts`, `website_post_analytics_snapshots`, `website_sync_logs`, and `website_integrations`.

### Option B: Full Restoration from Pre-Integration Backup
If you need to restore the website tables to the exact state before integration:
```bash
mysql -u u355625773_kiuq_system -p u355625773_kiuq_system < storage/app/pre_integration_websites_data_backup.sql
```

After rolling back:
```bash
php artisan optimize:clear
php artisan optimize
```
