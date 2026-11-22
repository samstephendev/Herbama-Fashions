<?php
/**
 * reset-password.php — Set a new password via a reset token
 *
 * Herbama Fashions · Kidswear E-Commerce
 *
 * Flow:
 *   GET  → verify token → show form (or error card if invalid/expired)
 *   POST → csrf_verify → validate password strength → resetPasswordWithToken
 *          → success: redirect to login | failure: show error
 */

require_once dirname(__DIR__) . '/app/config/bootstrap.php';

// ── Redirect logged-in users ─────────────────────────────────────────────────
if (Session::isLoggedIn()) {
    header('Location: ' . url('/account.php'));
    exit;
}

// ── Instantiate model ────────────────────────────────────────────────────────
$userModel = new UserModel();

// ── Token/email come from query string on GET, from hidden fields on POST ────
$email    = Sanitize::email($_REQUEST['email'] ?? '');
$rawToken = Sanitize::text($_REQUEST['token']  ?? '');

// Flags
$tokenValid   = false;
$showForm     = false;
$tokenChecked = false;

// ════════════════════════════════════════════════════════════════════════════
// POST HANDLER
// ════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. CSRF check
    csrf_verify();

    // 2. Re-read credentials from hidden POST fields
    $email    = Sanitize::email($_POST['email'] ?? '');
    $rawToken = Sanitize::text($_POST['token']  ?? '');

    // 3. Collect passwords
    $newPassword     = $_POST['new_password']     ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $postErrors = [];

    // 4. Basic presence check
    if ($email === '' || $rawToken === '') {
        $postErrors[] = 'Invalid or expired reset link. Please request a new one.';
    }

    // 5. Password strength validation
    if (empty($postErrors)) {
        if (strlen($newPassword) < 8) {
            $postErrors[] = 'Password must be at least 8 characters long.';
        } elseif (!preg_match('/[A-Z]/', $newPassword)) {
            $postErrors[] = 'Password must contain at least one uppercase letter.';
        } elseif (!preg_match('/[a-z]/', $newPassword)) {
            $postErrors[] = 'Password must contain at least one lowercase letter.';
        } elseif (!preg_match('/[0-9]/', $newPassword)) {
            $postErrors[] = 'Password must contain at least one number.';
        }

        if ($newPassword !== $confirmPassword) {
            $postErrors[] = 'Passwords do not match.';
        }
    }

    if (empty($postErrors)) {
        // 6. Attempt reset
        $result = $userModel->resetPasswordWithToken($email, $rawToken, $newPassword);

        if ($result['ok']) {
            Session::flash('success', 'Your password has been reset successfully. Please log in with your new password.');
            header('Location: ' . url('/login.php'));
            exit;
        }

        $postErrors[] = $result['error'] ?? 'Password reset failed. The link may have expired. Please request a new one.';
    }

    // Flash errors and re-show the form
    foreach ($postErrors as $err) {
        Session::flash('error', $err);
    }

    // Re-verify token so we can redisplay the form
    $tokenValid = $userModel->verifyPasswordResetToken($email, $rawToken);
    $showForm   = $tokenValid;
    $tokenChecked = true;

} else {
    // ════════════════════════════════════════════════════════════════════════
    // GET — Verify the token from the query string
    // ════════════════════════════════════════════════════════════════════════
    if ($email !== '' && $rawToken !== '') {
        $tokenValid = $userModel->verifyPasswordResetToken($email, $rawToken);
        $showForm   = $tokenValid;
    }
    $tokenChecked = true;
}

// ════════════════════════════════════════════════════════════════════════════
// PAGE SETUP
// ════════════════════════════════════════════════════════════════════════════
$pageTitle = 'Reset Password';

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
.error-icon { font-size: 2.5rem; text-align: center; margin-bottom: 0.75rem; }
.password-hint { font-size: 0.78rem; color: #999; margin-top: 0.3rem; }
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

        <?php if ($tokenChecked && !$tokenValid && !$showForm): ?>
            <!-- ── Invalid / expired token ───────────────────────────────── -->
            <div class="error-icon">🔒</div>
            <h1>Link Invalid</h1>
            <p class="auth-subtitle">This password reset link has expired or is invalid.</p>

            <div class="alert alert-error">
                Reset links are valid for <strong>1 hour</strong> and can only be used once.
                Please request a new one below.
            </div>

            <a href="<?= url('/forgot-password.php') ?>" class="auth-submit" style="display:block;text-align:center;text-decoration:none;line-height:2.2;">
                Request New Reset Link
            </a>

        <?php else: ?>
            <!-- ── Valid token — show the new-password form ──────────────── -->
            <h1>Reset Password</h1>
            <p class="auth-subtitle">Choose a strong new password for your account.</p>

            <!-- Flash messages -->
            <?php foreach (Session::getFlash('success') as $msg): ?>
                <div class="alert alert-success"><?= e($msg) ?></div>
            <?php endforeach; ?>
            <?php foreach (Session::getFlash('error') as $msg): ?>
                <div class="alert alert-error"><?= e($msg) ?></div>
            <?php endforeach; ?>

            <form class="auth-form" method="POST" action="<?= url('/reset-password.php') ?>" novalidate>
                <?= csrf_field() ?>

                <!-- Pass credentials through as hidden fields -->
                <input type="hidden" name="email"  value="<?= e($email) ?>">
                <input type="hidden" name="token"  value="<?= e($rawToken) ?>">

                <div class="form-group">
                    <label for="new_password">New Password <span style="color:var(--color-maroon)">*</span></label>
                    <input
                        type="password"
                        id="new_password"
                        name="new_password"
                        placeholder="Min 8 chars, upper, lower &amp; number"
                        required
                        autocomplete="new-password"
                    >
                    <p class="password-hint">At least 8 characters with uppercase, lowercase, and a number.</p>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password <span style="color:var(--color-maroon)">*</span></label>
                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        placeholder="Repeat your new password"
                        required
                        autocomplete="new-password"
                    >
                </div>

                <button type="submit" class="auth-submit">Reset Password</button>
            </form>

        <?php endif; ?>

        <hr class="auth-divider">

        <div class="auth-links">
            <a href="<?= url('/login.php') ?>">← Back to Sign In</a>
        </div>

    </div><!-- /.auth-card -->
</main>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
