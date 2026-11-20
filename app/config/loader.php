<?php
/**
 * Herbama Fashions — Environment & Configuration Loader
 * ========================================================
 * Loads configuration seamlessly across:
 *   1. XAMPP (Local development, subfolder support, plain HTTP)
 *   2. Render + Aiven (Cloud demo, Docker, environment variables, SSL CA)
 *   3. Hostinger (Production shared hosting, config.php, forced HTTPS)
 *
 * Precedence: Environment Variables / .env > config.php > Sensible Defaults
 */

declare(strict_types=1);

// ── Base directories ──────────────────────────────────────────────────────────
if (!defined('BASE_ROOT')) {
    define('BASE_ROOT', dirname(__DIR__, 2));
}
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// ── 1. Parse .env file if present in project root ─────────────────────────────
$dotenvFile = BASE_ROOT . '/.env';
if (file_exists($dotenvFile) && is_readable($dotenvFile)) {
    $lines = file($dotenvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines !== false) {
        foreach ($lines as $line) {
            $line = trim($line);
            // Skip comments and empty lines
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
                continue;
            }
            if (str_contains($line, '=')) {
                [$k, $v] = explode('=', $line, 2);
                $k = trim($k);
                $v = trim($v);
                // Strip surrounding single/double quotes if present
                if ((str_starts_with($v, '"') && str_ends_with($v, '"')) ||
                    (str_starts_with($v, "'") && str_ends_with($v, "'"))) {
                    $v = substr($v, 1, -1);
                }
                // Set in environment only if not already set by system/server
                if (getenv($k) === false) {
                    putenv("{$k}={$v}");
                    $_ENV[$k] = $v;
                    $_SERVER[$k] = $v;
                }
            }
        }
    }
}

// ── 2. Load config.php fallback file if present ───────────────────────────────
$configFile = __DIR__ . '/config.php';
$fileConfig = [];
if (file_exists($configFile) && is_readable($configFile)) {
    $loaded = require $configFile;
    if (is_array($loaded)) {
        $fileConfig = $loaded;
    }
}

// ── Helper: Read setting from env > config.php > default ──────────────────────
if (!function_exists('env_setting')) {
    function env_setting(string $key, mixed $default = null, array $fileConfig = []): mixed
    {
        $val = getenv($key);
        if ($val !== false && $val !== '') {
            return $val;
        }
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return $_SERVER[$key];
        }
        if (array_key_exists($key, $fileConfig) && $fileConfig[$key] !== '' && $fileConfig[$key] !== null) {
            return $fileConfig[$key];
        }
        return $default;
    }
}

// ── 3. Resolve Settings ───────────────────────────────────────────────────────

// Environment: 'local' or 'production'
$rawEnv = strtolower(trim((string)env_setting('APP_ENV', 'local', $fileConfig)));
$appEnv = ($rawEnv === 'production' || $rawEnv === 'prod') ? 'production' : 'local';

if (!defined('APP_ENV')) {
    define('APP_ENV', $appEnv);
}
if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', APP_ENV === 'local');
}

// Site Details & URL (handles subfolders on XAMPP)
$siteName   = (string)env_setting('SITE_NAME', 'Herbama Fashions', $fileConfig);
$adminEmail = (string)env_setting('ADMIN_EMAIL', 'admin@herbamafashions.com', $fileConfig);
$siteUrl    = (string)env_setting('SITE_URL', '', $fileConfig);

// Auto-detect SITE_URL if not explicitly set (e.g., http://localhost/herbama)
if (empty($siteUrl)) {
    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
        (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
    );
    $protocol = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Auto-detect subfolder path
    $scriptDir = '';
    if (!empty($_SERVER['SCRIPT_NAME'])) {
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        $scriptDir = ($dir === '/' || $dir === '.') ? '' : rtrim($dir, '/');
    }
    $siteUrl = rtrim($protocol . '://' . $host . $scriptDir, '/');
} else {
    $siteUrl = rtrim($siteUrl, '/');
}

