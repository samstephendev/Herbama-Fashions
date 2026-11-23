<?php
/**
 * Admin Products Management — Heramba Fashion
 * ============================================
 * Modules:
 *   - List products with category, price, discount, stock, active status
 *   - Search and category filters
 *   - Toggle hide/active
 *   - Delete product with confirmation modal
 *   - Links to edit or add new products
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$pageTitle = 'Products';
$topbarTitle = 'Product Catalogue';

$productModel = new ProductModel();
$adminId = (int)Session::userId();

// ── POST Handlers (Toggle active / Delete) ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action    = trim($_POST['action'] ?? '');
    $productId = (int)($_POST['product_id'] ?? 0);

    if ($action === 'toggle_active' && $productId > 0) {
        if ($productModel->toggleProductActive($productId, $adminId)) {
            Session::flash('success', 'Product visibility toggled successfully.');
        } else {
            Session::flash('error', 'Could not update product status.');
        }
        header('Location: ' . url('/admin/products.php'));
        exit;
    }

    if ($action === 'delete' && $productId > 0) {
        if ($productModel->deleteProduct($productId, $adminId)) {
            Session::flash('success', 'Product deleted permanently.');
        } else {
            Session::flash('error', 'Could not delete product.');
        }
        header('Location: ' . url('/admin/products.php'));
        exit;
    }
}

// ── Filters & Querying ────────────────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 25;

$products = $productModel->adminList($page, $limit, $search);
$categories = $productModel->getCategories(true);

require_once APP_ROOT . '/includes/admin-header.php';
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">All Products</h2>
        <a href="<?= e(url('/admin/product-form.php')) ?>" class="btn btn-maroon">
            <span>+</span> Add New Product
        </a>
    </div>

    <!-- Filters & Search -->
    <form method="GET" action="<?= e(url('/admin/products.php')) ?>" style="margin-bottom:1.5rem;display:flex;gap:0.75rem;flex-wrap:wrap;">
        <input type="text" name="q" class="form-control" style="max-width:320px;" 
               placeholder="Search product title..." value="<?= e($search) ?>">
        <button type="submit" class="btn btn-maroon btn-sm">Filter</button>
        <?php if ($search !== ''): ?>
            <a href="<?= e(url('/admin/products.php')) ?>" class="btn btn-outline btn-sm">Clear</a>
        <?php endif; ?>
    </form>

    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th style="width:60px;">Image</th>
                    <th>Product Details</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Status</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($products)): ?>
                <tr>
                    <td colspan="7" style="text-align:center;padding:2rem;color:#888;">
                        No products found matching your search.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($products as $p): ?>
                <?php 
                    $imgs = $productModel->getProductImages((int)$p['id']);
                    $thumb = !empty($imgs[0]['image_path']) ? asset('/uploads/' . $imgs[0]['image_path']) : asset('/assets/images/placeholder.svg');
                ?>
                <tr>
                    <td>
                        <img src="<?= e($thumb) ?>" alt="" 
                             style="width:48px;height:48px;object-fit:cover;border-radius:6px;border:1px solid #e0d4dd;"
                             onerror="this.src='<?= e(asset('/assets/images/placeholder.svg')) ?>'">
                    </td>
                    <td>
                        <strong><?= e($p['name']) ?></strong>
                        <div style="font-size:0.75rem;color:#888;"><?= e($p['slug']) ?></div>
                    </td>
                    <td>
                        <span style="font-size:0.85rem;font-weight:500;"><?= e($p['category_name'] ?? 'Uncategorized') ?></span>
                    </td>
                    <td>
                        <strong>₹<?= number_format((float)$p['base_price'], 2) ?></strong>
                    </td>
                    <td>
                        <strong style="color:<?= (int)$p['stock_total'] <= 5 ? '#c0392b' : '#27ae60' ?>;">
                            <?= (int)$p['stock_total'] ?> units
                        </strong>
                    </td>
                    <td>
                        <?php if ((int)$p['is_active'] === 1): ?>
                            <span class="badge badge-active">Active</span>
                        <?php else: ?>
                            <span class="badge badge-inactive">Hidden</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right;">
                        <div style="display:inline-flex;gap:0.4rem;">
                            <a href="<?= e(url('/admin/product-form.php?id=' . (int)$p['id'])) ?>" class="btn btn-outline btn-sm">
                                Edit
                            </a>

                            <!-- Toggle Visibility Button with Modal -->
                            <button type="button" class="btn btn-outline btn-sm"
                                    onclick="openConfirmModal({
                                        title: '<?= (int)$p['is_active'] === 1 ? 'Hide Product?' : 'Show Product?' ?>',
                                        message: 'Are you sure you want to change visibility for &quot;<?= e(addslashes($p['name'])) ?>&quot;?',
                                        action: '<?= e(url('/admin/products.php')) ?>',
                                        confirmText: '<?= (int)$p['is_active'] === 1 ? 'Hide from Store' : 'Make Active' ?>',
                                        btnClass: 'btn-maroon',
                                        params: { action: 'toggle_active', product_id: '<?= (int)$p['id'] ?>' }
                                    })">
                                <?= (int)$p['is_active'] === 1 ? 'Hide' : 'Show' ?>
                            </button>

                            <!-- Delete Button with Modal -->
                            <button type="button" class="btn btn-danger btn-sm"
                                    onclick="openConfirmModal({
                                        title: 'Delete Product?',
                                        message: 'Are you sure you want to delete &quot;<?= e(addslashes($p['name'])) ?>&quot;? This will remove all associated variants and gallery images.',
                                        action: '<?= e(url('/admin/products.php')) ?>',
                                        confirmText: 'Yes, Delete',
                                        btnClass: 'btn-danger',
                                        params: { action: 'delete', product_id: '<?= (int)$p['id'] ?>' }
                                    })">
                                Delete
                            </button>
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
