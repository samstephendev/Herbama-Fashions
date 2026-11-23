<?php
/**
 * Admin Layout Header — Heramba Fashion
 * ========================================
 * Role verification: role=admin checked on EVERY admin page.
 * Maroon (#7A1F3D) and gold (#C9A24B) theme, mobile-friendly responsive sidebar.
 */

declare(strict_types=1);

// Ensure bootstrap is loaded
if (!class_exists('Session')) {
    require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';
}

// ── Admin Security Guard (MANDATORY on every admin page) ─────────────────────
if (!Session::isAdmin()) {
    http_response_code(403);
    Session::flash('error', 'Administrator access required. Please sign in with an admin account.');
    $loginDest = url('/login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/admin/index.php'));
    header('Location: ' . $loginDest);
    exit;
}

$adminId    = (int) Session::userId();
$adminName  = (string) ($_SESSION['user_name'] ?? 'Admin');
$adminEmail = (string) ($_SESSION['user_email'] ?? '');

$currentUri  = $_SERVER['REQUEST_URI'] ?? '/admin/';
$currentPage = basename(parse_url($currentUri, PHP_URL_PATH) ?: 'index.php');

$pageTitle = isset($pageTitle) ? e($pageTitle) . ' — Heramba Admin' : 'Heramba Fashion — Admin Panel';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    
    <!-- Favicon set -->
    <link rel="icon" type="image/png" sizes="16x16"  href="<?= e(asset('/assets/images/icons/favicon-16.png')) ?>">
    <link rel="icon" type="image/png" sizes="32x32"  href="<?= e(asset('/assets/images/icons/favicon-32.png')) ?>">
    <link rel="icon" type="image/png" sizes="48x48"  href="<?= e(asset('/assets/images/icons/favicon-48.png')) ?>">
    <link rel="apple-touch-icon" sizes="180x180"      href="<?= e(asset('/assets/images/icons/apple-touch-icon.png')) ?>">
    <link rel="icon" type="image/png" sizes="512x512" href="<?= e(asset('/assets/images/icons/favicon-512.png')) ?>">
    <link rel="manifest" href="<?= e(asset('/manifest.json')) ?>">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS Design Tokens & Base -->
    <link rel="stylesheet" href="<?= e(asset('/assets/css/tokens.css')) ?>">
    
    <script>
        window.SITE_URL = '<?= e(rtrim(SITE_URL, '/')) ?>';
    </script>
    <meta name="csrf-token" content="<?= e(CSRF::token()) ?>">

    <style>
        :root {
            --admin-maroon: #7A1F3D;
            --admin-maroon-dark: #58142a;
            --admin-maroon-light: #9e2b52;
            --admin-gold: #C9A24B;
            --admin-gold-light: #e6c883;
            --admin-cream: #FAF6F0;
            --admin-dark: #2B2B2B;
            --admin-gray-bg: #F4EFEB;
            --admin-border: #E8D8DE;
            --admin-sidebar-w: 260px;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background: var(--admin-gray-bg);
            color: var(--admin-dark);
            min-height: 100vh;
            display: flex;
        }

        /* ── Sidebar ── */
        .admin-sidebar {
            width: var(--admin-sidebar-w);
            background: var(--admin-maroon);
            color: #fff;
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            transition: transform 0.25s ease;
            box-shadow: 2px 0 12px rgba(0,0,0,0.15);
        }

        .admin-brand {
            padding: 1.5rem 1.25rem;
            border-bottom: 1px solid rgba(255,255,255,0.12);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .admin-logo-circle {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: var(--admin-maroon-dark);
            border: 2px solid var(--admin-gold);
            color: var(--admin-gold);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Playfair Display', serif;
            font-weight: 700;
            font-size: 1.15rem;
            flex-shrink: 0;
        }
        .admin-brand-info h2 {
            margin: 0;
            font-size: 1.05rem;
            font-family: 'Playfair Display', serif;
            color: #fff;
            letter-spacing: 0.02em;
        }
        .admin-brand-info span {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--admin-gold-light);
            font-weight: 600;
        }

        .admin-nav {
            flex: 1;
            padding: 1rem 0;
            overflow-y: auto;
        }
        .admin-nav-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1.35rem;
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 500;
            border-left: 4px solid transparent;
            transition: all 0.2s ease;
        }
        .admin-nav-item:hover {
            background: rgba(255,255,255,0.08);
            color: #fff;
        }
        .admin-nav-item.active {
            background: rgba(201,162,75,0.15);
            color: var(--admin-gold);
            border-left-color: var(--admin-gold);
            font-weight: 600;
        }
        .admin-nav-icon {
            font-size: 1.15rem;
            width: 22px;
            text-align: center;
        }

        .admin-sidebar-footer {
            padding: 1rem 1.25rem;
            border-top: 1px solid rgba(255,255,255,0.12);
            font-size: 0.8rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .admin-sidebar-footer a {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0;
            transition: color 0.2s;
        }
        .admin-sidebar-footer a:hover {
            color: var(--admin-gold-light);
        }

        /* ── Main Container ── */
        .admin-main {
            margin-left: var(--admin-sidebar-w);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        /* ── Topbar ── */
        .admin-topbar {
            background: #fff;
            height: 64px;
            padding: 0 1.75rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--admin-border);
            box-shadow: 0 1px 4px rgba(0,0,0,0.03);
            position: sticky;
            top: 0;
            z-index: 90;
        }
        .topbar-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 1.4rem;
            color: var(--admin-maroon);
            cursor: pointer;
            padding: 0.25rem;
        }
        .topbar-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.35rem;
            color: var(--admin-maroon);
            margin: 0;
        }
        .topbar-right {
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }
        .admin-profile-pill {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--admin-dark);
            background: var(--admin-cream);
            padding: 0.4rem 0.9rem;
            border-radius: 50px;
            border: 1px solid var(--admin-border);
        }
        .admin-role-badge {
            background: var(--admin-maroon);
            color: #fff;
            font-size: 0.68rem;
            padding: 0.15rem 0.5rem;
            border-radius: 50px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* ── Content View ── */
        .admin-content {
            padding: 1.75rem;
            flex: 1;
        }

        /* ── Alerts ── */
        .alert {
            padding: 0.85rem 1.25rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            animation: fadeIn 0.3s ease;
        }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        /* ── Cards & Tables ── */
        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(122,31,61,0.05);
            border: 1px solid var(--admin-border);
            padding: 1.5rem;
            margin-bottom: 1.75rem;
        }
        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #f0eaee;
        }
        .card-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.2rem;
            color: var(--admin-maroon);
            margin: 0;
        }

        .table-responsive {
            overflow-x: auto;
            border-radius: 8px;
        }
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.88rem;
            text-align: left;
        }
        .admin-table th {
            background: #fbf6f8;
            color: var(--admin-maroon);
            font-weight: 600;
            padding: 0.75rem 0.9rem;
            border-bottom: 2px solid var(--admin-border);
            white-space: nowrap;
        }
        .admin-table td {
            padding: 0.75rem 0.9rem;
            border-bottom: 1px solid #f2e9ed;
            vertical-align: middle;
        }
        .admin-table tr:hover td {
            background: #fdfafc;
        }

        /* ── Action Buttons ── */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.5rem 1.15rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: 1.5px solid transparent;
            transition: all 0.2s;
        }
        .btn-sm {
            padding: 0.35rem 0.75rem;
            font-size: 0.78rem;
        }
        .btn-maroon {
            background: var(--admin-maroon);
            color: #fff;
        }
        .btn-maroon:hover {
            background: var(--admin-maroon-dark);
            color: #fff;
        }
        .btn-gold {
            background: var(--admin-gold);
            color: #fff;
        }
        .btn-gold:hover {
            background: #b58d37;
            color: #fff;
        }
        .btn-outline {
            background: #fff;
            color: var(--admin-maroon);
            border-color: var(--admin-maroon);
        }
        .btn-outline:hover {
            background: #fdf5f8;
        }
        .btn-danger {
            background: #c0392b;
            color: #fff;
        }
        .btn-danger:hover {
            background: #a93226;
        }
        .btn-success {
            background: #27ae60;
            color: #fff;
        }
        .btn-success:hover {
            background: #219653;
        }

        /* ── Badges ── */
        .badge {
            display: inline-block;
            padding: 0.22rem 0.65rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            white-space: nowrap;
        }
        .badge-placed          { background: #e8f4fd; color: #2980b9; }
        .badge-confirmed       { background: #ede8f8; color: #6c5ce7; }
        .badge-payment_pending { background: #fef5e7; color: #d35400; }
        .badge-paid            { background: #e8f8f0; color: #27ae60; }
        .badge-packed          { background: #e0f2f1; color: #00897b; }
        .badge-dispatched      { background: #f3e5f5; color: #8e24aa; }
        .badge-delivered       { background: #e8f5e9; color: #2e7d32; }
        .badge-cancelled       { background: #fbebee; color: #c0392b; }

        .badge-unpaid   { background: #fef5e7; color: #d35400; }
        .badge-refunded { background: #e8f4fd; color: #2980b9; }

        .badge-active   { background: #e8f8f0; color: #27ae60; }
        .badge-inactive { background: #f5f5f5; color: #888; }

        /* ── Form Controls ── */
        .form-group {
            margin-bottom: 1.15rem;
        }
        .form-label {
            display: block;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--admin-dark);
            margin-bottom: 0.4rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .form-control {
            width: 100%;
            padding: 0.6rem 0.85rem;
            border: 1.5px solid var(--admin-border);
            border-radius: 8px;
            font-size: 0.9rem;
            font-family: inherit;
            outline: none;
            transition: border 0.2s;
            background: #fff;
        }
        .form-control:focus {
            border-color: var(--admin-maroon);
        }
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
        }

        /* ── Modal Confirmation Dialog ── */
        .admin-modal-backdrop {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 200;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .admin-modal-backdrop.active {
            display: flex;
        }
        .admin-modal {
            background: #fff;
            border-radius: 14px;
            max-width: 440px;
            width: 100%;
            padding: 1.75rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            animation: zoomIn 0.2s ease;
        }
        .admin-modal h3 {
            margin: 0 0 0.75rem;
            font-family: 'Playfair Display', serif;
            color: var(--admin-maroon);
            font-size: 1.25rem;
        }
        .admin-modal p {
            margin: 0 0 1.5rem;
            font-size: 0.9rem;
            color: #666;
            line-height: 1.5;
        }
        .admin-modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }

        /* ── Responsive ── */
        @media (max-width: 900px) {
            .admin-sidebar {
                transform: translateX(-100%);
            }
            .admin-sidebar.open {
                transform: translateX(0);
            }
            .admin-main {
                margin-left: 0;
            }
            .mobile-menu-btn {
                display: block;
            }
        }
    </style>
    <?php if (isset($extraHead)) echo $extraHead; ?>
</head>
<body>

<!-- Sidebar Navigation -->
<aside class="admin-sidebar" id="adminSidebar">
    <div class="admin-brand">
        <picture>
            <source srcset="<?= e(asset('/assets/images/brand/logo-128.webp')) ?> 2x,
                            <?= e(asset('/assets/images/brand/logo-64.webp')) ?> 1x"
                    type="image/webp">
            <img src="<?= e(asset('/assets/images/brand/logo-64.png')) ?>"
                 srcset="<?= e(asset('/assets/images/brand/logo-128.png')) ?> 2x"
                 alt="Heramba Fashion logo"
                 width="44" height="44"
                 loading="eager"
                 style="border-radius:50%;flex-shrink:0;">
        </picture>
        <div class="admin-brand-info">
            <h2>Heramba Fashion</h2>
            <span>Admin Control Panel</span>
        </div>
    </div>

    <nav class="admin-nav">
        <a href="<?= e(url('/admin/index.php')) ?>" class="admin-nav-item <?= $currentPage === 'index.php' ? 'active' : '' ?>">
            <span class="admin-nav-icon">📊</span> Dashboard
        </a>
        <a href="<?= e(url('/admin/orders.php')) ?>" class="admin-nav-item <?= in_array($currentPage, ['orders.php', 'order-detail.php'], true) ? 'active' : '' ?>">
            <span class="admin-nav-icon">📋</span> Orders
        </a>
        <a href="<?= e(url('/admin/products.php')) ?>" class="admin-nav-item <?= in_array($currentPage, ['products.php', 'product-form.php'], true) ? 'active' : '' ?>">
            <span class="admin-nav-icon">🛍️</span> Products
        </a>
        <a href="<?= e(url('/admin/stock.php')) ?>" class="admin-nav-item <?= $currentPage === 'stock.php' ? 'active' : '' ?>">
            <span class="admin-nav-icon">📦</span> Stock & Inventory
        </a>
        <a href="<?= e(url('/admin/customers.php')) ?>" class="admin-nav-item <?= in_array($currentPage, ['customers.php', 'customer-detail.php'], true) ? 'active' : '' ?>">
            <span class="admin-nav-icon">👥</span> Customers
        </a>
        <a href="<?= e(url('/admin/offers.php')) ?>" class="admin-nav-item <?= $currentPage === 'offers.php' ? 'active' : '' ?>">
            <span class="admin-nav-icon">🏷️</span> Discount Offers
        </a>
        <a href="<?= e(url('/admin/banners.php')) ?>" class="admin-nav-item <?= $currentPage === 'banners.php' ? 'active' : '' ?>">
            <span class="admin-nav-icon">🖼️</span> Banners & Slides
        </a>
        <a href="<?= e(url('/admin/settings.php')) ?>" class="admin-nav-item <?= $currentPage === 'settings.php' ? 'active' : '' ?>">
            <span class="admin-nav-icon">⚙️</span> Store Settings
        </a>
        <a href="<?= e(url('/admin/audit-log.php')) ?>" class="admin-nav-item <?= $currentPage === 'audit-log.php' ? 'active' : '' ?>">
            <span class="admin-nav-icon">📜</span> Audit Logs
        </a>
        <a href="<?= e(url('/admin/admins.php')) ?>" class="admin-nav-item <?= $currentPage === 'admins.php' ? 'active' : '' ?>">
            <span class="admin-nav-icon">🛡️</span> Admin Team
        </a>
    </nav>

    <div class="admin-sidebar-footer">
        <a href="<?= e(url('/index.php')) ?>" target="_blank" rel="noopener">
            <span>🌐</span> Visit Store Front ↗
        </a>
        <a href="<?= e(url('/logout.php')) ?>">
            <span>🚪</span> Sign Out
        </a>
    </div>
</aside>

<!-- Main Wrapper -->
<div class="admin-main">
    <header class="admin-topbar">
        <div class="topbar-left">
            <button type="button" class="mobile-menu-btn" onclick="toggleSidebar()" aria-label="Toggle Navigation">☰</button>
            <h1 class="topbar-title"><?= isset($topbarTitle) ? e($topbarTitle) : 'Admin Dashboard' ?></h1>
        </div>
        <div class="topbar-right">
            <div class="admin-profile-pill">
                <span>👤</span>
                <span><?= e($adminName) ?></span>
                <span class="admin-role-badge">Admin</span>
            </div>
        </div>
    </header>

    <div class="admin-content">
        <!-- Placeholder Business Settings Alert Banner -->
        <?php
        $activeWa = WHATSAPP_NUMBER;
        $activeUpi = UPI_ID;
        try {
            if (class_exists('SettingModel')) {
                $stModel = new SettingModel();
                $activeWa = (string)$stModel->get('whatsapp_number', WHATSAPP_NUMBER);
                $activeUpi = (string)$stModel->get('upi_id', UPI_ID);
            }
        } catch (\Throwable $e) {}

        $isPlaceholderWa = (str_contains($activeWa, 'X') || str_contains($activeWa, '9876543210') || empty($activeWa));
        $isPlaceholderUpi = (str_contains(strtolower($activeUpi), 'yourname') || empty($activeUpi));
        ?>
        <?php if ($isPlaceholderWa || $isPlaceholderUpi): ?>
        <div style="background:#FFF3CD;border:1.5px solid #FFEEBA;color:#856404;padding:0.9rem 1.25rem;border-radius:10px;margin-bottom:1.5rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;box-shadow:0 2px 6px rgba(0,0,0,0.03);">
            <div style="display:flex;align-items:center;gap:0.6rem;">
                <span style="font-size:1.2rem;">⚠️</span>
                <div style="font-size:0.88rem;line-height:1.4;">
                    <strong>Default Placeholders Active:</strong>
                    <?php if ($isPlaceholderWa && $isPlaceholderUpi): ?>
                        WhatsApp (<code><?= e($activeWa) ?></code>) and UPI ID (<code><?= e($activeUpi) ?></code>) are currently using placeholder sample values.
                    <?php elseif ($isPlaceholderWa): ?>
                        WhatsApp number (<code><?= e($activeWa) ?></code>) is currently using a placeholder sample value.
                    <?php else: ?>
                        UPI ID (<code><?= e($activeUpi) ?></code>) is currently using a placeholder sample value.
                    <?php endif; ?>
                    Please update them with your real business details before accepting customer orders.
                </div>
            </div>
            <div>
                <a href="<?= e(url('/admin/settings.php')) ?>" class="btn btn-sm btn-outline" style="border-color:#856404;color:#856404;font-weight:600;white-space:nowrap;">
                    Configure Settings →
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Flash Notifications -->
        <?php foreach (Session::getFlash('success') as $msg): ?>
            <div class="alert alert-success">✓ <?= $msg ?></div>
        <?php endforeach; ?>
        <?php foreach (Session::getFlash('error') as $msg): ?>
            <div class="alert alert-error">✕ <?= $msg ?></div>
        <?php endforeach; ?>
