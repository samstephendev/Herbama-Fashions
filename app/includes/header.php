<?php
/**
 * Site Header Include — Herbama Fashions
 * ========================================
 * Master layout header with announcement bar, sticky navigation, dropdowns,
 * search modal, and accessibility attributes. All asset and page URLs are
 * built dynamically from SITE_URL via url() and asset() helpers for seamless
 * root and subfolder compatibility (XAMPP / Hostinger / Render).
 */
declare(strict_types=1);

$settingModel = new SettingModel();
$announcementLeft = (string)$settingModel->get('announcement_bar_left', 'Pan-India Shipping | International Dispatch to UAE / Dubai');
$announcementRight = (string)$settingModel->get('announcement_bar_right', 'No COD | 0-3 Years: Ready to Dispatch | Customization: 5 Working Days');

// Current request URI for active link highlighting
$currentUri = $_SERVER['REQUEST_URI'] ?? '/';
$currentPage = basename(parse_url($currentUri, PHP_URL_PATH) ?: 'index.php');
$currentCat = $_GET['cat'] ?? '';

// Cart and Wishlist counts
$cartCount = 0;
$wishlistCount = 0;
if (Session::isLoggedIn()) {
    $cartModel = new CartModel();
    $cartCount = $cartModel->countItems((int)Session::userId());
    
    $prodModel = new ProductModel();
    $wishlistIds = $prodModel->getWishlistIds((int)Session::userId());
    $wishlistCount = count($wishlistIds);
} else {
    $cartCount = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
}

$pageTitle = isset($pageTitle) ? e($pageTitle) . ' — ' . SITE_NAME : SITE_NAME . ' | Premium Handcrafted Kidswear';
$pageDesc = isset($pageDesc) ? e($pageDesc) : 'Adorable handcrafted kidswear for girls, boys, and infants. Pan-India shipping and UAE/Dubai international dispatch.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?= $pageTitle ?></title>
    <meta name="description" content="<?= $pageDesc ?>">
    
    <!-- Open Graph tags for SEO -->
    <meta property="og:title" content="<?= $pageTitle ?>">
    <meta property="og:description" content="<?= $pageDesc ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= e(url($currentUri)) ?>">
    <meta property="og:image" content="<?= e(asset('/assets/images/icons/og-image.jpg')) ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">

    <!-- Favicon set -->
    <link rel="icon" type="image/png" sizes="16x16"  href="<?= e(asset('/assets/images/icons/favicon-16.png')) ?>">
    <link rel="icon" type="image/png" sizes="32x32"  href="<?= e(asset('/assets/images/icons/favicon-32.png')) ?>">
    <link rel="icon" type="image/png" sizes="48x48"  href="<?= e(asset('/assets/images/icons/favicon-48.png')) ?>">
    <link rel="apple-touch-icon" sizes="180x180"      href="<?= e(asset('/assets/images/icons/apple-touch-icon.png')) ?>">
    <link rel="icon" type="image/png" sizes="512x512" href="<?= e(asset('/assets/images/icons/favicon-512.png')) ?>">

    <!-- Google Fonts: Playfair Display + Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,500;1,600&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- CSS Design Tokens & Stylesheet -->
    <link rel="stylesheet" href="<?= e(asset('/assets/css/tokens.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('/assets/css/main.css')) ?>">

    <!-- Global JS Environment Context (supports subfolder XAMPP) -->
    <script>
        window.SITE_URL = '<?= e(rtrim(SITE_URL, '/')) ?>';
    </script>

    <!-- CSRF Meta for Vanilla JS fetch requests -->
    <meta name="csrf-token" content="<?= e(CSRF::token()) ?>">

    <?php if (isset($extraHead)) echo $extraHead; ?>
</head>
<body class="<?= isset($bodyClass) ? e($bodyClass) : '' ?>">

<!-- Skip Link for Accessibility -->
<a href="#main-content" class="skip-link">Skip to main content</a>

