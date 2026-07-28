# 🏗️ Digital Team & CRM Management System
### Complete Setup, Local Run & Deployment Guide

---

## 📋 Table of Contents
1. [System Requirements](#1-system-requirements)
2. [First Time Setup (Local)](#2-first-time-setup-local)
3. [Running the App Daily](#3-running-the-app-daily)
4. [Default Login Accounts](#4-default-login-accounts)
5. [Deploy Option A — Cloudflare Tunnel (Easiest)](#5-deploy-option-a--cloudflare-tunnel-easiest)
6. [Deploy Option B — Hostinger Shared Hosting](#6-deploy-option-b--hostinger-shared-hosting)
7. [What Files to Upload](#7-what-files-to-upload)
8. [Environment Variables Reference](#8-environment-variables-reference)
9. [Troubleshooting](#9-troubleshooting)

---

## 1. System Requirements

Install all of these before starting:

| Tool | Version | Download |
|------|---------|----------|
| **XAMPP** | Latest | https://www.apachefriends.org |
| **PHP** | 8.3+ | Included in XAMPP |
| **MySQL** | 8.0+ | Included in XAMPP |
| **Composer** | Latest | https://getcomposer.org |
| **Node.js** | 18+ | https://nodejs.org |
| **Git** | Any | https://git-scm.com |

> ⚠️ **XAMPP must be running** (Apache + MySQL green) before you start Laravel.

---

## 2. First Time Setup (Local)

Open **Terminal** and run these commands **one by one**:

### Step 1 — Go to your project folder
```bash
cd /Users/soporadararin/Desktop/dgt-system
```

### Step 2 — Install PHP packages
```bash
composer install
```

### Step 3 — Install Node.js packages
```bash
npm install
```

### Step 4 — Copy environment file
```bash
cp .env.example .env
```

### Step 5 — Generate app key
```bash
php artisan key:generate
```

### Step 6 — Configure your `.env` file
Open `.env` in any text editor and update these lines:
```env
APP_NAME="Digital Team & CRM Management System"
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=dgt_system
DB_USERNAME=root
DB_PASSWORD=

MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@yourdomain.com
MAIL_PASSWORD=your-app-password
MAIL_FROM_ADDRESS=your-email@yourdomain.com
MAIL_FROM_NAME="DGT System"
```

### Step 7 — Create the database
1. Open your browser → go to **http://localhost/phpmyadmin**
2. Click **"New"** (left sidebar)
3. Database name: `dgt_system`
4. Collation: `utf8mb4_unicode_ci`
5. Click **Create**

### Step 8 — Run migrations and seed demo data
```bash
php artisan migrate:fresh --seed
```
This creates all tables and inserts demo accounts, sample leads, eBay offers, and logistics.

### Step 9 — Link storage folder (for file uploads)
```bash
php artisan storage:link
```

### Step 10 — Build frontend assets
```bash
npm run build
```

---

## 3. Running the App Daily

Every time you want to use the system, open **3 Terminal tabs**:

### Tab 1 — Start Laravel web server
```bash
cd /Users/soporadararin/Desktop/dgt-system
php artisan serve
```
→ Visit: **http://localhost:8000**

### Tab 2 — Start queue worker (for emails/notifications)
```bash
cd /Users/soporadararin/Desktop/dgt-system
php artisan queue:work --sleep=3 --tries=3
```

### Tab 3 — (Only if doing frontend changes)
```bash
cd /Users/soporadararin/Desktop/dgt-system
npm run dev
```

> ✅ XAMPP must also be running with MySQL started.

---

## 4. Default Login Accounts

After running `--seed`, these accounts are created:

| Role | Email | Password |
|------|-------|----------|
| Super Admin | superadmin@dgt.com | password |
| Admin | admin@dgt.com | password |
| Supervisor | supervisor@dgt.com | password |
| Staff | staff@dgt.com | password |
| Sales CRM | sales@dgt.com | password |
| Boss | boss@dgt.com | password |

> 🔒 Change all passwords immediately after first login in production!

---

## 5. Deploy Option A — Cloudflare Tunnel (Easiest)

This keeps the app running on **your Mac** but gives it a **public internet URL**.  
No server needed. Perfect for teams in the same company.

### Step 1 — Install cloudflared
```bash
brew install cloudflared
```

### Step 2 — Start your local server (keep Tab 1 running)
```bash
php artisan serve
```

### Step 3 — Create a public tunnel
```bash
cloudflared tunnel --url http://localhost:8000
```

You will see a URL like:
```
https://random-words-here.trycloudflare.com
```

Share this URL with your team. ✅

### Permanent Cloudflare Tunnel (for always-on access)

#### Step 1 — Login to Cloudflare
```bash
cloudflared tunnel login
```

#### Step 2 — Create a named tunnel
```bash
cloudflared tunnel create dgt-system
```

#### Step 3 — Create config file
Create file at `~/.cloudflared/config.yml`:
```yaml
tunnel: dgt-system
credentials-file: /Users/soporadararin/.cloudflared/YOUR-TUNNEL-ID.json

ingress:
  - hostname: crm.yourdomain.com
    service: http://localhost:8000
  - service: http_status:404
```

#### Step 4 — Point your domain DNS
In Cloudflare Dashboard → DNS → Add CNAME:
```
Name:    crm
Target:  YOUR-TUNNEL-ID.cfargotunnel.com
```

#### Step 5 — Run tunnel as service (auto-start)
```bash
cloudflared service install
```

#### Step 6 — Update `.env`
```env
APP_URL=https://crm.yourdomain.com
```

---

## 6. Deploy Option B — Hostinger Shared Hosting

Use this if you want the app running **24/7 on a real server** without your Mac.

### Prerequisites
- Hostinger Business or Cloud plan (needs SSH + PHP 8.3)
- A domain name pointed to Hostinger

---

### Step 1 — Enable SSH on Hostinger
1. Login to Hostinger hPanel
2. Go to **Hosting → Manage → SSH Access**
3. Enable SSH and note your SSH username/password

### Step 2 — Upload files via SSH or File Manager

#### Option A: Upload via Git (Recommended)
SSH into your server:
```bash
ssh username@yourdomain.com
```
Then clone your repo:
```bash
cd /home/username
git clone https://github.com/your-username/dgt-system.git dgt-system
cd dgt-system
composer install --no-dev --optimize-autoloader
npm install && npm run build
```

#### Option B: Upload via File Manager (No Git)
See **Section 7 — What Files to Upload** below.

### Step 3 — Create database on Hostinger
1. hPanel → **Databases → MySQL Databases**
2. Create database: `username_dgt`
3. Create user and assign full permissions
4. Note the host (usually `127.0.0.1`)

### Step 4 — Create `.env` for production
SSH into server and create `.env`:
```bash
cd /home/username/dgt-system
cp .env.example .env
nano .env
```
Set these values:
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_HOST=127.0.0.1
DB_DATABASE=username_dgt
DB_USERNAME=username_dbuser
DB_PASSWORD=your_db_password
```

### Step 5 — Run setup commands via SSH
```bash
cd /home/username/dgt-system
php artisan key:generate
php artisan migrate:fresh --seed
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Step 6 — Point web root to `/public`
In Hostinger hPanel:
1. Go to **Websites → Manage → File Manager**
2. Go to **Advanced → PHP Configuration** → set document root to `/home/username/dgt-system/public`

OR use `.htaccess` in `public_html`:

---

## 7. What Files to Upload

### For Hostinger (Full Upload)

Upload the **entire project folder** to `/home/username/dgt-system/`  
**EXCEPT** these folders (too large / not needed):

```
❌ DO NOT UPLOAD:
   node_modules/         ← 500MB+, run npm install on server instead
   .git/                 ← version control, not needed
   .env                  ← create fresh on server (contains passwords)
   storage/logs/*.log    ← clear logs first

✅ MUST UPLOAD:
   app/
   bootstrap/
   config/
   database/
   public/               ← this is your web root
   resources/
   routes/
   storage/              ← upload empty folder structure only
   vendor/               ← OR run composer install on server
   .env.example
   artisan
   composer.json
   composer.lock
   package.json
   vite.config.js
```

### Quick Upload Checklist

```
📁 Project root files to upload:
   ✅ artisan
   ✅ composer.json
   ✅ composer.lock
   ✅ package.json
   ✅ package-lock.json
   ✅ vite.config.js
   ✅ .env.example        (rename to .env on server and fill in values)

📁 Folders to upload:
   ✅ app/
   ✅ bootstrap/
   ✅ config/
   ✅ database/
   ✅ public/             ← contains built CSS/JS in public/build/
   ✅ resources/
   ✅ routes/
   ✅ storage/            ← create empty with same folder structure
   ✅ vendor/             ← upload OR run composer install on server
```

### For Cloudflare Tunnel

No upload needed — the app runs on **your own Mac**.  
Only share the tunnel URL with your team.

---

## 8. Environment Variables Reference

| Variable | Example | Description |
|----------|---------|-------------|
| `APP_NAME` | `DGT System` | App name shown in browser |
| `APP_ENV` | `production` | `local` for dev, `production` for live |
| `APP_DEBUG` | `false` | Always `false` in production |
| `APP_URL` | `https://yourdomain.com` | Full URL of the app |
| `DB_HOST` | `127.0.0.1` | MySQL server host |
| `DB_DATABASE` | `dgt_system` | Database name |
| `DB_USERNAME` | `root` | DB username |
| `DB_PASSWORD` | _(your password)_ | DB password |
| `MAIL_HOST` | `smtp-relay.gmail.com` | Google Workspace SMTP |
| `MAIL_PORT` | `587` | SMTP port |
| `MAIL_USERNAME` | `you@domain.com` | Your Google Workspace email |
| `MAIL_PASSWORD` | _(app password)_ | Gmail app password |
| `QUEUE_CONNECTION` | `database` | Use `database` for queued emails |
| `SESSION_DRIVER` | `database` | Stores sessions in DB |
| `CACHE_STORE` | `database` | Stores cache in DB |

---

## 9. Troubleshooting

### ❌ "No application encryption key has been specified"
```bash
php artisan key:generate
```

### ❌ "SQLSTATE: Connection refused"
- Make sure XAMPP MySQL is running (green light)
- Check DB_HOST=127.0.0.1 and DB_PORT=3306 in `.env`

### ❌ "Class not found" or "Target class does not exist"
```bash
composer dump-autoload
php artisan config:clear
php artisan cache:clear
```

### ❌ Uploads/images not showing
```bash
php artisan storage:link
```

### ❌ CSS/JS not loading
```bash
npm run build
```

### ❌ Emails not sending
```bash
# Start queue worker
php artisan queue:work

# Test email config
php artisan tinker
Mail::raw('Test', fn($m) => $m->to('test@gmail.com')->subject('Test'));
```

### ❌ 403 Forbidden on Hostinger
Fix storage permissions:
```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### ❌ Routes not working on Hostinger
Make sure `/public/.htaccess` exists with:
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

---

## 🗂️ System Modules Summary

| Module | URL | Description |
|--------|-----|-------------|
| Dashboard | `/dashboard` | Main overview |
| Website CRM | `/crm/website` | Lead nurturing pipeline |
| eBay CRM | `/crm/ebay` | Offer authorization flow |
| Logistic CRM | `/crm/logistics` | Shipment tracking |
| CRM Dashboard | `/crm/dashboard` | 3-panel CRM overview |
| Customers | `/crm/customers` | Customer database |
| Kanban | `/kanban` | Task management |
| Reports | `/reports` | Analytics & charts |
| Users | `/admin/users` | User management |

---

*Last updated: May 2026 | Built with Laravel 13, PHP 8.3, MySQL, Tailwind CSS, Alpine.js*
