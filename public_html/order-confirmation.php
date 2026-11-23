<?php
/**
 * order-confirmation.php — Heramba Fashion
 * ===========================================
 * Displays order confirmation and UPI payment instructions.
 *
 * Security:
 *   - Login required.
 *   - IDOR prevention: order must belong to the logged-in user.
 *   - Public order ID validated against safe regex.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config/bootstrap.php';

// ── Auth Guard ───────────────────────────────────────────────────────────────
if (!Session::isLoggedIn()) {
    header('Location: ' . url('/login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/')));
    exit;
}

$userId = (int) Session::userId();

// ── Fetch & Validate Order ID ────────────────────────────────────────────────
$publicOrderId = trim((string)($_GET['id'] ?? ''));
$order = null;

if (preg_match('/^HF-[A-Z0-9]{4,16}$/i', $publicOrderId)) {
    $orderModel = new OrderModel();
    // IDOR protection: passes $userId so query enforces user_id
    $order = $orderModel->findByPublicId($publicOrderId, $userId);
}

// ── Settings & WhatsApp Links ────────────────────────────────────────────────
$settingModel  = new SettingModel();
$upiId         = (string) $settingModel->get('upi_id', 'yourname@upi');
$rawWaNumber   = (string) $settingModel->get('whatsapp_number', '919876543210');
$cleanWaNumber = preg_replace('/\D/', '', $rawWaNumber);

require_once APP_ROOT . '/config/whatsapp-templates.php';

$waInquiryMsg  = whatsapp_order_inquiry_message($publicOrderId);
$waInquiryUrl  = 'https://wa.me/' . $cleanWaNumber . '?text=' . rawurlencode($waInquiryMsg);

// If checkout placed an order just now, retrieve the prefilled message URL
$autoWaUrl = $_SESSION['last_whatsapp_url'] ?? null;
unset($_SESSION['last_whatsapp_url']); // Clear once loaded

$pageTitle = $order ? 'Order Confirmed: ' . $order['public_order_id'] : 'Order Not Found';
$bodyClass = 'page-confirmation';

$extraHead = '<style>
.confirm-container { max-width: 820px; margin: 2.5rem auto 4rem; padding: 0 1rem; }
.confirm-card { background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(122,31,61,0.08); padding: 2.5rem 2rem; border: 1px solid #f0e6eb; text-align: center; }
.success-icon-wrap { width: 72px; height: 72px; background: #e8f8f0; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem; color: #27ae60; }
.success-icon-wrap svg { width: 40px; height: 40px; stroke: #27ae60; stroke-width: 2.5; }
.confirm-card h1 { font-family: var(--font-heading); color: var(--color-maroon); font-size: 2rem; margin: 0 0 0.5rem; }
.confirm-subtitle { color: #666; font-size: 1rem; margin: 0 0 1.5rem; }
.order-id-pill { display: inline-block; background: #fdf5f8; border: 1.5px dashed var(--color-maroon); color: var(--color-maroon); padding: 0.5rem 1.25rem; border-radius: 50px; font-weight: 700; font-size: 1.15rem; letter-spacing: 0.05em; margin-bottom: 2rem; }

/* UPI Payment Card */
.upi-box { background: #fffdf9; border: 2px solid #e8d8be; border-radius: 14px; padding: 1.75rem; text-align: left; margin: 2rem 0; position: relative; }
.upi-box-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 1px dashed #e4d3b8; }
.upi-title { font-family: var(--font-heading); color: var(--color-maroon); font-size: 1.2rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; margin: 0; }
.no-cod-tag { background: #fff0f0; color: #c0392b; font-size: 0.75rem; font-weight: 700; padding: 0.25rem 0.65rem; border-radius: 50px; border: 1px solid #f5c6cb; text-transform: uppercase; letter-spacing: 0.04em; }
.upi-id-row { display: flex; align-items: center; gap: 0.75rem; background: #fff; padding: 0.65rem 1rem; border-radius: 8px; border: 1.5px solid #e2d2ba; margin: 0.75rem 0; flex-wrap: wrap; }
.upi-id-text { font-family: monospace; font-size: 1.1rem; font-weight: 700; color: #2B2B2B; flex: 1; min-width: 180px; }
.btn-copy { background: var(--color-maroon); color: #fff; border: none; padding: 0.4rem 1rem; border-radius: 50px; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: background 0.2s; }
.btn-copy:hover { background: #5a1630; }
.upi-amount-row { display: flex; justify-content: space-between; align-items: center; font-size: 1.05rem; margin: 0.75rem 0; font-weight: 600; color: #2B2B2B; }
.upi-amount-val { color: var(--color-maroon); font-size: 1.35rem; font-weight: 700; }
.upi-steps { font-size: 0.88rem; color: #555; line-height: 1.6; margin: 1rem 0 0; padding-left: 1.25rem; }
.upi-steps li { margin-bottom: 0.35rem; }

/* Buttons */
.wa-btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.6rem; width: 100%; max-width: 440px; margin: 1rem auto 0; padding: 0.95rem 1.75rem; background: #25D366; color: #fff; border-radius: 50px; text-decoration: none; font-weight: 700; font-size: 1.05rem; box-shadow: 0 4px 14px rgba(37,211,102,0.3); transition: transform 0.2s, background 0.2s; }
.wa-btn:hover { background: #1eb956; transform: translateY(-2px); }
.wa-btn svg { width: 22px; height: 22px; fill: #fff; }

.action-links { display: flex; justify-content: center; gap: 1rem; margin-top: 1.75rem; flex-wrap: wrap; }
.btn-pill { padding: 0.65rem 1.4rem; border-radius: 50px; text-decoration: none; font-size: 0.9rem; font-weight: 600; transition: all 0.2s; }
.btn-pill-primary { background: var(--color-maroon); color: #fff; }
.btn-pill-primary:hover { background: #5a1630; }
.btn-pill-outline { background: #fff; color: var(--color-maroon); border: 1.5px solid var(--color-maroon); }
.btn-pill-outline:hover { background: #fdf5f8; }

/* Order Summary Box */
.order-summary-box { background: #faf8fa; border-radius: 12px; border: 1px solid #f0eaee; padding: 1.5rem; text-align: left; margin: 2rem 0; }
.summary-heading { font-family: var(--font-heading); color: var(--color-maroon); font-size: 1.15rem; margin: 0 0 1rem; border-bottom: 1px solid #e8dde5; padding-bottom: 0.5rem; }
.summary-items-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
.summary-items-table th { color: #888; font-weight: 600; text-align: left; padding: 0.4rem 0.5rem; }
.summary-items-table td { padding: 0.6rem 0.5rem; border-bottom: 1px solid #f2ecf0; }
.summary-items-table tr:last-child td { border-bottom: none; }
.summary-totals { margin-top: 1rem; padding-top: 0.75rem; border-top: 1.5px dashed #e0d6dc; font-size: 0.95rem; }
.totals-row { display: flex; justify-content: space-between; margin-bottom: 0.4rem; }
.totals-row.final { font-weight: 700; color: var(--color-maroon); font-size: 1.1rem; margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px solid #e8dde5; }
.address-snippet { font-size: 0.88rem; color: #555; line-height: 1.5; margin-top: 1rem; background: #fff; padding: 0.9rem 1.1rem; border-radius: 8px; border: 1px solid #e8dde5; }
.notes-snippet { margin-top: 0.75rem; padding: 0.75rem 1rem; background: #fff9ed; border-left: 3px solid #f39c12; border-radius: 4px; font-size: 0.85rem; color: #8a6d3b; }
</style>';

require_once APP_ROOT . '/includes/header.php';
?>

<main id="main-content" class="confirm-container">

<?php if (!$order): ?>
    <div class="confirm-card">
        <h1 style="color:#c0392b;">Order Not Found</h1>
        <p class="confirm-subtitle">We couldn't locate this order, or it doesn't belong to your account.</p>
        <div class="action-links">
            <a href="<?= e(url('/account.php?tab=orders')) ?>" class="btn-pill btn-pill-primary">View My Orders</a>
            <a href="<?= e(url('/collection.php')) ?>" class="btn-pill btn-pill-outline">Continue Shopping</a>
        </div>
    </div>
<?php else: ?>

    <div class="confirm-card">
        <div style="margin: 0 auto 1.25rem; text-align: center;">
            <a href="<?= e(url('/')) ?>" aria-label="Heramba Fashion — Home">
                <picture>
                    <source srcset="<?= e(asset('/assets/images/brand/logo-128.webp')) ?> 2x,
                                    <?= e(asset('/assets/images/brand/logo-64.webp')) ?> 1x"
                            type="image/webp">
                    <img src="<?= e(asset('/assets/images/brand/logo-64.png')) ?>"
                         srcset="<?= e(asset('/assets/images/brand/logo-128.png')) ?> 2x"
                         alt="Heramba Fashion logo"
                         width="64" height="64"
                         style="width:64px;height:64px;border-radius:50%;display:inline-block;"
                         loading="eager">
                </picture>
            </a>
        </div>

        <div class="success-icon-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
        </div>

        <h1>Thank You for Your Order!</h1>
        <p class="confirm-subtitle">Your order has been recorded. Complete payment via UPI to confirm dispatch.</p>

        <div class="order-id-pill">
            Order Reference: <?= e($order['public_order_id']) ?>
        </div>

        <!-- UPI Payment Card -->
        <div class="upi-box">
            <div class="upi-box-header">
                <h2 class="upi-title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="5" width="20" height="14" rx="2"/>
                        <line x1="2" y1="10" x2="22" y2="10"/>
                    </svg>
                    Pay via UPI
                </h2>
                <span class="no-cod-tag">Online Payment Only · No COD</span>
            </div>

            <div class="upi-amount-row">
                <span>Amount Payable:</span>
                <span class="upi-amount-val">₹<?= number_format((float)$order['total'], 2) ?></span>
            </div>

            <div style="font-size: 0.82rem; color: #777; margin-top: 0.5rem;">Transfer to Store UPI ID:</div>
            <div class="upi-id-row">
                <span class="upi-id-text" id="store-upi"><?= e($upiId) ?></span>
                <button type="button" class="btn-copy" id="copy-btn" onclick="copyUpiId()">Copy UPI ID</button>
            </div>

            <ol class="upi-steps">
                <li>Open Google Pay, PhonePe, Paytm or your bank app.</li>
                <li>Send <strong>₹<?= number_format((float)$order['total'], 2) ?></strong> to UPI ID: <strong><?= e($upiId) ?></strong></li>
                <li>Take a screenshot of the completed payment.</li>
                <li>Click the green WhatsApp button below to share the screenshot with our team.</li>
            </ol>
        </div>

        <!-- WhatsApp Share Button -->
        <?php $targetWa = $autoWaUrl ?: $waInquiryUrl; ?>
        <a href="<?= e($targetWa) ?>" target="_blank" rel="noopener noreferrer" class="wa-btn" id="wa-action-btn">
            <svg viewBox="0 0 24 24">
                <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
            </svg>
            Send Payment Screenshot on WhatsApp
        </a>

        <!-- Order Summary Collapsible -->
        <div class="order-summary-box">
            <h3 class="summary-heading">Order Breakdown</h3>
            <table class="summary-items-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th style="text-align:center;">Qty</th>
                        <th style="text-align:right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order['items'] as $item): ?>
                    <tr>
                        <td>
                            <strong><?= e($item['product_name']) ?></strong>
                            <?php if (!empty($item['variant_info'])): ?>
                                <div style="font-size:0.8rem;color:#777;"><?= e($item['variant_info']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;"><?= (int)$item['quantity'] ?></td>
                        <td style="text-align:right;">₹<?= number_format((float)$item['line_total'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="summary-totals">
                <div class="totals-row">
                    <span>Subtotal</span>
                    <span>₹<?= number_format((float)$order['subtotal'], 2) ?></span>
                </div>
                <div class="totals-row">
                    <span>Shipping</span>
                    <span><?= (float)$order['shipping_charge'] > 0 ? '₹' . number_format((float)$order['shipping_charge'], 2) : 'FREE' ?></span>
                </div>
                <?php if ((float)$order['discount_amount'] > 0): ?>
                <div class="totals-row" style="color:#27ae60;">
                    <span>Discount</span>
                    <span>-₹<?= number_format((float)$order['discount_amount'], 2) ?></span>
                </div>
                <?php endif; ?>
                <div class="totals-row final">
                    <span>Total Amount</span>
                    <span>₹<?= number_format((float)$order['total'], 2) ?></span>
                </div>
            </div>

            <!-- Delivery Address -->
            <div class="address-snippet">
                <strong>Shipping Address:</strong><br>
                <?= e($order['ship_name']) ?> (<?= e($order['ship_phone']) ?>)<br>
                <?= e($order['ship_line1']) ?><?= $order['ship_line2'] ? ', ' . e($order['ship_line2']) : '' ?><br>
                <?= e($order['ship_city']) ?>, <?= e($order['ship_state']) ?> — <?= e($order['ship_pincode']) ?><br>
                <?= e($order['ship_country']) ?>
            </div>

            <!-- Customization Notice -->
            <?php if (!empty($order['customization_notes'])): ?>
            <div class="notes-snippet">
                ✂️ <strong>Customization Instructions:</strong> <?= e($order['customization_notes']) ?><br>
                <em>Please note: Custom-tailored orders require up to 5 working days before dispatch.</em>
            </div>
            <?php endif; ?>
        </div>

        <!-- Navigation Actions -->
        <div class="action-links">
            <a href="<?= e(url('/order-tracking.php?id=' . urlencode($order['public_order_id']))) ?>" class="btn-pill btn-pill-primary">
                Track Order Status →
            </a>
            <a href="<?= e(url('/account.php?tab=orders')) ?>" class="btn-pill btn-pill-outline">
                All My Orders
            </a>
            <a href="<?= e(url('/collection.php')) ?>" class="btn-pill btn-pill-outline">
                Continue Shopping
            </a>
        </div>
    </div>

<?php endif; ?>

</main>

<script>
function copyUpiId() {
    const text = document.getElementById('store-upi').innerText.trim();
    const btn  = document.getElementById('copy-btn');
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(() => {
            btn.innerText = 'Copied!';
            setTimeout(() => { btn.innerText = 'Copy UPI ID'; }, 2000);
        }).catch(() => fallbackCopy(text, btn));
    } else {
        fallbackCopy(text, btn);
    }
}

function fallbackCopy(text, btn) {
    const ta = document.createElement('textarea');
    ta.value = text;
    document.body.appendChild(ta);
    ta.select();
    try {
        document.execCommand('copy');
        btn.innerText = 'Copied!';
        setTimeout(() => { btn.innerText = 'Copy UPI ID'; }, 2000);
    } catch (e) {
        btn.innerText = 'Select & Copy';
    }
    document.body.removeChild(ta);
}

// If customer just completed order placement, automatically open WhatsApp after 1.5 seconds
<?php if ($autoWaUrl): ?>
window.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        const waBtn = document.getElementById('wa-action-btn');
        if (waBtn) {
            window.open(waBtn.href, '_blank');
        }
    }, 1200);
});
<?php endif; ?>
</script>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