<!-- ── 1. Announcement Bar ─────────────────────────────────────────────── -->
<div class="announcement-bar" role="region" aria-label="Store Announcement">
    <div class="container announcement-bar__inner">
        <div class="announcement-bar__left">
            <span>🚚</span>
            <span><?= e($announcementLeft) ?></span>
        </div>
        <div class="announcement-bar__right">
            <span>✂️</span>
            <span><?= e($announcementRight) ?></span>
        </div>
    </div>
</div>

<!-- ── 2. Sticky Navbar ────────────────────────────────────────────────── -->
<div class="navbar-sticky-wrapper">
    <header class="container navbar" role="banner">
        <!-- Logo -->
        <a href="<?= e(url('/index.php')) ?>" class="brand-logo" aria-label="<?= SITE_NAME ?> — Home">
            <picture>
                <source srcset="<?= e(asset('/assets/images/brand/logo-128.webp')) ?> 2x,
                                <?= e(asset('/assets/images/brand/logo-64.webp')) ?> 1x"
                        type="image/webp">
                <img src="<?= e(asset('/assets/images/brand/logo-64.png')) ?>"
                     srcset="<?= e(asset('/assets/images/brand/logo-128.png')) ?> 2x"
                     alt="Herabama Fashions logo — round maroon and gold badge"
                     class="brand-logo__image"
                     width="56" height="56"
                     loading="eager">
            </picture>
            <div class="brand-logo__text-group">
                <span class="brand-logo__title">Herabama Fashions</span>
                <span class="brand-logo__tagline">Handcrafted Kidswear</span>
            </div>
        </a>

        <!-- Desktop Navigation Menu -->
        <nav class="nav-menu" id="primary-nav" aria-label="Primary Navigation">
            <div class="nav-item">
                <a href="<?= e(url('/index.php')) ?>" class="nav-link <?= ($currentPage === 'index.php' || $currentPage === '') ? 'is-active' : '' ?>">Home</a>
            </div>

            <!-- Girls Wear Dropdown -->
            <div class="nav-item">
                <a href="<?= e(url('/collection.php?cat=girls')) ?>" class="nav-link <?= ($currentCat === 'girls' || $currentCat === 'girls-wear') ? 'is-active' : '' ?>">
                    Girls Wear <span aria-hidden="true">▾</span>
                </a>
                <ul class="nav-dropdown" role="menu">
                    <li class="nav-dropdown-item"><a href="<?= e(url('/collection.php?cat=girls')) ?>">All Girls Wear</a></li>
                    <li class="nav-dropdown-item"><a href="<?= e(url('/collection.php?cat=girls&sub=girls-frocks')) ?>">Frocks &amp; Dresses</a></li>
                    <li class="nav-dropdown-item"><a href="<?= e(url('/collection.php?cat=girls&sub=girls-tops-bottoms')) ?>">Tops &amp; Bottoms</a></li>
                    <li class="nav-dropdown-item"><a href="<?= e(url('/collection.php?cat=girls&sub=girls-palazzo-sets')) ?>">Palazzo Sets</a></li>
                    <li class="nav-dropdown-item"><a href="<?= e(url('/collection.php?cat=girls&sub=girls-pattu-pavadai')) ?>">Pattu Pavadai</a></li>
                    <li class="nav-dropdown-item"><a href="<?= e(url('/collection.php?cat=girls&sub=girls-casual-wear')) ?>">Casual Wear</a></li>
                </ul>
            </div>

            <!-- Boys Wear Dropdown -->
            <div class="nav-item">
                <a href="<?= e(url('/collection.php?cat=boys')) ?>" class="nav-link <?= ($currentCat === 'boys' || $currentCat === 'boys-wear') ? 'is-active' : '' ?>">
                    Boys Wear <span aria-hidden="true">▾</span>
                </a>
                <ul class="nav-dropdown" role="menu">
                    <li class="nav-dropdown-item"><a href="<?= e(url('/collection.php?cat=boys')) ?>">All Boys Wear</a></li>
                    <li class="nav-dropdown-item"><a href="<?= e(url('/collection.php?cat=boys&sub=boys-tshirts')) ?>">T-Shirts &amp; Polos</a></li>
                    <li class="nav-dropdown-item"><a href="<?= e(url('/collection.php?cat=boys&sub=boys-shirts')) ?>">Casual &amp; Party Shirts</a></li>
                    <li class="nav-dropdown-item"><a href="<?= e(url('/collection.php?cat=boys&sub=boys-shorts')) ?>">Cargo &amp; Cotton Shorts</a></li>
                    <li class="nav-dropdown-item"><a href="<?= e(url('/collection.php?cat=boys&sub=boys-jeans')) ?>">Jeans &amp; Dungarees</a></li>
                    <li class="nav-dropdown-item"><a href="<?= e(url('/collection.php?cat=boys&sub=boys-casual-sets')) ?>">Casual Sets</a></li>
                    <li class="nav-dropdown-item"><a href="<?= e(url('/collection.php?cat=boys&sub=boys-ethnic-wear')) ?>">Ethnic Kurta Pyjama</a></li>
                </ul>
            </div>

            <!-- Infant Wear -->
            <div class="nav-item">
                <a href="<?= e(url('/collection.php?cat=infant')) ?>" class="nav-link <?= ($currentCat === 'infant' || $currentCat === 'infant-wear') ? 'is-active' : '' ?>">Infant Wear</a>
            </div>

            <!-- Festive Collection -->
            <div class="nav-item">
                <a href="<?= e(url('/collection.php?cat=festive')) ?>" class="nav-link <?= ($currentCat === 'festive' || $currentCat === 'festive-wear') ? 'is-active' : '' ?>">Festive Collection</a>
            </div>

            <!-- New Arrivals -->
            <div class="nav-item">
                <a href="<?= e(url('/new-arrivals.php')) ?>" class="nav-link <?= ($currentPage === 'new-arrivals.php') ? 'is-active' : '' ?>">New Arrivals</a>
            </div>

            <!-- Maroon Offers Pill Button -->
            <div class="nav-item">
                <a href="<?= e(url('/offers.php')) ?>" class="nav-pill-offers <?= ($currentPage === 'offers.php') ? 'is-active' : '' ?>">
                    <span>🏷️</span> Offers
                </a>
            </div>
        </nav>

        <!-- Navbar Actions (Search, Account, Wishlist, Cart) -->
        <div class="navbar-actions">
            <!-- Search Button -->
            <button type="button" class="nav-icon-btn" id="open-search-modal" aria-label="Search Kidswear">
                <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                </svg>
            </button>

            <!-- Account -->
            <?php if (Session::isLoggedIn()): ?>
                <a href="<?= e(url('/account/profile.php')) ?>" class="nav-icon-btn" aria-label="My Account" title="Hi, <?= e($_SESSION['user_name'] ?? 'User') ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                    </svg>
                </a>
            <?php else: ?>
                <a href="<?= e(url('/login.php')) ?>" class="nav-icon-btn" aria-label="Log In" title="Log in / Register">
                    <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                    </svg>
                </a>
            <?php endif; ?>

            <!-- Wishlist -->
            <a href="<?= e(url('/account/wishlist.php')) ?>" class="nav-icon-btn" aria-label="Wishlist">
                <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                </svg>
                <span class="nav-badge" id="wishlist-badge" style="<?= $wishlistCount > 0 ? '' : 'display:none;' ?>">
                    <?= $wishlistCount ?>
                </span>
            </a>

            <!-- Cart -->
            <a href="<?= e(url('/cart.php')) ?>" class="nav-icon-btn" aria-label="Shopping Cart">
                <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                </svg>
                <span class="nav-badge" id="cart-badge" style="<?= $cartCount > 0 ? '' : 'display:none;' ?>">
                    <?= $cartCount ?>
                </span>
            </a>

            <!-- Mobile Hamburger Toggle -->
            <button type="button" class="nav-mobile-toggle" id="mobile-menu-toggle" aria-label="Toggle navigation menu" aria-expanded="false">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </header>
