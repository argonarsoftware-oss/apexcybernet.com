<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/pusher.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

function msell_err($msg) {
    echo json_encode(['error' => $msg]);
    exit;
}

$data      = json_decode(file_get_contents('php://input'), true) ?: [];
$action    = $data['action'] ?? '';
$token     = trim($data['token'] ?? '');
$mid       = (int)($data['merchant_id'] ?? 0);

if (!$mid)    msell_err('Not authenticated');
if (!$token)  msell_err('Missing token');

// Verify merchant
$stmt = $pdo->prepare("SELECT id, display_name, h_coins, is_merchant FROM accounts WHERE id = ?");
$stmt->execute([$mid]);
$merchant = $stmt->fetch();
if (!$merchant || !$merchant['is_merchant']) msell_err('Access denied — merchant account required');

// Parse BUY QR: BUY:{uid}:{hcoins}:{ts}:{sig}
$parts = explode(':', $token, 5);
if (count($parts) !== 5 || $parts[0] !== 'BUY') msell_err('Not a valid Buy QR code');
$uid    = (int)$parts[1];
$hcoins = (int)$parts[2];
$ts     = (int)$parts[3];
$sig    = $parts[4];

// Verify HMAC
$expected = hash_hmac('sha256', "BUY:{$uid}:{$hcoins}:{$ts}", QR_HMAC_SECRET);
if (!hash_equals($expected, $sig)) msell_err('Invalid QR — signature mismatch');

// Check expiry (5 minutes)
if (time() - $ts > 300) msell_err('QR expired — ask customer to refresh');

if ($hcoins < 1 || $hcoins > 100000) msell_err('Invalid HC amount in QR');

// Look up buyer
$stmt = $pdo->prepare("SELECT id, display_name, h_coins FROM accounts WHERE id = ?");
$stmt->execute([$uid]);
$buyer = $stmt->fetch();
if (!$buyer) msell_err('Customer account not found');

// ── action=lookup: just return info, no transfer ──
if ($action === 'lookup') {
    echo json_encode([
        'ok'          => true,
        'user_id'     => $buyer['id'],
        'user_name'   => $buyer['display_name'],
        'hcoins'      => $hcoins,
        'peso_amount' => number_format($hcoins, 2, '.', ''),
        'token'       => $token,
    ]);
    exit;
}

// ── action=confirm: process transfer ──
if ($action === 'confirm') {
    if ($merchant['h_coins'] < $hcoins) msell_err('Insufficient merchant balance (' . number_format($merchant['h_coins']) . ' HC available)');

    $pdo->beginTransaction();
    try {
        // Deduct from merchant (atomic check)
        $stmt = $pdo->prepare("UPDATE accounts SET h_coins = h_coins - ? WHERE id = ? AND h_coins >= ?");
        $stmt->execute([$hcoins, $mid, $hcoins]);
        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            msell_err('Insufficient merchant balance');
        }

        // Credit buyer
        $pdo->prepare("UPDATE accounts SET h_coins = h_coins + ? WHERE id = ?")
            ->execute([$hcoins, $uid]);

        // Log merchant debit
        $pdo->prepare("INSERT INTO h_coin_transactions (account_id, type, amount, reason, ref) VALUES (?, 'debit', ?, 'sell_hc', ?)")
            ->execute([$mid, $hcoins, 'to:' . $buyer['display_name']]);

        // Log buyer credit
        $pdo->prepare("INSERT INTO h_coin_transactions (account_id, type, amount, reason, ref) VALUES (?, 'credit', ?, 'merchant_buy', ?)")
            ->execute([$uid, $hcoins, 'from:' . $merchant['display_name']]);

        $pdo->commit();

        // Fresh balances
        $newMerchBal = (int)$pdo->query("SELECT h_coins FROM accounts WHERE id = $mid")->fetchColumn();
        $newBuyerBal = (int)$pdo->query("SELECT h_coins FROM accounts WHERE id = $uid")->fetchColumn();

        hc_push($pdo, $uid, $hcoins, $merchant['display_name'], $newBuyerBal, 'merchant_buy');

        echo json_encode([
            'success'              => true,
            'hcoins'               => $hcoins,
            'user'                 => $buyer['display_name'],
            'merchant'             => $merchant['display_name'],
            'merchant_new_balance' => $newMerchBal,
            'user_new_balance'     => $newBuyerBal,
            'server_time'          => date('M j, g:i A'),
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        msell_err('Transaction failed — please try again');
    }
    exit;
}

msell_err('Unknown action');
