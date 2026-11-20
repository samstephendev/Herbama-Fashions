<?php
/**
 * Live Search API Endpoint
 * =========================
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$query = get('q');
if (strlen($query) < 2) {
    echo json_encode(['items' => []]);
    exit;
}

try {
    $prodModel = new ProductModel();
    $res = $prodModel->list(['q' => $query], 1, 6);
    echo json_encode(['items' => $res['items']]);
} catch (\Throwable $e) {
    error_log('[API Search] ' . $e->getMessage());
    echo json_encode(['items' => []]);
}