</div>

<!-- ── 3. Live Search Modal Overlay ────────────────────────────────────── -->
<div class="search-modal-backdrop" id="search-modal" role="dialog" aria-modal="true" aria-label="Search Kidswear">
    <div class="search-modal-box">
        <form action="<?= e(url('/search.php')) ?>" method="GET" class="search-form-row">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--color-maroon)" stroke-width="2.2">
                <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
            </svg>
            <input type="search" 
                   name="q" 
                   id="search-live-input" 
                   class="search-input-field" 
                   placeholder="Search for frocks, kurta pyjama, lehengas, shorts..." 
                   autocomplete="off" 
                   required>
            <button type="button" class="search-close-btn" id="close-search-modal" aria-label="Close search">✕</button>
        </form>
        <div class="search-live-results" id="search-results-container">
            <p style="color:var(--color-dark-muted); font-size:0.875rem; padding-top:0.5rem;">
                Popular searches: 
                <a href="<?= e(url('/collection.php?cat=girls')) ?>" style="color:var(--color-maroon); font-weight:600;">Floral Frocks</a>, 
                <a href="<?= e(url('/collection.php?cat=festive')) ?>" style="color:var(--color-maroon); font-weight:600;">Lehenga Sets</a>, 
                <a href="<?= e(url('/collection.php?cat=boys')) ?>" style="color:var(--color-maroon); font-weight:600;">Dungarees</a>, 
                <a href="<?= e(url('/collection.php?cat=infant')) ?>" style="color:var(--color-maroon); font-weight:600;">Cotton Rompers</a>
            </p>
        </div>
    </div>
