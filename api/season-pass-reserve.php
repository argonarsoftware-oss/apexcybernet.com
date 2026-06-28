<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

if (empty($_SESSION['account_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Please log in first']);
    exit;
}

$uid = (int)$_SESSION['account_id'];

// Reject if already has an active pass
$existing = $pdo->prepare("SELECT id, ref_code, status FROM season_passes WHERE account_id = ? AND status IN ('pending','active') ORDER BY id DESC LIMIT 1");
$existing->execute([$uid]);
$row = $existing->fetch();
if ($row) {
    echo json_encode([
        'ok'       => true,
        'ref_code' => $row['ref_code'],
        'status'   => $row['status'],
        'existing' => true,
    ]);
    exit;
}

// Generate unique ref code (SP-<uid>-<rand>)
for ($i = 0; $i < 10; $i++) {
    $ref = 'SP-' . $uid . '-' . strtoupper(bin2hex(random_bytes(3)));
    $chk = $pdo->prepare("SELECT 1 FROM season_passes WHERE ref_code = ?");
    $chk->execute([$ref]);
    if (!$chk->fetch()) break;
}

$stmt = $pdo->prepare("INSERT INTO season_passes
    (ref_code, account_id, season_label, price_paid, payment_method, status)
    VALUES (?, ?, 'Season 1', 999.00, 'gcash_manual', 'pending')");
$stmt->execute([$ref, $uid]);

echo json_encode([
    'ok'       => true,
    'ref_code' => $ref,
    'status'   => 'pending',
    'existing' => false,
]);
