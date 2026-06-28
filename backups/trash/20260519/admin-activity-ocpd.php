<?php
$active_site = 'ocpd';
$page_file   = 'activity-ocpd.php';
require_once __DIR__ . '/../includes/db.php';
$argonar_pdo = $pdo; // Preserve argonar_construction for Palantir + sidebar

require_once __DIR__ . '/omni/auth.php'; // auth first — _load_env defined here

// ── Connect to OCPD DB ──
$_ocpd_pdo_error = null;
$ocpd_env_candidates = [
    dirname(__DIR__, 2) . '/oslobparagliding/.env',
    dirname(__DIR__, 2) . '/oslobcebuparagliding.com/.env',
    '/var/www/oslobparagliding/.env',
    '/var/www/oslobcebuparagliding.com/.env',
];
$env = [];
foreach ($ocpd_env_candidates as $candidate) {
    if (file_exists($candidate)) { $env = _load_env($candidate); break; }
}
try {
    $ocpd_pdo = new PDO(
        "mysql:host=" . ($env['DB_HOST']??'localhost') . ";dbname=" . ($env['DB_NAME']??'oslobparagliding_db') . ";charset=utf8mb4",
        $env['DB_USER'] ?? 'root', $env['DB_PASS'] ?? '',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]
    );
} catch (PDOException $e) {
    $_ocpd_pdo_error = $e->getMessage();
    if (isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['ok'=>false,'error'=>'OCPD DB: ' . $_ocpd_pdo_error]); exit;
    }
    die('<pre>OCPD DB connection failed: ' . htmlspecialchars($_ocpd_pdo_error) . '</pre>');
}

