<?php
/**
 * Admin Product Add/Edit Form — Heramba Fashion
 * ===============================================
 * Features:
 *   - Create or edit product details
 *   - Category selection
 *   - Price & discount
 *   - Multiple image upload (JPG/PNG/WEBP, real MIME check, random filename, size limit)
 *   - Set primary image & delete images
 *   - Manage variants (age label, color, SKU, stock quantity)
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$productModel = new ProductModel();
$adminId      = (int) Session::userId();

$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product   = null;
$images    = [];
$variants  = [];

if ($productId > 0) {
    $product = $productModel->findById($productId);
    if (!$product) {
        Session::flash('error', 'Product not found.');
        header('Location: ' . url('/admin/products.php'));
        exit;
    }
    $images   = $productModel->getProductImages($productId);
    $variants = $productModel->getProductVariants($productId);
}

$pageTitle   = $productId > 0 ? 'Edit Product: ' . $product['name'] : 'Add New Product';
$topbarTitle = $productId > 0 ? 'Edit Product' : 'Create Product';

// ── POST Handling ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $subAction = trim($_POST['sub_action'] ?? 'save_product');

    // ── 1. Set Primary Image ──────────────────────────────────────────────────
    if ($subAction === 'set_primary_image' && $productId > 0) {
        $imgId = (int)($_POST['image_id'] ?? 0);
        if ($imgId > 0) {
            $productModel->setPrimaryImage($imgId, $productId, $adminId);
            Session::flash('success', 'Primary image updated.');
        }
        header('Location: ' . url('/admin/product-form.php?id=' . $productId));
        exit;
    }

    // ── 2. Delete Image ───────────────────────────────────────────────────────
    if ($subAction === 'delete_image' && $productId > 0) {
        $imgId = (int)($_POST['image_id'] ?? 0);
        if ($imgId > 0) {
            $productModel->deleteImage($imgId, $adminId);
            Session::flash('success', 'Image deleted.');
        }
        header('Location: ' . url('/admin/product-form.php?id=' . $productId));
        exit;
    }

    // ── 3. Add Variant ────────────────────────────────────────────────────────
    if ($subAction === 'add_variant' && $productId > 0) {
        $ageLabel = trim($_POST['age_label'] ?? '');
        $color    = trim($_POST['color'] ?? '');
        $sku      = trim($_POST['sku'] ?? '');
        $stockQty = max(0, (int)($_POST['stock_qty'] ?? 0));

        if ($ageLabel === '') {
            Session::flash('error', 'Age/Size label is required for variant.');
        } else {
            $productModel->addVariant($productId, [
                'age_label' => $ageLabel,
                'color'     => $color ?: null,
                'sku'       => $sku ?: null,
                'stock_qty' => $stockQty,
            ]);
            Session::flash('success', 'Variant added successfully.');
        }
        header('Location: ' . url('/admin/product-form.php?id=' . $productId));
        exit;
    }

    // ── 4. Delete Variant ─────────────────────────────────────────────────────
    if ($subAction === 'delete_variant' && $productId > 0) {
        $variantId = (int)($_POST['variant_id'] ?? 0);
        if ($variantId > 0) {
            $productModel->deleteVariant($variantId, $adminId);
            Session::flash('success', 'Variant removed.');
        }
        header('Location: ' . url('/admin/product-form.php?id=' . $productId));
        exit;
    }

    // ── 5. Main Save Product (Create or Update) ───────────────────────────────
    if ($subAction === 'save_product') {
        $name        = trim($_POST['name'] ?? '');
        $categoryId  = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $basePrice   = (float)($_POST['base_price'] ?? 0);
        $comparePrice= !empty($_POST['compare_price']) ? (float)$_POST['compare_price'] : null;
        $shortDesc   = trim($_POST['short_desc'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $material    = trim($_POST['material'] ?? '');
        $design      = trim($_POST['design'] ?? '');
        $neck        = trim($_POST['neck'] ?? '');
        $sleeves     = trim($_POST['sleeves'] ?? '');
        $closure     = trim($_POST['closure'] ?? '');
        $isNew       = !empty($_POST['is_new']) ? 1 : 0;
        $isFeatured  = !empty($_POST['is_featured']) ? 1 : 0;
        $isActive    = !empty($_POST['is_active']) ? 1 : 0;
        $metaTitle   = trim($_POST['meta_title'] ?? '');
        $metaDesc    = trim($_POST['meta_desc'] ?? '');

        // Calculate discount percentage if compare price provided
        $discountPct = null;
        if ($comparePrice !== null && $comparePrice > $basePrice && $comparePrice > 0) {
            $discountPct = (int) round((($comparePrice - $basePrice) / $comparePrice) * 100);
        }

        // Generate slug
        $slug = trim($_POST['slug'] ?? '');
        if ($slug === '') {
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
            $slug = trim($slug, '-');
        }

        $data = [
            'category_id'   => $categoryId,
            'name'          => $name,
            'slug'          => $slug,
            'description'   => $description,
            'short_desc'    => $shortDesc,
            'base_price'    => $basePrice,
            'compare_price' => $comparePrice,
            'discount_pct'  => $discountPct,
            'material'      => $material,
            'design'        => $design,
            'neck'          => $neck,
            'sleeves'       => $sleeves,
            'closure'       => $closure,
            'is_new'        => $isNew,
            'is_featured'   => $isFeatured,
            'is_active'     => $isActive,
            'meta_title'    => $metaTitle,
            'meta_desc'     => $metaDesc,
            'sort_order'    => (int)($_POST['sort_order'] ?? 0),
        ];

        $targetId = $productId;
        if ($productId > 0) {
            $productModel->adminUpdate($productId, $data);
            Session::flash('success', 'Product details updated successfully.');
        } else {
            $targetId = $productModel->adminCreate($data);
            Session::flash('success', 'Product created successfully! You can now add variants and gallery images.');
        }

        // ── Handle Multiple Image Uploads ──
        if (!empty($_FILES['images']['name'][0])) {
            $uploadErrors = [];
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
            $maxBytes     = 5 * 1024 * 1024; // 5 MB

            if (!is_dir(UPLOAD_DIR)) {
                @mkdir(UPLOAD_DIR, 0755, true);
            }

            $count = count($_FILES['images']['name']);
            for ($i = 0; $i < $count; $i++) {
                if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                    $tmpPath  = $_FILES['images']['tmp_name'][$i];
                    $fileSize = $_FILES['images']['size'][$i];

                    if ($fileSize > $maxBytes) {
                        $uploadErrors[] = "File {$_FILES['images']['name'][$i]} exceeds 5MB limit.";
                        continue;
                    }

                    // Check real MIME type
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime  = finfo_file($finfo, $tmpPath);
                    finfo_close($finfo);

                    if (!in_array($mime, $allowedMimes, true)) {
                        $uploadErrors[] = "File {$_FILES['images']['name'][$i]} is not a valid JPG, PNG, or WebP image.";
                        continue;
                    }

                    // Map extension safely
                    $ext = match ($mime) {
                        'image/jpeg' => 'jpg',
                        'image/png'  => 'png',
                        'image/webp' => 'webp',
                    };

                    $randomName = 'prod_' . bin2hex(random_bytes(10)) . '.' . $ext;
                    $destPath   = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $randomName;

                    if (move_uploaded_file($tmpPath, $destPath)) {
                        // If it's the very first image for the product, make it primary
                        $existingCount = count($productModel->getProductImages($targetId));
                        $isPrimary     = ($existingCount === 0);
                        $productModel->addImage($targetId, $randomName, $name, $isPrimary);
                    } else {
                        $uploadErrors[] = "Failed to save {$_FILES['images']['name'][$i]} to disk.";
                    }
                }
            }

            if (!empty($uploadErrors)) {
                Session::flash('error', implode(' ', $uploadErrors));
            }
        }

        header('Location: ' . url('/admin/product-form.php?id=' . $targetId));
        exit;
    }
}

$categories = $productModel->getCategories(true);

require_once APP_ROOT . '/includes/admin-header.php';
?>

<div style="margin-bottom: 1.5rem;">
    <a href="<?= e(url('/admin/products.php')) ?>" style="color:var(--admin-maroon);text-decoration:none;font-weight:600;font-size:0.9rem;">
        ← Back to Products List
    </a>
</div>

<form method="POST" action="<?= e(url('/admin/product-form.php' . ($productId > 0 ? '?id=' . $productId : ''))) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="sub_action" value="save_product">

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:1.5rem;">

        <!-- Left Column: Primary Details -->
        <div>
            <div class="card">
                <h2 class="card-title" style="margin-bottom:1.25rem;">General Information</h2>

                <div class="form-group">
                    <label class="form-label" for="prod_name">Product Name *</label>
                    <input type="text" id="prod_name" name="name" class="form-control" required
                           value="<?= e($product['name'] ?? '') ?>" placeholder="e.g. Royal Maroon Silk Anarkali Frock">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="prod_cat">Category *</label>
                        <select id="prod_cat" name="category_id" class="form-control" required>
                            <option value="">Select a category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int)$cat['id'] ?>" <?= (isset($product['category_id']) && (int)$product['category_id'] === (int)$cat['id']) ? 'selected' : '' ?>>
                                    <?= e($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="prod_slug">Slug (URL identifier)</label>
                        <input type="text" id="prod_slug" name="slug" class="form-control"
                               value="<?= e($product['slug'] ?? '') ?>" placeholder="leave empty to auto-generate">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="prod_short">Short Summary / Tagline</label>
                    <input type="text" id="prod_short" name="short_desc" class="form-control"
                           value="<?= e($product['short_desc'] ?? '') ?>" placeholder="Brief 1-line highlight">
                </div>

                <div class="form-group">
                    <label class="form-label" for="prod_desc">Full Description</label>
                    <textarea id="prod_desc" name="description" class="form-control" style="min-height:120px;"><?= e($product['description'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Pricing & Fabric Specifications -->
            <div class="card">
                <h2 class="card-title" style="margin-bottom:1.25rem;">Pricing & Fabric Specifications</h2>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="base_price">Selling Price (₹) *</label>
                        <input type="number" step="0.01" id="base_price" name="base_price" class="form-control" required
                               value="<?= e($product['base_price'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="compare_price">Original / Compare Price (₹)</label>
                        <input type="number" step="0.01" id="compare_price" name="compare_price" class="form-control"
                               value="<?= e($product['compare_price'] ?? '') ?>" placeholder="Crossed-out price">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Material / Fabric</label>
                        <input type="text" name="material" class="form-control" value="<?= e($product['material'] ?? '') ?>" placeholder="e.g. Pure Chanderi Silk">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Design / Pattern</label>
                        <input type="text" name="design" class="form-control" value="<?= e($product['design'] ?? '') ?>" placeholder="e.g. Floral Embroidery">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Neck Style</label>
                        <input type="text" name="neck" class="form-control" value="<?= e($product['neck'] ?? '') ?>" placeholder="e.g. Round Neck with Zari">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Sleeves</label>
                        <input type="text" name="sleeves" class="form-control" value="<?= e($product['sleeves'] ?? '') ?>" placeholder="e.g. Flutter Sleeves">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Closure</label>
                        <input type="text" name="closure" class="form-control" value="<?= e($product['closure'] ?? '') ?>" placeholder="e.g. Back Zipper & Tie-up">
                    </div>
                </div>
            </div>

            <!-- Image Upload Section -->
            <div class="card">
                <h2 class="card-title" style="margin-bottom:0.5rem;">Product Gallery Images</h2>
                <p style="font-size:0.85rem;color:#777;margin:0 0 1.25rem;">
                    Upload high quality photos. Supported formats: JPG, PNG, WebP (max 5MB each).
                </p>

                <div class="form-group">
                    <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp" class="form-control">
                </div>

                <?php if (!empty($images)): ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:1rem;margin-top:1.5rem;">
                    <?php foreach ($images as $img): ?>
                    <div style="border:1.5px solid <?= $img['is_primary'] ? 'var(--admin-maroon)' : '#e0d4dd' ?>;border-radius:8px;padding:0.5rem;text-align:center;background:#fff;position:relative;">
                        <?php if ($img['is_primary']): ?>
                            <span style="position:absolute;top:4px;left:4px;background:var(--admin-maroon);color:#fff;font-size:0.65rem;font-weight:700;padding:2px 6px;border-radius:4px;">PRIMARY</span>
                        <?php endif; ?>
                        <img src="<?= e(asset('/uploads/' . $img['image_path'])) ?>" alt="" 
                             style="width:100%;aspect-ratio:1/1;object-fit:cover;border-radius:4px;"
                             onerror="this.src='<?= e(asset('/assets/images/placeholder.svg')) ?>'">
                        
                        <div style="margin-top:0.5rem;display:flex;justify-content:center;gap:0.35rem;">
                            <?php if (!$img['is_primary']): ?>
                            <button type="submit" form="form-primary-<?= (int)$img['id'] ?>" class="btn btn-outline btn-sm" style="font-size:0.7rem;padding:0.2rem 0.5rem;" title="Set as primary image">
                                ⭐ Primary
                            </button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-danger btn-sm" style="font-size:0.7rem;padding:0.2rem 0.5rem;" title="Delete image"
                                    onclick="openConfirmModal({
                                        title: 'Delete Image?',
                                        message: 'Are you sure you want to remove this photo from the gallery?',
                                        action: '<?= e(url('/admin/product-form.php?id=' . $productId)) ?>',
                                        confirmText: 'Delete',
                                        btnClass: 'btn-danger',
                                        params: { sub_action: 'delete_image', image_id: '<?= (int)$img['id'] ?>' }
                                    })">
                                🗑️
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Variants & Stock Management (if product created) -->
            <?php if ($productId > 0): ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Size & Color Variants</h2>
                </div>

                <div class="table-responsive" style="margin-bottom:1.5rem;">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Age / Size</th>
                                <th>Color</th>
                                <th>SKU</th>
                                <th>Stock Qty</th>
                                <th>Status</th>
                                <th style="text-align:right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($variants)): ?>
                            <tr>
                                <td colspan="6" style="text-align:center;color:#888;padding:1rem;">
                                    No variants defined yet. Add sizes below (e.g. 0-6M, 1-2Y, etc.).
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($variants as $v): ?>
                            <tr>
                                <td><strong><?= e($v['age_label']) ?></strong></td>
                                <td><?= e($v['color'] ?? '—') ?></td>
                                <td><code><?= e($v['sku'] ?? '—') ?></code></td>
                                <td>
                                    <strong style="color:<?= (int)$v['stock_qty'] <= 5 ? '#c0392b' : '#27ae60' ?>;">
                                        <?= (int)$v['stock_qty'] ?> units
                                    </strong>
                                </td>
                                <td>
                                    <span class="badge <?= (int)$v['is_active'] === 1 ? 'badge-active' : 'badge-inactive' ?>">
                                        <?= (int)$v['is_active'] === 1 ? 'Active' : 'Disabled' ?>
                                    </span>
                                </td>
                                <td style="text-align:right;">
                                    <button type="button" class="btn btn-danger btn-sm"
                                            onclick="openConfirmModal({
                                                title: 'Delete Variant?',
                                                message: 'Remove <?= e($v['age_label']) ?> variant permanently?',
                                                action: '<?= e(url('/admin/product-form.php?id=' . $productId)) ?>',
                                                confirmText: 'Delete Variant',
                                                btnClass: 'btn-danger',
                                                params: { sub_action: 'delete_variant', variant_id: '<?= (int)$v['id'] ?>' }
                                            })">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Add Variant Sub-form -->
                <div style="background:#fdf9fa;border:1.5px dashed var(--admin-border);padding:1rem;border-radius:8px;">
                    <h3 style="font-size:0.95rem;margin:0 0 0.75rem;color:var(--admin-maroon);font-family:var(--font-heading);">+ Add New Variant</h3>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr)) auto;gap:0.75rem;align-items:flex-end;">
                        <div>
                            <label class="form-label" style="font-size:0.75rem;">Age / Size *</label>
                            <input type="text" form="add-variant-form" name="age_label" class="form-control" placeholder="e.g. 1-2Y" required>
                        </div>
                        <div>
                            <label class="form-label" style="font-size:0.75rem;">Color</label>
                            <input type="text" form="add-variant-form" name="color" class="form-control" placeholder="e.g. Maroon">
                        </div>
                        <div>
                            <label class="form-label" style="font-size:0.75rem;">SKU</label>
                            <input type="text" form="add-variant-form" name="sku" class="form-control" placeholder="HF-M-01">
                        </div>
                        <div>
                            <label class="form-label" style="font-size:0.75rem;">Stock Qty *</label>
                            <input type="number" form="add-variant-form" name="stock_qty" class="form-control" value="10" min="0" required>
                        </div>
                        <div>
                            <button type="submit" form="add-variant-form" class="btn btn-maroon btn-sm" style="height:38px;">
                                Add Variant
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <!-- Right Column: Settings & Visibility -->
        <div>
            <div class="card" style="position:sticky;top:80px;">
                <h2 class="card-title" style="margin-bottom:1rem;">Publishing & Flags</h2>

                <div class="form-group" style="display:flex;align-items:center;gap:0.6rem;">
                    <input type="checkbox" id="is_active" name="is_active" value="1" <?= (!isset($product) || !empty($product['is_active'])) ? 'checked' : '' ?> style="width:auto;">
                    <label for="is_active" style="margin:0;font-weight:600;font-size:0.9rem;">Visible in Store</label>
                </div>

                <div class="form-group" style="display:flex;align-items:center;gap:0.6rem;">
                    <input type="checkbox" id="is_new" name="is_new" value="1" <?= (!isset($product) || !empty($product['is_new'])) ? 'checked' : '' ?> style="width:auto;">
                    <label for="is_new" style="margin:0;font-weight:500;font-size:0.85rem;">Mark as "New Arrival"</label>
                </div>

                <div class="form-group" style="display:flex;align-items:center;gap:0.6rem;">
                    <input type="checkbox" id="is_featured" name="is_featured" value="1" <?= (!empty($product['is_featured'])) ? 'checked' : '' ?> style="width:auto;">
                    <label for="is_featured" style="margin:0;font-weight:500;font-size:0.85rem;">Featured Collection</label>
                </div>

                <div class="form-group">
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="sort_order" class="form-control" value="<?= e($product['sort_order'] ?? '0') ?>">
                </div>

                <hr style="border:none;border-top:1px solid #f0eaee;margin:1.5rem 0;">

                <button type="submit" class="btn btn-maroon" style="width:100%;justify-content:center;padding:0.75rem;">
                    <?= $productId > 0 ? 'Save Product Changes' : 'Create Product' ?>
                </button>
            </div>
        </div>

    </div>
</form>

<!-- Separate forms for primary image toggle & variant additions -->
<?php if (!empty($images)): ?>
    <?php foreach ($images as $img): ?>
    <form id="form-primary-<?= (int)$img['id'] ?>" method="POST" action="<?= e(url('/admin/product-form.php?id=' . $productId)) ?>" style="display:none;">
        <?= csrf_field() ?>
        <input type="hidden" name="sub_action" value="set_primary_image">
        <input type="hidden" name="image_id" value="<?= (int)$img['id'] ?>">
    </form>
    <?php endforeach; ?>
<?php endif; ?>

<?php if ($productId > 0): ?>
<form id="add-variant-form" method="POST" action="<?= e(url('/admin/product-form.php?id=' . $productId)) ?>" style="display:none;">
    <?= csrf_field() ?>
    <input type="hidden" name="sub_action" value="add_variant">
</form>
<?php endif; ?>

<?php require_once APP_ROOT . '/includes/admin-footer.php'; ?>
