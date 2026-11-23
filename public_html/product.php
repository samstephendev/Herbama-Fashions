<?php
/**
 * Heramba Fashion — Product Detail Page
 * ========================================
 * Rich gallery, size chips, color swatches, quantity stepper, WhatsApp inquiry,
 * feature strip, tabbed specs, pincode delivery checker, reviews, and related carousel.
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/config/bootstrap.php';

$slug = get('slug');
if (empty($slug)) {
    header('Location: /collection.php', true, 302);
    exit;
}

$productModel = new ProductModel();
$product = $productModel->findBySlug($slug);

if (!$product) {
    http_response_code(404);
    require_once APP_ROOT . '/includes/header.php';
    echo '<div class="container" style="padding:4rem 1rem; text-align:center;">';
    echo '<h1 style="color:var(--color-maroon); font-family:var(--font-serif); margin-bottom:1rem;">Product Not Found</h1>';
    echo '<p style="color:var(--color-dark-muted); margin-bottom:1.5rem;">The product you are looking for may have been moved or is out of stock.</p>';
    echo '<a href="' . url('/collection.php') . '" class="btn-pill btn-pill-primary">Browse All Collections &rarr;</a>';
    echo '</div>';
    require_once APP_ROOT . '/includes/footer.php';
    exit;
}

$pageTitle = e($product['name']) . ' — Heramba Fashion';
$pageDesc  = e($product['short_desc'] ?? $product['name'] . ' - Handcrafted kidswear at Heramba Fashion.');
$bodyClass = 'page-product-detail';

// Variants & Colours
$variants = $product['variants'] ?? [];
$images   = $product['images'] ?? [];

// Calculate colors list
$colors = [];
foreach ($variants as $v) {
    if (!empty($v['color']) && !in_array($v['color'], $colors, true)) {
        $colors[] = $v['color'];
    }
}
$selectedColor = $colors[0] ?? null;

// Primary Image
$primaryImg = '/assets/images/placeholder.svg';
if (!empty($images)) {
    foreach ($images as $img) {
        if (!empty($img['is_primary'])) {
            $primaryImg = $img['image_path'];
            break;
        }
    }
    if ($primaryImg === '/assets/images/placeholder.svg' && isset($images[0]['image_path'])) {
        $primaryImg = $images[0]['image_path'];
    }
}

// Calculate discount
$price = (float)$product['base_price'];
$comparePrice = !empty($product['compare_price']) ? (float)$product['compare_price'] : null;
$discountPct = (int)($product['discount_pct'] ?? 0);
if ($discountPct <= 0 && $comparePrice !== null && $comparePrice > $price) {
    $discountPct = (int)round((($comparePrice - $price) / $comparePrice) * 100);
}

// Subtitle
$subtitle = $product['short_desc'] ?: "Premium Handcrafted Wear for Kids (Sizes 0-6 Years)";

// Reviews
$reviewModel   = new ReviewModel();
$reviewSummary = $reviewModel->getSummary((int)$product['id']);
$reviews       = $reviewModel->getByProduct((int)$product['id']);

// Related Products
$relatedProducts = $productModel->getRelatedProducts((int)$product['id'], (int)($product['category_id'] ?? 0), 4);

// Settings
$settingModel   = new SettingModel();
$waNumber       = (string)$settingModel->get('whatsapp_number', WHATSAPP_NUMBER);
$cleanWaNumber  = preg_replace('/[^0-9]/', '', $waNumber);

require_once APP_ROOT . '/includes/header.php';
?>

<div class="container">
    <!-- ── 1. Breadcrumb Navigation ─────────────────────────────────────── -->
    <nav class="breadcrumb-nav" aria-label="Breadcrumb">
        <a href="<?= url('/index.php') ?>">Home</a>
        <span aria-hidden="true">&rsaquo;</span>
        <a href="<?= url('/collection.php?cat=' . urlencode($product['category_slug'] ?? 'girls')) ?>">
            <?= e($product['category_name'] ?? 'Collection') ?>
        </a>
        <span aria-hidden="true">&rsaquo;</span>
        <span class="current" aria-current="page"><?= e($product['name']) ?></span>
    </nav>

    <!-- ── 2. Product Detail Layout ─────────────────────────────────────── -->
    <div class="product-detail-layout">
        
        <!-- Left: Image Gallery -->
        <div class="product-gallery">
            <div class="product-gallery__thumbs">
                <?php if (!empty($images)): ?>
                    <?php foreach ($images as $idx => $img): ?>
                        <div class="product-thumb <?= $idx === 0 ? 'is-active' : '' ?>" 
                             data-img-src="<?= e($img['image_path']) ?>">
                            <img src="<?= e($img['image_path']) ?>" 
                                 alt="<?= e($product['name']) ?> thumbnail <?= $idx + 1 ?>" 
                                 width="80" 
                                 height="100"
                                 onerror="this.src='/assets/images/placeholder.svg';">
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="product-thumb is-active" data-img-src="/assets/images/placeholder.svg">
                        <img src="/assets/images/placeholder.svg" alt="<?= e($product['name']) ?>" width="80" height="100">
                    </div>
                <?php endif; ?>
            </div>

            <div class="product-gallery__main">
                <img src="<?= e($primaryImg) ?>" 
                     id="product-main-image" 
                     alt="<?= e($product['name']) ?>" 
                     width="550" 
                     height="700"
                     onerror="this.src='/assets/images/placeholder.svg';">
                
                <button type="button" class="product-gallery__zoom-btn" aria-label="Zoom image" title="Zoom">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Right: Information & Action Column -->
        <div class="product-info-col">
            <?php if (!empty($product['is_new'])): ?>
                <span class="product-info__tag">New Arrival</span>
            <?php endif; ?>

            <h1 class="product-info__title"><?= e($product['name']) ?></h1>
            <p class="product-info__subtitle"><?= e($subtitle) ?></p>

            <!-- Rating Stars and Bought Metric -->
            <div class="product-info__meta-strip">
                <div class="product-card__rating" style="margin:0;">
                    <span class="product-card__stars">★★★★★</span>
                    <strong style="color:var(--color-dark);"><?= $reviewSummary['avg_rating'] ?></strong>
                    <span style="color:var(--color-dark-muted);">(<?= $reviewSummary['count'] ?> reviews)</span>
                </div>
                <span class="product-info__bought-badge">🔥 <?= $reviewSummary['bought_count'] ?>+ Bought</span>
            </div>

            <!-- Price Box -->
            <div class="product-price-box">
                <span class="product-price-box__current">₹<?= number_format($price, 0) ?></span>
                <?php if ($comparePrice !== null && $comparePrice > $price): ?>
                    <span class="product-price-box__compare">₹<?= number_format($comparePrice, 0) ?></span>
                <?php endif; ?>
                <?php if ($discountPct > 0): ?>
                    <span class="product-card__discount-pill" style="font-size:12px; padding:3px 10px;">
                        <?= $discountPct ?>% OFF
                    </span>
                <?php endif; ?>
                <span style="font-size:12px; color:var(--color-dark-muted); margin-left:auto;">Inclusive of all taxes</span>
            </div>

            <!-- Option 1: Colour Selection -->
            <?php if (!empty($colors)): ?>
                <div class="product-option-group">
                    <div class="product-option-header">
                        <span>Select Colour: <strong id="selected-color-label" style="color:var(--color-maroon);"><?= e($selectedColor) ?></strong></span>
                    </div>
                    <div class="color-swatches-list">
                        <?php 
                        $colorHexMap = [
                            'Peach'   => '#FFCBA4',
                            'Pink'    => '#FFB6C1',
                            'Red'     => '#B91C1C',
                            'Purple'  => '#7E22CE',
                            'Yellow'  => '#FACC15',
                            'Green'   => '#15803D',
                            'Navy'    => '#1E3A8A',
                            'Khaki'   => '#C2B280',
                            'Denim'   => '#2563EB',
                            'Ivory'   => '#FFFFF0',
                            'Maroon'  => '#7A1F3D',
                            'Gold'    => '#C9A24B',
                            'Orange'  => '#EA580C',
                        ];
                        foreach ($colors as $idx => $cName): 
                            $cHex = $colorHexMap[$cName] ?? '#CCCCCC';
                        ?>
                            <button type="button" 
                                    class="color-swatch-btn <?= $idx === 0 ? 'is-selected' : '' ?>"
                                    style="background-color: <?= $cHex ?>;"
                                    data-color-name="<?= e($cName) ?>"
                                    title="<?= e($cName) ?>"
                                    aria-label="Select color <?= e($cName) ?>">
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Option 2: Age / Size Chips -->
            <div class="product-option-group">
                <div class="product-option-header">
                    <span>Select Size / Age:</span>
                    <a href="#size-guide" class="size-guide-modal-link">Size Guide 📏</a>
                </div>
                <div class="size-chips-list" id="size-chips-container">
                    <?php 
                    $firstSelectedVariantId = null;
                    foreach ($variants as $idx => $v): 
                        $isSoldOut = ($v['stock_qty'] <= 0);
                        if (!$isSoldOut && $firstSelectedVariantId === null) {
                            $firstSelectedVariantId = (int)$v['id'];
                        }
                        $isSelected = ($firstSelectedVariantId === (int)$v['id']);
                    ?>
                        <button type="button" 
                                class="size-chip-btn <?= $isSelected ? 'is-selected' : '' ?>"
                                data-variant-id="<?= (int)$v['id'] ?>"
                                data-stock-qty="<?= (int)$v['stock_qty'] ?>"
                                <?= $isSoldOut ? 'disabled title="Out of Stock"' : '' ?>>
                            <?= e($v['age_label']) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Quantity Stepper -->
            <div class="product-option-group" style="display:flex; align-items:center; gap:1.25rem;">
                <span style="font-size:0.875rem; font-weight:600;">Quantity:</span>
                <div class="qty-stepper">
                    <button type="button" class="qty-btn" id="qty-minus" aria-label="Decrease quantity">&minus;</button>
                    <input type="number" id="product-quantity-input" value="1" min="1" max="10" class="qty-input" readonly>
                    <button type="button" class="qty-btn" id="qty-plus" aria-label="Increase quantity">&plus;</button>
                </div>
                <span id="stock-availability-label" style="font-size:12px; color:var(--color-success); font-weight:600;">
                    ✓ In Stock (Dispatch in 24-48 hrs)
                </span>
            </div>

            <!-- Hidden input for selected variant -->
            <input type="hidden" id="selected-variant-id" value="<?= $firstSelectedVariantId ?>">

            <!-- CTAs: Add to Cart & Buy Now -->
            <div class="product-ctas">
                <button type="button" class="btn-pill btn-pill-outline" id="detail-add-to-cart-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                    </svg>
                    Add to Cart
                </button>
                <button type="button" class="btn-pill btn-pill-primary" id="detail-buy-now-btn">
                    Buy Now
                </button>
            </div>

            <!-- Enquire on WhatsApp Link -->
            <?php 
            $prodUrl = SITE_URL . '/product.php?slug=' . urlencode($slug);
            $waProductMsg = "Hello Heramba Fashion! I'm interested in *{$product['name']}* ({$prodUrl}). Could you please share size and customization details?";
            $waProductUrl = "https://wa.me/{$cleanWaNumber}?text=" . rawurlencode($waProductMsg);
            ?>
            <a href="<?= e($waProductUrl) ?>" class="product-whatsapp-enquire" target="_blank" rel="noopener noreferrer">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/>
                </svg>
                Enquire on WhatsApp (Custom fit &amp; colors)
            </a>

            <!-- Check Delivery Availability Card -->
            <div class="delivery-checker-box">
                <span style="font-size:0.875rem; font-weight:700; color:var(--color-maroon);">Check Delivery Availability</span>
                <div class="delivery-checker-form">
                    <input type="text" id="pincode-input" class="pincode-input" placeholder="Enter 6-digit Pincode or UAE" maxlength="12">
                    <button type="button" class="btn-pill btn-pill-primary" id="check-pincode-btn" style="padding:0.4rem 1.25rem;">Check</button>
                </div>
                <div class="pincode-result" id="pincode-result"></div>
            </div>

            <!-- Features Checklist Strip -->
            <div style="background:var(--color-blush-card); border-radius:12px; padding:1rem; border:1px solid var(--color-border-subtle);">
                <div style="font-size:0.85rem; font-weight:600; color:var(--color-maroon); margin-bottom:0.4rem;">The Heramba Guarantee:</div>
                <ul style="font-size:0.8rem; color:var(--color-dark-muted); line-height:1.6;">
                    <li>✓ <strong>Pan-India Shipping</strong> &amp; direct dispatch to UAE/Dubai</li>
                    <li>✓ <strong>0-3 Years:</strong> Ready for immediate dispatch</li>
                    <li>✓ <strong>Custom Tailoring:</strong> Delivered in 5 working days</li>
                    <li>✓ <strong>Online Payment Only:</strong> Safe UPI payment via WhatsApp (No COD)</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- ── 3. Tabbed Information Section (Tabs on Desktop / Accordion on Mobile) ── -->
    <div class="product-tabs-wrapper">
        <div class="product-tabs-nav" role="tablist">
            <button type="button" class="product-tab-btn is-active" data-tab-target="tab-details" role="tab">Product Details</button>
            <button type="button" class="product-tab-btn" data-tab-target="tab-fabric" role="tab">Fabric &amp; Care</button>
            <button type="button" class="product-tab-btn" data-tab-target="tab-sizeguide" role="tab">Size Guide</button>
            <button type="button" class="product-tab-btn" data-tab-target="tab-shipping" role="tab">Shipping &amp; Returns</button>
        </div>

        <!-- Mobile Accordion Header 1 -->
        <button type="button" class="product-accordion-header is-active" data-accordion-target="tab-details">
            <span>Product Details</span>
            <span class="product-accordion-icon">▾</span>
        </button>
        <!-- Tab 1: Details -->
        <div class="product-tab-pane is-active" id="tab-details" role="tabpanel">
            <p><?= nl2br(e($product['description'] ?? $product['short_desc'] ?? 'A handcrafted kidswear piece tailored for comfort and style.')) ?></p>
            
            <div class="checklist-grid">
                <div class="checklist-item">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    <span><strong>Material:</strong> <?= e($product['material'] ?? 'Pure Cotton') ?></span>
                </div>
                <div class="checklist-item">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    <span><strong>Design:</strong> <?= e($product['design'] ?? 'Contemporary Traditional') ?></span>
                </div>
                <div class="checklist-item">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    <span><strong>Neckline:</strong> <?= e($product['neck'] ?? 'Round Neck') ?></span>
                </div>
                <div class="checklist-item">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    <span><strong>Sleeves:</strong> <?= e($product['sleeves'] ?? 'Comfort Sleeves') ?></span>
                </div>
                <div class="checklist-item">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    <span><strong>Closure:</strong> <?= e($product['closure'] ?? 'Soft Zip / Button') ?></span>
                </div>
            </div>
        </div>

        <!-- Mobile Accordion Header 2 -->
        <button type="button" class="product-accordion-header" data-accordion-target="tab-fabric">
            <span>Fabric &amp; Care</span>
            <span class="product-accordion-icon">▾</span>
        </button>
        <!-- Tab 2: Fabric & Care -->
        <div class="product-tab-pane" id="tab-fabric" role="tabpanel">
            <p>At Heramba Fashion, every garment is thoughtfully crafted for the sensitive skin of little ones:</p>
            <ul style="list-style:disc; padding-left:1.5rem; margin-top:0.75rem;">
                <li>Hand wash gently in cold water with mild baby detergent.</li>
                <li>Do not bleach or soak for prolonged hours.</li>
                <li>Dry inside out in gentle shade to preserve natural fabric sheen.</li>
                <li>Warm iron on reverse side; avoid direct iron on embellishments or zari.</li>
            </ul>
        </div>

        <!-- Mobile Accordion Header 3 -->
        <button type="button" class="product-accordion-header" data-accordion-target="tab-sizeguide">
            <span>Size Guide</span>
            <span class="product-accordion-icon">▾</span>
        </button>
        <!-- Tab 3: Size Guide Table -->
        <div class="product-tab-pane" id="tab-sizeguide" role="tabpanel">
            <table class="size-table">
                <thead>
                    <tr>
                        <th>Size / Age</th>
                        <th>Chest (in)</th>
                        <th>Waist (in)</th>
                        <th>Garment Length (in)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>0-6 Months</td><td>18 - 19"</td><td>18"</td><td>15"</td></tr>
                    <tr><td>6-12 Months</td><td>19 - 20"</td><td>19"</td><td>17"</td></tr>
                    <tr><td>1-2 Years</td><td>20 - 21"</td><td>20"</td><td>19"</td></tr>
                    <tr><td>2-3 Years</td><td>22 - 23"</td><td>21"</td><td>22"</td></tr>
                    <tr><td>3-4 Years</td><td>23 - 24"</td><td>22"</td><td>25"</td></tr>
                    <tr><td>4-5 Years</td><td>24 - 25"</td><td>23"</td><td>28"</td></tr>
                    <tr><td>5-6 Years</td><td>25 - 26"</td><td>24"</td><td>31"</td></tr>
                </tbody>
            </table>
        </div>

        <!-- Mobile Accordion Header 4 -->
        <button type="button" class="product-accordion-header" data-accordion-target="tab-shipping">
            <span>Shipping &amp; Returns</span>
            <span class="product-accordion-icon">▾</span>
        </button>
        <!-- Tab 4: Shipping & Returns -->
        <div class="product-tab-pane" id="tab-shipping" role="tabpanel">
            <p><strong>Shipping:</strong> Pan-India shipping in 3-5 working days. International delivery to UAE / Dubai dispatched via air express (5-7 working days). 0-3 Years sizes are ready to dispatch.</p>
            <p style="margin-top:0.5rem;"><strong>Payment Policy:</strong> Online Payment Only (UPI) via WhatsApp chat. No COD.</p>
            <p style="margin-top:0.5rem;"><strong>Returns &amp; Exchanges:</strong> Size exchange is accepted within 7 days of delivery. Custom-tailored pieces are made to specific measurements and cannot be returned unless damaged during transit.</p>
        </div>
    </div>

    <!-- ── 4. Customer Reviews Section ───────────────────────────────────── -->
    <section class="reviews-section" aria-labelledby="reviews-title">
        <div class="section-header">
            <span class="section-eyebrow">Customer Feedback</span>
            <h2 id="reviews-title" class="section-title">Verified Customer Reviews</h2>
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:1.25rem;">
            <?php if (!empty($reviews)): ?>
                <?php foreach ($reviews as $rev): ?>
                    <div class="review-card">
                        <div class="review-card__header">
                            <span class="review-author"><?= e($rev['author_name']) ?></span>
                            <span class="review-verified">✓ Verified Buyer</span>
                        </div>
                        <div style="color:#F59E0B; margin-bottom:0.4rem;">
                            <?= str_repeat('★', (int)$rev['rating']) . str_repeat('☆', 5 - (int)$rev['rating']) ?>
                        </div>
                        <?php if (!empty($rev['review_title'])): ?>
                            <strong style="display:block; font-size:0.95rem; margin-bottom:0.25rem;"><?= e($rev['review_title']) ?></strong>
                        <?php endif; ?>
                        <p style="font-size:0.875rem; color:var(--color-dark-muted); margin:0;"><?= e($rev['review_text']) ?></p>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="review-card" style="grid-column:1 / -1; text-align:center;">
                    <p style="color:var(--color-dark-muted); margin:0;">Be the first to review this delightful style! Share your experience on WhatsApp.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ── 5. You May Also Like Carousel ─────────────────────────────────── -->
    <section class="section-padding" style="padding-top:2rem; padding-bottom:3.5rem;">
        <div class="section-header">
            <span class="section-eyebrow">Handpicked Pairings</span>
            <h2 class="section-title">
                <svg class="ornament-leaf" viewBox="0 0 24 24"><path d="M17,8C8,10 5.9,16.17 3.82,21.34L5.71,22L6.66,19.7C7.14,19.87 7.64,20 8,20C19,20 22,3 22,3C21,5 14,5.25 9,6.25C4,7.25 2,11.5 2,13.5C2,15.5 3.75,17.25 3.75,17.25C7,8 17,8 17,8Z"/></svg>
                You May Also Like
                <svg class="ornament-leaf" viewBox="0 0 24 24" style="transform: scaleX(-1);"><path d="M17,8C8,10 5.9,16.17 3.82,21.34L5.71,22L6.66,19.7C7.14,19.87 7.64,20 8,20C19,20 22,3 22,3C21,5 14,5.25 9,6.25C4,7.25 2,11.5 2,13.5C2,15.5 3.75,17.25 3.75,17.25C7,8 17,8 17,8Z"/></svg>
            </h2>
        </div>

        <div class="products-grid">
            <?php foreach ($relatedProducts as $product): ?>
                <?php require APP_ROOT . '/includes/product-card.php'; ?>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<!-- Sticky Bottom Bar on Mobile -->
<div class="mobile-sticky-bar">
    <button type="button" class="btn-pill btn-pill-outline" id="mobile-add-to-cart-btn" style="flex:1;">
        Add to Cart
    </button>
    <button type="button" class="btn-pill btn-pill-primary" id="mobile-buy-now-btn" style="flex:1.2;">
        Buy Now
    </button>
</div>

<!-- Product Detail JS Interactions -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const pId = <?= (int)$product['id'] ?>;
    const variantInput = document.getElementById('selected-variant-id');
    const qtyInput = document.getElementById('product-quantity-input');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    function addToCartAction(redirectCheckout) {
        const vid = variantInput ? variantInput.value : '';
        const qty = qtyInput ? qtyInput.value : '1';

        if (!vid) {
            showToast('Please select a size first!', '⚠️');
            return;
        }

        const formData = new FormData();
        formData.append('product_id', pId);
        formData.append('variant_id', vid);
        formData.append('quantity', qty);
        formData.append('_csrf', csrf);

        const baseUrl = (window.SITE_URL || '').replace(/\/$/, '');
        fetch(baseUrl + '/api/cart.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.ok) {
                const badge = document.getElementById('cart-badge');
                if (badge && typeof data.count !== 'undefined') {
                    badge.textContent = data.count;
                    badge.style.display = data.count > 0 ? '' : 'none';
                }
                showToast(data.message || 'Added to cart!', '🛍️');

                if (redirectCheckout) {
                    setTimeout(() => {
                        window.location.href = baseUrl + '/checkout.php';
                    }, 400);
                }
            } else {
                showToast(data.error || 'Failed to add item', '⚠️');
            }
        })
        .catch(() => {
            showToast('Unable to add item right now', '⚠️');
        });
    }

    const addBtn = document.getElementById('detail-add-to-cart-btn');
    const buyBtn = document.getElementById('detail-buy-now-btn');
    const mobAdd = document.getElementById('mobile-add-to-cart-btn');
    const mobBuy = document.getElementById('mobile-buy-now-btn');

    if (addBtn) addBtn.addEventListener('click', () => addToCartAction(false));
    if (buyBtn) buyBtn.addEventListener('click', () => addToCartAction(true));
    if (mobAdd) mobAdd.addEventListener('click', () => addToCartAction(false));
    if (mobBuy) mobBuy.addEventListener('click', () => addToCartAction(true));
});
</script>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
