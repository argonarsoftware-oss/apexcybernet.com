<?php
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

if (empty($_SESSION['account_id'])) {
    echo json_encode(['paid' => false]);
    exit;
}

$uid    = (int)$_SESSION['account_id'];
$after  = (int)($_GET['after']  ?? 0);   // token timestamp (unix)
$amount = (int)($_GET['amount'] ?? 0);

if ($amount < 1 || $after < 1) {
    echo json_encode(['paid' => false]);
    exit;
}

// Look for a merchant_buy credit for this user, for this exact amount, after the QR was generated
$stmt = $pdo->prepare("
    SELECT amount, ref, created_at
    FROM h_coin_transactions
    WHERE account_id = ?
      AND type       = 'credit'
      AND reason     = 'merchant_buy'
      AND amount     = ?
      AND created_at >= FROM_UNIXTIME(?)
    ORDER BY id DESC
    LIMIT 1
");
$stmt->execute([$uid, $amount, $after]);
$txn = $stmt->fetch();

if (!$txn) {
    echo json_encode(['paid' => false]);
    exit;
}

// Get fresh balance
$stmt = $pdo->prepare("SELECT h_coins FROM accounts WHERE id = ?");
$stmt->execute([$uid]);
$new_balance = (int)$stmt->fetchColumn();

// Parse merchant name from ref (format: "from:MerchantName")
$from = ltrim($txn['ref'] ?? '', 'from:');

echo json_encode([
    'paid'        => true,
    'amount'      => (int)$txn['amount'],
    'from'        => $from,
    'new_balance' => $new_balance,
    'time'        => $txn['created_at'],
]);