</div>

<!-- ── 4. Size Guide Modal (Global Accessible) ─────────────────────────── -->
<div class="modal-backdrop" id="size-guide-modal" role="dialog" aria-modal="true" aria-label="Herbama Fashions Size Guide">
    <div class="modal-box">
        <button type="button" class="modal-close" id="close-size-guide-modal" aria-label="Close size guide">✕</button>
        <h2 style="font-family:var(--font-serif); color:var(--color-maroon); margin-bottom:0.5rem;">Herbama Fashions — Kidswear Size Guide</h2>
        <p style="font-size:0.875rem; color:var(--color-dark-muted); margin-bottom:1.25rem;">
            All measurements are in inches. For custom tailoring (5 working days), contact us directly on WhatsApp!
        </p>
        <table class="size-table">
            <thead>
                <tr>
                    <th>Age Label</th>
                    <th>Chest (in)</th>
                    <th>Waist (in)</th>
                    <th>Length (in)</th>
                    <th>Height (cm)</th>
                </tr>
            </thead>
            <tbody>
                <tr><td>0-6 Months</td><td>18 - 19"</td><td>18"</td><td>14 - 15"</td><td>58 - 68 cm</td></tr>
                <tr><td>6-12 Months</td><td>19 - 20"</td><td>19"</td><td>16 - 17"</td><td>68 - 76 cm</td></tr>
                <tr><td>1-2 Years</td><td>20 - 21"</td><td>20"</td><td>18 - 20"</td><td>76 - 86 cm</td></tr>
                <tr><td>2-3 Years</td><td>22 - 23"</td><td>21"</td><td>21 - 23"</td><td>86 - 94 cm</td></tr>
                <tr><td>3-4 Years</td><td>23 - 24"</td><td>22"</td><td>24 - 26"</td><td>94 - 102 cm</td></tr>
                <tr><td>4-5 Years</td><td>24 - 25"</td><td>23"</td><td>27 - 29"</td><td>102 - 110 cm</td></tr>
                <tr><td>5-6 Years</td><td>25 - 26"</td><td>24"</td><td>30 - 32"</td><td>110 - 118 cm</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Main Content Anchor -->
<main id="main-content">
<?php render_flash(); ?>
