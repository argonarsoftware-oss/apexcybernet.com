<?php
/**
 * POST /api/send-hcoins.php
 * Logged-in user sends H-Coins to another user.
 *
 * Body (by ref code): { "to_ref_code": "ACC-XXXX", "amount": 100 }  ← preferred
 * Body (by username): { "to_name":     "display",  "amount": 100 }  ← legacy
 * Body (by QR token): { "token":       "uid:ts:sig","amount": 100 }
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/pusher.php';
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

$body    = json_decode(file_get_contents('php://input'), true);
$amount  = (int)($body['amount'] ?? 0);
$token   = trim($body['token']       ?? '');
$toRef   = trim($body['to_ref_code'] ?? '');
$toName  = trim($body['to_name']     ?? '');
$sender_id = (int)$_SESSION['account_id'];

if ($amount < 1 || $amount > 999999) {
    echo json_encode(['error' => 'Amount must be between 1 and 999,999 HC']);
    exit;
}

// ── Resolve recipient ──
if ($token) {
    // Verify QR token
    $parts = explode(':', $token);
    if (count($parts) !== 3) {
        echo json_encode(['error' => 'Invalid QR token']);
        exit;
    }
    [$uid, $ts, $sig] = $parts;
    $uid = (int)$uid;
    $ts  = (int)$ts;

    if (abs(time() - $ts) > 120) {
        echo json_encode(['error' => 'QR expired — ask them to refresh']);
        exit;
    }
    $expected = hash_hmac('sha256', $uid . ':' . $ts, QR_HMAC_SECRET);
    if (!hash_equals($expected, $sig)) {
        echo json_encode(['error' => 'Invalid QR signature']);
        exit;
    }

    $rec_stmt = $pdo->prepare("SELECT id, display_name, h_coins FROM accounts WHERE id = ?");
    $rec_stmt->execute([$uid]);
} else if ($toRef !== '') {
    // Canonical: resolve by immutable ref_code so username changes cannot redirect the transfer.
    $rec_stmt = $pdo->prepare("SELECT id, display_name, h_coins FROM accounts WHERE ref_code = ? AND claim_status = 'approved'");
    $rec_stmt->execute([$toRef]);
} else if ($toName !== '') {
    // Legacy path — deterministic LIMIT so case-collision rows pick the oldest account consistently.
    $rec_stmt = $pdo->prepare("SELECT id, display_name, h_coins FROM accounts WHERE LOWER(TRIM(display_name)) = LOWER(TRIM(?)) AND claim_status = 'approved' ORDER BY id ASC LIMIT 1");
    $rec_stmt->execute([$toName]);
} else {
    echo json_encode(['error' => 'Provide a ref code, username, or scan a QR code']);
    exit;
}

$recipient = $rec_stmt->fetch();
if (!$recipient) {
    echo json_encode(['error' => 'User not found']);
    exit;
}
if ($recipient['id'] === $sender_id) {
    echo json_encode(['error' => 'You cannot send H-Coins to yourself']);
    exit;
}

// ── Fetch sender ──
$sen_stmt = $pdo->prepare("SELECT id, display_name, h_coins FROM accounts WHERE id = ?");
$sen_stmt->execute([$sender_id]);
$sender = $sen_stmt->fetch();

if ((int)$sender['h_coins'] < $amount) {
    echo json_encode([
        'error'   => 'Insufficient balance',
        'balance' => (int)$sender['h_coins'],
    ]);
    exit;
}

// ── Block transfer of welcome bonus HC ──
// Unspent bonus = total bonus credited minus total debits (bonus is consumed first)
$bonus_q = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM h_coin_transactions WHERE account_id = ? AND type='credit' AND reason='welcome_bonus'");
$bonus_q->execute([$sender_id]);
$total_bonus = (int)$bonus_q->fetchColumn();

$spent_q = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM h_coin_transactions WHERE account_id = ? AND type='debit'");
$spent_q->execute([$sender_id]);
$total_spent = (int)$spent_q->fetchColumn();

$unspent_bonus  = max(0, $total_bonus - $total_spent);
$transferable   = max(0, (int)$sender['h_coins'] - $unspent_bonus);

if ($transferable < $amount) {
    echo json_encode([
        'error'       => 'Welcome bonus HC cannot be transferred. Only earned HC (from purchases or prediction wins) can be sent.',
        'transferable'=> $transferable,
        'balance'     => (int)$sender['h_coins'],
    ]);
    exit;
}

// ── Execute transfer ──
$pdo->beginTransaction();
try {
    $deduct = $pdo->prepare("UPDATE accounts SET h_coins = h_coins - ? WHERE id = ? AND h_coins >= ?");
    $deduct->execute([$amount, $sender_id, $amount]);
    if ($deduct->rowCount() === 0) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Balance check failed — try again']);
        exit;
    }

    $pdo->prepare("UPDATE accounts SET h_coins = h_coins + ? WHERE id = ?")
        ->execute([$amount, $recipient['id']]);

    $pdo->prepare("INSERT INTO h_coin_transactions (account_id, type, amount, reason, ref) VALUES (?, 'debit', ?, 'send', ?)")
        ->execute([$sender_id, $amount, 'to:' . $recipient['display_name']]);

    $pdo->prepare("INSERT INTO h_coin_transactions (account_id, type, amount, reason, ref) VALUES (?, 'credit', ?, 'received', ?)")
        ->execute([$recipient['id'], $amount, 'from:' . $sender['display_name']]);

    $pdo->commit();

    $recipientNewBal = (int)$recipient['h_coins'] + $amount;
    hc_push($pdo, $recipient['id'], $amount, $sender['display_name'], $recipientNewBal, 'received');

    echo json_encode([
        'success'           => true,
        'to'                => $recipient['display_name'],
        'amount'            => $amount,
        'sender_new_balance'=> (int)$sender['h_coins'] - $amount,
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('[send-hcoins] ' . $e->getMessage());
    echo json_encode(['error' => 'Transfer failed — try again']);
}
