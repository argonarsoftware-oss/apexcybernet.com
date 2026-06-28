<?php
$active_site = 'loan';
$page_file   = 'activity-loan.php';
require_once __DIR__ . '/../includes/db.php';
$argonar_pdo = $pdo; // Preserve argonar_construction for Palantir + sidebar

require_once __DIR__ . '/omni/auth.php'; // auth first — _load_env defined here

// ── Connect to Loan DB ──
$env = _load_env(dirname(__DIR__, 2) . '/loan-management/.env');
try {
    $loan_pdo = new PDO(
        "mysql:host=" . ($env['DB_HOST']??'localhost') . ";dbname=" . ($env['DB_NAME']??'loan_management_ph') . ";charset=utf8mb4",
        $env['DB_USER'] ?? 'root', $env['DB_PASS'] ?? '',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]
    );
} catch (PDOException $e) {
    if (isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['ok'=>false,'error'=>'Loan DB: ' . $e->getMessage()]); exit;
    }
    // Non-fatal for analytics — we only use argonar_pdo for activity_logs
    $loan_pdo = null;
}

// ── AJAX: session timeline ──
if (isset($_GET['ajax']) && $_GET['ajax'] === 'session') {
    header('Content-Type: application/json');
    $sid = substr(preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['sid'] ?? ''), 0, 64);
    if (!$sid) { echo json_encode([]); exit; }
    try {
        $st = $argonar_pdo->prepare("SELECT id, event_type, page_url, page_title, element_tag, element_text,
            element_href, element_id, referrer, ip, screen_w, created_at
            FROM activity_logs WHERE session_id = ? AND site='loan' ORDER BY id ASC LIMIT 200");
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
            FROM activity_logs WHERE id > ? AND site='loan' ORDER BY id DESC LIMIT 30");
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
            AND l.site='loan'
            $date_c
            GROUP BY l.account_id, l.display_name, a.email
            ORDER BY visits DESC LIMIT 200");
        $st->execute(['%' . $url . '%']);
        $users = $st->fetchAll(PDO::FETCH_ASSOC);

        $gs = $argonar_pdo->prepare("SELECT COUNT(DISTINCT session_id) FROM activity_logs
            WHERE account_id IS NULL AND page_url LIKE ? AND site='loan' $date_c");
        $gs->execute(['%' . $url . '%']);
        $guest_sessions = (int)$gs->fetchColumn();

        echo json_encode(['users' => $users, 'guest_sessions' => $guest_sessions]);
    } catch (Exception $e) { echo json_encode(['users'=>[],'guest_sessions'=>0,'error'=>$e->getMessage()]); }
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
    $site_cond = "AND site='loan'";
    $search_cond = $search !== '' ? "AND (page_url LIKE ? OR display_name LIKE ? OR ip LIKE ?)" : '';
    $like = '%' . $search . '%';
    $params = $search !== '' ? [$like, $like, $like] : [];
    $where = "WHERE 1=1 $date_cond $ev_cond $uf_cond $site_cond $search_cond";

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="activity_loan_' . date('Ymd_His') . '.csv"');
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Loan Analytics — Omniscient</title>
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

</div><!-- /.omni-main -->
</div><!-- /.omni-layout -->
</body>
</html>
