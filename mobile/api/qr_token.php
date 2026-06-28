<?php
require_once __DIR__ . '/../../includes/db.php';
header('Content-Type: application/json');

if (empty($_SESSION['account_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$uid = (int)$_SESSION['account_id'];
$ts  = time();
$sig = hash_hmac('sha256', $uid . ':' . $ts, QR_HMAC_SECRET);
echo json_encode(['token' => $uid . ':' . $ts . ':' . $sig, 'expires' => $ts + 120]);
