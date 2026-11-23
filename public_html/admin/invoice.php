<?php
/**
 * Printable Invoice — Heramba Fashion
 * =====================================
 * Clean, printable invoice layout formatted for A4 / standard paper.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

// Admin auth check
if (!Session::isAdmin()) {
    http_response_code(403);
    die('Unauthorized access.');
}

$orderModel   = new OrderModel();
$settingModel = new SettingModel();

$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$order   = $orderModel->findById($orderId);

if (!$order) {
    die('Order not found.');
}

$upiId     = (string)$settingModel->get('upi_id', 'yourname@upi');
$storeName = 'Heramba Fashion';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice_<?= e($order['public_order_id']) ?> — <?= $storeName ?></title>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= asset('/assets/images/icons/favicon-32.png') ?>">
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #333;
            margin: 0;
            padding: 2rem;
            background: #f9f9f9;
        }
        .invoice-box {
            max-width: 800px;
            margin: auto;
            padding: 2.5rem;
            border: 1px solid #eee;
            background: #fff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);
        }
        .header-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #7A1F3D;
            padding-bottom: 1.5rem;
            margin-bottom: 2rem;
        }
        .brand-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #7A1F3D;
            margin: 0 0 0.25rem;
        }
        .brand-subtitle {
            font-size: 0.85rem;
            color: #C9A24B;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .invoice-meta {
            text-align: right;
            font-size: 0.9rem;
            line-height: 1.5;
        }
        .invoice-meta strong {
            font-size: 1.1rem;
            color: #7A1F3D;
        }
        .address-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
            font-size: 0.9rem;
            line-height: 1.5;
        }
        .address-box h4 {
            margin: 0 0 0.5rem;
            color: #7A1F3D;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
            font-size: 0.9rem;
        }
        .items-table th {
            background: #fbf6f8;
            color: #7A1F3D;
            font-weight: 700;
            padding: 0.75rem 0.6rem;
            border-bottom: 2px solid #e0d4dd;
            text-align: left;
        }
        .items-table td {
            padding: 0.75rem 0.6rem;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }
        .totals-table {
            width: 300px;
            margin-left: auto;
            border-collapse: collapse;
            font-size: 0.95rem;
            margin-bottom: 2rem;
        }
        .totals-table td {
            padding: 0.4rem 0.5rem;
        }
        .totals-table tr.grand-total {
            font-weight: 700;
            font-size: 1.15rem;
            color: #7A1F3D;
            border-top: 2px solid #7A1F3D;
        }
        .invoice-footer {
            border-top: 1px solid #eee;
            padding-top: 1.5rem;
            font-size: 0.8rem;
            color: #777;
            text-align: center;
            line-height: 1.6;
        }
        .print-btn-bar {
            max-width: 800px;
            margin: 0 auto 1.5rem;
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }
        .btn-print {
            background: #7A1F3D;
            color: #fff;
            border: none;
            padding: 0.6rem 1.4rem;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.9rem;
        }
        @media print {
            body { background: #fff; padding: 0; }
            .invoice-box { box-shadow: none; border: none; padding: 0; }
            .print-btn-bar { display: none; }
        }
    </style>
</head>
<body>

<div class="print-btn-bar">
    <button type="button" class="btn-print" onclick="window.print()">🖨️ Print Invoice</button>
</div>

<div class="invoice-box">
    <div class="header-row">
        <div style="display:flex;align-items:center;gap:0.75rem;">
            <div style="position:relative;width:64px;height:64px;flex-shrink:0;">
                <img src="<?= asset('/assets/images/brand/logo-128.png') ?>"
                     alt="Heramba Fashion logo"
                     width="64" height="64"
                     style="width:64px;height:64px;border-radius:50%;display:block;"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                <div class="logo-fallback" style="display:none;width:64px;height:64px;background:#7A1F3D;color:#FFF9F3;border-radius:50%;align-items:center;justify-content:center;font-weight:700;font-family:serif;font-size:1.25rem;">HF</div>
            </div>
            <div>
                <div class="brand-title"><?= $storeName ?></div>
                <div class="brand-subtitle">Dress Every Smile · Premium Handcrafted Kidswear</div>
                <div style="font-size:0.82rem;color:#777;margin-top:0.35rem;">
                    Online Payment via UPI · Pan-India &amp; UAE Dispatch
                </div>
            </div>
        </div>
        <div class="invoice-meta">
            <strong>INVOICE</strong><br>
            Invoice #: INV-<?= e(str_replace('HF-', '', $order['public_order_id'])) ?><br>
            Order Ref: <?= e($order['public_order_id']) ?><br>
            Date: <?= e(date('d M Y', strtotime($order['created_at']))) ?><br>
            Payment: <strong style="color:<?= $order['payment_status'] === 'paid' ? '#27ae60' : '#d35400' ?>;"><?= strtoupper(e($order['payment_status'])) ?></strong>
        </div>
    </div>

    <div class="address-grid">
        <div class="address-box">
            <h4>Billed & Shipped To:</h4>
            <strong><?= e($order['ship_name']) ?></strong><br>
            Phone: <?= e($order['ship_phone']) ?><br>
            <?= e($order['ship_line1']) ?><?= $order['ship_line2'] ? ', ' . e($order['ship_line2']) : '' ?><br>
            <?= e($order['ship_city']) ?>, <?= e($order['ship_state']) ?> — <?= e($order['ship_pincode']) ?><br>
            <?= e($order['ship_country']) ?>
        </div>
        <div class="address-box" style="text-align:right;">
            <h4>Payment Information:</h4>
            Payment Method: <strong>Online UPI</strong><br>
            UPI ID: <strong><?= e($upiId) ?></strong><br>
            <?php if (!empty($order['payment_ref'])): ?>
                Transaction Ref: <code><?= e($order['payment_ref']) ?></code><br>
            <?php endif; ?>
            Dispatch Window: <?= !empty($order['customization_notes']) ? '5 Working Days (Customized)' : 'Ready to Dispatch (0-3Y)' ?>
        </div>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Item Description</th>
                <th>Variant / Size</th>
                <th style="text-align:center;">Qty</th>
                <th style="text-align:right;">Rate</th>
                <th style="text-align:right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php $i = 1; foreach ($order['items'] as $item): ?>
            <tr>
                <td style="width:25px;color:#888;"><?= $i++ ?></td>
                <td>
                    <strong><?= e($item['product_name']) ?></strong>
                </td>
                <td><?= e($item['variant_info'] ?? 'Standard') ?></td>
                <td style="text-align:center;"><?= (int)$item['quantity'] ?></td>
                <td style="text-align:right;">₹<?= number_format((float)$item['unit_price'], 2) ?></td>
                <td style="text-align:right;">₹<?= number_format((float)$item['line_total'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <table class="totals-table">
        <tr>
            <td>Subtotal:</td>
            <td style="text-align:right;">₹<?= number_format((float)$order['subtotal'], 2) ?></td>
        </tr>
        <tr>
            <td>Shipping:</td>
            <td style="text-align:right;"><?= (float)$order['shipping_charge'] > 0 ? '₹' . number_format((float)$order['shipping_charge'], 2) : 'FREE' ?></td>
        </tr>
        <?php if ((float)$order['discount_amount'] > 0): ?>
        <tr style="color:#27ae60;">
            <td>Discount:</td>
            <td style="text-align:right;">-₹<?= number_format((float)$order['discount_amount'], 2) ?></td>
        </tr>
        <?php endif; ?>
        <tr class="grand-total">
            <td>Total Payable:</td>
            <td style="text-align:right;">₹<?= number_format((float)$order['total'], 2) ?></td>
        </tr>
    </table>

    <?php if (!empty($order['customization_notes'])): ?>
    <div style="background:#fffcf5;border:1px dashed #f39c12;padding:0.75rem 1rem;font-size:0.85rem;color:#8a6d3b;margin-bottom:1.5rem;border-radius:6px;">
        <strong>✂️ Customization Notes:</strong> <?= e($order['customization_notes']) ?>
    </div>
    <?php endif; ?>

    <div class="invoice-footer">
        Thank you for choosing <?= $storeName ?> for your little fashionista!<br>
        For inquiries, size guidance or custom orders, message us on WhatsApp.
    </div>
</div>

</body>
</html>
