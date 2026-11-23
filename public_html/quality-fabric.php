<?php
/**
 * Heramba Fashion — Quality & Fabric Care
 * ==========================================
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/config/bootstrap.php';

$pageTitle = 'Quality &amp; Fabric Care — Heramba Fashion';
$pageDesc  = 'Learn about our baby-safe natural fabrics, breathable cottons, pure silks, and garment care instructions.';
$bodyClass = 'page-static page-quality';

require_once APP_ROOT . '/includes/header.php';
?>

<div class="collection-hero">
    <div class="container">
        <h1 class="collection-hero__title">Quality &amp; Fabric Standards</h1>
        <p class="collection-hero__tagline">Gentle On Sensitive Skin, Durable For Playful Days</p>
    </div>
</div>

<div class="container" style="max-width:860px; padding-bottom: 4rem;">
    <div style="background:#FFFFFF; border-radius:18px; padding: clamp(1.75rem, 5vw, 3rem); border:1px solid var(--color-border); box-shadow:var(--shadow-sm); line-height:1.8;">
        
        <h2 style="font-family:var(--font-serif); color:var(--color-maroon); font-size:1.6rem; margin-bottom:0.75rem;">Natural &amp; Baby-Safe Materials</h2>
        <p>A child's skin is up to 30% thinner than an adult’s, making it significantly more sensitive to synthetic dyes and rough polyesters. At Heramba Fashion, our philosophy is rooted in gentle natural textiles:</p>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.25rem; margin:1.75rem 0;">
            <div style="background:var(--color-blush-card); padding:1.25rem; border-radius:12px;">
                <h4 style="font-family:var(--font-serif); color:var(--color-maroon); font-size:1.1rem; margin-bottom:0.35rem;">Pure Combed Cotton</h4>
                <p style="font-size:0.85rem; color:var(--color-dark-muted); margin:0;">Extra-long staple fibers that feel silky smooth against the skin and improve in softness with every wash.</p>
            </div>
            <div style="background:var(--color-blush-card); padding:1.25rem; border-radius:12px;">
                <h4 style="font-family:var(--font-serif); color:var(--color-maroon); font-size:1.1rem; margin-bottom:0.35rem;">Mulmul &amp; Voile Linings</h4>
                <p style="font-size:0.85rem; color:var(--color-dark-muted); margin:0;">Even in our grandest festive lehengas and gowns, the inner lining touching the skin is always 100% lightweight cotton mulmul.</p>
            </div>
            <div style="background:var(--color-blush-card); padding:1.25rem; border-radius:12px;">
                <h4 style="font-family:var(--font-serif); color:var(--color-maroon); font-size:1.1rem; margin-bottom:0.35rem;">Heritage Art Silks</h4>
                <p style="font-size:0.85rem; color:var(--color-dark-muted); margin:0;">Rich luster and regal drape without the heavy stiffness or chemical coatings of mass-manufactured fabrics.</p>
            </div>
            <div style="background:var(--color-blush-card); padding:1.25rem; border-radius:12px;">
                <h4 style="font-family:var(--font-serif); color:var(--color-maroon); font-size:1.1rem; margin-bottom:0.35rem;">Soft Stretch Waistbands</h4>
                <p style="font-size:0.85rem; color:var(--color-dark-muted); margin:0;">Gentle, covered elastics with internal drawstring ties for a snug, secure fit that never leaves marks on baby tummies.</p>
            </div>
        </div>

        <h2 style="font-family:var(--font-serif); color:var(--color-maroon); font-size:1.6rem; margin-top:2rem; margin-bottom:0.75rem;">Wash &amp; Garment Care Guide</h2>
        <ul style="list-style:disc; padding-left:1.5rem;">
            <li><strong>Everyday Cotton &amp; Dungarees:</strong> Machine wash cold on a gentle cycle or hand wash with mild baby-safe liquid detergent.</li>
            <li><strong>Festive Wear &amp; Lehengas:</strong> Dry clean or gentle hand dip-wash in cold water. Do not wring or rub embroidery.</li>
            <li><strong>Drying:</strong> Hang inside out in the shade. Direct tropical sunlight can cause natural pastel dyes to fade prematurely.</li>
            <li><strong>Ironing:</strong> Use medium warmth on reverse side. When ironing near zari borders, place a soft cotton handkerchief on top.</li>
        </ul>
    </div>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
