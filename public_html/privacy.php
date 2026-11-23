<?php
/**
 * Heramba Fashion — Privacy Policy
 * ===================================
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/config/bootstrap.php';

$pageTitle = 'Privacy Policy — Heramba Fashion';
$pageDesc  = 'Heramba Fashion respects your privacy. Learn how we securely protect customer information and order details.';
$bodyClass = 'page-static page-privacy';

require_once APP_ROOT . '/includes/header.php';
?>

<div class="collection-hero">
    <div class="container">
        <h1 class="collection-hero__title">Privacy Policy</h1>
        <p class="collection-hero__tagline">Your Privacy &amp; Trust Are Sacred to Heramba Fashion</p>
    </div>
</div>

<div class="container" style="max-width:860px; padding-bottom: 4rem;">
    <div style="background:#FFFFFF; border-radius:18px; padding: clamp(1.75rem, 5vw, 3rem); border:1px solid var(--color-border); box-shadow:var(--shadow-sm); line-height:1.8;">
        
        <h2 style="font-family:var(--font-serif); color:var(--color-maroon); font-size:1.4rem; margin-bottom:0.5rem;">1. Information We Collect</h2>
        <p>When you browse our boutique, create an account, or place an order via WhatsApp, we collect only the necessary details required to fulfill and deliver your handcrafted garments:</p>
        <ul style="list-style:disc; padding-left:1.5rem; margin-bottom:1.5rem;">
            <li>Name, delivery address, phone / WhatsApp number, and email address.</li>
            <li>Custom tailoring measurements and sizing notes provided by you.</li>
            <li>UPI transaction identifiers submitted for payment confirmation.</li>
        </ul>

        <h2 style="font-family:var(--font-serif); color:var(--color-maroon); font-size:1.4rem; margin-bottom:0.5rem;">2. How Your Information Is Used</h2>
        <p>We use your information solely to:</p>
        <ul style="list-style:disc; padding-left:1.5rem; margin-bottom:1.5rem;">
            <li>Process, tailor, and dispatch your kidswear orders.</li>
            <li>Send WhatsApp / SMS delivery updates and tracking links.</li>
            <li>Respond to sizing and styling consultations.</li>
            <li>Prevent fraudulent transactions and unauthorized access.</li>
        </ul>

        <h2 style="font-family:var(--font-serif); color:var(--color-maroon); font-size:1.4rem; margin-bottom:0.5rem;">3. We Never Sell Your Data</h2>
        <p>Heramba Fashion does not sell, lease, or monetize your personal details to third-party advertisers. Information is shared only with verified logistics partners (such as Delhivery, BlueDart, DTDC) strictly for parcel delivery.</p>

        <h2 style="font-family:var(--font-serif); color:var(--color-maroon); font-size:1.4rem; margin-bottom:0.5rem;">4. Data Security</h2>
        <p>All passwords are encrypted with industry-standard Argon2id / bcrypt hashing. Sessions use secure, httpOnly cookies. Our systems undergo routine vulnerability checks to maintain high security standards.</p>

        <h2 style="font-family:var(--font-serif); color:var(--color-maroon); font-size:1.4rem; margin-bottom:0.5rem;">5. Contacting Us</h2>
        <p>If you have questions regarding your data or wish to delete your account, please reach out to us at <a href="mailto:<?= e(ADMIN_EMAIL) ?>" style="color:var(--color-maroon); font-weight:600;"><?= e(ADMIN_EMAIL) ?></a>.</p>
    </div>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
