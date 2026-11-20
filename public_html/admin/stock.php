<?php
/**
 * Admin Stock & Inventory Management — Herbama Fashions
 * =====================================================
 * Modules:
 *   - Low-stock threshold filter & alerts
 *   - Manual stock adjustment with required reason
 *   - Audit trail on stock updates
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$pageTitle = 'Stock & Inventory';
$topbarTitle = 'Inventory Management';

$productModel = new ProductModel();
$settingModel = new SettingModel();
$adminId      = (int) Session::userId();

$defaultThreshold = (int) $settingModel->get('low_stock_threshold', 5);

// ── POST Handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = trim($_POST['action'] ?? '');

    // 1. Manual stock adjustment
    if ($action === 'adjust_stock') {
        $variantId = (int)($_POST['variant_id'] ?? 0);
        $newStock  = max(0, (int)($_POST['new_stock'] ?? 0));
        $reason    = trim($_POST['reason'] ?? 'Manual stock adjustment');

        if ($variantId > 0) {
            $productModel->setVariantStock($variantId, $newStock, $adminId, $reason);
            Session::flash('success', "Stock updated to {$newStock} units.");
        } else {
            Session::flash('error', 'Invalid variant specified.');
        }
        header('Location: ' . url('/admin/stock.php'));
        exit;
    }

    // 2. Update threshold setting
    if ($action === 'update_threshold') {
        $threshold = max(1, (int)($_POST['threshold'] ?? 5));
        $settingModel->set('low_stock_threshold', (string)$threshold, $adminId);
        Session::flash('success', "Low-stock alert threshold set to {$threshold} units.");
        header('Location: ' . url('/admin/stock.php'));
        exit;
    }
}

// ── Filters & Listing ─────────────────────────────────────────────────────────
$filter = trim($_GET['filter'] ?? 'all'); // 'all', 'low', 'out'
$search = trim($_GET['q'] ?? '');

$threshold = (int) $settingModel->get('low_stock_threshold', 5);

// Query variants with product details
$sql = 'SELECT pv.*, p.name AS product_name, p.slug AS product_slug, c.name AS category_name
        FROM product_variants pv
        JOIN products p ON pv.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE 1=1 ';
$params = [];

if ($filter === 'low') {
    $sql .= 'AND pv.stock_qty <= :thresh AND pv.stock_qty > 0 ';
    $params[':thresh'] = $threshold;
} elseif ($filter === 'out') {
    $sql .= 'AND pv.stock_qty = 0 ';
}

if ($search !== '') {
    $sql .= 'AND (p.name LIKE :q OR pv.sku LIKE :q2 OR pv.age_label LIKE :q3) ';
    $like = '%' . $search . '%';
    $params[':q']  = $like;
    $params[':q2'] = $like;
    $params[':q3'] = $like;
}

$sql .= 'ORDER BY pv.stock_qty ASC, p.name ASC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$variants = $stmt->fetchAll();

require_once APP_ROOT . '/includes/admin-header.php';
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Inventory & Stock Tracking</h2>

        <!-- Threshold Setting Box -->
        <form method="POST" action="<?= e(url('/admin/stock.php')) ?>" style="display:flex;align-items:center;gap:0.5rem;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_threshold">
            <label style="font-size:0.8rem;color:#777;font-weight:600;">Alert Threshold:</label>
            <input type="number" name="threshold" value="<?= $threshold ?>" min="1" max="100" class="form-control" style="width:70px;padding:0.3rem 0.5rem;font-size:0.85rem;">
            <button type="submit" class="btn btn-outline btn-sm">Save</button>
        </form>
    </div>

    <!-- Filter Buttons & Search -->
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;">
        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
            <a href="<?= e(url('/admin/stock.php?filter=all')) ?>" class="btn btn-sm <?= $filter === 'all' ? 'btn-maroon' : 'btn-outline' ?>">
                All Stock (<?= count($variants) ?>)
            </a>
            <a href="<?= e(url('/admin/stock.php?filter=low')) ?>" class="btn btn-sm <?= $filter === 'low' ? 'btn-gold' : 'btn-outline' ?>">
                ⚠️ Low Stock (≤ <?= $threshold ?>)
            </a>
            <a href="<?= e(url('/admin/stock.php?filter=out')) ?>" class="btn btn-sm <?= $filter === 'out' ? 'btn-danger' : 'btn-outline' ?>">
                🚫 Out of Stock
            </a>
        </div>

        <form method="GET" action="<?= e(url('/admin/stock.php')) ?>" style="display:flex;gap:0.5rem;">
            <input type="hidden" name="filter" value="<?= e($filter) ?>">
            <input type="text" name="q" class="form-control" placeholder="Search product or SKU..." 
                   value="<?= e($search) ?>" style="max-width:260px;">
            <button type="submit" class="btn btn-maroon btn-sm">Search</button>
            <?php if ($search !== ''): ?>
                <a href="<?= e(url('/admin/stock.php?filter=' . $filter)) ?>" class="btn btn-outline btn-sm">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Age / Size</th>
                    <th>Color</th>
                    <th>SKU</th>
                    <th>Current Quantity</th>
                    <th>Stock Status</th>
                    <th style="text-align:right;">Quick Adjustment</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($variants)): ?>
                <tr>
                    <td colspan="7" style="text-align:center;color:#888;padding:2rem;">
                        No items found matching the selected filter.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($variants as $v): ?>
                <tr>
                    <td>
                        <a href="<?= e(url('/admin/product-form.php?id=' . (int)$v['product_id'])) ?>" style="color:var(--admin-maroon);font-weight:600;text-decoration:none;">
                            <?= e($v['product_name']) ?>
                        </a>
                        <div style="font-size:0.75rem;color:#888;"><?= e($v['category_name'] ?? '') ?></div>
                    </td>
                    <td><strong><?= e($v['age_label']) ?></strong></td>
                    <td><?= e($v['color'] ?? '—') ?></td>
                    <td><code><?= e($v['sku'] ?? '—') ?></code></td>
                    <td>
                        <span style="font-size:1.1rem;font-weight:700;color:<?= (int)$v['stock_qty'] === 0 ? '#c0392b' : ((int)$v['stock_qty'] <= $threshold ? '#e67e22' : '#27ae60') ?>;">
                            <?= (int)$v['stock_qty'] ?>
                        </span>
                    </td>
                    <td>
                        <?php if ((int)$v['stock_qty'] === 0): ?>
                            <span class="badge" style="background:#fbebee;color:#c0392b;">Out of Stock</span>
                        <?php elseif ((int)$v['stock_qty'] <= $threshold): ?>
                            <span class="badge" style="background:#fef5e7;color:#e67e22;">Low Stock (≤ <?= $threshold ?>)</span>
                        <?php else: ?>
                            <span class="badge" style="background:#e8f8f0;color:#27ae60;">In Stock</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right;">
                        <button type="button" class="btn btn-outline btn-sm"
                                onclick="openStockModal(<?= (int)$v['id'] ?>, '<?= e(addslashes($v['product_name'])) ?> (<?= e($v['age_label']) ?>)', <?= (int)$v['stock_qty'] ?>)">
                            ✏️ Adjust
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Stock Adjustment Modal -->
<div class="admin-modal-backdrop" id="stockModal">
    <div class="admin-modal">
        <h3 id="stockModalTitle">Adjust Stock</h3>
        <p style="font-size:0.85rem;color:#777;margin:0 0 1rem;" id="stockModalSubtitle"></p>

        <form method="POST" action="<?= e(url('/admin/stock.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="adjust_stock">
            <input type="hidden" name="variant_id" id="modalVariantId">

            <div class="form-group">
                <label class="form-label">New Stock Quantity *</label>
                <input type="number" name="new_stock" id="modalNewStock" class="form-control" min="0" required>
            </div>

            <div class="form-group">
                <label class="form-label">Reason for Adjustment *</label>
                <input type="text" name="reason" class="form-control" required placeholder="e.g. Physical inventory count, Restocked, Damaged item">
            </div>

            <div class="admin-modal-actions">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeStockModal()">Cancel</button>
                <button type="submit" class="btn btn-maroon btn-sm">Save Adjustment</button>
            </div>
        </form>
    </div>
</div>

<script>
function openStockModal(variantId, label, currentQty) {
    document.getElementById('modalVariantId').value = variantId;
    document.getElementById('modalNewStock').value = currentQty;
    document.getElementById('stockModalSubtitle').innerText = 'Adjusting inventory for ' + label + ' (Current: ' + currentQty + ')';
    document.getElementById('stockModal').classList.add('active');
}
function closeStockModal() {
    document.getElementById('stockModal').classList.remove('active');
}
</script>

<?php require_once APP_ROOT . '/includes/admin-footer.php'; ?>
