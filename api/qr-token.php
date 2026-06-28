<?php
/**
 * GET /api/qr-token.php
 *
 * ?format=json  → returns { token, expires_at, h_coins, display_name }
 * ?format=png   → returns QR code as PNG image (default)
 *
 * Token format: uid:timestamp:hmac_sha256  (valid 120 seconds)
 */
require_once __DIR__ . '/../includes/db.php';

if (empty($_SESSION['account_id'])) {
    if (($_GET['format'] ?? 'png') === 'json') {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['error' => 'Not logged in']);
    } else {
        http_response_code(401);
    }
    exit;
}

$uid = (int)$_SESSION['account_id'];
$ts  = time();
$sig = hash_hmac('sha256', $uid . ':' . $ts, QR_HMAC_SECRET);
$token = $uid . ':' . $ts . ':' . $sig;

$stmt = $pdo->prepare("SELECT h_coins, display_name FROM accounts WHERE id = ?");
$stmt->execute([$uid]);
$row = $stmt->fetch();

$format = $_GET['format'] ?? 'png';

if ($format === 'json') {
    header('Content-Type: application/json');
    echo json_encode([
        'token'        => $token,
        'expires_at'   => $ts + 120,
        'h_coins'      => (int)($row['h_coins'] ?? 0),
        'display_name' => $row['display_name'] ?? '',
    ]);
    exit;
}

// PNG mode — generate QR image server-side
require_once __DIR__ . '/../includes/qrcode.php';

$png = QRCodeGenerator::png($token, 400);

header('Content-Type: image/png');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Expires: 0');
echo $png;
