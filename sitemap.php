<?php
require_once __DIR__ . '/includes/db.php';
header('Content-Type: application/xml; charset=utf-8');

function sm_url(string $loc, ?string $lastmod = null, string $changefreq = 'weekly', string $priority = '0.5'): string {
    $s  = "    <url>\n";
    $s .= "        <loc>" . htmlspecialchars($loc, ENT_XML1) . "</loc>\n";
    if ($lastmod) $s .= "        <lastmod>" . substr($lastmod, 0, 10) . "</lastmod>\n";
    $s .= "        <changefreq>$changefreq</changefreq>\n";
    $s .= "        <priority>$priority</priority>\n";
    $s .= "    </url>\n";
    return $s;
}

$base  = 'https://apexcybernet.com';
$today = date('Y-m-d');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// ── Homepage + static pages ──
echo sm_url("$base/",                $today, 'daily',   '1.0');

// ── Per-game register + matchmaking pages (each has distinct title/meta) ──
echo sm_url("$base/register.php?game=dota2",      $today, 'weekly', '0.9');
echo sm_url("$base/register.php?game=valorant",   $today, 'weekly', '0.6');
echo sm_url("$base/register.php?game=crossfire",  $today, 'weekly', '0.6');
echo sm_url("$base/matchmaking.php?game=dota2",   $today, 'weekly', '0.9');
echo sm_url("$base/matchmaking.php?game=valorant",$today, 'weekly', '0.6');
echo sm_url("$base/matchmaking.php?game=crossfire",$today,'weekly', '0.6');

echo sm_url("$base/bracket.php",     $today, 'hourly',  '0.9');
echo sm_url("$base/leaderboard.php", $today, 'hourly',  '0.8');
echo sm_url("$base/rules.php",       $today, 'monthly', '0.6');
echo sm_url("$base/contact.php",     $today, 'monthly', '0.5');
echo sm_url("$base/terms.php",       $today, 'yearly',  '0.3');
echo sm_url("$base/privacy.php",     $today, 'yearly',  '0.3');

// ── Completed matches (indexable for deep linking) ──
try {
    $rows = $pdo->query("
        SELECT id, updated_at
        FROM matches
        WHERE game = 'dota2' AND status = 'completed'
        ORDER BY updated_at DESC LIMIT 500
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        echo sm_url("$base/bracket.php#match-" . (int)$r['id'], $r['updated_at'] ?? $today, 'monthly', '0.4');
    }
} catch (Exception $e) {}

echo '</urlset>' . "\n";
