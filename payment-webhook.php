<?php
/**
 * Apex Cybernet Tournament Payment Webhook
 *
 * The GCash listener API POSTs here when a payment is matched to an order.
 * This is the push counterpart to the polling in ticket.php — instant updates
 * even when the customer has closed the page.
 *
 * POST /payment-webhook.php
 * Header: X-API-Key: (must match listener's key)
 * Body: { "order_id": "DOTA-T-XXXX", "status": "paid", "pay_amount": 500.49, "sender": "...", "phone": "..." }
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/listener-api.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// Verify API key (same as the one ticket.php uses to talk to listener)
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($apiKey !== LISTENER_API_KEY) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid API key']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
$orderId  = trim((string)($payload['order_id'] ?? ''));
$status   = (string)($payload['status'] ?? '');
$payAmount= $payload['pay_amount'] ?? null;
$sender   = (string)($payload['sender'] ?? '');
$phone    = (string)($payload['phone'] ?? '');

if (empty($orderId) || $status !== 'paid') {
    http_response_code(200);
    echo json_encode(['message' => 'Ignored — not a paid event']);
    exit;
}

// ── H-Coin purchase order (HC-{user_id}-{rand4}) ──
if (strncmp($orderId, 'HC-', 3) === 0) {
    $verify = listenerCheckPayment($orderId);
    if (!$verify || empty($verify['paid'])) {
        error_log("[payment-webhook] HC verify failed for $orderId");
        http_response_code(200);
        echo json_encode(['message' => 'HC verification failed']);
        exit;
    }

    $order_row = $pdo->prepare("SELECT id, account_id, coins FROM h_coin_orders WHERE ref_code = ? AND status = 'pending'");
    $order_row->execute([$orderId]);
    $hco = $order_row->fetch();

    if (!$hco) {
        http_response_code(200);
        echo json_encode(['message' => "HC order $orderId not pending (already processed?)"]); exit;
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE h_coin_orders SET status = 'paid' WHERE id = ?")->execute([$hco['id']]);
        $pdo->prepare("UPDATE accounts SET h_coins = h_coins + ? WHERE id = ?")->execute([$hco['coins'], $hco['account_id']]);
        $pdo->prepare("INSERT INTO h_coin_transactions (account_id, type, amount, reason, ref) VALUES (?, 'credit', ?, 'purchase', ?)")->execute([$hco['account_id'], $hco['coins'], $orderId]);
        $pdo->commit();
        error_log("[payment-webhook] HC CREDITED {$hco['coins']} coins to account {$hco['account_id']} for $orderId");
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("[payment-webhook] HC credit failed for $orderId: " . $e->getMessage());
    }

    http_response_code(200);
    echo json_encode(['success' => true, 'order_id' => $orderId, 'type' => 'hcoin', 'coins' => $hco['coins']]);
    exit;
}

// ── Tournament registration order ──
// Determine type from prefix: DOTA-T-* / VAL-T-* / CF-T-* = team, *-S-* = solo
$type = (strpos($orderId, '-S-') !== false) ? 'solo' : 'team';

// Cross-verify with listener (don't trust the payload alone)
$verify = listenerCheckPayment($orderId);
if (!$verify || empty($verify['paid'])) {
    error_log("[payment-webhook] Verification failed for $orderId — listener says not paid");
    http_response_code(200);
    echo json_encode(['message' => 'Verification failed']);
    exit;
}

$proof = sprintf('GCASH-WEBHOOK | %s | %s | ₱%s | order=%s',
    $sender ?: '(no name)',
    $phone ?: '(no phone)',
    $payAmount ?: '?',
    $orderId
);

if ($type === 'solo') {
    $upd = $pdo->prepare("UPDATE solo_players SET status = 'approved', payment_proof = ? WHERE ref_code = ? AND status = 'pending'");
} else {
    $upd = $pdo->prepare("UPDATE teams SET status = 'approved', payment_proof = ? WHERE ref_code = ? AND status = 'pending'");
}
$upd->execute([$proof, $orderId]);

$rows = $upd->rowCount();
error_log("[payment-webhook] " . ($rows > 0 ? "APPROVED" : "SKIPPED") . " $orderId ($type) — sender=$sender amount=$payAmount");

http_response_code(200);
echo json_encode([
    'success' => true,
    'order_id' => $orderId,
    'type' => $type,
    'updated_rows' => $rows,
    'message' => $rows > 0 ? "Order $orderId marked paid" : "Order $orderId not pending (already processed?)",
]);
