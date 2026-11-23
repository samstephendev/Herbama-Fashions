<?php
/**
 * register.php — New account registration
 *
 * Heramba Fashion · Kidswear E-Commerce
 */

require_once dirname(__DIR__) . '/app/config/bootstrap.php';

// ── Redirect already-logged-in users ────────────────────────────────────────
if (Session::isLoggedIn()) {
    header('Location: ' . url('/account.php'));
    exit;
}

// ── Instantiate models ───────────────────────────────────────────────────────
$userModel = new UserModel();
$cartModel = new CartModel();

// ── Preserve form values across failed submissions ───────────────────────────
$formValues = [
    'full_name' => '',
    'email'     => '',
    'phone'     => '',
];

$errors = [];

// ════════════════════════════════════════════════════════════════════════════
// POST HANDLER
// ════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. CSRF check (terminates on failure)
    csrf_verify();

    // 2. Collect & sanitise raw inputs
    $fullName        = Sanitize::text($_POST['full_name']     ?? '');
    $email           = Sanitize::email($_POST['email']        ?? '');
    $phone           = Sanitize::text($_POST['phone']         ?? '');
    $password        = $_POST['password']         ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Repopulate form values for redisplay on error
    $formValues['full_name'] = $fullName;
    $formValues['email']     = $email;
    $formValues['phone']     = $phone;

    // 3. Validate inputs
    if ($fullName === '') {
        $errors[] = 'Full name is required.';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($phone !== '' && !preg_match('/^\d{10}$/', $phone)) {
        $errors[] = 'Phone number must be exactly 10 digits (numbers only).';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    // 4. Attempt registration if no validation errors
    if (empty($errors)) {
        $result = $userModel->register($fullName, $email, $password, $phone);

        if ($result['ok']) {
            // Fetch the full user record so Session::login() has everything it needs
            $user = $userModel->findById($result['user_id']);

            if ($user) {
                // Log the user in
                Session::login($user);

                // Merge any guest cart into the new account
                $guestCart = $_SESSION['cart'] ?? [];
                if (!empty($guestCart)) {
                    $cartModel->mergeGuestCart($result['user_id'], $guestCart);
                    $_SESSION['cart'] = [];
                }

                Session::flash('success', 'Welcome to Heramba Fashion, ' . $fullName . '! 🎉');

                header('Location: ' . url('/account.php'));
                exit;
            }
        }

        // Registration failed — show the error returned by the model
        $errors[] = $result['error'] ?? 'Registration failed. Please try again.';
    }

    // Flash all validation / registration errors
    foreach ($errors as $err) {
        Session::flash('error', $err);
    }
}

// ════════════════════════════════════════════════════════════════════════════
// PAGE SETUP
// ════════════════════════════════════════════════════════════════════════════
$pageTitle = 'Create Account';

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
                         alt="Heramba Fashion logo"
                         width="64" height="64" loading="eager">
                </picture>
                <span style="font-family:var(--font-heading,serif);font-size:1.25rem;color:var(--color-maroon,#6b1a1a);font-weight:700;letter-spacing:0.02em;">Heramba Fashion</span>
                <span style="font-size:0.75rem;text-transform:uppercase;letter-spacing:1.5px;color:var(--color-gold,#C9A24B);font-weight:600;margin-top:-0.3rem;">Dress Every Smile</span>
            </a>
        </div>

        <h1>Create Account</h1>
        <p class="auth-subtitle">Join Heramba Fashion — quality kidswear awaits.</p>

        <!-- Flash messages -->
        <?php foreach (Session::getFlash('success') as $msg): ?>
            <div class="alert alert-success"><?= e($msg) ?></div>
        <?php endforeach; ?>
        <?php foreach (Session::getFlash('error') as $msg): ?>
            <div class="alert alert-error"><?= e($msg) ?></div>
        <?php endforeach; ?>

        <!-- Registration form -->
        <form class="auth-form" method="POST" action="<?= url('/register.php') ?>" novalidate>
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="full_name">Full Name <span style="color:var(--color-maroon)">*</span></label>
                <input
                    type="text"
                    id="full_name"
                    name="full_name"
                    value="<?= e($formValues['full_name']) ?>"
                    placeholder="e.g. Amara Osei"
                    required
                    autocomplete="name"
                >
            </div>

            <div class="form-group">
                <label for="email">Email Address <span style="color:var(--color-maroon)">*</span></label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= e($formValues['email']) ?>"
                    placeholder="you@example.com"
                    required
                    autocomplete="email"
                >
            </div>

            <div class="form-group">
                <label for="phone">Phone Number <span style="color:#aaa;font-weight:400">(optional, 10 digits)</span></label>
                <input
                    type="tel"
                    id="phone"
                    name="phone"
                    value="<?= e($formValues['phone']) ?>"
                    placeholder="0241234567"
                    autocomplete="tel"
                    maxlength="10"
                >
            </div>

            <div class="form-group">
                <label for="password">Password <span style="color:var(--color-maroon)">*</span></label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Min 8 chars, upper, lower &amp; number"
                    required
                    autocomplete="new-password"
                >
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password <span style="color:var(--color-maroon)">*</span></label>
                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    placeholder="Repeat your password"
                    required
                    autocomplete="new-password"
                >
            </div>

            <button type="submit" class="auth-submit">Create My Account</button>
        </form>

        <hr class="auth-divider">

        <div class="auth-links">
            Already have an account?
            <a href="<?= url('/login.php') ?>">Sign In</a>
        </div>

    </div><!-- /.auth-card -->
</main>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
