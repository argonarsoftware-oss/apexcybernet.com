<?php
/**
 * POST /api/track.php  — Omniscient Activity Tracker
 * Accepts batched events from apexcybernet.com and oslobcebuparagliding.com.
 * Enriches with: UA parsing, IP geolocation (ip-api.com + cache), UTM extraction.
 *
 * Payload: { sid, uid, sw, site, events: [{t, url, title, ref, tag, text, href, id, sd, top, lt, utm:{...}}, ...] }
 */
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error'=>'Method not allowed']); exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) { echo json_encode(['ok'=>false,'error'=>'Invalid JSON']); exit; }

$sid    = substr(preg_replace('/[^a-zA-Z0-9_\-]/', '', $body['sid'] ?? ''), 0, 64);
$uid    = (int)($body['uid'] ?? 0);
$sw     = isset($body['sw']) ? (int)$body['sw'] : null;
$site   = in_array($body['site'] ?? '', ['apexcybernet','ocpd','loan']) ? $body['site'] : 'apexcybernet';
$events = $body['events'] ?? [];

if (!$sid || !is_array($events) || !$events) {
    echo json_encode(['ok'=>false,'error'=>'Missing data']); exit;
}
$events = array_slice($events, 0, 40);

// ── Real IP ──
$ip = null;
foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $k) {
    if (!empty($_SERVER[$k])) { $ip = trim(explode(',', $_SERVER[$k])[0]); break; }
}
if ($ip) $ip = substr(trim($ip), 0, 45);

$ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512);

// ── UA Parsing ──
function parse_ua(string $ua): array {
    if (!$ua) return ['browser'=>null,'os'=>null,'device'=>'unknown'];
    if (preg_match('/bot|crawl|spider|slurp|bingbot|googlebot|yandex|baidu|facebookexternalhit|twitterbot|semrush|ahrefs|datanyze|petalbot/i', $ua))
        return ['browser'=>'Bot','os'=>'Bot','device'=>'bot'];

    $device = 'desktop';
    if (preg_match('/iPad/i', $ua)) $device = 'tablet';
    elseif (preg_match('/Mobile|Android(?!.*Tablet)|iPhone|iPod|Windows Phone|BlackBerry|IEMobile|Opera Mini/i', $ua)) $device = 'mobile';

    $os = 'Unknown';
    if      (preg_match('/iPhone OS ([\d_]+)/i', $ua, $m))    $os = 'iOS '.str_replace('_','.',$m[1]);
    elseif  (preg_match('/iPad.*OS ([\d_]+)/i',  $ua, $m))    $os = 'iPadOS '.str_replace('_','.',$m[1]);
    elseif  (preg_match('/Android ([\d.]+)/i',   $ua, $m))    $os = 'Android '.explode('.',$m[1])[0];
    elseif  (preg_match('/Windows NT ([\d.]+)/i',$ua, $m))    $os = ['10.0'=>'Windows 10/11','6.3'=>'Windows 8.1','6.2'=>'Windows 8','6.1'=>'Windows 7'][$m[1]] ?? 'Windows';
    elseif  (preg_match('/Mac OS X ([\d_]+)/i',  $ua, $m))    $os = 'macOS '.str_replace('_','.',$m[1]);
    elseif  (preg_match('/CrOS/i', $ua))                      $os = 'ChromeOS';
    elseif  (preg_match('/Linux/i', $ua))                     $os = 'Linux';

    $br = 'Unknown';
    if      (preg_match('/Edg[eA]?\/([\d]+)/i',       $ua, $m)) $br = 'Edge '.$m[1];
    elseif  (preg_match('/OPR\/([\d]+)/i',             $ua, $m)) $br = 'Opera '.$m[1];
    elseif  (preg_match('/SamsungBrowser\/([\d]+)/i',  $ua, $m)) $br = 'Samsung '.$m[1];
    elseif  (preg_match('/YaBrowser\/([\d]+)/i',       $ua, $m)) $br = 'Yandex '.$m[1];
    elseif  (preg_match('/Brave\/?([\d]*)/i',          $ua, $m)) $br = 'Brave';
    elseif  (preg_match('/Chrome\/([\d]+)/i',          $ua, $m)) $br = 'Chrome '.$m[1];
    elseif  (preg_match('/Firefox\/([\d]+)/i',         $ua, $m)) $br = 'Firefox '.$m[1];
    elseif  (preg_match('/Version\/([\d]+).*Safari/i', $ua, $m)) $br = 'Safari '.$m[1];
    elseif  (preg_match('/MSIE ([\d]+)|Trident.*rv:([\d]+)/i', $ua, $m)) $br = 'IE '.($m[1]?:$m[2]);

    return ['browser'=>$br,'os'=>$os,'device'=>$device];
}

