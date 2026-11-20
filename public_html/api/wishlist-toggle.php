<?php
/**
 * Herbama Fashions — Wishlist Toggle AJAX Endpoint
 * ==================================================
 * Toggles a product in/out of a logged-in user's wishlist.
 * Returns JSON only — never outputs HTML.
 *
 * Required POST fields:
 *   product_id  int   The product to toggle.
 *   _csrf       str   CSRF token from <meta name="csrf-token">.
 *
 * Response shape:
 *   {ok: bool, action: 'added'|'removed', wishlist_count: int, csrf_token: str}
 *   or on error:
 *   {ok: false, error: str, redirect?: str, csrf_token?: str}
 *
 * Security:
 *   - POST only (405 on other methods).
 *   - Login required (401 + redirect URL on unauthenticated).
 *   - Manual CSRF validation with hash_equals() (constant-time).
 *   - ProductModel::toggleWishlist() validates product_id against DB (IDOR-safe).
 *   - Generic error messages only — no internal detail leaked.
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

// ── Force JSON response type ──────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');

// ── POST-only guard ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

// ── Authentication guard ──────────────────────────────────────────────────────
// Return a redirect hint so JS can send the user to login
if (!Session::isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'ok'       => false,
        'error'    => 'Login required.',
        'redirect' => url('/login.php'),
    ]);
    exit;
}

// ── CSRF validation (manual hash_equals — safe for AJAX) ─────────────────────
$submittedCsrf = $_POST['_csrf'] ?? '';
if (!hash_equals(CSRF::token(), (string) $submittedCsrf)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid request token.']);
    exit;
}

// ── Rotate CSRF token after successful validation ─────────────────────────────
CSRF::rotate();
$newCsrfToken = CSRF::token();

// ── Read and validate product_id ──────────────────────────────────────────────
$productId = (int) ($_POST['product_id'] ?? 0);

if ($productId <= 0) {
    echo json_encode([
        'ok'         => false,
        'error'      => 'Invalid product.',
        'csrf_token' => $newCsrfToken,
    ]);
    exit;
}

// ── Toggle wishlist ───────────────────────────────────────────────────────────
try {
    $productModel  = new ProductModel();
    $userId        = (int) Session::userId();

    // toggleWishlist validates the product exists and is active (throws on IDOR)
    $action        = $productModel->toggleWishlist($userId, $productId);

    // Fresh wishlist count for the header badge
    $wishlistIds   = $productModel->getWishlistIds($userId);
    $wishlistCount = count($wishlistIds);

    echo json_encode([
        'ok'             => true,
        'action'         => $action,          // 'added' | 'removed'
        'wishlist_count' => $wishlistCount,
        'csrf_token'     => $newCsrfToken,
    ]);

} catch (\InvalidArgumentException $e) {
    // Product not found or not active — safe to surface a generic message
    echo json_encode([
        'ok'         => false,
        'error'      => 'Product not available.',
        'csrf_token' => $newCsrfToken,
    ]);
} catch (\Throwable $e) {
    error_log('[wishlist-toggle.php] Error for user #' . (Session::userId() ?? '?')
        . ', product #' . $productId . ': ' . $e->getMessage());

    echo json_encode([
        'ok'         => false,
        'error'      => 'Unable to update wishlist. Please try again.',
        'csrf_token' => $newCsrfToken,
    ]);
}
