<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/pusher.php';

// Safety — local only or with secret token
$token = $_GET['token'] ?? '';
if ($token !== 'apexcybernet-ws-test-2026') {
    die(json_encode(['error' => 'Forbidden']));
}

header('Content-Type: application/json');

$account_id = (int)($_GET['uid'] ?? 0);

// List accounts if no uid given
if ($account_id < 1) {
    $rows = $pdo->query("SELECT id, display_name, email, h_coins FROM accounts ORDER BY id LIMIT 20")->fetchAll();
    echo json_encode(['accounts' => $rows]);
    exit;
}

$acct = $pdo->prepare("SELECT id, display_name, email, h_coins FROM accounts WHERE id = ?");
$acct->execute([$account_id]);
$user = $acct->fetch();
if (!$user) {
    echo json_encode(['error' => 'Account not found']);
    exit;
}

$amount = (int)($_GET['amount'] ?? 5);
$new_balance = (int)$user['h_coins'] + $amount;

hc_push($pdo, $account_id, $amount, 'WebSocket Test', $new_balance, 'test');

echo json_encode([
    'success'     => true,
    'pushed_to'   => $user['display_name'] ?: $user['email'],
    'uid'         => $account_id,
    'amount'      => $amount,
    'new_balance' => $new_balance,
    'note'        => 'HC was NOT added to DB — this is a push-only test',
]);
