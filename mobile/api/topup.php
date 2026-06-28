<?php
/**
 * mobile/api/topup.php
 * action=check — polls payment status for the user's active HC buy order
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/listener-api.php';
header('Content-Type: application/json');

if (empty($_SESSION['account_id'])) { http_response_code(401); echo json_encode(['error'=>'Not logged in']); exit; }
$uid = (int)$_SESSION['account_id'];

$action = $_GET['action'] ?? '';

if ($action === 'check') {
    $ref = $_SESSION['hc_order_ref'] ?? '';
    if (!$ref) { echo json_encode(['paid' => false]); exit; }

    $result = listenerCheckPayment($ref);
    if (!$result || empty($result['paid'])) { echo json_encode(['paid' => false]); exit; }

    $sender   = trim((string)($result['sender'] ?? ''));
    $phone    = trim((string)($result['phone'] ?? ''));
    $received = $result['pay_amount'] ?? $result['amount'] ?? null;

    $existing   = listenerGetOrder($ref);
    $order_paid = ($existing['order']['status'] ?? '') === 'paid' || !empty($existing['order']['paid_at']);
    $expected   = $existing['order']['pay_amount'] ?? $existing['order']['amount'] ?? null;
    $amount_ok  = $expected !== null && $received !== null && abs((float)$received - (float)$expected) < 0.5;

    if ((!$sender && !$phone) || !$received || !$order_paid || !$amount_ok) {
        echo json_encode(['paid' => false]); exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM h_coin_orders WHERE ref_code=? AND account_id=? AND status='pending'");
    $stmt->execute([$ref, $uid]);
    $order = $stmt->fetch();

    if ($order) {
        $pdo->prepare("UPDATE h_coin_orders SET status='paid' WHERE ref_code=?")->execute([$ref]);
        $pdo->prepare("UPDATE accounts SET h_coins = h_coins + ? WHERE id=?")->execute([$order['coins'], $uid]);
        $pdo->prepare("INSERT INTO h_coin_transactions (account_id, type, amount, reason, ref) VALUES (?, 'credit', ?, 'purchase', ?)")
            ->execute([$uid, $order['coins'], $ref]);
        unset($_SESSION['hc_order_ref']);
    }

    echo json_encode(['paid' => true, 'coins' => $order['coins'] ?? 0]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Unknown action']);
