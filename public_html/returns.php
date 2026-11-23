<?php
/**
 * Heramba Fashion — Returns & Exchange Policy
 * ==============================================
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/config/bootstrap.php';

$pageTitle = 'Returns &amp; Exchange Policy — Heramba Fashion';
$pageDesc  = '7-day size exchange guarantee for kidswear. Clear, fair, and hassle-free return and exchange terms.';
$bodyClass = 'page-static page-returns';

require_once APP_ROOT . '/includes/header.php';
?>

<div class="collection-hero">
    <div class="container">
        <h1 class="collection-hero__title">Returns &amp; Exchange Policy</h1>
        <p class="collection-hero__tagline">7-Day Hassle-Free Size Exchange for Growing Little Ones</p>
    </div>
</div>

<div class="container" style="max-width:860px; padding-bottom: 4rem;">
    <div style="background:#FFFFFF; border-radius:18px; padding: clamp(1.75rem, 5vw, 3rem); border:1px solid var(--color-border); box-shadow:var(--shadow-sm); line-height:1.8;">
        
        <h2 style="font-family:var(--font-serif); color:var(--color-maroon); font-size:1.5rem; margin-bottom:0.75rem;">1. Size Exchange (7 Days)</h2>
        <p>Children grow fast! If the size you ordered doesn't fit your little one comfortably, we gladly offer a <strong>size exchange within 7 days</strong> of delivery:</p>
        <ul style="list-style:disc; padding-left:1.5rem; margin-bottom:1.5rem;">
            <li>The item must be unused, unwashed, and in its original condition with all tags intact.</li>
            <li>Simply message our customer concierge on WhatsApp with your Order Reference (e.g. <code>HF-XXXXXX</code>) and the requested replacement size.</li>
            <li>We will schedule a reverse pickup from your address (serviceable PIN codes) or guide you on the simple dispatch procedure.</li>
        </ul>

        <h2 style="font-family:var(--font-serif); color:var(--color-maroon); font-size:1.5rem; margin-bottom:0.75rem;">2. Custom-Tailored Orders</h2>
        <p>Because bespoke custom orders are made to the exact unique measurements and color specifications provided by the parent, they <strong>cannot be returned or cancelled once tailoring has commenced</strong>. However, in the rare event of a transit defect or measurement error on our part, our atelier will gladly alter or remake the garment free of charge.</p>

        <h2 style="font-family:var(--font-serif); color:var(--color-maroon); font-size:1.5rem; margin-bottom:0.75rem;">3. Damaged or Defective Items</h2>
        <p>Every piece leaves our atelier after strict three-step quality checks. If your parcel arrives damaged in transit, please share an unboxing photo/video within 48 hours of delivery on WhatsApp, and we will immediately dispatch a fresh replacement.</p>

        <h2 style="font-family:var(--font-serif); color:var(--color-maroon); font-size:1.5rem; margin-bottom:0.75rem;">4. Refunds</h2>
        <p>Where exchange is not possible due to out-of-stock sizes, refunds are processed back to the original source UPI account within 3 to 5 business days after inspection at our warehouse.</p>
    </div>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
