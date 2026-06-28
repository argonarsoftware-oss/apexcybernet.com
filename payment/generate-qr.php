<?php
/**
 * Dynamic QR Ph (InstaPay/EMVCo) QR Code Generator
 * Usage: generate-qr.php?amount=500.37
 * Returns: PNG image
 */

$rawAmount = str_replace(',', '', $_GET['amount'] ?? '0');
$amount = (float)$rawAmount;
if ($amount <= 0 || $amount > 1000000) {
    http_response_code(400);
    echo 'Invalid amount';
    exit;
}

require_once __DIR__ . '/../includes/qrcode.php';

function buildQRPhPayload(float $amount): string {
    $payload = '';
    $payload .= '000201';
    $payload .= '010212';

    $merchantInner  = '0012com.p2pqrpay';
    $merchantInner .= '0111GXCHPHM2XXX';
    $merchantInner .= '020899964403';
    $merchantInner .= '0315217020000000656';
    $merchantInner .= '0417DWQM4TK3JDO90FAK9';
    $payload .= '27' . str_pad(strlen($merchantInner), 2, '0', STR_PAD_LEFT) . $merchantInner;

    $payload .= '52046016';
    $payload .= '5303608';

    $amtStr = number_format($amount, 2, '.', '');
    $payload .= '54' . str_pad(strlen($amtStr), 2, '0', STR_PAD_LEFT) . $amtStr;

    $payload .= '5802PH';
    $payload .= '5914Apex Cybernet Softwr';
    $payload .= '6008Inayawan';
    $payload .= '61041234';

    $payload .= '6304';
    $crc = crc16ccitt($payload);
    $payload .= $crc;

    return $payload;
}

function crc16ccitt(string $data): string {
    $crc = 0xFFFF;
    for ($i = 0; $i < strlen($data); $i++) {
        $crc ^= ord($data[$i]) << 8;
        for ($j = 0; $j < 8; $j++) {
            if ($crc & 0x8000) {
                $crc = (($crc << 1) ^ 0x1021) & 0xFFFF;
            } else {
                $crc = ($crc << 1) & 0xFFFF;
            }
        }
    }
    return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
}

$qrData = buildQRPhPayload($amount);
$png = QRCodeGenerator::png($qrData, 400);

header('Content-Type: image/png');
header('Cache-Control: public, max-age=2592000');
echo $png;