// ── IP Geolocation (cached) ──
function geo_ip(PDO $pdo, ?string $ip): array {
    if (!$ip) return [null, null];
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return [null, null];
    try {
        $r = $pdo->prepare("SELECT country, city FROM ip_geo_cache WHERE ip = ? AND fetched_at > DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $r->execute([$ip]);
        if ($row = $r->fetch()) return [$row['country'], $row['city']];
    } catch (Exception $e) {}
    $country = $city = null;
    $ctx = stream_context_create(['http'=>['timeout'=>2,'ignore_errors'=>true,'user_agent'=>'ApexCybernetAnalytics/1.0']]);
    $raw = @file_get_contents("http://ip-api.com/json/{$ip}?fields=status,countryCode,city", false, $ctx);
    if ($raw) {
        $d = json_decode($raw, true);
        if (($d['status'] ?? '') === 'success') { $country = $d['countryCode'] ?? null; $city = $d['city'] ?? null; }
    }
    try {
        $pdo->prepare("INSERT INTO ip_geo_cache (ip,country,city,fetched_at) VALUES (?,?,?,NOW()) ON DUPLICATE KEY UPDATE country=?,city=?,fetched_at=NOW()")
            ->execute([$ip,$country,$city,$country,$city]);
    } catch (Exception $e) {}
    return [$country, $city];
}

// ── UTM from URL ──
function extract_utm(string $url): array {
    $q = parse_url($url, PHP_URL_QUERY) ?? '';
    parse_str($q, $p);
    return [
        isset($p['utm_source'])   ? substr($p['utm_source'],   0,100) : null,
        isset($p['utm_medium'])   ? substr($p['utm_medium'],   0,100) : null,
        isset($p['utm_campaign']) ? substr($p['utm_campaign'], 0,100) : null,
    ];
}

// Enrich once per request
$parsed = parse_ua($ua);
[$country, $city] = geo_ip($pdo, $ip);
$browser = $parsed['browser'];
$os      = $parsed['os'];
$device  = $parsed['device'];

$display_name = null;
if ($uid > 0) {
    try {
        $st = $pdo->prepare("SELECT display_name FROM accounts WHERE id = ?");
        $st->execute([$uid]);
        $display_name = $st->fetchColumn() ?: null;
    } catch (Exception $e) {}
}

$allowed = ['pageview','click','form','custom','scroll','timeonpage','error','chat'];
$placeholders = [];
$params = [];

foreach ($events as $ev) {
    if (!isset($ev['t'])) continue;
    $et  = in_array($ev['t'], $allowed) ? $ev['t'] : 'custom';
    $url = substr($ev['url']   ?? '', 0, 512);

    // UTM: inline utm{} object first, then parse URL
    $utm_s = $utm_m = $utm_c = null;
    if (!empty($ev['utm']) && is_array($ev['utm'])) {
        $utm_s = isset($ev['utm']['utm_source'])   ? substr($ev['utm']['utm_source'],   0,100) : null;
        $utm_m = isset($ev['utm']['utm_medium'])   ? substr($ev['utm']['utm_medium'],   0,100) : null;
        $utm_c = isset($ev['utm']['utm_campaign']) ? substr($ev['utm']['utm_campaign'], 0,100) : null;
    }
    if (!$utm_s && $url) [$utm_s, $utm_m, $utm_c] = extract_utm($url);

    $placeholders[] = "(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    array_push($params,
        $sid, $site,
        $uid > 0 ? $uid : null, $display_name,
        $et,
        $url,
        isset($ev['title']) ? substr($ev['title'], 0, 255) : null,
        isset($ev['tag'])   ? substr($ev['tag'],   0,  32) : null,
        isset($ev['text'])  ? substr($ev['text'],  0, 200) : null,
        isset($ev['href'])  ? substr($ev['href'],  0, 512) : null,
        isset($ev['id'])    ? substr($ev['id'],    0, 100) : null,
        isset($ev['ref'])   ? substr($ev['ref'],   0, 512) : null,
        $ip, $ua, $sw,
        $country, $city, $browser, $os, $device,
        $utm_s, $utm_m, $utm_c,
        isset($ev['sd'])  ? min(100,max(0,(int)$ev['sd'])) : null,
        isset($ev['top']) ? max(0,(int)$ev['top'])          : null,
        isset($ev['lt'])  ? min(60000,max(0,(int)$ev['lt'])): null
    );
}

