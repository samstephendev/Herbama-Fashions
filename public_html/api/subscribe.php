<?php
/**
 * Newsletter Subscribe API Endpoint
 * ==================================
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

csrf_verify();

$email = post('email');
$contactModel = new ContactModel();
$res = $contactModel->subscribe($email);

echo json_encode($res);
