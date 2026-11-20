<?php
/**
 * Herbama Fashions — Why Herbama
 * ================================
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/config/bootstrap.php';

$pageTitle = 'Why Herbama — Handcrafted Kids Luxury';
$pageDesc  = 'Discover why parents choose Herbama Fashions for premium kidswear: child-safe fabrics, 5-day custom fits, ready-to-ship baby wear, and Pan-India & UAE delivery.';
$bodyClass = 'page-static page-why-herbama';

require_once APP_ROOT . '/includes/header.php';
?>

<div class="collection-hero">
    <div class="container">
        <h1 class="collection-hero__title">Why Choose Herbama?</h1>
        <p class="collection-hero__tagline">Six Reasons Why Parents Trust Us for Their Little Ones</p>
    </div>
</div>

<div class="container" style="max-width:960px; padding-bottom: 4rem;">
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:1.5rem;">
        
        <div style="background:#FFFFFF; border:1px solid var(--color-border); border-radius:16px; padding:1.75rem; box-shadow:var(--shadow-sm);">
            <div style="font-size:2.2rem; margin-bottom:0.75rem;">🌿</div>
            <h3 style="font-family:var(--font-serif); color:var(--color-maroon); font-size:1.25rem; margin-bottom:0.5rem;">100% Breathable Fabrics</h3>
            <p style="font-size:0.9rem; color:var(--color-dark-muted); line-height:1.6;">We handpick only pure combed cottons, soft malmal linings, and lightweight silks so your child stays happy, sweat-free, and comfy all day long.</p>
        </div>

        <div style="background:#FFFFFF; border:1px solid var(--color-border); border-radius:16px; padding:1.75rem; box-shadow:var(--shadow-sm);">
            <div style="font-size:2.2rem; margin-bottom:0.75rem;">✂️</div>
            <h3 style="font-family:var(--font-serif); color:var(--color-maroon); font-size:1.25rem; margin-bottom:0.5rem;">Customization in 5 Days</h3>
            <p style="font-size:0.9rem; color:var(--color-dark-muted); line-height:1.6;">Need a tailored fit for a special first birthday or family wedding? Our master artisans customize length, waist, and sleeves within just 5 working days.</p>
        </div>

        <div style="background:#FFFFFF; border:1px solid var(--color-border); border-radius:16px; padding:1.75rem; box-shadow:var(--shadow-sm);">
            <div style="font-size:2.2rem; margin-bottom:0.75rem;">👶</div>
            <h3 style="font-family:var(--font-serif); color:var(--color-maroon); font-size:1.25rem; margin-bottom:0.5rem;">0-3 Years Ready to Ship</h3>
            <p style="font-size:0.9rem; color:var(--color-dark-muted); line-height:1.6;">Our most popular infant and toddler sizes are pre-crafted and kept ready in stock to ensure lightning-fast dispatch within 24 to 48 hours.</p>
        </div>

        <div style="background:#FFFFFF; border:1px solid var(--color-border); border-radius:16px; padding:1.75rem; box-shadow:var(--shadow-sm);">
            <div style="font-size:2.2rem; margin-bottom:0.75rem;">🛡️</div>
            <h3 style="font-family:var(--font-serif); color:var(--color-maroon); font-size:1.25rem; margin-bottom:0.5rem;">Zero Scratchy Inners</h3>
            <p style="font-size:0.9rem; color:var(--color-dark-muted); line-height:1.6;">Every seam is cleanly enclosed or taped. No exposed raw nylon threads, itchy tags, or rough zari will ever touch your child’s sensitive skin.</p>
        </div>

        <div style="background:#FFFFFF; border:1px solid var(--color-border); border-radius:16px; padding:1.75rem; box-shadow:var(--shadow-sm);">
            <div style="font-size:2.2rem; margin-bottom:0.75rem;">🌍</div>
            <h3 style="font-family:var(--font-serif); color:var(--color-maroon); font-size:1.25rem; margin-bottom:0.5rem;">Direct UAE / Dubai Delivery</h3>
            <p style="font-size:0.9rem; color:var(--color-dark-muted); line-height:1.6;">Living in Dubai or the UAE? We ship directly to your door with express international tracking and personalized WhatsApp support.</p>
        </div>

        <div style="background:#FFFFFF; border:1px solid var(--color-border); border-radius:16px; padding:1.75rem; box-shadow:var(--shadow-sm);">
            <div style="font-size:2.2rem; margin-bottom:0.75rem;">💬</div>
            <h3 style="font-family:var(--font-serif); color:var(--color-maroon); font-size:1.25rem; margin-bottom:0.5rem;">Human Touch on WhatsApp</h3>
            <p style="font-size:0.9rem; color:var(--color-dark-muted); line-height:1.6;">No annoying chatbots! Talk directly with our boutique styling team on WhatsApp for size advice, fabric photos, and payment assistance.</p>
        </div>

    </div>

    <div style="text-align:center; margin-top:2.5rem;">
        <a href="<?= url('/collection.php') ?>" class="btn-pill btn-pill-primary">Shop Our Collections &rarr;</a>
    </div>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
