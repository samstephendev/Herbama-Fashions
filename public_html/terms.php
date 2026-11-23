<?php
/**
 * Heramba Fashion — Terms & Conditions
 * =======================================
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/config/bootstrap.php';

$pageTitle = 'Terms &amp; Conditions — Heramba Fashion';
$pageDesc  = 'Terms and conditions governing the use of Heramba Fashion, order placement, custom tailoring, and payments.';
$bodyClass = 'page-static page-terms';

require_once APP_ROOT . '/includes/header.php';
?>

<div class="collection-hero">
    <div class="container">
        <h1 class="collection-hero__title">Terms &amp; Conditions</h1>
        <p class="collection-hero__tagline">Policies and Terms Governing Orders &amp; Services at Heramba Fashion</p>
    </div>
</div>

<div class="container" style="max-width:860px; padding-bottom: 4rem;">
    <div style="background:#FFFFFF; border-radius:18px; padding: clamp(1.75rem, 5vw, 3rem); border:1px solid var(--color-border); box-shadow:var(--shadow-sm); line-height:1.8;">
        
        <h2 style="font-family:var(--font-serif); color:var(--color-maroon); font-size:1.4rem; margin-bottom:0.5rem;">1. Acceptance of Terms</h2>
        <p>By browsing Heramba Fashion, placing an order, or communicating with our team on WhatsApp, you agree to comply with and be bound by these Terms and Conditions.</p>

        <h2 style="font-family:var(--font-serif); color:var(--color-maroon); font-size:1.4rem; margin-top:1.5rem; margin-bottom:0.5rem;">2. Order Placement &amp; Payment Verification</h2>
        <p>Heramba Fashion operates on an <strong>Online Payment Only, No COD</strong> model. Orders are officially scheduled for tailoring and dispatch upon verification of payment via UPI to our registered ID. Placing an item in your shopping cart does not reserve stock until order completion.</p>

        <h2 style="font-family:var(--font-serif); color:var(--color-maroon); font-size:1.4rem; margin-top:1.5rem; margin-bottom:0.5rem;">3. Custom Tailoring Agreement</h2>
        <p>Custom orders require 5 working days for production. Parents are requested to provide accurate measurements. While our master tailors ensure high fidelity to provided dimensions, slight artisanal tolerances (within &plusmn;0.5 inches) are standard in bespoke apparel.</p>

        <h2 style="font-family:var(--font-serif); color:var(--color-maroon); font-size:1.4rem; margin-top:1.5rem; margin-bottom:0.5rem;">4. Product Color Representation</h2>
        <p>We photograph all garments in natural daylight to capture colors accurately. However, slight variations may occur due to device screen calibrations and handloom dye lots.</p>

        <h2 style="font-family:var(--font-serif); color:var(--color-maroon); font-size:1.4rem; margin-top:1.5rem; margin-bottom:0.5rem;">5. Intellectual Property</h2>
        <p>All brand graphics, logos, photos, and descriptions are the exclusive intellectual property of Heramba Fashion. Unauthorized reproduction or use is strictly prohibited.</p>
    </div>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
