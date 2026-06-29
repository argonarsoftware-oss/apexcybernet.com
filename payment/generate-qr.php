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

// Helper: build one EMVCo TLV field with a correctly computed 2-digit length.
function qrField(string $tag, string $value): string {
    return $tag . str_pad((string)strlen($value), 2, '0', STR_PAD_LEFT) . $value;
}

function buildQRPhPayload(float $amount): string {
    $payload  = '000201';   // payload format indicator
    $payload .= '010212';   // dynamic QR

    // Merchant account info (tag 27) — Tonik account. This is the field that
    // routes the money; it matches the account the Apex listener watches.
    $merchantInner  = '0012com.p2pqrpay';
    $merchantInner .= '0111TDBIPHM2XXX';        // Tonik bank BIC
    $merchantInner .= '020899964403';           // shared argonar merchant id
    $merchantInner .= '041460840747650001';     // Tonik account number
    $payload .= qrField('27', $merchantInner);

    $payload .= '52046016';   // MCC
    $payload .= '5303608';    // currency 608 = PHP

    $amtStr = number_format($amount, 2, '.', '');
    $payload .= qrField('54', $amtStr);   // transaction amount (unique-centavo)

    $payload .= '5802PH';                       // country
    $payload .= qrField('59', 'Apex Cybernet Softwr');  // merchant name (display only)
    $payload .= qrField('60', 'CEBU CITY');             // merchant city

    // Additional data (tag 62) — reference fields, mirrors the working Tonik QR.
    $additional  = '001447477334439830';
    $additional .= '051447477334439830';
    $additional .= '070868100529';
    $payload .= qrField('62', $additional);

    $payload .= '6304';                         // CRC tag + length placeholder
    $payload .= crc16ccitt($payload);

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
