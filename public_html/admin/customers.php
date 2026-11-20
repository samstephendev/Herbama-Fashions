<?php
/**
 * Admin Customers Management — Herbama Fashions
 * =============================================
 * Modules:
 *   - List registered customers
 *   - Search by name or email
 *   - Block and unblock customer accounts with confirmation
 *   - View customer order history and spend statistics
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$pageTitle = 'Customers';
$topbarTitle = 'Customer Management';

$userModel = new UserModel();
$adminId   = (int) Session::userId();

// ── POST Handlers (Block / Unblock) ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = trim($_POST['action'] ?? '');
    $targetUserId = (int)($_POST['target_user_id'] ?? 0);

    if ($action === 'block' && $targetUserId > 0) {
        $userModel->setBlockStatus($targetUserId, true, $adminId);
        Session::flash('success', 'Customer account has been suspended.');
        header('Location: ' . url('/admin/customers.php'));
        exit;
    }

    if ($action === 'unblock' && $targetUserId > 0) {
        $userModel->setBlockStatus($targetUserId, false, $adminId);
        Session::flash('success', 'Customer account has been restored to active status.');
        header('Location: ' . url('/admin/customers.php'));
        exit;
    }
}

// ── Search & Listing ─────────────────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 25;

$customers = $userModel->adminListUsers($page, $limit, $search);

require_once APP_ROOT . '/includes/admin-header.php';
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Registered Customer Accounts</h2>
    </div>

    <!-- Search Form -->
    <form method="GET" action="<?= e(url('/admin/customers.php')) ?>" style="margin-bottom:1.5rem;display:flex;gap:0.75rem;">
        <input type="text" name="q" class="form-control" placeholder="Search customer name or email..." 
               value="<?= e($search) ?>" style="max-width:320px;">
        <button type="submit" class="btn btn-maroon btn-sm">Search</button>
        <?php if ($search !== ''): ?>
            <a href="<?= e(url('/admin/customers.php')) ?>" class="btn btn-outline btn-sm">Clear</a>
        <?php endif; ?>
    </form>

    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Customer Name</th>
                    <th>Email Address</th>
                    <th>Phone</th>
                    <th>Orders</th>
                    <th>Total Spend</th>
                    <th>Joined</th>
                    <th>Status</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($customers)): ?>
                <tr>
                    <td colspan="8" style="text-align:center;color:#888;padding:2rem;">
                        No customer accounts found.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($customers as $c): ?>
                <?php 
                    $stats = $userModel->getCustomerStats((int)$c['id']);
                ?>
                <tr>
                    <td>
                        <a href="<?= e(url('/admin/customer-detail.php?id=' . (int)$c['id'])) ?>" style="color:var(--admin-maroon);font-weight:700;text-decoration:none;">
                            <?= e($c['full_name']) ?>
                        </a>
                    </td>
                    <td><?= e($c['email']) ?></td>
                    <td><?= e($c['phone'] ?? '—') ?></td>
                    <td><?= $stats['total_orders'] ?> orders</td>
                    <td><strong>₹<?= number_format($stats['total_spend'], 2) ?></strong></td>
                    <td style="font-size:0.8rem;color:#666;">
                        <?= e(date('d M Y', strtotime($c['created_at']))) ?>
                    </td>
                    <td>
                        <?php if ((int)$c['is_blocked'] === 1): ?>
                            <span class="badge" style="background:#fbebee;color:#c0392b;">Suspended</span>
                        <?php else: ?>
                            <span class="badge" style="background:#e8f8f0;color:#27ae60;">Active</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right;">
                        <div style="display:inline-flex;gap:0.35rem;">
                            <a href="<?= e(url('/admin/customer-detail.php?id=' . (int)$c['id'])) ?>" class="btn btn-outline btn-sm">
                                View Profile
                            </a>

                            <?php if ((int)$c['is_blocked'] === 1): ?>
                                <button type="button" class="btn btn-success btn-sm"
                                        onclick="openConfirmModal({
                                            title: 'Unblock Customer Account?',
                                            message: 'Restore access for <?= e(addslashes($c['full_name'])) ?> (<?= e($c['email']) ?>)?',
                                            action: '<?= e(url('/admin/customers.php')) ?>',
                                            confirmText: 'Unblock Account',
                                            btnClass: 'btn-success',
                                            params: { action: 'unblock', target_user_id: '<?= (int)$c['id'] ?>' }
                                        })">
                                    Unblock
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-danger btn-sm"
                                        onclick="openConfirmModal({
                                            title: 'Suspend Customer Account?',
                                            message: 'Are you sure you want to block <?= e(addslashes($c['full_name'])) ?> (<?= e($c['email']) ?>)? They will not be able to log in or place orders.',
                                            action: '<?= e(url('/admin/customers.php')) ?>',
                                            confirmText: 'Suspend Account',
                                            btnClass: 'btn-danger',
                                            params: { action: 'block', target_user_id: '<?= (int)$c['id'] ?>' }
                                        })">
                                    Block
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

<?php require_once APP_ROOT . '/includes/admin-footer.php'; ?>
