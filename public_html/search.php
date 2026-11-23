<?php
/**
 * Heramba Fashion — Search Results Page
 * ========================================
 * Search results grid with query highlighting, count, and no-results suggestions.
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/config/bootstrap.php';

$query = trim(get('q', ''));
$page  = getInt('page') ?? 1;
$sort  = get('sort', 'newest');

$pageTitle = !empty($query) ? 'Search results for "' . e($query) . '" — Heramba Fashion' : 'Search Kidswear — Heramba Fashion';
$pageDesc  = 'Search for handcrafted frocks, party lehengas, kurtas, dungarees, and infant wear at Heramba Fashion.';
$bodyClass = 'page-collection page-search';

$productModel = new ProductModel();
$productsData = ['items' => [], 'total' => 0, 'page' => 1, 'total_pages' => 1];

if (strlen($query) >= 2) {
    $productsData = $productModel->list([
        'q'    => $query,
        'sort' => $sort,
    ], $page, 24);
}

require_once APP_ROOT . '/includes/header.php';
?>

<div class="collection-hero">
    <div class="container">
        <h1 class="collection-hero__title">Search Kidswear</h1>
        <p class="collection-hero__tagline">
            <?php if (!empty($query)): ?>
                Results for: <strong style="color:var(--color-maroon);">"<?= e($query) ?>"</strong>
            <?php else: ?>
                Type keywords like frocks, lehenga, dungaree, cotton, or kurta
            <?php endif; ?>
        </p>
    </div>
</div>

<div class="container" style="padding-bottom: 3.5rem;">
    <!-- Search Bar -->
    <div style="max-width: 600px; margin: 1.5rem auto 2.5rem;">
        <form action="/search.php" method="GET" style="display:flex; gap:0.5rem; background:#FFFFFF; padding:6px; border-radius:9999px; border:1.5px solid var(--color-gold); box-shadow:var(--shadow-sm);">
            <input type="search" name="q" value="<?= e($query) ?>" placeholder="Search for frocks, kurta pyjama, lehengas, shorts..." style="flex:1; border:none; outline:none; padding:0.6rem 1.25rem; border-radius:9999px; font-size:1rem;" required>
            <button type="submit" class="btn-pill btn-pill-primary" style="padding:0.6rem 1.5rem;">Search</button>
        </form>
    </div>

    <!-- Header Row -->
    <?php if (!empty($query)): ?>
        <div class="collection-header-row">
            <div class="collection-count">
                Found <strong><?= $productsData['total'] ?></strong> styles matching "<?= e($query) ?>"
            </div>
            <?php if ($productsData['total'] > 0): ?>
                <div class="sort-select-wrapper">
                    <form method="GET" action="/search.php" id="search-sort-form">
                        <input type="hidden" name="q" value="<?= e($query) ?>">
                        <label for="search-sort-select" style="color:var(--color-dark-muted);">Sort by:</label>
                        <select id="search-sort-select" name="sort" class="sort-select" onchange="document.getElementById('search-sort-form').submit();">
                            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>New Arrivals</option>
                            <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                            <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                            <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Popularity</option>
                        </select>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Results Grid -->
    <?php if (!empty($productsData['items'])): ?>
        <div class="products-grid">
            <?php foreach ($productsData['items'] as $product): ?>
                <?php require APP_ROOT . '/includes/product-card.php'; ?>
            <?php endforeach; ?>
        </div>
    <?php elseif (!empty($query)): ?>
        <!-- No Results State with Suggestions -->
        <div style="text-align:center; padding: 4rem 1rem; background:#FFFFFF; border-radius:16px; border:1px solid var(--color-border); max-width:680px; margin:0 auto;">
            <span style="font-size:3.5rem; display:block; margin-bottom:1rem;">🔍</span>
            <h2 style="font-family:var(--font-serif); color:var(--color-maroon); margin-bottom:0.75rem;">No products found for "<?= e($query) ?>"</h2>
            <p style="color:var(--color-dark-muted); font-size:0.95rem; line-height:1.6; margin-bottom:1.5rem;">
                We couldn't find an exact match for your search. Check the spelling, try broader keywords, or explore our popular categories below.
            </p>
            <div style="display:flex; flex-wrap:wrap; justify-content:center; gap:0.6rem; margin-bottom:2rem;">
                <a href="<?= url('/search.php?q=Frock') ?>" class="chip">Floral Frocks</a>
                <a href="<?= url('/search.php?q=Lehenga') ?>" class="chip">Lehenga Sets</a>
                <a href="<?= url('/search.php?q=Cotton') ?>" class="chip">Pure Cotton</a>
                <a href="<?= url('/search.php?q=Kurta') ?>" class="chip">Silk Kurta</a>
                <a href="<?= url('/search.php?q=Shorts') ?>" class="chip">Cargo Shorts</a>
                <a href="<?= url('/search.php?q=Romper') ?>" class="chip">Infant Rompers</a>
            </div>
            <a href="<?= url('/collection.php') ?>" class="btn-pill btn-pill-primary">Browse All Collections &rarr;</a>
        </div>
    <?php endif; ?>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
