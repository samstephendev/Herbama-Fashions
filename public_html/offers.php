<?php
/**
 * Heramba Fashion — Special Offers Page
 * ========================================
 * Discounted items, active coupons, and festive deals.
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/config/bootstrap.php';

$pageTitle = 'Exclusive Offers &amp; Promo Codes — Heramba Fashion';
$pageDesc  = 'Save on premium handcrafted kidswear. View active coupon codes and discounted sets for girls, boys, and infants.';
$bodyClass = 'page-collection page-offers';

$productModel = new ProductModel();
$productsData = $productModel->list(['is_offer' => 1], 1, 24);

// Fetch all active coupon offers
$db = db();
$offersStmt = $db->query(
    'SELECT * FROM offers
     WHERE is_active = 1
       AND (starts_at IS NULL OR starts_at <= NOW())
       AND (ends_at IS NULL OR ends_at >= NOW())
     ORDER BY id DESC'
);
$activeOffers = $offersStmt->fetchAll();

require_once APP_ROOT . '/includes/header.php';
?>

<div class="collection-hero">
    <div class="container">
        <h1 class="collection-hero__title">Exclusive Festive Offers</h1>
        <p class="collection-hero__tagline">Handcrafted Luxury Kidswear at Irresistible Celebration Prices</p>
    </div>
</div>

<div class="container" style="padding-bottom: 3.5rem;">
    <!-- Active Coupons Strip -->
    <div style="margin-bottom: 2.5rem;">
        <h2 style="font-size:1.4rem; color:var(--color-maroon); margin-bottom:1rem; text-align:center;">
            Available Coupon Codes
        </h2>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:1.25rem;">
            <?php foreach ($activeOffers as $offer): ?>
                <div style="background:var(--color-blush-card); border:1.5px dashed var(--color-gold); border-radius:14px; padding:1.25rem; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <div style="font-weight:700; color:var(--color-maroon); font-size:1.05rem;"><?= e($offer['title']) ?></div>
                        <div style="font-size:0.8rem; color:var(--color-dark-muted); margin-top:2px;">
                            Min. Order: ₹<?= number_format((float)$offer['min_order_value'], 0) ?>
                        </div>
                    </div>
                    <div>
                        <span style="background:var(--color-maroon); color:#FFF9F3; font-weight:700; font-size:0.85rem; padding:6px 14px; border-radius:9999px; letter-spacing:1px; display:inline-block;">
                            <?= e($offer['code'] ?? 'AUTO') ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- On Sale Products Grid -->
    <div class="collection-header-row">
        <div class="collection-count">
            Showing <strong><?= $productsData['total'] ?></strong> discounted styles
        </div>
    </div>

    <div class="products-grid">
        <?php if (!empty($productsData['items'])): ?>
            <?php foreach ($productsData['items'] as $product): ?>
                <?php require APP_ROOT . '/includes/product-card.php'; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="grid-column: 1 / -1; text-align:center; padding: 3rem; background:#FFFFFF; border-radius:16px;">
                <p style="color:var(--color-dark-muted);">All offers are currently applied at checkout. Browse all products!</p>
                <a href="<?= url('/collection.php') ?>" class="btn-pill btn-pill-primary" style="margin-top:1rem;">Shop Collection</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
