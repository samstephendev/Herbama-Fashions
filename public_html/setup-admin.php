<?php
/**
 * Herbama Fashions — One-Time Initial Administrator Setup
 * ========================================================
 * Security Contract:
 * 1. ONLY functions when no administrator account exists in the database.
 * 2. Works freely in APP_ENV=local.
 * 3. In production mode (APP_ENV=production), requires a valid ?token= parameter
 *    matching SETUP_TOKEN configured in environment or config.php.
 * 4. Requires a strong password (minimum 12 characters).
 * 5. Uses Argon2id password hashing if available, fallback to PASSWORD_DEFAULT.
 * 6. Logs creation to audit_log and permanently locks itself once an admin exists.
 * 7. Prominently instructs the administrator to delete this file immediately after use.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config/bootstrap.php';

$userModel    = new UserModel();
$isProduction = (APP_ENV === 'production');
$token        = (string)($_GET['token'] ?? ($_POST['token'] ?? ''));

// ── 1. Check if an admin already exists ────────────────────────────────────────
if ($userModel->hasAdmin()) {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Setup Disabled — <?= e(SITE_NAME) ?></title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #FAF6F0; color: #2B2B2B; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 1rem; }
            .card { background: #fff; padding: 2.5rem; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); max-width: 520px; text-align: center; border-top: 5px solid #7A1F3D; }
            h1 { color: #7A1F3D; font-size: 1.5rem; margin-top: 0; }
            p { color: #555; line-height: 1.6; font-size: 0.95rem; }
            .alert { background: #FEECEC; border: 1px solid #F5C6CB; color: #721C24; padding: 0.9rem; border-radius: 6px; font-size: 0.88rem; margin: 1.25rem 0; text-align: left; }
            .btn { display: inline-block; background: #7A1F3D; color: #fff; text-decoration: none; padding: 0.75rem 1.5rem; border-radius: 6px; font-weight: 600; font-size: 0.9rem; margin-top: 0.5rem; }
        </style>
    </head>
    <body>
        <div class="card">
            <h1>403 — Setup Completed &amp; Disabled</h1>
            <p>An administrator account already exists for <strong><?= e(SITE_NAME) ?></strong>. For security reasons, this initial setup page cannot be run again.</p>
            <div class="alert">
                <strong>🔒 Security Recommendation:</strong><br>
                Please delete <code>public_html/setup-admin.php</code> from your web server now.
            </div>
            <a href="<?= e(url('/login.php')) ?>" class="btn">Go to Admin Login →</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ── 2. Production Token Authentication Check ──────────────────────────────────
if ($isProduction) {
    if (empty($token) || !hash_equals(SETUP_TOKEN, $token)) {
        http_response_code(403);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>403 Forbidden — Setup Locked</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #FAF6F0; color: #2B2B2B; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 1rem; }
                .card { background: #fff; padding: 2.5rem; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); max-width: 500px; text-align: center; border-top: 5px solid #C0392B; }
                h1 { color: #C0392B; font-size: 1.5rem; margin-top: 0; }
                p { color: #555; line-height: 1.6; font-size: 0.95rem; }
            </style>
        </head>
        <body>
            <div class="card">
                <h1>403 — Setup Token Required</h1>
                <p>Initial admin setup is protected in production mode. You must provide a valid secret setup token via the URL parameter:</p>
                <p><code>?token=YOUR_SETUP_TOKEN</code></p>
                <p style="font-size: 0.85rem; color: #777;">Define <code>SETUP_TOKEN</code> in your environment variables or <code>app/config/config.php</code>.</p>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

$error   = '';
$success = false;
$name    = '';
$email   = '';

// ── 3. Handle Form Submission ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $name            = trim($_POST['full_name'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $password        = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if (empty($name)) {
        $error = 'Please enter your full name.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 12) {
        $error = 'Password must be at least 12 characters in length.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match. Please re-enter.';
    } else {
        $res = $userModel->setupInitialAdmin($name, $email, $password);
        if ($res['ok']) {
            $success = true;
        } else {
            $error = $res['error'] ?? 'Could not create administrator account.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Initial Administrator Setup — <?= e(SITE_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --maroon: #7A1F3D;
            --maroon-dark: #58142a;
            --gold: #C9A24B;
            --cream: #FAF6F0;
            --dark: #2B2B2B;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background: var(--cream);
            color: var(--dark);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .setup-card {
            background: #fff;
            width: 100%;
            max-width: 480px;
            border-radius: 14px;
            box-shadow: 0 8px 30px rgba(122, 31, 61, 0.12);
            overflow: hidden;
            border: 1px solid #EADFD7;
        }
        .setup-header {
            background: var(--maroon);
            color: #fff;
            padding: 2rem 2rem 1.75rem;
            text-align: center;
        }
        .setup-header h1 {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 1.6rem;
            margin: 0.35rem 0 0;
            letter-spacing: 0.02em;
        }
        .setup-badge {
            display: inline-block;
            background: rgba(201, 162, 75, 0.25);
            color: var(--gold);
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
            border: 1px solid var(--gold);
        }
        .setup-body {
            padding: 2rem;
        }
        .alert {
            padding: 0.85rem 1rem;
            border-radius: 8px;
            font-size: 0.88rem;
            margin-bottom: 1.25rem;
            line-height: 1.5;
        }
        .alert-error {
            background: #FEECEC;
            border: 1px solid #F5C6CB;
            color: #721C24;
        }
        .alert-success {
            background: #E8F7EE;
            border: 1px solid #C3E6CB;
            color: #155724;
        }
        .alert-warning {
            background: #FFF3CD;
            border: 1px solid #FFEEBA;
            color: #856404;
        }
        .form-group {
            margin-bottom: 1.25rem;
        }
        .form-label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 0.4rem;
            color: #444;
        }
        .form-control {
            width: 100%;
            padding: 0.75rem 0.9rem;
            font-size: 0.95rem;
            font-family: inherit;
            border: 1.5px solid #D6C7C0;
            border-radius: 8px;
            background: #FAF8F6;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--maroon);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(122, 31, 61, 0.12);
        }
        .form-help {
            font-size: 0.78rem;
            color: #777;
            margin-top: 0.35rem;
        }
        .btn-submit {
            width: 100%;
            padding: 0.85rem;
            background: var(--maroon);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
        }
        .btn-submit:hover {
            background: var(--maroon-dark);
        }
        .btn-submit:active {
            transform: scale(0.99);
        }
        .success-box {
            text-align: center;
        }
        .success-icon {
            font-size: 3rem;
            color: #27AE60;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>

<div class="setup-card">
    <div class="setup-header">
        <span class="setup-badge">One-Time Setup</span>
        <h1>Herbama Fashions</h1>
        <p style="margin: 0.25rem 0 0; font-size: 0.85rem; opacity: 0.85;">Create Initial Store Administrator</p>
    </div>

    <div class="setup-body">
        <?php if ($success): ?>
            <div class="success-box">
                <div class="success-icon">✓</div>
                <h2 style="color: #155724; font-size: 1.35rem; margin-top: 0;">Admin Account Created!</h2>
                <p style="font-size: 0.92rem; color: #555; line-height: 1.6;">
                    The initial store administrator account (<strong><?= e($email) ?></strong>) has been successfully created.
                </p>

                <div class="alert alert-warning" style="text-align: left; margin: 1.5rem 0;">
                    <strong>⚠️ CRITICAL SECURITY ACTION:</strong><br>
                    Please delete this file from your web server now:
                    <div style="font-family: monospace; font-size: 0.82rem; margin-top: 0.35rem; word-break: break-all; background: rgba(0,0,0,0.05); padding: 0.35rem 0.5rem; border-radius: 4px;">
                        public_html/setup-admin.php
                    </div>
                </div>

                <a href="<?= e(url('/login.php')) ?>" class="btn-submit" style="display: block; text-decoration: none; text-align: center;">
                    Proceed to Admin Sign In →
                </a>
            </div>
        <?php else: ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">✕ <?= e($error) ?></div>
            <?php endif; ?>

            <p style="font-size: 0.88rem; color: #666; margin-top: 0; line-height: 1.5;">
                Welcome to <strong>Herbama Fashions</strong>. Set up your master store administrator account below. Once completed, this page will permanently deactivate itself.
            </p>

            <form method="POST" action="setup-admin.php<?= !empty($token) ? '?token=' . urlencode($token) : '' ?>">
                <?= csrf_field() ?>
                <?php if (!empty($token)): ?>
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-control" required value="<?= e($name) ?>" placeholder="Store Administrator">
                </div>

                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" required value="<?= e($email) ?>" placeholder="admin@herbamafashions.com">
                    <div class="form-help">This will be your login username.</div>
                </div>

                <div class="form-group">
                    <label class="form-label">Master Password (min. 12 characters)</label>
                    <input type="password" name="password" class="form-control" required minlength="12" placeholder="••••••••••••">
                    <div class="form-help">Must contain at least 12 characters. Strong passwords protect your store.</div>
                </div>

                <div class="form-group">
                    <label class="form-label">Confirm Master Password</label>
                    <input type="password" name="confirm_password" class="form-control" required minlength="12" placeholder="••••••••••••">
                </div>

                <button type="submit" class="btn-submit">
                    Create Administrator Account &amp; Finish Setup
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
