<?php
/**
 * Wishlist Toggle API Endpoint
 * ==============================
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (!Session::isLoggedIn()) {
    echo json_encode(['ok' => false, 'redirect' => '/login.php']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

csrf_verify();

$productId = postInt('product_id');
if (!$productId) {
    echo json_encode(['ok' => false, 'error' => 'Invalid product.']);
    exit;
}

try {
    $prodModel = new ProductModel();
    $userId = (int)Session::userId();
    $action = $prodModel->toggleWishlist($userId, $productId);
    $wishlistIds = $prodModel->getWishlistIds($userId);

    echo json_encode([
        'ok'     => true,
        'action' => $action,
        'count'  => count($wishlistIds),
    ]);
} catch (\Throwable $e) {
    error_log('[API Wishlist] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Unable to update wishlist.']);
}
