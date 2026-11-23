<?php
/**
 * Admin Orders Management — Heramba Fashion
 * ==========================================
 * Modules:
 *   - Search and filter by status and payment status
 *   - Quick "Mark as Paid" with automatic stock deduction and audit logging
 *   - Quick links to order details and customer WhatsApp chat
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$pageTitle = 'Orders';
$topbarTitle = 'Customer Orders';

$orderModel   = new OrderModel();
$settingModel = new SettingModel();
$adminId      = (int) Session::userId();

// ── POST Handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action  = trim($_POST['action'] ?? '');
    $orderId = (int)($_POST['order_id'] ?? 0);

    if ($action === 'mark_paid' && $orderId > 0) {
        $paymentRef = trim($_POST['payment_ref'] ?? '');
        $note       = trim($_POST['note'] ?? 'Payment verified manually by admin.');

        $res = $orderModel->markPaid($orderId, $adminId, $paymentRef ?: null, $note);
        if ($res['ok']) {
            Session::flash('success', 'Order marked as PAID. Inventory stock has been deducted.');
        } else {
            Session::flash('error', $res['error'] ?? 'Could not mark order as paid.');
        }
        header('Location: ' . url('/admin/orders.php'));
        exit;
    }

    if ($action === 'update_status' && $orderId > 0) {
        $newStatus = trim($_POST['status'] ?? '');
        $note      = trim($_POST['note'] ?? null);

        if ($newStatus !== '') {
            $orderModel->updateStatus($orderId, $newStatus, $adminId, $note);
            Session::flash('success', "Order status updated to &quot;{$newStatus}&quot;.");
        }
        header('Location: ' . url('/admin/orders.php'));
        exit;
    }
}

// ── Filters & Querying ────────────────────────────────────────────────────────
$statusFilter  = trim($_GET['status'] ?? '');
$paymentFilter = trim($_GET['payment_status'] ?? '');
$search        = trim($_GET['q'] ?? '');
$page          = max(1, (int)($_GET['page'] ?? 1));
$limit         = 20;

$filters = [];
if ($statusFilter !== '')  $filters['status'] = $statusFilter;
if ($paymentFilter !== '') $filters['payment_status'] = $paymentFilter;
if ($search !== '')        $filters['q'] = $search;

$ordersData = $orderModel->adminListOrders($filters, $page, $limit);
$orders     = $ordersData['orders'];
$totalPages = $ordersData['total_pages'];
$totalCount = $ordersData['total'];

require_once APP_ROOT . '/includes/admin-header.php';
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">All Customer Orders (<?= $totalCount ?>)</h2>
    </div>

    <!-- Filter Form -->
    <form method="GET" action="<?= e(url('/admin/orders.php')) ?>" style="margin-bottom:1.5rem;display:flex;gap:0.75rem;flex-wrap:wrap;align-items:center;">
        <input type="text" name="q" class="form-control" placeholder="Search Order ID, Name, Phone..." 
               value="<?= e($search) ?>" style="max-width:260px;">

        <select name="status" class="form-control" style="max-width:180px;">
            <option value="">All Order Statuses</option>
            <option value="placed"          <?= $statusFilter === 'placed' ? 'selected' : '' ?>>Placed</option>
            <option value="confirmed"       <?= $statusFilter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
            <option value="payment_pending" <?= $statusFilter === 'payment_pending' ? 'selected' : '' ?>>Payment Pending</option>
            <option value="paid"            <?= $statusFilter === 'paid' ? 'selected' : '' ?>>Paid</option>
            <option value="packed"          <?= $statusFilter === 'packed' ? 'selected' : '' ?>>Packed</option>
            <option value="dispatched"      <?= $statusFilter === 'dispatched' ? 'selected' : '' ?>>Dispatched</option>
            <option value="delivered"       <?= $statusFilter === 'delivered' ? 'selected' : '' ?>>Delivered</option>
            <option value="cancelled"       <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
        </select>

        <select name="payment_status" class="form-control" style="max-width:170px;">
            <option value="">All Payment States</option>
            <option value="unpaid"   <?= $paymentFilter === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
            <option value="paid"     <?= $paymentFilter === 'paid' ? 'selected' : '' ?>>Paid</option>
            <option value="refunded" <?= $paymentFilter === 'refunded' ? 'selected' : '' ?>>Refunded</option>
        </select>

        <button type="submit" class="btn btn-maroon btn-sm">Filter</button>
        <?php if ($search !== '' || $statusFilter !== '' || $paymentFilter !== ''): ?>
            <a href="<?= e(url('/admin/orders.php')) ?>" class="btn btn-outline btn-sm">Reset</a>
        <?php endif; ?>
    </form>

    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Order Ref</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Destination</th>
                    <th>Items</th>
                    <th>Total</th>
                    <th>Order Status</th>
                    <th>Payment</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="9" style="text-align:center;padding:2.5rem;color:#888;">
                        No orders match the specified filters.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($orders as $ord): ?>
                <tr>
                    <td>
                        <a href="<?= e(url('/admin/order-detail.php?id=' . (int)$ord['id'])) ?>" style="color:var(--admin-maroon);font-weight:700;text-decoration:none;">
                            <?= e($ord['public_order_id']) ?>
                        </a>
                        <?php if (!empty($ord['is_customized'])): ?>
                            <div style="font-size:0.7rem;color:#d35400;font-weight:700;">✂️ CUSTOMIZED</div>
                        <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap;font-size:0.8rem;color:#666;">
                        <?= e(date('d M Y, h:i A', strtotime($ord['created_at']))) ?>
                    </td>
                    <td>
                        <strong><?= e($ord['ship_name']) ?></strong>
                        <div style="font-size:0.78rem;color:#777;">📞 <?= e($ord['ship_phone']) ?></div>
                    </td>
                    <td>
                        <span style="font-size:0.82rem;font-weight:500;">
                            <?= e($ord['ship_city']) ?>, <?= e($ord['ship_country']) ?>
                        </span>
                    </td>
                    <td><?= (int)$ord['item_count'] ?> item<?= (int)$ord['item_count'] !== 1 ? 's' : '' ?></td>
                    <td><strong>₹<?= number_format((float)$ord['total'], 2) ?></strong></td>
                    <td><span class="badge badge-<?= e($ord['status']) ?>"><?= e($ord['status']) ?></span></td>
                    <td><span class="badge badge-<?= e($ord['payment_status']) ?>"><?= e($ord['payment_status']) ?></span></td>
                    <td style="text-align:right;">
                        <div style="display:inline-flex;gap:0.35rem;">
                            <a href="<?= e(url('/admin/order-detail.php?id=' . (int)$ord['id'])) ?>" class="btn btn-outline btn-sm">
                                Details
                            </a>

                            <?php if ($ord['payment_status'] !== 'paid' && $ord['status'] !== 'cancelled'): ?>
                            <button type="button" class="btn btn-success btn-sm"
                                    onclick="openConfirmModal({
                                        title: 'Mark as Paid?',
                                        message: 'Marking <?= e($ord['public_order_id']) ?> as paid will deduct stock for all line items. Confirm?',
                                        action: '<?= e(url('/admin/orders.php')) ?>',
                                        confirmText: 'Confirm Payment',
                                        btnClass: 'btn-success',
                                        params: { action: 'mark_paid', order_id: '<?= (int)$ord['id'] ?>' }
                                    })">
                                Paid
                            </button>
                            <?php endif; ?>

                            <!-- WhatsApp Message Button -->
                            <?php 
                                $cleanPhone = preg_replace('/\D/', '', $ord['ship_phone']);
                                $waTxt = "Hi " . $ord['ship_name'] . "! 👋 Heramba Fashion here regarding your order *" . $ord['public_order_id'] . "* (₹" . number_format((float)$ord['total'], 2) . ").";
                                $waUrl = "https://wa.me/" . $cleanPhone . "?text=" . rawurlencode($waTxt);
                            ?>
                            <a href="<?= e($waUrl) ?>" target="_blank" rel="noopener" class="btn btn-sm" style="background:#25D366;color:#fff;" title="Chat with customer on WhatsApp">
                                💬
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div style="display:flex;justify-content:center;gap:0.4rem;margin-top:1.5rem;">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a href="<?= e(url('/admin/orders.php?' . http_build_query(array_merge($_GET, ['page' => $p])))) ?>" 
               class="btn btn-sm <?= $p === $page ? 'btn-maroon' : 'btn-outline' ?>">
                <?= $p ?>
            </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once APP_ROOT . '/includes/admin-footer.php'; ?>