if (empty($placeholders)) { echo json_encode(['ok'=>true,'inserted'=>0]); exit; }

try {
    $sql = "INSERT INTO activity_logs
        (session_id, site,
         account_id, display_name,
         event_type, page_url, page_title,
         element_tag, element_text, element_href, element_id, referrer,
         ip, user_agent, screen_w,
         country, city, browser, os, device_type,
         utm_source, utm_medium, utm_campaign,
         scroll_depth, time_on_page, load_time)
        VALUES " . implode(',', $placeholders);
    $pdo->prepare($sql)->execute($params);

    // ── Upsert user_graph (identity stitching) ──
    $batch_pv = 0; $batch_ev = count($events);
    $ug_utm_s = $ug_utm_m = $ug_utm_c = null;
    foreach ($events as $ev) {
        if (($ev['t'] ?? '') === 'pageview') {
            $batch_pv++;
            if (!$ug_utm_s) {
                $u = (!empty($ev['utm']) && is_array($ev['utm'])) ? $ev['utm'] : [];
                $ug_utm_s = isset($u['utm_source'])   ? substr($u['utm_source'],   0, 100) : null;
                $ug_utm_m = isset($u['utm_medium'])   ? substr($u['utm_medium'],   0, 100) : null;
                $ug_utm_c = isset($u['utm_campaign']) ? substr($u['utm_campaign'], 0, 100) : null;
                if (!$ug_utm_s && !empty($ev['url'])) [$ug_utm_s, $ug_utm_m, $ug_utm_c] = extract_utm($ev['url']);
            }
        }
    }
    try {
        $pdo->prepare("INSERT INTO user_graph
            (session_id, site, uid, display_name, ip, country, city, browser, os, device_type,
             utm_source, utm_medium, utm_campaign, page_count, event_count, first_seen, last_seen)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())
            ON DUPLICATE KEY UPDATE
            uid          = COALESCE(VALUES(uid), uid),
            display_name = COALESCE(VALUES(display_name), display_name),
            country      = COALESCE(country, VALUES(country)),
            city         = COALESCE(city, VALUES(city)),
            utm_source   = COALESCE(utm_source, VALUES(utm_source)),
            utm_medium   = COALESCE(utm_medium, VALUES(utm_medium)),
            utm_campaign = COALESCE(utm_campaign, VALUES(utm_campaign)),
            page_count   = page_count + VALUES(page_count),
            event_count  = event_count + VALUES(event_count),
            last_seen    = NOW()")
        ->execute([$sid, $site, $uid > 0 ? $uid : null, $display_name,
                   $ip, $country, $city, $browser, $os, $device,
                   $ug_utm_s, $ug_utm_m, $ug_utm_c, $batch_pv, $batch_ev]);
    } catch (Exception $uge) { error_log('[track:graph] ' . $uge->getMessage()); }

    // ── Opportunistic retention / auto-prune ──
    // ~1 in 200 requests triggers a small batched delete of rows older than 60 days.
    // Cheap (~1ms per 1000 rows with index on created_at), avoids cron dependency.
    if (mt_rand(1, 200) === 1) {
        try {
            $pdo->exec("DELETE FROM activity_logs
                WHERE created_at < DATE_SUB(NOW(), INTERVAL 60 DAY)
                  AND event_type != 'chat'
                LIMIT 1000");
            // Keep chat events 180 days (they're higher-signal for analytics)
            $pdo->exec("DELETE FROM activity_logs
                WHERE created_at < DATE_SUB(NOW(), INTERVAL 180 DAY)
                  AND event_type = 'chat'
                LIMIT 500");
        } catch (Exception $pe) { error_log('[track:prune] ' . $pe->getMessage()); }
    }

    echo json_encode(['ok'=>true,'inserted'=>count($placeholders)]);
} catch (Exception $e) {
    error_log('[track] '.$e->getMessage());
    echo json_encode(['ok'=>false,'error'=>'DB error']);
}
