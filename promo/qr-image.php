<?php
/**
 * Outputs a large QR code PNG pointing to https://argonar.co
 * No auth required — public marketing asset.
 */
require_once __DIR__ . '/../includes/qrcode.php';

$url  = 'https://argonar.co';
$size = max(200, min(2000, (int)($_GET['size'] ?? 800)));

header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');
echo QRCodeGenerator::png($url, $size);
