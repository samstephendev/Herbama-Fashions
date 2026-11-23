<?php
/**
 * order-tracking.php — Heramba Fashion
 * =====================================
 * Live order status tracking page with status timeline and WhatsApp inquiry.
 *
 * Security:
 *   - Login required.
 *   - IDOR safe: order must belong to currently authenticated user.
 *   - Sanitized input and output.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config/bootstrap.php';

// ── Auth Guard ───────────────────────────────────────────────────────────────
if (!Session::isLoggedIn()) {
    header('Location: ' . url('/login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/')));
    exit;
}

$userId = (int) Session::userId();

// ── Fetch & Validate Order ───────────────────────────────────────────────────
$publicOrderId = trim((string)($_GET['id'] ?? ''));
$order         = null;
$history       = [];

if (preg_match('/^HF-[A-Z0-9]{4,16}$/i', $publicOrderId)) {
    $orderModel = new OrderModel();
    // IDOR protection: only loads if user_id matches
    $order = $orderModel->findByPublicId($publicOrderId, $userId);
    if ($order) {
        $history = $order['history'] ?? [];
    }
}

// ── Settings & WhatsApp Links ────────────────────────────────────────────────
$settingModel  = new SettingModel();
$rawWaNumber   = (string) $settingModel->get('whatsapp_number', '919876543210');
$cleanWaNumber = preg_replace('/\D/', '', $rawWaNumber);

require_once APP_ROOT . '/config/whatsapp-templates.php';

$waInquiryMsg = whatsapp_order_inquiry_message($publicOrderId ?: 'HF-ORDER');
$waInquiryUrl = 'https://wa.me/' . $cleanWaNumber . '?text=' . rawurlencode($waInquiryMsg);

$pageTitle = $order ? 'Track Order #' . $order['public_order_id'] : 'Track Order';
$bodyClass = 'page-tracking';

// Pipeline of standard chronological order milestones
$standardPipeline = [
    'placed'          => ['label' => 'Order Placed',      'desc' => 'Order submitted to boutique'],
    'confirmed'       => ['label' => 'Order Confirmed',   'desc' => 'Verified by boutique team'],
    'payment_pending' => ['label' => 'Payment Pending',   'desc' => 'Awaiting UPI verification'],
    'paid'            => ['label' => 'Payment Verified',  'desc' => 'Payment confirmed on WhatsApp'],
    'packed'          => ['label' => 'Packed with Care',  'desc' => 'Garment inspection & packaging'],
    'dispatched'      => ['label' => 'Dispatched',        'desc' => 'Handed over for courier delivery'],
    'delivered'       => ['label' => 'Delivered',         'desc' => 'Safely arrived at destination'],
];

$currentStatus = $order['status'] ?? 'placed';
$isCancelled   = ($currentStatus === 'cancelled');

// Determine timeline step progression index
$allStatuses = array_keys($standardPipeline);
$currentIndex = array_search($currentStatus, $allStatuses, true);
if ($currentIndex === false) {
    $currentIndex = 0;
}

$extraHead = '<style>
.tracking-container { max-width: 900px; margin: 2rem auto 4rem; padding: 0 1rem; }
.tracking-header { margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 1rem; }
.tracking-header h1 { font-family: var(--font-heading); color: var(--color-maroon); font-size: 1.85rem; margin: 0 0 0.25rem; }
.tracking-meta { color: #777; font-size: 0.9rem; }
.status-pill { display: inline-block; padding: 0.35rem 1rem; border-radius: 50px; font-weight: 700; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.04em; }

/* Status Colors */
.status-placed          { background: #e8f4fd; color: #2980b9; }
.status-confirmed       { background: #ede8f8; color: #6c5ce7; }
.status-payment_pending { background: #fef5e7; color: #d35400; }
.status-paid            { background: #e8f8f0; color: #27ae60; }
.status-packed          { background: #e0f2f1; color: #00897b; }
.status-dispatched      { background: #f3e5f5; color: #8e24aa; }
.status-delivered       { background: #e8f5e9; color: #2e7d32; }
.status-cancelled       { background: #fbebee; color: #c0392b; }

.track-grid { display: grid; grid-template-columns: 1fr 340px; gap: 1.75rem; }
@media (max-width: 768px) {
    .track-grid { grid-template-columns: 1fr; }
}

.track-card { background: #fff; border-radius: 14px; box-shadow: 0 2px 16px rgba(122,31,61,0.06); padding: 1.75rem; border: 1px solid #f0eaee; margin-bottom: 1.5rem; }
.track-card h2, .track-card h3 { font-family: var(--font-heading); color: var(--color-maroon); margin: 0 0 1.25rem; font-size: 1.2rem; }

/* Vertical Stepper Timeline */
.timeline { list-style: none; padding: 0; margin: 0; position: relative; }
.timeline-item { position: relative; padding-left: 2.25rem; margin-bottom: 1.75rem; }
.timeline-item:last-child { margin-bottom: 0; }
.timeline-item::before { content: ""; position: absolute; left: 11px; top: 24px; bottom: -20px; width: 2px; background: #e8dbe3; }
.timeline-item:last-child::before { display: none; }
.timeline-item.completed::before { background: var(--color-maroon); }

.timeline-dot { position: absolute; left: 0; top: 2px; width: 24px; height: 24px; border-radius: 50%; background: #fff; border: 2.5px solid #dcd0d8; display: flex; align-items: center; justify-content: center; }
.timeline-item.completed .timeline-dot { background: var(--color-maroon); border-color: var(--color-maroon); color: #fff; }
.timeline-item.current .timeline-dot { background: #fff; border-color: var(--color-gold); box-shadow: 0 0 0 4px rgba(201,162,75,0.25); }

.timeline-dot svg { width: 12px; height: 12px; stroke: #fff; stroke-width: 3; }
.timeline-title { font-weight: 700; font-size: 0.95rem; color: #2B2B2B; margin-bottom: 0.15rem; }
.timeline-desc { font-size: 0.82rem; color: #666; margin-bottom: 0.2rem; }
.timeline-time { font-size: 0.75rem; color: #999; }

/* Cancelled Alert Box */
.cancelled-box { background: #fdf2f2; border: 1.5px solid #f5c6cb; border-radius: 12px; padding: 1.25rem; margin-bottom: 1.5rem; color: #721c24; display: flex; gap: 0.75rem; align-items: flex-start; }
.cancelled-box svg { width: 24px; height: 24px; flex-shrink: 0; stroke: #c0392b; }

/* Sidebar Summary */
.side-items-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; margin-bottom: 1rem; }
.side-items-table td { padding: 0.5rem 0; border-bottom: 1px solid #f5edf2; }
.side-items-table tr:last-child td { border-bottom: none; }
.side-totals-row { display: flex; justify-content: space-between; font-size: 0.9rem; margin-bottom: 0.35rem; color: #555; }
.side-totals-row.final { font-weight: 700; color: var(--color-maroon); font-size: 1.05rem; margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px dashed #e0d4dd; }

.wa-inquiry-btn { display: flex; align-items: center; justify-content: center; gap: 0.5rem; background: #25D366; color: #fff; text-decoration: none; padding: 0.75rem 1.25rem; border-radius: 50px; font-weight: 600; font-size: 0.9rem; margin-top: 1.25rem; transition: background 0.2s; }
.wa-inquiry-btn:hover { background: #1eb956; }
.wa-inquiry-btn svg { width: 18px; height: 18px; fill: #fff; }

.customization-badge { background: #fff8e8; border: 1px solid #f3d99d; border-radius: 8px; padding: 0.8rem 1rem; font-size: 0.82rem; color: #7d591b; margin-top: 1rem; }
</style>';

require_once APP_ROOT . '/includes/header.php';
?>

<main id="main-content" class="tracking-container">

<?php if (!$order): ?>
    <div class="track-card" style="text-align: center; padding: 3rem 1.5rem;">
        <h2 style="color: #c0392b;">Order Not Found</h2>
        <p style="color: #666; margin-bottom: 1.5rem;">
            We could not find an order matching reference "<?= e($publicOrderId) ?>" under your account.
        </p>
        <a href="<?= e(url('/account.php?tab=orders')) ?>" class="btn-primary" style="display: inline-block; padding: 0.65rem 1.5rem; border-radius: 50px; text-decoration: none;">
            View All My Orders
        </a>
    </div>
<?php else: ?>

    <div class="tracking-header">
        <div>
            <h1>Order #<?= e($order['public_order_id']) ?></h1>
            <div class="tracking-meta">
                Placed on <?= e(date('d M Y, h:i A', strtotime($order['created_at']))) ?>
            </div>
        </div>
        <div>
            <span class="status-pill status-<?= e($order['status']) ?>">
                Status: <?= e(ucwords(str_replace('_', ' ', $order['status']))) ?>
            </span>
        </div>
    </div>

    <?php if ($isCancelled): ?>
        <div class="cancelled-box">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <line x1="15" y1="9" x2="9" y2="15"/>
                <line x1="9" y1="9" x2="15" y2="15"/>
            </svg>
            <div>
                <strong>This order has been cancelled.</strong><br>
                If you have already transferred payment or need to reactivate this order, please contact our team via WhatsApp.
            </div>
        </div>
    <?php endif; ?>

    <div class="track-grid">

        <!-- Left: Status Timeline -->
        <div>
            <div class="track-card">
                <h2>Progress Timeline</h2>

                <ul class="timeline">
                    <?php 
                    $historyMap = [];
                    foreach ($history as $h) {
                        $historyMap[$h['to_status']] = $h;
                    }

                    $idx = 0;
                    foreach ($standardPipeline as $stKey => $stData):
                        $isPast    = (!$isCancelled && $idx < $currentIndex);
                        $isCurrent = (!$isCancelled && $idx === $currentIndex);
                        $isCompleted = ($isPast || $isCurrent);

                        $class = $isPast ? 'completed' : ($isCurrent ? 'current' : '');
                        $histRecord = $historyMap[$stKey] ?? null;
                    ?>
                    <li class="timeline-item <?= $class ?>">
                        <div class="timeline-dot">
                            <?php if ($isPast): ?>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                            <?php endif; ?>
                        </div>
                        <div class="timeline-title"><?= e($stData['label']) ?></div>
                        <div class="timeline-desc"><?= e($stData['desc']) ?></div>
                        <?php if ($histRecord): ?>
                            <div class="timeline-time">
                                <?= e(date('d M Y, h:i A', strtotime($histRecord['created_at']))) ?>
                                <?php if (!empty($histRecord['note'])): ?>
                                    — <em><?= e($histRecord['note']) ?></em>
                                <?php endif; ?>
                            </div>
                        <?php elseif ($isCurrent): ?>
                            <div class="timeline-time" style="color: var(--color-maroon); font-weight: 600;">Currently in progress</div>
                        <?php endif; ?>
                    </li>
                    <?php 
                    $idx++;
                    endforeach; 
                    ?>
                </ul>
            </div>

            <!-- Customization Reminder -->
            <?php if (!empty($order['customization_notes'])): ?>
            <div class="customization-badge">
                <strong>✂️ Customization Request:</strong><br>
                <?= e($order['customization_notes']) ?><br><br>
                <em>Note: Our artisans handcraft customized outfits with extra care; dispatch is scheduled within 5 working days from payment confirmation.</em>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right: Order & Shipping Summary -->
        <div>
            <div class="track-card">
                <h3>Order Summary</h3>
                <table class="side-items-table">
                    <tbody>
                        <?php foreach ($order['items'] as $item): ?>
                        <tr>
                            <td>
                                <strong><?= e($item['product_name']) ?></strong>
                                <?php if (!empty($item['variant_info'])): ?>
                                    <div style="font-size:0.75rem;color:#888;"><?= e($item['variant_info']) ?></div>
                                <?php endif; ?>
                                <div style="font-size:0.8rem;color:#555;">Qty: <?= (int)$item['quantity'] ?> × ₹<?= number_format((float)$item['unit_price'], 2) ?></div>
                            </td>
                            <td style="text-align: right; vertical-align: top; font-weight: 600;">
                                ₹<?= number_format((float)$item['line_total'], 2) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="side-totals-row">
                    <span>Subtotal</span>
                    <span>₹<?= number_format((float)$order['subtotal'], 2) ?></span>
                </div>
                <div class="side-totals-row">
                    <span>Shipping</span>
                    <span><?= (float)$order['shipping_charge'] > 0 ? '₹' . number_format((float)$order['shipping_charge'], 2) : 'FREE' ?></span>
                </div>
                <?php if ((float)$order['discount_amount'] > 0): ?>
                <div class="side-totals-row" style="color: #27ae60;">
                    <span>Discount</span>
                    <span>-₹<?= number_format((float)$order['discount_amount'], 2) ?></span>
                </div>
                <?php endif; ?>
                <div class="side-totals-row final">
                    <span>Total</span>
                    <span>₹<?= number_format((float)$order['total'], 2) ?></span>
                </div>

                <div style="margin-top: 1rem; padding-top: 0.75rem; border-top: 1px solid #f0eaee;">
                    <div style="font-size: 0.8rem; color: #888; text-transform: uppercase; font-weight: 700;">Payment Status</div>
                    <div style="font-weight: 600; font-size: 0.95rem; color: <?= $order['payment_status'] === 'paid' ? '#27ae60' : '#d35400' ?>;">
                        <?= strtoupper(e($order['payment_status'])) ?>
                    </div>
                </div>

                <a href="<?= e($waInquiryUrl) ?>" target="_blank" rel="noopener noreferrer" class="wa-inquiry-btn">
                    <svg viewBox="0 0 24 24">
                        <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                    </svg>
                    Order Help on WhatsApp
                </a>
            </div>

            <!-- Shipping Destination -->
            <div class="track-card">
                <h3>Delivery Address</h3>
                <div style="font-size: 0.88rem; line-height: 1.5; color: #444;">
                    <strong><?= e($order['ship_name']) ?></strong><br>
                    📞 <?= e($order['ship_phone']) ?><br>
                    <?= e($order['ship_line1']) ?><?= $order['ship_line2'] ? ', ' . e($order['ship_line2']) : '' ?><br>
                    <?= e($order['ship_city']) ?>, <?= e($order['ship_state']) ?> — <?= e($order['ship_pincode']) ?><br>
                    <?= e($order['ship_country']) ?>
                </div>
            </div>
        </div>

    </div>

    <div style="margin-top: 2rem; text-align: center;">
        <a href="<?= e(url('/account.php?tab=orders')) ?>" style="color: var(--color-maroon); font-weight: 600; text-decoration: none; font-size: 0.95rem;">
            ← Back to Order History
        </a>
    </div>

<?php endif; ?>

</main>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
