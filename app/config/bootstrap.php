<?php
/**
 * Herbama Fashions — Master Bootstrap
 * =====================================
 * Loaded first on every request. Initializes environment configuration,
 * security headers, autoloader, helpers, and secure session.
 */

declare(strict_types=1);

// ── 1. Load environment configuration ─────────────────────────────────────────
require_once __DIR__ . '/loader.php';

// ── 2. Determine HTTPS status ─────────────────────────────────────────────────
$isHttps = isHttps();

// ── 3. Security headers (set before ANY output) ───────────────────────────────
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

// Content Security Policy
header(
    "Content-Security-Policy: " .
    "default-src 'self' " . rtrim(SITE_URL, '/') . "; " .
    "script-src 'self' 'unsafe-inline' " . rtrim(SITE_URL, '/') . "; " .
    "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com " . rtrim(SITE_URL, '/') . "; " .
    "font-src 'self' https://fonts.gstatic.com data: " . rtrim(SITE_URL, '/') . "; " .
    "img-src 'self' data: " . rtrim(SITE_URL, '/') . "; " .
    "connect-src 'self' " . rtrim(SITE_URL, '/') . "; " .
    "frame-ancestors 'none'; " .
    "base-uri 'self'; " .
    "form-action 'self' " . rtrim(SITE_URL, '/') . ";"
);

// ── 4. Production vs Local Mode Rules ─────────────────────────────────────────
if (APP_ENV === 'production') {
    // Enable HSTS in production when over HTTPS
    if (isHttps()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }

    // Force HTTPS redirect on live host (exempt health check and Render's health checker)
    if (shouldRedirectToHttps() && PHP_SAPI !== 'cli') {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri  = $_SERVER['REQUEST_URI'] ?? '/';
        header('Location: https://' . $host . $uri, true, 301);
        exit;
    }
} else {
    // Local development mode (XAMPP): plain HTTP is fully permitted.
    // Skip HSTS and skip HTTPS redirect.
}

// ── 5. Autoload app files & helpers ───────────────────────────────────────────
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/helpers/session.php';
require_once APP_ROOT . '/helpers/csrf.php';
require_once APP_ROOT . '/helpers/sanitize.php';
require_once APP_ROOT . '/helpers/flash.php';
require_once APP_ROOT . '/helpers/audit.php';
require_once APP_ROOT . '/helpers/mail.php';

// Dynamic model autoloader
spl_autoload_register(function (string $class): void {
    $file = APP_ROOT . '/models/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// ── 6. Start the secure session ───────────────────────────────────────────────
Session::start();
