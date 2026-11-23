<?php
/**
 * Admin Banners & Hero Slider Management — Heramba Fashion
 * ==========================================================
 * Modules:
 *   - Manage homepage hero slider banners & promo cards
 *   - Upload banner images or select vector graphics
 *   - Sort ordering and active toggling
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$pageTitle = 'Banners & Slides';
$topbarTitle = 'Promotional Banners';

$bannerModel = new BannerModel();
$adminId     = (int) Session::userId();

// ── POST Handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = trim($_POST['action'] ?? '');

    if ($action === 'create_banner') {
        $title      = trim($_POST['title'] ?? '');
        $subtitle   = trim($_POST['subtitle'] ?? '');
        $badgeText  = trim($_POST['badge_text'] ?? '');
        $buttonText = trim($_POST['button_text'] ?? 'Shop Now');
        $buttonUrl  = trim($_POST['button_url'] ?? '/collection.php');
        $bgColor    = trim($_POST['bg_color'] ?? '#FAF4EB');
        $sortOrder  = (int)($_POST['sort_order'] ?? 0);
        $isActive   = !empty($_POST['is_active']) ? 1 : 0;
        $imageUrl   = trim($_POST['image_url'] ?? '/assets/images/banner1.svg');

        // Handle uploaded file if present
        if (!empty($_FILES['banner_file']['name']) && $_FILES['banner_file']['error'] === UPLOAD_ERR_OK) {
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];
            $tmpPath = $_FILES['banner_file']['tmp_name'];
            $finfo   = finfo_open(FILEINFO_MIME_TYPE);
            $mime    = finfo_file($finfo, $tmpPath);
            finfo_close($finfo);

            if (in_array($mime, $allowedMimes, true)) {
                $ext = match ($mime) {
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/webp' => 'webp',
                    'image/svg+xml' => 'svg',
                };
                $newName = 'banner_' . bin2hex(random_bytes(8)) . '.' . $ext;
                $dest    = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $newName;
                if (move_uploaded_file($tmpPath, $dest)) {
                    $imageUrl = '/uploads/' . $newName;
                }
            }
        }

        if ($title === '') {
            Session::flash('error', 'Banner title is required.');
        } else {
            $bannerModel->createBanner([
                'title'       => $title,
                'subtitle'    => $subtitle ?: null,
                'badge_text'  => $badgeText ?: null,
                'button_text' => $buttonText,
                'button_url'  => $buttonUrl,
                'image_url'   => $imageUrl,
                'bg_color'    => $bgColor,
                'sort_order'  => $sortOrder,
                'is_active'   => $isActive,
            ], $adminId);
            Session::flash('success', 'Banner created successfully.');
        }
        header('Location: ' . url('/admin/banners.php'));
        exit;
    }

    if ($action === 'delete_banner') {
        $bannerId = (int)($_POST['banner_id'] ?? 0);
        if ($bannerId > 0) {
            $bannerModel->deleteBanner($bannerId, $adminId);
            Session::flash('success', 'Banner deleted.');
        }
        header('Location: ' . url('/admin/banners.php'));
        exit;
    }
}

$banners = $bannerModel->adminList();

require_once APP_ROOT . '/includes/admin-header.php';
?>

<div style="display:grid;grid-template-columns:1fr 2fr;gap:1.5rem;">

    <!-- Left: Add Banner Form -->
    <div>
        <div class="card">
            <h2 class="card-title" style="margin-bottom:1rem;">Add New Banner / Slide</h2>
            <form method="POST" action="<?= e(url('/admin/banners.php')) ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_banner">

                <div class="form-group">
                    <label class="form-label">Main Heading / Title *</label>
                    <input type="text" name="title" class="form-control" required placeholder="e.g. Adorable Styles for Little Princesses">
                </div>

                <div class="form-group">
                    <label class="form-label">Subtitle / Description</label>
                    <textarea name="subtitle" class="form-control" style="min-height:70px;" placeholder="Brief promotional narrative..."></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Badge Tag</label>
                        <input type="text" name="badge_text" class="form-control" placeholder="e.g. NEW ARRIVALS">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Button Text</label>
                        <input type="text" name="button_text" class="form-control" value="Shop Now">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Button Target URL</label>
                    <input type="text" name="button_url" class="form-control" value="/collection.php">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Card Background Tint</label>
                        <input type="text" name="bg_color" class="form-control" value="#FAF4EB" placeholder="#FAF4EB">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Sort Order</label>
                        <input type="number" name="sort_order" class="form-control" value="0">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Upload Banner Image (JPG/PNG/SVG/WebP)</label>
                    <input type="file" name="banner_file" accept="image/*" class="form-control">
                </div>

                <div class="form-group">
                    <label class="form-label">Or Preset Graphic Path</label>
                    <input type="text" name="image_url" class="form-control" value="/assets/images/banner1.svg">
                </div>

                <div class="form-group" style="display:flex;align-items:center;gap:0.6rem;">
                    <input type="checkbox" id="is_active_b" name="is_active" value="1" checked style="width:auto;">
                    <label for="is_active_b" style="margin:0;font-weight:600;font-size:0.88rem;">Active in hero slider</label>
                </div>

                <button type="submit" class="btn btn-maroon" style="width:100%;justify-content:center;margin-top:0.5rem;">
                    Create Banner
                </button>
            </form>
        </div>
    </div>

    <!-- Right: Banners Listing -->
    <div>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Configured Banners (<?= count($banners) ?>)</h2>
            </div>

            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="width:70px;">Preview</th>
                            <th>Title & Details</th>
                            <th>Button CTA</th>
                            <th>Order</th>
                            <th>Status</th>
                            <th style="text-align:right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($banners)): ?>
                        <tr>
                            <td colspan="6" style="text-align:center;color:#888;padding:2rem;">
                                No custom banners created. The homepage uses default promotional slides.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($banners as $b): ?>
                        <tr>
                            <td>
                                <img src="<?= e(asset($b['image_url'])) ?>" alt="" 
                                     style="width:60px;height:45px;object-fit:cover;border-radius:6px;border:1px solid #ddd;"
                                     onerror="this.src='<?= e(asset('/assets/images/placeholder.svg')) ?>'">
                            </td>
                            <td>
                                <strong><?= e($b['title']) ?></strong>
                                <?php if (!empty($b['badge_text'])): ?>
                                    <div style="font-size:0.75rem;color:var(--admin-gold);font-weight:700;"><?= e($b['badge_text']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="font-size:0.8rem;background:#f5edf2;padding:2px 8px;border-radius:4px;color:var(--admin-maroon);">
                                    <?= e($b['button_text']) ?> → <?= e($b['button_url']) ?>
                                </span>
                            </td>
                            <td><?= (int)$b['sort_order'] ?></td>
                            <td>
                                <span class="badge <?= (int)$b['is_active'] === 1 ? 'badge-active' : 'badge-inactive' ?>">
                                    <?= (int)$b['is_active'] === 1 ? 'Active' : 'Hidden' ?>
                                </span>
                            </td>
                            <td style="text-align:right;">
                                <button type="button" class="btn btn-danger btn-sm"
                                        onclick="openConfirmModal({
                                            title: 'Delete Banner?',
                                            message: 'Are you sure you want to remove &quot;<?= e(addslashes($b['title'])) ?>&quot;?',
                                            action: '<?= e(url('/admin/banners.php')) ?>',
                                            confirmText: 'Delete Banner',
                                            btnClass: 'btn-danger',
                                            params: { action: 'delete_banner', banner_id: '<?= (int)$b['id'] ?>' }
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
        </div>
    </div>

</div>

<?php require_once APP_ROOT . '/includes/admin-footer.php'; ?>
