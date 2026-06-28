<?php
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

if (empty($_SESSION['account_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$hcoins = (int)($_GET['hcoins'] ?? 0);
if ($hcoins < 1 || $hcoins > 100000) {
    echo json_encode(['error' => 'Invalid amount']);
    exit;
}

$uid = (int)$_SESSION['account_id'];
$ts  = time();
$sig = hash_hmac('sha256', "BUY:{$uid}:{$hcoins}:{$ts}", QR_HMAC_SECRET);

echo json_encode([
    'token'      => "BUY:{$uid}:{$hcoins}:{$ts}:{$sig}",
    'expires_in' => 300,
    'ts'         => $ts,
]);
