<?php
/**
 * Herbama Fashions — Collection Page
 * ====================================
 * Category catalog with subcategories, live AJAX filter sidebar, sort,
 * mid-page promo banner, and mobile slide-in filter drawer.
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/config/bootstrap.php';

$catSlug    = get('cat', 'girls');
$subcatSlug = get('sub', '');
$sort       = get('sort', 'newest');
$page       = getInt('page') ?? 1;

// Canonical category resolution
$catMeta = match ($catSlug) {
    'boys', 'boys-wear' => [
        'name'    => 'Boys Wear',
        'slug'    => 'boys-wear',
        'tagline' => 'Stylish | Comfortable | Everyday Wear',
        'subcats' => [
            ['name' => 'All Boys', 'slug' => ''],
            ['name' => 'T-Shirts & Polos', 'slug' => 'boys-tshirts'],
            ['name' => 'Casual Shirts', 'slug' => 'boys-shirts'],
            ['name' => 'Cargo Shorts', 'slug' => 'boys-shorts'],
            ['name' => 'Jeans & Dungarees', 'slug' => 'boys-jeans'],
            ['name' => 'Casual Sets', 'slug' => 'boys-casual-sets'],
            ['name' => 'Ethnic Wear', 'slug' => 'boys-ethnic-wear'],
        ],
    ],
    'infant', 'infant-wear' => [
        'name'    => 'Infant Wear',
        'slug'    => 'infant-wear',
        'tagline' => 'Ultra-Soft | 100% Pure Cotton | Gentle on Delicate Skin',
        'subcats' => [
            ['name' => 'All Infant', 'slug' => ''],
            ['name' => 'Rompers', 'slug' => 'infant-rompers'],
            ['name' => 'Onesies', 'slug' => 'infant-onesies'],
            ['name' => 'Winter Jackets', 'slug' => 'infant-jackets'],
        ],
    ],
    'festive', 'festive-wear' => [
        'name'    => 'Festive Collection',
        'slug'    => 'festive-wear',
        'tagline' => 'Regal Silks, Zari Embroidery & Traditional Heritage Ensembles',
        'subcats' => [
            ['name' => 'All Festive', 'slug' => ''],
            ['name' => 'Lehenga Sets', 'slug' => 'festive-lehengas'],
            ['name' => 'Silk Kurta Pyjamas', 'slug' => 'festive-kurtas'],
            ['name' => 'Sharara Sets', 'slug' => 'festive-shararas'],
            ['name' => 'Pattu Pavadai', 'slug' => 'festive-pattu'],
        ],
    ],
    default => [
        'name'    => 'Girls Wear',
        'slug'    => 'girls-wear',
        'tagline' => 'Charming Styles for Every Little Princess',
        'subcats' => [
            ['name' => 'All Girls', 'slug' => ''],
            ['name' => 'Frocks & Dresses', 'slug' => 'girls-frocks'],
            ['name' => 'Tops & Bottoms', 'slug' => 'girls-tops-bottoms'],
            ['name' => 'Palazzo Sets', 'slug' => 'girls-palazzo-sets'],
            ['name' => 'Pattu Pavadai', 'slug' => 'girls-pattu-pavadai'],
            ['name' => 'Casual Wear', 'slug' => 'girls-casual-wear'],
        ],
    ]
};

$pageTitle = $catMeta['name'] . ' Collection — Herbama Fashions';
$pageDesc  = 'Shop premium ' . $catMeta['name'] . ' at Herbama Fashions. ' . $catMeta['tagline'] . '. Pan-India & UAE dispatch.';
$bodyClass = 'page-collection';

$productModel = new ProductModel();
$priceBounds  = $productModel->getPriceBounds($catMeta['slug']);
$promoOffer   = $productModel->getActivePromo();

// Initial load data
$initialFilters = [
    'category_slug'    => $catMeta['slug'],
    'subcategory_slug' => $subcatSlug,
    'sort'             => $sort,
];
$productsData = $productModel->list($initialFilters, $page, 20);

require_once APP_ROOT . '/includes/header.php';
?>

<!-- ── 1. Category Hero Banner ──────────────────────────────────────────── -->
<div class="collection-hero">
    <div class="container">
        <h1 class="collection-hero__title"><?= e($catMeta['name']) ?></h1>
        <p class="collection-hero__tagline"><?= e($catMeta['tagline']) ?></p>
    </div>
</div>

<div class="container">
    <!-- ── 2. Subcategory Carousel Tiles ────────────────────────────────── -->
    <div class="subcategories-bar" aria-label="Subcategory filters">
        <?php foreach ($catMeta['subcats'] as $sub): ?>
            <?php 
                $isActive = ($subcatSlug === $sub['slug']);
                $url = '/collection.php?cat=' . urlencode($catSlug) . ($sub['slug'] ? '&sub=' . urlencode($sub['slug']) : '');
            ?>
            <a href="<?= e($url) ?>" 
               class="subcat-chip <?= $isActive ? 'is-active' : '' ?>"
               data-subcat-slug="<?= e($sub['slug']) ?>">
                <?= e($sub['name']) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- ── 3. Collection Layout (Sidebar + Grid) ────────────────────────── -->
    <div class="collection-layout">
        
        <!-- Left Filter Sidebar -->
        <aside class="filter-sidebar" id="filter-sidebar" aria-label="Product Filters">
            <div class="filter-sidebar__header">
                <span class="filter-sidebar__title">Filter By</span>
                <button type="button" class="filter-sidebar__clear" id="clear-all-filters-btn">Clear All</button>
            </div>

            <form id="filter-form">
                <!-- Age Filter -->
                <div class="filter-group">
                    <div class="filter-group__header">
                        <span>Age Group</span>
                        <span aria-hidden="true">▾</span>
                    </div>
                    <div class="filter-chips-grid">
                        <?php 
                        $commonAges = ['0-6M', '6-12M', '1Y', '2Y', '3Y', '4Y', '5Y', '6Y'];
                        foreach ($commonAges as $age): 
                        ?>
                            <label class="chip">
                                <input type="checkbox" name="age[]" value="<?= $age ?>" style="display:none;" class="filter-input-checkbox">
                                <span><?= $age ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Price Range Slider -->
                <div class="filter-group">
                    <div class="filter-group__header">
                        <span>Max Price: ₹<span id="price-slider-display"><?= (int)$priceBounds['max'] ?></span></span>
                    </div>
                    <input type="range" 
                           id="price-range-slider" 
                           name="max_price" 
                           min="<?= (int)$priceBounds['min'] ?>" 
                           max="<?= (int)$priceBounds['max'] ?>" 
                           value="<?= (int)$priceBounds['max'] ?>" 
                           step="50"
                           class="price-slider-input">
                    <div style="display:flex; justify-content:space-between; font-size:11px; color:var(--color-dark-muted); margin-top:4px;">
                        <span>₹<?= (int)$priceBounds['min'] ?></span>
                        <span>₹<?= (int)$priceBounds['max'] ?></span>
                    </div>
                </div>

                <!-- Collection Category Filter -->
                <div class="filter-group">
                    <div class="filter-group__header">
                        <span>Department</span>
                        <span aria-hidden="true">▾</span>
                    </div>
                    <div>
                        <label class="filter-checkbox-label">
                            <input type="radio" name="cat" value="girls" <?= $catSlug === 'girls' ? 'checked' : '' ?> class="filter-cat-radio">
                            <span>Girls Wear</span>
                        </label>
                        <label class="filter-checkbox-label">
                            <input type="radio" name="cat" value="boys" <?= $catSlug === 'boys' ? 'checked' : '' ?> class="filter-cat-radio">
                            <span>Boys Wear</span>
                        </label>
                        <label class="filter-checkbox-label">
                            <input type="radio" name="cat" value="infant" <?= $catSlug === 'infant' ? 'checked' : '' ?> class="filter-cat-radio">
                            <span>Infant Wear</span>
                        </label>
                        <label class="filter-checkbox-label">
                            <input type="radio" name="cat" value="festive" <?= $catSlug === 'festive' ? 'checked' : '' ?> class="filter-cat-radio">
                            <span>Festive Collection</span>
                        </label>
                    </div>
                </div>

                <!-- Special Offer Only Filter -->
                <div class="filter-group">
                    <label class="filter-checkbox-label">
                        <input type="checkbox" name="is_offer" value="1" id="filter-is-offer">
                        <span style="font-weight:600; color:var(--color-maroon);">🏷️ Offers Only</span>
                    </label>
                </div>

                <div style="margin-top:1.25rem;">
                    <button type="button" class="btn-pill btn-pill-primary" id="mobile-apply-filters-btn" style="width:100%; display:none;">Apply Filters</button>
                </div>
            </form>
        </aside>

        <!-- Right Side Products Grid -->
        <div class="collection-main">
            <!-- Header Row: Count & Sort Dropdown -->
            <div class="collection-header-row">
                <div class="collection-count">
                    <button type="button" class="btn-pill btn-pill-outline mobile-filter-btn" id="open-filter-drawer" style="padding:0.4rem 1rem; margin-right:0.5rem;">
                        ⚙️ Filters
                    </button>
                    Showing <strong id="product-total-count"><?= $productsData['total'] ?></strong> styles in <?= e($catMeta['name']) ?>
                </div>

                <div class="sort-select-wrapper">
                    <label for="sort-by-select" style="color:var(--color-dark-muted);">Sort by:</label>
                    <select id="sort-by-select" class="sort-select">
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>New Arrivals</option>
                        <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                        <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                        <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Popularity</option>
                    </select>
                </div>
            </div>

            <!-- Products Grid Container -->
            <div class="products-grid" id="collection-products-grid">
                <?php if (!empty($productsData['items'])): ?>
                    <?php foreach ($productsData['items'] as $product): ?>
                        <?php require APP_ROOT . '/includes/product-card.php'; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column: 1 / -1; text-align:center; padding: 3rem; background:#FFFFFF; border-radius:16px;">
                        <p style="color:var(--color-dark-muted);">No products found in this category.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ── 4. Mid-Page Promo Banner ─────────────────────────────── -->
            <section class="promo-strip-banner" style="margin-top:3rem;">
                <div class="promo-strip-banner__content">
                    <span class="promo-strip-banner__tag">LIMITED PERIOD OFFER</span>
                    <h3 class="promo-strip-banner__title">
                        Special Offer: <?= e($promoOffer['title'] ?? 'Flat 10% OFF on Festive & Kids Collection') ?>
                    </h3>
                    <p class="promo-strip-banner__desc">
                        Use coupon code <strong><?= e($promoOffer['code'] ?? 'HERBAMA10') ?></strong> on checkout or via WhatsApp chat.
                    </p>
                </div>
                <div>
                    <a href="<?= url('/offers.php') ?>" class="btn-pill btn-pill-gold">Shop Offers &rarr;</a>
                </div>
            </section>
        </div>

    </div>
</div>

<!-- Collection Live Filter JS -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const filterForm      = document.getElementById('filter-form');
    const sortSelect      = document.getElementById('sort-by-select');
    const productsGrid    = document.getElementById('collection-products-grid');
    const totalCountEl    = document.getElementById('product-total-count');
    const priceSlider     = document.getElementById('price-range-slider');
    const priceDisplay    = document.getElementById('price-slider-display');
    const filterSidebar   = document.getElementById('filter-sidebar');
    const openDrawerBtn   = document.getElementById('open-filter-drawer');
    const clearBtn        = document.getElementById('clear-all-filters-btn');
    const mobileApplyBtn  = document.getElementById('mobile-apply-filters-btn');

    let currentCat = '<?= e($catSlug) ?>';
    let currentSub = '<?= e($subcatSlug) ?>';

    // Update price display live on drag
    if (priceSlider && priceDisplay) {
        priceSlider.addEventListener('input', () => {
            priceDisplay.textContent = priceSlider.value;
        });
        priceSlider.addEventListener('change', fetchFilteredProducts);
    }

    // Toggle chip active class when checkbox changes
    document.querySelectorAll('.filter-chips-grid .chip').forEach(label => {
        const input = label.querySelector('input');
        input.addEventListener('change', () => {
            label.classList.toggle('is-active', input.checked);
            fetchFilteredProducts();
        });
    });

    // Subcategory clicks
    document.querySelectorAll('.subcat-chip').forEach(chip => {
        chip.addEventListener('click', function (e) {
            e.preventDefault();
            document.querySelectorAll('.subcat-chip').forEach(c => c.classList.remove('is-active'));
            this.classList.add('is-active');
            currentSub = this.getAttribute('data-subcat-slug') || '';
            fetchFilteredProducts();
        });
    });

    // Radio category change
    document.querySelectorAll('.filter-cat-radio').forEach(radio => {
        radio.addEventListener('change', () => {
            if (radio.checked) {
                window.location.href = '/collection.php?cat=' + encodeURIComponent(radio.value);
            }
        });
    });

    // Offer only filter
    const offerCheck = document.getElementById('filter-is-offer');
    if (offerCheck) {
        offerCheck.addEventListener('change', fetchFilteredProducts);
    }

    if (sortSelect) {
        sortSelect.addEventListener('change', fetchFilteredProducts);
    }

    // Clear all filters
    window.clearAllFilters = function () {
        if (filterForm) filterForm.reset();
        document.querySelectorAll('.filter-chips-grid .chip').forEach(c => c.classList.remove('is-active'));
        if (priceSlider && priceDisplay) {
            priceSlider.value = priceSlider.max;
            priceDisplay.textContent = priceSlider.max;
        }
        currentSub = '';
        document.querySelectorAll('.subcat-chip').forEach((c, idx) => {
            c.classList.toggle('is-active', idx === 0);
        });
        fetchFilteredProducts();
    };

    if (clearBtn) {
        clearBtn.addEventListener('click', window.clearAllFilters);
    }

    // Mobile Drawer Handlers
    if (openDrawerBtn && filterSidebar) {
        openDrawerBtn.addEventListener('click', () => {
            filterSidebar.classList.add('is-open');
            if (mobileApplyBtn) mobileApplyBtn.style.display = 'block';
        });
    }

    if (mobileApplyBtn && filterSidebar) {
        mobileApplyBtn.addEventListener('click', () => {
            filterSidebar.classList.remove('is-open');
        });
    }

    function fetchFilteredProducts() {
        if (!productsGrid) return;
        productsGrid.style.opacity = '0.4';

        const params = new URLSearchParams();
        params.set('cat', currentCat);
        if (currentSub) params.set('sub', currentSub);
        if (sortSelect) params.set('sort', sortSelect.value);
        if (priceSlider) params.set('max_price', priceSlider.value);
        if (offerCheck && offerCheck.checked) params.set('is_offer', '1');

        // Collect ages
        const checkedAges = [];
        document.querySelectorAll('.filter-chips-grid input:checked').forEach(input => {
            checkedAges.push(input.value);
        });
        if (checkedAges.length > 0) {
            params.set('age', checkedAges.join(','));
        }

        // Update browser URL without reload for shareability
        const newUrl = window.location.pathname + '?' + params.toString();
        window.history.replaceState({}, '', newUrl);

        const baseUrl = (window.SITE_URL || '').replace(/\/$/, '');
        fetch(baseUrl + '/api/products.php?' + params.toString())
            .then(res => res.json())
            .then(data => {
                productsGrid.innerHTML = data.html;
                if (totalCountEl) totalCountEl.textContent = data.total;
                productsGrid.style.opacity = '1';
            })
            .catch(() => {
                productsGrid.style.opacity = '1';
            });
    }
});
</script>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
