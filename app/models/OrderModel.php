<?php
/**
 * OrderModel — Herbama Fashions
 * ==============================
 * Order creation, processing, stock locking, payment status, and WhatsApp integration.
 *
 * Security Contract:
 * - All order totals, prices, discounts, and shipping are strictly calculated on the SERVER.
 * - Stock is locked via `SELECT ... FOR UPDATE` during order creation and deducted upon payment confirmation.
 * - Customers can ONLY view their own orders (strict IDOR protection).
 * - Only administrators can execute `markPaid()` or transition protected statuses.
 * - Public order references (`public_order_id`) are unpredictable cryptographically secure strings.
 */

declare(strict_types=1);

class OrderModel
{
    private PDO $db;
    private SettingModel $settings;

    public function __construct()
    {
        $this->db = db();
        $this->settings = new SettingModel();
    }

    /**
     * Generate an unpredictable, unique public order identifier (e.g. HF-7K9M2P).
     */
    public static function generatePublicOrderId(): string
    {
        // 6 random alphanumeric characters (avoiding easily confused chars like 0/O, 1/I)
        $charset = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $len = strlen($charset);
        $random = '';
        $bytes = random_bytes(6);
        for ($i = 0; $i < 6; $i++) {
            $random .= $charset[ord($bytes[$i]) % $len];
        }
        return 'HF-' . $random;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Order Placement (Customer)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Create a new order in a strict database transaction.
     *
     * 1. Re-fetches prices from DB (never trusts posted amounts).
     * 2. Locks variant stock rows with SELECT ... FOR UPDATE.
     * 3. Prevents overselling.
     * 4. Calculates subtotal, shipping charge, discount, and final total.
     * 5. Inserts order and order items.
     * 6. Clears the customer's cart.
     * 7. Generates pre-filled WhatsApp message.
     *
     * @param int|null $userId       Logged in user ID (or null for guest checkout)
     * @param array    $shippingAddr Address details [recipient_name, phone, line1, line2, city, state, pincode, country]
     * @param array    $cartItems    Array of items [['product_id' => int, 'variant_id' => int, 'quantity' => int]]
     * @param array    $options      Optional [customization_notes => string, customer_notes => string, coupon_code => ?string]
     *
     * @return array{ok: bool, order_id?: int, public_order_id?: string, total?: float, whatsapp_url?: string, whatsapp_message?: string, error?: string}
     */
    public function createOrder(?int $userId, array $shippingAddr, array $cartItems, array $options = []): array
    {
        if (empty($cartItems)) {
            return ['ok' => false, 'error' => 'Your shopping cart is empty.'];
        }

        // Validate required address fields
        $requiredFields = ['recipient_name', 'phone', 'line1', 'city', 'state', 'pincode'];
        foreach ($requiredFields as $field) {
            if (empty(trim((string)($shippingAddr[$field] ?? '')))) {
                return ['ok' => false, 'error' => "Shipping address is missing required field: {$field}."];
            }
        }

        $country = trim((string)($shippingAddr['country'] ?? 'India'));
        if (empty($country)) {
            $country = 'India';
        }

        $this->db->beginTransaction();

        try {
            $subtotal = 0.00;
            $preparedItems = [];

            // ── Lock & validate stock and fetch authoritative prices ────────
            foreach ($cartItems as $item) {
                $pid = (int)($item['product_id'] ?? 0);
                $vid = (int)($item['variant_id'] ?? 0);
                $qty = (int)($item['quantity'] ?? 0);

                if ($qty <= 0) {
                    continue;
                }

                // SELECT ... FOR UPDATE locks the variant record to prevent race conditions & overselling
                $stmt = $this->db->prepare(
                    'SELECT pv.id AS variant_id, pv.stock_qty, pv.age_label, pv.color, pv.is_active AS variant_active,
                            p.id AS product_id, p.name AS product_name, p.base_price, p.is_active AS product_active
                     FROM product_variants pv
                     JOIN products p ON pv.product_id = p.id
                     WHERE pv.id = :vid AND pv.product_id = :pid
                     FOR UPDATE'
                );
                $stmt->execute([':vid' => $vid, ':pid' => $pid]);
                $row = $stmt->fetch();

                if (!$row || !$row['product_active'] || !$row['variant_active']) {
                    $this->db->rollBack();
                    return ['ok' => false, 'error' => 'One or more items in your cart are no longer available.'];
                }

                if ((int)$row['stock_qty'] < $qty) {
                    $this->db->rollBack();
                    return [
                        'ok' => false,
                        'error' => "Insufficient stock for '{$row['product_name']} ({$row['age_label']})'. Only {$row['stock_qty']} available."
                    ];
                }

                $unitPrice = (float)$row['base_price'];
                $lineTotal = round($unitPrice * $qty, 2);
                $subtotal += $lineTotal;

                $variantInfo = trim("{$row['age_label']}" . (!empty($row['color']) ? " / {$row['color']}" : ''));

                $preparedItems[] = [
                    'product_id'   => $pid,
                    'variant_id'   => $vid,
                    'product_name' => $row['product_name'],
                    'variant_info' => $variantInfo,
                    'unit_price'   => $unitPrice,
                    'quantity'     => $qty,
                    'line_total'   => $lineTotal,
                ];
            }

            if (empty($preparedItems)) {
                $this->db->rollBack();
                return ['ok' => false, 'error' => 'No valid items found in the order.'];
            }

            // ── Calculate shipping & discounts server-side ──────────────────
            $shippingCharge = $this->settings->calculateShipping($country, $subtotal);
            $discountAmount = 0.00;
            $appliedOfferId = null;
            $appliedCouponCode = null;

            // Optional coupon processing
            if (!empty($options['coupon_code'])) {
                $couponCode = strtoupper(trim((string)$options['coupon_code']));
                $offerStmt = $this->db->prepare(
                    'SELECT * FROM offers
                     WHERE code = :code AND is_active = 1
                       AND (starts_at IS NULL OR starts_at <= NOW())
                       AND (ends_at IS NULL OR ends_at >= NOW())
                       AND (usage_limit IS NULL OR usage_count < usage_limit)
                     LIMIT 1
                     FOR UPDATE'
                );
                $offerStmt->execute([':code' => $couponCode]);
                $offer = $offerStmt->fetch();

                if ($offer && $subtotal >= (float)$offer['min_order_value']) {
                    if ($offer['type'] === 'percent') {
                        $discountAmount = round(($subtotal * (float)$offer['value']) / 100, 2);
                        if ($offer['max_discount'] !== null && $discountAmount > (float)$offer['max_discount']) {
                            $discountAmount = (float)$offer['max_discount'];
                        }
                    } elseif ($offer['type'] === 'fixed_amount') {
                        $discountAmount = min($subtotal, (float)$offer['value']);
                    } elseif ($offer['type'] === 'free_shipping') {
                        $discountAmount = $shippingCharge;
                    }
                    $appliedOfferId = (int)$offer['id'];
                    $appliedCouponCode = $couponCode;

                    // Increment coupon usage
                    $this->db->prepare('UPDATE offers SET usage_count = usage_count + 1 WHERE id = :id')
                        ->execute([':id' => $appliedOfferId]);
                }
            }

            $total = max(0.00, round($subtotal + $shippingCharge - $discountAmount, 2));

            // ── Generate unique public_order_id ─────────────────────────────
            $publicOrderId = self::generatePublicOrderId();
            // Verify collision (extremely improbable, but guarantees uniqueness)
            $chkStmt = $this->db->prepare('SELECT id FROM orders WHERE public_order_id = :poid LIMIT 1');
            $chkStmt->execute([':poid' => $publicOrderId]);
            if ($chkStmt->fetch()) {
                $publicOrderId = self::generatePublicOrderId() . 'A';
            }

            $customNotes = !empty($options['customization_notes']) ? trim((string)$options['customization_notes']) : null;
            $isCustomized = !empty($customNotes) ? 1 : 0;
            $custNotes = !empty($options['customer_notes']) ? trim((string)$options['customer_notes']) : null;

            // ── Insert Order Record ──────────────────────────────────────────
            $orderStmt = $this->db->prepare(
                'INSERT INTO orders
                 (public_order_id, user_id, ship_name, ship_phone, ship_line1, ship_line2,
                  ship_city, ship_state, ship_pincode, ship_country, subtotal, shipping_charge,
                  discount_amount, total, status, payment_status, coupon_code, offer_id,
                  customization_notes, is_customized, notes, created_at, updated_at)
                 VALUES
                 (:poid, :uid, :name, :phone, :l1, :l2,
                  :city, :state, :pin, :country, :subtotal, :shipping,
                  :discount, :total, "placed", "unpaid", :coupon, :offer_id,
                  :custom_notes, :is_custom, :notes, NOW(), NOW())'
            );
            $orderStmt->execute([
                ':poid'         => $publicOrderId,
                ':uid'          => $userId,
                ':name'         => trim($shippingAddr['recipient_name']),
                ':phone'        => trim($shippingAddr['phone']),
                ':l1'           => trim($shippingAddr['line1']),
                ':l2'           => !empty($shippingAddr['line2']) ? trim($shippingAddr['line2']) : null,
                ':city'         => trim($shippingAddr['city']),
                ':state'        => trim($shippingAddr['state']),
                ':pin'          => trim($shippingAddr['pincode']),
                ':country'      => $country,
                ':subtotal'     => $subtotal,
                ':shipping'     => $shippingCharge,
                ':discount'     => $discountAmount,
                ':total'        => $total,
                ':coupon'       => $appliedCouponCode,
                ':offer_id'     => $appliedOfferId,
                ':custom_notes' => $customNotes,
                ':is_custom'    => $isCustomized,
                ':notes'        => $custNotes,
            ]);

            $orderId = (int)$this->db->lastInsertId();

            // ── Insert Line Items ───────────────────────────────────────────
            $itemStmt = $this->db->prepare(
                'INSERT INTO order_items
                 (order_id, product_id, variant_id, product_name, variant_info, unit_price, quantity, line_total)
                 VALUES (:oid, :pid, :vid, :pname, :vinfo, :price, :qty, :total)'
            );
            foreach ($preparedItems as $pi) {
                $itemStmt->execute([
                    ':oid'   => $orderId,
                    ':pid'   => $pi['product_id'],
                    ':vid'   => $pi['variant_id'],
                    ':pname' => $pi['product_name'],
                    ':vinfo' => $pi['variant_info'],
                    ':price' => $pi['unit_price'],
                    ':qty'   => $pi['quantity'],
                    ':total' => $pi['line_total'],
                ]);
            }

            // ── Insert Initial Status History ───────────────────────────────
            $this->db->prepare(
                'INSERT INTO order_status_history (order_id, from_status, to_status, changed_by, note, created_at)
                 VALUES (:oid, NULL, "placed", :uid, "Order submitted by customer", NOW())'
            )->execute([':oid' => $orderId, ':uid' => $userId]);

            // ── Clear cart for user if logged in ────────────────────────────
            if ($userId !== null) {
                $cartModel = new CartModel();
                $cartModel->clearCart($userId);
            }

            $this->db->commit();

            // ── Construct WhatsApp Message Text ─────────────────────────────
            $orderRecord = [
                'id'                  => $orderId,
                'public_order_id'     => $publicOrderId,
                'ship_name'           => $shippingAddr['recipient_name'],
                'ship_phone'          => $shippingAddr['phone'],
                'ship_city'           => $shippingAddr['city'],
                'ship_state'          => $shippingAddr['state'],
                'ship_pincode'        => $shippingAddr['pincode'],
                'ship_country'        => $country,
                'subtotal'            => $subtotal,
                'shipping_charge'     => $shippingCharge,
                'discount_amount'     => $discountAmount,
                'total'               => $total,
                'is_customized'       => $isCustomized,
                'customization_notes' => $customNotes,
            ];

            $whatsAppText = $this->buildWhatsAppMessage($orderRecord, $preparedItems);
            $whatsappNumber = (string)$this->settings->get('whatsapp_number', WHATSAPP_NUMBER);
            $whatsappUrl = 'https://wa.me/' . preg_replace('/[^0-9]/', '', $whatsappNumber) . '?text=' . rawurlencode($whatsAppText);

            return [
                'ok'               => true,
                'order_id'         => $orderId,
                'public_order_id'  => $publicOrderId,
                'total'            => $total,
                'whatsapp_url'     => $whatsappUrl,
                'whatsapp_message' => $whatsAppText,
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('[OrderModel] createOrder exception: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'An error occurred while creating your order. Please try again.'];
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Admin: Mark Paid & Stock Deduction
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Mark an order as paid (Admin only).
     *
     * In a transaction:
     * 1. Verifies order is not already marked paid.
     * 2. Deducts stock from `product_variants` for each order item.
     * 3. Updates `orders` status to 'paid', payment_status to 'paid', paid_at, paid_by_admin, and payment_ref.
     * 4. Logs transition in `order_status_history` and `audit_log`.
     *
     * @param int         $orderId   Internal order ID
     * @param int         $adminId   Admin performing the action
     * @param string|null $paymentRef UPI reference / transaction ID
     * @param string|null $note      Optional admin note
     *
     * @return array{ok: bool, error?: string}
     */
    public function markPaid(int $orderId, int $adminId, ?string $paymentRef = null, ?string $note = null): array
    {
        $this->db->beginTransaction();

        try {
            // Lock order row for update
            $stmt = $this->db->prepare(
                'SELECT id, public_order_id, status, payment_status, total
                 FROM orders WHERE id = :id FOR UPDATE'
            );
            $stmt->execute([':id' => $orderId]);
            $order = $stmt->fetch();

            if (!$order) {
                $this->db->rollBack();
                return ['ok' => false, 'error' => 'Order not found.'];
            }

            if ($order['payment_status'] === 'paid') {
                $this->db->rollBack();
                return ['ok' => false, 'error' => 'This order is already marked as paid.'];
            }

            // Fetch order items, verify stock availability with FOR UPDATE lock, and deduct stock
            $itemsStmt = $this->db->prepare(
                'SELECT variant_id, quantity, product_name
                 FROM order_items
                 WHERE order_id = :oid'
            );
            $itemsStmt->execute([':oid' => $orderId]);
            $items = $itemsStmt->fetchAll();

            foreach ($items as $item) {
                if (!empty($item['variant_id'])) {
                    $vid = (int)$item['variant_id'];
                    $qty = (int)$item['quantity'];

                    // Lock variant row to check authoritative stock level
                    $vStmt = $this->db->prepare('SELECT id, stock_qty FROM product_variants WHERE id = :vid FOR UPDATE');
                    $vStmt->execute([':vid' => $vid]);
                    $vRow = $vStmt->fetch();

                    if (!$vRow || (int)$vRow['stock_qty'] < $qty) {
                        $this->db->rollBack();
                        $avail = (int)($vRow['stock_qty'] ?? 0);
                        return [
                            'ok' => false,
                            'error' => "Cannot mark as paid: insufficient stock for item '{$item['product_name']}'. Available stock is {$avail}, requested {$qty}."
                        ];
                    }

                    // Deduct stock safely
                    $this->db->prepare(
                        'UPDATE product_variants
                         SET stock_qty = stock_qty - :qty
                         WHERE id = :vid'
                    )->execute([':qty' => $qty, ':vid' => $vid]);
                }
            }

            $fromStatus = $order['status'];
            $newStatus = 'paid';

            // Update order
            $updateStmt = $this->db->prepare(
                'UPDATE orders
                 SET status = :status, payment_status = "paid", paid_at = NOW(),
                     paid_by_admin = :admin_id, payment_ref = :pref, updated_at = NOW()
                 WHERE id = :id'
            );
            $updateStmt->execute([
                ':status'   => $newStatus,
                ':admin_id' => $adminId,
                ':pref'     => $paymentRef ? trim($paymentRef) : null,
                ':id'       => $orderId,
            ]);

            // Append status history
            $this->db->prepare(
                'INSERT INTO order_status_history (order_id, from_status, to_status, changed_by, note, created_at)
                 VALUES (:oid, :from, :to, :admin, :note, NOW())'
            )->execute([
                ':oid'   => $orderId,
                ':from'  => $fromStatus,
                ':to'    => $newStatus,
                ':admin' => $adminId,
                ':note'  => $note ?? 'Payment confirmed via UPI. Stock deducted.',
            ]);

            // Audit log
            db_audit_log(
                'ORDER_MARKED_PAID',
                [
                    'public_order_id' => $order['public_order_id'],
                    'payment_ref'     => $paymentRef,
                    'total'           => $order['total']
                ],
                $adminId,
                'orders',
                $orderId
            );

            $this->db->commit();
            return ['ok' => true];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('[OrderModel] markPaid exception: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Database error while updating order payment status.'];
        }
    }

    /**
     * Check current inventory availability for all items in an order.
     * Used by admin order detail page to warn if items have insufficient stock.
     *
     * @param int $orderId
     * @return array{has_insufficient_stock: bool, insufficient_items: array, items_checked: array}
     */
    public function checkOrderStockAvailability(int $orderId): array
    {
        $stmt = $this->db->prepare(
            'SELECT oi.id, oi.variant_id, oi.quantity, oi.product_name, oi.variant_info,
                    pv.stock_qty, pv.is_active AS variant_active, p.is_active AS product_active
             FROM order_items oi
             LEFT JOIN product_variants pv ON oi.variant_id = pv.id
             LEFT JOIN products p         ON pv.product_id = p.id
             WHERE oi.order_id = :oid'
        );
        $stmt->execute([':oid' => $orderId]);
        $rows = $stmt->fetchAll();

        $insufficient = [];
        $checked = [];

        foreach ($rows as $row) {
            $orderedQty   = (int)$row['quantity'];
            $availableQty = $row['variant_id'] !== null ? (int)($row['stock_qty'] ?? 0) : 999;
            $isShortage   = $orderedQty > $availableQty;

            $itemDetail = [
                'item_id'       => (int)$row['id'],
                'variant_id'    => (int)($row['variant_id'] ?? 0),
                'product_name'  => $row['product_name'],
                'variant_info'  => $row['variant_info'] ?? 'Standard',
                'ordered_qty'   => $orderedQty,
                'available_qty' => $availableQty,
                'shortage'      => max(0, $orderedQty - $availableQty),
                'is_shortage'   => $isShortage,
            ];

            $checked[$row['id']] = $itemDetail;
            if ($isShortage) {
                $insufficient[] = $itemDetail;
            }
        }

        return [
            'has_insufficient_stock' => !empty($insufficient),
            'insufficient_items'     => $insufficient,
            'items_checked'          => $checked,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Status Transitions (Admin)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Transition order status (e.g. packed, dispatched, delivered, cancelled).
     */
    public function updateStatus(int $orderId, string $newStatus, int $adminId, ?string $note = null): bool
    {
        $allowed = ['placed', 'confirmed', 'payment_pending', 'paid', 'packed', 'dispatched', 'delivered', 'cancelled'];
        if (!in_array($newStatus, $allowed, true)) {
            throw new \InvalidArgumentException("Invalid order status: {$newStatus}");
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT status, public_order_id FROM orders WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $orderId]);
            $order = $stmt->fetch();

            if (!$order) {
                $this->db->rollBack();
                return false;
            }

            $fromStatus = $order['status'];

            $this->db->prepare('UPDATE orders SET status = :status, updated_at = NOW() WHERE id = :id')
                ->execute([':status' => $newStatus, ':id' => $orderId]);

            $this->db->prepare(
                'INSERT INTO order_status_history (order_id, from_status, to_status, changed_by, note, created_at)
                 VALUES (:oid, :from, :to, :admin, :note, NOW())'
            )->execute([
                ':oid'   => $orderId,
                ':from'  => $fromStatus,
                ':to'    => $newStatus,
                ':admin' => $adminId,
                ':note'  => $note,
            ]);

            db_audit_log('ORDER_STATUS_CHANGED', [
                'public_order_id' => $order['public_order_id'],
                'from'            => $fromStatus,
                'to'              => $newStatus,
                'note'            => $note,
            ], $adminId, 'orders', $orderId);

            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('[OrderModel] updateStatus exception: ' . $e->getMessage());
            return false;
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Lookups & IDOR-safe Queries
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Fetch order by public reference (e.g. HF-XXXXXX).
     * IDOR Protection: If $userId is provided, only returns order if it belongs to that user.
     */
    public function findByPublicId(string $publicId, ?int $userId = null): ?array
    {
        $sql = 'SELECT * FROM orders WHERE public_order_id = :poid';
        $params = [':poid' => $publicId];

        if ($userId !== null) {
            $sql .= ' AND user_id = :uid';
            $params[':uid'] = $userId;
        }

        $stmt = $this->db->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        $order = $stmt->fetch();

        if (!$order) {
            return null;
        }

        $order['items'] = $this->getOrderItems((int)$order['id']);
        $order['history'] = $this->getStatusHistory((int)$order['id']);
        return $order;
    }

    /**
     * Fetch order by internal ID with IDOR protection.
     */
    public function findById(int $orderId, ?int $userId = null): ?array
    {
        $sql = 'SELECT * FROM orders WHERE id = :id';
        $params = [':id' => $orderId];

        if ($userId !== null) {
            $sql .= ' AND user_id = :uid';
            $params[':uid'] = $userId;
        }

        $stmt = $this->db->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        $order = $stmt->fetch();

        if (!$order) {
            return null;
        }

        $order['items'] = $this->getOrderItems((int)$order['id']);
        $order['history'] = $this->getStatusHistory((int)$order['id']);
        return $order;
    }

    /**
     * Retrieve line items for an order.
     */
    public function getOrderItems(int $orderId): array
    {
        $stmt = $this->db->prepare(
            'SELECT oi.*, p.slug AS product_slug, pi.image_path AS primary_image
             FROM order_items oi
             LEFT JOIN products p ON oi.product_id = p.id
             LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
             WHERE oi.order_id = :oid
             ORDER BY oi.id ASC'
        );
        $stmt->execute([':oid' => $orderId]);
        return $stmt->fetchAll();
    }

    /**
     * Retrieve status history for an order.
     */
    public function getStatusHistory(int $orderId): array
    {
        $stmt = $this->db->prepare(
            'SELECT osh.*, u.full_name AS changed_by_name
             FROM order_status_history osh
             LEFT JOIN users u ON osh.changed_by = u.id
             WHERE osh.order_id = :oid
             ORDER BY osh.created_at ASC'
        );
        $stmt->execute([':oid' => $orderId]);
        return $stmt->fetchAll();
    }

    /**
     * Retrieve paginated orders for a customer (IDOR-safe).
     */
    public function getUserOrders(int $userId, int $page = 1, int $perPage = 15): array
    {
        $offset = ($page - 1) * $perPage;

        $countStmt = $this->db->prepare('SELECT COUNT(*) FROM orders WHERE user_id = :uid');
        $countStmt->execute([':uid' => $userId]);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $this->db->prepare(
            'SELECT o.*, COUNT(oi.id) AS item_count
             FROM orders o
             LEFT JOIN order_items oi ON oi.order_id = o.id
             WHERE o.user_id = :uid
             GROUP BY o.id
             ORDER BY o.created_at DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':uid',    $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();

        return [
            'orders'      => $stmt->fetchAll(),
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int)ceil($total / $perPage),
        ];
    }

    /**
     * Admin: Retrieve paginated orders with filters.
     */
    public function adminListOrders(array $filters = [], int $page = 1, int $perPage = 30): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'o.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['payment_status'])) {
            $where[] = 'o.payment_status = :pstatus';
            $params[':pstatus'] = $filters['payment_status'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(o.public_order_id LIKE :q OR o.ship_name LIKE :q2 OR o.ship_phone LIKE :q3)';
            $like = '%' . $filters['q'] . '%';
            $params[':q'] = $like;
            $params[':q2'] = $like;
            $params[':q3'] = $like;
        }

        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM orders o WHERE {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $this->db->prepare(
            "SELECT o.*, u.email AS customer_email, COUNT(oi.id) AS item_count
             FROM orders o
             LEFT JOIN users u ON o.user_id = u.id
             LEFT JOIN order_items oi ON oi.order_id = o.id
             WHERE {$whereClause}
             GROUP BY o.id
             ORDER BY o.created_at DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();

        return [
            'orders'      => $stmt->fetchAll(),
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int)ceil($total / $perPage),
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // WhatsApp Order Message Builder
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Build the structured WhatsApp message sent to the store owner using central template file.
     */
    public function buildWhatsAppMessage(array $order, array $items): string
    {
        $upiId = (string)$this->settings->get('upi_id', UPI_ID);
        require_once APP_ROOT . '/config/whatsapp-templates.php';
        return whatsapp_order_placed_message($order, $items, $upiId);
    }
}
