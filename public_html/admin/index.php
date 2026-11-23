<?php
/**
 * Admin Dashboard — Heramba Fashion
 * ===================================
 * Modules:
 *   - Today's orders count & value
 *   - Pending payments count & value
 *   - Total all-time revenue (paid orders)
 *   - Low-stock alerts
 *   - Recent orders & low stock alert tables
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$pageTitle = 'Dashboard';
$topbarTitle = 'Store Overview';

// ── Models ────────────────────────────────────────────────────────────────────
$orderModel   = new OrderModel();
$productModel = new ProductModel();
$db           = db();

// ── 1. Statistics Aggregation ────────────────────────────────────────────────
// Today's orders
$stmtToday = $db->query(
    'SELECT COUNT(id) AS cnt, COALESCE(SUM(total), 0) AS val 
     FROM orders 
     WHERE DATE(created_at) = CURDATE()'
);
$todayStats = $stmtToday->fetch();

// Pending payments (orders placed or payment_pending where payment_status = unpaid)
$stmtPending = $db->query(
    "SELECT COUNT(id) AS cnt, COALESCE(SUM(total), 0) AS val 
     FROM orders 
     WHERE payment_status = 'unpaid' AND status != 'cancelled'"
);
$pendingStats = $stmtPending->fetch();

// Total revenue (paid orders)
$stmtRevenue = $db->query(
    "SELECT COALESCE(SUM(total), 0) AS val 
     FROM orders 
     WHERE payment_status = 'paid'"
);
$totalRevenue = (float)$stmtRevenue->fetchColumn();

// Low stock alerts count (stock <= 5)
$lowStockVariants = $productModel->getLowStockVariants(5);
$lowStockCount = count($lowStockVariants);

// ── 2. Recent Orders ─────────────────────────────────────────────────────────
$recentOrders = $orderModel->adminListOrders([], 1, 8)['orders'];

require_once APP_ROOT . '/includes/admin-header.php';
?>

<style>
/* Stat Cards Grid */
.stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
    gap: 1.25rem;
    margin-bottom: 2rem;
}
.stat-card {
    background: #fff;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 10px rgba(122,31,61,0.05);
    border: 1px solid var(--admin-border);
    display: flex;
    align-items: center;
    gap: 1.25rem;
    position: relative;
    overflow: hidden;
}
.stat-card::before {
    content: "";
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: var(--admin-maroon);
}
.stat-card.gold::before { background: var(--admin-gold); }
.stat-card.orange::before { background: #e67e22; }
.stat-card.green::before { background: #27ae60; }
.stat-card.red::before { background: #c0392b; }

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    background: #FAF4EB;
    color: var(--admin-maroon);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
}
.stat-info h3 {
    margin: 0;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #777;
    font-weight: 600;
}
.stat-info .stat-value {
    font-size: 1.55rem;
    font-weight: 700;
    color: var(--admin-dark);
    margin: 0.25rem 0 0.15rem;
    line-height: 1.2;
}
.stat-info .stat-sub {
    font-size: 0.8rem;
    color: #999;
}

/* Dashboard 2-column Layout */
.dashboard-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 1.5rem;
}
@media (max-width: 1024px) {
    .dashboard-grid { grid-template-columns: 1fr; }
}

.quick-actions-bar {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
    margin-bottom: 2rem;
}
</style>

<!-- Quick Action Shortcuts -->
<div class="quick-actions-bar">
    <a href="<?= e(url('/admin/product-form.php')) ?>" class="btn btn-maroon">
        <span>+</span> Add New Product
    </a>
    <a href="<?= e(url('/admin/orders.php?status=payment_pending')) ?>" class="btn btn-gold">
        <span>⏳</span> Review Pending Payments
    </a>
    <a href="<?= e(url('/admin/stock.php?filter=low')) ?>" class="btn btn-outline">
        <span>📦</span> Low Stock Inventory
    </a>
    <a href="<?= e(url('/admin/settings.php')) ?>" class="btn btn-outline">
        <span>⚙️</span> Store Configuration
    </a>
</div>

<!-- Stat Cards -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon">🛍️</div>
        <div class="stat-info">
            <h3>Today's Orders</h3>
            <div class="stat-value"><?= (int)$todayStats['cnt'] ?></div>
            <div class="stat-sub">Value: ₹<?= number_format((float)$todayStats['val'], 2) ?></div>
        </div>
    </div>

    <div class="stat-card orange">
        <div class="stat-icon" style="background:#fef5e7;color:#e67e22;">⏳</div>
        <div class="stat-info">
            <h3>Pending Payments</h3>
            <div class="stat-value"><?= (int)$pendingStats['cnt'] ?></div>
            <div class="stat-sub">Unpaid: ₹<?= number_format((float)$pendingStats['val'], 2) ?></div>
        </div>
    </div>

    <div class="stat-card green">
        <div class="stat-icon" style="background:#e8f8f0;color:#27ae60;">💰</div>
        <div class="stat-info">
            <h3>Total Revenue</h3>
            <div class="stat-value">₹<?= number_format($totalRevenue, 2) ?></div>
            <div class="stat-sub">Paid orders to date</div>
        </div>
    </div>

    <div class="stat-card red">
        <div class="stat-icon" style="background:#fbebee;color:#c0392b;">⚠️</div>
        <div class="stat-info">
            <h3>Low Stock Alerts</h3>
            <div class="stat-value"><?= $lowStockCount ?></div>
            <div class="stat-sub">Items with ≤ 5 in stock</div>
        </div>
    </div>
</div>

<div class="dashboard-grid">
    <!-- Left: Recent Orders -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Recent Orders</h2>
            <a href="<?= e(url('/admin/orders.php')) ?>" class="btn btn-outline btn-sm">View All Orders →</a>
        </div>

        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($recentOrders)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: #888; padding: 2rem;">No orders placed yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentOrders as $ord): ?>
                    <tr>
                        <td>
                            <a href="<?= e(url('/admin/order-detail.php?id=' . (int)$ord['id'])) ?>" style="color:var(--admin-maroon);font-weight:700;text-decoration:none;">
                                <?= e($ord['public_order_id']) ?>
                            </a>
                        </td>
                        <td>
                            <div style="font-weight:600;"><?= e($ord['ship_name']) ?></div>
                            <div style="font-size:0.75rem;color:#777;"><?= e($ord['ship_phone']) ?></div>
                        </td>
                        <td><?= (int)$ord['item_count'] ?> item<?= (int)$ord['item_count'] !== 1 ? 's' : '' ?></td>
                        <td><strong>₹<?= number_format((float)$ord['total'], 2) ?></strong></td>
                        <td><span class="badge badge-<?= e($ord['status']) ?>"><?= e($ord['status']) ?></span></td>
                        <td><span class="badge badge-<?= e($ord['payment_status']) ?>"><?= e($ord['payment_status']) ?></span></td>
                        <td>
                            <div style="display:flex;gap:0.35rem;">
                                <a href="<?= e(url('/admin/order-detail.php?id=' . (int)$ord['id'])) ?>" class="btn btn-outline btn-sm" title="View details">
                                    Details
                                </a>
                                <?php if ($ord['payment_status'] !== 'paid' && $ord['status'] !== 'cancelled'): ?>
                                <button type="button" class="btn btn-success btn-sm" title="Mark as Paid"
                                        onclick="openConfirmModal({
                                            title: 'Mark Order as Paid?',
                                            message: 'Confirming payment for <?= e($ord['public_order_id']) ?> will update status to Paid and automatically deduct item stock quantities.',
                                            action: '<?= e(url('/admin/orders.php')) ?>',
                                            confirmText: 'Yes, Confirm Payment',
                                            btnClass: 'btn-success',
                                            params: { action: 'mark_paid', order_id: '<?= (int)$ord['id'] ?>' }
                                        })">
                                    Paid
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right: Low Stock Alert Box -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Low Stock Alert</h2>
            <a href="<?= e(url('/admin/stock.php')) ?>" class="btn btn-outline btn-sm">Manage →</a>
        </div>

        <?php if (empty($lowStockVariants)): ?>
            <div style="text-align:center;padding:2rem 1rem;color:#27ae60;">
                <div style="font-size:2rem;margin-bottom:0.5rem;">✓</div>
                <strong>Stock levels look healthy!</strong>
                <p style="font-size:0.85rem;color:#777;margin:0.25rem 0 0;">No active variants are at or below 5 units.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Variant</th>
                            <th>Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($lowStockVariants, 0, 7) as $lv): ?>
                        <tr>
                            <td>
                                <strong><?= e($lv['product_name']) ?></strong>
                            </td>
                            <td>
                                <span style="font-size:0.82rem;color:#666;">
                                    <?= e($lv['age_label']) ?><?= !empty($lv['color']) ? ' / ' . e($lv['color']) : '' ?>
                                </span>
                            </td>
                            <td>
                                <strong style="color: <?= (int)$lv['stock_qty'] === 0 ? '#c0392b' : '#e67e22' ?>;">
                                    <?= (int)$lv['stock_qty'] ?> left
                                </strong>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once APP_ROOT . '/includes/admin-footer.php'; ?>
