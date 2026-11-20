<?php
/**
 * Admin Customer Detail — Herbama Fashions
 * ========================================
 * Shows customer account details, spend metrics, saved addresses,
 * and complete order history.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$userModel  = new UserModel();
$orderModel = new OrderModel();
$adminId    = (int) Session::userId();

$customerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$customer   = $userModel->findById($customerId);

if (!$customer) {
    Session::flash('error', 'Customer not found.');
    header('Location: ' . url('/admin/customers.php'));
    exit;
}

$pageTitle   = 'Customer: ' . $customer['full_name'];
$topbarTitle = 'Customer Profile';

$stats       = $userModel->getCustomerStats($customerId);
$addresses   = $userModel->getAddresses($customerId);
$ordersData  = $orderModel->getUserOrders($customerId, 1, 50);
$orders      = $ordersData['orders'] ?? [];

$cleanPhone = preg_replace('/\D/', '', $customer['phone'] ?? '');
$waLink = $cleanPhone ? "https://wa.me/" . $cleanPhone . "?text=" . rawurlencode("Hello " . $customer['full_name'] . "! 🌸 Greetings from Herbama Fashions.") : null;

require_once APP_ROOT . '/includes/admin-header.php';
?>

<div style="margin-bottom: 1.5rem;">
    <a href="<?= e(url('/admin/customers.php')) ?>" style="color:var(--admin-maroon);text-decoration:none;font-weight:600;font-size:0.9rem;">
        ← Back to Customers List
    </a>
</div>

<div style="display:grid;grid-template-columns:1fr 2fr;gap:1.5rem;">

    <!-- Left Column: Profile Card -->
    <div>
        <div class="card">
            <h2 class="card-title" style="margin-bottom:1rem;">Account Info</h2>

            <div style="font-size:1.15rem;font-weight:700;color:var(--admin-maroon);">
                <?= e($customer['full_name']) ?>
            </div>
            <div style="font-size:0.85rem;color:#666;margin:0.25rem 0 1rem;">
                <?= e($customer['email']) ?><br>
                📞 <?= e($customer['phone'] ?? 'No phone provided') ?>
            </div>

            <div style="display:flex;gap:0.5rem;margin-bottom:1.25rem;">
                <?php if ((int)$customer['is_blocked'] === 1): ?>
                    <span class="badge" style="background:#fbebee;color:#c0392b;">Suspended</span>
                <?php else: ?>
                    <span class="badge" style="background:#e8f8f0;color:#27ae60;">Active Account</span>
                <?php endif; ?>
                <span class="badge" style="background:#f5eef3;color:var(--admin-maroon);"><?= e(ucfirst($customer['role'])) ?></span>
            </div>

            <div style="font-size:0.82rem;color:#777;border-top:1px solid #f0eaee;padding-top:0.75rem;line-height:1.6;">
                Joined: <strong><?= e(date('d M Y', strtotime($customer['created_at']))) ?></strong><br>
                Last Login: <strong><?= !empty($customer['last_login_at']) ? e(date('d M Y, h:i A', strtotime($customer['last_login_at']))) : 'Never' ?></strong>
            </div>

            <?php if ($waLink): ?>
            <div style="margin-top:1.25rem;">
                <a href="<?= e($waLink) ?>" target="_blank" rel="noopener" class="btn btn-sm" style="background:#25D366;color:#fff;width:100%;justify-content:center;">
                    💬 Chat on WhatsApp
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Spend Metrics Card -->
        <div class="card">
            <h3 class="card-title" style="font-size:1.05rem;margin-bottom:1rem;">Customer Value</h3>
            <div style="display:flex;justify-content:space-between;margin-bottom:0.5rem;font-size:0.9rem;">
                <span>Total Orders Placed:</span>
                <strong><?= $stats['total_orders'] ?></strong>
            </div>
            <div style="display:flex;justify-content:space-between;margin-bottom:0.5rem;font-size:0.9rem;">
                <span>Total Lifetime Spend:</span>
                <strong style="color:var(--admin-maroon);font-size:1.05rem;">₹<?= number_format($stats['total_spend'], 2) ?></strong>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:0.82rem;color:#777;">
                <span>Last Order:</span>
                <span><?= !empty($stats['last_order_at']) ? e(date('d M Y', strtotime($stats['last_order_at']))) : 'None' ?></span>
            </div>
        </div>

        <!-- Saved Addresses -->
        <div class="card">
            <h3 class="card-title" style="font-size:1.05rem;margin-bottom:0.75rem;">Saved Addresses (<?= count($addresses) ?>)</h3>
            <?php if (empty($addresses)): ?>
                <div style="font-size:0.85rem;color:#888;">No saved addresses in address book.</div>
            <?php else: ?>
                <?php foreach ($addresses as $addr): ?>
                <div style="font-size:0.82rem;line-height:1.5;margin-bottom:0.75rem;padding-bottom:0.75rem;border-bottom:1px solid #f0eaee;">
                    <strong><?= e($addr['recipient_name']) ?></strong> <?= !empty($addr['is_default']) ? '<span style="color:var(--admin-maroon);font-weight:700;">(Default)</span>' : '' ?><br>
                    <?= e($addr['line1']) ?><?= $addr['line2'] ? ', ' . e($addr['line2']) : '' ?><br>
                    <?= e($addr['city']) ?>, <?= e($addr['state']) ?> — <?= e($addr['pincode']) ?><br>
                    <?= e($addr['country']) ?> · 📞 <?= e($addr['phone']) ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column: Order History -->
    <div>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Order History (<?= count($orders) ?>)</h2>
            </div>

            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Date</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Order Status</th>
                            <th>Payment</th>
                            <th style="text-align:right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center;color:#888;padding:2rem;">
                                This customer has not placed any orders yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $ord): ?>
                        <tr>
                            <td>
                                <a href="<?= e(url('/admin/order-detail.php?id=' . (int)$ord['id'])) ?>" style="color:var(--admin-maroon);font-weight:700;text-decoration:none;">
                                    <?= e($ord['public_order_id']) ?>
                                </a>
                            </td>
                            <td style="font-size:0.8rem;color:#666;white-space:nowrap;">
                                <?= e(date('d M Y', strtotime($ord['created_at']))) ?>
                            </td>
                            <td><?= (int)($ord['item_count'] ?? 1) ?> item<?= (int)($ord['item_count'] ?? 1) !== 1 ? 's' : '' ?></td>
                            <td><strong>₹<?= number_format((float)$ord['total'], 2) ?></strong></td>
                            <td><span class="badge badge-<?= e($ord['status']) ?>"><?= e($ord['status']) ?></span></td>
                            <td><span class="badge badge-<?= e($ord['payment_status']) ?>"><?= e($ord['payment_status']) ?></span></td>
                            <td style="text-align:right;">
                                <a href="<?= e(url('/admin/order-detail.php?id=' . (int)$ord['id'])) ?>" class="btn btn-outline btn-sm">
                                    View Order →
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php require_once APP_ROOT . '/includes/admin-footer.php'; ?>
