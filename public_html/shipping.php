<?php
/**
 * Heramba Fashion — Shipping & Delivery Policy
 * ==============================================
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/config/bootstrap.php';

$pageTitle = 'Shipping &amp; Delivery Policy — Heramba Fashion';
$pageDesc  = 'Pan-India express delivery and direct international dispatch to UAE/Dubai. Transparent delivery timelines and policies.';
$bodyClass = 'page-static page-shipping';

require_once APP_ROOT . '/includes/header.php';
?>

<div class="collection-hero">
    <div class="container">
        <h1 class="collection-hero__title">Shipping &amp; Delivery Policy</h1>
        <p class="collection-hero__tagline">Delivering Handcrafted Happiness Across India and to UAE / Dubai</p>
    </div>
</div>

<div class="container" style="max-width:860px; padding-bottom: 4rem;">
    <div style="background:#FFFFFF; border-radius:18px; padding: clamp(1.75rem, 5vw, 3rem); border:1px solid var(--color-border); box-shadow:var(--shadow-sm); line-height:1.8;">
        
        <h2 style="font-family:var(--font-serif); color:var(--color-maroon); font-size:1.5rem; margin-bottom:0.75rem;">1. Domestic Shipping Across Pan-India</h2>
        <p>We deliver across all serviceable PIN codes throughout India in partnership with reputed courier networks (BlueDart, Delhivery, DTDC, XpressBees):</p>
        <ul style="list-style:disc; padding-left:1.5rem; margin-bottom:1.5rem;">
            <li><strong>Standard Delivery:</strong> 3 &ndash; 5 working days from order dispatch.</li>
            <li><strong>Ready-to-Ship Sizes (0-3 Years):</strong> Dispatched within 24 to 48 hours.</li>
            <li><strong>Flat Shipping Rate:</strong> ₹99 across India. <strong>FREE Shipping</strong> on all domestic orders above ₹999.</li>
            <li><strong>Tracking:</strong> Real-time tracking link sent via SMS and WhatsApp as soon as your parcel is handed to the courier.</li>
        </ul>

        <h2 style="font-family:var(--font-serif); color:var(--color-maroon); font-size:1.5rem; margin-bottom:0.75rem;">2. International Dispatch to UAE / Dubai</h2>
        <p>For our valued families living in the UAE (Dubai, Abu Dhabi, Sharjah, Ajman, RAK, Fujairah, UAQ):</p>
        <ul style="list-style:disc; padding-left:1.5rem; margin-bottom:1.5rem;">
            <li><strong>Air Express Courier:</strong> Dispatched directly from our Indian atelier via international air express.</li>
            <li><strong>Transit Timeline:</strong> 5 &ndash; 7 business days from dispatch date.</li>
            <li><strong>International Shipping Fee:</strong> Flat ₹1,499 (approx. AED 65) per order.</li>
            <li><strong>Customs &amp; Duties:</strong> Any regional customs duties or VAT applicable at UAE import are handled transparently; our team coordinates directly with you on WhatsApp.</li>
        </ul>

        <h2 style="font-family:var(--font-serif); color:var(--color-maroon); font-size:1.5rem; margin-bottom:0.75rem;">3. Custom Orders Timeline</h2>
        <p>Custom-tailored outfits require <strong>5 working days</strong> for careful pattern drafting, cutting, hand embroidery, stitching, and quality inspection before entering the courier stream.</p>

        <h2 style="font-family:var(--font-serif); color:var(--color-maroon); font-size:1.5rem; margin-bottom:0.75rem;">4. Payment Policy Reminder</h2>
        <div style="background:var(--color-blush-card); padding:1rem 1.25rem; border-radius:12px; border-left:4px solid var(--color-maroon); margin-top:0.75rem;">
            <strong>Online Payment Only, No COD:</strong> All orders are confirmed upon receipt of payment via instant UPI on WhatsApp. We do not support Cash on Delivery.
        </div>
    </div>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
