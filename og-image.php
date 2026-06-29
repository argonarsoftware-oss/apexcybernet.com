<?php
// Generate OG share image (1200x630 PNG)
header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');

$w = 1200;
$h = 630;
$img = imagecreatetruecolor($w, $h);

// Colors
$bg = imagecolorallocate($img, 15, 15, 19);
$accent = imagecolorallocate($img, 167, 139, 250);
$gold = imagecolorallocate($img, 251, 191, 36);
$white = imagecolorallocate($img, 228, 228, 231);
$muted = imagecolorallocate($img, 156, 163, 175);
$purple_bg = imagecolorallocate($img, 26, 26, 46);
$card_bg = imagecolorallocate($img, 35, 35, 55);
$card_border = imagecolorallocate($img, 60, 60, 80);
$cta_bg = imagecolorallocate($img, 124, 58, 237);

// Background gradient (approximate with filled rects)
imagefill($img, 0, 0, $bg);
imagefilledrectangle($img, 0, 0, $w, $h / 2, $bg);
imagefilledrectangle($img, 0, $h / 2, $w, $h, $purple_bg);

// Try to use a good font, fallback to built-in
$font = __DIR__ . '/fonts/Inter-Bold.ttf';
$has_font = file_exists($font);

if ($has_font) {
    // Title
    $title = 'Fight for Glory';
    $bbox = imagettfbbox(56, 0, $font, $title);
    $tx = ($w - ($bbox[2] - $bbox[0])) / 2;
    imagettftext($img, 56, 0, (int)$tx, 200, $accent, $font, $title);

    // Subtitle
    $sub = 'Apex Cybernet Gaming Tournament  —  Dota 2';
    $bbox = imagettfbbox(22, 0, $font, $sub);
    $tx = ($w - ($bbox[2] - $bbox[0])) / 2;
    imagettftext($img, 22, 0, (int)$tx, 260, $muted, $font, $sub);

    // Detail boxes
    $details = ['5v5 Double Elim', 'P20,000 Prize', 'May 30, 2026'];
    $box_w = 280;
    $box_h = 50;
    $gap = 30;
    $total_w = (count($details) * $box_w) + ((count($details) - 1) * $gap);
    $start_x = ($w - $total_w) / 2;
    $box_y = 300;

    foreach ($details as $i => $text) {
        $bx = (int)($start_x + $i * ($box_w + $gap));
        imagefilledrectangle($img, $bx, $box_y, $bx + $box_w, $box_y + $box_h, $card_bg);
        imagerectangle($img, $bx, $box_y, $bx + $box_w, $box_y + $box_h, $card_border);
        $bbox = imagettfbbox(16, 0, $font, $text);
        $ttx = $bx + ($box_w - ($bbox[2] - $bbox[0])) / 2;
        imagettftext($img, 16, 0, (int)$ttx, $box_y + 32, $gold, $font, $text);
    }

    // Prize
    $prize = 'Win the cash and bragging rights for your squad';
    $bbox = imagettfbbox(18, 0, $font, $prize);
    $tx = ($w - ($bbox[2] - $bbox[0])) / 2;
    imagettftext($img, 18, 0, (int)$tx, 420, $gold, $font, $prize);

    // CTA button
    $cta = 'Register Now  -  apexcybernet.com';
    $bbox = imagettfbbox(17, 0, $font, $cta);
    $cta_w = ($bbox[2] - $bbox[0]) + 60;
    $cta_x = ($w - $cta_w) / 2;
    imagefilledrectangle($img, (int)$cta_x, 455, (int)($cta_x + $cta_w), 500, $cta_bg);
    $ttx = $cta_x + ($cta_w - ($bbox[2] - $bbox[0])) / 2;
    imagettftext($img, 17, 0, (int)$ttx, 485, $white, $font, $cta);

    // Brand
    $brand = 'Apex Cybernet';
    $bbox = imagettfbbox(13, 0, $font, $brand);
    $tx = ($w - ($bbox[2] - $bbox[0])) / 2;
    imagettftext($img, 13, 0, (int)$tx, 590, $muted, $font, $brand);
} else {
    // Fallback: simple text with built-in font
    $title = 'Fight for Glory';
    $tx = ($w - strlen($title) * 13) / 2;
    imagestring($img, 5, (int)$tx, 250, $title, $accent);

    $sub = 'Apex Cybernet Gaming Tournament - Dota 2 - apexcybernet.com';
    $tx = ($w - strlen($sub) * 9) / 2;
    imagestring($img, 4, (int)$tx, 300, $sub, $muted);

    $prize = 'P20,000 Prize | May 30, 2026';
    $tx = ($w - strlen($prize) * 9) / 2;
    imagestring($img, 4, (int)$tx, 340, $prize, $gold);
}

imagepng($img);
imagedestroy($img);
