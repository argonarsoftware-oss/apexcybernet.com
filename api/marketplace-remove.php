<?php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

if (empty($_SESSION['account_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

if (empty($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Only admins can remove listings']);
    exit;
}

$body       = json_decode(file_get_contents('php://input'), true);
$listing_id = (int)($body['listing_id'] ?? 0);
$uid        = (int)$_SESSION['account_id'];

$stmt = $pdo->prepare("
    UPDATE marketplace_listings SET status = 'removed'
    WHERE id = ? AND seller_id = ? AND status = 'active'
");
$stmt->execute([$listing_id, $uid]);

if ($stmt->rowCount() === 0) {
    echo json_encode(['error' => 'Listing not found or already closed']);
    exit;
}

echo json_encode(['success' => true]);
