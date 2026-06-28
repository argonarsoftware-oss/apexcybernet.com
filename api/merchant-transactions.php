<?php
/**
 * POST /api/merchant-transactions.php
 * Returns recent incoming transactions for a merchant.
 * Body: { "merchant_id": 42 }
 */
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

$body        = json_decode(file_get_contents('php://input'), true);
$merchant_id = (int)($body['merchant_id'] ?? 0);

if (!$merchant_id) {
    echo json_encode(['error' => 'merchant_id required']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT amount, reason, ref, created_at
    FROM h_coin_transactions
    WHERE account_id = ? AND type = 'credit' AND reason IN ('qr_received','marketplace_sale')
    ORDER BY created_at DESC
    LIMIT 30
");
$stmt->execute([$merchant_id]);
$rows = $stmt->fetchAll();

// Period totals (qr_received only — POS payments)
$period_stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN DATE(created_at) = CURDATE() THEN amount END), 0) AS today,
        COALESCE(SUM(CASE WHEN YEARWEEK(created_at,1) = YEARWEEK(NOW(),1) THEN amount END), 0) AS this_week,
        COALESCE(SUM(CASE WHEN YEAR(created_at) = YEAR(NOW()) AND MONTH(created_at) = MONTH(NOW()) THEN amount END), 0) AS this_month,
        COALESCE(SUM(amount), 0) AS all_time,
        COUNT(*) AS total_txns
    FROM h_coin_transactions
    WHERE account_id = ? AND type = 'credit' AND reason = 'qr_received'
");
$period_stmt->execute([$merchant_id]);
$periods = $period_stmt->fetch();
$today_total = (int)$periods['today'];

$txns = [];
foreach ($rows as $t) {
    // ref format: "from:Name" or "from:Name:note text"
    $parts  = explode(':', $t['ref'], 3);
    $from   = $parts[1] ?? '';
    $note   = $parts[2] ?? '';
    $txns[] = [
        'amount' => (int)$t['amount'],
        'from'   => $from,
        'note'   => $note,
        'reason' => $t['reason'],
        'time'   => $t['created_at'],
    ];
}

echo json_encode([
    'ok'          => true,
    'today_total' => $today_total,
    'this_week'   => (int)$periods['this_week'],
    'this_month'  => (int)$periods['this_month'],
    'all_time'    => (int)$periods['all_time'],
    'total_txns'  => (int)$periods['total_txns'],
    'transactions'=> $txns,
]);
