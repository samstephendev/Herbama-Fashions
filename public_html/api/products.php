<?php
/**
 * Filtered Products API Endpoint
 * ================================
 * Returns rendered HTML product cards and pagination info for live filter & sort.
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$catSlug    = get('cat');
$subcatSlug = get('sub');
$sort       = get('sort', 'newest');
$minPrice   = isset($_GET['min_price']) && is_numeric($_GET['min_price']) ? (float)$_GET['min_price'] : null;
$maxPrice   = isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? (float)$_GET['max_price'] : null;
$page       = getInt('page') ?? 1;
$isNew      = (bool)getInt('is_new');
$isOffer    = (bool)getInt('is_offer');
$q          = get('q');

// Handle age / size array filters
$ages = [];
if (!empty($_GET['age'])) {
    $ages = is_array($_GET['age']) ? $_GET['age'] : explode(',', (string)$_GET['age']);
}

$filters = [
    'category_slug'    => $catSlug,
    'subcategory_slug' => $subcatSlug,
    'sort'             => $sort,
    'min_price'        => $minPrice,
    'max_price'        => $maxPrice,
    'is_new'           => $isNew ? 1 : 0,
    'is_offer'         => $isOffer ? 1 : 0,
    'age'              => $ages,
    'q'                => $q,
];

$productModel = new ProductModel();
$result = $productModel->list($filters, $page, 20);

// Render product cards buffer
ob_start();
if (!empty($result['items'])) {
    foreach ($result['items'] as $product) {
        require APP_ROOT . '/includes/product-card.php';
    }
} else {
    echo '<div style="grid-column: 1 / -1; text-align:center; padding: 3.5rem 1rem; background:#FFFFFF; border-radius:16px; border:1px solid var(--color-border);">';
    echo '<span style="font-size:3rem; display:block; margin-bottom:0.75rem;">🎀</span>';
    echo '<h3 style="font-family:var(--font-serif); color:var(--color-maroon); margin-bottom:0.5rem;">No products match your filters</h3>';
    echo '<p style="color:var(--color-dark-muted); font-size:0.9rem; margin-bottom:1.25rem;">Try adjusting the price range, age, or clear filters to see more lovely styles.</p>';
    echo '<button type="button" class="btn-pill btn-pill-outline" onclick="window.clearAllFilters();">Reset All Filters</button>';
    echo '</div>';
}
$cardsHtml = ob_get_clean();

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok'          => true,
    'html'        => $cardsHtml,
    'total'       => $result['total'],
    'page'        => $result['page'],
    'total_pages' => $result['total_pages'],
]);
