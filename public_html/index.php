<?php
/**
 * Heramba Fashion — Homepage
 * =============================
 * Hero slider, Shop by Category, New Arrivals, Promo Offer Banner, Trust Strip.
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/config/bootstrap.php';

$pageTitle = 'Heramba Fashion | Premium Handcrafted Kidswear (0-14 Years)';
$pageDesc  = 'Discover adorable, handcrafted kidswear for girls, boys, and infants. Pure breathable cotton, silk lehengas, and festive sets. Pan-India & UAE dispatch.';
$bodyClass = 'page-home';

// Fetch data via PDO Models
$bannerModel  = new BannerModel();
$banners      = $bannerModel->getActiveBanners();

$productModel = new ProductModel();
$newArrivals  = $productModel->list(['is_new' => 1], 1, 4)['items'];
$promoOffer   = $productModel->getActivePromo();

require_once APP_ROOT . '/includes/header.php';
?>

<div class="container">
    <!-- ── 1. Hero Slider Carousel ──────────────────────────────────────── -->
    <section class="hero-carousel" aria-label="Featured Collections Carousel">
        <?php foreach ($banners as $idx => $banner): ?>
            <div class="hero-slide <?= $idx === 0 ? 'is-active' : '' ?>" 
                 data-slide-index="<?= $idx ?>"
                 style="background-color: <?= e($banner['bg_color'] ?? '#FAF4EB') ?>;">
                <div class="hero-slide__inner">
                    <div class="hero-slide__content">
                        <?php if (!empty($banner['badge_text'])): ?>
                            <span class="hero-slide__badge"><?= e($banner['badge_text']) ?></span>
                        <?php endif; ?>
                        
                        <h1 class="hero-slide__title">
                            <?= e($banner['title']) ?>
                        </h1>
                        
                        <p class="hero-slide__sub">
                            <?= e($banner['subtitle'] ?? '') ?>
                        </p>

                        <a href="<?= e(url($banner['button_url'] ?? '/collection.php')) ?>" class="btn-pill btn-pill-primary">
                            <?= e($banner['button_text'] ?? 'Shop Now') ?> &rarr;
                        </a>
                    </div>

                    <div class="hero-slide__media">
                        <img src="<?= e(asset($banner['image_url'])) ?>" 
                             alt="<?= e($banner['title']) ?>" 
                             width="480" 
                             height="360"
                             loading="eager">
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Carousel Navigation Dots -->
        <div class="hero-carousel__dots" role="tablist" aria-label="Slider controls">
            <?php foreach ($banners as $idx => $banner): ?>
                <button type="button" 
                        class="hero-dot <?= $idx === 0 ? 'is-active' : '' ?>" 
                        data-dot-target="<?= $idx ?>" 
                        aria-label="Go to slide <?= $idx + 1 ?>"
                        role="tab">
                </button>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ── 2. Shop by Category ──────────────────────────────────────────── -->
    <section class="section-padding" style="padding: 2.5rem 0;" aria-labelledby="cats-heading">
        <div class="section-header">
            <span class="section-eyebrow">Curated Collections</span>
            <h2 id="cats-heading" class="section-title">
                <svg class="ornament-leaf" viewBox="0 0 24 24"><path d="M17,8C8,10 5.9,16.17 3.82,21.34L5.71,22L6.66,19.7C7.14,19.87 7.64,20 8,20C19,20 22,3 22,3C21,5 14,5.25 9,6.25C4,7.25 2,11.5 2,13.5C2,15.5 3.75,17.25 3.75,17.25C7,8 17,8 17,8Z"/></svg>
                Shop by Category
                <svg class="ornament-leaf" viewBox="0 0 24 24" style="transform: scaleX(-1);"><path d="M17,8C8,10 5.9,16.17 3.82,21.34L5.71,22L6.66,19.7C7.14,19.87 7.64,20 8,20C19,20 22,3 22,3C21,5 14,5.25 9,6.25C4,7.25 2,11.5 2,13.5C2,15.5 3.75,17.25 3.75,17.25C7,8 17,8 17,8Z"/></svg>
            </h2>
            <p class="section-subtitle">Charming styles crafted for delicate skin, playtime adventures, and festive joy.</p>
        </div>

        <div class="category-grid">
            <!-- Girls Wear -->
            <a href="<?= url('/collection.php?cat=girls') ?>" class="category-card">
                <div class="category-card__img-box">
                    <span class="category-card__emoji" aria-hidden="true">👗</span>
                </div>
                <h3 class="category-card__title">Girls Wear</h3>
                <span class="category-card__age">Ages 1 - 12 Years</span>
            </a>

            <!-- Boys Wear -->
            <a href="<?= url('/collection.php?cat=boys') ?>" class="category-card">
                <div class="category-card__img-box">
                    <span class="category-card__emoji" aria-hidden="true">👕</span>
                </div>
                <h3 class="category-card__title">Boys Wear</h3>
                <span class="category-card__age">Ages 1 - 12 Years</span>
            </a>

            <!-- Infant Wear -->
            <a href="<?= url('/collection.php?cat=infant') ?>" class="category-card">
                <div class="category-card__img-box">
                    <span class="category-card__emoji" aria-hidden="true">👶</span>
                </div>
                <h3 class="category-card__title">Infant Wear</h3>
                <span class="category-card__age">Ages 0 - 24 Months</span>
            </a>

            <!-- Festive Collection -->
            <a href="<?= url('/collection.php?cat=festive') ?>" class="category-card">
                <div class="category-card__img-box">
                    <span class="category-card__emoji" aria-hidden="true">👑</span>
                </div>
                <h3 class="category-card__title">Festive Collection</h3>
                <span class="category-card__age">Celebration Specials</span>
            </a>
        </div>
    </section>

    <!-- ── 3. New Arrivals Row ──────────────────────────────────────────── -->
    <section class="section-padding" style="padding: 2.5rem 0;" aria-labelledby="new-heading">
        <div class="section-header">
            <span class="section-eyebrow">Fresh in the Boutique</span>
            <h2 id="new-heading" class="section-title">
                <svg class="ornament-leaf" viewBox="0 0 24 24"><path d="M17,8C8,10 5.9,16.17 3.82,21.34L5.71,22L6.66,19.7C7.14,19.87 7.64,20 8,20C19,20 22,3 22,3C21,5 14,5.25 9,6.25C4,7.25 2,11.5 2,13.5C2,15.5 3.75,17.25 3.75,17.25C7,8 17,8 17,8Z"/></svg>
                New Arrivals
                <svg class="ornament-leaf" viewBox="0 0 24 24" style="transform: scaleX(-1);"><path d="M17,8C8,10 5.9,16.17 3.82,21.34L5.71,22L6.66,19.7C7.14,19.87 7.64,20 8,20C19,20 22,3 22,3C21,5 14,5.25 9,6.25C4,7.25 2,11.5 2,13.5C2,15.5 3.75,17.25 3.75,17.25C7,8 17,8 17,8Z"/></svg>
            </h2>
            <p class="section-subtitle">Handpicked contemporary designs crafted in small artisanal batches.</p>
        </div>

        <div class="products-grid">
            <?php foreach ($newArrivals as $product): ?>
                <?php require APP_ROOT . '/includes/product-card.php'; ?>
            <?php endforeach; ?>
        </div>

        <div style="text-align:center; margin-top: 2.5rem;">
            <a href="<?= url('/new-arrivals.php') ?>" class="btn-pill btn-pill-outline">View All New Arrivals &rarr;</a>
        </div>
    </section>

    <!-- ── 4. Mid-Page Promo Banner (Everyday Essentials / Offers) ──────── -->
    <section class="promo-strip-banner" aria-label="Special Promotional Offer">
        <div class="promo-strip-banner__content">
            <span class="promo-strip-banner__tag">
                <?= e($promoOffer['code'] ?? 'SPECIAL OFFER') ?>
            </span>
            <h2 class="promo-strip-banner__title">
                <?= e($promoOffer['title'] ?? 'Everyday Essentials: Handcrafted with Love') ?>
            </h2>
            <p class="promo-strip-banner__desc">
                Get up to 15% OFF on everyday playwear, pure cotton rompers, and festive wardrobe essentials. Use coupon at checkout or on WhatsApp!
            </p>
        </div>
        <div>
            <a href="<?= url('/offers.php') ?>" class="btn-pill btn-pill-gold">
                Claim Offer &rarr;
            </a>
        </div>
    </section>
</div>

<!-- Inline Carousel Controller -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const slides = document.querySelectorAll('.hero-slide');
    const dots = document.querySelectorAll('.hero-dot');
    let current = 0;
    let timer = null;

    function goToSlide(index) {
        slides.forEach(s => s.classList.remove('is-active'));
        dots.forEach(d => d.classList.remove('is-active'));
        slides[index].classList.add('is-active');
        dots[index].classList.add('is-active');
        current = index;
    }

    dots.forEach((dot, idx) => {
        dot.addEventListener('click', () => {
            goToSlide(idx);
            resetTimer();
        });
    });

    function nextSlide() {
        goToSlide((current + 1) % slides.length);
    }

    function resetTimer() {
        clearInterval(timer);
        timer = setInterval(nextSlide, 5500);
    }

    if (slides.length > 1) {
        timer = setInterval(nextSlide, 5500);
    }
});
</script>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
