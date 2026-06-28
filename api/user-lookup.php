<?php
/**
 * POST /api/user-lookup.php
 * Look up a user by display_name (no password required).
 * Requires logged-in session.
 *
 * Body: { "username": "displayName" }
 */
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

if (empty($_SESSION['account_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$name = trim($body['username'] ?? $body['merchant'] ?? '');

if ($name === '') {
    echo json_encode(['error' => 'Username is required']);
    exit;
}

// Deterministic result: oldest matching account wins (LIMIT 1 ORDER BY id ASC).
// Using TRIM to match the tightened duplicate check in profile.php.
$stmt = $pdo->prepare("SELECT id, display_name, profile_picture, ref_code FROM accounts WHERE LOWER(TRIM(display_name)) = LOWER(TRIM(?)) AND claim_status = 'approved' ORDER BY id ASC LIMIT 1");
$stmt->execute([$name]);
$user = $stmt->fetch();

if (!$user) {
    echo json_encode(['error' => 'User not found']);
    exit;
}

if ((int)$user['id'] === (int)$_SESSION['account_id']) {
    echo json_encode(['error' => 'You cannot send H-Coins to yourself']);
    exit;
}

echo json_encode([
    'ok'             => true,
    'id'             => (int)$user['id'],
    'display_name'   => $user['display_name'],
    'ref_code'       => $user['ref_code'],
    'profile_picture'=> $user['profile_picture'],
]);
