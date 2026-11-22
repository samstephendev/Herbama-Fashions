<?php
/**
 * forgot-password.php — Request a password reset link
 *
 * Herbama Fashions · Kidswear E-Commerce
 *
 * Security note: We ALWAYS show the same success message regardless of
 * whether the submitted email belongs to a real account. This prevents
 * user-enumeration attacks.
 */

require_once dirname(__DIR__) . '/app/config/bootstrap.php';

// ── Redirect logged-in users ─────────────────────────────────────────────────
if (Session::isLoggedIn()) {
    header('Location: ' . url('/account.php'));
    exit;
}

// ── Instantiate models ───────────────────────────────────────────────────────
$userModel = new UserModel();

// ════════════════════════════════════════════════════════════════════════════
// POST HANDLER
// ════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. CSRF check
    csrf_verify();

    // 2. Sanitise input
    $email = Sanitize::email($_POST['email'] ?? '');

    // 3. Basic format validation
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        Session::flash('error', 'Please enter a valid email address.');
    } else {
        // 4. Attempt to create a reset token.
        //    createPasswordReset() returns the raw token on success, or null
        //    if the email is not registered — we treat both cases the same way.
        $rawToken = $userModel->createPasswordReset($email);

        if ($rawToken !== null) {
            // Build the reset link
            $resetLink = url('/reset-password.php')
                       . '?email=' . urlencode($email)
                       . '&token=' . urlencode($rawToken);

            // Compose the email body
            $siteName   = 'Herbama Fashions';
            $emailBody  = "Hello,\n\n";
            $emailBody .= "We received a request to reset the password for your {$siteName} account.\n\n";
            $emailBody .= "Click the link below to set a new password (valid for 1 hour):\n";
            $emailBody .= $resetLink . "\n\n";
            $emailBody .= "If you did not request this, you can safely ignore this email — your password will not change.\n\n";
            $emailBody .= "Warm regards,\nThe {$siteName} Team";

            // Send the email (fire-and-forget; we do not reveal delivery status)
            send_email($email, 'Reset your ' . $siteName . ' password', $emailBody);
        }

        // Always show the same generic success message
        Session::flash('success', 'If that email is registered, you will receive a password reset link shortly. Please check your inbox (and spam folder).');
        header('Location: ' . url('/forgot-password.php'));
        exit;
    }
}

// ════════════════════════════════════════════════════════════════════════════
// PAGE SETUP
// ════════════════════════════════════════════════════════════════════════════
$pageTitle = 'Forgot Password';

$extraHead = '<style>
.auth-page { min-height: 70vh; display: flex; align-items: center; justify-content: center; padding: 2rem 1rem; background: var(--color-cream); }
.auth-card { background: #fff; border-radius: 16px; box-shadow: 0 4px 32px rgba(122,31,61,0.10); padding: 2.5rem 2rem; max-width: 440px; width: 100%; }
.auth-card .auth-logo { text-align: center; margin-bottom: 1.5rem; }
.auth-card h1 { font-family: var(--font-heading); color: var(--color-maroon); font-size: 1.75rem; text-align: center; margin-bottom: 0.25rem; }
.auth-card .auth-subtitle { text-align: center; color: #888; font-size: 0.9rem; margin-bottom: 1.75rem; }
.auth-form .form-group { margin-bottom: 1.1rem; }
.auth-form label { display: block; font-size: 0.85rem; font-weight: 600; color: var(--color-dark); margin-bottom: 0.4rem; }
.auth-form input[type=text], .auth-form input[type=email], .auth-form input[type=tel], .auth-form input[type=password] { width: 100%; padding: 0.65rem 0.9rem; border: 1.5px solid #e0d6dc; border-radius: 8px; font-size: 0.95rem; outline: none; transition: border 0.2s; box-sizing: border-box; }
.auth-form input:focus { border-color: var(--color-maroon); }
.auth-submit { width: 100%; padding: 0.8rem; background: var(--color-maroon); color: #fff; border: none; border-radius: 50px; font-size: 1rem; font-weight: 600; cursor: pointer; margin-top: 0.5rem; transition: background 0.2s; }
.auth-submit:hover { background: #5a1630; }
.auth-links { text-align: center; margin-top: 1.25rem; font-size: 0.88rem; color: #888; }
.auth-links a { color: var(--color-maroon); font-weight: 600; text-decoration: none; }
.auth-divider { border: none; border-top: 1px solid #f0eaee; margin: 1.5rem 0; }
.alert { padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem; }
.alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
</style>';

require_once APP_ROOT . '/includes/header.php';
?>

<main class="auth-page">
    <div class="auth-card">

        <!-- Logo -->
        <div class="auth-logo">
            <a href="<?= url('/') ?>" style="display:flex;flex-direction:column;align-items:center;gap:0.5rem;text-decoration:none;">
                <picture>
                    <source srcset="<?= asset('/assets/images/brand/logo-128.webp') ?> 2x,
                                    <?= asset('/assets/images/brand/logo-64.webp') ?> 1x"
                            type="image/webp">
                    <img src="<?= asset('/assets/images/brand/logo-64.png') ?>"
                         srcset="<?= asset('/assets/images/brand/logo-128.png') ?> 2x"
                         alt="Herabama Fashions logo — round maroon and gold badge"
                         width="64" height="64" loading="eager">
                </picture>
                <span style="font-family:var(--font-heading,serif);font-size:1.15rem;color:var(--color-maroon,#6b1a1a);font-weight:600;letter-spacing:0.03em;">Herabama Fashions</span>
            </a>
        </div>

        <h1>Forgot Password?</h1>
        <p class="auth-subtitle">Enter your email and we'll send you a reset link.</p>

        <!-- Flash messages -->
        <?php foreach (Session::getFlash('success') as $msg): ?>
            <div class="alert alert-success"><?= e($msg) ?></div>
        <?php endforeach; ?>
        <?php foreach (Session::getFlash('error') as $msg): ?>
            <div class="alert alert-error"><?= e($msg) ?></div>
        <?php endforeach; ?>

        <!-- Forgot-password form -->
        <form class="auth-form" method="POST" action="<?= url('/forgot-password.php') ?>" novalidate>
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="you@example.com"
                    required
                    autocomplete="email"
                >
            </div>

            <button type="submit" class="auth-submit">Send Reset Link</button>
        </form>

        <hr class="auth-divider">

        <div class="auth-links">
            Remembered your password?
            <a href="<?= url('/login.php') ?>">Sign In</a>
        </div>

    </div><!-- /.auth-card -->
</main>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
