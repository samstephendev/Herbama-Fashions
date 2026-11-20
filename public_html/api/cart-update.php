<?php
/**
 * Herbama Fashions — Cart Update AJAX Endpoint
 * ==============================================
 * Handles qty update and item removal for both logged-in users (DB cart)
 * and guest users (session cart). Returns JSON only — never outputs HTML.
 *
 * Actions:
 *   update  — set a cart item to a new quantity (qty 0 removes the item)
 *   remove  — delete a cart item entirely
 *
 * Security:
 *   - POST only.
 *   - Manual CSRF validation with hash_equals() (constant-time).
 *   - All DB operations are IDOR-safe (user_id always enforced by CartModel).
 *   - Prices are NEVER accepted from the client; always re-fetched from DB.
 *
 * Response shape:
 *   {ok: bool, error?: string, cart_count: int, subtotal?: float,
 *    line_total?: float, item_count?: int, csrf_token?: string}
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

// ── CSRF validation (manual hash_equals — safe for AJAX) ─────────────────────
$submittedCsrf = $_POST['_csrf'] ?? '';
if (!hash_equals(CSRF::token(), (string) $submittedCsrf)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid request token.']);
    exit;
}

// ── Rotate CSRF token after validation (double-submit protection) ─────────────
CSRF::rotate();
$newCsrfToken = CSRF::token(); // Fresh token to return to client

// ── Read action ───────────────────────────────────────────────────────────────
$action = Sanitize::string($_POST['action'] ?? '', 20);

if (!in_array($action, ['update', 'remove'], true)) {
    echo json_encode(['ok' => false, 'error' => 'Unknown action.', 'csrf_token' => $newCsrfToken]);
    exit;
}

// ── Helper: build JSON response ───────────────────────────────────────────────
function jsonResponse(array $data, string $newToken): void
{
    $data['csrf_token'] = $newToken;
    echo json_encode($data);
    exit;
}

// ═════════════════════════════════════════════════════════════════════════════
// LOGGED-IN USER — DB cart via CartModel (IDOR-safe)
// ═════════════════════════════════════════════════════════════════════════════
if (Session::isLoggedIn()) {
    $userId     = (int) Session::userId();
    $cartItemId = (int) ($_POST['cart_item_id'] ?? 0);

    if ($cartItemId <= 0) {
        jsonResponse(['ok' => false, 'error' => 'Invalid item.'], $newCsrfToken);
    }

    $cartModel = new CartModel();

    try {
        if ($action === 'update') {
            // ── Update quantity ───────────────────────────────────────────
            $qty = (int) ($_POST['quantity'] ?? 0);

            if ($qty < 1 || $qty > 10) {
                jsonResponse(['ok' => false, 'error' => 'Quantity must be between 1 and 10.'], $newCsrfToken);
            }

            // CartModel::updateItem(userId, cartItemId, qty)
            $result = $cartModel->updateItem($userId, $cartItemId, $qty);

            if (!$result['ok']) {
                jsonResponse(['ok' => false, 'error' => $result['error'] ?? 'Could not update item.'], $newCsrfToken);
            }

            // Fetch fresh cart for authoritative totals
            $cartData   = $cartModel->getCart($userId);
            $cartCount  = $cartModel->countItems($userId);

            // Find the updated line total for this item
            $lineTotal = null;
            foreach ($cartData['items'] as $item) {
                if ((int) $item['cart_item_id'] === $cartItemId) {
                    $lineTotal = (float) $item['line_total'];
                    break;
                }
            }

            jsonResponse([
                'ok'         => true,
                'cart_count' => $cartCount,
                'item_count' => $cartData['item_count'],
                'subtotal'   => $cartData['subtotal'],
                'line_total' => $lineTotal,
            ], $newCsrfToken);

        } else {
            // ── Remove item ───────────────────────────────────────────────
            // CartModel::removeItem(userId, cartItemId)
            $cartModel->removeItem($userId, $cartItemId);

            $cartCount = $cartModel->countItems($userId);

            jsonResponse([
                'ok'         => true,
                'cart_count' => $cartCount,
            ], $newCsrfToken);
        }

    } catch (\Throwable $e) {
        error_log('[cart-update.php] Logged-in cart error: ' . $e->getMessage());
        jsonResponse(['ok' => false, 'error' => 'An error occurred. Please try again.'], $newCsrfToken);
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// GUEST USER — session cart
// cart_item_id is the session key string "productId_variantId" (e.g. "5_12")
// ═════════════════════════════════════════════════════════════════════════════

// Ensure the session cart array is initialised
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// The session key comes in as cart_item_id (e.g. "5_12")
$sessionKey = Sanitize::string($_POST['cart_item_id'] ?? '', 50);

// Validate the session key format: digits _ digits
if (!preg_match('/^\d+_\d+$/', $sessionKey)) {
    jsonResponse(['ok' => false, 'error' => 'Invalid cart item.'], $newCsrfToken);
}

try {
    if ($action === 'update') {
        // ── Guest update ──────────────────────────────────────────────────
        $qty = (int) ($_POST['quantity'] ?? 0);

        if ($qty < 1 || $qty > 10) {
            jsonResponse(['ok' => false, 'error' => 'Quantity must be between 1 and 10.'], $newCsrfToken);
        }

        if (!isset($_SESSION['cart'][$sessionKey])) {
            jsonResponse(['ok' => false, 'error' => 'Item not found in cart.'], $newCsrfToken);
        }

        // Parse product_id and variant_id from the session key
        [$productIdStr, $variantIdStr] = explode('_', $sessionKey, 2);
        $productId = (int) $productIdStr;
        $variantId = (int) $variantIdStr;

        // Re-fetch price from DB (NEVER trust session-stored price)
        $productModel = new ProductModel();
        $variantRow   = $productModel->getVariantWithProduct($variantId);

        if (!$variantRow || (int) $variantRow['product_id'] !== $productId) {
            // Product/variant gone — remove from session
            unset($_SESSION['cart'][$sessionKey]);
            jsonResponse(['ok' => false, 'error' => 'This item is no longer available.'], $newCsrfToken);
        }

        // Check requested qty against stock
        if ($qty > (int) $variantRow['stock_qty']) {
            $available = (int) $variantRow['stock_qty'];
            jsonResponse([
                'ok'    => false,
                'error' => "Only {$available} unit(s) available in stock.",
            ], $newCsrfToken);
        }

        // Update session quantity
        $_SESSION['cart'][$sessionKey]['quantity'] = $qty;

        // Compute line total and subtotal
        $unitPrice = (float) $variantRow['base_price'];
        $lineTotal = round($unitPrice * $qty, 2);

        // Recompute subtotal across all guest cart items (prices from DB)
        $subtotal   = 0.00;
        $cartCount  = 0;

        foreach ($_SESSION['cart'] as $key => $entry) {
            [$pid, $vid] = array_map('intval', explode('_', $key, 2));
            $vRow = $productModel->getVariantWithProduct($vid);
            if ($vRow && (int) $vRow['product_id'] === $pid) {
                $entryQty   = (int) $entry['quantity'];
                $cartCount += $entryQty;
                $subtotal  += round((float) $vRow['base_price'] * $entryQty, 2);
            }
        }

        jsonResponse([
            'ok'         => true,
            'cart_count' => $cartCount,
            'line_total' => $lineTotal,
            'subtotal'   => round($subtotal, 2),
        ], $newCsrfToken);

    } else {
        // ── Guest remove ──────────────────────────────────────────────────
        unset($_SESSION['cart'][$sessionKey]);

        // Count remaining items
        $cartCount = 0;
        foreach ($_SESSION['cart'] as $entry) {
            $cartCount += (int) ($entry['quantity'] ?? 1);
        }

        jsonResponse([
            'ok'         => true,
            'cart_count' => $cartCount,
        ], $newCsrfToken);
    }

} catch (\Throwable $e) {
    error_log('[cart-update.php] Guest cart error: ' . $e->getMessage());
    jsonResponse(['ok' => false, 'error' => 'An error occurred. Please try again.'], $newCsrfToken);
}
