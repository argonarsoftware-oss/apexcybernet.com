<?php
/**
 * Generates a PNG icon for the PWA at any requested size.
 * /icons/icon.php?size=192  or  ?size=512
 */
$size = max(48, min(1024, (int)($_GET['size'] ?? 192)));

// Cache for a week
header('Content-Type: image/png');
header('Cache-Control: public, max-age=604800');

$im  = imagecreatetruecolor($size, $size);
imagesavealpha($im, true);

// Background: #0f0f13 (app background color)
$bg = imagecolorallocate($im, 15, 15, 19);
imagefill($im, 0, 0, $bg);

// Draw rounded rect background (purple)
$r   = (int)($size * 0.22);
$pad = (int)($size * 0.12);
$col = imagecolorallocate($im, 124, 58, 237); // #7c3aed

// Fill rounded rectangle manually using arcs + rectangles
imagefilledrectangle($im, $pad + $r, $pad, $size - $pad - $r, $size - $pad, $col);
imagefilledrectangle($im, $pad, $pad + $r, $size - $pad, $size - $pad - $r, $col);
imagefilledellipse($im, $pad + $r, $pad + $r, $r * 2, $r * 2, $col);
imagefilledellipse($im, $size - $pad - $r, $pad + $r, $r * 2, $r * 2, $col);
imagefilledellipse($im, $pad + $r, $size - $pad - $r, $r * 2, $r * 2, $col);
imagefilledellipse($im, $size - $pad - $r, $size - $pad - $r, $r * 2, $r * 2, $col);

// Draw a bold "A" shape centered
$white = imagecolorallocate($im, 255, 255, 255);
$cx    = $size / 2;
$cy    = $size / 2;
$aw    = $size * 0.48; // total width of A
$ah    = $size * 0.5;  // total height of A
$sw    = $size * 0.09; // stroke width

$x1 = $cx - $aw / 2;
$x2 = $cx + $aw / 2;
$top = $cy - $ah / 2;
$bot = $cy + $ah / 2;

// Left leg of A
$pts_left = [
    $cx - $sw * 0.5, $top,           // top-center left
    $cx + $sw * 0.5, $top,           // top-center right
    $x2 - $sw * 0.5, $bot,           // bottom-right inner
    $x2,             $bot,           // bottom-right outer
    $x1 + $sw,       $bot,           // bottom-left outer... wait
    $x1,             $bot,           // bottom-left outer
    $x1 + $sw * 0.5, $bot,           // bottom-left inner
];

// Simpler: draw two thick lines for legs + crossbar using filled polygons

// Left leg
$lx1 = (int)$x1;
$lx2 = (int)($x1 + $sw);
$ll = [
    $cx - (int)($sw / 2), (int)$top,
    $cx + (int)($sw / 2), (int)$top,
    $lx2,                  (int)$bot,
    $lx1,                  (int)$bot,
];
imagefilledpolygon($im, $ll, 4, $white);

// Right leg
$rx1 = (int)($x2 - $sw);
$rx2 = (int)$x2;
$rl = [
    $cx - (int)($sw / 2), (int)$top,
    $cx + (int)($sw / 2), (int)$top,
    $rx2,                  (int)$bot,
    $rx1,                  (int)$bot,
];
imagefilledpolygon($im, $rl, 4, $white);

// Crossbar (horizontal bar ~45% from top)
$barY  = (int)($top + $ah * 0.52);
$barH  = (int)($sw * 0.75);
$barX1 = (int)($x1 + ($aw * 0.18));
$barX2 = (int)($x2 - ($aw * 0.18));
imagefilledrectangle($im, $barX1, $barY, $barX2, $barY + $barH, $white);

imagepng($im);
imagedestroy($im);