// ── AJAX: session timeline ──
if (isset($_GET['ajax']) && $_GET['ajax'] === 'session') {
    header('Content-Type: application/json');
    $sid = substr(preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['sid'] ?? ''), 0, 64);
    if (!$sid) { echo json_encode([]); exit; }
    try {
        $st = $argonar_pdo->prepare("SELECT id, event_type, page_url, page_title, element_tag, element_text,
            element_href, element_id, referrer, ip, screen_w, created_at
            FROM activity_logs WHERE session_id = ? AND site='ocpd' ORDER BY id ASC LIMIT 200");
        $st->execute([$sid]);
        echo json_encode($st->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) { echo json_encode([]); }
    exit;
}

// ── AJAX: live feed ──
if (isset($_GET['ajax']) && $_GET['ajax'] === 'live') {
    header('Content-Type: application/json');
    $after = max(0, (int)($_GET['after'] ?? 0));
    try {
        $st = $argonar_pdo->prepare("SELECT id, session_id, account_id, display_name, event_type,
            page_url, page_title, element_tag, element_text, element_href, ip, created_at
            FROM activity_logs WHERE id > ? AND site='ocpd' ORDER BY id DESC LIMIT 30");
        $st->execute([$after]);
        echo json_encode($st->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) { echo json_encode([]); }
    exit;
}

// ── AJAX: retargeting ──
if (isset($_GET['ajax']) && $_GET['ajax'] === 'retarget') {
    header('Content-Type: application/json');
    $url     = trim($_GET['url'] ?? '');
    $dr      = $_GET['dr'] ?? 'all';
    $date_c  = match($dr) {
        'today' => "AND l.created_at >= CURDATE()",
        '7d'    => "AND l.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
        '30d'   => "AND l.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
        default => "",
    };
    if (!$url) { echo json_encode(['users'=>[],'guest_sessions'=>0]); exit; }
    try {
        $st = $argonar_pdo->prepare("SELECT l.account_id, l.display_name, a.email,
            COUNT(*) AS visits, MAX(l.created_at) AS last_seen
            FROM activity_logs l
            LEFT JOIN accounts a ON a.id = l.account_id
            WHERE l.account_id IS NOT NULL AND l.page_url LIKE ?
            AND l.site='ocpd'
            $date_c
            GROUP BY l.account_id, l.display_name, a.email
            ORDER BY visits DESC LIMIT 200");
        $st->execute(['%' . $url . '%']);
        $users = $st->fetchAll(PDO::FETCH_ASSOC);

        $gs = $argonar_pdo->prepare("SELECT COUNT(DISTINCT session_id) FROM activity_logs
            WHERE account_id IS NULL AND page_url LIKE ? AND site='ocpd' $date_c");
        $gs->execute(['%' . $url . '%']);
        $guest_sessions = (int)$gs->fetchColumn();

        echo json_encode(['users' => $users, 'guest_sessions' => $guest_sessions]);
    } catch (Exception $e) { echo json_encode(['users'=>[],'guest_sessions'=>0,'error'=>$e->getMessage()]); }
    exit;
}

// ── AJAX: OCPD bookings list ──
if (isset($_GET['ajax']) && $_GET['ajax'] === 'ocpd_bookings') {
    header('Content-Type: application/json');
    $status = in_array($_GET['status'] ?? '', ['pending','confirmed','cancelled']) ? $_GET['status'] : 'pending';
    $search = trim($_GET['q'] ?? '');
    $where  = "WHERE b.status = ?";
    $params = [$status];
    if ($search) { $where .= " AND (b.first_name LIKE ? OR b.last_name LIKE ? OR b.email LIKE ? OR b.reference_code LIKE ?)"; $s = "%$search%"; array_push($params, $s,$s,$s,$s); }
    try {
        $st = $ocpd_pdo->prepare("SELECT b.id, b.reference_code, b.first_name, b.last_name,
            b.email, b.mobile, b.event_date, b.event_time, b.total_passengers,
            b.booking_type, b.amount_paid, b.payment_type, b.status, b.admin_notes,
            b.notes, b.weight_kg, b.gender, b.created_at,
            b.discovery_source, b.referred_by,
            GROUP_CONCAT(p.name ORDER BY p.name SEPARATOR ', ') as packages
            FROM bookings b
            LEFT JOIN booking_packages bp ON bp.booking_id = b.id
            LEFT JOIN packages p ON p.id = bp.package_id
            $where GROUP BY b.id ORDER BY b.created_at DESC LIMIT 100");
        $st->execute($params);
        echo json_encode($st->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) { echo json_encode(['_error' => $e->getMessage()]); }
    exit;
}

// ── AJAX: OCPD booking action ──
if (isset($_GET['ajax']) && $_GET['ajax'] === 'booking_action') {
    header('Content-Type: application/json');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok'=>false]); exit; }
    $body   = json_decode(file_get_contents('php://input'), true) ?: [];
    $id     = (int)($body['id'] ?? 0);
    $action = $body['action'] ?? '';
    if (!$id) { echo json_encode(['ok'=>false,'error'=>'Missing ID']); exit; }
    try {
        if ($action === 'confirm') {
            $ocpd_pdo->prepare("UPDATE bookings SET status='confirmed', updated_at=NOW() WHERE id=?")->execute([$id]);
        } elseif ($action === 'cancel') {
            $ocpd_pdo->prepare("UPDATE bookings SET status='cancelled', updated_at=NOW() WHERE id=?")->execute([$id]);
        } elseif ($action === 'notes') {
            $notes = substr($body['notes'] ?? '', 0, 1000);
            $ocpd_pdo->prepare("UPDATE bookings SET admin_notes=?, updated_at=NOW() WHERE id=?")->execute([$notes, $id]);
        } else {
            echo json_encode(['ok'=>false,'error'=>'Unknown action']); exit;
        }
        echo json_encode(['ok'=>true]);
    } catch (Exception $e) { echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); }
    exit;
}

// ── AJAX: Facebook Segment CSV Download ──
if (isset($_GET['ajax']) && $_GET['ajax'] === 'fb_segment') {
    $seg = $_GET['seg'] ?? '';
    if (!in_array($seg, ['hot','warm','seed'])) { http_response_code(400); exit; }

    $rows = [];
    try {
        if ($seg === 'hot') {
            $st = $argonar_pdo->query("SELECT DISTINCT l.session_id, l.ip, l.country, l.city, l.device_type, l.browser,
                l.referrer, MAX(l.created_at) as last_seen, COUNT(*) as total_events
                FROM activity_logs l
                WHERE l.site = 'ocpd'
                AND l.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND l.session_id IN (
                  SELECT DISTINCT session_id FROM activity_logs
                  WHERE site='ocpd' AND (page_url LIKE '%book%' OR page_url LIKE '%reserv%' OR page_url LIKE '%contact%')
                  AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                )
                GROUP BY l.session_id, l.ip, l.country, l.city, l.device_type, l.browser, l.referrer
                ORDER BY last_seen DESC LIMIT 500");
            $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
        } elseif ($seg === 'warm') {
            $st = $argonar_pdo->query("SELECT session_id, ip, country, city, device_type, browser, referrer,
                COUNT(*) as pricing_views, MAX(created_at) as last_seen
                FROM activity_logs
                WHERE site='ocpd' AND event_type='pageview'
                AND (page_url LIKE '%price%' OR page_url LIKE '%package%' OR page_url LIKE '%rate%')
                AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY session_id, ip, country, city, device_type, browser, referrer
                HAVING pricing_views >= 2
                ORDER BY pricing_views DESC LIMIT 500");
            $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
        } elseif ($seg === 'seed') {
            $st = $argonar_pdo->query("SELECT session_id, ip, country, city, device_type, browser, referrer,
                COUNT(*) as events, MAX(created_at) as last_seen
                FROM activity_logs
                WHERE site='ocpd' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY session_id, ip, country, city, device_type, browser, referrer
                HAVING events >= 3
                ORDER BY events DESC LIMIT 500");
            $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
        }
    } catch (Exception $e) {}

    $filename = 'ocpd_fb_' . $seg . '_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['session_id_hash','country','city','device_type','browser','referrer','last_seen','events']);
    foreach ($rows as $r) {
        fputcsv($out, [
            substr(md5($r['session_id'] ?? ''), 0, 16),
            $r['country'] ?? '',
            $r['city'] ?? '',
            $r['device_type'] ?? '',
            $r['browser'] ?? '',
            $r['referrer'] ?? '',
            $r['last_seen'] ?? '',
            $r['total_events'] ?? $r['pricing_views'] ?? $r['events'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

// ── CSV Export ──
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $date_range   = $_GET['dr'] ?? 'today';
    $event_filter = $_GET['ev'] ?? 'all';
    $user_filter  = $_GET['uf'] ?? 'all';
    $search       = trim($_GET['q'] ?? '');
    $date_cond = match($date_range) {
        'today' => "AND created_at >= CURDATE()",
        '7d'    => "AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
        '30d'   => "AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
        default => "",
    };
    $ev_cond = ($event_filter !== 'all') ? "AND event_type = " . $argonar_pdo->quote($event_filter) : "";
    $uf_cond = match($user_filter) {
        'loggedin' => "AND account_id IS NOT NULL",
        'guest'    => "AND account_id IS NULL",
        default    => "",
    };
    $site_cond = "AND site='ocpd'";
    $search_cond = $search !== '' ? "AND (page_url LIKE ? OR display_name LIKE ? OR ip LIKE ?)" : '';
    $like = '%' . $search . '%';
    $params = $search !== '' ? [$like, $like, $like] : [];
    $where = "WHERE 1=1 $date_cond $ev_cond $uf_cond $site_cond $search_cond";

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="activity_ocpd_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Time','Type','Session','User','Display Name','Page URL','Page Title','Element','Href','Referrer','IP','Screen W']);
    try {
        $st = $argonar_pdo->prepare("SELECT id, created_at, event_type, session_id, account_id, display_name,
            page_url, page_title, element_text, element_href, referrer, ip, screen_w
            FROM activity_logs $where ORDER BY id DESC LIMIT 50000");
        $st->execute($params);
        while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, [
                $r['id'], $r['created_at'], $r['event_type'],
                substr($r['session_id'],0,12).'…',
                $r['account_id']??'guest', $r['display_name']??'',
                $r['page_url'], $r['page_title']??'',
                $r['element_text']??'', $r['element_href']??'',
                $r['referrer']??'', $r['ip']??'', $r['screen_w']??'',
            ]);
        }
    } catch (Exception $e) {}
    fclose($out);
    exit;
}

// ── Filters ──
$date_range   = $_GET['dr'] ?? 'today';
$event_filter = $_GET['ev'] ?? 'all';
$user_filter  = $_GET['uf'] ?? 'all';
$search       = trim($_GET['q'] ?? '');
$page         = max(1, (int)($_GET['p'] ?? 1));
$per_page     = 50;

$date_cond = match($date_range) {
    'today' => "AND created_at >= CURDATE()",
    '7d'    => "AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
    '30d'   => "AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
    default => "",
};
$prev_cond = match($date_range) {
    'today' => "AND created_at >= DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND created_at < CURDATE()",
    '7d'    => "AND created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)",
    '30d'   => "AND created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY) AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)",
    default => "AND 1=0",
};
$ev_cond = ($event_filter !== 'all') ? "AND event_type = " . $argonar_pdo->quote($event_filter) : "";
$uf_cond = match($user_filter) {
    'loggedin' => "AND account_id IS NOT NULL",
    'guest'    => "AND account_id IS NULL",
    default    => "",
};
$search_cond   = '';
$search_params = [];
if ($search !== '') {
    $search_cond   = "AND (page_url LIKE ? OR display_name LIKE ? OR ip LIKE ? OR element_text LIKE ?)";
    $like          = '%' . $search . '%';
    $search_params = [$like, $like, $like, $like];
}

// ── Sidebar quick-stats ──
$sidebar_stats = ['argonar'=>['sessions'=>0,'live'=>0],'ocpd'=>['sessions'=>0,'live'=>0],'loan'=>['sessions'=>0,'live'=>0],'alrisha'=>['sessions'=>0,'live'=>0]];
try {
    $rows_sb = $argonar_pdo->query("SELECT CASE WHEN site IS NULL OR site='' THEN 'argonar' ELSE site END as s,
        COUNT(DISTINCT session_id) as n FROM activity_logs WHERE created_at >= CURDATE() GROUP BY s")->fetchAll();
    foreach ($rows_sb as $r) if (isset($sidebar_stats[$r['s']])) $sidebar_stats[$r['s']]['sessions'] = (int)$r['n'];
    $rows_sb = $argonar_pdo->query("SELECT CASE WHEN site IS NULL OR site='' THEN 'argonar' ELSE site END as s,
        COUNT(DISTINCT session_id) as n FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) GROUP BY s")->fetchAll();
    foreach ($rows_sb as $r) if (isset($sidebar_stats[$r['s']])) $sidebar_stats[$r['s']]['live'] = (int)$r['n'];
} catch (Exception $e) {}

// ── OCPD Booking counts ──
$ocpd_booking_counts = ['pending'=>0,'confirmed'=>0,'cancelled'=>0];
try {
    $bc = $ocpd_pdo->query("SELECT status, COUNT(*) as n FROM bookings GROUP BY status")->fetchAll();
    foreach ($bc as $r) if (isset($ocpd_booking_counts[$r['status']])) $ocpd_booking_counts[$r['status']] = (int)$r['n'];
} catch (Exception $e) {}

// ── FB Ads Intelligence Data ──

// Sub-panel A: Booking Funnel
$fb_funnel = ['awareness'=>0,'interest'=>0,'intent'=>0,'conversion'=>0];
try {
    $fb_funnel['awareness'] = (int)$argonar_pdo->query("SELECT COUNT(DISTINCT session_id) FROM activity_logs
        WHERE site='ocpd' AND event_type='pageview'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
    $fb_funnel['interest'] = (int)$argonar_pdo->query("SELECT COUNT(DISTINCT session_id) FROM activity_logs
        WHERE site='ocpd' AND event_type='pageview'
        AND (page_url LIKE '%price%' OR page_url LIKE '%package%' OR page_url LIKE '%rate%' OR page_url LIKE '%about%')
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
    $fb_funnel['intent'] = (int)$argonar_pdo->query("SELECT COUNT(DISTINCT session_id) FROM activity_logs
        WHERE site='ocpd' AND event_type='pageview'
        AND (page_url LIKE '%book%' OR page_url LIKE '%reserv%' OR page_url LIKE '%contact%')
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
} catch (Exception $e) {}
try {
    $fb_funnel['conversion'] = (int)$ocpd_pdo->query("SELECT COUNT(*) FROM bookings
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND status != 'cancelled'")->fetchColumn();
} catch (Exception $e) {}

// Compute funnel %
$fb_awareness = max(1, $fb_funnel['awareness']);
$fb_interest_pct  = round($fb_funnel['interest']  / $fb_awareness * 100, 1);
$fb_intent_pct    = round($fb_funnel['intent']     / $fb_awareness * 100, 1);
$fb_conversion_pct = round($fb_funnel['conversion'] / $fb_awareness * 100, 1);

// Sub-panel B: Retargeting segment counts
$fb_seg_counts = ['hot'=>0,'warm'=>0,'seed'=>0];
try {
    $fb_seg_counts['hot'] = (int)$argonar_pdo->query("SELECT COUNT(DISTINCT session_id) FROM activity_logs
        WHERE site='ocpd' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND session_id IN (
          SELECT DISTINCT session_id FROM activity_logs
          WHERE site='ocpd' AND (page_url LIKE '%book%' OR page_url LIKE '%reserv%' OR page_url LIKE '%contact%')
          AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        )")->fetchColumn();
} catch (Exception $e) {}
try {
    $fb_seg_counts['warm'] = (int)$argonar_pdo->query("SELECT COUNT(*) FROM (
        SELECT session_id FROM activity_logs
        WHERE site='ocpd' AND event_type='pageview'
        AND (page_url LIKE '%price%' OR page_url LIKE '%package%' OR page_url LIKE '%rate%')
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY session_id HAVING COUNT(*) >= 2) t")->fetchColumn();
} catch (Exception $e) {}
try {
    $fb_seg_counts['seed'] = (int)$argonar_pdo->query("SELECT COUNT(*) FROM (
        SELECT session_id FROM activity_logs
        WHERE site='ocpd' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY session_id HAVING COUNT(*) >= 3) t")->fetchColumn();
} catch (Exception $e) {}

// Segment profile summaries
$fb_seg_profiles = [];
foreach (['hot','warm','seed'] as $seg) {
    $profile = ['top_country'=>'—','top_device'=>'—','avg_depth'=>0];
    try {
        $field = $seg === 'hot' ? 'total_events' : ($seg === 'warm' ? 'pricing_views' : 'ev');
        if ($seg === 'hot') {
            $qr = $argonar_pdo->query("SELECT country, COUNT(*) as n FROM activity_logs
                WHERE site='ocpd' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND session_id IN (SELECT DISTINCT session_id FROM activity_logs WHERE site='ocpd'
                  AND (page_url LIKE '%book%' OR page_url LIKE '%reserv%' OR page_url LIKE '%contact%')
                  AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY))
                AND country IS NOT NULL GROUP BY country ORDER BY n DESC LIMIT 1");
            $r = $qr ? $qr->fetch() : null;
            if ($r) $profile['top_country'] = $r['country'];
            $qr2 = $argonar_pdo->query("SELECT device_type, COUNT(*) as n FROM activity_logs
                WHERE site='ocpd' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND session_id IN (SELECT DISTINCT session_id FROM activity_logs WHERE site='ocpd'
                  AND (page_url LIKE '%book%' OR page_url LIKE '%reserv%' OR page_url LIKE '%contact%')
                  AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY))
                AND device_type IS NOT NULL GROUP BY device_type ORDER BY n DESC LIMIT 1");
            $r2 = $qr2 ? $qr2->fetch() : null;
            if ($r2) $profile['top_device'] = $r2['device_type'];
        } elseif ($seg === 'warm') {
            $qr = $argonar_pdo->query("SELECT country, COUNT(*) as n FROM activity_logs
                WHERE site='ocpd' AND event_type='pageview'
                AND (page_url LIKE '%price%' OR page_url LIKE '%package%' OR page_url LIKE '%rate%')
                AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND country IS NOT NULL GROUP BY country ORDER BY n DESC LIMIT 1");
            $r = $qr ? $qr->fetch() : null;
            if ($r) $profile['top_country'] = $r['country'];
            $qr2 = $argonar_pdo->query("SELECT device_type, COUNT(*) as n FROM activity_logs
                WHERE site='ocpd' AND event_type='pageview'
                AND (page_url LIKE '%price%' OR page_url LIKE '%package%' OR page_url LIKE '%rate%')
                AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND device_type IS NOT NULL GROUP BY device_type ORDER BY n DESC LIMIT 1");
            $r2 = $qr2 ? $qr2->fetch() : null;
            if ($r2) $profile['top_device'] = $r2['device_type'];
        } else {
            $qr = $argonar_pdo->query("SELECT country, COUNT(*) as n FROM activity_logs
                WHERE site='ocpd' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND country IS NOT NULL GROUP BY country ORDER BY n DESC LIMIT 1");
            $r = $qr ? $qr->fetch() : null;
            if ($r) $profile['top_country'] = $r['country'];
            $qr2 = $argonar_pdo->query("SELECT device_type, COUNT(*) as n FROM activity_logs
                WHERE site='ocpd' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND device_type IS NOT NULL GROUP BY device_type ORDER BY n DESC LIMIT 1");
            $r2 = $qr2 ? $qr2->fetch() : null;
            if ($r2) $profile['top_device'] = $r2['device_type'];
        }
    } catch (Exception $e) {}
    $fb_seg_profiles[$seg] = $profile;
}

// Sub-panel C: Ad Timing Heatmap
$fb_heatmap = [];
try {
    $st = $argonar_pdo->query("SELECT HOUR(created_at) as hr, DAYOFWEEK(created_at) as dow,
        COUNT(DISTINCT session_id) as sessions
        FROM activity_logs
        WHERE site='ocpd' AND event_type='pageview'
        AND (page_url LIKE '%book%' OR page_url LIKE '%price%' OR page_url LIKE '%package%')
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY hr, dow");
    if ($st) {
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $fb_heatmap[(int)$r['hr']][(int)$r['dow']] = (int)$r['sessions'];
        }
    }
} catch (Exception $e) {}
$fb_hm_max = 1;
foreach ($fb_heatmap as $hrs) foreach ($hrs as $v) if ($v > $fb_hm_max) $fb_hm_max = $v;

// Find top 3 hour-day combos for ad timing insight
$fb_timing_flat = [];
foreach ($fb_heatmap as $hr => $days) {
    foreach ($days as $dow => $sess) {
        $fb_timing_flat[] = ['hr'=>$hr,'dow'=>$dow,'sess'=>$sess];
    }
}
usort($fb_timing_flat, fn($a,$b) => $b['sess'] - $a['sess']);
$fb_timing_top3 = array_slice($fb_timing_flat, 0, 3);
$dow_names = [1=>'Sun',2=>'Mon',3=>'Tue',4=>'Wed',5=>'Thu',6=>'Fri',7=>'Sat'];

// Sub-panel D: Ad Copy Suggestions
$fb_top_clicks = [];
try {
    $st = $argonar_pdo->query("SELECT COALESCE(NULLIF(element_text,''), element_href, '?') as label, COUNT(*) as clicks
        FROM activity_logs
        WHERE site='ocpd' AND event_type='click'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND element_text IS NOT NULL AND element_text != ''
        GROUP BY label ORDER BY clicks DESC LIMIT 5");
    $fb_top_clicks = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) {}

$fb_top_pages = [];
try {
    $st = $argonar_pdo->query("SELECT page_url, ROUND(AVG(time_on_page)) as avg_sec, COUNT(*) as cnt
        FROM activity_logs
        WHERE site='ocpd' AND event_type='timeonpage'
        AND time_on_page > 0 AND time_on_page < 3600
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY page_url ORDER BY avg_sec DESC LIMIT 5");
    $fb_top_pages = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) {}

$fb_top_refs = [];
try {
    $st = $argonar_pdo->query("SELECT REGEXP_REPLACE(referrer, '^https?://(www\\.)?([^/]+).*', '\\\\2') as src,
        COUNT(DISTINCT session_id) as sessions
        FROM activity_logs
        WHERE site='ocpd' AND referrer IS NOT NULL AND referrer != ''
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY src ORDER BY sessions DESC LIMIT 5");
    $fb_top_refs = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) {}

// ── Facebook Boosted Post Ads Maker data ──
$fb_ad_top_country  = $fb_seg_profiles['seed']['top_country'] ?? '—';
$fb_ad_top_device   = $fb_seg_profiles['seed']['top_device']  ?? 'mobile';
$fb_ad_total_sess   = $fb_funnel['awareness'];
$fb_ad_hot_count    = $fb_seg_counts['hot'];
$fb_ad_warm_count   = $fb_seg_counts['warm'];
$fb_ad_top_cta      = !empty($fb_top_clicks)  ? $fb_top_clicks[0]['label']   : 'Book Now';
$fb_ad_top_page     = !empty($fb_top_pages)   ? basename($fb_top_pages[0]['page_url'], '.php') : 'pricing';
$fb_ad_top_ref      = !empty($fb_top_refs)    ? $fb_top_refs[0]['src']       : 'facebook.com';

// Build timing recommendation
$fb_ad_best_times = [];
foreach ($fb_timing_top3 as $t) {
    $h = (int)$t['hr'];
    $ampm = $h < 12 ? ($h === 0 ? '12am' : $h.'am') : ($h === 12 ? '12pm' : ($h-12).'pm');
    $day_short = $dow_names[$t['dow']] ?? '';
    $fb_ad_best_times[] = $day_short . ' ' . $ampm;
}
$fb_ad_timing_str = !empty($fb_ad_best_times) ? implode(', ', $fb_ad_best_times) : 'weekday evenings';

// Compute UTM links for this ads maker
$ocpd_utm_awareness   = 'https://oslobcebuparagliding.com/?utm_source=fb&utm_medium=paid&utm_campaign=aw';
$ocpd_utm_retarget    = 'https://oslobcebuparagliding.com/booking?utm_source=fb&utm_medium=paid&utm_campaign=ret';
$ocpd_utm_conversion  = 'https://oslobcebuparagliding.com/booking?utm_source=fb&utm_medium=paid&utm_campaign=conv';

// ── Sales & Metrics ──
// Sales price = full ticket price (sum of package prices × passengers) − voucher discount.
// Always uses the booking's actual price, NEVER the partial amount_paid (downpayment).
// Mirrors the formula used by oslobcebuparagliding.com/admin/dashboard.php.
$pkgJoin = "LEFT JOIN (
    SELECT bp.booking_id, SUM(p.price) AS total_price
    FROM booking_packages bp
    JOIN packages p ON p.id = bp.package_id
    GROUP BY bp.booking_id
) pkg ON pkg.booking_id = b.id";

// Use voucher_discount column if it exists; otherwise treat as 0
$has_voucher = false;
try {
    $bk_cols = $ocpd_pdo->query("SHOW COLUMNS FROM bookings")->fetchAll(PDO::FETCH_COLUMN);
    $has_voucher = in_array('voucher_discount', $bk_cols);
} catch (Exception $e) {}
$discountExpr = $has_voucher ? 'COALESCE(b.voucher_discount, 0)' : '0';
$salesExpr    = "GREATEST(COALESCE(pkg.total_price, 0) * GREATEST(COALESCE(b.total_passengers, 1), 1) - $discountExpr, 0)";

$sales_kpis = ['total_revenue'=>0,'revenue_this_month'=>0,'bookings_total'=>0,'bookings_this_month'=>0,'avg_pax'=>0,'cancellation_rate'=>0,'avg_value'=>0];
try {
    $r = $ocpd_pdo->query("SELECT
        COALESCE(SUM(CASE WHEN b.status='confirmed' THEN $salesExpr ELSE 0 END),0) AS total_revenue,
        COALESCE(SUM(CASE WHEN b.status='confirmed' AND YEAR(b.created_at)=YEAR(NOW()) AND MONTH(b.created_at)=MONTH(NOW()) THEN $salesExpr ELSE 0 END),0) AS revenue_this_month,
        COUNT(*) AS bookings_total,
        SUM(CASE WHEN YEAR(b.created_at)=YEAR(NOW()) AND MONTH(b.created_at)=MONTH(NOW()) THEN 1 ELSE 0 END) AS bookings_this_month,
        ROUND(AVG(b.total_passengers),1) AS avg_pax,
        ROUND(100*SUM(CASE WHEN b.status='cancelled' THEN 1 ELSE 0 END)/COUNT(*),1) AS cancellation_rate,
        ROUND(AVG(CASE WHEN b.status='confirmed' AND $salesExpr > 0 THEN $salesExpr END),0) AS avg_value
        FROM bookings b $pkgJoin")->fetch();
    if ($r) $sales_kpis = array_merge($sales_kpis, array_map(fn($v) => $v ?? 0, $r));
} catch (Exception $e) {}

$revenue_by_month = [];
try {
    $st = $ocpd_pdo->query("SELECT DATE_FORMAT(b.created_at,'%b %Y') AS month_label,
        DATE_FORMAT(b.created_at,'%Y-%m') AS month_key,
        SUM(CASE WHEN b.status='confirmed' THEN 1 ELSE 0 END) AS confirmed_cnt,
        COALESCE(SUM(CASE WHEN b.status='confirmed' THEN $salesExpr ELSE 0 END),0) AS revenue
        FROM bookings b $pkgJoin
        WHERE b.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY month_key, month_label ORDER BY month_key ASC");
    $revenue_by_month = $st ? $st->fetchAll() : [];
} catch (Exception $e) {}

$top_packages = [];
try {
    // For per-package revenue, attribute each booking's full sales price equally per package
    $st = $ocpd_pdo->query("SELECT p.name,
        COUNT(DISTINCT b.id) AS bookings,
        COALESCE(SUM($salesExpr / NULLIF((SELECT COUNT(*) FROM booking_packages bp2 WHERE bp2.booking_id = b.id),0)),0) AS revenue
        FROM bookings b
        $pkgJoin
        JOIN booking_packages bp ON bp.booking_id = b.id
        JOIN packages p ON p.id = bp.package_id
        WHERE b.status='confirmed'
        GROUP BY p.id, p.name ORDER BY bookings DESC LIMIT 6");
    $top_packages = $st ? $st->fetchAll() : [];
} catch (Exception $e) {}

$payment_breakdown = [];
try {
    $st = $ocpd_pdo->query("SELECT COALESCE(NULLIF(b.payment_type,''),'Unknown') AS ptype,
        COUNT(*) AS cnt, COALESCE(SUM($salesExpr),0) AS revenue
        FROM bookings b $pkgJoin
        WHERE b.status='confirmed'
        GROUP BY ptype ORDER BY cnt DESC");
    $payment_breakdown = $st ? $st->fetchAll() : [];
} catch (Exception $e) {}

// ── Found Us Via — direct from OCPD bookings table (column: discovery_source) ──
$found_us_via = [];
try {
    $st = $ocpd_pdo->query("SELECT
        COALESCE(NULLIF(TRIM(b.discovery_source),''), 'Not specified') AS source,
        COUNT(*) AS bookings,
        SUM(CASE WHEN b.status='confirmed' THEN 1 ELSE 0 END) AS confirmed,
        COALESCE(SUM(CASE WHEN b.status='confirmed' THEN $salesExpr ELSE 0 END),0) AS revenue
        FROM bookings b $pkgJoin
        GROUP BY source ORDER BY bookings DESC");
    $found_us_via = $st ? $st->fetchAll() : [];
} catch (Exception $e) {}

$found_us_via_total = max(1, array_sum(array_column($found_us_via, 'bookings')));

// Source → color map
$fv_colors = [
    'Facebook'      => '#60a5fa',
    'Google'        => '#34d399',
    'Instagram'     => '#f472b6',
    'TikTok'        => '#f87171',
    'YouTube'       => '#fbbf24',
    'Direct / None' => '#9ca3af',
    'Argonar'       => '#a78bfa',
    'Twitter / X'   => '#38bdf8',
    'Friend'        => '#fb923c',
    'Word of Mouth' => '#fb923c',
    'Not specified' => '#374151',
    'Other'         => '#6b7280',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>OCPD Analytics — Omniscient</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<?php require __DIR__ . '/omni/css.php'; ?>
</head>
<body>
<div class="omni-layout">

<?php require __DIR__ . '/omni/sidebar.php'; ?>

<div class="omni-main">

<?php require __DIR__ . '/omni/analytics.php'; ?>

<!-- ══ Facebook Ads Intelligence ══ -->
<div style="padding: 0 1.5rem 1rem;">
<style>
.fb-seg-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; }
@media (max-width: 768px) { .fb-seg-grid { grid-template-columns: 1fr; } }
.fb-seg-card { background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); border-radius: 10px; padding: 1rem; }
.ad-copy-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; }
@media (max-width: 768px) { .ad-copy-grid { grid-template-columns: 1fr; } }
.ad-copy-col { background: rgba(96,165,250,0.05); border: 1px solid rgba(96,165,250,0.15); border-radius: 10px; padding: 0.85rem; }
.ad-suggestion { background: rgba(96,165,250,0.08); border-left: 3px solid #60a5fa; border-radius: 0 6px 6px 0; padding: 0.5rem 0.65rem; margin-top: 0.75rem; font-size: 0.75rem; color: #93c5fd; font-style: italic; }
.fb-funnel-row { display:flex; align-items:center; gap:0.75rem; padding:0.4rem 0; }
.fb-funnel-label { font-size:0.76rem; font-weight:700; color:#9ca3af; width:90px; flex-shrink:0; }
.fb-funnel-bar-wrap { flex:1; height:8px; background:rgba(255,255,255,0.06); border-radius:99px; overflow:hidden; }
.fb-funnel-bar-fill { height:100%; border-radius:99px; }
.fb-funnel-num { font-size:0.74rem; font-weight:800; color:#e5e7eb; width:48px; text-align:right; flex-shrink:0; }
.fb-funnel-pct { font-size:0.67rem; color:#6b7280; width:52px; text-align:right; flex-shrink:0; }
.fb-hm-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:2px; }
.fb-hm-cell { height:18px; border-radius:3px; background:rgba(96,165,250,0.04); transition:background 0.2s; position:relative; cursor:default; }
.fb-hm-cell:hover::after { content:attr(data-tip); position:absolute; bottom:110%; left:50%; transform:translateX(-50%);
    background:#1e1e28; border:1px solid rgba(255,255,255,0.1); color:#e5e7eb; font-size:0.62rem;
    padding:2px 6px; border-radius:4px; white-space:nowrap; pointer-events:none; z-index:10; }
.fb-hm-dow-labels { display:grid; grid-template-columns:repeat(7,1fr); gap:2px; margin-bottom:3px; }
.fb-hm-dow-lbl { font-size:0.57rem; color:#4b5563; text-align:center; }
</style>

<div class="palantir-section">
    <div class="palantir-header" onclick="palToggle(this)">
        <i class="bi bi-meta pal-icon" style="color:#60a5fa;"></i>
        <span>Facebook Ads Intelligence</span>
        <span class="pal-badge" style="background:rgba(96,165,250,0.15);color:#60a5fa;">OCPD</span>
        <i class="bi bi-chevron-down pal-toggle"></i>
    </div>
    <div class="palantir-body">

    <!-- Sub-panel A: Booking Funnel -->
    <div style="margin-bottom:1.5rem;">
        <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:#6b7280;margin-bottom:0.75rem;">
            <i class="bi bi-funnel" style="color:#60a5fa;"></i> Booking Funnel — Awareness → Conversion
        </div>
        <div class="fb-funnel-row">
            <span class="fb-funnel-label">Awareness</span>
            <div class="fb-funnel-bar-wrap"><div class="fb-funnel-bar-fill" style="width:100%;background:#60a5fa;"></div></div>
            <span class="fb-funnel-num"><?= number_format($fb_funnel['awareness']) ?></span>
            <span class="fb-funnel-pct">100%</span>
        </div>
        <div class="fb-funnel-row">
            <span class="fb-funnel-label">Interest</span>
            <div class="fb-funnel-bar-wrap"><div class="fb-funnel-bar-fill" style="width:<?= min(100,$fb_interest_pct) ?>%;background:#a78bfa;"></div></div>
            <span class="fb-funnel-num"><?= number_format($fb_funnel['interest']) ?></span>
            <span class="fb-funnel-pct"><?= $fb_interest_pct ?>%</span>
        </div>
        <div class="fb-funnel-row">
            <span class="fb-funnel-label">Intent</span>
            <div class="fb-funnel-bar-wrap"><div class="fb-funnel-bar-fill" style="width:<?= min(100,$fb_intent_pct) ?>%;background:#fbbf24;"></div></div>
            <span class="fb-funnel-num"><?= number_format($fb_funnel['intent']) ?></span>
            <span class="fb-funnel-pct"><?= $fb_intent_pct ?>%</span>
        </div>
        <div class="fb-funnel-row">
            <span class="fb-funnel-label">Conversion</span>
            <div class="fb-funnel-bar-wrap"><div class="fb-funnel-bar-fill" style="width:<?= min(100,$fb_conversion_pct) ?>%;background:#34d399;"></div></div>
            <span class="fb-funnel-num"><?= number_format($fb_funnel['conversion']) ?></span>
            <span class="fb-funnel-pct"><?= $fb_conversion_pct ?>%</span>
        </div>
        <?php
        // Find biggest drop
        $drops = [
            'Awareness → Interest' => $fb_interest_pct,
            'Interest → Intent'    => ($fb_funnel['interest'] > 0 ? round($fb_funnel['intent']/$fb_funnel['interest']*100,1) : 0),
            'Intent → Conversion'  => ($fb_funnel['intent'] > 0 ? round($fb_funnel['conversion']/$fb_funnel['intent']*100,1) : 0),
        ];
        $worst_stage = array_search(min($drops), $drops);
        $int_to_intent_pct = $drops['Interest → Intent'];
        ?>
        <div style="background:rgba(96,165,250,0.06);border-left:3px solid #60a5fa;border-radius:0 8px 8px 0;padding:0.65rem 0.9rem;margin-top:0.9rem;font-size:0.78rem;color:#93c5fd;">
            <strong style="color:#60a5fa;"><?= $fb_interest_pct ?>%</strong> of visitors show interest in pricing.
            Only <strong style="color:#34d399;"><?= $fb_conversion_pct ?>%</strong> convert to bookings.
            The biggest drop is <strong style="color:#fbbf24;"><?= htmlspecialchars($worst_stage) ?></strong> —
            <?php if ($worst_stage === 'Interest → Intent'): ?>your booking page may have friction. Consider a clearer CTA.
            <?php elseif ($worst_stage === 'Awareness → Interest'): ?>visitors aren't engaging with pricing pages. Add pricing highlights to your homepage.
            <?php else: ?>the final step is leaking. Reduce booking form friction or add urgency.<?php endif; ?>
        </div>
    </div>

    <!-- Sub-panel B: Retargeting Audiences -->
    <div style="margin-bottom:1.5rem;">
        <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:#6b7280;margin-bottom:0.75rem;">
            <i class="bi bi-people" style="color:#a78bfa;"></i> Retargeting Audiences
        </div>
        <div class="fb-seg-grid">
            <?php
            $segs = [
                'hot'  => ['name'=>'Hot Leads',        'desc'=>'Visited booking/contact page in last 30d', 'color'=>'#f87171', 'icon'=>'bi-fire'],
                'warm' => ['name'=>'Warm Audience',    'desc'=>'Viewed pricing/packages 2+ times',         'color'=>'#fbbf24', 'icon'=>'bi-thermometer-half'],
                'seed' => ['name'=>'Lookalike Seed',   'desc'=>'Engaged visitors (3+ events)',             'color'=>'#34d399', 'icon'=>'bi-diagram-3'],
            ];
            foreach ($segs as $seg_key => $seg_info):
                $cnt = $fb_seg_counts[$seg_key];
                $prof = $fb_seg_profiles[$seg_key];
            ?>
            <div class="fb-seg-card">
                <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.5rem;">
                    <i class="bi <?= $seg_info['icon'] ?>" style="color:<?= $seg_info['color'] ?>;"></i>
                    <span style="font-size:0.82rem;font-weight:800;color:#e5e7eb;"><?= $seg_info['name'] ?></span>
                </div>
                <div style="font-size:1.6rem;font-weight:900;color:<?= $seg_info['color'] ?>;line-height:1;margin-bottom:0.25rem;"><?= number_format($cnt) ?></div>
                <div style="font-size:0.68rem;color:#6b7280;margin-bottom:0.65rem;"><?= $seg_info['desc'] ?></div>
                <div style="font-size:0.69rem;color:#9ca3af;margin-bottom:0.6rem;">
                    <?php if ($prof['top_country'] !== '—'): ?>
                    <span style="margin-right:0.5rem;">Top: <?= htmlspecialchars($prof['top_country']) ?></span>
                    <?php endif; ?>
                    <?php if ($prof['top_device'] !== '—'): ?>
                    <span><?= htmlspecialchars(ucfirst($prof['top_device'])) ?></span>
                    <?php endif; ?>
                </div>
                <button onclick="fbDownloadSeg('<?= $seg_key ?>')" style="background:rgba(96,165,250,0.1);color:#60a5fa;border:1px solid rgba(96,165,250,0.3);border-radius:7px;padding:0.28rem 0.7rem;font-size:0.72rem;font-weight:700;cursor:pointer;">
                    <i class="bi bi-download"></i> Download CSV
                </button>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Sub-panel C: Ad Timing Heatmap -->
    <div style="margin-bottom:1.5rem;">
        <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:#6b7280;margin-bottom:0.75rem;">
            <i class="bi bi-clock-history" style="color:#fbbf24;"></i> Ad Timing Heatmap — Booking Intent Traffic (Last 30d)
        </div>
        <div class="fb-hm-dow-labels">
            <?php foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d): ?>
            <div class="fb-hm-dow-lbl"><?= $d ?></div>
            <?php endforeach; ?>
        </div>
        <?php for($hr = 6; $hr <= 23; $hr++):
            $hr_label = $hr < 12 ? $hr.'am' : ($hr === 12 ? '12pm' : ($hr-12).'pm');
        ?>
        <div style="display:flex;align-items:center;gap:3px;margin-bottom:2px;">
            <span style="font-size:0.52rem;color:#4b5563;width:26px;flex-shrink:0;text-align:right;"><?= $hr_label ?></span>
            <div class="fb-hm-grid" style="flex:1;">
                <?php
                // dow: 1=Sun,2=Mon,...,7=Sat → reorder to Mon-Sun = 2,3,4,5,6,7,1
                $dow_order = [2,3,4,5,6,7,1];
                foreach ($dow_order as $dow):
                    $val = $fb_heatmap[$hr][$dow] ?? 0;
                    $intensity = $fb_hm_max > 0 ? $val / $fb_hm_max : 0;
                    $alpha = round($intensity * 0.85, 2);
                    $dow_name_map = [1=>'Sun',2=>'Mon',3=>'Tue',4=>'Wed',5=>'Thu',6=>'Fri',7=>'Sat'];
                    $tip = $dow_name_map[$dow] . ' ' . $hr_label . ': ' . $val . ' sessions';
                ?>
                <div class="fb-hm-cell" style="background:rgba(96,165,250,<?= $alpha ?>);" data-tip="<?= htmlspecialchars($tip) ?>"></div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endfor; ?>
        <?php if (!empty($fb_timing_top3)): ?>
        <div style="background:rgba(251,191,36,0.06);border-left:3px solid #fbbf24;border-radius:0 8px 8px 0;padding:0.6rem 0.9rem;margin-top:0.85rem;font-size:0.78rem;color:#fbbf24;">
            Best time to run OCPD Facebook Ads:
            <?php $fb_best = []; foreach($fb_timing_top3 as $t):
                $h = (int)$t['hr'];
                $ampm = $h < 12 ? ($h===0?'12am':$h.'am') : ($h===12?'12pm':($h-12).'pm');
                $fb_best[] = $ampm . ' on ' . ($dow_names[$t['dow']] ?? 'Day'.$t['dow']);
            endforeach; ?>
            <strong style="color:#fff;"><?= implode(', ', $fb_best) ?></strong>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sub-panel D: Ad Copy Suggestions -->
    <div>
        <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:#6b7280;margin-bottom:0.75rem;">
            <i class="bi bi-pencil-square" style="color:#34d399;"></i> Ad Copy Suggestions
        </div>
        <div class="ad-copy-grid">
            <!-- Column 1: Most clicked CTAs -->
            <div class="ad-copy-col">
                <div style="font-size:0.72rem;font-weight:700;color:#60a5fa;margin-bottom:0.6rem;">Users click on…</div>
                <?php if (empty($fb_top_clicks)): ?>
                <p style="font-size:0.72rem;color:#4b5563;">No click data yet.</p>
                <?php else: ?>
                <?php foreach ($fb_top_clicks as $c): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:0.3rem 0;border-bottom:1px solid rgba(255,255,255,0.04);font-size:0.74rem;">
                    <span style="color:#d1d5db;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:140px;" title="<?= htmlspecialchars($c['label']) ?>"><?= htmlspecialchars(mb_substr($c['label'],0,35)) ?></span>
                    <span style="color:#60a5fa;font-weight:700;font-size:0.68rem;"><?= number_format((int)$c['clicks']) ?></span>
                </div>
                <?php endforeach; ?>
                <?php if (!empty($fb_top_clicks)): $top_cta = $fb_top_clicks[0]; ?>
                <div class="ad-suggestion">Use "<?= htmlspecialchars(mb_substr($top_cta['label'],0,40)) ?>" as your ad CTA button text</div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
            <!-- Column 2: Content that hooks -->
            <div class="ad-copy-col">
                <div style="font-size:0.72rem;font-weight:700;color:#60a5fa;margin-bottom:0.6rem;">Content that hooks…</div>
                <?php if (empty($fb_top_pages)): ?>
                <p style="font-size:0.72rem;color:#4b5563;">No time-on-page data yet.</p>
                <?php else: ?>
                <?php foreach ($fb_top_pages as $p): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:0.3rem 0;border-bottom:1px solid rgba(255,255,255,0.04);font-size:0.74rem;">
                    <span style="color:#d1d5db;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:130px;" title="<?= htmlspecialchars($p['page_url']) ?>"><?= htmlspecialchars(short_url($p['page_url'])) ?></span>
                    <span style="color:#34d399;font-weight:700;font-size:0.68rem;"><?= number_format((int)$p['avg_sec']) ?>s avg</span>
                </div>
                <?php endforeach; ?>
                <?php if (!empty($fb_top_pages)): $top_page = $fb_top_pages[0];
                    $page_topic = trim(basename($top_page['page_url'], '.php') ?: 'this page');
                ?>
                <div class="ad-suggestion">Feature "<?= htmlspecialchars(mb_substr($page_topic,0,30)) ?>" in your ad creative — visitors spend <?= number_format((int)$top_page['avg_sec']) ?>s on it</div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
            <!-- Column 3: Traffic from -->
            <div class="ad-copy-col">
                <div style="font-size:0.72rem;font-weight:700;color:#60a5fa;margin-bottom:0.6rem;">Traffic from…</div>
                <?php if (empty($fb_top_refs)): ?>
                <p style="font-size:0.72rem;color:#4b5563;">No referrer data yet.</p>
                <?php else: ?>
                <?php foreach ($fb_top_refs as $ref): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:0.3rem 0;border-bottom:1px solid rgba(255,255,255,0.04);font-size:0.74rem;">
                    <span style="color:#d1d5db;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:130px;" title="<?= htmlspecialchars($ref['src']) ?>"><?= htmlspecialchars(mb_substr($ref['src'],0,35)) ?></span>
                    <span style="color:#a78bfa;font-weight:700;font-size:0.68rem;"><?= number_format((int)$ref['sessions']) ?> sess</span>
                </div>
                <?php endforeach; ?>
                <?php if (!empty($fb_top_refs)): $top_ref = $fb_top_refs[0]; ?>
                <div class="ad-suggestion">Run ads on <?= htmlspecialchars(mb_substr($top_ref['src'],0,30)) ?> — it already sends quality traffic</div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    </div><!-- /.palantir-body -->
</div><!-- /.palantir-section -->
</div><!-- /.fb-ads-wrap -->

<!-- ══ Facebook Boosted Post Ads Maker ══ -->
<div style="padding: 0 1.5rem 1rem;" id="fb-ads-maker">
<style>
.ad-maker-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; }
@media (max-width: 900px) { .ad-maker-grid { grid-template-columns: 1fr; } }
.ad-card { background: linear-gradient(145deg, rgba(255,255,255,0.04), rgba(255,255,255,0.02)); border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; padding: 1.15rem; display: flex; flex-direction: column; gap: 0.6rem; }
.ad-card-obj { font-size: 0.6rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; padding: 0.2rem 0.6rem; border-radius: 99px; display: inline-block; width: fit-content; }
.ad-card-headline { font-size: 1rem; font-weight: 900; color: #f9fafb; line-height: 1.25; }
.ad-card-body { font-size: 0.78rem; color: #d1d5db; line-height: 1.55; white-space: pre-line; }
.ad-card-cta { display: inline-block; font-size: 0.75rem; font-weight: 800; padding: 0.35rem 0.9rem; border-radius: 7px; margin-top: 0.15rem; }
.ad-card-targeting { font-size: 0.68rem; color: #9ca3af; background: rgba(255,255,255,0.03); border-radius: 8px; padding: 0.55rem 0.7rem; border: 1px solid rgba(255,255,255,0.06); }
.ad-card-targeting strong { color: #e5e7eb; }
.ad-card-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: auto; padding-top: 0.4rem; border-top: 1px solid rgba(255,255,255,0.06); }
.ad-copy-btn { background: rgba(96,165,250,0.1); border: 1px solid rgba(96,165,250,0.25); color: #60a5fa; border-radius: 7px; padding: 0.28rem 0.7rem; font-size: 0.72rem; font-weight: 700; cursor: pointer; }
.ad-copy-btn:hover { background: rgba(96,165,250,0.18); }
.ad-budget-bar { display: flex; align-items: center; gap: 1rem; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.07); border-radius: 10px; padding: 0.75rem 1rem; margin-bottom: 1rem; flex-wrap: wrap; }
.ab-item { text-align: center; }
.ab-item .ab-val { font-size: 1.1rem; font-weight: 900; }
.ab-item .ab-lbl { font-size: 0.6rem; text-transform: uppercase; letter-spacing: 0.06em; color: #6b7280; margin-top: 1px; }
.ab-divider { width: 1px; height: 36px; background: rgba(255,255,255,0.07); flex-shrink: 0; }
</style>

<div class="palantir-section" id="pal-fb-ads-maker">
    <div class="palantir-header" onclick="palToggle(this)">
        <i class="bi bi-megaphone-fill pal-icon" style="color:#38bdf8;"></i>
        <span>Facebook Boosted Post Ads Maker</span>
        <span class="pal-badge" style="background:rgba(56,189,248,0.15);color:#38bdf8;border-color:rgba(56,189,248,0.25);">OCPD · Data-Driven</span>
        <i class="bi bi-chevron-down pal-toggle"></i>
    </div>
    <div class="palantir-body">

    <!-- Budget & Targeting Recommendation row -->
    <div class="ad-budget-bar">
        <div class="ab-item">
            <div class="ab-val" style="color:#34d399;">₱150–₱300</div>
            <div class="ab-lbl">Recommended Daily Budget</div>
        </div>
        <div class="ab-divider"></div>
        <div class="ab-item">
            <div class="ab-val" style="color:#60a5fa;"><?= htmlspecialchars($fb_ad_top_country !== '—' ? $fb_ad_top_country : 'Philippines') ?></div>
            <div class="ab-lbl">Primary Target Country</div>
        </div>
        <div class="ab-divider"></div>
        <div class="ab-item">
            <div class="ab-val" style="color:#fbbf24;"><?= htmlspecialchars(ucfirst($fb_ad_top_device ?: 'Mobile')) ?></div>
            <div class="ab-lbl">Best Device</div>
        </div>
        <div class="ab-divider"></div>
        <div class="ab-item">
            <div class="ab-val" style="color:#a78bfa;"><?= htmlspecialchars($fb_ad_timing_str ?: 'evenings') ?></div>
            <div class="ab-lbl">Best Time to Run</div>
        </div>
        <div class="ab-divider"></div>
        <div class="ab-item">
            <div class="ab-val" style="color:#f87171;"><?= number_format($fb_ad_hot_count + $fb_ad_warm_count) ?></div>
            <div class="ab-lbl">Retargetable Users</div>
        </div>
    </div>

    <p style="font-size:0.76rem;color:#6b7280;margin-bottom:1rem;">
        Three ready-to-post ad variations generated from your OCPD analytics. Copy the text and UTM link into Facebook Ads Manager → Boosted Post or Conversions campaign.
    </p>

    <div class="ad-maker-grid">

        <!-- Ad 1: Awareness (Cold Audience) -->
        <div class="ad-card" style="border-color:rgba(96,165,250,0.2);">
            <span class="ad-card-obj" style="background:rgba(96,165,250,0.15);color:#60a5fa;">🌊 Awareness · Cold Audience</span>
            <div class="ad-card-headline" id="ad1-headline">Experience the Sky Above Cebu</div>
            <div class="ad-card-body" id="ad1-body">Have you ever wanted to fly?

OCPD Paragliding offers tandem flights over the stunning hills of Oslobog, Cebu — no experience needed. Just show up and soar.

✅ Professional pilots
✅ Safety-certified equipment
✅ Breathtaking aerial views
✅ Open year-round

Slots are limited. Book yours today 👇</div>
            <a class="ad-card-cta" style="background:rgba(96,165,250,0.15);color:#60a5fa;text-decoration:none;" href="<?= htmlspecialchars($ocpd_utm_awareness) ?>" target="_blank">
                <i class="bi bi-box-arrow-up-right"></i> Book a Flight
            </a>
            <div class="ad-card-targeting">
                <strong>Targeting:</strong> <?= htmlspecialchars($fb_ad_top_country !== '—' ? $fb_ad_top_country : 'Philippines') ?> · 22–45 · Interests: Adventure Sports, Travel, Outdoor Activities, Extreme Sports<br>
                <strong>Device:</strong> <?= htmlspecialchars(ucfirst($fb_ad_top_device ?: 'Mobile')) ?> · <strong>Objective:</strong> Awareness / Reach<br>
                <strong>Budget:</strong> ₱150/day · <strong>Duration:</strong> 7 days
            </div>
            <div class="ad-card-actions">
                <button class="ad-copy-btn" onclick="adCopy('ad1-headline','ad1-body','<?= htmlspecialchars(addslashes($ocpd_utm_awareness)) ?>',this)">
                    <i class="bi bi-clipboard"></i> Copy Ad + Link
                </button>
                <button class="ad-copy-btn" onclick="adCopyLink('<?= htmlspecialchars(addslashes($ocpd_utm_awareness)) ?>',this)">
                    <i class="bi bi-link"></i> Copy UTM Link
                </button>
            </div>
        </div>

        <!-- Ad 2: Interest / Retarget Warm -->
        <div class="ad-card" style="border-color:rgba(251,191,36,0.2);">
            <span class="ad-card-obj" style="background:rgba(251,191,36,0.12);color:#fbbf24;">🔥 Interest · Retarget Warm Audience</span>
            <div class="ad-card-headline" id="ad2-headline">Still thinking about it? Slots are filling up.</div>
            <div class="ad-card-body" id="ad2-body">You checked out our packages — now it's time to make it happen.

Tandem paragliding in Oslobog, Cebu is an experience you'll talk about for years. Your crew is waiting.

🏔️ 3,000 ft altitude
📸 Free aerial photos included
⏱️ 15–20 min flight time
💳 Easy online booking

Use code FLYCEBU for 10% off your next booking.

Limited spots available this weekend 👇</div>
            <a class="ad-card-cta" style="background:rgba(251,191,36,0.12);color:#fbbf24;text-decoration:none;" href="<?= htmlspecialchars($ocpd_utm_retarget) ?>" target="_blank">
                <i class="bi bi-box-arrow-up-right"></i> Reserve My Slot
            </a>
            <div class="ad-card-targeting">
                <strong>Targeting:</strong> Custom Audience — website visitors last 30d who viewed pricing/packages<br>
                <strong>Audience size:</strong> ~<?= number_format($fb_ad_warm_count) ?> people · <strong>Device:</strong> All<br>
                <strong>Objective:</strong> Traffic / Conversions · <strong>Budget:</strong> ₱200/day · 5 days
            </div>
            <div class="ad-card-actions">
                <button class="ad-copy-btn" onclick="adCopy('ad2-headline','ad2-body','<?= htmlspecialchars(addslashes($ocpd_utm_retarget)) ?>',this)">
                    <i class="bi bi-clipboard"></i> Copy Ad + Link
                </button>
                <button class="ad-copy-btn" onclick="adCopyLink('<?= htmlspecialchars(addslashes($ocpd_utm_retarget)) ?>',this)">
                    <i class="bi bi-link"></i> Copy UTM Link
                </button>
            </div>
        </div>

        <!-- Ad 3: Conversion / Hot Leads -->
        <div class="ad-card" style="border-color:rgba(52,211,153,0.2);">
            <span class="ad-card-obj" style="background:rgba(52,211,153,0.12);color:#34d399;">✅ Conversion · Hot Leads</span>
            <div class="ad-card-headline" id="ad3-headline">Book Today. Fly This Weekend.</div>
            <div class="ad-card-body" id="ad3-body">⚡ Only a few slots left for this weekend's flights.

OCPD Paragliding — Oslobog, Cebu's #1 tandem flight experience. Instant confirmation, online payment accepted.

👉 Takes 2 minutes to book online
💰 Starting at ₱2,500/pax
🛡️ Full insurance included

Don't wait — your spot won't be there tomorrow.</div>
            <a class="ad-card-cta" style="background:rgba(52,211,153,0.12);color:#34d399;text-decoration:none;" href="<?= htmlspecialchars($ocpd_utm_conversion) ?>" target="_blank">
                <i class="bi bi-box-arrow-up-right"></i> Book Now — Limited Slots
            </a>
            <div class="ad-card-targeting">
                <strong>Targeting:</strong> Custom Audience — visited booking page last 14d (did NOT convert)<br>
                <strong>Audience size:</strong> ~<?= number_format($fb_ad_hot_count) ?> people · <strong>Objective:</strong> Conversions<br>
                <strong>Budget:</strong> ₱300/day · 3 days · <strong>Bid strategy:</strong> Lowest cost
            </div>
            <div class="ad-card-actions">
                <button class="ad-copy-btn" onclick="adCopy('ad3-headline','ad3-body','<?= htmlspecialchars(addslashes($ocpd_utm_conversion)) ?>',this)">
                    <i class="bi bi-clipboard"></i> Copy Ad + Link
                </button>
                <button class="ad-copy-btn" onclick="adCopyLink('<?= htmlspecialchars(addslashes($ocpd_utm_conversion)) ?>',this)">
                    <i class="bi bi-link"></i> Copy UTM Link
                </button>
            </div>
        </div>

    </div><!-- /.ad-maker-grid -->

    <!-- Detailed Targeting Reference -->
    <div style="margin-top:1.25rem;padding:0.85rem 1rem;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07);border-radius:10px;">
        <div style="font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:#6b7280;margin-bottom:0.65rem;"><i class="bi bi-crosshair" style="color:#38bdf8;margin-right:4px;"></i>Detailed Targeting Reference — Facebook Ads Manager</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:0.75rem;font-size:0.73rem;">
            <div>
                <div style="color:#60a5fa;font-weight:700;margin-bottom:0.3rem;">Demographics</div>
                <div style="color:#9ca3af;line-height:1.6;">
                    Age: 20–40<br>
                    Location: <?= htmlspecialchars($fb_ad_top_country !== '—' ? $fb_ad_top_country : 'Philippines') ?>, Cebu City +50km radius<br>
                    Language: English, Filipino<br>
                    Device: <?= htmlspecialchars(ucfirst($fb_ad_top_device ?: 'Mobile')) ?>
                </div>
            </div>
            <div>
                <div style="color:#a78bfa;font-weight:700;margin-bottom:0.3rem;">Interests</div>
                <div style="color:#9ca3af;line-height:1.6;">
                    Paragliding · Skydiving<br>
                    Adventure travel · Hiking<br>
                    Extreme sports · Tourism<br>
                    Outdoor recreation
                </div>
            </div>
            <div>
                <div style="color:#fbbf24;font-weight:700;margin-bottom:0.3rem;">Behaviors</div>
                <div style="color:#9ca3af;line-height:1.6;">
                    Frequent international travelers<br>
                    Adventure seekers<br>
                    Engaged shoppers<br>
                    Summer activities
                </div>
            </div>
            <div>
                <div style="color:#34d399;font-weight:700;margin-bottom:0.3rem;">Custom Audiences (upload CSV)</div>
                <div style="color:#9ca3af;line-height:1.6;">
                    Hot: <?= number_format($fb_ad_hot_count) ?> (booking page visitors)<br>
                    Warm: <?= number_format($fb_ad_warm_count) ?> (pricing viewers)<br>
                    Lookalike: use Seed CSV from FB Intelligence above
                </div>
            </div>
            <div>
                <div style="color:#f87171;font-weight:700;margin-bottom:0.3rem;">Best Run Times</div>
                <div style="color:#9ca3af;line-height:1.6;">
                    <?= nl2br(htmlspecialchars(implode("\n", $fb_ad_best_times) ?: 'Thu–Sun evenings')) ?><br>
                    (from your booking intent traffic data)
                </div>
            </div>
        </div>
    </div>

    </div><!-- /.palantir-body -->
</div><!-- /.palantir-section -->
</div><!-- /#fb-ads-maker -->

<!-- ══ Sales & Metrics ══ -->
<div style="padding: 0 1.5rem 1rem;">
<style>
.sm-kpi-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(130px,1fr)); gap:0.75rem; margin-bottom:1.25rem; }
.sm-kpi { background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); border-radius:10px; padding:0.85rem 1rem; }
.sm-kpi-val { font-size:1.4rem; font-weight:900; line-height:1.1; }
.sm-kpi-lbl { font-size:0.62rem; font-weight:700; text-transform:uppercase; letter-spacing:0.07em; color:#6b7280; margin-top:3px; }
.sm-table { width:100%; border-collapse:collapse; font-size:0.76rem; }
.sm-table th { font-size:0.62rem; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:#6b7280; padding:0.3rem 0.6rem; text-align:left; border-bottom:1px solid rgba(255,255,255,0.07); }
.sm-table td { padding:0.42rem 0.6rem; border-bottom:1px solid rgba(255,255,255,0.04); color:#d1d5db; }
.sm-table tr:last-child td { border-bottom:none; }
.sm-bar-wrap { height:6px; background:rgba(255,255,255,0.06); border-radius:99px; overflow:hidden; display:inline-block; width:80px; vertical-align:middle; margin-right:4px; }
.sm-bar-fill { height:100%; border-radius:99px; }
.sm-two-col { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
@media(max-width:700px) { .sm-two-col { grid-template-columns:1fr; } }
.sm-section-lbl { font-size:0.68rem; font-weight:700; text-transform:uppercase; letter-spacing:0.07em; color:#6b7280; margin-bottom:0.6rem; }
</style>
<div class="palantir-section">
    <div class="palantir-header" onclick="palToggle(this)">
        <i class="bi bi-graph-up-arrow pal-icon" style="color:#34d399;"></i>
        <span>Sales &amp; Metrics</span>
        <span class="pal-badge" style="background:rgba(52,211,153,0.15);color:#34d399;border-color:rgba(52,211,153,0.25);">OCPD · Bookings</span>
        <i class="bi bi-chevron-down pal-toggle"></i>
    </div>
    <div class="palantir-body">

    <!-- KPI Row -->
    <div class="sm-kpi-grid">
        <div class="sm-kpi">
            <div class="sm-kpi-val" style="color:#34d399;">₱<?= number_format((float)$sales_kpis['total_revenue']) ?></div>
            <div class="sm-kpi-lbl">Total Revenue (Confirmed)</div>
        </div>
        <div class="sm-kpi">
            <div class="sm-kpi-val" style="color:#60a5fa;">₱<?= number_format((float)$sales_kpis['revenue_this_month']) ?></div>
            <div class="sm-kpi-lbl">Revenue This Month</div>
        </div>
        <div class="sm-kpi">
            <div class="sm-kpi-val" style="color:#e5e7eb;"><?= number_format((int)$sales_kpis['bookings_total']) ?></div>
            <div class="sm-kpi-lbl">Total Bookings</div>
        </div>
        <div class="sm-kpi">
            <div class="sm-kpi-val" style="color:#a78bfa;"><?= number_format((int)$sales_kpis['bookings_this_month']) ?></div>
            <div class="sm-kpi-lbl">Bookings This Month</div>
        </div>
        <div class="sm-kpi">
            <div class="sm-kpi-val" style="color:#fbbf24;">₱<?= number_format((float)$sales_kpis['avg_value']) ?></div>
            <div class="sm-kpi-lbl">Avg Booking Value</div>
        </div>
        <div class="sm-kpi">
            <div class="sm-kpi-val" style="color:#38bdf8;"><?= $sales_kpis['avg_pax'] ?></div>
            <div class="sm-kpi-lbl">Avg Passengers / Booking</div>
        </div>
        <div class="sm-kpi">
            <div class="sm-kpi-val" style="color:<?= (float)$sales_kpis['cancellation_rate'] > 20 ? '#f87171' : '#9ca3af' ?>;"><?= $sales_kpis['cancellation_rate'] ?>%</div>
            <div class="sm-kpi-lbl">Cancellation Rate</div>
        </div>
    </div>

    <div class="sm-two-col">

        <!-- Monthly Revenue -->
        <div>
            <div class="sm-section-lbl"><i class="bi bi-bar-chart-line" style="color:#34d399;"></i> Revenue Last 6 Months</div>
            <?php if (empty($revenue_by_month)): ?>
            <div style="font-size:0.75rem;color:#4b5563;">No monthly data yet.</div>
            <?php else:
                $max_rev = max(1, max(array_column($revenue_by_month, 'revenue')));
            ?>
            <table class="sm-table">
                <thead><tr><th>Month</th><th>Revenue</th><th>Bookings</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($revenue_by_month as $m): $pct = min(100, round($m['revenue'] / $max_rev * 100)); ?>
                <tr>
                    <td><?= htmlspecialchars($m['month_label']) ?></td>
                    <td style="color:#34d399;font-weight:700;">₱<?= number_format((float)$m['revenue']) ?></td>
                    <td style="color:#9ca3af;"><?= (int)$m['confirmed_cnt'] ?></td>
                    <td><div class="sm-bar-wrap"><div class="sm-bar-fill" style="width:<?= $pct ?>%;background:#34d399;"></div></div></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Top Packages + Payment Breakdown -->
        <div>
            <div class="sm-section-lbl"><i class="bi bi-box-seam" style="color:#a78bfa;"></i> Top Packages</div>
            <?php if (empty($top_packages)): ?>
            <div style="font-size:0.75rem;color:#4b5563;margin-bottom:1rem;">No package data yet.</div>
            <?php else:
                $max_pkg = max(1, max(array_column($top_packages, 'bookings')));
            ?>
            <table class="sm-table" style="margin-bottom:1.1rem;">
                <thead><tr><th>Package</th><th>Bookings</th><th>Revenue</th></tr></thead>
                <tbody>
                <?php foreach ($top_packages as $p): $pct = min(100, round($p['bookings'] / $max_pkg * 100)); ?>
                <tr>
                    <td style="max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($p['name']) ?>"><?= htmlspecialchars(mb_substr($p['name'],0,26)) ?></td>
                    <td><div class="sm-bar-wrap"><div class="sm-bar-fill" style="width:<?= $pct ?>%;background:#a78bfa;"></div></div><?= (int)$p['bookings'] ?></td>
                    <td style="color:#34d399;">₱<?= number_format((float)$p['revenue']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <div class="sm-section-lbl"><i class="bi bi-credit-card" style="color:#60a5fa;"></i> Payment Types</div>
            <?php if (empty($payment_breakdown)): ?>
            <div style="font-size:0.75rem;color:#4b5563;">No payment data yet.</div>
            <?php else:
                $max_pay = max(1, max(array_column($payment_breakdown, 'cnt')));
            ?>
            <table class="sm-table">
                <thead><tr><th>Method</th><th>Bookings</th><th>Revenue</th></tr></thead>
                <tbody>
                <?php foreach ($payment_breakdown as $pt): $pct = min(100, round($pt['cnt'] / $max_pay * 100)); ?>
                <tr>
                    <td><?= htmlspecialchars(ucfirst($pt['ptype'])) ?></td>
                    <td><div class="sm-bar-wrap"><div class="sm-bar-fill" style="width:<?= $pct ?>%;background:#60a5fa;"></div></div><?= (int)$pt['cnt'] ?></td>
                    <td style="color:#34d399;">₱<?= number_format((float)$pt['revenue']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

    </div><!-- /.sm-two-col -->
    </div><!-- /.palantir-body -->
</div><!-- /.palantir-section -->
</div>

<!-- ══ Found Us Via ══ -->
<div style="padding: 0 1.5rem 1rem;">
<style>
.fv-bar-row { display:flex; align-items:center; gap:0.65rem; margin-bottom:0.5rem; }
.fv-source { font-size:0.78rem; font-weight:700; color:#e5e7eb; width:120px; flex-shrink:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.fv-bar-outer { flex:1; height:10px; background:rgba(255,255,255,0.05); border-radius:99px; overflow:hidden; }
.fv-bar-inner { height:100%; border-radius:99px; }
.fv-cnt { font-size:0.72rem; font-weight:800; width:36px; text-align:right; flex-shrink:0; }
.fv-pct { font-size:0.67rem; color:#6b7280; width:36px; text-align:right; flex-shrink:0; }
</style>
<div class="palantir-section">
    <div class="palantir-header" onclick="palToggle(this)">
        <i class="bi bi-signpost-split pal-icon" style="color:#f472b6;"></i>
        <span>Found Us Via</span>
        <span class="pal-badge" style="background:rgba(244,114,182,0.15);color:#f472b6;border-color:rgba(244,114,182,0.25);">OCPD · Last 30 Days</span>
        <i class="bi bi-chevron-down pal-toggle"></i>
    </div>
    <div class="palantir-body">
    <p style="font-size:0.76rem;color:#6b7280;margin-bottom:1.1rem;">
        How customers say they found OCPD — collected directly from the booking form. Real answers, not estimated.
    </p>

    <?php if (empty($found_us_via)): ?>
    <div style="font-size:0.78rem;color:#4b5563;">No data yet — the <code>found_us_via</code> field hasn't been filled in on any bookings.</div>
    <?php else: $fv_max = max(1, $found_us_via[0]['bookings']); ?>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem;margin-bottom:1.1rem;">
    <?php foreach ($found_us_via as $fv):
        $color   = '#6b7280';
        foreach ($fv_colors as $key => $c) {
            if (stripos($fv['source'], $key) !== false) { $color = $c; break; }
        }
        $pct     = round($fv['bookings'] / $found_us_via_total * 100, 1);
        $bar_w   = min(100, round($fv['bookings'] / $fv_max * 100));
        $conv_r  = $fv['bookings'] > 0 ? round($fv['confirmed'] / $fv['bookings'] * 100) : 0;
        $fv_icon = match(true) {
            stripos($fv['source'],'facebook')  !== false => 'bi-facebook',
            stripos($fv['source'],'instagram') !== false => 'bi-instagram',
            stripos($fv['source'],'google')    !== false => 'bi-google',
            stripos($fv['source'],'tiktok')    !== false => 'bi-tiktok',
            stripos($fv['source'],'youtube')   !== false => 'bi-youtube',
            stripos($fv['source'],'twitter')   !== false => 'bi-twitter-x',
            stripos($fv['source'],'friend')    !== false,
            stripos($fv['source'],'word')      !== false => 'bi-people-fill',
            stripos($fv['source'],'argonar')   !== false => 'bi-controller',
            stripos($fv['source'],'not spec')  !== false => 'bi-question-circle',
            default => 'bi-globe',
        };
    ?>
    <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07);border-radius:10px;padding:0.85rem 1rem;">
        <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.5rem;">
            <i class="bi <?= $fv_icon ?>" style="color:<?= $color ?>;font-size:1rem;"></i>
            <span style="font-size:0.84rem;font-weight:800;color:#e5e7eb;flex:1;"><?= htmlspecialchars($fv['source']) ?></span>
            <span style="font-size:0.68rem;font-weight:700;color:<?= $color ?>;"><?= $pct ?>%</span>
        </div>
        <div class="fv-bar-outer" style="margin-bottom:0.6rem;">
            <div class="fv-bar-inner" style="width:<?= $bar_w ?>%;background:<?= $color ?>;"></div>
        </div>
        <div style="display:flex;gap:1rem;font-size:0.72rem;">
            <div><span style="color:#6b7280;">Bookings</span> <strong style="color:#e5e7eb;"><?= number_format((int)$fv['bookings']) ?></strong></div>
            <div><span style="color:#6b7280;">Confirmed</span> <strong style="color:#34d399;"><?= number_format((int)$fv['confirmed']) ?></strong></div>
            <div><span style="color:#6b7280;">Revenue</span> <strong style="color:#34d399;">₱<?= number_format((float)$fv['revenue']) ?></strong></div>
            <div><span style="color:#6b7280;">Conv.</span> <strong style="color:#fbbf24;"><?= $conv_r ?>%</strong></div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>

    <?php
    // Find highest-converting source (min 2 bookings, exclude "not specified")
    $best_conv = null;
    foreach ($found_us_via as $fv) {
        if ((int)$fv['bookings'] >= 2 && stripos($fv['source'],'not spec') === false) {
            $cr = $fv['bookings'] > 0 ? round($fv['confirmed'] / $fv['bookings'] * 100) : 0;
            if (!$best_conv || $cr > ($best_conv['cr'] ?? 0)) $best_conv = array_merge($fv, ['cr'=>$cr]);
        }
    }
    $top_src = $found_us_via[0] ?? null;
    $top_pct = $top_src ? round($top_src['bookings'] / $found_us_via_total * 100, 1) : 0;
    ?>
    <div style="background:rgba(244,114,182,0.06);border-left:3px solid #f472b6;border-radius:0 8px 8px 0;padding:0.65rem 0.9rem;font-size:0.78rem;color:#f9a8d4;">
        <?php if ($top_src): ?>
        <strong style="color:#f472b6;"><?= htmlspecialchars($top_src['source']) ?></strong>
        brings in the most bookings at <strong><?= $top_pct ?>%</strong>.
        <?php if ($best_conv && $best_conv['source'] !== $top_src['source']): ?>
        But <strong style="color:#fbbf24;"><?= htmlspecialchars($best_conv['source']) ?></strong>
        has the highest conversion rate at <strong><?= $best_conv['cr'] ?>%</strong> — consider putting more effort there.
        <?php elseif ($best_conv): ?>
        It also has a strong conversion rate of <strong><?= $best_conv['cr'] ?>%</strong>.
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php endif; ?>
    </div><!-- /.palantir-body -->
</div><!-- /.palantir-section -->
</div>

<!-- OCPD Bookings panel injected after topbar/wrap (accessible via JS scroll) -->
<div style="padding: 0 1.5rem 1.5rem;">
<div class="bookings-panel">
    <div class="bookings-panel-head">
        <i class="bi bi-calendar-check" style="color:#38bdf8;font-size:1rem;"></i>
        <h3>Bookings</h3>
        <?php if ($ocpd_booking_counts['pending'] > 0): ?>
        <span style="background:rgba(251,191,36,0.15); color:#fbbf24; border:1px solid rgba(251,191,36,0.3); border-radius:99px; padding:0.1rem 0.55rem; font-size:0.68rem; font-weight:800;"><?= $ocpd_booking_counts['pending'] ?> pending</span>
        <?php endif; ?>
        <span style="margin-left:auto; font-size:0.7rem; color:#6b7280;" id="bkLastUpdated"></span>
    </div>
    <div class="bk-tab-bar">
        <button class="bk-tab active" data-status="pending"   onclick="bkSwitch(this,'pending')">  Pending   <span class="bk-cnt"><?= $ocpd_booking_counts['pending'] ?></span></button>
        <button class="bk-tab"        data-status="confirmed" onclick="bkSwitch(this,'confirmed')">Confirmed <span class="bk-cnt"><?= $ocpd_booking_counts['confirmed'] ?></span></button>
        <button class="bk-tab"        data-status="cancelled" onclick="bkSwitch(this,'cancelled')">Cancelled <span class="bk-cnt"><?= $ocpd_booking_counts['cancelled'] ?></span></button>
        <input type="text" class="bk-search" id="bkSearch" placeholder="Search name / email / ref…" oninput="bkSearchDebounce()">
    </div>
    <div class="bk-list" id="bkList">
        <div class="bk-empty">Loading…</div>
    </div>
</div>
</div>

</div><!-- /.omni-main -->
</div><!-- /.omni-layout -->

<script>
// ── OCPD Bookings JS ──
let _bkStatus = 'pending', _bkSearch = '', _bkSearchTimer = null;

function bkSwitch(el, status) {
    _bkStatus = status;
    document.querySelectorAll('.bk-tab').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    loadBookings();
}
function bkSearchDebounce() {
    clearTimeout(_bkSearchTimer);
    _bkSearchTimer = setTimeout(() => { _bkSearch = document.getElementById('bkSearch').value; loadBookings(); }, 350);
}
function loadBookings() {
    const list = document.getElementById('bkList');
    list.innerHTML = '<div class="bk-empty">Loading…</div>';
    fetch(`activity-ocpd.php?ajax=ocpd_bookings&status=${_bkStatus}&q=${encodeURIComponent(_bkSearch)}`)
        .then(r => r.json())
        .then(bookings => {
            document.getElementById('bkLastUpdated').textContent = 'Updated ' + new Date().toLocaleTimeString();
            if (!Array.isArray(bookings)) {
                list.innerHTML = '<div class="bk-empty" style="color:#f87171;">DB error: ' + (bookings._error || JSON.stringify(bookings)) + '</div>';
                return;
            }
            if (!bookings.length) { list.innerHTML = '<div class="bk-empty">No ' + _bkStatus + ' bookings.</div>'; return; }
            list.innerHTML = bookings.map(b => bkCard(b)).join('');
        })
        .catch(err => { list.innerHTML = '<div class="bk-empty" style="color:#f87171;">Failed to load: ' + err + '</div>'; });
}
function bkCard(b) {
    const name = (b.first_name||'') + ' ' + (b.last_name||'');
    const evDate = b.event_date ? new Date(b.event_date).toLocaleDateString('en-PH', {year:'numeric',month:'short',day:'numeric'}) : '—';
    const evTime = b.event_time ? b.event_time.slice(0,5) : '';
    const created = b.created_at ? b.created_at.slice(0,10) : '';
    const pkgs = b.packages ? `<div class="bk-packages"><i class="bi bi-box"></i> ${b.packages}</div>` : '';
    const actions = b.status === 'pending' ? `
        <button class="bk-btn bk-btn-confirm" onclick="bkAction(${b.id},'confirm',this)"><i class="bi bi-check-lg"></i> Confirm</button>
        <button class="bk-btn bk-btn-cancel"  onclick="bkAction(${b.id},'cancel',this)"><i class="bi bi-x-lg"></i> Cancel</button>` :
      b.status === 'confirmed' ? `<button class="bk-btn bk-btn-cancel" onclick="bkAction(${b.id},'cancel',this)"><i class="bi bi-x-lg"></i> Cancel</button>` : '';
    return `<div class="bk-card ${b.status}" id="bk-${b.id}">
        <div class="bk-card-top">
            <span class="bk-ref">${b.reference_code||'#'+b.id}</span>
            <div>
                <div class="bk-name">${name.trim()||'—'}</div>
                <div class="bk-email">${b.email||'—'} ${b.mobile ? '· '+b.mobile : ''}</div>
            </div>
            <span class="bk-status-badge ${b.status}">${b.status}</span>
        </div>
        <div class="bk-meta">
            <span><strong>Date</strong> ${evDate}${evTime?' '+evTime:''}</span>
            <span><strong>Pax</strong> ${b.total_passengers||1}</span>
            ${b.amount_paid ? `<span><strong>Paid</strong> ₱${parseFloat(b.amount_paid).toLocaleString()}</span>` : ''}
            ${b.weight_kg ? `<span><strong>Weight</strong> ${b.weight_kg}kg</span>` : ''}
            ${b.booking_type ? `<span><strong>Type</strong> ${b.booking_type}</span>` : ''}
            ${b.discovery_source ? `<span style="color:#f472b6;"><strong style="color:#f472b6;">Found via</strong> ${b.discovery_source}</span>` : ''}
            ${b.referred_by ? `<span style="color:#fb923c;"><strong style="color:#fb923c;">Referred by</strong> ${b.referred_by}</span>` : ''}
            <span><strong>Booked</strong> ${created}</span>
        </div>
        ${pkgs}
        ${b.notes ? `<div style="font-size:0.73rem;color:#9ca3af;margin-bottom:0.45rem;"><i class="bi bi-chat-left-text"></i> ${b.notes}</div>` : ''}
        <textarea class="bk-notes-area" id="bk-notes-${b.id}" placeholder="Admin notes…">${b.admin_notes||''}</textarea>
        <div class="bk-actions">
            ${actions}
            <button class="bk-btn bk-btn-save" onclick="bkSaveNotes(${b.id},this)"><i class="bi bi-floppy"></i> Save Notes</button>
            <span class="bk-msg" id="bk-msg-${b.id}"></span>
        </div>
    </div>`;
}
function bkAction(id, action, btn) {
    btn.disabled = true;
    fetch('activity-ocpd.php?ajax=booking_action', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({id, action})
    }).then(r=>r.json()).then(d => {
        if (d.ok) {
            const msg = document.getElementById('bk-msg-' + id);
            if (msg) { msg.textContent = action === 'confirm' ? '✓ Confirmed' : '✓ Cancelled'; msg.className = 'bk-msg ok'; }
            setTimeout(loadBookings, 800);
        } else { btn.disabled = false; alert('Error: ' + (d.error||'unknown')); }
    }).catch(()=>{ btn.disabled = false; });
}
function bkSaveNotes(id, btn) {
    const notes = document.getElementById('bk-notes-' + id)?.value || '';
    btn.disabled = true;
    fetch('activity-ocpd.php?ajax=booking_action', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({id, action:'notes', notes})
    }).then(r=>r.json()).then(d => {
        btn.disabled = false;
        const msg = document.getElementById('bk-msg-' + id);
        if (msg) { msg.textContent = d.ok ? '✓ Saved' : '✗ Error'; msg.className = 'bk-msg ' + (d.ok?'ok':'err'); setTimeout(()=>{ msg.textContent=''; }, 2000); }
    }).catch(()=>{ btn.disabled = false; });
}
document.addEventListener('DOMContentLoaded', loadBookings);

// ── FB Segment CSV Download ──
function fbDownloadSeg(seg) {
    window.location.href = 'activity-ocpd.php?ajax=fb_segment&seg=' + seg;
}

// ── FB Ads Maker copy helpers ──
function adCopy(headlineId, bodyId, utmLink, btn) {
    const headline = document.getElementById(headlineId)?.textContent?.trim() || '';
    const body = document.getElementById(bodyId)?.textContent?.trim() || '';
    const text = headline + '\n\n' + body + '\n\n' + utmLink;
    navigator.clipboard?.writeText(text).catch(() => {
        const t = document.createElement('textarea');
        t.value = text; document.body.appendChild(t); t.select();
        document.execCommand('copy'); document.body.removeChild(t);
    });
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-check-lg"></i> Copied!';
    btn.style.color = '#34d399'; btn.style.borderColor = 'rgba(52,211,153,0.4)';
    setTimeout(() => { btn.innerHTML = orig; btn.style.color = ''; btn.style.borderColor = ''; }, 2000);
}
function adCopyLink(link, btn) {
    navigator.clipboard?.writeText(link).catch(() => {
        const t = document.createElement('textarea');
        t.value = link; document.body.appendChild(t); t.select();
        document.execCommand('copy'); document.body.removeChild(t);
    });
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-check-lg"></i> Link Copied!';
    btn.style.color = '#34d399'; btn.style.borderColor = 'rgba(52,211,153,0.4)';
    setTimeout(() => { btn.innerHTML = orig; btn.style.color = ''; btn.style.borderColor = ''; }, 2000);
}
</script>
</body>
</html>
