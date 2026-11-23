<?php
/**
 * Admin Store Settings — Heramba Fashion
 * ========================================
 * Modules:
 *   - WhatsApp Number and UPI ID
 *   - Announcement bar texts (editable in settings table)
 *   - Shipping rates and free shipping threshold
 *   - Low-stock threshold
 *   - Contact information
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$pageTitle = 'Settings';
$topbarTitle = 'Store Configuration';

$settingModel = new SettingModel();
$adminId      = (int) Session::userId();

// ── POST Handling ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    // Mapping of editable settings keys
    $keys = [
        'whatsapp_number',
        'upi_id',
        'announcement_bar_left',
        'announcement_bar_right',
        'free_shipping_threshold_india',
        'shipping_india',
        'shipping_uae',
        'low_stock_threshold',
        'store_email',
        'store_phone',
        'store_address',
    ];

    foreach ($keys as $k) {
        if (isset($_POST[$k])) {
            $settingModel->set($k, trim($_POST[$k]), $adminId);
        }
    }

    Session::flash('success', 'Store settings updated successfully.');
    header('Location: ' . url('/admin/settings.php'));
    exit;
}

$all = $settingModel->all();

require_once APP_ROOT . '/includes/admin-header.php';
?>

<form method="POST" action="<?= e(url('/admin/settings.php')) ?>">
    <?= csrf_field() ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">

        <!-- Left: Payment & Communication -->
        <div>
            <!-- Payment & WhatsApp Details -->
            <div class="card">
                <h2 class="card-title" style="margin-bottom:1.25rem;">Payment & WhatsApp Configuration</h2>

                <div class="form-group">
                    <label class="form-label">WhatsApp Contact Number *</label>
                    <input type="text" name="whatsapp_number" class="form-control" required
                           value="<?= e($settingModel->get('whatsapp_number', '919876543210')) ?>"
                           placeholder="Country code + mobile without spaces (e.g. 919876543210)">
                    <div style="font-size:0.75rem;color:#777;margin-top:0.25rem;">
                        This number receives all customer order messages and floating WhatsApp chat inquiries.
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Store UPI ID (For Payment Verification) *</label>
                    <input type="text" name="upi_id" class="form-control" required
                           value="<?= e($settingModel->get('upi_id', 'yourname@upi')) ?>"
                           placeholder="e.g. yourname@upi">
                    <div style="font-size:0.75rem;color:#777;margin-top:0.25rem;">
                        Displayed on checkout and confirmation pages ("Online Payment Only, No COD").
                    </div>
                </div>
            </div>

            <!-- Announcement Bar Customization -->
            <div class="card">
                <h2 class="card-title" style="margin-bottom:1.25rem;">Top Maroon Announcement Bar</h2>

                <div class="form-group">
                    <label class="form-label">Left Announcement Text</label>
                    <input type="text" name="announcement_bar_left" class="form-control"
                           value="<?= e($settingModel->get('announcement_bar_left', 'Pan-India Shipping | International Dispatch to UAE / Dubai')) ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Right Announcement Text</label>
                    <input type="text" name="announcement_bar_right" class="form-control"
                           value="<?= e($settingModel->get('announcement_bar_right', 'No COD | 0-3 Years: Ready to Dispatch | Customization: 5 Working Days')) ?>">
                </div>
            </div>
        </div>

        <!-- Right: Shipping Rates & Inventory -->
        <div>
            <!-- Shipping Rates -->
            <div class="card">
                <h2 class="card-title" style="margin-bottom:1.25rem;">Shipping & Freight Rates</h2>

                <div class="form-group">
                    <label class="form-label">Free Shipping Threshold (India, ₹)</label>
                    <input type="number" step="0.01" name="free_shipping_threshold_india" class="form-control"
                           value="<?= e($settingModel->get('free_shipping_threshold_india', '999.00')) ?>">
                    <div style="font-size:0.75rem;color:#777;margin-top:0.25rem;">
                        Orders equal to or above this amount receive free Pan-India delivery.
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Standard India Delivery (₹)</label>
                        <input type="number" step="0.01" name="shipping_india" class="form-control"
                               value="<?= e($settingModel->get('shipping_india', '99.00')) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">UAE / International (₹)</label>
                        <input type="number" step="0.01" name="shipping_uae" class="form-control"
                               value="<?= e($settingModel->get('shipping_uae', '1499.00')) ?>">
                    </div>
                </div>
            </div>

            <!-- Inventory Alerts & Store Info -->
            <div class="card">
                <h2 class="card-title" style="margin-bottom:1.25rem;">Stock Alerts & Boutique Details</h2>

                <div class="form-group">
                    <label class="form-label">Low-Stock Alert Threshold (Units)</label>
                    <input type="number" name="low_stock_threshold" class="form-control" min="1" max="100"
                           value="<?= e($settingModel->get('low_stock_threshold', '5')) ?>">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Contact Email</label>
                        <input type="email" name="store_email" class="form-control"
                               value="<?= e($settingModel->get('store_email', 'contact@yourdomain.com')) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact Phone</label>
                        <input type="text" name="store_phone" class="form-control"
                               value="<?= e($settingModel->get('store_phone', '+91 98765 43210')) ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Boutique Physical Address</label>
                    <textarea name="store_address" class="form-control" style="min-height:60px;"><?= e($settingModel->get('store_address', "Heramba Fashion Boutique\n124 Fashion Heritage Avenue\nBangalore, Karnataka 560001\nIndia")) ?></textarea>
                </div>

                <button type="submit" class="btn btn-maroon" style="width:100%;justify-content:center;padding:0.75rem;margin-top:0.5rem;">
                    Save Store Settings
                </button>
            </div>
        </div>

    </div>
</form>

<?php require_once APP_ROOT . '/includes/admin-footer.php'; ?>
