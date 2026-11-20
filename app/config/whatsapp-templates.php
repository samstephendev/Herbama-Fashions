<?php
/**
 * Herbama Fashions — WhatsApp Message Templates
 * ===============================================
 * Keep all customer & store WhatsApp message templates in this single editable file.
 * The store uses "Online Payment Only, No COD". Payment is confirmed manually by admin.
 */

declare(strict_types=1);

if (!function_exists('whatsapp_order_placed_message')) {
    /**
     * Pre-filled WhatsApp message sent to store owner upon placing an order.
     *
     * @param array  $order Full order details (public_order_id, ship_name, ship_phone, address, totals, notes)
     * @param array  $items List of items [[product_name, variant_info, quantity, unit_price, line_total]]
     * @param string $upiId Store UPI payment address
     * @return string Pre-filled WhatsApp message
     */
    function whatsapp_order_placed_message(array $order, array $items, string $upiId): string
    {
        $sym = defined('CURRENCY_SYMBOL') ? CURRENCY_SYMBOL : '₹';
        $orderId = $order['public_order_id'] ?? 'HF-ORDER';

        $msg = "✨ *NEW ORDER: {$orderId}* ✨\n";
        $msg .= "------------------------------------\n";
        $msg .= "*Customer:* " . ($order['ship_name'] ?? '') . "\n";
        $msg .= "*Phone:* " . ($order['ship_phone'] ?? '') . "\n";

        // Full Address
        $addrParts = array_filter([
            $order['ship_line1'] ?? '',
            $order['ship_line2'] ?? '',
            $order['ship_city'] ?? '',
            $order['ship_state'] ?? '',
            !empty($order['ship_pincode']) ? "PIN: {$order['ship_pincode']}" : '',
            $order['ship_country'] ?? 'India'
        ]);
        $msg .= "*Delivery Address:* " . implode(', ', $addrParts) . "\n";
        $msg .= "------------------------------------\n";

        // Ordered Items
        $msg .= "🛍️ *ITEMS ORDERED:*\n";
        foreach ($items as $item) {
            $name  = $item['product_name'] ?? 'Product';
            $vInfo = !empty($item['variant_info']) ? " ({$item['variant_info']})" : '';
            $qty   = (int)($item['quantity'] ?? 1);
            $line  = number_format((float)($item['line_total'] ?? 0), 2);
            $msg .= "• {$name}{$vInfo} × {$qty} = {$sym}{$line}\n";
        }
        $msg .= "------------------------------------\n";

        // Financial Summary
        $subtotal = number_format((float)($order['subtotal'] ?? 0), 2);
        $shipping = (float)($order['shipping_charge'] ?? 0);
        $discount = (float)($order['discount_amount'] ?? 0);
        $total    = number_format((float)($order['total'] ?? 0), 2);

        $msg .= "*Subtotal:* {$sym}{$subtotal}\n";
        if ($shipping > 0) {
            $msg .= "*Shipping:* {$sym}" . number_format($shipping, 2) . "\n";
        } else {
            $msg .= "*Shipping:* FREE\n";
        }
        if ($discount > 0) {
            $msg .= "*Discount:* -{$sym}" . number_format($discount, 2) . "\n";
        }
        $msg .= "*TOTAL PAYABLE:* {$sym}{$total}\n";
        $msg .= "------------------------------------\n";

        // Customization Notes (if any)
        if (!empty($order['customization_notes'])) {
            $msg .= "✂️ *CUSTOMIZATION NOTES:*\n";
            $msg .= trim((string)$order['customization_notes']) . "\n";
            $msg .= "_(Note: Custom tailored outfits require 5 working days)_\n";
            $msg .= "------------------------------------\n";
        }

        // Customer Delivery Notes
        if (!empty($order['notes'])) {
            $msg .= "📝 *CUSTOMER NOTES:* " . trim((string)$order['notes']) . "\n";
            $msg .= "------------------------------------\n";
        }

        // Online Payment Instructions
        $msg .= "💳 *ONLINE PAYMENT ONLY (NO COD)*\n";
        $msg .= "Please pay to UPI ID: *{$upiId}*\n";
        $msg .= "Amount: *{$sym}{$total}*\n\n";
        $msg .= "Please share payment details and screenshot here. Our team will verify and confirm your order dispatch! 🎀\n";
        $msg .= "------------------------------------\n";
        $msg .= "Herbama Fashions — Premium Handcrafted Kidswear";

        return $msg;
    }
}

if (!function_exists('whatsapp_order_inquiry_message')) {
    /**
     * Message for customer inquiring about an existing order status.
     */
    function whatsapp_order_inquiry_message(string $publicOrderId): string
    {
        return "Hi Herbama Fashions! 👋 I would like to check the status of my order *{$publicOrderId}*. Could you please provide an update?";
    }
}

if (!function_exists('whatsapp_general_chat_message')) {
    /**
     * Message for generic floating chat button.
     */
    function whatsapp_general_chat_message(): string
    {
        return "Hello Herbama Fashions! 🌸 I'm browsing your kidswear boutique and would like to ask a few questions.";
    }
}
