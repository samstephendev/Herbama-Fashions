<?php
/**
 * Heramba Fashion — Shopping Cart Page
 * ========================================
 * Displays cart items for both logged-in users (DB-backed cart via CartModel)
 * and guest users (session-backed cart). Quantity stepper and remove button
 * use AJAX to call /api/cart-update.php without full page reload.
 *
 * Security:
 *   - Prices are ALWAYS read from DB, never from POST or session.
 *   - CSRF token injected via <meta name="csrf-token"> from header.php.
 *   - Logged-in cart enforces user_id (IDOR-safe via CartModel).
 *
 * Layout:
 *   - Two-column desktop: cart table (left) + order summary sidebar (right).
 *   - Mobile: stacked single column.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config/bootstrap.php';

// ── Page Meta ─────────────────────────────────────────────────────────────────
$pageTitle = 'My Cart';
$bodyClass = 'page-cart';

// ── Build cart data ───────────────────────────────────────────────────────────
$items    = [];
$subtotal = 0.00;
$isGuest  = !Session::isLoggedIn();

if (!$isGuest) {
    // ── Logged-in: use DB cart via CartModel ──────────────────────────────────
    $cartModel = new CartModel();
    $cartData  = $cartModel->getCart((int) Session::userId());
    $items     = $cartData['items'];
    $subtotal  = $cartData['subtotal'];
} else {
    // ── Guest: build from $_SESSION['cart'] keyed as "productId_variantId" ───
    $sessionCart = (isset($_SESSION['cart']) && is_array($_SESSION['cart']))
        ? $_SESSION['cart']
        : [];

    if (!empty($sessionCart)) {
        $productModel = new ProductModel();

        foreach ($sessionCart as $key => $entry) {
            $productId = (int) ($entry['product_id'] ?? 0);
            $variantId = (int) ($entry['variant_id'] ?? 0);
            $qty       = max(1, (int) ($entry['quantity'] ?? 1));

            if ($productId <= 0 || $variantId <= 0) {
                continue;
            }

            // Fetch product + variant from DB (prices NEVER from session)
            $variantRow = $productModel->getVariantWithProduct($variantId);

            if (!$variantRow || (int) $variantRow['product_id'] !== $productId) {
                // Variant not found or mismatched — skip silently
                continue;
            }

            $unitPrice = (float) $variantRow['base_price'];
            $lineTotal = round($unitPrice * $qty, 2);
            $inStock   = (int) $variantRow['stock_qty'] > 0;

            // Build variant descriptor string (e.g. "Age: 1-2Y | Color: Blue")
            $variantParts = [];
            if (!empty($variantRow['age_label'])) {
                $variantParts[] = 'Age: ' . $variantRow['age_label'];
            }
            if (!empty($variantRow['color'])) {
                $variantParts[] = 'Color: ' . $variantRow['color'];
            }
            $variantInfo = implode(' | ', $variantParts);

            if ($inStock) {
                $subtotal += $lineTotal;
            }

            $items[] = [
                // Use the session key string as "cart_item_id" for guest AJAX calls
                'cart_item_id'  => $key,
                'product_id'    => $productId,
                'variant_id'    => $variantId,
                'product_name'  => $variantRow['product_name'],
                'product_slug'  => $variantRow['product_slug'],
                'variant_info'  => $variantInfo,
                'age_label'     => $variantRow['age_label'] ?? '',
                'color'         => $variantRow['color'] ?? '',
                'sku'           => $variantRow['sku'] ?? '',
                'unit_price'    => $unitPrice,
                'base_price'    => $unitPrice,
                'quantity'      => $qty,
                'stock_qty'     => (int) $variantRow['stock_qty'],
                'line_total'    => $lineTotal,
                'is_available'  => $inStock,
                'available'     => $inStock,
                'has_stock'     => (int) $variantRow['stock_qty'] >= $qty,
                'primary_image' => null, // Guests: no image pre-fetched (kept minimal)
            ];
        }
    }
}

$subtotal   = round($subtotal, 2);
$itemCount  = count($items);
$cartEmpty  = ($itemCount === 0);

// ── Checkout URL ──────────────────────────────────────────────────────────────
$checkoutUrl = $isGuest
    ? url('/login.php?next=' . urlencode('/checkout.php'))
    : url('/checkout.php');

// ── Render page ───────────────────────────────────────────────────────────────
require_once APP_ROOT . '/includes/header.php';
?>

<section class="cart-page-section">
    <div class="container">

        <!-- ── Page Heading ───────────────────────────────────────────────── -->
        <div class="cart-page-heading">
            <h1 class="cart-page-title">
                <?php if ($cartEmpty): ?>
                    Your Cart is Empty
                <?php else: ?>
                    Shopping Cart
                    <span class="cart-item-count-badge"><?= e($itemCount) ?> item<?= $itemCount !== 1 ? 's' : '' ?></span>
                <?php endif; ?>
            </h1>
            <a href="<?= e(url('/collection.php')) ?>" class="cart-continue-link">
                &larr; Continue Shopping
            </a>
        </div>

        <?php if ($cartEmpty): ?>
        <!-- ── Empty Cart State ───────────────────────────────────────────── -->
        <div class="cart-empty-state">
            <div class="cart-empty-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="72" height="72" viewBox="0 0 24 24"
                     fill="none" stroke="var(--color-maroon)" stroke-width="1.5" opacity="0.4">
                    <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                </svg>
            </div>
            <h2 class="cart-empty-title">Nothing here yet!</h2>
            <p class="cart-empty-desc">
                Browse our adorable kidswear collections and add something special for your little one.
            </p>
            <div class="cart-empty-actions">
                <a href="<?= e(url('/collection.php?cat=girls')) ?>" class="btn-pill btn-pill-primary">
                    Girls Wear
                </a>
                <a href="<?= e(url('/collection.php?cat=boys')) ?>" class="btn-pill btn-pill-secondary">
                    Boys Wear
                </a>
                <a href="<?= e(url('/new-arrivals.php')) ?>" class="btn-pill btn-pill-outline">
                    New Arrivals
                </a>
            </div>
        </div>

        <?php else: ?>
        <!-- ── Cart Layout: Table + Sidebar ──────────────────────────────── -->
        <div class="cart-layout">

            <!-- ── Cart Items Table ─────────────────────────────────────── -->
            <div class="cart-items-panel">
                <div class="cart-table-wrapper" role="region" aria-label="Cart Items">
                    <table class="cart-table" id="cart-items-table">
                        <thead class="cart-table-head">
                            <tr>
                                <th scope="col" class="cart-col-product">Product</th>
                                <th scope="col" class="cart-col-price">Price</th>
                                <th scope="col" class="cart-col-qty">Quantity</th>
                                <th scope="col" class="cart-col-total">Total</th>
                                <th scope="col" class="cart-col-remove">
                                    <span class="visually-hidden">Remove</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="cart-tbody">
                        <?php foreach ($items as $item): ?>
                            <?php
                            // Determine the placeholder or actual image URL
                            $imgSrc = !empty($item['primary_image'])
                                ? asset($item['primary_image'])
                                : asset('/assets/images/placeholder.svg');

                            // For DB cart rows, cart_item_id is an integer.
                            // For guest cart rows, it's the "pid_vid" session key string.
                            $cartItemId = e($item['cart_item_id']);
                            $qty        = (int) $item['quantity'];
                            $unitPrice  = (float) $item['base_price'];
                            $lineTotal  = round($unitPrice * $qty, 2);
                            $isAvail    = !empty($item['available']) || !empty($item['is_available']);
                            ?>
                            <tr class="cart-row <?= !$isAvail ? 'cart-row--unavailable' : '' ?>"
                                data-item-id="<?= $cartItemId ?>"
                                data-unit-price="<?= e(number_format($unitPrice, 2, '.', '')) ?>">

                                <!-- Product Info Cell -->
                                <td class="cart-cell cart-cell-product">
                                    <div class="cart-product-wrapper">
                                        <a href="<?= e(url('/product.php?slug=' . ($item['product_slug'] ?? ''))) ?>"
                                           class="cart-product-image-link"
                                           tabindex="-1" aria-hidden="true">
                                            <img src="<?= e($imgSrc) ?>"
                                                 alt="<?= e($item['product_name']) ?>"
                                                 class="cart-product-thumb"
                                                 width="80" height="80"
                                                 loading="lazy">
                                        </a>
                                        <div class="cart-product-info">
                                            <a href="<?= e(url('/product.php?slug=' . ($item['product_slug'] ?? ''))) ?>"
                                               class="cart-product-name">
                                                <?= e($item['product_name']) ?>
                                            </a>
                                            <?php if (!empty($item['variant_info'])): ?>
                                                <span class="cart-variant-info">
                                                    <?= e($item['variant_info']) ?>
                                                </span>
                                            <?php else: ?>
                                                <?php
                                                // Build variant info dynamically for DB cart rows
                                                $parts = [];
                                                if (!empty($item['age_label'])) {
                                                    $parts[] = 'Age: ' . $item['age_label'];
                                                }
                                                if (!empty($item['color'])) {
                                                    $parts[] = 'Color: ' . $item['color'];
                                                }
                                                if (!empty($parts)):
                                                ?>
                                                    <span class="cart-variant-info">
                                                        <?= e(implode(' | ', $parts)) ?>
                                                    </span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <?php if (!$isAvail): ?>
                                                <span class="cart-unavail-badge">Unavailable</span>
                                            <?php elseif (!$item['has_stock']): ?>
                                                <span class="cart-unavail-badge cart-unavail-badge--stock">
                                                    Low stock
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>

                                <!-- Unit Price Cell -->
                                <td class="cart-cell cart-cell-price" data-label="Price">
                                    <span class="cart-unit-price">
                                        <?= e(CURRENCY_SYMBOL) ?><?= e(number_format($unitPrice, 2)) ?>
                                    </span>
                                </td>

                                <!-- Quantity Stepper Cell -->
                                <td class="cart-cell cart-cell-qty" data-label="Quantity">
                                    <div class="qty-stepper" role="group" aria-label="Adjust quantity">
                                        <button type="button"
                                                class="qty-btn qty-btn--minus"
                                                aria-label="Decrease quantity"
                                                data-item-id="<?= $cartItemId ?>"
                                                <?= $qty <= 1 ? 'disabled' : '' ?>>
                                            &minus;
                                        </button>
                                        <span class="qty-display"
                                              id="qty-<?= $cartItemId ?>"
                                              aria-live="polite"
                                              aria-atomic="true">
                                            <?= e($qty) ?>
                                        </span>
                                        <button type="button"
                                                class="qty-btn qty-btn--plus"
                                                aria-label="Increase quantity"
                                                data-item-id="<?= $cartItemId ?>"
                                                data-max-stock="<?= e($item['stock_qty']) ?>"
                                                <?= $qty >= (int)$item['stock_qty'] ? 'disabled' : '' ?>>
                                            &plus;
                                        </button>
                                    </div>
                                </td>

                                <!-- Line Total Cell -->
                                <td class="cart-cell cart-cell-total" data-label="Total">
                                    <span class="cart-line-total" id="line-<?= $cartItemId ?>">
                                        <?= e(CURRENCY_SYMBOL) ?><?= e(number_format($lineTotal, 2)) ?>
                                    </span>
                                </td>

                                <!-- Remove Button Cell -->
                                <td class="cart-cell cart-cell-remove">
                                    <button type="button"
                                            class="cart-remove-btn"
                                            aria-label="Remove <?= e($item['product_name']) ?> from cart"
                                            data-item-id="<?= $cartItemId ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <line x1="18" y1="6" x2="6" y2="18"/>
                                            <line x1="6" y1="6" x2="18" y2="18"/>
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div><!-- /.cart-table-wrapper -->

                <!-- ── Trust Badges below cart table ──────────────────────── -->
                <div class="cart-trust-row" aria-label="Shopping Guarantees">
                    <div class="cart-trust-item">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                             fill="none" stroke="var(--color-maroon)" stroke-width="2" aria-hidden="true">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        </svg>
                        <span>Secure Checkout</span>
                    </div>
                    <div class="cart-trust-item">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                             fill="none" stroke="var(--color-maroon)" stroke-width="2" aria-hidden="true">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                        </svg>
                        <span>WhatsApp Support</span>
                    </div>
                    <div class="cart-trust-item">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                             fill="none" stroke="var(--color-maroon)" stroke-width="2" aria-hidden="true">
                            <rect x="1" y="3" width="15" height="13"/>
                            <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
                            <circle cx="5.5" cy="18.5" r="2.5"/>
                            <circle cx="18.5" cy="18.5" r="2.5"/>
                        </svg>
                        <span>Pan-India Shipping</span>
                    </div>
                </div><!-- /.cart-trust-row -->
            </div><!-- /.cart-items-panel -->

            <!-- ── Order Summary Sidebar ───────────────────────────────── -->
            <aside class="cart-summary-sidebar" aria-label="Order Summary">
                <div class="cart-summary-card">
                    <h2 class="cart-summary-title">Order Summary</h2>

                    <div class="cart-summary-row">
                        <span>Subtotal (<span id="summary-item-count"><?= e($itemCount) ?></span> item<?= $itemCount !== 1 ? 's' : '' ?>)</span>
                        <span class="cart-summary-amount" id="cart-subtotal">
                            <?= e(CURRENCY_SYMBOL) ?><?= e(number_format($subtotal, 2)) ?>
                        </span>
                    </div>

                    <div class="cart-summary-row cart-summary-row--shipping">
                        <span>Shipping</span>
                        <span class="cart-summary-shipping-note">Calculated at checkout</span>
                    </div>

                    <div class="cart-summary-divider"></div>

                    <div class="cart-summary-row cart-summary-row--total">
                        <span>Estimated Total</span>
                        <span class="cart-summary-grand-total" id="cart-grand-total">
                            <?= e(CURRENCY_SYMBOL) ?><?= e(number_format($subtotal, 2)) ?>
                        </span>
                    </div>

                    <!-- Proceed to Checkout CTA -->
                    <a href="<?= e($checkoutUrl) ?>"
                       class="btn-pill btn-pill-primary cart-checkout-btn"
                       id="proceed-checkout-btn">
                        <?php if ($isGuest): ?>
                            Login to Checkout
                        <?php else: ?>
                            Proceed to Checkout &rarr;
                        <?php endif; ?>
                    </a>

                    <?php if ($isGuest): ?>
                        <p class="cart-guest-note">
                            <a href="<?= e(url('/login.php?next=' . urlencode('/cart.php'))) ?>">Log in</a>
                            or
                            <a href="<?= e(url('/register.php')) ?>">create an account</a>
                            to save your cart and checkout.
                        </p>
                    <?php endif; ?>

                    <!-- UPI / Payment note -->
                    <p class="cart-payment-note">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                            <line x1="1" y1="10" x2="23" y2="10"/>
                        </svg>
                        Online payment only (UPI / Net Banking)
                    </p>
                </div>

                <!-- WhatsApp Help box -->
                <div class="cart-whatsapp-box">
                    <span class="cart-whatsapp-icon" aria-hidden="true">💬</span>
                    <div>
                        <strong>Need help?</strong>
                        <p>Chat with us on WhatsApp for size advice, customizations, or order queries.</p>
                        <a href="https://wa.me/<?= e(WHATSAPP_NUMBER) ?>"
                           target="_blank" rel="noopener noreferrer"
                           class="cart-whatsapp-link">
                            WhatsApp Us &rarr;
                        </a>
                    </div>
                </div>
            </aside><!-- /.cart-summary-sidebar -->

        </div><!-- /.cart-layout -->
        <?php endif; ?>

    </div><!-- /.container -->
</section><!-- /.cart-page-section -->

<!-- ══════════════════════════════════════════════════════════════════════════
     Cart Page Styles
════════════════════════════════════════════════════════════════════════════ -->
<style>
/* ── Layout ─────────────────────────────────────────────────────────────────── */
.cart-page-section {
    padding: 2.5rem 0 4rem;
    background: var(--color-cream, #FFF9F3);
    min-height: 60vh;
}
.cart-page-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 2rem;
    flex-wrap: wrap;
}
.cart-page-title {
    font-family: var(--font-serif, 'Playfair Display', serif);
    font-size: 1.75rem;
    color: var(--color-maroon, #7A1F3D);
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin: 0;
}
.cart-item-count-badge {
    font-family: var(--font-sans, 'Poppins', sans-serif);
    font-size: 0.8rem;
    font-weight: 600;
    background: var(--color-maroon, #7A1F3D);
    color: #fff;
    padding: 0.2rem 0.65rem;
    border-radius: 999px;
    vertical-align: middle;
}
.cart-continue-link {
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--color-maroon, #7A1F3D);
    text-decoration: none;
    transition: opacity 0.15s;
}
.cart-continue-link:hover { opacity: 0.75; }

/* ── Two-column cart layout ──────────────────────────────────────────────── */
.cart-layout {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 2rem;
    align-items: start;
}
@media (max-width: 900px) {
    .cart-layout { grid-template-columns: 1fr; }
}

/* ── Cart Table ─────────────────────────────────────────────────────────── */
.cart-items-panel {}
.cart-table-wrapper {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 2px 12px rgba(122,31,61,0.07);
    overflow: hidden;
    overflow-x: auto;
    margin-bottom: 1.5rem;
}
.cart-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 560px;
}
.cart-table-head {
    background: var(--color-maroon, #7A1F3D);
}
.cart-table-head th {
    color: #fff;
    font-family: var(--font-sans, 'Poppins', sans-serif);
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 0.9rem 1rem;
    text-align: left;
}
.cart-col-price,
.cart-col-qty,
.cart-col-total  { text-align: center; }
.cart-col-remove { text-align: right; width: 48px; }

/* ── Cart Rows ─────────────────────────────────────────────────────────── */
.cart-row {
    border-bottom: 1px solid rgba(122,31,61,0.07);
    transition: background 0.15s;
}
.cart-row:last-child { border-bottom: none; }
.cart-row:hover { background: rgba(201,162,75,0.04); }
.cart-row--unavailable { opacity: 0.55; }
.cart-cell {
    padding: 1.1rem 1rem;
    vertical-align: middle;
    font-family: var(--font-sans, 'Poppins', sans-serif);
    font-size: 0.9rem;
    color: var(--color-dark, #2B2B2B);
}
.cart-cell-price,
.cart-cell-qty,
.cart-cell-total { text-align: center; }
.cart-cell-remove { text-align: right; }

/* Product info */
.cart-product-wrapper {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
}
.cart-product-image-link { flex-shrink: 0; }
.cart-product-thumb {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 10px;
    border: 1px solid rgba(122,31,61,0.1);
    display: block;
}
.cart-product-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}
.cart-product-name {
    font-weight: 600;
    color: var(--color-dark, #2B2B2B);
    text-decoration: none;
    font-size: 0.9rem;
    line-height: 1.4;
}
.cart-product-name:hover { color: var(--color-maroon, #7A1F3D); }
.cart-variant-info {
    font-size: 0.775rem;
    color: #777;
    font-weight: 400;
}
.cart-unavail-badge {
    display: inline-block;
    font-size: 0.7rem;
    font-weight: 600;
    background: #fce8e8;
    color: #c0392b;
    padding: 0.1rem 0.5rem;
    border-radius: 999px;
}
.cart-unavail-badge--stock {
    background: #fff3cd;
    color: #856404;
}

/* Price / total */
.cart-unit-price {
    font-weight: 500;
    color: var(--color-dark, #2B2B2B);
}
.cart-line-total {
    font-weight: 700;
    color: var(--color-maroon, #7A1F3D);
}

/* ── Qty Stepper ────────────────────────────────────────────────────────── */
.qty-stepper {
    display: inline-flex;
    align-items: center;
    border: 1.5px solid rgba(122,31,61,0.2);
    border-radius: 999px;
    overflow: hidden;
    gap: 0;
}
.qty-btn {
    width: 32px;
    height: 32px;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 1.1rem;
    color: var(--color-maroon, #7A1F3D);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s;
    flex-shrink: 0;
}
.qty-btn:hover:not(:disabled) { background: rgba(122,31,61,0.07); }
.qty-btn:disabled { opacity: 0.35; cursor: default; }
.qty-display {
    min-width: 32px;
    text-align: center;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--color-dark, #2B2B2B);
    padding: 0 0.1rem;
}

/* ── Remove Button ─────────────────────────────────────────────────────── */
.cart-remove-btn {
    background: none;
    border: none;
    cursor: pointer;
    color: #aaa;
    padding: 0.4rem;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: color 0.15s, background 0.15s;
}
.cart-remove-btn:hover { color: #c0392b; background: #fce8e8; }

/* ── Cart Trust Badges ──────────────────────────────────────────────────── */
.cart-trust-row {
    display: flex;
    gap: 1.25rem;
    flex-wrap: wrap;
}
.cart-trust-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8rem;
    font-weight: 500;
    color: var(--color-dark, #2B2B2B);
    background: #fff;
    border-radius: 999px;
    padding: 0.45rem 0.9rem;
    box-shadow: 0 1px 6px rgba(122,31,61,0.08);
}

/* ── Order Summary Sidebar ──────────────────────────────────────────────── */
.cart-summary-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 2px 12px rgba(122,31,61,0.08);
    padding: 1.75rem;
    margin-bottom: 1.25rem;
}
.cart-summary-title {
    font-family: var(--font-serif, 'Playfair Display', serif);
    font-size: 1.25rem;
    color: var(--color-maroon, #7A1F3D);
    margin: 0 0 1.5rem;
}
.cart-summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.9rem;
    font-family: var(--font-sans, 'Poppins', sans-serif);
    color: var(--color-dark, #2B2B2B);
    margin-bottom: 0.85rem;
}
.cart-summary-row--shipping { color: #666; }
.cart-summary-shipping-note { font-size: 0.8rem; color: #888; }
.cart-summary-divider {
    height: 1px;
    background: rgba(122,31,61,0.1);
    margin: 0.75rem 0 1rem;
}
.cart-summary-row--total {
    font-weight: 700;
    font-size: 1rem;
    margin-bottom: 1.5rem;
}
.cart-summary-grand-total {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--color-maroon, #7A1F3D);
}
.cart-checkout-btn {
    display: block;
    text-align: center;
    width: 100%;
    padding: 0.9rem 1rem;
    font-size: 0.95rem;
    font-weight: 600;
    text-decoration: none;
    margin-bottom: 1rem;
    box-sizing: border-box;
}
.cart-guest-note {
    font-size: 0.8rem;
    color: #777;
    text-align: center;
    margin-bottom: 0.75rem;
}
.cart-guest-note a {
    color: var(--color-maroon, #7A1F3D);
    font-weight: 600;
}
.cart-payment-note {
    font-size: 0.775rem;
    color: #999;
    display: flex;
    align-items: center;
    gap: 0.35rem;
    margin: 0;
    justify-content: center;
}

/* ── WhatsApp help box ──────────────────────────────────────────────────── */
.cart-whatsapp-box {
    background: #e8f5e9;
    border-radius: 12px;
    padding: 1.1rem 1.25rem;
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}
.cart-whatsapp-icon { font-size: 1.5rem; flex-shrink: 0; }
.cart-whatsapp-box strong { font-size: 0.875rem; display: block; margin-bottom: 0.2rem; }
.cart-whatsapp-box p { font-size: 0.775rem; color: #555; margin: 0 0 0.5rem; }
.cart-whatsapp-link {
    font-size: 0.8rem;
    font-weight: 600;
    color: #25D366;
    text-decoration: none;
}
.cart-whatsapp-link:hover { text-decoration: underline; }

/* ── Empty state ────────────────────────────────────────────────────────── */
.cart-empty-state {
    text-align: center;
    padding: 4rem 1rem;
    max-width: 480px;
    margin: 0 auto;
}
.cart-empty-icon { margin-bottom: 1.5rem; }
.cart-empty-title {
    font-family: var(--font-serif, 'Playfair Display', serif);
    font-size: 1.5rem;
    color: var(--color-maroon, #7A1F3D);
    margin-bottom: 0.75rem;
}
.cart-empty-desc {
    font-size: 0.925rem;
    color: #666;
    margin-bottom: 2rem;
    line-height: 1.6;
}
.cart-empty-actions {
    display: flex;
    gap: 0.75rem;
    justify-content: center;
    flex-wrap: wrap;
}

/* Loading spinner overlay on stepper buttons */
.qty-btn--loading {
    pointer-events: none;
    opacity: 0.5;
}

/* Responsive data-labels for mobile table */
@media (max-width: 600px) {
    .cart-table-head { display: none; }
    .cart-table, .cart-table tbody, .cart-table tr, .cart-table td {
        display: block;
        width: 100%;
    }
    .cart-row { padding: 1rem; border-radius: 12px; margin-bottom: 0.75rem; background: #fff; }
    .cart-cell { padding: 0.35rem 0; display: flex; justify-content: space-between; align-items: center; }
    .cart-cell::before {
        content: attr(data-label);
        font-weight: 600;
        font-size: 0.775rem;
        color: #888;
        text-transform: uppercase;
    }
    .cart-cell-product { flex-direction: column; align-items: flex-start; }
    .cart-cell-product::before { content: ''; }
}
.visually-hidden {
    position: absolute;
    width: 1px;
    height: 1px;
    clip: rect(0,0,0,0);
    overflow: hidden;
    white-space: nowrap;
}
</style>

<!-- ══════════════════════════════════════════════════════════════════════════
     Cart Page JavaScript — AJAX qty stepper, remove, live totals
════════════════════════════════════════════════════════════════════════════ -->
<?php if (!$cartEmpty): ?>
<script>
(function () {
    'use strict';

    // ── Config ────────────────────────────────────────────────────────────
    const BASE_URL   = (window.SITE_URL || '').replace(/\/$/, '');
    const UPDATE_URL = BASE_URL + '/api/cart-update.php';
    const SYMBOL     = '<?= e(CURRENCY_SYMBOL) ?>';

    // ── Helpers ───────────────────────────────────────────────────────────

    /** Get the current CSRF token from the meta tag. */
    function csrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    }

    /** Update the CSRF meta tag after each request (token is rotated server-side). */
    function refreshCsrfToken(newToken) {
        if (newToken) {
            const meta = document.querySelector('meta[name="csrf-token"]');
            if (meta) meta.content = newToken;
        }
    }

    /** Format a number as a localised price string. */
    function fmtPrice(val) {
        return SYMBOL + parseFloat(val).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    /** Show the global toast notification. */
    function showToast(msg, isError = false) {
        const toast = document.getElementById('global-toast');
        if (!toast) return;
        const msgEl = toast.querySelector('.toast-msg');
        if (msgEl) msgEl.textContent = msg;
        toast.style.borderLeftColor = isError ? '#c0392b' : 'var(--color-gold, #C9A24B)';
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 3000);
    }

    /** Update the cart count badge in the header. */
    function updateCartBadge(count) {
        const badge = document.getElementById('cart-badge');
        if (!badge) return;
        if (count > 0) {
            badge.textContent = count;
            badge.style.display = '';
        } else {
            badge.style.display = 'none';
        }
    }

    /** Recompute the subtotal from all visible line totals. */
    function recomputeSubtotal() {
        let subtotal = 0;
        document.querySelectorAll('[id^="line-"]').forEach(el => {
            const val = parseFloat(el.dataset.rawTotal || '0');
            subtotal += val;
        });
        const subEl = document.getElementById('cart-subtotal');
        const grandEl = document.getElementById('cart-grand-total');
        if (subEl) subEl.textContent = fmtPrice(subtotal);
        if (grandEl) grandEl.textContent = fmtPrice(subtotal);
    }

    /**
     * Send an AJAX cart update request.
     * @param {string} action  'update' | 'remove'
     * @param {string|number} itemId   Cart item ID (int for logged-in, 'pid_vid' for guest)
     * @param {number|null} quantity   Required for action='update'
     * @returns {Promise<object>}
     */
    async function sendCartUpdate(action, itemId, quantity = null) {
        const body = new URLSearchParams({
            action       : action,
            cart_item_id : String(itemId),
            _csrf        : csrfToken(),
        });
        if (quantity !== null) {
            body.append('quantity', String(quantity));
        }

        const resp = await fetch(UPDATE_URL, {
            method  : 'POST',
            headers : { 'X-Requested-With': 'XMLHttpRequest' },
            body,
        });

        if (!resp.ok) {
            throw new Error('HTTP ' + resp.status);
        }
        return resp.json();
    }

    // ── Quantity Update ───────────────────────────────────────────────────

    async function handleQtyChange(itemId, delta) {
        const row      = document.querySelector(`.cart-row[data-item-id="${CSS.escape(String(itemId))}"]`);
        const qtyEl    = document.getElementById('qty-' + itemId);
        const lineEl   = document.getElementById('line-' + itemId);
        const minusBtn = row ? row.querySelector('.qty-btn--minus') : null;
        const plusBtn  = row ? row.querySelector('.qty-btn--plus') : null;

        if (!qtyEl || !row) return;

        const currentQty = parseInt(qtyEl.textContent, 10);
        const newQty     = currentQty + delta;
        const maxStock   = plusBtn ? parseInt(plusBtn.dataset.maxStock || '999', 10) : 999;

        if (newQty < 1 || newQty > maxStock) return;

        // Optimistic UI
        qtyEl.textContent = newQty;
        const unitPrice = parseFloat(row.dataset.unitPrice || '0');
        const newLineTotal = unitPrice * newQty;
        if (lineEl) {
            lineEl.textContent = fmtPrice(newLineTotal);
            lineEl.dataset.rawTotal = String(newLineTotal);
        }
        recomputeSubtotal();

        // Disable buttons during request
        if (minusBtn) minusBtn.classList.add('qty-btn--loading');
        if (plusBtn)  plusBtn.classList.add('qty-btn--loading');

        try {
            const data = await sendCartUpdate('update', itemId, newQty);
            refreshCsrfToken(data.csrf_token);

            if (!data.ok) {
                // Rollback optimistic UI
                qtyEl.textContent = currentQty;
                if (lineEl) {
                    lineEl.textContent = fmtPrice(unitPrice * currentQty);
                    lineEl.dataset.rawTotal = String(unitPrice * currentQty);
                }
                recomputeSubtotal();
                showToast(data.error || 'Could not update quantity.', true);
            } else {
                // Sync server-confirmed totals if returned
                if (data.line_total !== undefined && lineEl) {
                    lineEl.textContent = fmtPrice(data.line_total);
                    lineEl.dataset.rawTotal = String(data.line_total);
                }
                if (data.subtotal !== undefined) {
                    const subEl = document.getElementById('cart-subtotal');
                    const grandEl = document.getElementById('cart-grand-total');
                    if (subEl) subEl.textContent = fmtPrice(data.subtotal);
                    if (grandEl) grandEl.textContent = fmtPrice(data.subtotal);
                } else {
                    recomputeSubtotal();
                }
                updateCartBadge(data.cart_count || 0);
            }
        } catch (err) {
            // Network error — rollback
            qtyEl.textContent = currentQty;
            if (lineEl) {
                lineEl.textContent = fmtPrice(unitPrice * currentQty);
                lineEl.dataset.rawTotal = String(unitPrice * currentQty);
            }
            recomputeSubtotal();
            showToast('Network error. Please try again.', true);
        } finally {
            if (minusBtn) minusBtn.classList.remove('qty-btn--loading');
            if (plusBtn)  plusBtn.classList.remove('qty-btn--loading');

            // Update button disabled states
            const finalQty = parseInt(qtyEl.textContent, 10);
            if (minusBtn) minusBtn.disabled = finalQty <= 1;
            if (plusBtn)  plusBtn.disabled  = finalQty >= maxStock;
        }
    }

    // ── Remove Item ───────────────────────────────────────────────────────

    async function handleRemove(itemId) {
        const row = document.querySelector(`.cart-row[data-item-id="${CSS.escape(String(itemId))}"]`);
        if (!row) return;

        // Animate row out
        row.style.transition = 'opacity 0.25s, max-height 0.3s';
        row.style.opacity = '0.4';
        row.style.pointerEvents = 'none';

        try {
            const data = await sendCartUpdate('remove', itemId);
            refreshCsrfToken(data.csrf_token);

            if (data.ok) {
                // Remove line total contribution before deleting the row
                const lineEl = document.getElementById('line-' + itemId);
                if (lineEl) lineEl.dataset.rawTotal = '0';

                row.remove();
                recomputeSubtotal();
                updateCartBadge(data.cart_count || 0);

                // Check if cart is now empty
                const tbody = document.getElementById('cart-tbody');
                if (tbody && tbody.querySelectorAll('.cart-row').length === 0) {
                    location.reload(); // Show empty cart state
                }
                showToast('Item removed from cart.');
            } else {
                row.style.opacity = '';
                row.style.pointerEvents = '';
                showToast(data.error || 'Could not remove item.', true);
            }
        } catch (err) {
            row.style.opacity = '';
            row.style.pointerEvents = '';
            showToast('Network error. Please try again.', true);
        }
    }

    // ── Initialise raw totals on page load ────────────────────────────────
    // Store raw numeric totals in data attributes so recompute works correctly
    document.querySelectorAll('[id^="line-"]').forEach(el => {
        // Extract numeric value from the displayed text (e.g. "₹1,250.00" → 1250)
        const raw = el.textContent.replace(/[^\d.]/g, '');
        el.dataset.rawTotal = raw || '0';
    });

    // ── Event Delegation ─────────────────────────────────────────────────
    document.addEventListener('click', function (e) {
        // Quantity minus
        const minusBtn = e.target.closest('.qty-btn--minus');
        if (minusBtn) {
            const itemId = minusBtn.dataset.itemId;
            handleQtyChange(itemId, -1);
            return;
        }

        // Quantity plus
        const plusBtn = e.target.closest('.qty-btn--plus');
        if (plusBtn) {
            const itemId = plusBtn.dataset.itemId;
            handleQtyChange(itemId, +1);
            return;
        }

        // Remove button
        const removeBtn = e.target.closest('.cart-remove-btn');
        if (removeBtn) {
            const itemId = removeBtn.dataset.itemId;
            handleRemove(itemId);
            return;
        }
    });

})();
</script>
<?php endif; ?>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
