<?php
/**
 * Herbama Fashions — System Health & Diagnostics Check
 * ======================================================
 * Reports environment mode, database connectivity, upload directory permissions,
 * log status, and core PHP prerequisites.
 *
 * SECURITY:
 *   In production (APP_ENV === 'production'), access is LOCKED and returns HTTP 403
 *   unless accessed with ?token= matching HEALTH_CHECK_TOKEN.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config/bootstrap.php';

// ── 1. Access Control: Locked in Production ──────────────────────────────────
$isProduction = (APP_ENV === 'production');
$token = $_GET['token'] ?? '';
$isAuthenticated = !empty($token) && hash_equals(HEALTH_CHECK_TOKEN, (string)$token);
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$isRenderCheck = stripos($userAgent, 'Render/1.0') !== false || stripos($userAgent, 'Go-http-client') !== false;

if ($isProduction && !$isAuthenticated && !$isRenderCheck) {
    http_response_code(403);
    if (($_GET['format'] ?? '') === 'json' || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status'  => 'forbidden',
            'message' => 'Health check is locked in production mode. Provide a valid ?token= parameter.'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><title>403 Forbidden</title>';
        echo '<style>body{font-family:sans-serif; background:#FFF9F3; color:#7A1F3D; display:flex; align-items:center; justify-content:center; height:100vh; margin:0;} .box{background:#fff; padding:2rem 3rem; border-radius:12px; box-shadow:0 4px 16px rgba(0,0,0,0.08); text-align:center; max-width:480px;} h2{margin-top:0;} p{color:#555; font-size:14px;}</style></head><body>';
        echo '<div class="box"><h2>403 — Health Check Locked</h2><p>This endpoint is protected in production mode. Pass a valid access token via <code>?token=YOUR_TOKEN</code> to view system health diagnostics.</p></div>';
        echo '</body></html>';
    }
    exit;
}

// ── 2. Run Health Diagnostics ────────────────────────────────────────────────
$dbRaw = Database::testConnection();
$dbStatus = [
    'connected' => !empty($dbRaw['connected']) || !empty($dbRaw['ok']),
    'message'   => $dbRaw['message'] ?? null,
    'error'     => $dbRaw['error'] ?? null,
];

$uploadDirExists    = is_dir(UPLOAD_DIR);
$uploadDirWritable  = is_dir_writable(UPLOAD_DIR);
$uploadHtaccess     = file_exists(rtrim(UPLOAD_DIR, '/') . '/.htaccess');

$logDirExists       = is_dir(LOG_DIR);
$logDirWritable     = is_dir_writable(LOG_DIR);

$requiredExtensions = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'curl'];
$extensionStatus    = [];
$allExtensionsOk    = true;
foreach ($requiredExtensions as $ext) {
    $loaded = extension_loaded($ext);
    $extensionStatus[$ext] = $loaded;
    if (!$loaded) $allExtensionsOk = false;
}

// Healthy when core database connection, uploads folder, and logs folder are functioning
$isHealthy = $dbStatus['connected'] && $uploadDirWritable && $logDirWritable;
$httpCode  = $isHealthy ? 200 : 503;

// ── 3. JSON Output Mode ──────────────────────────────────────────────────────
$format = $_GET['format'] ?? '';
$wantsJson = ($format === 'json') || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

if ($wantsJson) {
    // Prevent any PHP notices or buffered output from contaminating clean JSON
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');

    $databasePayload = [
        'connected' => (bool)$dbStatus['connected'],
        'ssl_ca'    => !empty(DB_SSL_CA) ? 'configured' : 'disabled',
        'error'     => $isProduction ? ($dbStatus['connected'] ? null : 'Connection failed') : $dbStatus['error'],
    ];
    if (!$isProduction) {
        $databasePayload['host']     = DB_HOST;
        $databasePayload['port']     = DB_PORT;
        $databasePayload['database'] = DB_NAME;
    }

    $uploadsPayload = [
        'exists'           => $uploadDirExists,
        'writable'         => $uploadDirWritable,
        'htaccess_present' => $uploadHtaccess,
    ];
    if (!$isProduction) {
        $uploadsPayload['path'] = UPLOAD_DIR;
    }

    $systemPayload = [
        'extensions'  => $extensionStatus,
    ];
    if (!$isProduction) {
        $systemPayload['php_version'] = PHP_VERSION;
    }

    echo json_encode([
        'status'       => $isHealthy ? 'ok' : 'degraded',
        'app_name'     => SITE_NAME,
        'environment'  => APP_ENV,
        'debug'        => APP_DEBUG,
        'timestamp'    => date('c'),
        'database'     => $databasePayload,
        'uploads'      => $uploadsPayload,
        'logs'         => [
            'exists'   => $logDirExists,
            'writable' => $logDirWritable
        ],
        'system'       => $systemPayload
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// ── 4. HTML Dashboard Mode ───────────────────────────────────────────────────
http_response_code($httpCode);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Health — <?= e(SITE_NAME) ?></title>
    <style>
        :root {
            --maroon: #7A1F3D;
            --maroon-dark: #58142A;
            --gold: #C9A24B;
            --cream: #FFF9F3;
            --dark: #2B2B2B;
            --muted: #6B6865;
            --success: #1EAA52;
            --danger: #D32F2F;
            --warning: #F59E0B;
            --card-bg: #FFFFFF;
            --border: #EDE4D8;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: var(--cream);
            color: var(--dark);
            line-height: 1.5;
            padding: 2rem 1rem;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .brand-badge {
            display: inline-block;
            background: var(--maroon);
            color: #FFFFFF;
            font-weight: 700;
            font-size: 0.85rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 0.35rem 0.9rem;
            border-radius: 999px;
            margin-bottom: 0.75rem;
        }
        h1 {
            color: var(--maroon);
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
        }
        .subtitle {
            color: var(--muted);
            font-size: 0.95rem;
        }
        .status-banner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.25rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
        }
        .status-banner.is-ok {
            background: #E8F7EE;
            border-left: 6px solid var(--success);
            color: #0E622F;
        }
        .status-banner.is-error {
            background: #FEECEC;
            border-left: 6px solid var(--danger);
            color: #8C1D1D;
        }
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-weight: 700;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 0.35rem 0.85rem;
            border-radius: 999px;
        }
        .status-pill.is-ok { background: var(--success); color: #fff; }
        .status-pill.is-error { background: var(--danger); color: #fff; }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }
        .card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 1.25rem 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }
        .card-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--maroon);
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border);
        }
        .row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.4rem 0;
            font-size: 0.9rem;
            border-bottom: 1px dashed #F0EAE1;
        }
        .row:last-child { border-bottom: none; }
        .label { color: var(--muted); }
        .val { font-weight: 600; color: var(--dark); font-family: monospace; }
        .tag {
            display: inline-block;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.15rem 0.5rem;
            border-radius: 6px;
        }
        .tag.ok { background: #E8F7EE; color: var(--success); }
        .tag.bad { background: #FEECEC; color: var(--danger); }
        .tag.env-local { background: #FEF3C7; color: #92400E; }
        .tag.env-prod { background: #E0E7FF; color: #3730A3; }
        .footer-note {
            text-align: center;
            font-size: 0.85rem;
            color: var(--muted);
            margin-top: 1.5rem;
        }
        .footer-note a { color: var(--maroon); font-weight: 600; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <span class="brand-badge"><?= e(SITE_NAME) ?></span>
            <h1>System Health &amp; Diagnostics</h1>
            <p class="subtitle">Environment readiness and infrastructure monitor</p>
        </header>

        <!-- Main Banner -->
        <div class="status-banner <?= $isHealthy ? 'is-ok' : 'is-error' ?>">
            <div>
                <strong><?= $isHealthy ? '✓ All Core Systems Operational' : '⚠️ System Health Warning' ?></strong>
                <p style="font-size: 0.85rem; margin-top: 0.25rem;">
                    <?= $isHealthy ? 'Database, file storage, and PHP dependencies are functioning as expected.' : 'One or more subsystem checks have reported an issue.' ?>
                </p>
            </div>
            <span class="status-pill <?= $isHealthy ? 'is-ok' : 'is-error' ?>">
                <?= $isHealthy ? 'Healthy' : 'Degraded' ?>
            </span>
        </div>

        <div class="grid">
            <!-- 1. Environment & Server -->
            <div class="card">
                <div class="card-title">
                    <span>Environment &amp; Server</span>
                    <span class="tag <?= APP_ENV === 'production' ? 'env-prod' : 'env-local' ?>">
                        <?= strtoupper(APP_ENV) ?>
                    </span>
                </div>
                <div class="row">
                    <span class="label">Application Environment</span>
                    <span class="val"><?= APP_ENV ?></span>
                </div>
                <div class="row">
                    <span class="label">Debug Mode</span>
                    <span class="val"><?= APP_DEBUG ? 'Enabled' : 'Disabled' ?></span>
                </div>
                <div class="row">
                    <span class="label">Site Base URL</span>
                    <span class="val"><?= e(SITE_URL) ?></span>
                </div>
                <div class="row">
                    <span class="label">PHP Version</span>
                    <span class="val"><?= $isProduction ? '[PROTECTED]' : PHP_VERSION ?></span>
                </div>
                <div class="row">
                    <span class="label">Server Software</span>
                    <span class="val"><?= e($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') ?></span>
                </div>
            </div>

            <!-- 2. Database Connection -->
            <div class="card">
                <div class="card-title">
                    <span>Database (MySQL / PDO)</span>
                    <span class="tag <?= $dbStatus['connected'] ? 'ok' : 'bad' ?>">
                        <?= $dbStatus['connected'] ? 'Connected' : 'Error' ?>
                    </span>
                </div>
                <div class="row">
                    <span class="label">Database Host</span>
                    <span class="val"><?= $isProduction ? '[PROTECTED]' : e(DB_HOST) . ':' . DB_PORT ?></span>
                </div>
                <div class="row">
                    <span class="label">Database Name</span>
                    <span class="val"><?= $isProduction ? '[PROTECTED]' : e(DB_NAME) ?></span>
                </div>
                <div class="row">
                    <span class="label">SSL CA Status</span>
                    <span class="val"><?= !empty(DB_SSL_CA) ? 'Enabled' : 'None' ?></span>
                </div>
                <div class="row">
                    <span class="label">Connection Result</span>
                    <span class="val">
                        <?php if ($dbStatus['connected']): ?>
                            <span class="tag ok">OK (PDO UTF8MB4)</span>
                        <?php else: ?>
                            <span class="tag bad"><?= e($isProduction ? 'Failed' : ($dbStatus['error'] ?? 'Unknown error')) ?></span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <!-- 3. Upload Directory -->
            <div class="card">
                <div class="card-title">
                    <span>Upload Directory</span>
                    <span class="tag <?= $uploadDirWritable ? 'ok' : 'bad' ?>">
                        <?= $uploadDirWritable ? 'Writable' : 'Action Required' ?>
                    </span>
                </div>
                <div class="row">
                    <span class="label">Upload Path</span>
                    <span class="val" style="font-size:0.75rem;"><?= e($isProduction ? '[PROTECTED]' : basename(UPLOAD_DIR)) ?></span>
                </div>
                <div class="row">
                    <span class="label">Directory Exists</span>
                    <span class="val"><span class="tag <?= $uploadDirExists ? 'ok' : 'bad' ?>"><?= $uploadDirExists ? 'Yes' : 'No' ?></span></span>
                </div>
                <div class="row">
                    <span class="label">Writable Permissions</span>
                    <span class="val"><span class="tag <?= $uploadDirWritable ? 'ok' : 'bad' ?>"><?= $uploadDirWritable ? 'Writable' : 'Not Writable' ?></span></span>
                </div>
                <div class="row">
                    <span class="label">.htaccess Protection</span>
                    <span class="val"><span class="tag <?= $uploadHtaccess ? 'ok' : 'bad' ?>"><?= $uploadHtaccess ? 'Active (No PHP)' : 'Missing' ?></span></span>
                </div>
            </div>

            <!-- 4. Required Extensions -->
            <div class="card">
                <div class="card-title">
                    <span>PHP Extensions</span>
                    <span class="tag <?= $allExtensionsOk ? 'ok' : 'bad' ?>">
                        <?= $allExtensionsOk ? 'All Present' : 'Missing Ext' ?>
                    </span>
                </div>
                <?php foreach ($extensionStatus as $name => $present): ?>
                    <div class="row">
                        <span class="label">ext-<?= e($name) ?></span>
                        <span class="val"><span class="tag <?= $present ? 'ok' : 'bad' ?>"><?= $present ? 'Loaded' : 'Missing' ?></span></span>
                    </div>
                <?php endforeach; ?>
                <div class="row">
                    <span class="label">Log Directory Writable</span>
                    <span class="val"><span class="tag <?= $logDirWritable ? 'ok' : 'bad' ?>"><?= $logDirWritable ? 'Writable' : 'Read-only' ?></span></span>
                </div>
            </div>
        </div>

        <div class="footer-note">
            <p>Herbama Fashions &bull; <?= date('Y') ?> &bull; JSON API available via <a href="?format=json<?= !empty($token) ? '&token=' . urlencode($token) : '' ?>">?format=json</a></p>
        </div>
    </div>
</body>
</html>
