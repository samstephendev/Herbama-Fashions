<?php
/**
 * Add To Cart API Endpoint
 * =========================
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

csrf_verify();

$productId = postInt('product_id');
$variantId = postInt('variant_id');
$rawQty    = postInt('quantity') ?? 1;
$qty       = max(1, min(10, $rawQty));

if (!$productId || !$variantId) {
    echo json_encode(['ok' => false, 'error' => 'Please select a size/variant.']);
    exit;
}

try {
    $cartCount = 0;
    if (Session::isLoggedIn()) {
        $cartModel = new CartModel();
        $res = $cartModel->addItem((int)Session::userId(), $productId, $variantId, $qty);
        if (!$res['ok']) {
            echo json_encode(['ok' => false, 'error' => $res['error'] ?? 'Unable to add item.']);
            exit;
        }
        $cartCount = $cartModel->countItems((int)Session::userId());
    } else {
        // Session-based cart for guest users — validate stock from DB
        $prodModel = new ProductModel();
        $varData   = $prodModel->getVariantWithProduct($variantId);
        if (!$varData || (int)$varData['product_id'] !== $productId || !(int)$varData['is_active']) {
            echo json_encode(['ok' => false, 'error' => 'This product or variant is no longer available.']);
            exit;
        }

        if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        $key = "{$productId}_{$variantId}";
        $currentInCart = isset($_SESSION['cart'][$key]) ? (int)$_SESSION['cart'][$key]['quantity'] : 0;
        $newQty = min(10, $currentInCart + $qty);

        if ($newQty > (int)$varData['stock_qty']) {
            $available = max(0, (int)$varData['stock_qty'] - $currentInCart);
            if ($available <= 0) {
                echo json_encode(['ok' => false, 'error' => 'This item is out of stock.']);
                exit;
            }
            echo json_encode(['ok' => false, 'error' => "Only {$available} more unit(s) available."]);
            exit;
        }

        $_SESSION['cart'][$key] = [
            'product_id' => $productId,
            'variant_id' => $variantId,
            'quantity'   => $newQty,
        ];

        $cartCount = 0;
        foreach ($_SESSION['cart'] as $item) {
            $cartCount += (int)$item['quantity'];
        }
    }

    echo json_encode([
        'ok'      => true,
        'message' => 'Item added to your shopping cart!',
        'count'   => $cartCount,
    ]);
} catch (\Throwable $e) {
    error_log('[API Cart] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Unable to update cart.']);
}
