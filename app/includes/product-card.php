<?php
/**
 * Product Card Component Include
 * ===============================
 * Reusable card representing a product item in collection, search, and related carousels.
 * Expected parameter: $product (array)
 */
declare(strict_types=1);

if (!isset($product) || !is_array($product)) {
    return;
}

$pId = (int)($product['id'] ?? 0);
$pName = (string)($product['name'] ?? '');
$pSlug = (string)($product['slug'] ?? '');
$pCatName = (string)($product['category_name'] ?? 'Kidswear');
$pPrice = (float)($product['base_price'] ?? 0);
$pCompare = !empty($product['compare_price']) ? (float)$product['compare_price'] : null;
$pDiscount = (int)($product['discount_pct'] ?? 0);
$pImg = !empty($product['primary_image']) ? $product['primary_image'] : asset('/assets/images/placeholder.svg');
$pStock = isset($product['stock_total']) ? (int)$product['stock_total'] : 10;
$pIsNew = !empty($product['is_new']);
$pIsFeatured = !empty($product['is_featured']);
$pSizes = !empty($product['available_sizes']) ? (string)$product['available_sizes'] : '0-6Y';

// Calculate discount pct if not precomputed
if ($pDiscount <= 0 && $pCompare !== null && $pCompare > $pPrice) {
    $pDiscount = (int)round((($pCompare - $pPrice) / $pCompare) * 100);
}

// Check wishlist state for logged in user
$isInWishlist = false;
if (Session::isLoggedIn()) {
    static $userWishlist = null;
    if ($userWishlist === null) {
        $prodModel = new ProductModel();
        $userWishlist = $prodModel->getWishlistIds((int)Session::userId());
    }
    $isInWishlist = in_array($pId, $userWishlist, true);
}
?>
<div class="product-card" data-product-id="<?= $pId ?>">
    <!-- Image Box -->
    <div class="product-card__image-box">
        <a href="<?= e(url('/product.php?slug=' . urlencode($pSlug))) ?>" aria-label="View <?= e($pName) ?>">
            <img src="<?= e($pImg) ?>" 
                 alt="<?= e($pName) ?>" 
                 class="product-card__img" 
                 loading="lazy" 
                 width="300" 
                 height="380"
                 onerror="this.src='<?= e(asset('/assets/images/placeholder.svg')) ?>';">
        </a>

        <!-- Badges -->
        <?php if ($pIsNew): ?>
            <span class="product-card__badge">New Arrival</span>
        <?php elseif ($pIsFeatured): ?>
            <span class="product-card__badge product-card__badge--bestseller">Bestseller</span>
        <?php endif; ?>

        <!-- Wishlist Heart Button -->
        <button type="button" 
                class="product-card__wishlist-btn <?= $isInWishlist ? 'is-active' : '' ?>" 
                data-wishlist-id="<?= $pId ?>" 
                aria-label="Add to wishlist">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="<?= $isInWishlist ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
            </svg>
        </button>

        <!-- Stock Status Overlays -->
        <?php if ($pStock <= 0): ?>
            <div class="product-card__out-of-stock">
                <span class="product-card__out-of-stock-pill">Out of Stock</span>
            </div>
        <?php elseif ($pStock <= 5): ?>
            <span class="product-card__low-stock">Only <?= $pStock ?> left!</span>
        <?php endif; ?>
    </div>

    <!-- Content -->
    <div class="product-card__content">
        <span class="product-card__category"><?= e($pCatName) ?></span>
        
        <h3 class="product-card__title">
            <a href="<?= e(url('/product.php?slug=' . urlencode($pSlug))) ?>"><?= e($pName) ?></a>
        </h3>

        <!-- Rating Stars -->
        <div class="product-card__rating" aria-label="Rating 4.9 out of 5 stars">
            <span class="product-card__stars">★★★★★</span>
            <span>4.9 (<?= max(12, ($pId * 7) % 60 + 8) ?>)</span>
        </div>

        <!-- Pricing -->
        <div class="product-card__price-row">
            <span class="product-card__price">₹<?= number_format($pPrice, 0) ?></span>
            <?php if ($pCompare !== null && $pCompare > $pPrice): ?>
                <span class="product-card__compare-price">₹<?= number_format($pCompare, 0) ?></span>
            <?php endif; ?>
            <?php if ($pDiscount > 0): ?>
                <span class="product-card__discount-pill"><?= $pDiscount ?>% OFF</span>
            <?php endif; ?>
        </div>

        <!-- Footer: Age chip and Add to Cart -->
        <div class="product-card__footer">
            <span class="product-card__age-chip"><?= e($pSizes) ?></span>
            
            <a href="<?= e(url('/product.php?slug=' . urlencode($pSlug))) ?>" 
               class="product-card__cart-btn" 
               aria-label="Select options for <?= e($pName) ?>"
               title="View Product Details &amp; Sizes">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                </svg>
            </a>
        </div>
    </div>
</div>