if (!defined('SITE_NAME'))   define('SITE_NAME',   $siteName);
if (!defined('ADMIN_EMAIL')) define('ADMIN_EMAIL', $adminEmail);
if (!defined('SITE_URL'))    define('SITE_URL',    $siteUrl);

// Database Settings
if (!defined('DB_HOST'))    define('DB_HOST',    (string)env_setting('DB_HOST', '127.0.0.1', $fileConfig));
if (!defined('DB_PORT'))    define('DB_PORT',    (int)env_setting('DB_PORT', 3306, $fileConfig));
if (!defined('DB_NAME'))    define('DB_NAME',    (string)env_setting('DB_NAME', 'herbama_db', $fileConfig));
if (!defined('DB_USER'))    define('DB_USER',    (string)env_setting('DB_USER', 'root', $fileConfig));
if (!defined('DB_PASS'))    define('DB_PASS',    (string)env_setting('DB_PASS', '', $fileConfig));
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');
if (!defined('DB_SSL_CA'))  define('DB_SSL_CA',  (string)env_setting('DB_SSL_CA', '', $fileConfig));

// Business Settings
if (!defined('WHATSAPP_NUMBER')) define('WHATSAPP_NUMBER', (string)env_setting('WHATSAPP_NUMBER', '91XXXXXXXXXX', $fileConfig));
if (!defined('UPI_ID'))          define('UPI_ID',          (string)env_setting('UPI_ID', 'yourname@upi', $fileConfig));
if (!defined('CURRENCY'))        define('CURRENCY',        'INR');
if (!defined('CURRENCY_SYMBOL')) define('CURRENCY_SYMBOL', '₹');

// Upload Directory
$configuredUpload = (string)env_setting('UPLOAD_DIR', '', $fileConfig);
if (empty($configuredUpload)) {
    // Default to public_html/uploads/
    $configuredUpload = BASE_ROOT . '/public_html/uploads/';
} else {
    $configuredUpload = rtrim(str_replace('\\', '/', $configuredUpload), '/') . '/';
}
if (!defined('UPLOAD_DIR'))          define('UPLOAD_DIR',          $configuredUpload);
if (!defined('UPLOAD_MAX_BYTES'))    define('UPLOAD_MAX_BYTES',    2 * 1024 * 1024); // 2 MB
if (!defined('UPLOAD_ALLOWED_MIME')) define('UPLOAD_ALLOWED_MIME', ['image/jpeg', 'image/png', 'image/webp']);
if (!defined('UPLOAD_ALLOWED_EXT'))  define('UPLOAD_ALLOWED_EXT',  ['jpg', 'jpeg', 'png', 'webp']);

// Sessions
if (!defined('SESSION_LIFETIME')) define('SESSION_LIFETIME', 1800); // 30 mins
if (!defined('SESSION_NAME'))     define('SESSION_NAME',     'hf_sess');

// Security & Tokens
if (!defined('CSRF_TOKEN_LENGTH'))     define('CSRF_TOKEN_LENGTH',     32);
if (!defined('LOGIN_MAX_ATTEMPTS'))    define('LOGIN_MAX_ATTEMPTS',    5);
if (!defined('LOGIN_LOCKOUT_SECONDS')) define('LOGIN_LOCKOUT_SECONDS', 900);
if (!defined('HEALTH_CHECK_TOKEN'))    define('HEALTH_CHECK_TOKEN',    (string)env_setting('HEALTH_CHECK_TOKEN', 'hf_health_secret', $fileConfig));
if (!defined('SETUP_TOKEN'))           define('SETUP_TOKEN',           (string)env_setting('SETUP_TOKEN', 'hf_setup_secret', $fileConfig));

// Logging
if (!defined('LOG_DIR'))        define('LOG_DIR',        BASE_ROOT . '/logs/');
if (!defined('ERROR_LOG_FILE')) define('ERROR_LOG_FILE', LOG_DIR . 'error.log');
if (!defined('AUDIT_LOG_FILE')) define('AUDIT_LOG_FILE', LOG_DIR . 'audit.log');
if (!defined('MAIL_LOG_FILE'))  define('MAIL_LOG_FILE',  LOG_DIR . 'mail.log');

