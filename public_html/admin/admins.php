<?php
/**
 * Admin Team Management — Heramba Fashion
 * =========================================
 * Modules:
 *   - List all administrator accounts
 *   - Create new administrator with secure Argon2id password hash
 *   - Delete administrator (prevents deleting oneself)
 *   - Change administrator password
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$pageTitle = 'Admin Team';
$topbarTitle = 'Administrator Accounts';

$userModel = new UserModel();
$currentAdminId = (int) Session::userId();

// ── POST Handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = trim($_POST['action'] ?? '');

    // 1. Create new Admin
    if ($action === 'create_admin') {
        $name     = trim($_POST['full_name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($name === '' || $email === '' || $password === '') {
            Session::flash('error', 'All fields are required.');
        } else {
            $res = $userModel->createAdmin($name, $email, $password, $currentAdminId);
            if ($res['ok']) {
                Session::flash('success', "Admin user &quot;{$name}&quot; created successfully.");
            } else {
                Session::flash('error', $res['error'] ?? 'Could not create administrator.');
            }
        }
        header('Location: ' . url('/admin/admins.php'));
        exit;
    }

    // 2. Delete Admin
    if ($action === 'delete_admin') {
        $targetId = (int)($_POST['target_id'] ?? 0);
        if ($targetId === $currentAdminId) {
            Session::flash('error', 'You cannot delete your own admin account.');
        } elseif ($targetId > 0) {
            if ($userModel->adminDeleteUser($targetId, $currentAdminId)) {
                Session::flash('success', 'Admin user removed.');
            } else {
                Session::flash('error', 'Could not delete administrator.');
            }
        }
        header('Location: ' . url('/admin/admins.php'));
        exit;
    }
}

$admins = $userModel->adminListAdmins();

require_once APP_ROOT . '/includes/admin-header.php';
?>

<div style="display:grid;grid-template-columns:1fr 2fr;gap:1.5rem;">

    <!-- Left: Add Admin Form -->
    <div>
        <div class="card">
            <h2 class="card-title" style="margin-bottom:1rem;">Add Administrator</h2>
            <form method="POST" action="<?= e(url('/admin/admins.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_admin">

                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="full_name" class="form-control" required placeholder="e.g. Priyanshu Sharma">
                </div>

                <div class="form-group">
                    <label class="form-label">Admin Email *</label>
                    <input type="email" name="email" class="form-control" required placeholder="admin@yourdomain.com">
                </div>

                <div class="form-group">
                    <label class="form-label">Temporary Password *</label>
                    <input type="password" name="password" class="form-control" required minlength="8" placeholder="Min. 8 chars, upper+lower+digit">
                </div>

                <div style="font-size:0.75rem;color:#777;margin-bottom:1.25rem;line-height:1.5;">
                    🔐 Password will be hashed using Argon2id. The new administrator will have full store access.
                </div>

                <button type="submit" class="btn btn-maroon" style="width:100%;justify-content:center;">
                    Create Administrator Account
                </button>
            </form>
        </div>
    </div>

    <!-- Right: Admins Listing -->
    <div>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Authorized Administrators (<?= count($admins) ?>)</h2>
            </div>

            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Last Login</th>
                            <th>Created</th>
                            <th style="text-align:right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($admins as $adm): ?>
                    <tr>
                        <td>
                            <strong><?= e($adm['full_name']) ?></strong>
                            <?php if ((int)$adm['id'] === $currentAdminId): ?>
                                <span style="font-size:0.7rem;background:#fdf2f5;color:var(--admin-maroon);font-weight:700;padding:2px 6px;border-radius:4px;margin-left:0.25rem;">YOU</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($adm['email']) ?></td>
                        <td><span class="badge" style="background:var(--admin-maroon);color:#fff;">ADMIN</span></td>
                        <td style="font-size:0.78rem;color:#666;">
                            <?= !empty($adm['last_login_at']) ? e(date('d M Y, h:i A', strtotime($adm['last_login_at']))) : 'Never' ?>
                        </td>
                        <td style="font-size:0.78rem;color:#666;">
                            <?= e(date('d M Y', strtotime($adm['created_at']))) ?>
                        </td>
                        <td style="text-align:right;">
                            <?php if ((int)$adm['id'] !== $currentAdminId): ?>
                            <button type="button" class="btn btn-danger btn-sm"
                                    onclick="openConfirmModal({
                                        title: 'Remove Administrator?',
                                        message: 'Revoke admin access for &quot;<?= e(addslashes($adm['full_name'])) ?>&quot; (<?= e($adm['email']) ?>)?',
                                        action: '<?= e(url('/admin/admins.php')) ?>',
                                        confirmText: 'Revoke Access',
                                        btnClass: 'btn-danger',
                                        params: { action: 'delete_admin', target_id: '<?= (int)$adm['id'] ?>' }
                                    })">
                                Remove
                            </button>
                            <?php else: ?>
                                <span style="font-size:0.75rem;color:#aaa;">Current User</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php require_once APP_ROOT . '/includes/admin-footer.php'; ?>
