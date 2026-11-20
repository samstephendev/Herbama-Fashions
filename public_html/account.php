<?php
/**
 * account.php — Customer Account Dashboard
 *
 * Herbama Fashions · Kidswear E-Commerce
 * ========================================
 * Tabs: Profile | My Addresses | Order History | Wishlist
 * All POST actions are CSRF-verified and IDOR-safe.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config/bootstrap.php';

// ── Auth Guard ───────────────────────────────────────────────────────────────
if (!Session::isLoggedIn()) {
    header('Location: ' . url('/login.php?next=' . urlencode('/account.php')));
    exit;
}

$userId    = (int) Session::userId();
$userModel = new UserModel();
$user      = $userModel->findById($userId);

if (!$user) {
    // Session stale — force logout
    Session::logout();
    header('Location: ' . url('/login.php'));
    exit;
}

// ════════════════════════════════════════════════════════════════════════════
// POST HANDLERS (all before any output)
// ════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = trim($_POST['action'] ?? '');

    // ── 1. Update Profile ────────────────────────────────────────────────────
    if ($action === 'update_profile') {
        $name  = trim($_POST['full_name'] ?? '');
        $phone = preg_replace('/\D/', '', $_POST['phone'] ?? '');

        $errors = [];
        if (strlen($name) < 2 || strlen($name) > 80) {
            $errors[] = 'Name must be between 2 and 80 characters.';
        }
        if ($phone !== '' && !preg_match('/^\d{10}$/', $phone)) {
            $errors[] = 'Phone must be exactly 10 digits.';
        }

        if ($errors) {
            Session::flash('error', implode(' ', $errors));
        } else {
            $result = $userModel->updateProfile($userId, $name, $phone ?: null);
            if ($result['ok']) {
                Session::flash('success', 'Profile updated successfully.');
                // Update session name
                $_SESSION['user_name'] = $name;
            } else {
                Session::flash('error', $result['error'] ?? 'Could not update profile.');
            }
        }
        header('Location: ' . url('/account.php?tab=profile'));
        exit;
    }

    // ── 2. Change Password ───────────────────────────────────────────────────
    if ($action === 'change_password') {
        $oldPass     = $_POST['current_password']  ?? '';
        $newPass     = $_POST['new_password']       ?? '';
        $confirmPass = $_POST['confirm_password']   ?? '';

        $errors = [];
        if (!$oldPass || !$newPass || !$confirmPass) {
            $errors[] = 'All password fields are required.';
        }
        if ($newPass !== $confirmPass) {
            $errors[] = 'New passwords do not match.';
        }
        if (!Sanitize::validatePassword($newPass)) {
            $errors[] = 'New password must be at least 8 characters with uppercase, lowercase and a digit.';
        }

        if ($errors) {
            Session::flash('error', implode(' ', $errors));
        } else {
            $result = $userModel->changePassword($userId, $oldPass, $newPass);
            if ($result['ok']) {
                Session::flash('success', 'Password changed successfully.');
            } else {
                Session::flash('error', $result['error'] ?? 'Could not change password.');
            }
        }
        header('Location: ' . url('/account.php?tab=profile'));
        exit;
    }

    // ── 3. Add Address ───────────────────────────────────────────────────────
    if ($action === 'add_address') {
        $country = trim($_POST['country'] ?? 'India');
        $isUae   = in_array(strtolower($country), ['uae', 'united arab emirates'], true);
        $data    = [
            'recipient_name' => trim($_POST['recipient_name'] ?? ''),
            'phone'          => preg_replace('/\D/', '', $_POST['phone'] ?? ''),
            'line1'          => trim($_POST['line1'] ?? ''),
            'line2'          => trim($_POST['line2'] ?? '') ?: null,
            'city'           => trim($_POST['city'] ?? ''),
            'state'          => trim($_POST['state'] ?? ''),
            'pincode'        => trim($_POST['pincode'] ?? ''),
            'country'        => $isUae ? 'UAE' : 'India',
            'is_default'     => !empty($_POST['is_default']) ? 1 : 0,
        ];

        $errors = [];
        if (strlen($data['recipient_name']) < 2) $errors[] = 'Recipient name is required.';
        if (!$data['line1'])                      $errors[] = 'Address line 1 is required.';
        if (!$data['city'])                       $errors[] = 'City is required.';
        if (!$data['state'])                      $errors[] = 'State/Emirate is required.';

        // Phone: India=10 digits, UAE=9 or 10 digits
        $phoneLen = strlen($data['phone']);
        if ($isUae) {
            if ($phoneLen < 9 || $phoneLen > 10) $errors[] = 'UAE phone must be 9 or 10 digits.';
        } else {
            if ($phoneLen !== 10) $errors[] = 'Phone must be exactly 10 digits.';
        }

        // Pincode: India=6 digits, UAE=4-9 alphanumeric
        if ($isUae) {
            if (!preg_match('/^[A-Za-z0-9]{3,9}$/', $data['pincode'])) $errors[] = 'Enter a valid UAE postal code.';
        } else {
            if (!preg_match('/^\d{6}$/', $data['pincode'])) $errors[] = 'Pincode must be exactly 6 digits.';
        }

        if ($errors) {
            Session::flash('error', implode(' ', $errors));
        } else {
            try {
                $userModel->addAddress($userId, $data);
                Session::flash('success', 'Address added successfully.');
            } catch (Exception $e) {
                error_log('[account] addAddress: ' . $e->getMessage());
                Session::flash('error', 'Could not save address. Please try again.');
            }
        }
        header('Location: ' . url('/account.php?tab=addresses'));
        exit;
    }

    // ── 4. Delete Address ─────────────────────────────────────────────────────
    if ($action === 'delete_address') {
        $addressId = (int) ($_POST['address_id'] ?? 0);
        if ($addressId > 0 && $userModel->deleteAddress($addressId, $userId)) {
            Session::flash('success', 'Address removed.');
        } else {
            Session::flash('error', 'Could not remove address.');
        }
        header('Location: ' . url('/account.php?tab=addresses'));
        exit;
    }

    // ── 5. Set Default Address ────────────────────────────────────────────────
    if ($action === 'set_default_address') {
        $addressId = (int) ($_POST['address_id'] ?? 0);
        if ($addressId > 0) {
            // Use updateAddress with is_default=1 to trigger the transaction-safe unset
            $addr = $userModel->getAddress($addressId, $userId);
            if ($addr) {
                $addr['is_default'] = 1;
                $userModel->updateAddress($addressId, $userId, $addr);
                Session::flash('success', 'Default address updated.');
            } else {
                Session::flash('error', 'Address not found.');
            }
        }
        header('Location: ' . url('/account.php?tab=addresses'));
        exit;
    }

    // ── 6. Remove from Wishlist ───────────────────────────────────────────────
    if ($action === 'remove_wishlist') {
        $productId   = (int) ($_POST['product_id'] ?? 0);
        $productModel = new ProductModel();
        if ($productId > 0) {
            $productModel->toggleWishlist($userId, $productId);
        }
        Session::flash('success', 'Removed from wishlist.');
        header('Location: ' . url('/account.php?tab=wishlist'));
        exit;
    }
}

// ── Active Tab ────────────────────────────────────────────────────────────────
$tab       = $_GET['tab'] ?? 'profile';
$validTabs = ['profile', 'addresses', 'orders', 'wishlist'];
if (!in_array($tab, $validTabs, true)) {
    $tab = 'profile';
}

// ── Load Tab-Specific Data ────────────────────────────────────────────────────
$addresses    = [];
$orders       = [];
$totalOrders  = 0;
$totalPages   = 1;
$orderPage    = max(1, (int) ($_GET['page'] ?? 1));
$wishlistItems = [];

$orderModel   = new OrderModel();
$productModel = new ProductModel();

if ($tab === 'addresses') {
    $addresses = $userModel->getAddresses($userId);
} elseif ($tab === 'orders') {
    $ordersData  = $orderModel->getUserOrders($userId, $orderPage, 10);
    $orders      = $ordersData['orders']      ?? [];
    $totalOrders = $ordersData['total']       ?? 0;
    $totalPages  = $ordersData['total_pages'] ?? 1;
} elseif ($tab === 'wishlist') {
    $wishlistItems = $productModel->getWishlist($userId);
}

// ── Status badge helper ───────────────────────────────────────────────────────
function orderStatusBadge(string $status): string
{
    $map = [
        'placed'          => ['#3498db', 'Placed'],
        'confirmed'       => ['#2980b9', 'Confirmed'],
        'payment_pending' => ['#e67e22', 'Payment Pending'],
        'paid'            => ['#27ae60', 'Paid'],
        'packed'          => ['#16a085', 'Packed'],
        'dispatched'      => ['#8e44ad', 'Dispatched'],
        'delivered'       => ['#196f3d', 'Delivered'],
        'cancelled'       => ['#c0392b', 'Cancelled'],
    ];
    [$color, $label] = $map[$status] ?? ['#888', ucfirst($status)];
    return sprintf(
        '<span class="status-badge" style="background:%s;color:#fff">%s</span>',
        htmlspecialchars($color, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
    );
}

function paymentBadge(string $status): string
{
    $map = [
        'unpaid'   => ['#e67e22', 'Unpaid'],
        'paid'     => ['#27ae60', 'Paid'],
        'refunded' => ['#3498db', 'Refunded'],
    ];
    [$color, $label] = $map[$status] ?? ['#888', ucfirst($status)];
    return sprintf(
        '<span class="status-badge" style="background:%s;color:#fff">%s</span>',
        htmlspecialchars($color, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
    );
}

// ── Page Setup ────────────────────────────────────────────────────────────────
$pageTitle = 'My Account';
$bodyClass = 'page-account';
$extraHead = '<style>
.account-wrapper{max-width:1100px;margin:0 auto;padding:2rem 1rem 4rem;}
.account-greeting{margin-bottom:1.5rem;}
.account-greeting h1{font-family:var(--font-heading);color:var(--color-maroon);font-size:1.8rem;margin:0 0 0.25rem;}
.account-greeting p{color:#888;margin:0;font-size:0.9rem;}
.account-tabs{display:flex;gap:0.4rem;margin-bottom:2rem;border-bottom:2px solid #f0eaee;padding-bottom:0;flex-wrap:wrap;}
.account-tabs a{padding:0.6rem 1.3rem;border-radius:50px 50px 0 0;text-decoration:none;color:var(--color-dark);font-weight:500;font-size:0.88rem;border:1.5px solid transparent;border-bottom:none;transition:all .2s;white-space:nowrap;}
.account-tabs a.active{background:var(--color-maroon);color:#fff;border-color:var(--color-maroon);}
.account-tabs a:not(.active):hover{color:var(--color-maroon);background:#fdf6fa;}
.acct-card{background:#fff;border-radius:14px;padding:1.75rem;box-shadow:0 2px 16px rgba(122,31,61,0.07);margin-bottom:1.5rem;}
.acct-card h3{color:var(--color-maroon);font-family:var(--font-heading);margin:0 0 1.25rem;font-size:1.1rem;padding-bottom:0.75rem;border-bottom:1px solid #f5eef3;}
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;}
@media(max-width:768px){.two-col{grid-template-columns:1fr;}}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}
@media(max-width:600px){.form-row{grid-template-columns:1fr;}}
.fg{margin-bottom:1rem;}
.fg label{display:block;font-weight:600;font-size:0.82rem;color:var(--color-dark);margin-bottom:0.35rem;text-transform:uppercase;letter-spacing:.03em;}
.fg input,.fg select,.fg textarea{width:100%;padding:0.6rem 0.85rem;border:1.5px solid #e0d6dc;border-radius:8px;font-size:0.9rem;outline:none;box-sizing:border-box;font-family:inherit;transition:border .2s;}
.fg input:focus,.fg select:focus,.fg textarea:focus{border-color:var(--color-maroon);}
.fg input[readonly]{background:#f9f5f7;color:#888;}
.fg textarea{resize:vertical;min-height:70px;}
.btn-primary{background:var(--color-maroon);color:#fff;border:none;padding:0.65rem 1.6rem;border-radius:50px;font-weight:600;cursor:pointer;font-size:0.9rem;transition:background .2s;}
.btn-primary:hover{background:#5a1630;}
.btn-secondary{background:#f5f5f5;color:var(--color-dark);border:1.5px solid #ddd;padding:0.5rem 1rem;border-radius:50px;font-size:0.82rem;cursor:pointer;transition:all .2s;}
.btn-secondary:hover{background:#eee;}
.btn-danger{background:#c0392b;color:#fff;border:none;padding:0.45rem 0.9rem;border-radius:50px;font-size:0.8rem;cursor:pointer;}
.btn-danger:hover{background:#a93226;}
.address-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:1rem;}
.addr-card{background:#fdf8fb;border:1.5px solid #e8dde5;border-radius:12px;padding:1.1rem 1.25rem;position:relative;}
.addr-card.is-default{border-color:var(--color-maroon);background:#fdf5f8;}
.addr-badge{display:inline-block;background:var(--color-maroon);color:#fff;font-size:0.7rem;padding:0.15rem 0.6rem;border-radius:50px;font-weight:700;margin-bottom:0.5rem;letter-spacing:.04em;}
.addr-name{font-weight:700;color:var(--color-dark);margin-bottom:0.15rem;}
.addr-line{font-size:0.85rem;color:#666;line-height:1.5;}
.addr-actions{margin-top:0.75rem;display:flex;gap:0.5rem;flex-wrap:wrap;}
.add-addr-toggle{margin-bottom:1rem;}
#add-addr-form{display:none;}
#add-addr-form.open{display:block;}
.order-table{width:100%;border-collapse:collapse;font-size:0.88rem;}
.order-table th{background:var(--color-maroon);color:#fff;padding:0.65rem 0.9rem;text-align:left;white-space:nowrap;}
.order-table td{padding:0.6rem 0.9rem;border-bottom:1px solid #f5eef3;vertical-align:middle;}
.order-table tr:last-child td{border-bottom:none;}
.order-table a{color:var(--color-maroon);text-decoration:none;font-weight:600;}
.status-badge{padding:0.22rem 0.65rem;border-radius:50px;font-size:0.76rem;font-weight:700;white-space:nowrap;}
.wishlist-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1.25rem;}
@media(max-width:768px){.wishlist-grid{grid-template-columns:repeat(2,1fr);}}
@media(max-width:480px){.wishlist-grid{grid-template-columns:1fr;}}
.wl-card{background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 10px rgba(122,31,61,0.07);border:1px solid #f0eaee;}
.wl-img{width:100%;aspect-ratio:3/4;object-fit:cover;display:block;}
.wl-info{padding:0.9rem;}
.wl-name{font-weight:600;font-size:0.88rem;color:var(--color-dark);margin-bottom:0.3rem;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
.wl-price{color:var(--color-maroon);font-weight:700;font-size:0.95rem;margin-bottom:0.75rem;}
.wl-actions{display:flex;gap:0.5rem;flex-wrap:wrap;}
.wl-actions a{flex:1;text-align:center;padding:0.45rem;background:var(--color-maroon);color:#fff;border-radius:50px;font-size:0.78rem;font-weight:600;text-decoration:none;}
.wl-actions button{flex:1;padding:0.45rem;background:#f5f5f5;border:1.5px solid #ddd;border-radius:50px;font-size:0.78rem;cursor:pointer;}
.alert{padding:0.75rem 1.1rem;border-radius:10px;margin-bottom:1.25rem;font-size:0.9rem;display:flex;align-items:center;gap:0.5rem;}
.alert-success{background:#d4edda;color:#155724;border:1px solid #c3e6cb;}
.alert-error{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;}
.empty-state{text-align:center;padding:3rem 1rem;color:#aaa;}
.empty-state svg{margin-bottom:1rem;opacity:.4;}
.empty-state p{margin:0 0 1rem;font-size:0.95rem;}
.pagination{display:flex;gap:0.5rem;justify-content:center;margin-top:1.5rem;flex-wrap:wrap;}
.pagination a,.pagination span{padding:0.4rem 0.9rem;border-radius:50px;font-size:0.85rem;text-decoration:none;border:1.5px solid #e0d6dc;}
.pagination a{color:var(--color-maroon);}
.pagination a:hover{background:var(--color-maroon);color:#fff;border-color:var(--color-maroon);}
.pagination span{background:var(--color-maroon);color:#fff;border-color:var(--color-maroon);}
.section-divider{border:none;border-top:2px solid #f5eef3;margin:1.5rem 0;}
.member-info{display:flex;gap:1.5rem;flex-wrap:wrap;margin-top:0.5rem;}
.member-info-item{font-size:0.85rem;color:#888;}
.member-info-item strong{color:var(--color-dark);}
.role-badge{display:inline-block;background:#f5eef3;color:var(--color-maroon);font-size:0.75rem;padding:0.15rem 0.6rem;border-radius:50px;font-weight:700;}
</style>';

require_once APP_ROOT . '/includes/header.php';
?>

<main id="main-content" class="account-wrapper">

    <!-- Page greeting -->
    <div class="account-greeting">
        <h1>Welcome, <?= e(explode(' ', $user['full_name'])[0]) ?>!</h1>
        <p><?= e($user['email']) ?> &nbsp;·&nbsp; <span class="role-badge"><?= e(ucfirst($user['role'])) ?></span></p>
    </div>

    <!-- Flash messages -->
    <?php foreach (Session::getFlash('success') as $msg): ?>
        <div class="alert alert-success">✓ <?= $msg ?></div>
    <?php endforeach; ?>
    <?php foreach (Session::getFlash('error') as $msg): ?>
        <div class="alert alert-error">✕ <?= $msg ?></div>
    <?php endforeach; ?>

    <!-- Tab navigation -->
    <nav class="account-tabs" aria-label="Account sections">
        <a href="<?= url('/account.php?tab=profile') ?>"    class="<?= $tab === 'profile'    ? 'active' : '' ?>">👤 Profile</a>
        <a href="<?= url('/account.php?tab=addresses') ?>"  class="<?= $tab === 'addresses'  ? 'active' : '' ?>">📍 My Addresses</a>
        <a href="<?= url('/account.php?tab=orders') ?>"     class="<?= $tab === 'orders'     ? 'active' : '' ?>">📦 Order History</a>
        <a href="<?= url('/account.php?tab=wishlist') ?>"   class="<?= $tab === 'wishlist'   ? 'active' : '' ?>">❤️ Wishlist</a>
    </nav>

    <!-- ════════════════════════════════════════════════════════════════════ -->
    <!-- PROFILE TAB                                                          -->
    <!-- ════════════════════════════════════════════════════════════════════ -->
    <?php if ($tab === 'profile'): ?>

    <div class="two-col">

        <!-- Personal Information -->
        <div class="acct-card">
            <h3>Personal Information</h3>
            <form method="POST" action="<?= url('/account.php?tab=profile') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_profile">
                <div class="fg">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" required
                           value="<?= e($user['full_name']) ?>" maxlength="80">
                </div>
                <div class="fg">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" value="<?= e($user['email']) ?>" readonly title="Email cannot be changed">
                </div>
                <div class="fg">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" maxlength="10"
                           value="<?= e($user['phone'] ?? '') ?>" placeholder="10-digit mobile number">
                </div>
                <button type="submit" class="btn-primary">Save Changes</button>
            </form>

            <hr class="section-divider">
            <div class="member-info">
                <div class="member-info-item">Member since <strong><?= e(date('d M Y', strtotime($user['created_at']))) ?></strong></div>
                <?php if ($user['last_login_at']): ?>
                <div class="member-info-item">Last login <strong><?= e(date('d M Y, H:i', strtotime($user['last_login_at']))) ?></strong></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Change Password -->
        <div class="acct-card">
            <h3>Change Password</h3>
            <form method="POST" action="<?= url('/account.php?tab=profile') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="change_password">
                <div class="fg">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
                </div>
                <div class="fg">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required minlength="8"
                           autocomplete="new-password" placeholder="Min. 8 chars, upper+lower+digit">
                </div>
                <div class="fg">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="8"
                           autocomplete="new-password">
                </div>
                <button type="submit" class="btn-primary">Update Password</button>
            </form>

            <hr class="section-divider">
            <p style="font-size:0.82rem;color:#aaa;margin:0;">
                Your password is securely hashed and never stored in plain text.<br>
                Choose a strong password with a mix of upper, lower, and digits.
            </p>
        </div>

    </div>

    <!-- ════════════════════════════════════════════════════════════════════ -->
    <!-- ADDRESSES TAB                                                        -->
    <!-- ════════════════════════════════════════════════════════════════════ -->
    <?php elseif ($tab === 'addresses'): ?>

    <div class="acct-card">
        <h3>My Delivery Addresses</h3>

        <?php if ($addresses): ?>
        <div class="address-grid">
            <?php foreach ($addresses as $addr): ?>
            <div class="addr-card <?= $addr['is_default'] ? 'is-default' : '' ?>">
                <?php if ($addr['is_default']): ?>
                    <span class="addr-badge">Default</span><br>
                <?php endif; ?>
                <div class="addr-name"><?= e($addr['recipient_name']) ?></div>
                <div class="addr-line">📞 <?= e($addr['phone']) ?></div>
                <div class="addr-line">
                    <?= e($addr['line1']) ?><?= $addr['line2'] ? ', ' . e($addr['line2']) : '' ?>,<br>
                    <?= e($addr['city']) ?>, <?= e($addr['state']) ?> — <?= e($addr['pincode']) ?><br>
                    <?= e($addr['country']) ?>
                </div>
                <div class="addr-actions">
                    <?php if (!$addr['is_default']): ?>
                    <form method="POST" action="<?= url('/account.php?tab=addresses') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="set_default_address">
                        <input type="hidden" name="address_id" value="<?= (int)$addr['id'] ?>">
                        <button type="submit" class="btn-secondary">Set Default</button>
                    </form>
                    <?php endif; ?>
                    <form method="POST" action="<?= url('/account.php?tab=addresses') ?>"
                          onsubmit="return confirm('Remove this address?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_address">
                        <input type="hidden" name="address_id" value="<?= (int)$addr['id'] ?>">
                        <button type="submit" class="btn-danger">Remove</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <p>No saved addresses yet.</p>
        </div>
        <?php endif; ?>

        <!-- Add new address toggle -->
        <button type="button" class="btn-secondary add-addr-toggle" style="margin-top:1.5rem;"
                onclick="document.getElementById('add-addr-form').classList.toggle('open');this.textContent=this.textContent.trim()==='+ Add New Address'?'− Cancel':'+ Add New Address';">
            + Add New Address
        </button>

        <div id="add-addr-form">
            <hr class="section-divider">
            <h4 style="color:var(--color-maroon);margin:0 0 1rem;font-family:var(--font-heading);">New Delivery Address</h4>
            <form method="POST" action="<?= url('/account.php?tab=addresses') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_address">
                <div class="form-row">
                    <div class="fg">
                        <label for="a_name">Recipient Name *</label>
                        <input type="text" id="a_name" name="recipient_name" required maxlength="100" placeholder="Full name">
                    </div>
                    <div class="fg">
                        <label for="a_phone">Phone Number *</label>
                        <input type="tel" id="a_phone" name="phone" required maxlength="15" placeholder="10-digit number">
                    </div>
                </div>
                <div class="fg">
                    <label for="a_line1">Address Line 1 *</label>
                    <input type="text" id="a_line1" name="line1" required maxlength="200" placeholder="Flat/House no., Street, Area">
                </div>
                <div class="fg">
                    <label for="a_line2">Address Line 2</label>
                    <input type="text" id="a_line2" name="line2" maxlength="200" placeholder="Landmark, Colony (optional)">
                </div>
                <div class="form-row">
                    <div class="fg">
                        <label for="a_city">City *</label>
                        <input type="text" id="a_city" name="city" required maxlength="80">
                    </div>
                    <div class="fg">
                        <label for="a_state">State / Emirate *</label>
                        <input type="text" id="a_state" name="state" required maxlength="80">
                    </div>
                </div>
                <div class="form-row">
                    <div class="fg">
                        <label for="a_pincode">Pincode / Postal Code *</label>
                        <input type="text" id="a_pincode" name="pincode" required maxlength="10">
                    </div>
                    <div class="fg">
                        <label for="a_country">Country *</label>
                        <select id="a_country" name="country">
                            <option value="India">India</option>
                            <option value="UAE">UAE (United Arab Emirates)</option>
                        </select>
                    </div>
                </div>
                <div class="fg" style="display:flex;align-items:center;gap:0.5rem;">
                    <input type="checkbox" id="a_default" name="is_default" value="1" style="width:auto;">
                    <label for="a_default" style="margin:0;text-transform:none;font-weight:500;font-size:0.88rem;">Set as default address</label>
                </div>
                <button type="submit" class="btn-primary">Save Address</button>
            </form>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════════════ -->
    <!-- ORDER HISTORY TAB                                                    -->
    <!-- ════════════════════════════════════════════════════════════════════ -->
    <?php elseif ($tab === 'orders'): ?>

    <div class="acct-card">
        <h3>Order History <?= $totalOrders > 0 ? '(' . $totalOrders . ' orders)' : '' ?></h3>

        <?php if ($orders): ?>
        <div style="overflow-x:auto;">
            <table class="order-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Date</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $ord): ?>
                    <tr>
                        <td><strong><?= e($ord['public_order_id']) ?></strong></td>
                        <td style="white-space:nowrap;"><?= e(date('d M Y', strtotime($ord['created_at']))) ?></td>
                        <td><?= (int)($ord['item_count'] ?? 1) ?> item<?= (int)($ord['item_count'] ?? 1) !== 1 ? 's' : '' ?></td>
                        <td style="white-space:nowrap;"><strong>₹<?= number_format((float)$ord['total'], 2) ?></strong></td>
                        <td><?= orderStatusBadge($ord['status']) ?></td>
                        <td><?= paymentBadge($ord['payment_status']) ?></td>
                        <td>
                            <a href="<?= url('/order-tracking.php?id=' . urlencode($ord['public_order_id'])) ?>">Track →</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($orderPage > 1): ?>
                <a href="<?= url('/account.php?tab=orders&page=' . ($orderPage - 1)) ?>">‹ Prev</a>
            <?php endif; ?>
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <?php if ($p === $orderPage): ?>
                    <span><?= $p ?></span>
                <?php else: ?>
                    <a href="<?= url('/account.php?tab=orders&page=' . $p) ?>"><?= $p ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($orderPage < $totalPages): ?>
                <a href="<?= url('/account.php?tab=orders&page=' . ($orderPage + 1)) ?>">Next ›</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="empty-state">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M20 7H4a2 2 0 00-2 2v10a2 2 0 002 2h16a2 2 0 002-2V9a2 2 0 00-2-2z"/>
                <path d="M16 3H8a2 2 0 00-2 4h12a2 2 0 00-2-4z"/>
            </svg>
            <p>You haven't placed any orders yet.</p>
            <a href="<?= url('/collection.php') ?>" class="btn-primary" style="display:inline-block;text-decoration:none;">Start Shopping</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- ════════════════════════════════════════════════════════════════════ -->
    <!-- WISHLIST TAB                                                         -->
    <!-- ════════════════════════════════════════════════════════════════════ -->
    <?php elseif ($tab === 'wishlist'): ?>

    <div class="acct-card">
        <h3>My Wishlist <?= count($wishlistItems) > 0 ? '(' . count($wishlistItems) . ' items)' : '' ?></h3>

        <?php if ($wishlistItems): ?>
        <div class="wishlist-grid">
            <?php foreach ($wishlistItems as $wl): ?>
            <div class="wl-card">
                <a href="<?= url('/product.php?slug=' . urlencode($wl['slug'])) ?>">
                    <img class="wl-img"
                         src="<?= $wl['primary_image'] ? e(asset('/uploads/' . $wl['primary_image'])) : e(asset('/assets/images/placeholder.svg')) ?>"
                         alt="<?= e($wl['name']) ?>"
                         onerror="this.src='<?= e(asset('/assets/images/placeholder.svg')) ?>'">
                </a>
                <div class="wl-info">
                    <div class="wl-name"><?= e($wl['name']) ?></div>
                    <div class="wl-price">₹<?= number_format((float)($wl['base_price'] ?? 0), 2) ?>
                        <?php if (!empty($wl['compare_price']) && $wl['compare_price'] > $wl['base_price']): ?>
                            <span style="text-decoration:line-through;color:#aaa;font-size:0.8rem;font-weight:400;">₹<?= number_format((float)$wl['compare_price'], 2) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="wl-actions">
                        <a href="<?= url('/product.php?slug=' . urlencode($wl['slug'])) ?>">View Product</a>
                        <form method="POST" action="<?= url('/account.php?tab=wishlist') ?>"
                              onsubmit="return confirm('Remove from wishlist?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="remove_wishlist">
                            <input type="hidden" name="product_id" value="<?= (int)$wl['id'] ?>">
                            <button type="submit" title="Remove from wishlist">🗑 Remove</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>
            </svg>
            <p>Your wishlist is empty.</p>
            <a href="<?= url('/collection.php') ?>" class="btn-primary" style="display:inline-block;text-decoration:none;">Browse Collection</a>
        </div>
        <?php endif; ?>
    </div>

    <?php endif; ?>

</main>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