// Ensure essential directories exist
if (!is_dir(LOG_DIR)) {
    @mkdir(LOG_DIR, 0755, true);
}

// ── Production Security Token Verification ────────────────────────────────────
if (APP_ENV === 'production' && !defined('TEST_SUITE_RUNNING')) {
    $sampleTokens = [
        '',
        'hf_health_secret',
        'hf_setup_secret',
        'sample_token',
        'changeme',
        'secret',
        'default',
    ];
    $tokenViolations = [];

    if (in_array(strtolower(trim(HEALTH_CHECK_TOKEN)), $sampleTokens, true)) {
        $tokenViolations[] = 'HEALTH_CHECK_TOKEN is empty or still set to a default sample value';
    }
    if (in_array(strtolower(trim(SETUP_TOKEN)), $sampleTokens, true)) {
        $tokenViolations[] = 'SETUP_TOKEN is empty or still set to a default sample value';
    }

    if (!empty($tokenViolations)) {
        $logMessage = '[CRITICAL SECURITY ERROR] Application refused to start in production: '
            . implode(', ', $tokenViolations)
            . '. Please define secure, unique tokens in your environment variables or app/config/config.php.';
        error_log($logMessage);
        http_response_code(500);
        die('System Configuration Error: Default security tokens cannot be used in production mode. Please check server error logs for remediation.');
    }
}

// ── 4. URL & Asset Helpers (supports subfolder deployment) ────────────────────
if (!function_exists('url')) {
    /**
     * Build an absolute application URL from a relative path.
     * Works seamlessly in root domain or subfolder (e.g. /herbama).
     */
    function url(string $path = ''): string
    {
        $base = rtrim(SITE_URL, '/');
        $p = '/' . ltrim($path, '/');
        return ($path === '' || $path === '/') ? ($base ?: '/') : ($base . $p);
    }
}

if (!function_exists('asset')) {
    /**
     * Build an absolute URL to a public asset.
     */
    function asset(string $path = ''): string
    {
        $p = ltrim($path, '/');
        if (!str_starts_with($p, 'assets/') && !str_starts_with($p, 'uploads/')) {
            $p = 'assets/' . $p;
        }
        return url('/' . $p);
    }
}

// ── 5. Error Reporting & Logging Setup ────────────────────────────────────────
if (APP_ENV === 'production') {
    ini_set('display_errors',         '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors',             '1');
    ini_set('error_log',               ERROR_LOG_FILE);
    error_reporting(E_ALL);
} else {
    // Local development mode: show errors directly for rapid debugging
    ini_set('display_errors',         '1');
    ini_set('display_startup_errors', '1');
    ini_set('log_errors',             '1');
    ini_set('error_log',               ERROR_LOG_FILE);
    error_reporting(E_ALL);
}

if (!function_exists('is_dir_writable')) {
    /**
     * Cross-platform check if a directory exists and is writable.
     * Fixes Windows PHP is_writable() bug where directory ACLs/read-only flag cause false negatives.
     */
    function is_dir_writable(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }
        if (DIRECTORY_SEPARATOR !== '\\') {
            return is_writable($dir);
        }
        $testFile = rtrim($dir, '/\\') . '/.hf_perm_' . bin2hex(random_bytes(4)) . '.tmp';
        $handle = @fopen($testFile, 'w');
        if ($handle === false) {
            return false;
        }
        @fclose($handle);
        @unlink($testFile);
        return true;
    }
}

// ── 6. Upload Directory Verification ─────────────────────────────────────────
if (!is_dir(UPLOAD_DIR)) {
    @mkdir(UPLOAD_DIR, 0755, true);
}
$isUploadWritable = is_dir_writable(UPLOAD_DIR);
if (!defined('UPLOAD_DIR_WRITABLE')) {
    define('UPLOAD_DIR_WRITABLE', $isUploadWritable);
}
if (!$isUploadWritable) {
    error_log('[WARNING] Upload directory does not exist or is not writable: ' . UPLOAD_DIR);
}
