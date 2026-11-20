<?php
/**
 * Herbama Fashions — New Arrivals Page
 * =====================================
 * Displays fresh designs across all categories.
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/config/bootstrap.php';

$pageTitle = 'New Arrivals 2024 — Herbama Fashions';
$pageDesc  = 'Explore our latest collection of handcrafted kidswear. Fresh frocks, party lehengas, cotton dungarees, and festive kurta pyjama.';
$bodyClass = 'page-collection page-new-arrivals';

$productModel = new ProductModel();
$productsData = $productModel->list(['is_new' => 1], 1, 24);
$promoOffer   = $productModel->getActivePromo();

require_once APP_ROOT . '/includes/header.php';
?>

<div class="collection-hero">
    <div class="container">
        <h1 class="collection-hero__title">New Arrivals 2024</h1>
        <p class="collection-hero__tagline">Freshly Crafted Boutique Kidswear for Every Special Moment</p>
    </div>
</div>

<div class="container" style="padding-bottom: 3.5rem;">
    <!-- Filter Bar -->
    <div class="collection-header-row" style="margin-top:1rem;">
        <div class="collection-count">
            Showing <strong><?= $productsData['total'] ?></strong> fresh styles
        </div>
        <div class="sort-select-wrapper">
            <span style="color:var(--color-dark-muted); font-size:0.875rem;">Showing newest designs</span>
        </div>
    </div>

    <!-- Products Grid -->
    <div class="products-grid">
        <?php if (!empty($productsData['items'])): ?>
            <?php foreach ($productsData['items'] as $product): ?>
                <?php require APP_ROOT . '/includes/product-card.php'; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="grid-column: 1 / -1; text-align:center; padding: 3rem; background:#FFFFFF; border-radius:16px;">
                <p style="color:var(--color-dark-muted);">No new arrivals at this moment. Please check our main collection!</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
