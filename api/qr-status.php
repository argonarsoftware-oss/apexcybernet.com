<?php
/**
 * GET /api/qr-status.php?since=UNIX_TIMESTAMP
 *
 * Returns any qr_payment transactions for the logged-in user
 * that occurred after `since`. Used by qr-wallet.php to detect
 * when a merchant has successfully charged the user.
 */
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

if (empty($_SESSION['account_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$uid   = (int)$_SESSION['account_id'];
$since = (int)($_GET['since'] ?? 0);

if ($since <= 0) {
    echo json_encode(['charges' => [], 'h_coins' => 0]);
    exit;
}

$since_dt = date('Y-m-d H:i:s', $since);

// Debits the user incurred — merchant QR charges, so qr-wallet.php can
// redirect to the receipt page when a POS charges them.
$debit_stmt = $pdo->prepare("
    SELECT amount, ref, created_at
    FROM h_coin_transactions
    WHERE account_id = ?
      AND type = 'debit'
      AND reason = 'qr_payment'
      AND created_at > ?
    ORDER BY created_at ASC
");
$debit_stmt->execute([$uid, $since_dt]);
$charges = $debit_stmt->fetchAll();

// Credits the user received — user-to-user sends AND merchant qr_received.
// receive-hcoins.php polls this so it can show a toast + balance bump when
// money lands in their account.
$credit_stmt = $pdo->prepare("
    SELECT amount, ref, reason, created_at
    FROM h_coin_transactions
    WHERE account_id = ?
      AND type = 'credit'
      AND reason IN ('received', 'qr_received')
      AND created_at > ?
    ORDER BY created_at ASC
");
$credit_stmt->execute([$uid, $since_dt]);
$credits = $credit_stmt->fetchAll();

// Current balance
$bal_stmt = $pdo->prepare("SELECT h_coins FROM accounts WHERE id = ?");
$bal_stmt->execute([$uid]);
$h_coins = (int)($bal_stmt->fetchColumn() ?? 0);

$charges_result = [];
$receipts_result = [];
$latest_ts = $since; // will advance to the latest row's unix timestamp

foreach ($charges as $c) {
    $merchant  = strpos($c['ref'], 'to:') === 0 ? substr($c['ref'], 3) : $c['ref'];
    $charge_ts = strtotime($c['created_at']);
    if ($charge_ts > $latest_ts) $latest_ts = $charge_ts;

    $charges_result[] = [
        'amount'   => (int)$c['amount'],
        'merchant' => $merchant,
        'time'     => $c['created_at'],
        'ts'       => $charge_ts, // unix timestamp — no client-side date parsing needed
    ];
}

foreach ($credits as $c) {
    $from     = strpos($c['ref'], 'from:') === 0 ? substr($c['ref'], 5) : $c['ref'];
    $from     = strtok($from, ':'); // strip any trailing note suffix
    $recv_ts  = strtotime($c['created_at']);
    if ($recv_ts > $latest_ts) $latest_ts = $recv_ts;

    $receipts_result[] = [
        'amount' => (int)$c['amount'],
        'from'   => $from,
        'reason' => $c['reason'],
        'time'   => $c['created_at'],
        'ts'     => $recv_ts,
    ];
}

echo json_encode([
    'charges'    => $charges_result,
    'receipts'   => $receipts_result,
    'h_coins'    => $h_coins,
    'next_since' => $latest_ts + 1, // client uses this as the next poll's since param
]);
