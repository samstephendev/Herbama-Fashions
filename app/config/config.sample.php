<?php
/**
 * Herbama Fashions — Sample Configuration File
 * ===============================================
 * Instructions:
 * 1. For local development (XAMPP):
 *    Copy this file to `app/config/config.php` and adjust settings below.
 * 2. For Hostinger shared hosting:
 *    Upload and rename to `app/config/config.php` (outside public_html).
 * 3. For Render / Cloud platforms:
 *    Do NOT commit config.php. Instead, set these as Environment Variables
 *    directly in the platform dashboard (Render Environment settings).
 */

return [
    // ── Application Environment ('local' or 'production') ────────────────────
    // 'local'      : Allows plain HTTP, shows errors on screen, logs mail to /logs/mail.log
    // 'production' : Forces HTTPS, sets HSTS/CSP, hides errors, uses real mail
    'APP_ENV' => 'local',

    // ── Site URL ─────────────────────────────────────────────────────────────
    // For local XAMPP in a subfolder: e.g. 'http://localhost/Herbama-Fashions/public_html'
    // For Hostinger production:        e.g. 'https://herbamafashions.com'
    // For Render demo:                e.g. 'https://herbama-fashions.onrender.com'
    // Leave blank ('') to enable dynamic auto-detection.
    'SITE_URL' => '',

    // ── Site Metadata ────────────────────────────────────────────────────────
    'SITE_NAME'   => 'Heramba Fashion',
    'ADMIN_EMAIL' => 'admin@yourdomain.com',

    // ── Database (MySQL PDO) ─────────────────────────────────────────────────
    // XAMPP Default: host '127.0.0.1', port 3306, user 'root', pass ''
    // Aiven Cloud:   host 'mysql-xxx.aivencloud.com', port 12345, user 'avnadmin'
    // Hostinger:     host 'localhost', port 3306, user 'u123456789_user'
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => 3306,
    'DB_NAME' => 'herbama_db',
    'DB_USER' => 'root',
    'DB_PASS' => '',

    // ── SSL Mode for Database ────────────────────────────────────────────────
    // 'require' : Connect with SSL enabled without verifying server certificate (no CA needed)
    // 'verify'  : Connect with SSL and verify against DB_SSL_CA (default in production)
    // 'disable' : Plain connection without SSL (default in local development)
    'DB_SSL_MODE' => 'disable',

    // ── SSL Certificate for Database (Needed for Aiven / Cloud MySQL) ────────
    // Path to CA certificate file, e.g. 'app/config/ca.pem' or '/etc/ssl/certs/aiven-ca.pem'
    // In 'verify' mode, this file MUST exist or connection is aborted with error.
    'DB_SSL_CA' => '',

    // ── Business Settings ────────────────────────────────────────────────────
    'WHATSAPP_NUMBER' => '91XXXXXXXXXX',  // Country code + number (no '+' or spaces)
    'UPI_ID'          => 'yourname@upi',   // UPI address for order payments

    // ── Upload Directory (Optional override) ─────────────────────────────────
    // Leave empty ('') to use default: [project_root]/public_html/uploads/
    'UPLOAD_DIR' => '',

    // ── Security Secret Tokens ───────────────────────────────────────────────
    // Required to view /health.php in production mode (e.g. /health.php?token=my_secret)
    'HEALTH_CHECK_TOKEN' => 'hf_health_secret',
    // Required to access /setup-admin.php in production mode (e.g. /setup-admin.php?token=my_secret)
    'SETUP_TOKEN'        => 'hf_setup_secret',
];
