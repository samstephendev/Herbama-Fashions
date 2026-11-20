<?php
/**
 * CartModel — Herbama Fashions
 * ==============================
 * Server-side cart for logged-in users (stored in DB).
 *
 * Security contract:
 *   - Prices are ALWAYS re-fetched from DB, never from POST or session.
 *   - Stock availability checked on add; definitive check is in createOrder().
 *   - All queries filter by user_id to prevent IDOR.
 */

declare(strict_types=1);

class CartModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = db();
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Internal helpers
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Get or create a cart for the user. Returns the cart id.
     */
    private function getOrCreateCart(int $userId): int
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM carts WHERE user_id = :uid LIMIT 1'
        );
        $stmt->execute([':uid' => $userId]);
        $cart = $stmt->fetch();

        if ($cart) {
            return (int) $cart['id'];
        }

        $this->db->prepare(
            'INSERT INTO carts (user_id) VALUES (:uid)'
        )->execute([':uid' => $userId]);

        return (int) $this->db->lastInsertId();
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Public API
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Get full cart contents for a user with server-side prices.
     * Returns array with 'items', 'subtotal', 'item_count'.
     */
    public function getCart(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                ci.id         AS cart_item_id,
                ci.quantity,
                ci.variant_id,
                p.id          AS product_id,
                p.name        AS product_name,
                p.slug        AS product_slug,
                p.base_price,            -- authoritative price from DB
                p.compare_price,
                p.is_active   AS product_active,
                pv.age_label,
                pv.color,
                pv.sku,
                pv.stock_qty,
                pv.is_active  AS variant_active,
                pi.image_path AS primary_image
             FROM carts c
             JOIN cart_items ci   ON ci.cart_id = c.id
             JOIN products p      ON ci.product_id = p.id
             LEFT JOIN product_variants pv ON ci.variant_id = pv.id
             LEFT JOIN product_images pi   ON pi.product_id = p.id AND pi.is_primary = 1
             WHERE c.user_id = :uid
             ORDER BY ci.added_at DESC'
        );
        $stmt->execute([':uid' => $userId]);
        $rows = $stmt->fetchAll();

        $subtotal  = 0.00;
        $itemCount = 0;
        $items     = [];

        foreach ($rows as $row) {
            // If product or variant has been deactivated, skip from totals but flag it
            $available = $row['product_active'] && $row['variant_active'];
            $qty       = (int) $row['quantity'];
            $price     = (float) $row['base_price'];  // DB price — not from client
            $lineTotal = $available ? round($price * $qty, 2) : 0.00;

            if ($available) {
                $subtotal  += $lineTotal;
                $itemCount += $qty;
            }

            $items[] = array_merge($row, [
                'line_total' => $lineTotal,
                'available'  => $available,
                'has_stock'  => $row['stock_qty'] >= $qty,
            ]);
        }

        return [
            'items'      => $items,
            'subtotal'   => round($subtotal, 2),
            'item_count' => $itemCount,
        ];
    }

    /**
     * Add an item to the cart (or increment quantity if already present).
     * Validates product and variant from DB; checks stock before adding.
     *
     * @return array{ok:bool, error?:string}
     */
    public function addItem(int $userId, int $productId, int $variantId, int $qty = 1): array
    {
        if ($qty < 1) {
            return ['ok' => false, 'error' => 'Quantity must be at least 1.'];
        }
        if ($qty > 10) {
            return ['ok' => false, 'error' => 'Maximum 10 units per item.'];
        }

        // Re-fetch variant from DB to validate (never trust client-supplied data)
        $stmt = $this->db->prepare(
            'SELECT pv.id, pv.stock_qty, pv.is_active, pv.product_id,
                    p.is_active AS product_active, p.name AS product_name
             FROM product_variants pv
             JOIN products p ON pv.product_id = p.id
             WHERE pv.id = :vid AND pv.product_id = :pid
             LIMIT 1'
        );
        $stmt->execute([':vid' => $variantId, ':pid' => $productId]);
        $variant = $stmt->fetch();

        if (!$variant || !$variant['is_active'] || !$variant['product_active']) {
            return ['ok' => false, 'error' => 'This product is no longer available.'];
        }

        // Check existing cart quantity for this variant
        $cartId  = $this->getOrCreateCart($userId);
        $existing = $this->db->prepare(
            'SELECT id, quantity FROM cart_items WHERE cart_id = :cid AND variant_id = :vid LIMIT 1'
        );
        $existing->execute([':cid' => $cartId, ':vid' => $variantId]);
        $existingItem = $existing->fetch();

        $newQty = $qty + (int) ($existingItem['quantity'] ?? 0);

        if ($newQty > $variant['stock_qty']) {
            $available = max(0, $variant['stock_qty'] - (int) ($existingItem['quantity'] ?? 0));
            if ($available <= 0) {
                return ['ok' => false, 'error' => 'This item is out of stock.'];
            }
            return ['ok' => false, 'error' => "Only {$available} more unit(s) available."];
        }

        if ($existingItem) {
            // Update existing cart item
            $this->db->prepare(
                'UPDATE cart_items SET quantity = :qty WHERE id = :id'
            )->execute([':qty' => $newQty, ':id' => $existingItem['id']]);
        } else {
            // Insert new cart item
            $this->db->prepare(
                'INSERT INTO cart_items (cart_id, product_id, variant_id, quantity)
                 VALUES (:cid, :pid, :vid, :qty)'
            )->execute([
                ':cid' => $cartId,
                ':pid' => $productId,
                ':vid' => $variantId,
                ':qty' => $qty,
            ]);
        }

        // Touch cart updated_at
        $this->db->prepare('UPDATE carts SET updated_at=NOW() WHERE id=:cid')
            ->execute([':cid' => $cartId]);

        return ['ok' => true];
    }

    /**
     * Update the quantity of a specific cart item.
     * qty=0 removes the item. User ID enforced to prevent IDOR.
     *
     * @return array{ok:bool, error?:string}
     */
    public function updateItem(int $userId, int $cartItemId, int $qty): array
    {
        if ($qty < 0 || $qty > 10) {
            return ['ok' => false, 'error' => 'Quantity must be 0–10.'];
        }

        // Fetch item with IDOR protection (join through carts.user_id)
        $stmt = $this->db->prepare(
            'SELECT ci.id, ci.variant_id, ci.quantity,
                    pv.stock_qty, p.is_active AS product_active, pv.is_active AS variant_active
             FROM cart_items ci
             JOIN carts c               ON ci.cart_id = c.id AND c.user_id = :uid
             JOIN product_variants pv   ON ci.variant_id = pv.id
             JOIN products p            ON pv.product_id = p.id
             WHERE ci.id = :ciid
             LIMIT 1'
        );
        $stmt->execute([':uid' => $userId, ':ciid' => $cartItemId]);
        $item = $stmt->fetch();

        if (!$item) {
            return ['ok' => false, 'error' => 'Cart item not found.'];
        }

        if ($qty === 0) {
            $this->db->prepare('DELETE FROM cart_items WHERE id = :id')
                ->execute([':id' => $cartItemId]);
            return ['ok' => true];
        }

        if ($qty > $item['stock_qty']) {
            return ['ok' => false, 'error' => "Only {$item['stock_qty']} unit(s) in stock."];
        }

        $this->db->prepare('UPDATE cart_items SET quantity = :qty WHERE id = :id')
            ->execute([':qty' => $qty, ':id' => $cartItemId]);

        return ['ok' => true];
    }

    /**
     * Remove a specific item from the cart. User ID enforced (IDOR).
     */
    public function removeItem(int $userId, int $cartItemId): bool
    {
        $stmt = $this->db->prepare(
            'DELETE FROM cart_items
             WHERE id = :ciid AND cart_id IN (SELECT id FROM carts WHERE user_id = :uid)'
        );
        $stmt->execute([':uid' => $userId, ':ciid' => $cartItemId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Clear the entire cart for a user (called after order placement).
     */
    public function clearCart(int $userId): void
    {
        $this->db->prepare(
            'DELETE FROM cart_items
             WHERE cart_id IN (SELECT id FROM carts WHERE user_id = :uid)'
        )->execute([':uid' => $userId]);
    }

    /**
     * Count total items in cart (for the header badge).
     * Stored in session for performance; this is the DB source of truth.
     */
    public function countItems(int $userId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(ci.quantity), 0)
             FROM cart_items ci
             JOIN carts c ON ci.cart_id = c.id
             WHERE c.user_id = :uid'
        );
        $stmt->execute([':uid' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Merge a session-based guest cart into the user's DB cart after login.
     * $guestItems = [['product_id'=>x, 'variant_id'=>y, 'quantity'=>z], ...]
     */
    public function mergeGuestCart(int $userId, array $guestItems): void
    {
        foreach ($guestItems as $item) {
            $pid = Sanitize::positiveInt($item['product_id']);
            $vid = Sanitize::positiveInt($item['variant_id']);
            $qty = Sanitize::nonNegativeInt($item['quantity']) ?? 1;

            if ($pid && $vid && $qty > 0) {
                // Silently ignore errors (item may no longer be available)
                try {
                    $this->addItem($userId, $pid, $vid, $qty);
                } catch (\Exception $e) {
                    error_log('[CartModel] mergeGuestCart item error: ' . $e->getMessage());
                }
            }
        }
    }
}
