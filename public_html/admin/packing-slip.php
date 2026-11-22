<?php
/**
 * Printable Packing Slip — Herbama Fashions
 * ==========================================
 * Formatted for boutique warehouse fulfillment and garment packaging.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

// Admin auth check
if (!Session::isAdmin()) {
    http_response_code(403);
    die('Unauthorized access.');
}

$orderModel = new OrderModel();
$orderId    = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$order      = $orderModel->findById($orderId);

if (!$order) {
    die('Order not found.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PackingSlip_<?= e($order['public_order_id']) ?> — Heramba Fashion</title>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= asset('/assets/images/icons/favicon-32.png') ?>">
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #222;
            margin: 0;
            padding: 2rem;
            background: #f9f9f9;
        }
        .slip-box {
            max-width: 800px;
            margin: auto;
            padding: 2.5rem;
            border: 2px dashed #7A1F3D;
            background: #fff;
        }
        .slip-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #7A1F3D;
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
        }
        .slip-title {
            font-size: 1.6rem;
            font-weight: 700;
            color: #7A1F3D;
            margin: 0;
        }
        .slip-meta {
            text-align: right;
            font-size: 0.95rem;
        }
        .dest-card {
            background: #FAF6F0;
            border: 1px solid #E8D8DE;
            border-radius: 8px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
        }
        .dest-card h3 {
            margin: 0 0 0.5rem;
            color: #7A1F3D;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1.5rem;
        }
        .items-table th {
            background: #7A1F3D;
            color: #fff;
            padding: 0.65rem 0.6rem;
            font-size: 0.85rem;
            text-align: left;
        }
        .items-table td {
            padding: 0.75rem 0.6rem;
            border-bottom: 1px solid #eee;
            font-size: 0.9rem;
        }
        .checkbox-cell {
            width: 40px;
            text-align: center;
        }
        .box-check {
            width: 18px;
            height: 18px;
            border: 2px solid #777;
            display: inline-block;
            border-radius: 3px;
        }
        .customization-box {
            background: #fff8e8;
            border: 2px solid #f39c12;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            color: #8a6d3b;
            font-size: 0.9rem;
        }
        .dispatch-checklist {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            border-top: 1px solid #eee;
            padding-top: 1.25rem;
            margin-top: 1.5rem;
            font-size: 0.85rem;
        }
        .check-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .sign-row {
            display: flex;
            justify-content: space-between;
            margin-top: 2.5rem;
            padding-top: 1rem;
            border-top: 1px solid #ddd;
            font-size: 0.85rem;
            color: #555;
        }
        .print-btn-bar {
            max-width: 800px;
            margin: 0 auto 1.5rem;
            display: flex;
            justify-content: flex-end;
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
            .slip-box { border: 1px solid #000; padding: 1.5rem; }
            .print-btn-bar { display: none; }
        }
    </style>
</head>
<body>

<div class="print-btn-bar">
    <button type="button" class="btn-print" onclick="window.print()">🖨️ Print Packing Slip</button>
</div>

<div class="slip-box">
    <div class="slip-header">
        <div style="display:flex;align-items:center;gap:0.6rem;">
            <img src="<?= asset('/assets/images/brand/logo-64.png') ?>"
                 alt="Herabama Fashions logo — round maroon and gold badge"
                 width="48" height="48"
                 style="border-radius:50%;">
            <div>
                <h1 class="slip-title">Herabama Fashions</h1>
                <div style="font-size:0.8rem;color:#666;">Warehouse Packing &amp; Fulfillment Slip</div>
            </div>
        </div>
        <div class="slip-meta">
            <strong style="font-size:1.2rem;color:#7A1F3D;"><?= e($order['public_order_id']) ?></strong><br>
            Order Date: <?= e(date('d M Y', strtotime($order['created_at']))) ?>
        </div>
    </div>

    <div class="dest-card">
        <h3>Deliver To Customer:</h3>
        <div style="font-size:1.05rem;font-weight:700;"><?= e($order['ship_name']) ?></div>
        <div style="font-size:1rem;margin:0.25rem 0;">📞 <strong><?= e($order['ship_phone']) ?></strong></div>
        <div style="line-height:1.4;">
            <?= e($order['ship_line1']) ?><?= $order['ship_line2'] ? ', ' . e($order['ship_line2']) : '' ?><br>
            <?= e($order['ship_city']) ?>, <?= e($order['ship_state']) ?> — <?= e($order['ship_pincode']) ?><br>
            <strong><?= e($order['ship_country']) ?></strong>
        </div>
    </div>

    <?php if (!empty($order['customization_notes'])): ?>
    <div class="customization-box">
        <strong>✂️ CUSTOMIZATION / SPECIAL INSTRUCTIONS:</strong><br>
        <?= e($order['customization_notes']) ?>
    </div>
    <?php endif; ?>

    <table class="items-table">
        <thead>
            <tr>
                <th class="checkbox-cell">Pick</th>
                <th>Item Name</th>
                <th>Size / Age</th>
                <th style="text-align:center;">Qty</th>
                <th>Verified</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($order['items'] as $item): ?>
            <tr>
                <td class="checkbox-cell">
                    <span class="box-check"></span>
                </td>
                <td>
                    <strong><?= e($item['product_name']) ?></strong>
                </td>
                <td>
                    <span style="font-weight:600;color:#7A1F3D;"><?= e($item['variant_info'] ?? 'Standard') ?></span>
                </td>
                <td style="text-align:center;font-size:1.1rem;font-weight:700;">
                    <?= (int)$item['quantity'] ?>
                </td>
                <td style="width:70px;text-align:center;">
                    <span class="box-check"></span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="dispatch-checklist">
        <div>
            <div class="check-item"><span class="box-check"></span> Garment Steamed & Tagged</div>
            <div class="check-item" style="margin-top:0.5rem;"><span class="box-check"></span> Butter Paper & Gold Ribbon Wrapped</div>
        </div>
        <div>
            <div class="check-item"><span class="box-check"></span> Invoice & Care Guide Included</div>
            <div class="check-item" style="margin-top:0.5rem;"><span class="box-check"></span> Polybag Sealed with Security Tape</div>
        </div>
    </div>

    <div class="sign-row">
        <div>Packed By: ___________________________</div>
        <div>QC Checked: ___________________________</div>
        <div>Date: ________________</div>
    </div>
</div>

</body>
</html>
