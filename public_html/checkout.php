<?php
/**
 * checkout.php — Herbama Fashions
 * =================================
 * Secure checkout page. Allows the customer to:
 *   1. Select a saved delivery address (or add a new one on-the-fly).
 *   2. Enter customization / special instruction notes.
 *   3. Review the order summary (items, subtotal, shipping, total).
 *   4. Place the order via WhatsApp (redirects to order-confirmation.php).
 *
 * POST actions:
 *   - action=add_address   → save a new address, redirect back to checkout.
 *   - action=place_order   → create the order, redirect to order-confirmation.php.
 *
 * Security:
 *   - Login required (Session::requireLogin).
 *   - CSRF verification on every POST.
 *   - Prices/totals always re-fetched server-side from CartModel.
 *   - Address ownership verified before use (IDOR guard).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config/bootstrap.php';

$pageTitle = 'Checkout';
$bodyClass = 'page-checkout';

// ── Auth guard ────────────────────────────────────────────────────────────────
if (!Session::isLoggedIn()) {
    header('Location: ' . url('/login.php?next=' . urlencode('/checkout.php')));
    exit;
}
$userId = (int) Session::userId();

// ── Instantiate models ────────────────────────────────────────────────────────
$cartModel    = new CartModel();
$userModel    = new UserModel();
$orderModel   = new OrderModel();
$settingModel = new SettingModel();

// ══════════════════════════════════════════════════════════════════════════════
// POST Handler
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify(); // Mandatory CSRF check on every POST

    $action = trim($_POST['action'] ?? '');

    // ── Helper: Validate address fields ───────────────────────────────────────
    /**
     * Returns array of validated address data or false with errors in $errors.
     */
    $validateAddress = function (array $post, array &$errors): array|false {
        $data = [
            'recipient_name' => trim(strip_tags($post['recipient_name'] ?? '')),
            'phone'          => preg_replace('/\s+/', '', $post['phone'] ?? ''),
            'line1'          => trim(strip_tags($post['line1'] ?? '')),
            'line2'          => trim(strip_tags($post['line2'] ?? '')),
            'city'           => trim(strip_tags($post['city'] ?? '')),
            'state'          => trim(strip_tags($post['state'] ?? '')),
            'pincode'        => trim($post['pincode'] ?? ''),
            'country'        => trim($post['country'] ?? 'India'),
        ];

        // Required fields
        if (empty($data['recipient_name'])) {
            $errors[] = 'Recipient name is required.';
        }
        if (empty($data['line1'])) {
            $errors[] = 'Address line 1 is required.';
        }
        if (empty($data['city'])) {
            $errors[] = 'City is required.';
        }
        if (empty($data['state'])) {
            $errors[] = 'State / Emirate is required.';
        }
        if (empty($data['pincode'])) {
            $errors[] = 'PIN / ZIP code is required.';
        }

        // Normalise country
        $countryLower = strtolower($data['country']);
        if (in_array($countryLower, ['uae', 'united arab emirates'], true)) {
            $data['country'] = 'UAE';
        } else {
            $data['country'] = 'India';
        }

        // Phone validation
        if (empty($data['phone'])) {
            $errors[] = 'Phone number is required.';
        } elseif ($data['country'] === 'India') {
            // India: exactly 10 digits
            if (!preg_match('/^[6-9]\d{9}$/', $data['phone'])) {
                $errors[] = 'Enter a valid 10-digit Indian mobile number.';
            }
        } else {
            // UAE: 9 or 10 digits (with optional leading +971 already stripped by preg_replace)
            $uaePhone = preg_replace('/^\+?971/', '', $data['phone']);
            if (!preg_match('/^\d{9,10}$/', $uaePhone) && !preg_match('/^\d{9,10}$/', $data['phone'])) {
                $errors[] = 'Enter a valid UAE phone number (9–10 digits).';
            }
        }

        // Pincode validation
        if (!empty($data['pincode'])) {
            if ($data['country'] === 'India') {
                if (!preg_match('/^\d{6}$/', $data['pincode'])) {
                    $errors[] = 'India PIN code must be exactly 6 digits.';
                }
            } else {
                // UAE: 4-7 alphanumeric characters
                if (!preg_match('/^[A-Za-z0-9]{3,9}$/', $data['pincode'])) {
                    $errors[] = 'Enter a valid UAE postal / area code.';
                }
            }
        }

        return empty($errors) ? $data : false;
    };

    // ── Action: Add Address ───────────────────────────────────────────────────
    if ($action === 'add_address') {
        $errors = [];
        $data   = $validateAddress($_POST, $errors);

        if ($data === false) {
            Session::flash('error', implode(' ', $errors));
            header('Location: ' . url('/checkout.php'));
            exit;
        }

        // Save address
        try {
            $userModel->addAddress($userId, $data);
            Session::flash('success', 'Address saved successfully.');
        } catch (Throwable $e) {
            error_log('[checkout] addAddress error: ' . $e->getMessage());
            Session::flash('error', 'Could not save address. Please try again.');
        }

        header('Location: ' . url('/checkout.php'));
        exit;
    }

    // ── Action: Place Order ───────────────────────────────────────────────────
    if ($action === 'place_order') {
        // Re-fetch cart server-side — NEVER trust POST amounts
        $cartData = $cartModel->getCart($userId);

        if (empty($cartData['items'])) {
            Session::flash('error', 'Your cart is empty.');
            header('Location: ' . url('/cart.php'));
            exit;
        }

        // --- Determine delivery address ---
        $addressSource = trim($_POST['address_source'] ?? 'saved'); // 'saved' | 'new'
        $addressData   = null;
        $errors        = [];

        if ($addressSource === 'new') {
            // Validate and use the inline new address fields
            $addressData = $validateAddress($_POST, $errors);
            if ($addressData === false) {
                Session::flash('error', implode(' ', $errors));
                header('Location: ' . url('/checkout.php'));
                exit;
            }
        } else {
            // Use a saved address — IDOR: verify it belongs to this user
            $addressId = (int) ($_POST['address_id'] ?? 0);
            if ($addressId < 1) {
                Session::flash('error', 'Please select a delivery address.');
                header('Location: ' . url('/checkout.php'));
                exit;
            }

            // Fetch ALL user addresses and find the chosen one
            $savedAddresses = $userModel->getAddresses($userId);
            foreach ($savedAddresses as $addr) {
                if ((int) $addr['id'] === $addressId) {
                    $addressData = [
                        'recipient_name' => $addr['recipient_name'],
                        'phone'          => $addr['phone'],
                        'line1'          => $addr['line1'],
                        'line2'          => $addr['line2'] ?? '',
                        'city'           => $addr['city'],
                        'state'          => $addr['state'],
                        'pincode'        => $addr['pincode'],
                        'country'        => $addr['country'],
                    ];
                    break;
                }
            }

            if ($addressData === null) {
                // Address ID not found in this user's list → reject (IDOR blocked)
                Session::flash('error', 'Invalid delivery address selected. Please try again.');
                header('Location: ' . url('/checkout.php'));
                exit;
            }
        }

        // --- Customization / special instructions notes (max 500 chars) ---
        $customNotes = strip_tags(trim($_POST['customization_notes'] ?? ''));
        if (mb_strlen($customNotes) > 500) {
            $customNotes = mb_substr($customNotes, 0, 500);
        }

        // --- Create the order ---
        $result = $orderModel->createOrder(
            $userId,
            $addressData,
            $cartData['items'],
            ['customization_notes' => $customNotes]
        );

        if (!$result['ok']) {
            Session::flash('error', $result['error'] ?? 'Failed to place your order. Please try again.');
            header('Location: ' . url('/checkout.php'));
            exit;
        }

        // --- Store WhatsApp URL in session for the confirmation page ---
        if (!empty($result['whatsapp_url'])) {
            $_SESSION['last_whatsapp_url'] = $result['whatsapp_url'];
        }

        // --- Redirect to order confirmation ---
        $publicOrderId = $result['public_order_id'];
        header('Location: ' . url('/order-confirmation.php?id=' . urlencode($publicOrderId)));
        exit;
    }

    // Unknown action
    header('Location: ' . url('/checkout.php'));
    exit;
}

