<?php
/**
 * POST /api/merchant-lookup.php
 * Validates a merchant account by display_name and returns their balance.
 * Called by demo-pos.php on the login step.
 *
 * Body: { "merchant": "displayName" }
 */
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$body     = json_decode(file_get_contents('php://input'), true);
$name     = trim($body['merchant']  ?? '');
$password = $body['password'] ?? '';

if ($name === '' || $password === '') {
    echo json_encode(['error' => 'Username and password are required']);
    exit;
}

$stmt = $pdo->prepare("SELECT id, display_name, h_coins, password_hash, is_merchant FROM accounts WHERE LOWER(display_name) = LOWER(?) AND claim_status = 'approved'");
$stmt->execute([$name]);
$merchant = $stmt->fetch();

if (!$merchant || !password_verify($password, $merchant['password_hash'])) {
    echo json_encode(['error' => 'Invalid username or password']);
    exit;
}

if (!$merchant['is_merchant']) {
    echo json_encode(['error' => 'This account is not registered as a merchant. Contact an admin to enable merchant access.']);
    exit;
}

echo json_encode([
    'ok'           => true,
    'merchant_id'  => (int)$merchant['id'],
    'display_name' => $merchant['display_name'],
    'h_coins'      => (int)$merchant['h_coins'],
]);
