<?php
/**
 * logout.php — Session termination handler
 *
 * Heramba Fashion · Kidswear E-Commerce
 *
 * Security model:
 *   • Logout MUST be a POST action to prevent CSRF-driven forced logout via
 *     a simple <img> or <link> tag.
 *   • GET requests display a self-submitting form so that plain <a href>
 *     logout links still work (the page loads, the form auto-submits via JS,
 *     and the user is logged out without any extra click).
 *   • The auto-submit form is CSRF-protected, so it is safe even with JS.
 */

require_once dirname(__DIR__) . '/app/config/bootstrap.php';

// ════════════════════════════════════════════════════════════════════════════
// POST HANDLER — actual logout
// ════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Verify CSRF token before destroying the session
    csrf_verify();

    // Destroy session and clear all data
    Session::logout();

    // Redirect to home page
    header('Location: ' . url('/'));
    exit;
}

// ════════════════════════════════════════════════════════════════════════════
// GET — render an auto-submitting form (graceful fallback for <a href> links)
// ════════════════════════════════════════════════════════════════════════════

// If the user is not logged in at all, just send them home immediately.
if (!Session::isLoggedIn()) {
    header('Location: ' . url('/'));
    exit;
}

// Render a minimal page that immediately POSTs the logout form.
// No header/footer is needed here — the page should vanish instantly.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signing out… — Heramba Fashion</title>
    <style>
        body {
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            font-family: 'Poppins', sans-serif;
            background: #FFF9F3;
            color: #7A1F3D;
            font-size: 1rem;
        }
        p { opacity: 0.7; }
    </style>
</head>
<body>
    <p>Signing you out&hellip;</p>

    <!--
        Auto-submit form — fires immediately via JS.
        If JS is disabled the user sees a "Sign Out" button.
    -->
    <form id="logout-form" method="POST" action="<?= url('/logout.php') ?>" style="display:none;">
        <?= csrf_field() ?>
        <button type="submit">Sign Out</button>
    </form>

    <script>
        // Show the form briefly in case JS loads but submit fails
        document.getElementById('logout-form').style.display = 'block';
        // Submit immediately
        document.getElementById('logout-form').submit();
    </script>
</body>
</html>
