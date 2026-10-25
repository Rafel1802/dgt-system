# WordPress Website Connector Integration Guide

This guide describes how to connect any WordPress website hosted on Hostinger to the central DGT System.

---

## 1. Prerequisites
- WordPress 5.8+ (WordPress 6.x recommended)
- PHP 8.0, 8.1, 8.2, or 8.3
- Optional: A view counter plugin such as **WP-PostViews**, **Post Views Counter**, or **Jetpack Stats** (if post views tracking is desired).

---

## 2. Installation Options

### Option A: Standard WordPress Plugin (Recommended)
1. Download the single-file plugin from DGT System:
   `https://YOUR-DGT-DOMAIN.com/connectors/wordpress/dgt-connector.php`
2. Connect to your Hostinger File Manager or FTP:
   - Navigate to `/public_html/wp-content/plugins/`
   - Create a folder named `dgt-connector/`
   - Upload `dgt-connector.php` into `/public_html/wp-content/plugins/dgt-connector/`
3. In WP Admin:
   - Go to **Plugins > Installed Plugins**
   - Locate **DGT Central Website Connector** and click **Activate**.

### Option B: Must-Use Plugin (Zero Maintenance)
Upload `dgt-connector.php` directly to `/public_html/wp-content/mu-plugins/dgt-connector.php`.
WordPress will activate it automatically without needing activation in WP Admin.

---

## 3. Configuration in WP Admin

1. Open your WordPress Admin panel and navigate to **Settings > DGT Connector**.
2. Enter your configuration details from DGT Central System:
   - **DGT Platform URL**: `https://YOUR-DGT-DOMAIN.com`
   - **Integration API Key**: Enter the key obtained from DGT System (e.g., `dgt_live_...`)
   - **Integration Secret**: Enter the HMAC Secret obtained when rotating or setting up credentials (e.g., `dgt_sec_...`)
   - **Hourly Auto-Sync**: Keep checked (uses WP-Cron to push views every hour).
3. Click **Save Configuration**.

---

## 4. Testing & Verification

1. On the **Settings > DGT Connector** page in WP Admin, click **Sync Now to DGT System**.
2. You will see a green success message:
   > *"Sync successful! Transmitted posts to DGT Central System."*
3. Log in to DGT Central System:
   - Navigate to **Central Connectors** (`/websites/integrations`)
   - Find your website. You should see a green **Connected** status, updated blog count, and verified views.
   - Click **Test Connection** to verify server-to-server reachability.

---

## 5. Supported View Counter Detection

The DGT Connector plugin automatically inspects and extracts views from the following sources in order:
1. `views` meta field (**WP-PostViews** plugin)
2. `_post_views` meta field (**Post Views Counter** plugin)
3. `_jetpack_post_views` (**Automattic Jetpack** Stats)
4. `post_views_count` (Custom themes/counters)

If no counter plugin is active, post metadata and publication timestamps are still fully tracked, and view tracking will activate automatically as soon as a view counter is enabled.
