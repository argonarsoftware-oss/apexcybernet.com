<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/pusher.php';
header('Content-Type: application/json');

if (empty($_SESSION['account_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$body       = json_decode(file_get_contents('php://input'), true);
$listing_id = (int)($body['listing_id'] ?? 0);
$buyer_id   = (int)$_SESSION['account_id'];

if (!$listing_id) { echo json_encode(['error' => 'Invalid listing']); exit; }

// Load listing
$lst = $pdo->prepare("SELECT * FROM marketplace_listings WHERE id = ? AND status = 'active'");
$lst->execute([$listing_id]);
$listing = $lst->fetch();

if (!$listing)                        { echo json_encode(['error' => 'Listing not found or already sold']); exit; }
if ((int)$listing['seller_id'] === $buyer_id) { echo json_encode(['error' => 'You cannot buy your own listing']); exit; }

$price     = (int)$listing['price'];
$seller_id = (int)$listing['seller_id'];

// Race-safe deduct from buyer
$deduct = $pdo->prepare("
    UPDATE accounts SET h_coins = h_coins - ?
    WHERE id = ? AND h_coins >= ?
");
$deduct->execute([$price, $buyer_id, $price]);

if ($deduct->rowCount() === 0) {
    echo json_encode(['error' => 'Insufficient H-Coins']);
    exit;
}

// Mark listing sold
$sold = $pdo->prepare("
    UPDATE marketplace_listings
    SET status = 'sold', buyer_id = ?, sold_at = NOW()
    WHERE id = ? AND status = 'active'
");
$sold->execute([$buyer_id, $listing_id]);

if ($sold->rowCount() === 0) {
    // Someone else bought it just now — refund buyer
    $pdo->prepare("UPDATE accounts SET h_coins = h_coins + ? WHERE id = ?")->execute([$price, $buyer_id]);
    echo json_encode(['error' => 'Listing was just sold — try another']);
    exit;
}

// Credit seller
$pdo->prepare("UPDATE accounts SET h_coins = h_coins + ? WHERE id = ?")->execute([$price, $seller_id]);

// Log both sides
$pdo->prepare("
    INSERT INTO h_coin_transactions (account_id, type, amount, reason, ref)
    VALUES (?, 'debit', ?, 'marketplace_buy', ?)
")->execute([$buyer_id, $price, 'listing:' . $listing_id]);

$pdo->prepare("
    INSERT INTO h_coin_transactions (account_id, type, amount, reason, ref)
    VALUES (?, 'credit', ?, 'marketplace_sale', ?)
")->execute([$seller_id, $price, 'listing:' . $listing_id]);

// Notify seller (bell row inserted by hc_push, then WS event)
$sellerNewBal = (int)$pdo->query("SELECT h_coins FROM accounts WHERE id = $seller_id")->fetchColumn();
hc_push($pdo, $seller_id, $price, 'Marketplace sale', $sellerNewBal, 'marketplace_sale');

// Buyer bell — "You purchased X"
$buyer_stmt = $pdo->prepare("SELECT display_name FROM accounts WHERE id = ?");
$buyer_stmt->execute([$buyer_id]);
$buyer_name = $buyer_stmt->fetchColumn() ?: 'You';

notify_user(
    $pdo, $buyer_id,
    'Purchase complete',
    htmlspecialchars($listing['title']) . ' — ' . number_format($price) . ' HC',
    'bi-bag-check-fill',
    '/marketplace.php'
);

// Clients discover the sold state via api/marketplace-feed.php polling.

// Return buyer's new balance
$bal = $pdo->prepare("SELECT h_coins FROM accounts WHERE id = ?");
$bal->execute([$buyer_id]);
$new_balance = (int)$bal->fetchColumn();

echo json_encode([
    'success'     => true,
    'item'        => htmlspecialchars($listing['title']),
    'price'       => $price,
    'new_balance' => $new_balance,
]);
