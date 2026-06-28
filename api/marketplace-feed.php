<?php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');

// No auth required — marketplace is public. Logged-in users get own-listing flag.
$uid   = (int)($_SESSION['account_id'] ?? 0);
$since = (int)($_GET['since'] ?? 0);  // unix ts — 0 returns recent changes

// Listings created/updated since $since. Cap to last 50 events to keep responses small.
// Uses greatest of created_at, sold_at, or NOW() for removed (we don't have removed_at).
$threshold = $since > 0 ? date('Y-m-d H:i:s', $since) : date('Y-m-d H:i:s', time() - 3600);

$stmt = $pdo->prepare("
    SELECT l.id, l.seller_id, l.title, l.description, l.price, l.category,
           l.image_path, l.gallery, l.status, l.buyer_id,
           UNIX_TIMESTAMP(l.created_at) AS created_ts,
           UNIX_TIMESTAMP(l.sold_at)    AS sold_ts,
           a.display_name AS seller_name,
           b.display_name AS buyer_name
    FROM marketplace_listings l
    JOIN accounts a ON a.id = l.seller_id
    LEFT JOIN accounts b ON b.id = l.buyer_id
    WHERE l.created_at > ? OR (l.sold_at IS NOT NULL AND l.sold_at > ?)
    ORDER BY GREATEST(l.created_at, COALESCE(l.sold_at, l.created_at)) DESC
    LIMIT 50
");
$stmt->execute([$threshold, $threshold]);
$rows = $stmt->fetchAll();

$events = [];
$now = time();
foreach ($rows as $r) {
    // Determine event type(s) relative to $since
    $created_ts = (int)$r['created_ts'];
    $sold_ts    = (int)($r['sold_ts'] ?? 0);

    // Newly created since $since
    if ($created_ts > $since && $r['status'] === 'active') {
        $events[] = [
            'type'        => 'listing.created',
            'ts'          => $created_ts,
            'id'          => (int)$r['id'],
            'seller_id'   => (int)$r['seller_id'],
            'seller_name' => $r['seller_name'],
            'title'       => $r['title'],
            'description' => $r['description'],
            'price'       => (int)$r['price'],
            'category'    => $r['category'],
            'image_path'  => $r['image_path'],
        ];
    }

    // Sold since $since
    if ($sold_ts > $since && $r['status'] === 'sold') {
        $events[] = [
            'type'       => 'listing.sold',
            'ts'         => $sold_ts,
            'id'         => (int)$r['id'],
            'buyer_id'   => (int)$r['buyer_id'],
            'buyer_name' => $r['buyer_name'],
            'price'      => (int)$r['price'],
        ];
    }

    // Removed since $since — we don't track a removed_at; skip unless status='removed' and created recently
    // (Client will notice cards gone on next full page load; removals are rare.)
}

echo json_encode([
    'events' => $events,
    'server_ts' => $now,
]);
