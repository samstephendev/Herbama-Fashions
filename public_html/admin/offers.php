<?php
/**
 * Admin Offers & Coupons Management — Heramba Fashion
 * ====================================================
 * Modules:
 *   - Create and manage discount coupons and promotional offers
 *   - Percentage, Fixed Amount, and Free Shipping types
 *   - Start/End validity dates and minimum order values
 *   - Confirmation dialogs for deletion
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$pageTitle = 'Offers & Coupons';
$topbarTitle = 'Discounts & Promotions';

$offerModel = new OfferModel();
$adminId    = (int) Session::userId();

// ── POST Handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = trim($_POST['action'] ?? '');

    if ($action === 'create_offer') {
        $title     = trim($_POST['title'] ?? '');
        $code      = trim($_POST['code'] ?? '');
        $type      = trim($_POST['type'] ?? 'percent');
        $value     = (float)($_POST['value'] ?? 0);
        $minVal    = (float)($_POST['min_order_value'] ?? 0);
        $maxDisc   = !empty($_POST['max_discount']) ? (float)$_POST['max_discount'] : null;
        $startsAt  = !empty($_POST['starts_at']) ? $_POST['starts_at'] : null;
        $endsAt    = !empty($_POST['ends_at']) ? $_POST['ends_at'] : null;
        $isActive  = !empty($_POST['is_active']) ? 1 : 0;

        if ($title === '' || $value <= 0) {
            Session::flash('error', 'Offer title and a valid positive value are required.');
        } else {
            $offerModel->createOffer([
                'title'           => $title,
                'code'            => $code ?: null,
                'type'            => $type,
                'value'           => $value,
                'min_order_value' => $minVal,
                'max_discount'    => $maxDisc,
                'applies_to'      => 'all',
                'starts_at'       => $startsAt,
                'ends_at'         => $endsAt,
                'is_active'       => $isActive,
            ], $adminId);
            Session::flash('success', 'Offer created successfully.');
        }
        header('Location: ' . url('/admin/offers.php'));
        exit;
    }

    if ($action === 'delete_offer') {
        $offerId = (int)($_POST['offer_id'] ?? 0);
        if ($offerId > 0) {
            $offerModel->deleteOffer($offerId, $adminId);
            Session::flash('success', 'Offer removed.');
        }
        header('Location: ' . url('/admin/offers.php'));
        exit;
    }
}

$offers = $offerModel->adminList();

require_once APP_ROOT . '/includes/admin-header.php';
?>

<div style="display:grid;grid-template-columns:1fr 2fr;gap:1.5rem;">

    <!-- Left: Create Offer Form -->
    <div>
        <div class="card">
            <h2 class="card-title" style="margin-bottom:1rem;">Create New Offer</h2>
            <form method="POST" action="<?= e(url('/admin/offers.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_offer">

                <div class="form-group">
                    <label class="form-label">Offer Title *</label>
                    <input type="text" name="title" class="form-control" required placeholder="e.g. Festive Launch 15% Off">
                </div>

                <div class="form-group">
                    <label class="form-label">Coupon Code (optional)</label>
                    <input type="text" name="code" class="form-control" placeholder="e.g. HERAMBA15" style="text-transform:uppercase;">
                    <div style="font-size:0.75rem;color:#777;margin-top:0.25rem;">Leave empty for auto-applied storewide offer.</div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Discount Type *</label>
                        <select name="type" class="form-control">
                            <option value="percent">Percentage (%)</option>
                            <option value="fixed_amount">Fixed Amount (₹)</option>
                            <option value="free_shipping">Free Shipping</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Discount Value *</label>
                        <input type="number" step="0.01" name="value" class="form-control" required placeholder="e.g. 15 or 500">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Min. Order Value (₹)</label>
                        <input type="number" step="0.01" name="min_order_value" class="form-control" value="0">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Max. Cap (₹ optional)</label>
                        <input type="number" step="0.01" name="max_discount" class="form-control" placeholder="No limit">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Starts On</label>
                        <input type="date" name="starts_at" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Expires On</label>
                        <input type="date" name="ends_at" class="form-control">
                    </div>
                </div>

                <div class="form-group" style="display:flex;align-items:center;gap:0.6rem;margin-top:0.5rem;">
                    <input type="checkbox" id="is_active_offer" name="is_active" value="1" checked style="width:auto;">
                    <label for="is_active_offer" style="margin:0;font-weight:600;font-size:0.88rem;">Active immediately</label>
                </div>

                <button type="submit" class="btn btn-maroon" style="width:100%;justify-content:center;margin-top:0.5rem;">
                    Save Discount Offer
                </button>
            </form>
        </div>
    </div>

    <!-- Right: Offers List -->
    <div>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Active & Scheduled Offers (<?= count($offers) ?>)</h2>
            </div>

            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Title & Code</th>
                            <th>Benefit</th>
                            <th>Min Order</th>
                            <th>Validity</th>
                            <th>Status</th>
                            <th style="text-align:right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($offers)): ?>
                        <tr>
                            <td colspan="6" style="text-align:center;color:#888;padding:2rem;">
                                No discount offers configured.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($offers as $off): ?>
                        <tr>
                            <td>
                                <strong><?= e($off['title']) ?></strong>
                                <?php if (!empty($off['code'])): ?>
                                    <div><code style="background:#fdf2f5;color:var(--admin-maroon);padding:2px 6px;border-radius:4px;"><?= e($off['code']) ?></code></div>
                                <?php else: ?>
                                    <div style="font-size:0.75rem;color:#888;">Auto-Applied</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong>
                                    <?php if ($off['type'] === 'percent'): ?>
                                        <?= (float)$off['value'] ?>% Off
                                    <?php elseif ($off['type'] === 'fixed_amount'): ?>
                                        ₹<?= number_format((float)$off['value'], 2) ?> Off
                                    <?php else: ?>
                                        Free Shipping
                                    <?php endif; ?>
                                </strong>
                            </td>
                            <td>₹<?= number_format((float)$off['min_order_value'], 2) ?></td>
                            <td style="font-size:0.78rem;color:#666;">
                                <?= !empty($off['starts_at']) ? date('d M', strtotime($off['starts_at'])) : 'Anytime' ?> — 
                                <?= !empty($off['ends_at']) ? date('d M Y', strtotime($off['ends_at'])) : 'No expiry' ?>
                            </td>
                            <td>
                                <span class="badge <?= (int)$off['is_active'] === 1 ? 'badge-active' : 'badge-inactive' ?>">
                                    <?= (int)$off['is_active'] === 1 ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td style="text-align:right;">
                                <button type="button" class="btn btn-danger btn-sm"
                                        onclick="openConfirmModal({
                                            title: 'Delete Offer?',
                                            message: 'Are you sure you want to remove &quot;<?= e(addslashes($off['title'])) ?>&quot;?',
                                            action: '<?= e(url('/admin/offers.php')) ?>',
                                            confirmText: 'Delete Offer',
                                            btnClass: 'btn-danger',
                                            params: { action: 'delete_offer', offer_id: '<?= (int)$off['id'] ?>' }
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
