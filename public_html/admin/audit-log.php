<?php
/**
 * Admin Audit Log Viewer — Herbama Fashions
 * ==========================================
 * Modules:
 *   - Immutable log of all administrative actions
 *   - Search and filter by action type or date
 *   - Formatted JSON details and IP tracking
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$pageTitle = 'Audit Logs';
$topbarTitle = 'Security & Audit Logs';

$db = db();

// ── Filters & Querying ────────────────────────────────────────────────────────
$actionFilter = trim($_GET['action_type'] ?? '');
$search       = trim($_GET['q'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$limit        = 30;
$offset       = ($page - 1) * $limit;

$where  = ['1=1'];
$params = [];

if ($actionFilter !== '') {
    $where[] = 'al.action = :action';
    $params[':action'] = $actionFilter;
}

if ($search !== '') {
    $where[] = '(al.action LIKE :q OR al.entity LIKE :q2 OR u.full_name LIKE :q3 OR al.details LIKE :q4)';
    $like = '%' . $search . '%';
    $params[':q']  = $like;
    $params[':q2'] = $like;
    $params[':q3'] = $like;
    $params[':q4'] = $like;
}

$whereClause = implode(' AND ', $where);

// Total count
$countStmt = $db->prepare("SELECT COUNT(*) FROM audit_log al LEFT JOIN users u ON al.admin_id = u.id WHERE {$whereClause}");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = (int)ceil($total / $limit);

// Fetch logs
$stmt = $db->prepare(
    "SELECT al.*, u.full_name AS admin_name, u.email AS admin_email
     FROM audit_log al
     LEFT JOIN users u ON al.admin_id = u.id
     WHERE {$whereClause}
     ORDER BY al.created_at DESC
     LIMIT :limit OFFSET :offset"
);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit',  $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

// Distinct action list for dropdown
$actionsList = $db->query('SELECT DISTINCT action FROM audit_log ORDER BY action ASC')->fetchAll(PDO::FETCH_COLUMN);

require_once APP_ROOT . '/includes/admin-header.php';
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">System Action Log (<?= $total ?> entries)</h2>
    </div>

    <!-- Filter Form -->
    <form method="GET" action="<?= e(url('/admin/audit-log.php')) ?>" style="margin-bottom:1.5rem;display:flex;gap:0.75rem;flex-wrap:wrap;">
        <input type="text" name="q" class="form-control" placeholder="Search keyword or details..." 
               value="<?= e($search) ?>" style="max-width:280px;">

        <select name="action_type" class="form-control" style="max-width:240px;">
            <option value="">All Action Types</option>
            <?php foreach ($actionsList as $act): ?>
                <option value="<?= e($act) ?>" <?= $actionFilter === $act ? 'selected' : '' ?>><?= e($act) ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn btn-maroon btn-sm">Filter Logs</button>
        <?php if ($search !== '' || $actionFilter !== ''): ?>
            <a href="<?= e(url('/admin/audit-log.php')) ?>" class="btn btn-outline btn-sm">Reset</a>
        <?php endif; ?>
    </form>

    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Action</th>
                    <th>Admin User</th>
                    <th>Target Entity</th>
                    <th>Context Details</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="6" style="text-align:center;color:#888;padding:2.5rem;">
                        No audit log entries recorded yet.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td style="font-size:0.8rem;color:#666;white-space:nowrap;">
                        <?= e(date('d M Y, h:i:s A', strtotime($log['created_at']))) ?>
                    </td>
                    <td>
                        <span class="badge" style="background:#f4edf1;color:var(--admin-maroon);font-family:monospace;font-size:0.75rem;">
                            <?= e($log['action']) ?>
                        </span>
                    </td>
                    <td>
                        <strong><?= e($log['admin_name'] ?? 'System / Anonymous') ?></strong>
                        <?php if (!empty($log['admin_email'])): ?>
                            <div style="font-size:0.72rem;color:#888;"><?= e($log['admin_email']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($log['entity'])): ?>
                            <code><?= e($log['entity']) ?><?= !empty($log['entity_id']) ? '#' . (int)$log['entity_id'] : '' ?></code>
                        <?php else: ?>
                            <span style="color:#aaa;">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="max-width:320px;">
                        <?php if (!empty($log['details'])): ?>
                            <pre style="margin:0;font-size:0.72rem;background:#f8f9fa;padding:0.4rem;border-radius:4px;overflow-x:auto;max-height:80px;white-space:pre-wrap;word-break:break-word;"><?= e((string)$log['details']) ?></pre>
                        <?php else: ?>
                            <span style="color:#aaa;">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:0.78rem;color:#777;white-space:nowrap;">
                        <?= e($log['ip_address'] ?? '—') ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div style="display:flex;justify-content:center;gap:0.4rem;margin-top:1.5rem;">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a href="<?= e(url('/admin/audit-log.php?' . http_build_query(array_merge($_GET, ['page' => $p])))) ?>" 
               class="btn btn-sm <?= $p === $page ? 'btn-maroon' : 'btn-outline' ?>">
                <?= $p ?>
            </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once APP_ROOT . '/includes/admin-footer.php'; ?>
