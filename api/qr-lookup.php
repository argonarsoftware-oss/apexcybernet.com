<?php
/**
 * POST /api/qr-lookup.php
 * Called by the merchant POS after scanning a QR.
 * Verifies the token and returns the user's name and balance (preview before charging).
 *
 * Body: { "token": "uid:ts:sig" }
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

$body  = json_decode(file_get_contents('php://input'), true);
$token = trim($body['token'] ?? '');

if (!$token) {
    echo json_encode(['error' => 'Token required']);
    exit;
}

$parts = explode(':', $token);
if (count($parts) !== 3) {
    echo json_encode(['error' => 'Invalid token format']);
    exit;
}

[$uid, $ts, $sig] = $parts;
$uid = (int)$uid;
$ts  = (int)$ts;

// Freshness: 120 seconds
if (abs(time() - $ts) > 120) {
    echo json_encode(['error' => 'QR expired — ask the user to refresh their code']);
    exit;
}

// Verify HMAC
$expected = hash_hmac('sha256', $uid . ':' . $ts, QR_HMAC_SECRET);
if (!hash_equals($expected, $sig)) {
    echo json_encode(['error' => 'Invalid QR — token signature mismatch']);
    exit;
}

$stmt = $pdo->prepare("SELECT id, display_name, h_coins FROM accounts WHERE id = ?");
$stmt->execute([$uid]);
$user = $stmt->fetch();
if (!$user) {
    echo json_encode(['error' => 'User account not found']);
    exit;
}

echo json_encode([
    'ok'           => true,
    'uid'          => $user['id'],
    'display_name' => $user['display_name'],
    'h_coins'      => (int)$user['h_coins'],
]);
