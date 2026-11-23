<?php
/**
 * Admin Order Detail — Heramba Fashion
 * =====================================
 * Modules:
 *   - Comprehensive order overview
 *   - Change order status with optional note
 *   - Mark as paid (stock deduction + UPI transaction ref)
 *   - WhatsApp customer messaging with customizable pre-filled templates
 *   - Direct links to printable Invoice and Packing Slip
 *   - Full status history audit trail
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$orderModel = new OrderModel();
$adminId    = (int) Session::userId();

$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$order   = $orderModel->findById($orderId);

if (!$order) {
    Session::flash('error', 'Order not found.');
    header('Location: ' . url('/admin/orders.php'));
    exit;
}

$pageTitle   = 'Order #' . $order['public_order_id'];
$topbarTitle = 'Order ' . $order['public_order_id'];

// Check stock availability if order is pending payment
$stockCheck = ($order['payment_status'] !== 'paid')
    ? $orderModel->checkOrderStockAvailability($orderId)
    : ['has_insufficient_stock' => false, 'insufficient_items' => [], 'items_checked' => []];

// ── POST Handlers (Update Status / Mark Paid) ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = trim($_POST['action'] ?? '');

    if ($action === 'mark_paid') {
        $paymentRef = trim($_POST['payment_ref'] ?? '');
        $note       = trim($_POST['note'] ?? 'Payment confirmed manually by admin.');

        $res = $orderModel->markPaid($orderId, $adminId, $paymentRef ?: null, $note);
        if ($res['ok']) {
            Session::flash('success', 'Order marked as PAID and inventory deducted.');
        } else {
            Session::flash('error', $res['error'] ?? 'Could not mark order as paid.');
        }
        header('Location: ' . url('/admin/order-detail.php?id=' . $orderId));
        exit;
    }

    if ($action === 'update_status') {
        $newStatus = trim($_POST['status'] ?? '');
        $note      = trim($_POST['note'] ?? '');

        if ($newStatus !== '') {
            $orderModel->updateStatus($orderId, $newStatus, $adminId, $note ?: null);
            Session::flash('success', "Status changed to &quot;{$newStatus}&quot;.");
        }
        header('Location: ' . url('/admin/order-detail.php?id=' . $orderId));
        exit;
    }
}

// ── WhatsApp Message Templates Generator ─────────────────────────────────────
$cleanPhone = preg_replace('/\D/', '', $order['ship_phone']);
$custName   = $order['ship_name'];
$orderRef   = $order['public_order_id'];
$amount     = number_format((float)$order['total'], 2);

$waTemplates = [
    'payment_reminder' => [
        'label' => 'Payment Reminder',
        'text'  => "Hi {$custName}! 🌸 Thank you for choosing Heramba Fashion. Your order *{$orderRef}* (₹{$amount}) is reserved. Please send your payment screenshot via UPI to complete confirmation. Let us know if you need any assistance!",
    ],
    'payment_received' => [
        'label' => 'Payment Confirmed & In Processing',
        'text'  => "Hello {$custName}! ✨ We have received your payment for order *{$orderRef}*. Our boutique team is now preparing your outfit with utmost care. You will receive dispatch updates soon! 🎀",
    ],
    'dispatched' => [
        'label' => 'Dispatch & Courier Notification',
        'text'  => "Great news, {$custName}! 📦 Your Heramba Fashion order *{$orderRef}* has been dispatched. Track your delivery or contact us anytime here on WhatsApp for live updates. Happy dressing!",
    ],
    'customization_query' => [
        'label' => 'Customization Sizing Query',
        'text'  => "Hi {$custName}! ✂️ Regarding your customization request for order *{$orderRef}*, could you please share a few additional measurements (shoulder to hem, chest)? Custom orders take 5 working days to craft.",
    ],
];

require_once APP_ROOT . '/includes/admin-header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;">
    <a href="<?= e(url('/admin/orders.php')) ?>" style="color:var(--admin-maroon);text-decoration:none;font-weight:600;font-size:0.9rem;">
        ← Back to Orders List
    </a>
    
    <!-- Printable Actions -->
    <div style="display:flex;gap:0.5rem;">
        <a href="<?= e(url('/admin/invoice.php?id=' . $orderId)) ?>" target="_blank" rel="noopener" class="btn btn-outline btn-sm">
            🖨️ Printable Invoice
        </a>
        <a href="<?= e(url('/admin/packing-slip.php?id=' . $orderId)) ?>" target="_blank" rel="noopener" class="btn btn-outline btn-sm">
            📦 Packing Slip
        </a>
    </div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:1.5rem;">

    <!-- Left Column: Order Items & Delivery -->
    <div>
        <!-- Order Header Card -->
        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Order Ref: <?= e($order['public_order_id']) ?></h2>
                    <div style="font-size:0.82rem;color:#777;margin-top:0.25rem;">
                        Placed on <?= e(date('d M Y, h:i A', strtotime($order['created_at']))) ?>
                    </div>
                </div>
                <div style="display:flex;gap:0.5rem;">
                    <span class="badge badge-<?= e($order['status']) ?>"><?= e($order['status']) ?></span>
                    <span class="badge badge-<?= e($order['payment_status']) ?>"><?= e($order['payment_status']) ?></span>
                </div>
            </div>

            <!-- Insufficient Stock Warning Alert -->
            <?php if ($stockCheck['has_insufficient_stock']): ?>
            <div style="background:#FFF0F0;border:1.5px solid #E74C3C;border-radius:8px;padding:1rem 1.25rem;margin-bottom:1.5rem;color:#C0392B;">
                <div style="display:flex;align-items:center;gap:0.5rem;font-weight:700;font-size:0.95rem;margin-bottom:0.35rem;">
                    <span>⚠️</span> <span>Insufficient Inventory Alert</span>
                </div>
                <div style="font-size:0.85rem;line-height:1.5;">
                    One or more variants in this order have insufficient stock available. Another customer may have completed payment for the last unit before confirmation.
                    <ul style="margin:0.35rem 0 0;padding-left:1.25rem;">
                        <?php foreach ($stockCheck['insufficient_items'] as $item): ?>
                            <li>
                                <strong><?= e($item['product_name']) ?> (<?= e($item['variant_info']) ?>)</strong>:
                                Ordered <strong><?= (int)$item['ordered_qty'] ?></strong> unit(s), but only <strong><?= (int)$item['available_qty'] ?></strong> in stock (Shortage: <?= (int)$item['shortage'] ?>).
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div style="margin-top:0.5rem;font-size:0.8rem;color:#78281F;">
                        <em>Note: Marking this order as paid will fail cleanly until stock is adjusted in <a href="<?= e(url('/admin/stock.php')) ?>" style="color:#C0392B;font-weight:700;text-decoration:underline;">Stock Management</a>.</em>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Customization Alert -->
            <?php if (!empty($order['customization_notes'])): ?>
            <div style="background:#fff9ed;border:1.5px solid #f39c12;border-radius:8px;padding:1rem;margin-bottom:1.5rem;color:#8a6d3b;">
                <strong>✂️ CUSTOMIZATION INSTRUCTIONS:</strong><br>
                <?= e($order['customization_notes']) ?>
                <div style="font-size:0.8rem;margin-top:0.35rem;font-weight:600;">
                    Note: Handcrafted custom outfits require 5 working days before dispatch.
                </div>
            </div>
            <?php endif; ?>

            <!-- Ordered Line Items -->
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Variant</th>
                            <th>Unit Price</th>
                            <th style="text-align:center;">Qty</th>
                            <th style="text-align:right;">Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order['items'] as $item): ?>
                        <?php $itemCheck = $stockCheck['items_checked'][$item['id']] ?? null; ?>
                        <tr>
                            <td>
                                <strong><?= e($item['product_name']) ?></strong>
                            </td>
                            <td>
                                <span style="font-size:0.85rem;color:#666;">
                                    <?= e($item['variant_info'] ?? 'Standard') ?>
                                </span>
                                <?php if ($itemCheck && $itemCheck['is_shortage']): ?>
                                <div style="margin-top:0.25rem;">
                                    <span class="badge badge-cancelled" style="font-size:0.75rem;">
                                        ⚠️ Only <?= (int)$itemCheck['available_qty'] ?> in stock
                                    </span>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>₹<?= number_format((float)$item['unit_price'], 2) ?></td>
                            <td style="text-align:center;"><strong><?= (int)$item['quantity'] ?></strong></td>
                            <td style="text-align:right;"><strong>₹<?= number_format((float)$item['line_total'], 2) ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Financials Summary -->
            <div style="margin-top:1.5rem;padding-top:1rem;border-top:1.5px dashed var(--admin-border);max-width:320px;margin-left:auto;">
                <div style="display:flex;justify-content:space-between;margin-bottom:0.4rem;font-size:0.9rem;">
                    <span>Subtotal:</span>
                    <span>₹<?= number_format((float)$order['subtotal'], 2) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:0.4rem;font-size:0.9rem;">
                    <span>Shipping:</span>
                    <span><?= (float)$order['shipping_charge'] > 0 ? '₹' . number_format((float)$order['shipping_charge'], 2) : 'FREE' ?></span>
                </div>
                <?php if ((float)$order['discount_amount'] > 0): ?>
                <div style="display:flex;justify-content:space-between;margin-bottom:0.4rem;font-size:0.9rem;color:#27ae60;">
                    <span>Discount:</span>
                    <span>-₹<?= number_format((float)$order['discount_amount'], 2) ?></span>
                </div>
                <?php endif; ?>
                <div style="display:flex;justify-content:space-between;font-weight:700;font-size:1.15rem;color:var(--admin-maroon);margin-top:0.5rem;padding-top:0.5rem;border-top:1px solid #e0d4dd;">
                    <span>Grand Total:</span>
                    <span>₹<?= number_format((float)$order['total'], 2) ?></span>
                </div>
            </div>
        </div>

        <!-- Status History Audit Trail -->
        <div class="card">
            <h2 class="card-title" style="margin-bottom:1rem;">Status Audit History</h2>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Transition</th>
                            <th>Updated By</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($order['history'])): ?>
                        <tr>
                            <td colspan="4" style="text-align:center;color:#888;">No history records found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($order['history'] as $h): ?>
                        <tr>
                            <td style="font-size:0.8rem;white-space:nowrap;color:#666;">
                                <?= e(date('d M Y, h:i A', strtotime($h['created_at']))) ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= e($h['to_status']) ?>">
                                    <?= e($h['from_status'] ?? 'new') ?> → <?= e($h['to_status']) ?>
                                </span>
                            </td>
                            <td><?= e($h['changed_by_name'] ?? 'System') ?></td>
                            <td><em style="font-size:0.85rem;color:#555;"><?= e($h['note'] ?? '—') ?></em></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Right Column: Actions, WhatsApp & Customer -->
    <div>
        <!-- Mark as Paid Action Card (if not yet paid) -->
        <?php if ($order['payment_status'] !== 'paid'): ?>
        <div class="card" style="border:2px solid #27ae60;background:#fcfdfc;">
            <h3 style="font-size:1.05rem;color:#27ae60;margin:0 0 0.5rem;display:flex;align-items:center;gap:0.4rem;">
                <span>💳</span> Mark Order as Paid
            </h3>
            <p style="font-size:0.82rem;color:#666;margin:0 0 1rem;">
                Verifies customer UPI payment, marks status as Paid, and automatically deducts stock in a database transaction.
            </p>

            <?php if ($stockCheck['has_insufficient_stock']): ?>
            <div style="background:#FFF0F0;border:1px solid #F5C6CB;color:#721C24;padding:0.75rem;border-radius:6px;font-size:0.8rem;margin-bottom:1rem;line-height:1.4;">
                <strong>⚠️ Insufficient Inventory Alert:</strong> One or more items exceed current stock. Submitting this form will fail until inventory is replenished.
            </div>
            <?php endif; ?>

            <form method="POST" action="<?= e(url('/admin/order-detail.php?id=' . $orderId)) ?>"
                  onsubmit="return confirm('Confirm payment receipt? This will permanently deduct inventory stock for the ordered items.');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="mark_paid">
                
                <div class="form-group">
                    <label class="form-label" style="font-size:0.75rem;">UPI Transaction ID / UTR (optional)</label>
                    <input type="text" name="payment_ref" class="form-control" placeholder="e.g. 4123891023">
                </div>

                <div class="form-group">
                    <label class="form-label" style="font-size:0.75rem;">Internal Note</label>
                    <input type="text" name="note" class="form-control" value="Payment verified on WhatsApp via UPI screenshot">
                </div>

                <button type="submit" class="btn btn-success" style="width:100%;justify-content:center;">
                    ✓ Confirm Payment & Deduct Stock
                </button>
            </form>
        </div>
        <?php else: ?>
        <div class="card" style="background:#e8f8f0;border:1px solid #c3e6cb;">
            <div style="color:#155724;font-weight:700;display:flex;align-items:center;gap:0.5rem;font-size:0.95rem;">
                <span>✓</span> Payment Verified
            </div>
            <div style="font-size:0.8rem;color:#155724;margin-top:0.35rem;">
                Paid at: <?= e(date('d M Y, h:i A', strtotime($order['paid_at'] ?? $order['updated_at']))) ?><br>
                <?php if (!empty($order['payment_ref'])): ?>
                    Ref: <code><?= e($order['payment_ref']) ?></code>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Change Order Status -->
        <div class="card">
            <h3 class="card-title" style="margin-bottom:1rem;font-size:1.1rem;">Update Order Status</h3>
            <form method="POST" action="<?= e(url('/admin/order-detail.php?id=' . $orderId)) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_status">

                <div class="form-group">
                    <label class="form-label">New Status</label>
                    <select name="status" class="form-control">
                        <option value="confirmed"       <?= $order['status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                        <option value="payment_pending" <?= $order['status'] === 'payment_pending' ? 'selected' : '' ?>>Payment Pending</option>
                        <option value="paid"            <?= $order['status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                        <option value="packed"          <?= $order['status'] === 'packed' ? 'selected' : '' ?>>Packed</option>
                        <option value="dispatched"      <?= $order['status'] === 'dispatched' ? 'selected' : '' ?>>Dispatched</option>
                        <option value="delivered"       <?= $order['status'] === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                        <option value="cancelled"       <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Change Note (visible in tracking)</label>
                    <input type="text" name="note" class="form-control" placeholder="e.g. Courier: Delhivery AWB# 12345678">
                </div>

                <button type="submit" class="btn btn-maroon" style="width:100%;justify-content:center;">
                    Update Status
                </button>
            </form>
        </div>

        <!-- WhatsApp Messaging Module -->
        <div class="card">
            <h3 class="card-title" style="margin-bottom:0.75rem;font-size:1.1rem;display:flex;align-items:center;gap:0.4rem;">
                <span style="color:#25D366;">💬</span> WhatsApp Customer
            </h3>
            <p style="font-size:0.8rem;color:#777;margin:0 0 1rem;">
                Send pre-formatted notifications directly to <strong><?= e($order['ship_phone']) ?></strong>.
            </p>

            <div style="display:flex;flex-direction:column;gap:0.5rem;">
                <?php foreach ($waTemplates as $tKey => $tpl): ?>
                <?php 
                    $waLink = "https://wa.me/" . $cleanPhone . "?text=" . rawurlencode($tpl['text']);
                ?>
                <a href="<?= e($waLink) ?>" target="_blank" rel="noopener" class="btn btn-outline btn-sm" style="text-align:left;justify-content:flex-start;padding:0.5rem 0.75rem;">
                    <span style="color:#25D366;font-size:1rem;">💬</span> <?= e($tpl['label']) ?> ↗
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Customer & Shipping Address -->
        <div class="card">
            <h3 class="card-title" style="margin-bottom:0.75rem;font-size:1.1rem;">Delivery Destination</h3>
            <div style="font-size:0.88rem;line-height:1.5;color:#444;">
                <strong><?= e($order['ship_name']) ?></strong><br>
                📞 <?= e($order['ship_phone']) ?><br>
                <?= e($order['ship_line1']) ?><?= $order['ship_line2'] ? ', ' . e($order['ship_line2']) : '' ?><br>
                <?= e($order['ship_city']) ?>, <?= e($order['ship_state']) ?> — <?= e($order['ship_pincode']) ?><br>
                <strong><?= e($order['ship_country']) ?></strong>
            </div>
            <?php if (!empty($order['user_id'])): ?>
                <div style="margin-top:0.75rem;padding-top:0.75rem;border-top:1px solid #f0eaee;">
                    <a href="<?= e(url('/admin/customer-detail.php?id=' . (int)$order['user_id'])) ?>" style="font-size:0.82rem;color:var(--admin-maroon);font-weight:600;text-decoration:none;">
                        View Customer Profile →
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php require_once APP_ROOT . '/includes/admin-footer.php'; ?>
