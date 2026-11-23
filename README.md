# Heramba Fashion — Kidswear E-Commerce Platform

A premium kidswear e-commerce website for **Heramba Fashion** featuring an artisan royal maroon (`#7A1F3D`) and gold (`#C9A24B`) aesthetic on warm cream (`#FFF9F3`). Built with plain modern **PHP 8**, **MySQL (PDO)**, **HTML5**, **CSS3**, and vanilla **JavaScript**—with zero external frameworks or npm dependencies.

The platform is designed to run seamlessly across three distinct hosting environments with **zero code modifications**, relying entirely on environment variables or configuration files:
1. **XAMPP** (Local Development — Subfolder or Root, plain HTTP, mail logging)
2. **Render + Aiven** (Cloud Demo — Dockerized PHP 8.3 Apache, MySQL over SSL CA, dynamic `$PORT`)
3. **Hostinger Shared Hosting** (Production — `public_html`, phpMyAdmin, least-privilege MySQL, strict HTTPS & security headers)

---

## Table of Contents

- [Directory Structure](#directory-structure)
- [Configuration & Environment Precedence](#configuration--environment-precedence)
- [Environment Setup Guides](#environment-setup-guides)
  - [1. Local Development (XAMPP)](#1-local-development-xampp)
  - [2. Cloud Demo Deployment (Render + Aiven)](#2-cloud-demo-deployment-render--aiven)
  - [3. Production Deployment (Hostinger Shared Hosting)](#3-production-deployment-hostinger-shared-hosting)
- [Health Check & Diagnostics (`health.php`)](#health-check--diagnostics-healthphp)
- [Security Architecture](#security-architecture)
- [Created & Modified Files](#created--modified-files)

---

## Directory Structure

```text
Heramba-Fashions/
├── app/
│   ├── config/
│   │   ├── .htaccess          # Blocks direct HTTP access to configuration files
│   │   ├── bootstrap.php      # Master bootstrap: headers, sessions, autoloader
│   │   ├── config.php         # Local/production configuration (gitignored)
│   │   ├── config.sample.php  # Template configuration file with placeholders
│   │   ├── database.php       # PDO connection with SSL CA and error handling
│   │   └── loader.php         # Environment loader (.env -> getenv -> config.php)
│   ├── helpers/
│   │   ├── audit.php          # Admin audit logging
│   │   ├── csrf.php           # CSRF token generation and validation
│   │   ├── flash.php          # Session flash messaging
│   │   ├── mail.php           # Local mail logger (logs/mail.log) vs production mail()
│   │   ├── sanitize.php       # Input sanitization and htmlspecialchars escaping
│   │   └── session.php        # Secure session handling with dynamic secure cookies
│   ├── includes/
│   │   ├── footer.php         # Master footer with trust strip & WhatsApp button
│   │   ├── header.php         # Master header with announcement bar & live search
│   │   ├── product-card.php   # Reusable product card component
│   │   └── whatsapp-button.php# Floating WhatsApp chat trigger
│   └── models/                # PDO database models
├── logs/                      # Runtime application logs (writable by web server)
│   ├── audit.log              # Administrative action log
│   ├── error.log              # Server error log (in production)
│   └── mail.log               # Intercepted outgoing emails in local development
├── public_html/               # Public document root
│   ├── admin/                 # Back-office administration panel
│   ├── api/                   # Async JSON API endpoints (search, wishlist, cart)
│   ├── assets/                # Design tokens, CSS stylesheets, SVGs, and JS
│   ├── uploads/               # Product and banner images (PHP execution blocked)
│   │   └── .htaccess          # Hardened: prevents execution of any PHP scripts
│   ├── .htaccess              # Rewrites, clean URLs, and subfolder RewriteBase
│   ├── collection.php         # Category catalog with live filters
│   ├── health.php             # Diagnostic monitor (locked in production)
│   ├── index.php              # Homepage with hero slider & curated categories
│   ├── product.php            # Product details, age size chips & cart trigger
│   └── ...                    # Static pages (about, terms, shipping, returns)
├── sql/
│   └── schema.sql             # Complete InnoDB schema with seed data
├── Dockerfile                 # Multi-stage PHP 8.3 Apache container for Render
├── docker-entrypoint.sh       # Dynamic $PORT binder for cloud container runtimes
├── .dockerignore              # Files excluded from container build
├── .gitignore                 # Excludes secrets, credentials, and local logs
└── README.md                  # Comprehensive platform documentation
```

---

## Configuration & Environment Precedence

Configuration settings are loaded using a three-tier precedence mechanism:
1. **System Environment Variables & `.env` file** (Render, Docker, Cloud CI/CD)
2. **`app/config/config.php`** (Hostinger, XAMPP fallback)
3. **Internal Defaults** (Fail-safes for rapid local startup)

### Configuration Variables Reference

| Variable | Description | Example / Default |
| :--- | :--- | :--- |
| `APP_ENV` | Application environment (`local` or `production`) | `local` |
| `APP_DEBUG` | Display errors (`true` in local, `false` in production) | `true` |
| `SITE_URL` | Canonical site URL (auto-detected if empty) | `http://localhost/heramba-fashion` |
| `DB_HOST` | MySQL hostname or IP address | `127.0.0.1` |
| `DB_PORT` | MySQL connection port | `3306` (Aiven often uses `10000+`) |
| `DB_NAME` | MySQL database name | `heramba_db` |
| `DB_USER` | MySQL database username | `heramba_user` (never root in prod) |
| `DB_PASS` | MySQL database password | `secret_password` |
| `DB_SSL_MODE` | MySQL SSL mode (`require`, `verify`, `disable`) | `verify` (in prod) / `disable` (in local) |
| `DB_SSL_CA` | Path to MySQL SSL Certificate Authority file | `app/config/ca.pem` (for Aiven) |
| `WHATSAPP_NUMBER` | Business WhatsApp number (country code + number) | `91XXXXXXXXXX` |
| `UPI_ID` | Business UPI ID for WhatsApp order payments | `yourname@upi` |
| `UPLOAD_DIR` | Absolute or relative upload directory path | `public_html/uploads/` |
| `HEALTH_CHECK_TOKEN`| Secret token to unlock `health.php` in production | `generate_random_token` |
| `SETUP_TOKEN` | Secret token to access `setup-admin.php` in production | `generate_random_token` |

---

## Environment Setup Guides

### 1. Local Development (XAMPP)

XAMPP allows rapid development on Windows, macOS, or Linux using Apache and MariaDB/MySQL.

#### Step 1: Clone or Copy the Repository
Place the project inside your XAMPP web root (`htdocs`):
```text
C:\xampp\htdocs\Heramba-Fashions\
```
*(If you rename the folder to `heramba-fashion`, the site will be accessible at `http://localhost/heramba-fashion/public_html` or via virtual host).*

#### Step 2: Configure Environment Settings
Copy the template configuration file:
```bash
cp app/config/config.sample.php app/config/config.php
```
Open `app/config/config.php` and verify:
```php
return [
    'APP_ENV'   => 'local',
    'APP_DEBUG' => true,
    'SITE_URL'  => 'http://localhost/Heramba-Fashions/public_html',
    'DB_HOST'   => '127.0.0.1',
    'DB_PORT'   => 3306,
    'DB_NAME'   => 'heramba_db',
    'DB_USER'   => 'root',
    'DB_PASS'   => '',
    'DB_SSL_CA' => '',
];
```
*(Note: Alternatively, you can create a `.env` file in the root directory with key-value pairs).*

#### Step 3: Create Database & Import Schema
1. Start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Open **phpMyAdmin** (`http://localhost/phpmyadmin/`).
3. Create a new database named `heramba_db` with collation `utf8mb4_unicode_ci`.
4. Click **Import** and select `sql/schema.sql`. Execute the import.
   *(Note: `sql/schema.sql` creates all tables and category seeds, but contains NO default admin user or hardcoded passwords).*

#### Step 4: Create Store Administrator Account (First-Time Setup)
Create your master administrator account using either method:
- **Browser Method:** Navigate to `http://localhost/Heramba-Fashions/public_html/setup-admin.php`. Enter your name, email, and password (minimum 12 characters).
- **CLI Method:** Run `php scripts/create-admin.php` in your terminal and follow the interactive prompts.
- **Security Cleanup:** Once the account is created, **immediately delete `public_html/setup-admin.php`** from your project.

#### Step 5: Subfolder Clean URLs (`.htaccess`)
If running inside a subfolder, open `public_html/.htaccess` and verify or set `RewriteBase`:
```apache
RewriteEngine On
RewriteBase /Heramba-Fashions/public_html/
```

#### Step 6: Verify Local Behaviors
- **Plain HTTP Allowed:** No HTTPS redirect or HSTS headers are sent.
- **Session Cookies:** The `secure` cookie attribute is dynamically set only if accessed over HTTPS.
- **Mail Interception:** Password reset and notification emails are not sent via SMTP; instead, they are cleanly appended to `logs/mail.log`.
- **Health Check:** Visit `http://localhost/Heramba-Fashions/public_html/health.php` to verify database connectivity and directory write permissions.

---

### 2. Cloud Demo Deployment (Render + Aiven)

Deploy the containerized application on **Render Web Services** connected to an **Aiven Cloud MySQL** database.

#### Step 1: Set up Aiven MySQL
1. Log in to [Aiven](https://aiven.io/) and create a free or standard **MySQL** service.
2. Under service overview, download the **CA Certificate** (`ca.pem`).
3. Place `ca.pem` inside `app/config/ca.pem` (or commit as an environment file / Render secret file).
4. Connect to Aiven via MySQL CLI or phpMyAdmin/DBeaver and import `sql/schema.sql`.

#### Step 2: Deploy to Render as a Web Service
1. Push your repository to GitHub.
2. Log in to [Render Dashboard](https://dashboard.render.com/) and click **New +** > **Web Service**.
3. Select your GitHub repository.
4. Render will detect the `Dockerfile` automatically. Set the runtime environment to **Docker**.
5. Set the instance type (e.g. Free or Starter).

#### Step 3: Set Render Environment Variables
Under the **Environment Variables** tab in your Render service, add the following:

```env
APP_ENV=production
APP_DEBUG=false
SITE_URL=https://your-render-app-name.onrender.com
DB_HOST=mysql-xxxx-your-aiven-project.aivencloud.com
DB_PORT=25060
DB_NAME=defaultdb
DB_USER=avnadmin
DB_PASS=your_aiven_password
DB_SSL_CA=app/config/ca.pem
WHATSAPP_NUMBER=91XXXXXXXXXX
UPI_ID=yourname@upi
HEALTH_CHECK_TOKEN=your_secure_random_token_here
SETUP_TOKEN=your_secure_random_setup_token_here
```

#### Step 4: One-Time Administrator Account Setup
Once deployed, initialize your master store administrator account:
1. Open in your browser: `https://your-render-app-name.onrender.com/setup-admin.php?token=your_secure_random_setup_token_here`
2. Submit your name, email, and password (minimum 12 characters).
3. The page will permanently disable itself once the admin account is created.

#### Step 5: Ephemeral Filesystem Notice
> [!IMPORTANT]
> **Render's free filesystem is temporary (ephemeral).** Any product images uploaded through the admin panel on Render will be deleted whenever the container sleeps, restarts, or redeploys. For production hosting with persistent uploads, use **Hostinger** or attach a Render Persistent Disk.

#### Step 6: Verify Cloud Health
Visit `https://your-render-app-name.onrender.com/health.php?token=your_secure_random_token_here` or test JSON output:
```bash
curl -s "https://your-render-app-name.onrender.com/health.php?token=your_secure_random_token_here&format=json"
```

---

### 3. Production Deployment (Hostinger Shared Hosting)

Hostinger shared hosting provides optimized PHP 8.x, LiteSpeed/Apache web servers, automated SSL, and phpMyAdmin.

#### Step 1: Upload Files
Using **Hostinger File Manager** or **Git Deployment** in hPanel:
1. Place the contents of `public_html/` directly inside the server's `public_html/` folder.
2. Place the `app/`, `logs/`, and `sql/` folders **one level above `public_html/`** (recommended) or keep the folder structure as provided.
   - *If `app/` is within `public_html/`, the provided `app/config/.htaccess` automatically blocks all HTTP requests to `config.php`, `database.php`, and `loader.php`.*

#### Step 2: Create a Least-Privilege MySQL User
1. In Hostinger hPanel, navigate to **Databases** > **MySQL Databases**.
2. Create a new database: e.g. `u123456789_heramba`.
3. Create a dedicated database user (e.g., `u123456789_app`) with a strong password.
   - **Never use the root or administrative account.**
   - Ensure the user has `SELECT`, `INSERT`, `UPDATE`, `DELETE`, `CREATE`, `DROP`, `INDEX`, `ALTER` permissions only.

#### Step 3: Import SQL Schema
1. Open **phpMyAdmin** from hPanel.
2. Select your newly created database.
3. Click **Import** > select `sql/schema.sql` > click **Go**.

#### Step 4: Configure `app/config/config.php`
Edit `app/config/config.php` directly on the server:
```php
<?php
return [
    'APP_ENV'            => 'production',
    'APP_DEBUG'          => false,
    'SITE_URL'           => 'https://www.yourdomain.com',
    'DB_HOST'            => 'localhost',
    'DB_PORT'            => 3306,
    'DB_NAME'            => 'u123456789_heramba',
    'DB_USER'            => 'u123456789_app',
    'DB_PASS'            => 'your_strong_database_password',
    'DB_SSL_CA'          => '', // Not required for localhost MySQL on Hostinger
    'WHATSAPP_NUMBER'    => '91XXXXXXXXXX',
    'UPI_ID'             => 'yourname@upi',
    'HEALTH_CHECK_TOKEN' => 'generate_a_random_32_character_string',
    'SETUP_TOKEN'        => 'generate_a_random_32_character_setup_token',
];
```

#### Step 5: One-Time Administrator Account Setup
1. In your browser, open: `https://www.yourdomain.com/setup-admin.php?token=generate_a_random_32_character_setup_token`
2. Enter your name, email, and master password (minimum 12 characters).
3. Once created, **delete `public_html/setup-admin.php` immediately** via Hostinger File Manager.

#### Step 6: Enable SSL & Security
1. In hPanel, go to **Security** > **SSL** and ensure **Lifetime SSL** is active.
2. Enable **Force HTTPS** in hPanel.
3. The application's `app/config/bootstrap.php` automatically enforces:
   - `Strict-Transport-Security` (HSTS)
   - `Content-Security-Policy` (CSP)
   - `X-Frame-Options: DENY`
   - `X-Content-Type-Options: nosniff`
   - Secure, HttpOnly, SameSite=Lax session cookies.

#### Step 7: Verify Permissions & Daily Backups
1. Ensure the `public_html/uploads/` and `logs/` folders are writable (`chmod 755` or `775`).
2. Verify that `public_html/uploads/.htaccess` is present to block any PHP execution in the uploads folder.
3. In hPanel, go to **Files** > **Backups** and enable **Automated Daily Backups**.

---

## Health Check & Diagnostics (`health.php`)

The health check endpoint (`/health.php`) performs real-time diagnostics:
- **Environment Status:** Verifies `APP_ENV` and `APP_DEBUG`.
- **Database Connectivity:** Executes a test PDO query and validates UTF-8 support.
- **Upload Directory:** Checks existence, write permissions, and `.htaccess` PHP blocking.
- **PHP Extensions:** Checks for `pdo`, `pdo_mysql`, `mbstring`, `json`, and `curl`.
- **Log Storage:** Verifies that `logs/` is writable.

### Production Locking
In production (`APP_ENV=production`), the health check is **locked by default** and returns `403 Forbidden` to prevent leaking infrastructure details.

To unlock it, provide the configured `HEALTH_CHECK_TOKEN`:
```text
https://yourdomain.com/health.php?token=YOUR_HEALTH_CHECK_TOKEN
```

### JSON API Format
Append `&format=json` or send header `Accept: application/json` for machine-readable status monitoring:
```json
{
  "status": "ok",
  "app_name": "Heramba Fashion",
  "environment": "production",
  "debug": false,
  "timestamp": "2026-11-20T12:00:00+05:30",
  "database": {
    "connected": true,
    "host": "localhost",
    "port": 3306,
    "database": "heramba_db",
    "ssl_ca": "disabled",
    "error": null
  },
  "uploads": {
    "path": "[PROTECTED]",
    "exists": true,
    "writable": true,
    "htaccess_present": true
  },
  "logs": {
    "exists": true,
    "writable": true
  },
  "system": {
    "php_version": "8.3.14",
    "extensions": {
      "pdo": true,
      "pdo_mysql": true,
      "mbstring": true,
      "json": true,
      "curl": true
    }
  }
}
```

---

## Security Architecture

The application implements a defense-in-depth security model:

1. **SQL Injection Prevention:**
   - 100% PDO prepared statements with bound parameters (`PDO::ATTR_EMULATE_PREPARES => false`).
   - String concatenation of SQL queries is strictly prohibited.
2. **Authentication & Session Security:**
   - Passwords hashed using standard `password_hash()` (bcrypt / argon2id).
   - Rate limiting on login attempts (`login_attempts` table) with temporary lockout.
   - Session fixation defense via `session_regenerate_id(true)` upon authentication.
   - Session cookies configured with `HttpOnly`, `SameSite=Lax`, and dynamic `Secure` flag.
3. **Cross-Site Request Forgery (CSRF):**
   - Cryptographic CSRF tokens on every POST request and AJAX action verified via `CSRF::validate()`.
4. **Cross-Site Scripting (XSS) & Content Security:**
   - Strict output escaping via `e()` (`htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`).
   - Strict Content Security Policy preventing inline script injections from third-party sources.
5. **Secure File Uploads:**
   - Upload directory located in `public_html/uploads/` is protected by `.htaccess` with `SetHandler default-handler` and `php_flag engine off` to prevent script execution.
   - MIME validation and extension whitelisting (`jpg`, `jpeg`, `png`, `webp`).
   - Random file name hashing (`bin2hex(random_bytes(16))`).
6. **Error Handling & Information Disclosure:**
   - In production (`APP_ENV=production`), `display_errors` is disabled. All exceptions are caught and logged privately to `logs/error.log`.
7. **Direct File Protection:**
   - Configuration and SQL files protected via `.htaccess` (`deny from all`).

---

## Created & Modified Files

### Configuration & Infrastructure
- `app/config/loader.php` — Multi-environment loader (.env, env vars, config.php, `url()`, `asset()` helpers).
- `app/config/config.sample.php` — Documented environment template with placeholders.
- `app/config/config.php` — Local configuration file.
- `app/config/database.php` — PDO MySQL connection supporting `DB_PORT`, `DB_SSL_MODE` (`require`/`verify`/`disable`), `DB_SSL_CA`, and connection testing.
- `app/config/bootstrap.php` — Environment-aware bootstrap enforcing HTTPS, HSTS, CSP, and security headers.
- `app/helpers/mail.php` — Mail helper redirecting outgoing emails to `logs/mail.log` in local mode.
- `app/helpers/session.php` — Dynamic `secure` cookie attribute based on HTTPS/environment.
- `Dockerfile` — Production Docker container with `php:8.3-apache`, pdo_mysql, mod_rewrite, and dynamic `$PORT`.
- `docker-entrypoint.sh` — Cloud entrypoint dynamically substituting `$PORT` in Apache vhost configurations.
- `.dockerignore` — Excludes git repository metadata, local logs, and secret files from container builds.
- `.gitignore` — Verified inclusion of `config.php`, `.env`, and `logs/*.log`.

### Application Pages & Templates
- `public_html/health.php` — System diagnostics check with production locking and JSON API mode.
- `public_html/.htaccess` — Subfolder `RewriteBase` support and localhost HTTPS bypass.
- `app/includes/header.php` — Dynamic asset URLs via `asset()` and root links via `url()`; exports `window.SITE_URL`.
- `app/includes/footer.php` — Dynamic asset links, navigation URLs, and newsletter subscribe action.
- `app/includes/product-card.php` — Reusable product component with dynamic image placeholders and URLs.
- `public_html/index.php` — Banner URLs and category links updated to use `url()` and `asset()`.
- `public_html/collection.php` — Dynamic API fetch endpoint and offer link resolution.
- `public_html/product.php` — Breadcrumbs, 404 links, and add-to-cart AJAX updated to use dynamic `SITE_URL`.
- `public_html/about.php`, `public_html/offers.php`, `public_html/search.php`, `public_html/why-heramba.php` — Root links updated to `url()`.
- `public_html/assets/js/main.js` — Live search, wishlist toggle, and newsletter subscription updated to dynamically prefix `window.SITE_URL`.
