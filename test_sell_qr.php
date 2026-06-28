<?php
require_once __DIR__ . '/includes/db.php';

if (($_GET['token'] ?? '') !== 'argonar-ws-test-2026') die('Forbidden');

header('Content-Type: application/json');

$uid    = (int)($_GET['uid'] ?? 8);   // buyer (kirfenia)
$mid    = (int)($_GET['mid'] ?? 14);  // merchant (kirfenia2)
$hcoins = (int)($_GET['hc'] ?? 10);

// Generate fresh BUY token
$ts        = time();
$sig       = hash_hmac('sha256', "BUY:{$uid}:{$hcoins}:{$ts}", QR_HMAC_SECRET);
$buy_token = "BUY:{$uid}:{$hcoins}:{$ts}:{$sig}";

function api_post($url, $payload) {
    $body = json_encode($payload);
    $ctx  = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => "Content-Type: application/json\r\nContent-Length: " . strlen($body),
        'content'       => $body,
        'timeout'       => 5,
        'ignore_errors' => true,
    ]]);
    return json_decode(@file_get_contents($url, false, $ctx), true);
}

$base = 'https://argonar.co/api/merchant-sell.php';

// Step 1: lookup
$lookup = api_post($base, ['action' => 'lookup', 'token' => $buy_token, 'merchant_id' => $mid]);

// Step 2: confirm (only if lookup succeeded and ?confirm=1)
$confirm = null;
if (!empty($lookup['ok']) && ($_GET['confirm'] ?? '0') === '1') {
    $confirm = api_post($base, ['action' => 'confirm', 'token' => $buy_token, 'merchant_id' => $mid]);
}

echo json_encode([
    'token'   => $buy_token,
    'lookup'  => $lookup,
    'confirm' => $confirm,
], JSON_PRETTY_PRINT);
