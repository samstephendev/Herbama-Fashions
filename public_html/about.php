<?php
/**
 * Heramba Fashion — About Us / Our Story
 * =========================================
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/config/bootstrap.php';

$pageTitle = 'Our Story — Heramba Fashion';
$pageDesc  = 'Learn about Heramba Fashion, our passion for handcrafted kidswear, delicate fabrics, and tailoring joy for little ones.';
$bodyClass = 'page-static page-about';

require_once APP_ROOT . '/includes/header.php';
?>

<div class="collection-hero">
    <div class="container">
        <h1 class="collection-hero__title">Our Story</h1>
        <p class="collection-hero__tagline">Handcrafting Love, Elegance &amp; Comfort for Every Little Fashionista</p>
    </div>
</div>

<div class="container" style="max-width:860px; padding-bottom: 4rem;">
    <div style="background:#FFFFFF; border-radius:18px; padding: clamp(1.75rem, 5vw, 3rem); border:1px solid var(--color-border); box-shadow:var(--shadow-sm); line-height:1.8;">
        
        <div style="text-align:center; margin-bottom:2rem;">
            <picture>
                <source srcset="<?= e(asset('/assets/images/brand/logo-128.webp')) ?> 2x,
                                <?= e(asset('/assets/images/brand/logo-64.webp')) ?> 1x"
                        type="image/webp">
                <img src="<?= e(asset('/assets/images/brand/logo-128.png')) ?>"
                     alt="Heramba Fashion logo"
                     width="72" height="72"
                     style="width:72px; height:72px; margin:0 auto 1rem; border-radius:50%; display:block;">
            </picture>
            <h2 style="font-family:var(--font-serif); color:var(--color-maroon); font-size:1.8rem;">A Dream Born Out of Love for Little Ones</h2>
        </div>

        <p>At <strong>Heramba Fashion</strong>, we believe every child’s wardrobe should feel like a warm, comforting hug while looking breathtakingly royal. What began as a boutique passion project for designing bespoke celebratory outfits has blossomed into a cherished kidswear label loved by parents across India and in the UAE.</p>
        <p style="font-style:italic;color:var(--color-gold-dark,#9B7828);font-size:1rem;margin-top:0.25rem;">“Dress Every Smile”</p>

        <p style="margin-top:1rem;">We know children live in motion — running, spinning, jumping, and playing. That's why our designs prioritize delicate baby skin above all else: breathability, soft cotton linings, zero rough inner seams, and gentle elastic waistbands that never dig in.</p>

        <div style="margin:2.5rem 0; padding:1.5rem; background:var(--color-blush-card); border-left:4px solid var(--color-maroon); border-radius:0 12px 12px 0;">
            <h3 style="font-family:var(--font-serif); color:var(--color-maroon); font-size:1.25rem; margin-bottom:0.5rem;">The Heramba Fashion Promise</h3>
            <p style="margin:0; font-size:0.95rem; color:var(--color-dark);">
                “Pure handcrafted fabrics, artisanal Indian heritage motifs, and custom fits ready within 5 working days — delivered directly to your doorstep with love.”
            </p>
        </div>

        <h3 style="font-family:var(--font-serif); color:var(--color-maroon); font-size:1.4rem; margin-top:2rem; margin-bottom:0.75rem;">Pan-India Craft &amp; UAE International Dispatch</h3>
        <p>Whether you're looking for lightweight everyday cotton frocks, rugged playwear shorts, or heirloom zari lehengas and silk sherwanis, our workshop in India crafts small, exquisite batches. We take pride in delivering to every single pin code in India as well as providing express air couriers directly to Dubai and the UAE.</p>

        <div style="text-align:center; margin-top:2.5rem;">
            <a href="<?= url('/collection.php') ?>" class="btn-pill btn-pill-primary">Explore Our Collections &rarr;</a>
        </div>
    </div>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
