<?php
/**
 * POST /mobile/api/pusher-auth.php
 * Authenticates private Pusher/Soketi channels for sessions under /mobile/.
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/pusher.php';

header('Content-Type: application/json');

if (empty($_SESSION['account_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$uid     = (int)$_SESSION['account_id'];
$socket  = $_POST['socket_id']    ?? '';
$channel = $_POST['channel_name'] ?? '';

if (!$socket || $channel !== 'private-user.' . $uid) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$sig = hash_hmac('sha256', $socket . ':' . $channel, PUSHER_SECRET);
echo json_encode(['auth' => PUSHER_KEY . ':' . $sig]);
