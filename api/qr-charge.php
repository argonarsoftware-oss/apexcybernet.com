<?php
/**
 * POST /api/qr-charge.php
 * Processes the actual H-Coin transfer from user to merchant.
 *
 * Body: {
 *   "token":    "uid:ts:sig",    — user's QR token
 *   "amount":   500,             — HC to charge
 *   "merchant": "MerchantName"   — merchant's display_name (their argonar account)
 * }
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/pusher.php';
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

$body     = json_decode(file_get_contents('php://input'), true);
$token    = trim($body['token'] ?? '');
$amount   = (int)($body['amount'] ?? 0);
$merchant_name = trim($body['merchant'] ?? '');
$note     = trim(substr(preg_replace('/[:\x00-\x1f]/', ' ', $body['note'] ?? ''), 0, 60));

// ── Validate inputs ──
if (!$token || !$merchant_name) {
    echo json_encode(['error' => 'Token and merchant name are required']);
    exit;
}
if ($amount < 1 || $amount > 999999) {
    echo json_encode(['error' => 'Amount must be between 1 and 999,999 HC']);
    exit;
}

// ── Parse and verify token ──
$parts = explode(':', $token);
if (count($parts) !== 3) {
    echo json_encode(['error' => 'Invalid token format']);
    exit;
}
[$uid, $ts, $sig] = $parts;
$uid = (int)$uid;
$ts  = (int)$ts;

if (abs(time() - $ts) > 120) {
    echo json_encode(['error' => 'QR expired — user must refresh their code']);
    exit;
}

$expected = hash_hmac('sha256', $uid . ':' . $ts, QR_HMAC_SECRET);
if (!hash_equals($expected, $sig)) {
    echo json_encode(['error' => 'Invalid QR signature']);
    exit;
}

// ── Fetch user and merchant accounts ──
$user_stmt = $pdo->prepare("SELECT id, display_name, h_coins FROM accounts WHERE id = ?");
$user_stmt->execute([$uid]);
$user = $user_stmt->fetch();
if (!$user) {
    echo json_encode(['error' => 'User account not found']);
    exit;
}

$merch_stmt = $pdo->prepare("SELECT id, display_name, h_coins FROM accounts WHERE LOWER(display_name) = LOWER(?)");
$merch_stmt->execute([$merchant_name]);
$merchant = $merch_stmt->fetch();
if (!$merchant) {
    echo json_encode(['error' => "Merchant account '{$merchant_name}' not found"]);
    exit;
}

if ($merchant['id'] === $user['id']) {
    echo json_encode(['error' => 'Cannot charge your own account']);
    exit;
}

if ((int)$user['h_coins'] < $amount) {
    echo json_encode([
        'error'   => 'Insufficient balance',
        'balance' => (int)$user['h_coins'],
        'needed'  => $amount,
    ]);
    exit;
}

// ── Execute transfer ──
$pdo->beginTransaction();
try {
    // Atomically claim this (uid, ts) pair via the qr_tokens_used dedup table.
    // A duplicate means the same QR was already charged — reject so the merchant
    // must rescan a fresh code. If the table is missing (migration not yet run),
    // degrade gracefully to the legacy behavior so charges don't break.
    try {
        $claim = $pdo->prepare("INSERT IGNORE INTO qr_tokens_used (user_id, ts, used_at) VALUES (?, ?, ?)");
        $claim->execute([$uid, $ts, time()]);
        if ($claim->rowCount() === 0) {
            $pdo->rollBack();
            echo json_encode(['error' => 'This QR was already used — ask customer to refresh']);
            exit;
        }
    } catch (PDOException $e) {
        // Missing table or DDL perms → log & continue. Dedup is best-effort.
        error_log('[qr-charge] dedup skipped: ' . $e->getMessage());
    }

    // Deduct from user (with balance re-check for race safety)
    $deduct = $pdo->prepare("UPDATE accounts SET h_coins = h_coins - ? WHERE id = ? AND h_coins >= ?");
    $deduct->execute([$amount, $user['id'], $amount]);
    if ($deduct->rowCount() === 0) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Balance check failed — please retry']);
        exit;
    }

    // Credit merchant
    $pdo->prepare("UPDATE accounts SET h_coins = h_coins + ? WHERE id = ?")
        ->execute([$amount, $merchant['id']]);

    $ref_suffix = $note !== '' ? ':' . $note : '';

    // Transaction log — user side
    $pdo->prepare("INSERT INTO h_coin_transactions (account_id, type, amount, reason, ref) VALUES (?, 'debit', ?, 'qr_payment', ?)")
        ->execute([$user['id'], $amount, 'to:' . $merchant['display_name'] . $ref_suffix]);

    // Transaction log — merchant side
    $pdo->prepare("INSERT INTO h_coin_transactions (account_id, type, amount, reason, ref) VALUES (?, 'credit', ?, 'qr_received', ?)")
        ->execute([$merchant['id'], $amount, 'from:' . $user['display_name'] . $ref_suffix]);

    $pdo->commit();

    $merchantNewBal = (int)$merchant['h_coins'] + $amount;
    hc_push($pdo, $merchant['id'], $amount, $user['display_name'], $merchantNewBal, 'qr_received');

    echo json_encode([
        'success'              => true,
        'user'                 => $user['display_name'],
        'merchant'             => $merchant['display_name'],
        'amount'               => $amount,
        'note'                 => $note,
        'user_new_balance'     => (int)$user['h_coins'] - $amount,
        'merchant_new_balance' => (int)$merchant['h_coins'] + $amount,
        'server_time'          => date('H:i:s'),
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('[qr-charge] ' . $e->getMessage());
    echo json_encode(['error' => 'Transaction failed — please try again']);
}