// ══════════════════════════════════════════════════════════════════════════════
// GET — Prepare page data
// ══════════════════════════════════════════════════════════════════════════════

// Fetch cart — redirect to cart page if empty
$cartData = $cartModel->getCart($userId);
if (empty($cartData['items'])) {
    Session::flash('info', 'Your cart is empty. Add items before checking out.');
    header('Location: ' . url('/cart.php'));
    exit;
}

// Fetch saved addresses
$addresses = $userModel->getAddresses($userId);

// Determine default address id (for pre-selecting radio)
$defaultAddressId = 0;
foreach ($addresses as $addr) {
    if ($addr['is_default']) {
        $defaultAddressId = (int) $addr['id'];
        break;
    }
}
if ($defaultAddressId === 0 && !empty($addresses)) {
    $defaultAddressId = (int) $addresses[0]['id'];
}

// Shipping label
$shippingLabel = 'Free for India';
$shippingNote  = 'International shipping calculated at checkout for UAE/Dubai orders.';

// WhatsApp number for display
$waNumber = (string) $settingModel->get('whatsapp_number', WHATSAPP_NUMBER);

require_once APP_ROOT . '/includes/header.php';
?>

<div class="container checkout-layout">

    <!-- ── Page Heading ─────────────────────────────────────────────────────── -->
    <div class="checkout-header">
        <h1 class="checkout-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24"
                 fill="none" stroke="var(--color-maroon)" stroke-width="2" aria-hidden="true">
                <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
            </svg>
            Checkout
        </h1>
        <p class="checkout-subtitle">Complete your order below.</p>
    </div>

    <div class="checkout-grid">

        <!-- ── LEFT COLUMN: Address + Notes ─────────────────────────────────── -->
        <div class="checkout-left">

            <!-- ── 1. Delivery Address ─────────────────────────────────────── -->
            <section class="checkout-card" aria-labelledby="address-heading">
                <h2 class="checkout-card__title" id="address-heading">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                         fill="none" stroke="var(--color-maroon)" stroke-width="2" aria-hidden="true">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                        <circle cx="12" cy="10" r="3"/>
                    </svg>
                    Delivery Address
                </h2>

                <form method="POST" action="<?= e(url('/checkout.php')) ?>" id="place-order-form" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="place_order">
                    <input type="hidden" name="address_source" id="address-source-input" value="saved">

                    <?php if (!empty($addresses)): ?>
                        <!-- Saved Address Cards -->
                        <div class="address-list" role="radiogroup" aria-label="Select delivery address">
                            <?php foreach ($addresses as $addr): ?>
                                <label class="address-card <?= (int)$addr['id'] === $defaultAddressId ? 'address-card--selected' : '' ?>"
                                       for="addr-<?= (int)$addr['id'] ?>">
                                    <input
                                        type="radio"
                                        name="address_id"
                                        id="addr-<?= (int)$addr['id'] ?>"
                                        value="<?= (int)$addr['id'] ?>"
                                        class="address-card__radio"
                                        <?= (int)$addr['id'] === $defaultAddressId ? 'checked' : '' ?>
                                        onchange="selectSavedAddress(this)"
                                    >
                                    <div class="address-card__body">
                                        <div class="address-card__name">
                                            <?= e($addr['recipient_name']) ?>
                                            <?php if ($addr['is_default']): ?>
                                                <span class="badge-default">Default</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="address-card__phone"><?= e($addr['phone']) ?></div>
                                        <div class="address-card__address">
                                            <?= e($addr['line1']) ?>
                                            <?php if (!empty($addr['line2'])): ?>,
                                                <?= e($addr['line2']) ?>
                                            <?php endif; ?>
                                            <br>
                                            <?= e($addr['city']) ?>, <?= e($addr['state']) ?> — <?= e($addr['pincode']) ?>
                                            <br>
                                            <?= e($addr['country']) ?>
                                        </div>
                                    </div>
                                    <div class="address-card__check" aria-hidden="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                            <polyline points="20 6 9 17 4 12"/>
                                        </svg>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="checkout-no-address">No saved addresses. Add one below.</p>
                    <?php endif; ?>

                    <!-- ── Add New Address Toggle ──────────────────────────── -->
                    <div class="new-address-toggle-wrap">
                        <button type="button" class="btn-add-address" id="toggle-new-address-btn"
                                onclick="toggleNewAddressForm()" aria-expanded="false" aria-controls="new-address-section">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                            <?= empty($addresses) ? 'Add Delivery Address' : 'Add a New Address' ?>
                        </button>
                    </div>

                    <!-- ── New Address Inline Form (hidden by default) ─────── -->
                    <div id="new-address-section" class="new-address-form" style="display:none;"
                         aria-hidden="true">
                        <h3 class="new-address-form__heading">New Delivery Address</h3>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="na-name" class="form-label">Recipient Name <span class="req">*</span></label>
                                <input type="text" id="na-name" name="recipient_name"
                                       class="form-input" placeholder="Full name of recipient"
                                       autocomplete="name">
                            </div>
                            <div class="form-group">
                                <label for="na-phone" class="form-label">Phone <span class="req">*</span></label>
                                <input type="tel" id="na-phone" name="phone"
                                       class="form-input" placeholder="Mobile number"
                                       autocomplete="tel">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="na-line1" class="form-label">Address Line 1 <span class="req">*</span></label>
                            <input type="text" id="na-line1" name="line1"
                                   class="form-input" placeholder="House / flat / building, street name"
                                   autocomplete="address-line1">
                        </div>

                        <div class="form-group">
                            <label for="na-line2" class="form-label">Address Line 2 <small>(optional)</small></label>
                            <input type="text" id="na-line2" name="line2"
                                   class="form-input" placeholder="Area, landmark, etc."
                                   autocomplete="address-line2">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="na-city" class="form-label">City <span class="req">*</span></label>
                                <input type="text" id="na-city" name="city"
                                       class="form-input" placeholder="City"
                                       autocomplete="address-level2">
                            </div>
                            <div class="form-group">
                                <label for="na-state" class="form-label">State / Emirate <span class="req">*</span></label>
                                <input type="text" id="na-state" name="state"
                                       class="form-input" placeholder="State or Emirate"
                                       autocomplete="address-level1">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="na-pincode" class="form-label">PIN / Postal Code <span class="req">*</span></label>
                                <input type="text" id="na-pincode" name="pincode"
                                       class="form-input" placeholder="6-digit PIN or UAE code"
                                       autocomplete="postal-code">
                            </div>
                            <div class="form-group">
                                <label for="na-country" class="form-label">Country <span class="req">*</span></label>
                                <select id="na-country" name="country" class="form-select" autocomplete="country">
                                    <option value="India">India</option>
                                    <option value="UAE">UAE (United Arab Emirates)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Hidden submit for "Save & Use" button -->
                        <div class="new-address-actions">
                            <button type="button" class="btn-secondary" onclick="cancelNewAddress()">Cancel</button>
                            <button type="button" class="btn-use-new-address" onclick="useNewAddress()">
                                Use This Address
                            </button>
                        </div>
                    </div>

                    <!-- ── 2. Customization / Special Instructions ─────────── -->
                    <section class="checkout-notes-section" aria-labelledby="notes-heading">
                        <h2 class="checkout-card__title" id="notes-heading" style="margin-top:2rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                                 fill="none" stroke="var(--color-maroon)" stroke-width="2" aria-hidden="true">
                                <path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
                            </svg>
                            Special Instructions / Customization Notes
                            <span class="optional-label">(optional)</span>
                        </h2>
                        <textarea
                            id="customization-notes"
                            name="customization_notes"
                            class="form-textarea"
                            rows="4"
                            maxlength="500"
                            placeholder="e.g. Custom embroidery name: Priya | Size: 3-4 years | Add frill at hem — customized orders take 5 working days."
                            aria-describedby="notes-hint"
                        ></textarea>
                        <p class="form-hint" id="notes-hint">
                            <span id="notes-char-count">0</span>/500 characters.
                            Customization adds ~5 working days to dispatch.
                        </p>
                    </section>

                    <!-- ── Place Order Button (mobile-visible here too) ──────── -->
                    <div class="checkout-submit-row">
                        <button type="submit" class="btn-place-order" id="place-order-btn">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"
                                 fill="currentColor" aria-hidden="true">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                                <path d="M12 0C5.373 0 0 5.373 0 12c0 2.127.558 4.117 1.532 5.84L.057 23.26a.75.75 0 0 0 .925.925l5.421-1.475A11.943 11.943 0 0 0 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-1.853 0-3.593-.5-5.093-1.37l-.364-.214-3.214.875.875-3.213-.214-.365A9.953 9.953 0 0 1 2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/>
                            </svg>
                            Place Order on WhatsApp
                        </button>
                        <p class="checkout-submit-note">
                            Your order will be shared on WhatsApp for payment confirmation.
                            Online payment only — No COD.
                        </p>
                    </div>

                </form>
            </section>

        </div><!-- /.checkout-left -->

        <!-- ── RIGHT COLUMN: Order Summary Sidebar ───────────────────────────── -->
        <aside class="checkout-right" aria-labelledby="summary-heading">
            <div class="checkout-card checkout-summary">
                <h2 class="checkout-card__title" id="summary-heading">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                         fill="none" stroke="var(--color-maroon)" stroke-width="2" aria-hidden="true">
                        <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/>
                        <line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/>
                        <line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>
                    </svg>
                    Order Summary
                    <span class="summary-item-count"><?= (int)$cartData['item_count'] ?> item<?= $cartData['item_count'] !== 1 ? 's' : '' ?></span>
                </h2>

                <!-- Items List -->
                <ul class="summary-items" aria-label="Order items">
                    <?php foreach ($cartData['items'] as $item): ?>
                        <li class="summary-item">
                            <div class="summary-item__image">
                                <?php if (!empty($item['primary_image'])): ?>
                                    <img src="<?= e(asset('/uploads/' . $item['primary_image'])) ?>"
                                         alt="<?= e($item['product_name']) ?>"
                                         width="52" height="52"
                                         loading="lazy">
                                <?php else: ?>
                                    <div class="summary-item__no-image" aria-hidden="true">👗</div>
                                <?php endif; ?>
                                <span class="summary-item__qty-badge" aria-label="Quantity: <?= (int)$item['quantity'] ?>">
                                    <?= (int)$item['quantity'] ?>
                                </span>
                            </div>
                            <div class="summary-item__details">
                                <div class="summary-item__name"><?= e($item['product_name']) ?></div>
                                <?php if (!empty($item['variant_info'])): ?>
                                    <div class="summary-item__variant"><?= e($item['variant_info']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="summary-item__price">
                                ₹<?= number_format((float)$item['line_total'], 2) ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="summary-divider"></div>

                <!-- Totals -->
                <div class="summary-totals">
                    <div class="summary-row">
                        <span>Subtotal</span>
                        <span>₹<?= number_format((float)$cartData['subtotal'], 2) ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Shipping</span>
                        <span class="shipping-label">
                            <?php
                            // Determine shipping display based on user's addresses or lack thereof
                            // We show 'Free' for India and a note for UAE
                            $hasUaeAddress = false;
                            foreach ($addresses as $a) {
                                if (strtolower($a['country']) === 'uae') {
                                    $hasUaeAddress = true;
                                    break;
                                }
                            }
                            ?>
                            🚚 Free for India
                        </span>
                    </div>
                    <div class="summary-shipping-note">
                        International shipping for UAE/Dubai is calculated on confirmation.
                    </div>
                </div>

                <div class="summary-divider"></div>

                <div class="summary-total-row">
                    <span class="summary-total-label">Estimated Total</span>
                    <span class="summary-total-amount">₹<?= number_format((float)$cartData['subtotal'], 2) ?></span>
                </div>

                <!-- Payment Note -->
                <div class="summary-payment-note">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                        <line x1="1" y1="10" x2="23" y2="10"/>
                    </svg>
                    Online Payment Only · No COD
                    <br>Pay via UPI after order placement.
                </div>

                <!-- CTA (desktop duplicate for sidebar convenience) -->
                <button type="submit" form="place-order-form" class="btn-place-order btn-place-order--sidebar">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                         fill="currentColor" aria-hidden="true">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                        <path d="M12 0C5.373 0 0 5.373 0 12c0 2.127.558 4.117 1.532 5.84L.057 23.26a.75.75 0 0 0 .925.925l5.421-1.475A11.943 11.943 0 0 0 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-1.853 0-3.593-.5-5.093-1.37l-.364-.214-3.214.875.875-3.213-.214-.365A9.953 9.953 0 0 1 2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/>
                    </svg>
                    Place Order on WhatsApp
                </button>
            </div>
        </aside>

    </div><!-- /.checkout-grid -->

</div><!-- /.container.checkout-layout -->

<!-- ── Add Address (Save to Account) — Separate form ──────────────────────── -->
<form method="POST" action="<?= e(url('/checkout.php')) ?>" id="save-address-form" style="display:none;" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_address">
</form>

<style>
/* ── Checkout Layout ──────────────────────────────────────────────────────── */
.checkout-layout    { padding-top: 2rem; padding-bottom: 4rem; }
.checkout-header    { margin-bottom: 2rem; }
.checkout-title     { font-family: var(--font-serif); font-size: 2rem; color: var(--color-maroon);
                       display: flex; align-items: center; gap: .6rem; }
.checkout-subtitle  { color: var(--color-dark-muted); font-size: .95rem; margin-top: .25rem; }

.checkout-grid { display: grid; grid-template-columns: 1fr 380px; gap: 2rem; align-items: start; }
@media (max-width: 860px) {
    .checkout-grid { grid-template-columns: 1fr; }
    .checkout-right { order: -1; }
}

/* ── Cards ─────────────────────────────────────────────────────────────────── */
.checkout-card          { background: #fff; border-radius: 16px; padding: 1.75rem;
                           box-shadow: 0 2px 16px rgba(122,31,61,.07); margin-bottom: 1.5rem; }
.checkout-card__title   { font-family: var(--font-serif); font-size: 1.15rem; color: var(--color-dark);
                           display: flex; align-items: center; gap: .5rem; margin-bottom: 1.25rem; }
.optional-label         { font-family: var(--font-body); font-size: .78rem; color: var(--color-dark-muted);
                           font-weight: 400; margin-left: .25rem; }

/* ── Address cards ──────────────────────────────────────────────────────────── */
.address-list           { display: flex; flex-direction: column; gap: .85rem; margin-bottom: 1rem; }
.address-card           { display: flex; align-items: flex-start; gap: .9rem; border: 2px solid #e8d9e2;
                           border-radius: 12px; padding: 1rem 1.1rem; cursor: pointer;
                           transition: border-color .2s, box-shadow .2s; position: relative; }
.address-card:hover     { border-color: var(--color-maroon); box-shadow: 0 0 0 3px rgba(122,31,61,.08); }
.address-card--selected { border-color: var(--color-maroon); background: rgba(122,31,61,.03); }
.address-card__radio    { position: absolute; opacity: 0; width: 0; height: 0; }
.address-card__body     { flex: 1; }
.address-card__name     { font-weight: 600; color: var(--color-dark); display: flex; align-items: center; gap: .5rem; }
.address-card__phone    { font-size: .88rem; color: var(--color-dark-muted); margin-top: .15rem; }
.address-card__address  { font-size: .88rem; color: var(--color-dark); margin-top: .3rem; line-height: 1.55; }
.address-card__check    { width: 24px; height: 24px; border-radius: 50%; background: var(--color-maroon);
                           color: #fff; display: none; align-items: center; justify-content: center;
                           flex-shrink: 0; }
.address-card--selected .address-card__check { display: flex; }
.badge-default          { font-size: .72rem; background: var(--color-gold); color: #fff;
                           border-radius: 20px; padding: .1rem .55rem; font-weight: 600; }
.checkout-no-address    { color: var(--color-dark-muted); font-size: .92rem; margin-bottom: 1rem; }

/* ── New address toggle button ─────────────────────────────────────────────── */
.new-address-toggle-wrap { margin-bottom: .5rem; }
.btn-add-address         { display: inline-flex; align-items: center; gap: .4rem;
                            background: none; border: 2px dashed var(--color-maroon);
                            color: var(--color-maroon); font-weight: 600; font-size: .9rem;
                            padding: .6rem 1.1rem; border-radius: 10px; cursor: pointer;
                            transition: background .2s; font-family: var(--font-body); }
.btn-add-address:hover   { background: rgba(122,31,61,.06); }

/* ── New address form ───────────────────────────────────────────────────────── */
.new-address-form          { background: var(--color-cream); border: 1.5px solid #e8d9e2;
                              border-radius: 12px; padding: 1.5rem; margin-top: 1rem; }
.new-address-form__heading { font-family: var(--font-serif); color: var(--color-maroon);
                              font-size: 1rem; margin-bottom: 1.1rem; }
.form-row  { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
@media (max-width: 540px) { .form-row { grid-template-columns: 1fr; } }
.form-group  { display: flex; flex-direction: column; gap: .35rem; margin-bottom: .9rem; }
.form-label  { font-size: .875rem; font-weight: 600; color: var(--color-dark); }
.form-label .req { color: var(--color-maroon); }
.form-input, .form-select, .form-textarea {
    border: 1.5px solid #d8c8d2; border-radius: 8px; padding: .6rem .85rem;
    font-size: .9rem; font-family: var(--font-body); color: var(--color-dark);
    background: #fff; transition: border-color .2s, box-shadow .2s; width: 100%; box-sizing: border-box;
}
.form-input:focus, .form-select:focus, .form-textarea:focus {
    outline: none; border-color: var(--color-maroon); box-shadow: 0 0 0 3px rgba(122,31,61,.1);
}
.form-textarea { resize: vertical; }
.form-hint     { font-size: .8rem; color: var(--color-dark-muted); margin-top: .3rem; }
.new-address-actions { display: flex; gap: .75rem; justify-content: flex-end; margin-top: .5rem; }
.btn-secondary { background: none; border: 1.5px solid #c0b0b8; color: var(--color-dark-muted);
                  border-radius: 8px; padding: .55rem 1.2rem; font-family: var(--font-body);
                  font-weight: 600; cursor: pointer; font-size: .88rem; }
.btn-use-new-address { background: var(--color-maroon); color: #fff; border: none; border-radius: 8px;
                        padding: .55rem 1.4rem; font-family: var(--font-body); font-weight: 600;
                        cursor: pointer; font-size: .88rem; }

/* ── Notes section ─────────────────────────────────────────────────────────── */
.checkout-notes-section { margin-top: .5rem; }

/* ── Submit ─────────────────────────────────────────────────────────────────── */
.checkout-submit-row     { margin-top: 1.75rem; }
.btn-place-order         { display: flex; align-items: center; justify-content: center; gap: .6rem;
                            width: 100%; background: var(--color-maroon); color: #fff;
                            border: none; border-radius: 50px; padding: .95rem 1.5rem;
                            font-family: var(--font-body); font-size: 1.05rem; font-weight: 700;
                            cursor: pointer; transition: background .2s, transform .15s; }
.btn-place-order:hover   { background: #5a1529; transform: translateY(-1px); }
.btn-place-order--sidebar { margin-top: 1rem; display: none; }
@media (max-width: 860px) { .btn-place-order--sidebar { display: flex; } }
.checkout-submit-note    { font-size: .8rem; color: var(--color-dark-muted); text-align: center;
                            margin-top: .6rem; }

/* ── Order Summary Sidebar ──────────────────────────────────────────────────── */
.checkout-summary          { position: sticky; top: 90px; }
.summary-item-count        { font-family: var(--font-body); font-size: .78rem; font-weight: 500;
                              color: var(--color-dark-muted); margin-left: auto; }
.summary-items             { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: .85rem; }
.summary-item              { display: flex; align-items: center; gap: .75rem; }
.summary-item__image       { position: relative; width: 52px; height: 52px; flex-shrink: 0; }
.summary-item__image img   { width: 52px; height: 52px; border-radius: 8px; object-fit: cover; }
.summary-item__no-image    { width: 52px; height: 52px; border-radius: 8px; background: #f5e8ef;
                              display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
.summary-item__qty-badge   { position: absolute; top: -6px; right: -6px; background: var(--color-maroon);
                              color: #fff; border-radius: 50%; width: 20px; height: 20px;
                              font-size: .7rem; font-weight: 700; display: flex;
                              align-items: center; justify-content: center; }
.summary-item__details     { flex: 1; min-width: 0; }
.summary-item__name        { font-size: .88rem; font-weight: 600; color: var(--color-dark);
                              white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.summary-item__variant     { font-size: .78rem; color: var(--color-dark-muted); margin-top: .1rem; }
.summary-item__price       { font-size: .9rem; font-weight: 700; color: var(--color-dark); flex-shrink: 0; }
.summary-divider           { border: none; border-top: 1.5px solid #f0e4ea; margin: 1rem 0; }
.summary-totals            { display: flex; flex-direction: column; gap: .5rem; }
.summary-row               { display: flex; justify-content: space-between; font-size: .9rem; color: var(--color-dark); }
.shipping-label            { color: #1a7d44; font-weight: 600; }
.summary-shipping-note     { font-size: .76rem; color: var(--color-dark-muted); margin-top: .25rem; text-align: right; }
.summary-total-row         { display: flex; justify-content: space-between; align-items: center; }
.summary-total-label       { font-weight: 700; font-size: 1rem; color: var(--color-dark); }
.summary-total-amount      { font-weight: 800; font-size: 1.3rem; color: var(--color-maroon); }
.summary-payment-note      { background: #fff8f0; border: 1px solid #f0d8b0; border-radius: 8px;
                              padding: .65rem .85rem; font-size: .8rem; color: #8a5a00;
                              margin-top: 1rem; display: flex; align-items: flex-start;
                              gap: .4rem; flex-wrap: wrap; line-height: 1.6; }
</style>

<script>
/* ── New Address Form Toggle ──────────────────────────────────────────────── */
function toggleNewAddressForm() {
    const section = document.getElementById('new-address-section');
    const btn     = document.getElementById('toggle-new-address-btn');
    const isHidden = section.style.display === 'none' || section.style.display === '';

    if (isHidden) {
        section.style.display = 'block';
        section.setAttribute('aria-hidden', 'false');
        btn.setAttribute('aria-expanded', 'true');
        // Deselect saved address radios when "new" is opened
        document.getElementById('address-source-input').value = 'new';
        document.querySelectorAll('.address-card').forEach(c => c.classList.remove('address-card--selected'));
        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } else {
        cancelNewAddress();
    }
}

function cancelNewAddress() {
    const section = document.getElementById('new-address-section');
    const btn     = document.getElementById('toggle-new-address-btn');
    section.style.display = 'none';
    section.setAttribute('aria-hidden', 'true');
    btn.setAttribute('aria-expanded', 'false');
    document.getElementById('address-source-input').value = 'saved';
    // Re-select first saved address if any
    const firstRadio = document.querySelector('.address-card__radio');
    if (firstRadio) {
        firstRadio.checked = true;
        firstRadio.closest('.address-card').classList.add('address-card--selected');
    }
}

function useNewAddress() {
    // Validate new address fields client-side before marking as source
    const required = ['na-name', 'na-phone', 'na-line1', 'na-city', 'na-state', 'na-pincode'];
    let valid = true;
    required.forEach(id => {
        const el = document.getElementById(id);
        if (!el || el.value.trim() === '') {
            el && (el.style.borderColor = 'var(--color-maroon)');
            valid = false;
        } else {
            el.style.borderColor = '';
        }
    });
    if (!valid) {
        alert('Please fill in all required address fields.');
        return;
    }
    document.getElementById('address-source-input').value = 'new';
    // Visual confirm
    document.getElementById('new-address-section').style.border = '2px solid var(--color-maroon)';
    document.querySelectorAll('.address-card').forEach(c => c.classList.remove('address-card--selected'));
    // Submit the main form
    document.getElementById('place-order-form').submit();
}

/** Highlight the selected address card */
function selectSavedAddress(radio) {
    document.querySelectorAll('.address-card').forEach(c => c.classList.remove('address-card--selected'));
    const card = radio.closest('.address-card');
    if (card) card.classList.add('address-card--selected');
    document.getElementById('address-source-input').value = 'saved';
    // Collapse new address form if open
    const section = document.getElementById('new-address-section');
    if (section.style.display !== 'none') cancelNewAddress();
}

/* ── Character counter for notes textarea ──────────────────────────────────── */
(function () {
    const textarea = document.getElementById('customization-notes');
    const counter  = document.getElementById('notes-char-count');
    if (textarea && counter) {
        textarea.addEventListener('input', () => {
            counter.textContent = textarea.value.length;
        });
    }
})();

/* ── Form submit guard ─────────────────────────────────────────────────────── */
document.getElementById('place-order-form').addEventListener('submit', function (e) {
    const source = document.getElementById('address-source-input').value;
    if (source === 'saved') {
        const checked = document.querySelector('input[name="address_id"]:checked');
        <?php if (!empty($addresses)): ?>
        if (!checked) {
            e.preventDefault();
            alert('Please select a delivery address before placing your order.');
        }
        <?php endif; ?>
    }
    // Disable button to prevent double-submit
    const btn = document.getElementById('place-order-btn');
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Placing Order…';
    }
});
</script>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
