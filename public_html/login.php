<?php
/**
 * login.php — User sign-in
 *
 * Herbama Fashions · Kidswear E-Commerce
 */

require_once dirname(__DIR__) . '/app/config/bootstrap.php';

// ── Redirect already-logged-in users ────────────────────────────────────────
if (Session::isLoggedIn()) {
    $dest = (Session::role() === 'admin') ? url('/admin/index.php') : url('/account.php');
    header('Location: ' . $dest);
    exit;
}

// ── Instantiate models ───────────────────────────────────────────────────────
$userModel = new UserModel();
$cartModel = new CartModel();

// ── Capture the ?next= redirect target (preserved across GET/POST) ───────────
$nextRaw = $_GET['next'] ?? $_POST['next'] ?? '';

/**
 * Validate a post-login redirect target.
 * Accepts only paths that start with '/' but NOT '//' (prevents open redirect).
 */
function isValidNext(string $next): bool
{
    return $next !== ''
        && str_starts_with($next, '/')
        && !str_starts_with($next, '//');
}

$safeNext = isValidNext($nextRaw) ? $nextRaw : '';

// ── Repopulate email on failed attempt ──────────────────────────────────────
$emailValue = '';

// ════════════════════════════════════════════════════════════════════════════
// POST HANDLER
// ════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. CSRF check
    csrf_verify();

    // 2. Collect inputs
    $email    = Sanitize::email($_POST['email']    ?? '');
    $password = $_POST['password'] ?? '';

    $emailValue = $email; // repopulate

    // 3. Basic presence validation
    if ($email === '' || $password === '') {
        Session::flash('error', 'Email and password are required.');
    } else {
        // 4. Delegate to model (handles hashing, rate-limiting, lock check)
        $result = $userModel->login($email, $password, $_SERVER['REMOTE_ADDR'] ?? '');

        if ($result['ok']) {
            $user = $result['user'];

            // Start the session
            Session::login($user);

            // Merge guest cart
            $guestCart = $_SESSION['cart'] ?? [];
            if (!empty($guestCart)) {
                $cartModel->mergeGuestCart($user['id'], $guestCart);
                $_SESSION['cart'] = [];
            }

            // Determine redirect destination
            if ($safeNext !== '') {
                $redirectTo = url($safeNext);
            } elseif ($user['role'] === 'admin') {
                $redirectTo = url('/admin/index.php');
            } else {
                $redirectTo = url('/account.php');
            }

            header('Location: ' . $redirectTo);
            exit;

        } else {
            // Login failed
            if (isset($result['locked_until'])) {
                // Show how many minutes remain on the lock
                $lockedUntil    = strtotime($result['locked_until']);
                $minutesRemaining = max(1, (int) ceil(($lockedUntil - time()) / 60));
                Session::flash('error', 'Your account is temporarily locked. Please try again in ' . $minutesRemaining . ' minute(s).');
            } else {
                Session::flash('error', 'Invalid email or password. Please try again.');
            }
        }
    }
}

// ════════════════════════════════════════════════════════════════════════════
// PAGE SETUP
// ════════════════════════════════════════════════════════════════════════════
$pageTitle = 'Sign In';

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
.forgot-link { text-align: right; margin-top: -0.6rem; margin-bottom: 1.1rem; font-size: 0.82rem; }
.forgot-link a { color: var(--color-maroon); text-decoration: none; font-weight: 600; }
</style>';

require_once APP_ROOT . '/includes/header.php';
?>

<main class="auth-page">
    <div class="auth-card">

        <!-- Logo -->
        <div class="auth-logo">
            <a href="<?= url('/') ?>">
                <img src="<?= asset('/assets/images/logo.png') ?>" alt="Herbama Fashions" height="56">
            </a>
        </div>

        <h1>Welcome Back</h1>
        <p class="auth-subtitle">Sign in to your Herbama Fashions account.</p>

        <!-- Flash messages -->
        <?php foreach (Session::getFlash('success') as $msg): ?>
            <div class="alert alert-success"><?= e($msg) ?></div>
        <?php endforeach; ?>
        <?php foreach (Session::getFlash('error') as $msg): ?>
            <div class="alert alert-error"><?= e($msg) ?></div>
        <?php endforeach; ?>

        <!-- Login form -->
        <form class="auth-form" method="POST" action="<?= url('/login.php') . ($safeNext !== '' ? '?next=' . urlencode($safeNext) : '') ?>" novalidate>
            <?= csrf_field() ?>

            <!-- Pass ?next through POST as well -->
            <?php if ($safeNext !== ''): ?>
                <input type="hidden" name="next" value="<?= e($safeNext) ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= e($emailValue) ?>"
                    placeholder="you@example.com"
                    required
                    autocomplete="email"
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Your password"
                    required
                    autocomplete="current-password"
                >
            </div>

            <!-- Forgot password link sits under the password field -->
            <div class="forgot-link">
                <a href="<?= url('/forgot-password.php') ?>">Forgot password?</a>
            </div>

            <button type="submit" class="auth-submit">Sign In</button>
        </form>

        <hr class="auth-divider">

        <div class="auth-links">
            Don't have an account?
            <a href="<?= url('/register.php') ?>">Create one</a>
        </div>

    </div><!-- /.auth-card -->
</main>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
