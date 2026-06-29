<?php
$active_site = 'bizops';
$page_file   = 'activity-bizops.php';
require_once __DIR__ . '/../includes/db.php';
$apexcybernet_pdo = $pdo;
require_once __DIR__ . '/omni/auth.php';

// ── Auto-create decision_log table ──
try {
    $apexcybernet_pdo->exec("CREATE TABLE IF NOT EXISTS decision_log (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        decided_at   DATE NOT NULL,
        title        VARCHAR(220) NOT NULL,
        context_text MEDIUMTEXT,
        action_taken MEDIUMTEXT,
        result_text  MEDIUMTEXT,
        impact_text  MEDIUMTEXT,
        outcome      ENUM('pending','positive','negative','neutral','mixed') DEFAULT 'pending',
        tags         VARCHAR(300),
        business     VARCHAR(60) DEFAULT 'general',
        created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

// ── Decision Log AJAX ──
if (isset($_GET['ajax']) && $_GET['ajax'] === 'dl_list') {
    header('Content-Type: application/json');
    $outcome  = $_GET['outcome'] ?? 'all';
    $biz      = $_GET['biz'] ?? 'all';
    $q        = trim($_GET['q'] ?? '');
    $where    = 'WHERE 1=1';
    $params   = [];
    if ($outcome !== 'all') { $where .= ' AND outcome=?'; $params[] = $outcome; }
    if ($biz !== 'all')     { $where .= ' AND business=?'; $params[] = $biz; }
    if ($q !== '')          { $where .= ' AND (title LIKE ? OR action_taken LIKE ? OR result_text LIKE ? OR tags LIKE ?)'; $like = "%$q%"; $params = array_merge($params, [$like,$like,$like,$like]); }
    try {
        $st = $apexcybernet_pdo->prepare("SELECT * FROM decision_log $where ORDER BY decided_at DESC, id DESC LIMIT 100");
        $st->execute($params);
        echo json_encode($st->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) { echo json_encode([]); }
    exit;
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'dl_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $b = json_decode(file_get_contents('php://input'), true) ?: [];
    $id           = (int)($b['id'] ?? 0);
    $decided_at   = $b['decided_at'] ?? date('Y-m-d');
    $title        = substr(trim($b['title'] ?? ''), 0, 220);
    $context_text = substr(trim($b['context_text'] ?? ''), 0, 50000);
    $action_taken = substr(trim($b['action_taken'] ?? ''), 0, 50000);
    $result_text  = substr(trim($b['result_text'] ?? ''), 0, 50000);
    $impact_text  = substr(trim($b['impact_text'] ?? ''), 0, 100000);
    $outcome      = in_array($b['outcome'] ?? '', ['pending','positive','negative','neutral','mixed']) ? $b['outcome'] : 'pending';
    $tags         = substr(trim($b['tags'] ?? ''), 0, 300);
    $business     = substr(trim($b['business'] ?? 'general'), 0, 60);
    if (!$title) { echo json_encode(['ok'=>false,'error'=>'Title required']); exit; }
    try {
        if ($id > 0) {
            $apexcybernet_pdo->prepare("UPDATE decision_log SET decided_at=?,title=?,context_text=?,action_taken=?,result_text=?,impact_text=?,outcome=?,tags=?,business=?,updated_at=NOW() WHERE id=?")
                ->execute([$decided_at,$title,$context_text,$action_taken,$result_text,$impact_text,$outcome,$tags,$business,$id]);
        } else {
            $apexcybernet_pdo->prepare("INSERT INTO decision_log (decided_at,title,context_text,action_taken,result_text,impact_text,outcome,tags,business) VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([$decided_at,$title,$context_text,$action_taken,$result_text,$impact_text,$outcome,$tags,$business]);
            $id = (int)$apexcybernet_pdo->lastInsertId();
        }
        echo json_encode(['ok'=>true,'id'=>$id]);
    } catch (Exception $e) { echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); }
    exit;
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'dl_delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $b = json_decode(file_get_contents('php://input'), true) ?: [];
    $id = (int)($b['id'] ?? 0);
    try { $apexcybernet_pdo->prepare("DELETE FROM decision_log WHERE id=?")->execute([$id]); echo json_encode(['ok'=>true]); }
    catch (Exception $e) { echo json_encode(['ok'=>false]); }
    exit;
}

// ── Decision Log stats for badge ──
$dl_total = 0;
try { $dl_total = (int)$apexcybernet_pdo->query("SELECT COUNT(*) FROM decision_log")->fetchColumn(); } catch (Exception $e) {}

// ── Auto-create utm_links table ──
try {
    $apexcybernet_pdo->exec("CREATE TABLE IF NOT EXISTS utm_links (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        label       VARCHAR(120) NOT NULL,
        business    VARCHAR(32)  NOT NULL,
        platform    VARCHAR(32)  NOT NULL,
        url         TEXT         NOT NULL,
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Seed default links if table is empty
    $count = (int)$apexcybernet_pdo->query("SELECT COUNT(*) FROM utm_links")->fetchColumn();
    if ($count === 0) {
        $seeds = [
            ['OCPD — Facebook Feed Ad',        'ocpd',    'Facebook',  'https://oslobcebuparagliding.com/?utm_source=fb&utm_medium=paid&utm_campaign=ocpd'],
            ['OCPD — Facebook Story Ad',        'ocpd',    'Facebook',  'https://oslobcebuparagliding.com/?utm_source=fb&utm_medium=paid&utm_campaign=ocpd-story'],
            ['OCPD — Instagram Feed Ad',        'ocpd',    'Instagram', 'https://oslobcebuparagliding.com/?utm_source=ig&utm_medium=paid&utm_campaign=ocpd'],
            ['OCPD — Instagram Reel Ad',        'ocpd',    'Instagram', 'https://oslobcebuparagliding.com/?utm_source=ig&utm_medium=paid&utm_campaign=ocpd-reel'],
            ['OCPD — Facebook Organic Post',   'ocpd',    'Facebook',  'https://oslobcebuparagliding.com/?utm_source=fb&utm_medium=social&utm_campaign=ocpd-org'],
            ['OCPD — Booking Page Direct Ad',  'ocpd',    'Facebook',  'https://oslobcebuparagliding.com/booking?utm_source=fb&utm_medium=paid&utm_campaign=ocpd-bk'],
            ['Apex Cybernet — Facebook Feed Ad',     'apexcybernet', 'Facebook',  'https://apexcybernet.com/?utm_source=fb&utm_medium=paid&utm_campaign=arg-t'],
            ['Apex Cybernet — Facebook Organic Post','apexcybernet', 'Facebook',  'https://apexcybernet.com/?utm_source=fb&utm_medium=social&utm_campaign=arg-org'],
            ['Apex Cybernet — Instagram Ad',         'apexcybernet', 'Instagram', 'https://apexcybernet.com/?utm_source=ig&utm_medium=paid&utm_campaign=arg-t'],
            ['Apex Cybernet — Register CTA Ad',      'apexcybernet', 'Facebook',  'https://apexcybernet.com/login.php?tab=register&utm_source=fb&utm_medium=paid&utm_campaign=arg-reg'],
            ['Apex Cybernet — Market Page Ad',       'apexcybernet', 'Facebook',  'https://apexcybernet.com/marketplace.php?utm_source=fb&utm_medium=paid&utm_campaign=arg-mkt'],
            ['Apex Cybernet — Coins Page Ad',        'apexcybernet', 'Facebook',  'https://apexcybernet.com/coins.php?utm_source=fb&utm_medium=paid&utm_campaign=arg-hc'],
        ];
        $ins = $apexcybernet_pdo->prepare("INSERT INTO utm_links (label, business, platform, url) VALUES (?,?,?,?)");
        foreach ($seeds as $s) $ins->execute($s);
    }

    // Migrate existing rows — shorten long UTM params in-place (runs once)
    if ($count > 0) {
        $has_long = (int)$apexcybernet_pdo->query("SELECT COUNT(*) FROM utm_links WHERE url LIKE '%utm_medium=paid_social%'")->fetchColumn();
        if ($has_long > 0) {
            $fixes = [
                ['utm_source=facebook',              'utm_source=fb'],
                ['utm_source=instagram',             'utm_source=ig'],
                ['utm_medium=paid_social',           'utm_medium=paid'],
                ['utm_campaign=ocpd-paragliding',    'utm_campaign=ocpd'],
                ['utm_campaign=ocpd-booking',        'utm_campaign=ocpd-bk'],
                ['utm_campaign=apexcybernet-tournament',  'utm_campaign=arg-t'],
                ['utm_campaign=apexcybernet-organic',     'utm_campaign=arg-org'],
                ['utm_campaign=apexcybernet-register',    'utm_campaign=arg-reg'],
                ['utm_campaign=apexcybernet-market',      'utm_campaign=arg-mkt'],
                ['utm_campaign=apexcybernet-coins',       'utm_campaign=arg-hc'],
                ['&utm_content=feed-ad',             ''],
                ['&utm_content=story-ad',            ''],
                ['&utm_content=reel-ad',             ''],
                ['&utm_content=cta-book-now',        ''],
                ['&utm_content=cta-register',        ''],
                // fix register.php → login.php?tab=register
                ['apexcybernet.com/register.php?',         'apexcybernet.com/login.php?tab=register&'],
            ];
            foreach ($fixes as [$from, $to]) {
                $apexcybernet_pdo->prepare("UPDATE utm_links SET url = REPLACE(url, ?, ?) WHERE url LIKE ?")->execute([$from, $to, '%'.$from.'%']);
            }
        }
    }
} catch (Exception $e) {}

// ── Handle add new UTM link ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_utm') {
    $label    = trim($_POST['label'] ?? '');
    $business = trim($_POST['business'] ?? '');
    $platform = trim($_POST['platform'] ?? '');
    $base_url = trim($_POST['base_url'] ?? '');
    $source   = trim($_POST['utm_source'] ?? '');
    $medium   = trim($_POST['utm_medium'] ?? '');
    $campaign = trim($_POST['utm_campaign'] ?? '');
    $content  = trim($_POST['utm_content'] ?? '');
    if ($label && $base_url && $source && $medium && $campaign) {
        $full = $base_url . (str_contains($base_url,'?') ? '&' : '?')
              . 'utm_source='   . urlencode($source)
              . '&utm_medium='  . urlencode($medium)
              . '&utm_campaign='. urlencode($campaign)
              . ($content ? '&utm_content='.urlencode($content) : '');
        try {
            $apexcybernet_pdo->prepare("INSERT INTO utm_links (label, business, platform, url) VALUES (?,?,?,?)")
                ->execute([$label, $business ?: 'other', $platform ?: 'Facebook', $full]);
        } catch (Exception $e) {}
    }
    header('Location: activity-bizops.php#pal-utm'); exit;
}

// ── Handle delete UTM link ──
if (isset($_GET['del_utm'])) {
    try { $apexcybernet_pdo->prepare("DELETE FROM utm_links WHERE id=?")->execute([(int)$_GET['del_utm']]); } catch (Exception $e) {}
    header('Location: activity-bizops.php#pal-utm'); exit;
}

// ── Fetch all UTM links ──
$utm_links = [];
try {
    $utm_links = $apexcybernet_pdo->query("SELECT * FROM utm_links ORDER BY business, platform, id")->fetchAll();
} catch (Exception $e) {}

// ══════════════════════════════════════════════════════════════════════════
// PSYCHOLOGICAL LEVERAGE LEDGER
// "Systems don't move money. People do. And people move by the invisible debts
// written in their own minds." — rebuilt around Cialdini's six principles of
// influence: reciprocity, commitment/consistency, social proof, authority,
// liking, scarcity. Every customer is a relationship account, not a row in a P&L.
// ══════════════════════════════════════════════════════════════════════════

// Connect to OCPD (oslobparagliding) DB
$ocpd_pdo_e = null;
try {
    $env = [];
    foreach ([dirname(__DIR__,2).'/oslobparagliding/.env', '/var/www/oslobparagliding/.env'] as $p) {
        if (file_exists($p)) { $env = _load_env($p); break; }
    }
    $ocpd_pdo_e = new PDO(
        "mysql:host=" . ($env['DB_HOST']??'localhost') . ";dbname=" . ($env['DB_NAME']??'oslobparagliding_db') . ";charset=utf8mb4",
        $env['DB_USER'] ?? 'root', $env['DB_PASS'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT]
    );
} catch (Exception $e) {}

// Connect to Loan DB
$loan_pdo_e = null;
try {
    $env = [];
    foreach ([dirname(__DIR__,2).'/loan-management/.env', '/var/www/loan-management/.env'] as $p) {
        if (file_exists($p)) { $env = _load_env($p); break; }
    }
    $loan_pdo_e = new PDO(
        "mysql:host=" . ($env['DB_HOST']??'localhost') . ";dbname=" . ($env['DB_NAME']??'loan_management_ph') . ";charset=utf8mb4",
        $env['DB_USER'] ?? 'root', $env['DB_PASS'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT]
    );
} catch (Exception $e) {}

// ── Per-business revenue this month + last month + customer counts ──
$emp = [
    'ocpd'    => ['name'=>'OCPD',    'icon'=>'bi-airplane-fill', 'color'=>'#34d399', 'rev_now'=>0, 'rev_prev'=>0, 'cust_now'=>0, 'cust_total'=>0, 'avg'=>0, 'last30_growth'=>null, 'unit'=>'bookings'],
    'loan'    => ['name'=>'Loan PH', 'icon'=>'bi-cash-stack',     'color'=>'#fbbf24', 'rev_now'=>0, 'rev_prev'=>0, 'cust_now'=>0, 'cust_total'=>0, 'avg'=>0, 'last30_growth'=>null, 'unit'=>'loans'],
    'apexcybernet' => ['name'=>'Apex Cybernet', 'icon'=>'bi-trophy-fill',    'color'=>'#a78bfa', 'rev_now'=>0, 'rev_prev'=>0, 'cust_now'=>0, 'cust_total'=>0, 'avg'=>0, 'last30_growth'=>null, 'unit'=>'players'],
];

// OCPD — full ticket price (packages × pax − voucher)
if ($ocpd_pdo_e) {
    try {
        $hasV = false;
        try { $cols = $ocpd_pdo_e->query("SHOW COLUMNS FROM bookings")->fetchAll(PDO::FETCH_COLUMN); $hasV = in_array('voucher_discount', $cols); } catch (Exception $e) {}
        $disc = $hasV ? 'COALESCE(b.voucher_discount,0)' : '0';
        $sx = "GREATEST(COALESCE(pkg.total_price,0)*GREATEST(COALESCE(b.total_passengers,1),1)-$disc,0)";
        $jn = "LEFT JOIN (SELECT bp.booking_id, SUM(p.price) AS total_price FROM booking_packages bp JOIN packages p ON p.id=bp.package_id GROUP BY bp.booking_id) pkg ON pkg.booking_id=b.id";
        $r = $ocpd_pdo_e->query("SELECT
            COALESCE(SUM(CASE WHEN b.status='confirmed' AND b.created_at >= DATE_FORMAT(NOW(),'%Y-%m-01') THEN $sx ELSE 0 END),0) AS rev_now,
            COALESCE(SUM(CASE WHEN b.status='confirmed' AND b.created_at >= DATE_FORMAT(DATE_SUB(NOW(),INTERVAL 1 MONTH),'%Y-%m-01') AND b.created_at < DATE_FORMAT(NOW(),'%Y-%m-01') THEN $sx ELSE 0 END),0) AS rev_prev,
            COUNT(DISTINCT CASE WHEN b.status='confirmed' AND b.created_at >= DATE_FORMAT(NOW(),'%Y-%m-01') THEN b.email END) AS cust_now,
            COUNT(DISTINCT b.email) AS cust_total
            FROM bookings b $jn")->fetch(PDO::FETCH_ASSOC);
        if ($r) {
            $emp['ocpd']['rev_now']    = (float)$r['rev_now'];
            $emp['ocpd']['rev_prev']   = (float)$r['rev_prev'];
            $emp['ocpd']['cust_now']   = (int)$r['cust_now'];
            $emp['ocpd']['cust_total'] = (int)$r['cust_total'];
            $emp['ocpd']['avg']        = $emp['ocpd']['cust_now'] > 0 ? round($emp['ocpd']['rev_now'] / $emp['ocpd']['cust_now']) : 0;
        }
    } catch (Exception $e) {}
}

// Loan — principal disbursed (revenue is total loan capital deployed; interest is the actual revenue but principal shows scale)
if ($loan_pdo_e) {
    try {
        $r = $loan_pdo_e->query("SELECT
            COALESCE(SUM(CASE WHEN created_at >= DATE_FORMAT(NOW(),'%Y-%m-01') THEN principal_amount ELSE 0 END),0) AS rev_now,
            COALESCE(SUM(CASE WHEN created_at >= DATE_FORMAT(DATE_SUB(NOW(),INTERVAL 1 MONTH),'%Y-%m-01') AND created_at < DATE_FORMAT(NOW(),'%Y-%m-01') THEN principal_amount ELSE 0 END),0) AS rev_prev,
            COUNT(DISTINCT CASE WHEN created_at >= DATE_FORMAT(NOW(),'%Y-%m-01') THEN borrower_id END) AS cust_now,
            COUNT(DISTINCT borrower_id) AS cust_total
            FROM loans")->fetch(PDO::FETCH_ASSOC);
        if ($r) {
            $emp['loan']['rev_now']    = (float)$r['rev_now'];
            $emp['loan']['rev_prev']   = (float)$r['rev_prev'];
            $emp['loan']['cust_now']   = (int)$r['cust_now'];
            $emp['loan']['cust_total'] = (int)$r['cust_total'];
            $emp['loan']['avg']        = $emp['loan']['cust_now'] > 0 ? round($emp['loan']['rev_now'] / $emp['loan']['cust_now']) : 0;
        }
    } catch (Exception $e) {}
}

// Apex Cybernet — tournament participation (player counts; HCoin revenue removed)
try {
    $emp['apexcybernet']['cust_total'] = (int)$apexcybernet_pdo->query("SELECT COUNT(*) FROM accounts")->fetchColumn();
    $emp['apexcybernet']['cust_now']   = (int)$apexcybernet_pdo->query("SELECT COUNT(*) FROM accounts WHERE created_at >= DATE_FORMAT(NOW(),'%Y-%m-01')")->fetchColumn();
} catch (Exception $e) {}

// Compute growth %
foreach ($emp as $k => &$b) {
    $b['growth'] = $b['rev_prev'] > 0 ? round(100 * ($b['rev_now'] - $b['rev_prev']) / $b['rev_prev'], 1) : null;
}
unset($b);

$emp_total_now  = array_sum(array_column($emp, 'rev_now'));
$emp_total_prev = array_sum(array_column($emp, 'rev_prev'));
$emp_growth     = $emp_total_prev > 0 ? round(100 * ($emp_total_now - $emp_total_prev) / $emp_total_prev, 1) : null;

// ── Cross-pollination: customers active in 2+ businesses (the real moat) ──
$cross_overlap = ['ocpd_apexcybernet' => 0, 'ocpd_loan' => 0, 'loan_apexcybernet' => 0, 'all_three' => 0];
$cross_emails  = [];
try {
    $argo_emails = array_map('strtolower', array_filter($apexcybernet_pdo->query("SELECT DISTINCT email FROM accounts WHERE email IS NOT NULL AND email != ''")->fetchAll(PDO::FETCH_COLUMN)));
    $ocpd_emails = $ocpd_pdo_e ? array_map('strtolower', array_filter($ocpd_pdo_e->query("SELECT DISTINCT email FROM bookings WHERE email IS NOT NULL AND email != ''")->fetchAll(PDO::FETCH_COLUMN))) : [];
    // Loan uses borrower table; emails likely in borrowers
    $loan_emails = [];
    if ($loan_pdo_e) {
        try { $loan_emails = array_map('strtolower', array_filter($loan_pdo_e->query("SELECT DISTINCT email FROM borrowers WHERE email IS NOT NULL AND email != ''")->fetchAll(PDO::FETCH_COLUMN))); } catch (Exception $e) {}
    }
    $set_a = array_flip($argo_emails);
    $set_o = array_flip($ocpd_emails);
    $set_l = array_flip($loan_emails);
    foreach ($ocpd_emails as $em) { if (isset($set_a[$em])) $cross_overlap['ocpd_apexcybernet']++; if (isset($set_l[$em])) $cross_overlap['ocpd_loan']++; }
    foreach ($loan_emails as $em) { if (isset($set_a[$em])) $cross_overlap['loan_apexcybernet']++; }
    foreach ($ocpd_emails as $em) { if (isset($set_a[$em]) && isset($set_l[$em])) $cross_overlap['all_three']++; }
} catch (Exception $e) {}

// ══ Psychological Leverage Accounts ══
// Each principle below lists concrete people + the Cialdini hook they're vulnerable to.
// The goal isn't to manipulate — it's to surface asks you've already earned the right to make.

$psy = [
    'reciprocity' => [
        'title' => 'Reciprocity Debt',
        'hook'  => 'People you gave something to first. They feel obligated. An ask now has the lowest friction.',
        'count' => 0, 'value' => 0, 'samples' => [],
        'icon'  => 'bi-gift-fill', 'color' => '#ec4899',
        'play'  => 'Reach out, thank them for choosing you, ask for the small favor (review, referral, upsell).'
    ],
    'commitment' => [
        'title' => 'Commitment Zone',
        'hook'  => 'People who publicly said yes once. Saying no to a related ask creates cognitive dissonance.',
        'count' => 0, 'value' => 0, 'samples' => [],
        'icon'  => 'bi-person-check-fill', 'color' => '#34d399',
        'play'  => 'Anchor to their prior commitment ("Since you\'re already a Season 1 team…") and escalate the ask.'
    ],
    'sunk_cost' => [
        'title' => 'Sunk-Cost Contacts',
        'hook'  => 'Paid partial, waiting, or already time-invested. Closing feels like finishing, not buying.',
        'count' => 0, 'value' => 0, 'samples' => [],
        'icon'  => 'bi-hourglass-split', 'color' => '#fbbf24',
        'play'  => 'Lead with "almost there" language; surface the progress bar; close the loop.'
    ],
    'authority' => [
        'title' => 'Authority Capital',
        'hook'  => 'Your favor inventory. Each callable option = one "yes" in the bank.',
        'count' => 0, 'value' => 0, 'samples' => [],
        'icon'  => 'bi-shield-fill-check', 'color' => '#a78bfa',
        'play'  => 'Make a specific ask framed as "I need your help with…" — Cialdini\'s favor-return rate > 80%.'
    ],
    'liking' => [
        'title' => 'Liking / Rapport',
        'hook'  => 'Long-relationship customers. Emotional investment makes "no" feel like a friendship breach.',
        'count' => 0, 'value' => 0, 'samples' => [],
        'icon'  => 'bi-heart-fill', 'color' => '#f87171',
        'play'  => 'Personal voice, not corporate. Ask as a friend asking a friend.'
    ],
    'scarcity' => [
        'title' => 'Scarcity / Loss-Aversion',
        'hook'  => 'People with something active to lose. Fear of losing > desire to gain.',
        'count' => 0, 'value' => 0, 'samples' => [],
        'icon'  => 'bi-alarm-fill', 'color' => '#ef4444',
        'play'  => 'Frame the ask as preventing a loss they already own, not giving them a new gain.'
    ],
];

// ── RECIPROCITY: discounts given, admin HC credits, extended loans, vouchers ──
// OCPD: bookings with voucher_discount > 0
if ($ocpd_pdo_e) {
    try {
        $cols = $ocpd_pdo_e->query("SHOW COLUMNS FROM bookings")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('voucher_discount', $cols)) {
            $r = $ocpd_pdo_e->query("SELECT COUNT(*) c, COALESCE(SUM(voucher_discount),0) v FROM bookings WHERE COALESCE(voucher_discount,0) > 0")->fetch();
            $psy['reciprocity']['count'] += (int)$r['c'];
            $psy['reciprocity']['value'] += (float)$r['v'];
            $rs = $ocpd_pdo_e->query("SELECT first_name, last_name, email, voucher_discount AS v, created_at FROM bookings WHERE COALESCE(voucher_discount,0) > 0 ORDER BY voucher_discount DESC LIMIT 3")->fetchAll();
            foreach ($rs as $row) $psy['reciprocity']['samples'][] = ['name'=>trim($row['first_name'].' '.$row['last_name']), 'email'=>$row['email'], 'amount'=>'₱'.number_format((float)$row['v']).' voucher', 'source'=>'OCPD'];
        }
    } catch (Exception $e) {}
}
// ── COMMITMENT: registered tournament teams/solos, repeat OCPD bookers ──
// Apex Cybernet: active Season 1 teams
try {
    $r = $apexcybernet_pdo->query("SELECT COUNT(*) c FROM teams WHERE status IN ('approved','confirmed','pending')")->fetch();
    $psy['commitment']['count'] += (int)$r['c'];
    $rs = $apexcybernet_pdo->query("SELECT team_name AS name, game, status, created_at FROM teams WHERE status IN ('approved','confirmed') ORDER BY created_at DESC LIMIT 3")->fetchAll();
    foreach ($rs as $row) $psy['commitment']['samples'][] = ['name'=>$row['name'], 'email'=>$row['game'].' · '.$row['status'], 'amount'=>'Season 1 team', 'source'=>'Apex Cybernet'];
} catch (Exception $e) {}
// OCPD: repeat customers (>1 booking)
if ($ocpd_pdo_e) {
    try {
        $r = $ocpd_pdo_e->query("SELECT COUNT(*) c FROM (SELECT email FROM bookings WHERE email IS NOT NULL AND email != '' GROUP BY email HAVING COUNT(*) >= 2) t")->fetch();
        $psy['commitment']['count'] += (int)$r['c'];
        $rs = $ocpd_pdo_e->query("SELECT email, COUNT(*) c, MAX(CONCAT(first_name,' ',last_name)) name FROM bookings WHERE email IS NOT NULL GROUP BY email HAVING COUNT(*) >= 2 ORDER BY c DESC LIMIT 3")->fetchAll();
        foreach ($rs as $row) $psy['commitment']['samples'][] = ['name'=>trim($row['name']), 'email'=>$row['email'], 'amount'=>$row['c'].' bookings', 'source'=>'OCPD'];
    } catch (Exception $e) {}
}

// ── SUNK COST: partial-paid OCPD, loan balances, pending registrations ──
if ($ocpd_pdo_e) {
    try {
        $r = $ocpd_pdo_e->query("SELECT COUNT(*) c, COALESCE(SUM(amount_paid),0) v FROM bookings WHERE status='pending' AND amount_paid > 0")->fetch();
        $psy['sunk_cost']['count'] += (int)$r['c'];
        $psy['sunk_cost']['value'] += (float)$r['v'];
        $rs = $ocpd_pdo_e->query("SELECT first_name, last_name, email, amount_paid, event_date FROM bookings WHERE status='pending' AND amount_paid > 0 ORDER BY amount_paid DESC LIMIT 3")->fetchAll();
        foreach ($rs as $row) $psy['sunk_cost']['samples'][] = ['name'=>trim($row['first_name'].' '.$row['last_name']), 'email'=>$row['email'], 'amount'=>'₱'.number_format((float)$row['amount_paid']).' paid · '.$row['event_date'], 'source'=>'OCPD'];
    } catch (Exception $e) {}
}
// Loan: active loans with remaining balance
if ($loan_pdo_e) {
    try {
        $r = $loan_pdo_e->query("SELECT COUNT(*) c, COALESCE(SUM(principal_amount),0) v FROM loans WHERE status IN ('active','approved','disbursed')")->fetch();
        $psy['sunk_cost']['count'] += (int)$r['c'];
        $psy['sunk_cost']['value'] += (float)$r['v'];
    } catch (Exception $e) {}
}
// Apex Cybernet: pending tournament teams
try {
    $r = $apexcybernet_pdo->query("SELECT COUNT(*) c FROM teams WHERE status='pending'")->fetch();
    $psy['sunk_cost']['count'] += (int)$r['c'];
} catch (Exception $e) {}

// ── AUTHORITY CAPITAL: total principals disbursed + total vouchers granted ──
// Every one is a "yes" you've already put in motion — callable social capital.
if ($loan_pdo_e) {
    try {
        $r = $loan_pdo_e->query("SELECT COUNT(*) c, COALESCE(SUM(principal_amount),0) v FROM loans")->fetch();
        $psy['authority']['count'] += (int)$r['c'];
        $psy['authority']['value'] += (float)$r['v'];
        $rs = $loan_pdo_e->query("SELECT l.principal_amount, b.first_name, b.last_name, b.email FROM loans l LEFT JOIN borrowers b ON b.borrower_id=l.borrower_id ORDER BY l.principal_amount DESC LIMIT 3")->fetchAll();
        foreach ($rs as $row) $psy['authority']['samples'][] = ['name'=>trim(($row['first_name']??'').' '.($row['last_name']??'')) ?: 'Borrower', 'email'=>$row['email']??'', 'amount'=>'₱'.number_format((float)$row['principal_amount']).' lent', 'source'=>'Loan PH'];
    } catch (Exception $e) {}
}

// ── LIKING: users with deep engagement — many sessions or long relationship ──
try {
    $r = $apexcybernet_pdo->query("SELECT COUNT(*) c FROM (
        SELECT account_id FROM activity_logs WHERE account_id IS NOT NULL
        GROUP BY account_id HAVING COUNT(DISTINCT session_id) >= 5
    ) t")->fetch();
    $psy['liking']['count'] += (int)$r['c'];
    $rs = $apexcybernet_pdo->query("SELECT a.display_name, a.email, COUNT(DISTINCT l.session_id) AS sess
        FROM activity_logs l JOIN accounts a ON a.id=l.account_id
        WHERE l.account_id IS NOT NULL
        GROUP BY a.id, a.display_name, a.email HAVING sess >= 5
        ORDER BY sess DESC LIMIT 3")->fetchAll();
    foreach ($rs as $row) $psy['liking']['samples'][] = ['name'=>$row['display_name'], 'email'=>$row['email'], 'amount'=>$row['sess'].' sessions', 'source'=>'Apex Cybernet'];
} catch (Exception $e) {}

// ── SCARCITY / LOSS-AVERSION: active unpaid loans, pending bookings near event date ──
if ($ocpd_pdo_e) {
    try {
        $r = $ocpd_pdo_e->query("SELECT COUNT(*) c FROM bookings WHERE status='pending' AND event_date >= CURDATE() AND event_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)")->fetch();
        $psy['scarcity']['count'] += (int)$r['c'];
        $rs = $ocpd_pdo_e->query("SELECT first_name, last_name, email, event_date FROM bookings WHERE status='pending' AND event_date >= CURDATE() AND event_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY) ORDER BY event_date ASC LIMIT 3")->fetchAll();
        foreach ($rs as $row) $psy['scarcity']['samples'][] = ['name'=>trim($row['first_name'].' '.$row['last_name']), 'email'=>$row['email'], 'amount'=>'flight on '.$row['event_date'], 'source'=>'OCPD'];
    } catch (Exception $e) {}
}

// Total psychological exposure
$psy_total_value = array_sum(array_column($psy, 'value'));
$psy_total_count = array_sum(array_column($psy, 'count'));

// ── Highest-leverage signal: weighted score (growth × current size × overlap potential) ──
$leverage_pick = null; $leverage_score = -INF;
foreach ($emp as $k => $b) {
    $size = $b['rev_now'] > 0 ? log10($b['rev_now'] + 10) : 0;
    $g    = $b['growth'] !== null ? max(-50, min(200, $b['growth'])) : 0;
    $score = ($size * 30) + ($g * 0.6);
    if ($score > $leverage_score) { $leverage_score = $score; $leverage_pick = $k; }
}

// ── Sidebar quick-stats ──
$sidebar_stats = ['apexcybernet'=>['sessions'=>0,'live'=>0],'ocpd'=>['sessions'=>0,'live'=>0],'loan'=>['sessions'=>0,'live'=>0],'alrisha'=>['sessions'=>0,'live'=>0]];
try {
    $rows_sb = $apexcybernet_pdo->query("SELECT CASE WHEN site IS NULL OR site='' THEN 'apexcybernet' ELSE site END as s,
        COUNT(DISTINCT session_id) as n FROM activity_logs WHERE created_at >= CURDATE() GROUP BY s")->fetchAll();
    foreach ($rows_sb as $r) if (isset($sidebar_stats[$r['s']])) $sidebar_stats[$r['s']]['sessions'] = (int)$r['n'];
    $rows_sb = $apexcybernet_pdo->query("SELECT CASE WHEN site IS NULL OR site='' THEN 'apexcybernet' ELSE site END as s,
        COUNT(DISTINCT session_id) as n FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) GROUP BY s")->fetchAll();
    foreach ($rows_sb as $r) if (isset($sidebar_stats[$r['s']])) $sidebar_stats[$r['s']]['live'] = (int)$r['n'];
} catch (Exception $e) {}

// ── Panel 1: Opportunity Score ──
// For each site: traffic health (30), engagement (25), retention (25), growth (20)
$opp_scores = [];
$sites_scored = ['apexcybernet', 'ocpd', 'loan'];

foreach ($sites_scored as $site) {
    $site_cond = ($site === 'apexcybernet') ? "(site='apexcybernet' OR site IS NULL OR site='')" : "site='$site'";
    $score = 0;
    $breakdown = [];

    // Traffic health: sessions this week vs last week (30 pts)
    $sessions_this_week = 0; $sessions_last_week = 0;
    try {
        $sessions_this_week = (int)$apexcybernet_pdo->query("SELECT COUNT(DISTINCT session_id) FROM activity_logs
            WHERE $site_cond AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
        $sessions_last_week = (int)$apexcybernet_pdo->query("SELECT COUNT(DISTINCT session_id) FROM activity_logs
            WHERE $site_cond AND created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
            AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
    } catch (Exception $e) {}
    $traffic_pts = 0;
    if ($sessions_last_week > 0) {
        $ratio = $sessions_this_week / $sessions_last_week;
        $traffic_pts = (int)min(30, round($ratio * 30));
    } elseif ($sessions_this_week > 0) {
        $traffic_pts = 20;
    }
    $score += $traffic_pts;
    if ($traffic_pts < 20) $breakdown[] = 'Traffic down vs last week';

    // Engagement: avg session depth (25 pts)
    $avg_depth = 0;
    try {
        $avg_depth = (float)$apexcybernet_pdo->query("SELECT AVG(ev) FROM (
            SELECT COUNT(*) as ev FROM activity_logs
            WHERE $site_cond AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY session_id) t")->fetchColumn();
    } catch (Exception $e) {}
    $engagement_pts = 0;
    if ($avg_depth > 5) $engagement_pts = 25;
    elseif ($avg_depth > 3) $engagement_pts = 15;
    elseif ($avg_depth > 1) $engagement_pts = 8;
    $score += $engagement_pts;
    if ($engagement_pts < 15) $breakdown[] = 'Low avg session depth (' . round($avg_depth,1) . ' events)';

    // Retention: % users who returned (25 pts)
    $retention_pct = 0;
    try {
        $total_users = (int)$apexcybernet_pdo->query("SELECT COUNT(DISTINCT account_id) FROM activity_logs
            WHERE $site_cond AND account_id IS NOT NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
        $returning_users = (int)$apexcybernet_pdo->query("SELECT COUNT(DISTINCT account_id) FROM (
            SELECT account_id, COUNT(DISTINCT DATE(created_at)) as days
            FROM activity_logs
            WHERE $site_cond AND account_id IS NOT NULL
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY account_id HAVING days >= 2) t")->fetchColumn();
        if ($total_users > 0) $retention_pct = round(($returning_users / $total_users) * 100, 1);
    } catch (Exception $e) {}
    $retention_pts = (int)min(25, round($retention_pct / 4));
    $score += $retention_pts;
    if ($retention_pts < 15) $breakdown[] = 'Retention at ' . $retention_pct . '% (few returning users)';

    // Growth: MoM pageview positive = 20 pts
    $pvs_this_month = 0; $pvs_last_month = 0;
    try {
        $pvs_this_month = (int)$apexcybernet_pdo->query("SELECT COUNT(*) FROM activity_logs
            WHERE $site_cond AND event_type='pageview' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
        $pvs_last_month = (int)$apexcybernet_pdo->query("SELECT COUNT(*) FROM activity_logs
            WHERE $site_cond AND event_type='pageview'
            AND created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
            AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
    } catch (Exception $e) {}
    $growth_pts = 0;
    if ($pvs_this_month > $pvs_last_month && $pvs_last_month > 0) $growth_pts = 20;
    elseif ($pvs_last_month === 0 && $pvs_this_month > 0) $growth_pts = 10;
    $score += $growth_pts;
    if ($growth_pts < 10) $breakdown[] = 'No MoM pageview growth';

    if (empty($breakdown)) $breakdown[] = 'All signals healthy';

    $opp_scores[$site] = [
        'score' => $score,
        'traffic_pts' => $traffic_pts, 'sessions_this_week' => $sessions_this_week,
        'engagement_pts' => $engagement_pts, 'avg_depth' => round($avg_depth, 1),
        'retention_pts' => $retention_pts, 'retention_pct' => $retention_pct,
        'growth_pts' => $growth_pts, 'pvs_this' => $pvs_this_month, 'pvs_last' => $pvs_last_month,
        'breakdown' => $breakdown,
    ];
}

// ── Panel 2: Traffic Conversion Gaps ──
$conversion_gaps = [];
try {
    $st = $apexcybernet_pdo->query("SELECT page_url,
        COUNT(CASE WHEN event_type='pageview' THEN 1 END) as pvs,
        COUNT(CASE WHEN event_type='click' THEN 1 END) as clks,
        ROUND(COUNT(CASE WHEN event_type='click' THEN 1 END) / GREATEST(COUNT(CASE WHEN event_type='pageview' THEN 1 END),1) * 100, 1) as eng_rate
        FROM activity_logs
        WHERE (site='apexcybernet' OR site IS NULL OR site='')
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY page_url
        HAVING pvs > 20 AND eng_rate < 10
        ORDER BY pvs DESC LIMIT 10");
    $conversion_gaps = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) {}

// ── Panel 3: Geographic Expansion ──
$geo_data = [];
try {
    $st = $apexcybernet_pdo->query("SELECT country, COUNT(DISTINCT session_id) as sessions,
        ROUND(AVG(events_per_session),1) as avg_depth,
        GROUP_CONCAT(DISTINCT CASE WHEN site IS NULL OR site='' THEN 'apexcybernet' ELSE site END ORDER BY site SEPARATOR ', ') as sites
        FROM (
          SELECT session_id, country, site, COUNT(*) as events_per_session
          FROM activity_logs
          WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
          AND country IS NOT NULL AND country != ''
          GROUP BY session_id, country, site
        ) t
        GROUP BY country
        HAVING sessions >= 10
        ORDER BY sessions DESC LIMIT 15");
    $geo_data = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) {}

// ── Panel 4: Demand Signals ──
$demand_signals = [];
try {
    $st = $apexcybernet_pdo->query("SELECT COALESCE(NULLIF(element_text,''), element_href, element_tag, '?') AS label,
        COUNT(*) as clicks,
        COUNT(DISTINCT session_id) as unique_sessions,
        CASE WHEN site IS NULL OR site='' THEN 'apexcybernet' ELSE site END as site
        FROM activity_logs
        WHERE event_type='click'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY label, site
        ORDER BY clicks DESC LIMIT 20");
    $demand_signals = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) {}

// ── Panel 5: Retention Leaks ──
$retention_leaks = [];
try {
    $st = $apexcybernet_pdo->query("SELECT
        CASE WHEN site IS NULL OR site='' THEN 'apexcybernet' ELSE site END as site,
        COUNT(DISTINCT session_id) as lapsed_sessions,
        COUNT(DISTINCT account_id) as lapsed_known_users
        FROM activity_logs
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
        AND session_id NOT IN (
          SELECT DISTINCT session_id FROM activity_logs
          WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        )
        GROUP BY site");
    if ($st) {
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $retention_leaks[$r['site']] = $r;
        }
    }
} catch (Exception $e) {}

// ── Panel 6: Peak Demand Windows ──
$peak_windows_raw = [];
try {
    $st = $apexcybernet_pdo->query("SELECT CASE WHEN site IS NULL OR site='' THEN 'apexcybernet' ELSE site END as site,
        HOUR(created_at) as hr,
        DAYNAME(created_at) as dow,
        COUNT(DISTINCT session_id) as sessions
        FROM activity_logs
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY site, hr, dow
        ORDER BY sessions DESC");
    $peak_windows_raw = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) {}

// Process peak windows per site — top 3 hour-slots
$peak_windows = [];
foreach ($peak_windows_raw as $r) {
    $s = $r['site'];
    if (!isset($peak_windows[$s])) $peak_windows[$s] = [];
    if (count($peak_windows[$s]) < 3) $peak_windows[$s][] = $r;
}

$date_range = 'all';

// ── Revenue Engine: connect to apexcybernet_market for H-Coin data ──
$market_pdo = null;
try {
    $market_pdo = new PDO('mysql:host=localhost;dbname=apexcybernet_market', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) {}

// Account & subscription metrics
$rev_accounts_total   = 0;
$rev_accounts_month   = 0;
$rev_subs_active      = 0;
$rev_sub_mrr          = 0.0;
try {
    $rev_accounts_total  = (int)$apexcybernet_pdo->query("SELECT COUNT(*) FROM accounts")->fetchColumn();
    $rev_accounts_month  = (int)$apexcybernet_pdo->query("SELECT COUNT(*) FROM accounts WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
    $rev_subs_active     = (int)$apexcybernet_pdo->query("SELECT COUNT(*) FROM subscriptions WHERE status='active'")->fetchColumn();
    $rev_sub_mrr         = (float)$apexcybernet_pdo->query("SELECT COALESCE(SUM(amount_paid),0) FROM subscriptions WHERE status='active'")->fetchColumn();
} catch (Exception $e) {}

// Tournament metrics
$rev_teams_total   = 0;
$rev_solo_total    = 0;
$rev_paid_entries  = 0;
try {
    $rev_teams_total  = (int)$apexcybernet_pdo->query("SELECT COUNT(*) FROM teams")->fetchColumn();
    $rev_solo_total   = (int)$apexcybernet_pdo->query("SELECT COUNT(*) FROM solo_players")->fetchColumn();
    $rev_paid_entries = (int)$apexcybernet_pdo->query("SELECT COUNT(*) FROM teams WHERE status='approved'")->fetchColumn();
    $rev_paid_entries += (int)$apexcybernet_pdo->query("SELECT COUNT(*) FROM solo_players WHERE status='approved'")->fetchColumn();
} catch (Exception $e) {}

// H-Coin metrics from apexcybernet_market
$rev_hcoin_volume   = 0.0;
$rev_hcoin_txns     = 0;
$rev_market_fees    = 0.0;
$rev_market_orders  = 0;
$rev_market_users   = 0;
if ($market_pdo) {
    try {
        $rev_hcoin_volume   = (float)$market_pdo->query("SELECT COALESCE(SUM(ABS(amount)),0) FROM wallet_transactions")->fetchColumn();
        $rev_hcoin_txns     = (int)$market_pdo->query("SELECT COUNT(*) FROM wallet_transactions")->fetchColumn();
        $rev_market_fees    = (float)$market_pdo->query("SELECT COALESCE(SUM(total_fees),0) FROM platform_revenue")->fetchColumn();
        $rev_market_orders  = (int)$market_pdo->query("SELECT COUNT(*) FROM orders WHERE status='filled'")->fetchColumn();
        $rev_market_users   = (int)$market_pdo->query("SELECT COUNT(*) FROM users WHERE wallet_balance > 0")->fetchColumn();
    } catch (Exception $e) {}
}

// Behavior: untapped segments on apexcybernet.com
$rev_coins_visitors   = 0;
$rev_market_visitors  = 0;
$rev_guest_sessions   = 0;
$rev_apexcybernet_sessions = 0;
try {
    $sc = "(site='apexcybernet' OR site IS NULL OR site='')";
    $rev_coins_visitors   = (int)$apexcybernet_pdo->query("SELECT COUNT(DISTINCT session_id) FROM activity_logs WHERE $sc AND page_url LIKE '%coin%' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
    $rev_market_visitors  = (int)$apexcybernet_pdo->query("SELECT COUNT(DISTINCT session_id) FROM activity_logs WHERE $sc AND page_url LIKE '%market%' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
    $rev_guest_sessions   = (int)$apexcybernet_pdo->query("SELECT COUNT(DISTINCT session_id) FROM activity_logs WHERE $sc AND account_id IS NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
    $rev_apexcybernet_sessions = (int)$apexcybernet_pdo->query("SELECT COUNT(DISTINCT session_id) FROM activity_logs WHERE $sc AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
} catch (Exception $e) {}

// Compute register-conversion rate
$rev_register_rate = $rev_apexcybernet_sessions > 0 ? round(($rev_accounts_total / max(1,$rev_apexcybernet_sessions)) * 100, 1) : 0;

// ── Reels Maker data ──
// Best post time from apexcybernet peak windows
$reel_best_time = 'evenings';
if (!empty($peak_windows['apexcybernet'])) {
    $pw = $peak_windows['apexcybernet'][0];
    $h = (int)$pw['hr'];
    $ampm = $h < 12 ? ($h === 0 ? '12am' : $h.'am') : ($h === 12 ? '12pm' : ($h-12).'pm');
    $reel_best_time = $pw['dow'] . 's at ' . $ampm;
}

// Top geo
$reel_top_geo = !empty($geo_data) ? ($geo_data[0]['country'] ?? 'Philippines') : 'Philippines';

// Top clicked CTA text
$reel_top_cta = 'Join Now';
foreach ($demand_signals as $ds) {
    $lbl = trim($ds['label'] ?? '');
    if (strlen($lbl) > 2 && strlen($lbl) < 40 && !str_starts_with($lbl, 'http')) {
        $reel_top_cta = $lbl; break;
    }
}

// Top page people engage with (exclude homepage)
$reel_top_feature = 'tournaments';
try {
    $tp = $apexcybernet_pdo->query("SELECT page_url, COUNT(*) as cnt FROM activity_logs
        WHERE (site='apexcybernet' OR site IS NULL OR site='')
        AND event_type='pageview' AND page_url NOT LIKE '%index%' AND page_url != '/'
        AND page_url NOT LIKE '%login%' AND page_url NOT LIKE '%register%'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY page_url ORDER BY cnt DESC LIMIT 1")->fetch();
    if ($tp) $reel_top_feature = ucfirst(trim(basename($tp['page_url'], '.php'), '/') ?: 'tournaments');
} catch (Exception $e) {}

// Active prediction pool total (match_predictions removed — no HCoin wager pool)
$reel_predict_pool = 0;

// UTM links for reels
$reel_utm_awareness   = 'https://apexcybernet.com/?utm_source=ig&utm_medium=reel&utm_campaign=arg-aw';
$reel_utm_engagement  = 'https://apexcybernet.com/predict.php?utm_source=ig&utm_medium=reel&utm_campaign=arg-pred';
$reel_utm_conversion  = 'https://apexcybernet.com/login.php?tab=register&utm_source=ig&utm_medium=reel&utm_campaign=arg-reg';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Biz Ops Intelligence — Omniscient</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<?php require __DIR__ . '/omni/css.php'; ?>
<style>
.bizops-topbar { background: var(--surface); border-bottom: 1px solid var(--border); padding: 0.75rem 1.5rem; display: flex; align-items: center; gap: 0.75rem; }
.bizops-topbar h1 { margin: 0; font-size: 1.05rem; font-weight: 800; color: #fbbf24; }
.opp-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
.opp-card { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 1.2rem 1.3rem; position: relative; }
.opp-score-num { font-size: 2.6rem; font-weight: 900; line-height: 1; }
.opp-score-bar { height: 6px; border-radius: 99px; background: rgba(255,255,255,0.08); margin: 0.6rem 0 0.5rem; overflow: hidden; }
.opp-score-fill { height: 100%; border-radius: 99px; transition: width 0.5s; }
.opp-label { font-size: 0.67rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #6b7280; margin-bottom: 0.4rem; }
.opp-biz-name { font-size: 0.9rem; font-weight: 800; color: #e5e7eb; margin-bottom: 0.25rem; }
.opp-drag { font-size: 0.72rem; color: #9ca3af; margin-top: 0.5rem; }
.opp-drag li { margin-bottom: 2px; }
.opp-erp { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 1.2rem 1.3rem; display: flex; align-items: center; justify-content: center; flex-direction: column; gap: 0.4rem; color: #4b5563; }

.gap-table { width: 100%; border-collapse: collapse; font-size: 0.78rem; }
.gap-table th { padding: 0.5rem 0.75rem; text-align: left; color: #6b7280; font-weight: 600; border-bottom: 1px solid var(--border); white-space: nowrap; }
.gap-table td { padding: 0.4rem 0.75rem; border-bottom: 1px solid rgba(255,255,255,0.04); vertical-align: middle; }
.gap-table tr:hover td { background: rgba(255,255,255,0.025); }
.gap-table tr:last-child td { border-bottom: none; }
.opp-badge { display: inline-block; font-size: 0.65rem; font-weight: 700; background: rgba(251,191,36,0.12); color: #fbbf24; border: 1px solid rgba(251,191,36,0.3); border-radius: 6px; padding: 0.1rem 0.45rem; }
.redflag-row td { background: rgba(248,113,113,0.04) !important; }

.site-badge { display: inline-block; font-size: 0.65rem; font-weight: 700; border-radius: 99px; padding: 0.1rem 0.45rem; }
.site-badge.apexcybernet { background:rgba(124,58,237,0.18); color:#a78bfa; }
.site-badge.ocpd    { background:rgba(56,189,248,0.15); color:#38bdf8; }
.site-badge.loan    { background:rgba(167,139,250,0.15); color:#c4b5fd; }
.site-badge.alrisha { background:rgba(52,211,153,0.15); color:#34d399; }

.demand-rank { display: inline-block; width: 22px; height: 22px; border-radius: 50%; background: rgba(255,255,255,0.07); text-align: center; line-height: 22px; font-size: 0.68rem; font-weight: 800; color: #9ca3af; flex-shrink: 0; }
.demand-label { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 0.78rem; color: #d1d5db; }
.demand-row { display: flex; align-items: center; gap: 0.6rem; padding: 0.35rem 0; border-bottom: 1px solid rgba(255,255,255,0.04); }
.demand-row:last-child { border-bottom: none; }
.demand-clicks { font-size: 0.72rem; font-weight: 700; color: #fbbf24; white-space: nowrap; }

.leak-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); gap: 1rem; }
.leak-card { background: var(--surface2); border-radius: 12px; padding: 1rem 1.2rem; border: 1px solid var(--border); }
.leak-card .lc-site { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #6b7280; margin-bottom: 0.5rem; }
.leak-card .lc-num { font-size: 1.6rem; font-weight: 900; color: #f87171; line-height: 1; }
.leak-card .lc-sub { font-size: 0.72rem; color: #9ca3af; margin-top: 0.25rem; }
.leak-card .lc-reco { font-size: 0.72rem; color: #fbbf24; margin-top: 0.6rem; padding: 0.4rem 0.6rem; background: rgba(251,191,36,0.06); border-left: 3px solid rgba(251,191,36,0.4); border-radius: 0 6px 6px 0; }

.peak-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); gap: 1rem; }
.peak-card { background: var(--surface2); border-radius: 12px; padding: 1rem 1.2rem; border: 1px solid var(--border); }
.peak-card .pk-site { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #6b7280; margin-bottom: 0.5rem; }
.peak-card .pk-slot { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.4rem; font-size: 0.78rem; }
.peak-card .pk-slot .pk-hr { font-weight: 800; color: #60a5fa; }
.peak-card .pk-slot .pk-day { color: #9ca3af; }
.peak-card .pk-slot .pk-sess { font-size: 0.65rem; color: #4b5563; margin-left: auto; }
.peak-insight { font-size: 0.78rem; color: #d1d5db; background: rgba(96,165,250,0.06); border-left: 3px solid rgba(96,165,250,0.4); border-radius: 0 6px 6px 0; padding: 0.45rem 0.65rem; margin-top: 0.5rem; }

/* Revenue Engine */
.rev-kpi-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px,1fr)); gap: 0.75rem; margin-bottom: 1.25rem; }
.rev-kpi { background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 0.9rem 1rem; }
.rev-kpi .rk-label { font-size: 0.62rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #6b7280; margin-bottom: 0.3rem; }
.rev-kpi .rk-val { font-size: 1.5rem; font-weight: 900; line-height: 1; }
.rev-kpi .rk-sub { font-size: 0.65rem; color: #4b5563; margin-top: 0.2rem; }
.rev-opp-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px,1fr)); gap: 1rem; margin-bottom: 1.25rem; }
.rev-opp-card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 14px; padding: 1.1rem 1.2rem; position: relative; }
.rev-opp-card .roc-rank { position: absolute; top: 0.75rem; right: 0.9rem; font-size: 0.6rem; font-weight: 800; background: rgba(255,255,255,0.06); border-radius: 99px; padding: 0.1rem 0.45rem; color: #6b7280; }
.rev-opp-card .roc-icon { font-size: 1.35rem; margin-bottom: 0.5rem; }
.rev-opp-card .roc-title { font-size: 0.9rem; font-weight: 800; color: #e5e7eb; margin-bottom: 0.25rem; }
.rev-opp-card .roc-desc { font-size: 0.73rem; color: #9ca3af; margin-bottom: 0.7rem; line-height: 1.45; }
.rev-opp-card .roc-potential { font-size: 0.72rem; font-weight: 700; padding: 0.35rem 0.6rem; border-radius: 7px; display: inline-block; margin-bottom: 0.5rem; }
.rev-opp-card .roc-data { font-size: 0.68rem; color: #6b7280; border-top: 1px solid rgba(255,255,255,0.06); padding-top: 0.55rem; margin-top: 0.4rem; }
.seg-playbook-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px,1fr)); gap: 1rem; }
.seg-card { border-radius: 14px; padding: 1.15rem 1.2rem; border: 1px solid; position: relative; overflow: hidden; }
.seg-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; border-radius: 14px 14px 0 0; }
.seg-card.guest::before  { background: linear-gradient(90deg, #fbbf24, #f59e0b); }
.seg-card.coins::before  { background: linear-gradient(90deg, #a78bfa, #7c3aed); }
.seg-card.market::before { background: linear-gradient(90deg, #34d399, #059669); }
.seg-card .sc-top { display: flex; align-items: flex-start; gap: 0.75rem; margin-bottom: 0.85rem; }
.seg-card .sc-num { font-size: 2rem; font-weight: 900; line-height: 1; flex-shrink: 0; }
.seg-card .sc-meta { flex: 1; }
.seg-card .sc-title { font-size: 0.88rem; font-weight: 800; color: #f9fafb; margin-bottom: 0.2rem; }
.seg-card .sc-why { font-size: 0.72rem; color: #9ca3af; line-height: 1.45; }
.seg-card .sc-status { display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.62rem; font-weight: 700; padding: 0.15rem 0.5rem; border-radius: 99px; margin-bottom: 0.75rem; }
.seg-card .sc-steps { list-style: none; padding: 0; margin: 0 0 0.85rem; display: flex; flex-direction: column; gap: 0.45rem; }
.seg-card .sc-steps li { display: flex; align-items: flex-start; gap: 0.55rem; font-size: 0.73rem; color: #d1d5db; line-height: 1.45; }
.seg-card .sc-steps li .step-num { flex-shrink: 0; width: 18px; height: 18px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.6rem; font-weight: 800; margin-top: 1px; }
.seg-card .sc-impact { font-size: 0.7rem; font-weight: 700; padding: 0.38rem 0.65rem; border-radius: 8px; }
</style>
</head>
<body>
<div class="omni-layout">

<?php require __DIR__ . '/omni/sidebar.php'; ?>

<div class="omni-main">

<!-- Topbar -->
<div class="bizops-topbar">
    <i class="bi bi-lightbulb" style="color:#fbbf24;font-size:1.1rem;"></i>
    <h1>◈ Business Intelligence</h1>
    <span style="margin-left:auto;font-size:0.75rem;color:#6b7280;">Last 30 days · All sites</span>
</div>

<div class="wrap">

<!-- ══════ PSYCHOLOGICAL LEVERAGE LEDGER ══════
     Cialdini, not Excel. Each section surfaces people whose minds already owe you something.
     Every number is a callable option — one "yes" you've already earned the right to ask for. -->
<style>
.psy-section { background: linear-gradient(135deg, #0b0614 0%, #0a0a0f 100%); border: 1px solid rgba(236,72,153,0.2); border-radius: 14px; padding: 1.25rem 1.5rem; margin: 0 0 1rem; }
.psy-head { display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.45rem; }
.psy-head i.logo { color: #ec4899; font-size: 1.25rem; }
.psy-head h2 { font-size: 0.98rem; font-weight: 900; color: #fbcfe8; margin: 0; letter-spacing: -0.2px; }
.psy-head .psy-tag { margin-left: auto; font-size: 0.6rem; color: #ec4899; background: rgba(236,72,153,0.08); border: 1px solid rgba(236,72,153,0.3); padding: 0.18rem 0.6rem; border-radius: 99px; font-weight: 800; text-transform: uppercase; letter-spacing: 1.2px; }
.psy-quote { font-style: italic; font-size: 0.74rem; color: #6b7280; margin: 0 0 1rem; line-height: 1.5; }
.psy-empire-row { display: flex; align-items: baseline; gap: 0.85rem; margin-bottom: 1rem; padding: 0.85rem 1rem; background: rgba(236,72,153,0.04); border: 1px solid rgba(236,72,153,0.18); border-radius: 10px; }
.psy-empire-label { font-size: 0.62rem; color: #9ca3af; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 800; }
.psy-empire-value { font-size: 1.4rem; color: #e5e7eb; font-weight: 900; line-height: 1; margin-top: 2px; }
.psy-empire-sub { font-size: 0.72rem; color: #9ca3af; margin-top: 2px; }
.psy-empire-mantra { margin-left: auto; font-size: 0.78rem; font-weight: 800; color: #f9a8d4; font-style: italic; max-width: 50%; text-align: right; }
.psy-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 0.85rem; }
.psy-card { background: rgba(255,255,255,0.025); border: 1px solid rgba(255,255,255,0.08); border-radius: 10px; padding: 0.95rem 1.1rem; position: relative; }
.psy-card-head { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.45rem; }
.psy-card-head i { font-size: 1.05rem; }
.psy-card-title { font-size: 0.82rem; font-weight: 900; color: #e5e7eb; letter-spacing: -0.1px; }
.psy-card-hook { font-size: 0.72rem; color: #9ca3af; line-height: 1.5; margin-bottom: 0.7rem; }
.psy-card-metric { display: flex; gap: 1.2rem; align-items: baseline; margin-bottom: 0.65rem; padding-bottom: 0.55rem; border-bottom: 1px dashed rgba(255,255,255,0.06); }
.psy-card-count { font-size: 1.5rem; font-weight: 900; color: #e5e7eb; line-height: 1; }
.psy-card-count-lbl { font-size: 0.6rem; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; font-weight: 700; margin-top: 1px; }
.psy-card-value { margin-left: auto; text-align: right; }
.psy-card-value-num { font-size: 0.9rem; font-weight: 900; color: #fbcfe8; line-height: 1; }
.psy-card-value-lbl { font-size: 0.58rem; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; font-weight: 700; margin-top: 1px; }
.psy-sample { font-size: 0.7rem; margin-bottom: 0.3rem; display: flex; align-items: center; gap: 0.4rem; }
.psy-sample-name { font-weight: 700; color: #d1d5db; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; min-width: 0; }
.psy-sample-src { font-size: 0.58rem; color: #6b7280; background: rgba(255,255,255,0.04); padding: 0.08rem 0.4rem; border-radius: 3px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
.psy-sample-amount { font-size: 0.65rem; color: #9ca3af; }
.psy-play { margin-top: 0.65rem; padding-top: 0.55rem; border-top: 1px dashed rgba(255,255,255,0.06); font-size: 0.7rem; color: #fca5a5; line-height: 1.5; }
.psy-play b { color: #fbcfe8; font-weight: 800; }
.psy-empty { font-size: 0.72rem; color: #6b7280; font-style: italic; }
</style>

<div class="psy-section">
    <div class="psy-head">
        <i class="bi bi-bank2 logo"></i>
        <h2>Psychological Leverage Ledger</h2>
        <span class="psy-tag">Cialdini · Live</span>
    </div>
    <p class="psy-quote">"Systems don't move money. People do. And people move by the invisible debts written in their own minds." &nbsp;·&nbsp; Every account below is a yes you've already earned.</p>

    <div class="psy-empire-row">
        <div>
            <div class="psy-empire-label">Total callable leverage</div>
            <div class="psy-empire-value"><?= number_format($psy_total_count) ?> accounts</div>
            <div class="psy-empire-sub">&#8776; ₱<?= number_format($psy_total_value, 0) ?> in social/financial capital deployed — collectable on ask</div>
        </div>
        <div class="psy-empire-mantra">"Control without owning."<br>The ask you've earned costs less than the one you haven't.</div>
    </div>

    <div class="psy-grid">
        <?php foreach ($psy as $k => $p): ?>
        <div class="psy-card">
            <div class="psy-card-head">
                <i class="bi <?= $p['icon'] ?>" style="color:<?= $p['color'] ?>;"></i>
                <span class="psy-card-title"><?= htmlspecialchars($p['title']) ?></span>
            </div>
            <div class="psy-card-hook"><?= htmlspecialchars($p['hook']) ?></div>
            <div class="psy-card-metric">
                <div>
                    <div class="psy-card-count" style="color:<?= $p['color'] ?>;"><?= number_format($p['count']) ?></div>
                    <div class="psy-card-count-lbl">accounts</div>
                </div>
                <?php if ($p['value'] > 0): ?>
                <div class="psy-card-value">
                    <div class="psy-card-value-num">₱<?= number_format($p['value'], 0) ?></div>
                    <div class="psy-card-value-lbl">capital deployed</div>
                </div>
                <?php endif; ?>
            </div>
            <?php if (empty($p['samples'])): ?>
            <div class="psy-empty">No accounts in this bucket yet.</div>
            <?php else: foreach (array_slice($p['samples'], 0, 3) as $s): ?>
            <div class="psy-sample">
                <span class="psy-sample-name"><?= htmlspecialchars($s['name'] ?: '—') ?></span>
                <span class="psy-sample-src"><?= htmlspecialchars($s['source']) ?></span>
                <span class="psy-sample-amount"><?= htmlspecialchars($s['amount']) ?></span>
            </div>
            <?php endforeach; endif; ?>
            <div class="psy-play"><b>Play:</b> <?= htmlspecialchars($p['play']) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ══ Panel 1: Opportunity Scores ══ -->
<div class="palantir-section">
    <div class="palantir-header" onclick="palToggle(this)">
        <i class="bi bi-speedometer2 pal-icon" style="color:#fbbf24;"></i>
        <span>Opportunity Score</span>
        <span class="pal-badge">4 businesses</span>
        <i class="bi bi-chevron-down pal-toggle"></i>
    </div>
    <div class="palantir-body">
        <div class="opp-grid">
            <?php foreach ($opp_scores as $site => $d):
                $score = $d['score'];
                $color = $score >= 70 ? '#34d399' : ($score >= 40 ? '#fbbf24' : '#f87171');
                $names = ['apexcybernet'=>'Apex Cybernet','ocpd'=>'OCPD','loan'=>'Loan'];
            ?>
            <div class="opp-card">
                <div class="opp-label"><?= htmlspecialchars($names[$site] ?? $site) ?></div>
                <div class="opp-score-num" style="color:<?= $color ?>;"><?= $score ?></div>
                <div style="font-size:0.68rem;color:#4b5563;margin-top:2px;">/ 100</div>
                <div class="opp-score-bar">
                    <div class="opp-score-fill" style="width:<?= $score ?>%;background:<?= $color ?>;"></div>
                </div>
                <div style="display:flex;gap:0.4rem;flex-wrap:wrap;margin-bottom:0.5rem;">
                    <span style="font-size:0.62rem;background:rgba(255,255,255,0.06);border-radius:6px;padding:0.1rem 0.4rem;color:#9ca3af;" title="Traffic">T:<?= $d['traffic_pts'] ?>/30</span>
                    <span style="font-size:0.62rem;background:rgba(255,255,255,0.06);border-radius:6px;padding:0.1rem 0.4rem;color:#9ca3af;" title="Engagement">E:<?= $d['engagement_pts'] ?>/25</span>
                    <span style="font-size:0.62rem;background:rgba(255,255,255,0.06);border-radius:6px;padding:0.1rem 0.4rem;color:#9ca3af;" title="Retention">R:<?= $d['retention_pts'] ?>/25</span>
                    <span style="font-size:0.62rem;background:rgba(255,255,255,0.06);border-radius:6px;padding:0.1rem 0.4rem;color:#9ca3af;" title="Growth">G:<?= $d['growth_pts'] ?>/20</span>
                </div>
                <ul class="opp-drag" style="padding-left:1.1rem;margin:0;">
                    <?php foreach (array_slice($d['breakdown'],0,2) as $drag): ?>
                    <li><?= htmlspecialchars($drag) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>

            <!-- Alrisha: ERP only -->
            <div class="opp-erp">
                <i class="bi bi-database" style="color:#34d399;font-size:1.3rem;"></i>
                <div style="font-size:0.9rem;font-weight:800;color:#e5e7eb;">Alrisha</div>
                <div style="font-size:0.72rem;color:#4b5563;">ERP only — no activity logs</div>
            </div>
        </div>
    </div>
</div>

<!-- ══ Panel 2: Traffic Conversion Gaps ══ -->
<div class="palantir-section">
    <div class="palantir-header" onclick="palToggle(this)">
        <i class="bi bi-funnel pal-icon" style="color:#f87171;"></i>
        <span>Traffic Conversion Gaps</span>
        <span class="pal-badge"><?= count($conversion_gaps) ?> dead pages</span>
        <i class="bi bi-chevron-down pal-toggle"></i>
    </div>
    <div class="palantir-body">
        <p style="font-size:0.78rem;color:#6b7280;margin-bottom:1rem;">Pages with high traffic but low engagement (&lt;10% click rate, &gt;20 views in last 30d). These are pages users visit but don't interact with.</p>
        <?php if (empty($conversion_gaps)): ?>
        <p style="color:#4b5563;font-size:0.78rem;">No dead pages found — all high-traffic pages have adequate engagement.</p>
        <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="gap-table">
            <thead>
                <tr>
                    <th>Page</th>
                    <th>Views</th>
                    <th>Clicks</th>
                    <th>Eng Rate</th>
                    <th>Opportunity</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($conversion_gaps as $g): ?>
                <tr class="<?= (float)$g['eng_rate'] < 5 ? 'redflag-row' : '' ?>">
                    <td style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#a78bfa;" title="<?= htmlspecialchars($g['page_url']) ?>">
                        <?= htmlspecialchars(short_url($g['page_url'])) ?>
                        <?php if ((float)$g['eng_rate'] < 5): ?>
                        <span style="color:#f87171;font-size:0.68rem;margin-left:4px;">⚠</span>
                        <?php endif; ?>
                    </td>
                    <td><?= number_format((int)$g['pvs']) ?></td>
                    <td><?= number_format((int)$g['clks']) ?></td>
                    <td style="color:<?= (float)$g['eng_rate'] < 5 ? '#f87171' : '#fbbf24' ?>;font-weight:700;"><?= htmlspecialchars($g['eng_rate']) ?>%</td>
                    <td><span class="opp-badge">High Traffic / Low Action → Add CTA here</span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ══ Panel 3: Geographic Expansion ══ -->
<div class="palantir-section">
    <div class="palantir-header" onclick="palToggle(this)">
        <i class="bi bi-globe pal-icon" style="color:#60a5fa;"></i>
        <span>Geographic Expansion</span>
        <span class="pal-badge"><?= count($geo_data) ?> markets</span>
        <i class="bi bi-chevron-down pal-toggle"></i>
    </div>
    <div class="palantir-body">
        <p style="font-size:0.78rem;color:#6b7280;margin-bottom:1rem;">Countries with significant traffic (≥10 sessions/30d) that may be underserved — low session depth signals content mismatch.</p>
        <?php if (empty($geo_data)): ?>
        <p style="color:#4b5563;font-size:0.78rem;">Not enough geographic data yet.</p>
        <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="gap-table">
            <thead>
                <tr>
                    <th>Country</th>
                    <th>Sessions</th>
                    <th>Avg Depth</th>
                    <th>Sites</th>
                    <th>Opportunity</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($geo_data as $g):
                    $flag = country_flag($g['country'] ?? '');
                    $depth = (float)$g['avg_depth'];
                ?>
                <tr>
                    <td><span class="country-flag"><?= $flag ?></span><?= htmlspecialchars($g['country']) ?></td>
                    <td><?= number_format((int)$g['sessions']) ?></td>
                    <td style="color:<?= $depth < 2 ? '#f87171' : ($depth < 3 ? '#fbbf24' : '#34d399') ?>;font-weight:700;"><?= $depth ?></td>
                    <td>
                        <?php foreach (explode(', ', $g['sites'] ?? '') as $s): $s = trim($s); ?>
                        <span class="site-badge <?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></span>
                        <?php endforeach; ?>
                    </td>
                    <td>
                        <?php if ($depth < 2): ?>
                        <span style="font-size:0.72rem;color:#fbbf24;">High traffic, low engagement → localize content</span>
                        <?php elseif ($depth < 3): ?>
                        <span style="font-size:0.72rem;color:#9ca3af;">Moderate engagement — consider targeted landing page</span>
                        <?php else: ?>
                        <span style="font-size:0.72rem;color:#34d399;">Engaged market — invest further</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ══ Panel 4: Demand Signals ══ -->
<div class="palantir-section">
    <div class="palantir-header" onclick="palToggle(this)">
        <i class="bi bi-cursor pal-icon" style="color:#a78bfa;"></i>
        <span>Demand Signals</span>
        <span class="pal-badge">Top 20 clicks</span>
        <i class="bi bi-chevron-down pal-toggle"></i>
    </div>
    <div class="palantir-body">
        <p style="font-size:0.78rem;color:#6b7280;margin-bottom:1rem;">Most-clicked elements across all sites in the last 30 days — what users actually want.</p>
        <?php if (empty($demand_signals)): ?>
        <p style="color:#4b5563;font-size:0.78rem;">No click data available.</p>
        <?php else: ?>
        <?php $top = $demand_signals[0] ?? null; ?>
        <?php if ($top): ?>
        <div style="background:rgba(167,139,250,0.08);border-left:3px solid #a78bfa;border-radius:0 8px 8px 0;padding:0.6rem 0.9rem;margin-bottom:1rem;font-size:0.78rem;color:#c4b5fd;">
            <strong>Insight:</strong> Users click "<?= htmlspecialchars(mb_substr($top['label'],0,60)) ?>" most — make sure this path converts.
        </div>
        <?php endif; ?>
        <div>
            <?php foreach ($demand_signals as $i => $d): ?>
            <div class="demand-row">
                <span class="demand-rank"><?= $i+1 ?></span>
                <span class="demand-label" title="<?= htmlspecialchars($d['label']) ?>"><?= htmlspecialchars(mb_substr($d['label'],0,80)) ?></span>
                <span class="site-badge <?= htmlspecialchars($d['site']) ?>"><?= htmlspecialchars($d['site']) ?></span>
                <span class="demand-clicks"><?= number_format((int)$d['clicks']) ?> clicks</span>
                <span style="font-size:0.65rem;color:#4b5563;"><?= number_format((int)$d['unique_sessions']) ?> sess</span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ══ Panel 5: Retention Leaks ══ -->
<div class="palantir-section">
    <div class="palantir-header" onclick="palToggle(this)">
        <i class="bi bi-droplet-half pal-icon" style="color:#f87171;"></i>
        <span>Retention Leaks</span>
        <span class="pal-badge">Lapsed users</span>
        <i class="bi bi-chevron-down pal-toggle"></i>
    </div>
    <div class="palantir-body">
        <p style="font-size:0.78rem;color:#6b7280;margin-bottom:1rem;">Sessions/users who visited 8–30 days ago but NOT in the last 7 days — ripe for re-engagement campaigns.</p>
        <?php if (empty($retention_leaks)): ?>
        <p style="color:#4b5563;font-size:0.78rem;">No lapsed session data found.</p>
        <?php else: ?>
        <div class="leak-grid">
            <?php foreach ($retention_leaks as $site => $l):
                $site_names = ['apexcybernet'=>'Apex Cybernet','ocpd'=>'OCPD','loan'=>'Loan'];
                $known = (int)$l['lapsed_known_users'];
                $sess = (int)$l['lapsed_sessions'];
            ?>
            <div class="leak-card">
                <div class="lc-site"><?= htmlspecialchars($site_names[$site] ?? $site) ?></div>
                <div class="lc-num"><?= number_format($sess) ?></div>
                <div class="lc-sub">lapsed sessions</div>
                <?php if ($known > 0): ?>
                <div class="lc-sub" style="margin-top:0.25rem;"><?= number_format($known) ?> known users</div>
                <?php endif; ?>
                <div class="lc-reco">Send re-engagement push/email to these <?= $known > 0 ? number_format($known) : number_format($sess) ?> <?= $known > 0 ? 'users' : 'sessions' ?>.</div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ══ Panel 6: Peak Demand Windows ══ -->
<div class="palantir-section">
    <div class="palantir-header" onclick="palToggle(this)">
        <i class="bi bi-clock pal-icon" style="color:#34d399;"></i>
        <span>Peak Demand Windows</span>
        <span class="pal-badge">Best time to post/run ads</span>
        <i class="bi bi-chevron-down pal-toggle"></i>
    </div>
    <div class="palantir-body">
        <p style="font-size:0.78rem;color:#6b7280;margin-bottom:1rem;">Top session-hour slots per site in the last 30 days. Use these windows for posting, ads, and promotions.</p>
        <?php if (empty($peak_windows)): ?>
        <p style="color:#4b5563;font-size:0.78rem;">Not enough data yet.</p>
        <?php else: ?>
        <div class="peak-grid">
            <?php foreach ($peak_windows as $site => $slots):
                $site_names = ['apexcybernet'=>'Apex Cybernet','ocpd'=>'OCPD','loan'=>'Loan'];
                // Build plain-English insight
                $hours_str = [];
                foreach ($slots as $sl) {
                    $hr = (int)$sl['hr'];
                    $ampm = $hr < 12 ? ($hr === 0 ? '12am' : $hr.'am') : ($hr === 12 ? '12pm' : ($hr-12).'pm');
                    $hours_str[] = $ampm . ' on ' . $sl['dow'];
                }
            ?>
            <div class="peak-card">
                <div class="pk-site"><?= htmlspecialchars($site_names[$site] ?? $site) ?></div>
                <?php foreach ($slots as $sl):
                    $hr = (int)$sl['hr'];
                    $ampm = $hr < 12 ? ($hr === 0 ? '12am' : $hr.'am') : ($hr === 12 ? '12pm' : ($hr-12).'pm');
                ?>
                <div class="pk-slot">
                    <span class="pk-hr"><?= $ampm ?></span>
                    <span class="pk-day"><?= htmlspecialchars($sl['dow']) ?></span>
                    <span class="pk-sess"><?= number_format((int)$sl['sessions']) ?> sess</span>
                </div>
                <?php endforeach; ?>
                <div class="peak-insight">
                    <?= htmlspecialchars($site_names[$site] ?? $site) ?> peaks at <?= implode(', ', $hours_str) ?>.
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ══ Revenue Engine ══ -->
<div class="palantir-section" id="pal-revenue">
    <div class="palantir-header" onclick="palToggle('pal-revenue')">
        <i class="bi bi-currency-dollar pal-icon" style="color:#34d399;"></i>
        <span>Revenue Engine</span>
        <span class="pal-badge" style="background:rgba(52,211,153,0.15);color:#34d399;border-color:rgba(52,211,153,0.3);">Apex Cybernet · What to build next</span>
        <i class="bi bi-chevron-down pal-toggle"></i>
    </div>
    <div class="palantir-body">

    <!-- KPI row -->
    <div class="rev-kpi-row">
        <div class="rev-kpi">
            <div class="rk-label">Registered Users</div>
            <div class="rk-val" style="color:#a78bfa;"><?= number_format($rev_accounts_total) ?></div>
            <div class="rk-sub">+<?= number_format($rev_accounts_month) ?> this month</div>
        </div>
        <div class="rev-kpi">
            <div class="rk-label">Active Subscriptions</div>
            <div class="rk-val" style="color:#34d399;"><?= number_format($rev_subs_active) ?></div>
            <div class="rk-sub">₱<?= number_format($rev_sub_mrr, 2) ?> MRR</div>
        </div>
        <div class="rev-kpi">
            <div class="rk-label">Tournament Entries</div>
            <div class="rk-val" style="color:#60a5fa;"><?= number_format($rev_teams_total + $rev_solo_total) ?></div>
            <div class="rk-sub"><?= number_format($rev_paid_entries) ?> paid / <?= number_format($rev_teams_total) ?> teams · <?= number_format($rev_solo_total) ?> solo</div>
        </div>
        <div class="rev-kpi">
            <div class="rk-label">H-Coin Volume</div>
            <div class="rk-val" style="color:#fbbf24;"><?= number_format($rev_hcoin_volume, 0) ?></div>
            <div class="rk-sub"><?= number_format($rev_hcoin_txns) ?> txns · <?= number_format($rev_market_orders) ?> filled orders</div>
        </div>
        <div class="rev-kpi">
            <div class="rk-label">Platform Fees Earned</div>
            <div class="rk-val" style="color:#f87171;"><?= number_format($rev_market_fees, 2) ?></div>
            <div class="rk-sub">from marketplace trades</div>
        </div>
        <div class="rev-kpi">
            <div class="rk-label">Guest Sessions (30d)</div>
            <div class="rk-val" style="color:#9ca3af;"><?= number_format($rev_guest_sessions) ?></div>
            <div class="rk-sub"><?= $rev_register_rate ?>% visitor-to-register rate</div>
        </div>
    </div>

    <!-- Untapped Segments — Playbook -->
    <div style="margin-bottom:1.5rem;">
        <div style="font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:#6b7280;margin-bottom:0.85rem;">
            <i class="bi bi-people" style="color:#fbbf24;margin-right:4px;"></i>Untapped Segments — Playbook
        </div>
        <?php if ($rev_guest_sessions === 0 && $rev_coins_visitors === 0 && $rev_market_visitors === 0): ?>
        <div style="font-size:0.75rem;color:#4b5563;">Run ads to apexcybernet.com to populate segment data.</div>
        <?php else: ?>
        <div class="seg-playbook-grid">

        <?php if ($rev_guest_sessions > 0): ?>
        <div class="seg-card guest" style="background:rgba(251,191,36,0.04);border-color:rgba(251,191,36,0.18);">
            <div class="sc-top">
                <div class="sc-num" style="color:#fbbf24;"><?= number_format($rev_guest_sessions) ?></div>
                <div class="sc-meta">
                    <div class="sc-title">Unregistered Visitors</div>
                    <div class="sc-why">Browsed Apex Cybernet in the last 30 days but never created an account. These are warm leads — they found you, they just need a push.</div>
                </div>
            </div>
            <span class="sc-status" style="background:rgba(52,211,153,0.12);color:#34d399;border:1px solid rgba(52,211,153,0.2);">
                <i class="bi bi-check-circle-fill"></i> Guest banner active (fires at 30s)
            </span>
            <ul class="sc-steps">
                <li>
                    <span class="step-num" style="background:rgba(251,191,36,0.15);color:#fbbf24;">1</span>
                    <span><strong style="color:#e5e7eb;">Reduce signup friction</strong> — add Google/Facebook OAuth so visitors can join in one click instead of filling a form.</span>
                </li>
                <li>
                    <span class="step-num" style="background:rgba(251,191,36,0.15);color:#fbbf24;">2</span>
                    <span><strong style="color:#e5e7eb;">Gate a feature they already want</strong> — show tournament brackets to guests, but blur the "Join Team" button with a "Sign up to register" prompt right where they click.</span>
                </li>
                <li>
                    <span class="step-num" style="background:rgba(251,191,36,0.15);color:#fbbf24;">3</span>
                    <span><strong style="color:#e5e7eb;">Welcome bonus on register page</strong> — "Get 20 H-Coins free when you create your account" is already live. First-time incentives lift registration 20–40%.</span>
                </li>
                <li>
                    <span class="step-num" style="background:rgba(251,191,36,0.15);color:#fbbf24;">4</span>
                    <span><strong style="color:#e5e7eb;">Retarget on Facebook</strong> — upload a Custom Audience of these sessions (use the Seed CSV from OCPD FB Intelligence) and run a ₱150/day "Join Apex Cybernet" ad.</span>
                </li>
            </ul>
            <div class="sc-impact" style="background:rgba(251,191,36,0.08);color:#fbbf24;border:1px solid rgba(251,191,36,0.2);">
                At 10% conversion → +<?= number_format((int)round($rev_guest_sessions * 0.10)) ?> new accounts · At 5% buy H-Coins → +₱<?= number_format((int)round($rev_guest_sessions * 0.10 * 0.05 * 99)) ?> revenue
            </div>
        </div>
        <?php endif; ?>

        <?php if ($rev_coins_visitors > 0): ?>
        <div class="seg-card coins" style="background:rgba(167,139,250,0.04);border-color:rgba(167,139,250,0.18);">
            <div class="sc-top">
                <div class="sc-num" style="color:#a78bfa;"><?= number_format($rev_coins_visitors) ?></div>
                <div class="sc-meta">
                    <div class="sc-title">Coins Page Visitors</div>
                    <div class="sc-why">Visited the H-Coins page but didn't buy. They know H-Coins exist and are curious — the barrier is price hesitation or not knowing what to do with coins.</div>
                </div>
            </div>
            <span class="sc-status" style="background:rgba(248,113,113,0.1);color:#f87171;border:1px solid rgba(248,113,113,0.2);">
                <i class="bi bi-exclamation-circle"></i> No active conversion on this page
            </span>
            <ul class="sc-steps">
                <li>
                    <span class="step-num" style="background:rgba(167,139,250,0.15);color:#a78bfa;">1</span>
                    <span><strong style="color:#e5e7eb;">Add "What can I do with H-Coins?" section</strong> — most visitors don't buy because they don't understand the value. List: trade in marketplace, enter tournaments, unlock profile badges, stake on matches.</span>
                </li>
                <li>
                    <span class="step-num" style="background:rgba(167,139,250,0.15);color:#a78bfa;">2</span>
                    <span><strong style="color:#e5e7eb;">Starter Pack anchor pricing</strong> — show three tiers at ₱1/HC: 100 HC (₱100), 500 HC (₱500) ← "Most Popular", 2,000 HC (₱2,000). Middle-tier anchoring increases average order value.</span>
                </li>
                <li>
                    <span class="step-num" style="background:rgba(167,139,250,0.15);color:#a78bfa;">3</span>
                    <span><strong style="color:#e5e7eb;">Time-limited offer banner</strong> — "Double H-Coins this week only" creates urgency without permanently discounting. Run it for 7 days and measure lift.</span>
                </li>
                <li>
                    <span class="step-num" style="background:rgba(167,139,250,0.15);color:#a78bfa;">4</span>
                    <span><strong style="color:#e5e7eb;">Show live coin activity</strong> — a live ticker like "JohnD just bought 500 H-Coins" uses social proof to nudge fence-sitters.</span>
                </li>
            </ul>
            <div class="sc-impact" style="background:rgba(167,139,250,0.08);color:#a78bfa;border:1px solid rgba(167,139,250,0.2);">
                At 15% conversion → +<?= number_format((int)round($rev_coins_visitors * 0.15)) ?> purchases · Avg ₱99 → +₱<?= number_format((int)round($rev_coins_visitors * 0.15 * 99)) ?> revenue
            </div>
        </div>
        <?php endif; ?>

        <?php if ($rev_market_visitors > 0): ?>
        <div class="seg-card market" style="background:rgba(52,211,153,0.04);border-color:rgba(52,211,153,0.18);">
            <div class="sc-top">
                <div class="sc-num" style="color:#34d399;"><?= number_format($rev_market_visitors) ?></div>
                <div class="sc-meta">
                    <div class="sc-title">Marketplace Browsers</div>
                    <div class="sc-why">Visited the marketplace but didn't list or buy anything. They're window-shopping — either the listings aren't compelling, or they don't know how to participate as sellers.</div>
                </div>
            </div>
            <span class="sc-status" style="background:rgba(248,113,113,0.1);color:#f87171;border:1px solid rgba(248,113,113,0.2);">
                <i class="bi bi-exclamation-circle"></i> No seller onboarding flow
            </span>
            <ul class="sc-steps">
                <li>
                    <span class="step-num" style="background:rgba(52,211,153,0.15);color:#34d399;">1</span>
                    <span><strong style="color:#e5e7eb;">Add "Sell something" empty-state CTA</strong> — if the marketplace has few listings, browsers leave immediately. A prominent "Be the first to list in [category]" prompt turns browsers into sellers.</span>
                </li>
                <li>
                    <span class="step-num" style="background:rgba(52,211,153,0.15);color:#34d399;">2</span>
                    <span><strong style="color:#e5e7eb;">Free first listing, paid to feature</strong> — zero barrier to list, but charge ₱49 to pin to the top. Sellers who list always come back to check sales, increasing retention.</span>
                </li>
                <li>
                    <span class="step-num" style="background:rgba(52,211,153,0.15);color:#34d399;">3</span>
                    <span><strong style="color:#e5e7eb;">H-Coin marketplace category</strong> — add a section where users can buy/sell H-Coins directly from each other. Platform takes a 5% cut. Drives both marketplace and H-Coin engagement simultaneously.</span>
                </li>
                <li>
                    <span class="step-num" style="background:rgba(52,211,153,0.15);color:#34d399;">4</span>
                    <span><strong style="color:#e5e7eb;">Email/notification blast</strong> — send "New listings in the marketplace" notification to all registered accounts who browsed it. Re-engagement with a reason.</span>
                </li>
            </ul>
            <div class="sc-impact" style="background:rgba(52,211,153,0.08);color:#34d399;border:1px solid rgba(52,211,153,0.2);">
                At 8% list/buy rate → +<?= number_format((int)round($rev_market_visitors * 0.08)) ?> active market users · Platform 5% cut on each transaction
            </div>
        </div>
        <?php endif; ?>

        </div><!-- /.seg-playbook-grid -->
        <?php endif; ?>
    </div>

    <!-- Ranked Revenue Opportunities -->
    <div style="font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:#6b7280;margin-bottom:0.75rem;"><i class="bi bi-trophy" style="color:#fbbf24;margin-right:4px;"></i>Ranked Revenue Opportunities</div>
    <div class="rev-opp-grid">

        <div class="rev-opp-card" style="border-color:rgba(251,191,36,0.2);">
            <span class="roc-rank">#1</span>
            <div class="roc-icon">🏆</div>
            <div class="roc-title">Premium Tournament Entry</div>
            <div class="roc-desc">Add a "Premium Bracket" tier at ₱199–₱299/team. Includes seeded placement, highlight reel, and priority scheduling. Low build effort — you already have tournament registration running.</div>
            <div class="roc-potential" style="background:rgba(251,191,36,0.12);color:#fbbf24;">₱199–₱299 per team · Immediate</div>
            <div class="roc-data">
                <?= number_format($rev_teams_total) ?> teams registered · <?= number_format($rev_solo_total) ?> solo players
                <?php if ($rev_teams_total > 0): ?> — ₱<?= number_format((int)round($rev_teams_total * 249 * 0.5)) ?> one-time revenue if 50% upgrade<?php endif; ?>
            </div>
        </div>

        <div class="rev-opp-card" style="border-color:rgba(167,139,250,0.2);">
            <span class="roc-rank">#2</span>
            <div class="roc-icon">⚡</div>
            <div class="roc-title">H-Coin Starter Pack</div>
            <div class="roc-desc">Sell a "Starter Pack" of 500 H-Coins for ₱99. Target every new account on signup and every coins-page visitor who hasn't bought. 1 in 5 new users typically converts on a low-price first offer.</div>
            <div class="roc-potential" style="background:rgba(167,139,250,0.12);color:#a78bfa;">₱99/pack · High volume potential</div>
            <div class="roc-data">
                <?= number_format($rev_coins_visitors) ?> coins-page visitors last 30d · <?= number_format($rev_market_users) ?> users with active balance
            </div>
        </div>

        <div class="rev-opp-card" style="border-color:rgba(52,211,153,0.2);">
            <span class="roc-rank">#3</span>
            <div class="roc-icon">🎯</div>
            <div class="roc-title">Prediction Staking (Mini-Game)</div>
            <div class="roc-desc">Let users stake H-Coins on tournament match outcomes. Platform takes a 5–10% house fee on each staking pool. Drives H-Coin circulation, retention, and repeat visits during tournament days.</div>
            <div class="roc-potential" style="background:rgba(52,211,153,0.12);color:#34d399;">5–10% of staked pool · Zero marginal cost</div>
            <div class="roc-data">
                Requires: match scheduling table (already exists) + new `staking_pools` table + H-Coin escrow logic
            </div>
        </div>

        <div class="rev-opp-card" style="border-color:rgba(96,165,250,0.2);">
            <span class="roc-rank">#4</span>
            <div class="roc-icon">⭐</div>
            <div class="roc-title">Apex Cybernet Pro Pass</div>
            <div class="roc-desc">Monthly subscription at ₱149/month. Perks: no ads, early tournament registration, profile badge, 100 H-Coins/month bonus, exclusive Discord/community access. Subscriptions table is already built.</div>
            <div class="roc-potential" style="background:rgba(96,165,250,0.12);color:#60a5fa;">₱149/mo · Recurring MRR</div>
            <div class="roc-data">
                <?= number_format($rev_subs_active) ?> active subs now · <?= number_format($rev_accounts_total) ?> total accounts to upsell
                — ₱<?= number_format($rev_accounts_total * 149 * 0.05, 0) ?> MRR at 5% conversion
            </div>
        </div>

        <div class="rev-opp-card" style="border-color:rgba(248,113,113,0.2);">
            <span class="roc-rank">#5</span>
            <div class="roc-icon">📌</div>
            <div class="roc-title">Featured Marketplace Listings</div>
            <div class="roc-desc">Charge ₱49–₱149 to pin a listing to the top of the marketplace for 7 days. Sellers get a "Featured" badge. Every marketplace has this — it's frictionless because sellers see ROI immediately.</div>
            <div class="roc-potential" style="background:rgba(248,113,113,0.12);color:#f87171;">₱49–₱149/week per listing</div>
            <div class="roc-data">
                <?= number_format($rev_market_visitors) ?> marketplace page visitors last 30d · Add `featured` flag + `expires_at` column to listings table
            </div>
        </div>

        <div class="rev-opp-card" style="border-color:rgba(251,191,36,0.15);">
            <span class="roc-rank">#6</span>
            <div class="roc-icon">🏢</div>
            <div class="roc-title">Tournament Naming Rights</div>
            <div class="roc-desc">Sell sponsors the ability to brand a tournament: "BRAND presents: Apex Cybernet Cup". Local gaming cafes, peripheral brands, and energy drink companies pay ₱2,000–₱10,000/tournament for brand exposure.</div>
            <div class="roc-potential" style="background:rgba(251,191,36,0.1);color:#fbbf24;">₱2,000–₱10,000 per event · Zero tech cost</div>
            <div class="roc-data">Tournaments are implicit (via <code>game</code> field on teams/matches) — needs a new lightweight <code>tournaments</code> table with sponsor fields, or a sponsor row per game/season. Display logo on bracket screen + tournament heading.</div>
        </div>

        <div class="rev-opp-card" style="border-color:rgba(52,211,153,0.15);">
            <span class="roc-rank">#7</span>
            <div class="roc-icon">🎓</div>
            <div class="roc-title">Coaching Marketplace</div>
            <div class="roc-desc">Let top-ranked tournament players sell 1-on-1 coaching sessions via the platform. Apex Cybernet takes 15–20% cut. Coaches set their own price (₱100–₱500/hr).</div>
            <div class="roc-potential" style="background:rgba(52,211,153,0.1);color:#34d399;">15–20% of each session · High engagement</div>
            <div class="roc-data">Requires new <code>coaching_sessions</code> table (coach_id, booked_time, status, price) + coach profile pages (reuses tournament_results rank data). Not reusable from marketplace_listings — different shape.</div>
        </div>

        <div class="rev-opp-card" style="border-color:rgba(167,139,250,0.15);">
            <span class="roc-rank">#8</span>
            <div class="roc-icon">👕</div>
            <div class="roc-title">Team Merch Store</div>
            <div class="roc-desc">Let teams sell branded merch (jerseys, mousepads) through Apex Cybernet. Print-on-demand integration (e.g. Printful). Platform takes 10% commission. Teams love having official storefronts — drives pride and virality.</div>
            <div class="roc-potential" style="background:rgba(167,139,250,0.1);color:#a78bfa;">10% per sale · Viral team identity</div>
            <div class="roc-data">Requires: team store page + product upload + payment + POD webhook · <?= number_format($rev_teams_total) ?> teams to launch with</div>
        </div>

    </div>

    <div style="font-size:0.7rem;color:#4b5563;padding:0.5rem 0.75rem;background:rgba(255,255,255,0.025);border-radius:8px;">
        <i class="bi bi-info-circle" style="color:#6b7280;margin-right:4px;"></i>
        Opportunities ranked by: low build effort × high revenue potential × existing data leverage. Start with #1 (zero new tables) then #2 (signup flow change only).
    </div>

    <!-- ══ Capital & Crowdfunding Strategies ══ -->
    <div style="font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:#6b7280;margin:1.5rem 0 0.75rem;"><i class="bi bi-bank" style="color:#22c55e;margin-right:4px;"></i>Capital &amp; Crowdfunding Strategies</div>
    <div style="font-size:0.75rem;color:#9ca3af;margin-bottom:0.9rem;line-height:1.55;">
        Recurring revenue above grows the bottom line. These strategies raise <strong style="color:#e5e7eb;">one-time capital</strong> to fund tournaments, marketing, infra, or reach the next milestone without bootstrapping from revenue alone.
    </div>
    <div class="rev-opp-grid">

        <div class="rev-opp-card" style="border-color:rgba(251,191,36,0.2);">
            <span class="roc-rank">#1</span>
            <div class="roc-icon">🏆</div>
            <div class="roc-title">Tournament Prize Pool Crowdfund</div>
            <div class="roc-desc">Let users contribute HC or ₱ to boost a specific tournament's prize pool — Dota 2's <em>International</em> raised $40M this way via Battle Pass. Platform takes 10–15% as organizing fee; rest goes to winners. Drives hype, participation, and user investment in the outcome.</div>
            <div class="roc-potential" style="background:rgba(251,191,36,0.12);color:#fbbf24;">₱20k–₱100k+ per major event · 10–15% take</div>
            <div class="roc-data">
                <?= number_format($rev_teams_total) ?> teams registered · <?= number_format($rev_accounts_total) ?> accounts to solicit · New <code>prize_pool_contributions</code> table + UI on bracket page
            </div>
        </div>

        <div class="rev-opp-card" style="border-color:rgba(167,139,250,0.2);">
            <span class="roc-rank">#2</span>
            <div class="roc-icon">⚡</div>
            <div class="roc-title">HCoin Founder's Pre-sale</div>
            <div class="roc-desc">Sell a limited batch (e.g. 500,000 HC) at a 30% founder discount — ₱0.70/HC vs ₱1.00. Capped raise ~₱350,000. Buyers get a permanent "Founder" badge + locked-in rate. Runs entirely on the existing internal HC ledger — no blockchain needed.</div>
            <div class="roc-potential" style="background:rgba(167,139,250,0.12);color:#a78bfa;">₱350,000 one-time · Zero equity</div>
            <div class="roc-data">
                Add pre-sale UI to coins.php, cap via a <code>presale_purchases</code> ledger, flag "Founder" status on accounts.
            </div>
        </div>

        <div class="rev-opp-card" style="border-color:rgba(52,211,153,0.2);">
            <span class="roc-rank">#3</span>
            <div class="roc-icon">🚀</div>
            <div class="roc-title">Kickstarter / Spark Project Campaign</div>
            <div class="roc-desc">Launch a 30–45 day crowdfunding campaign with tiered rewards. ₱500 → Founding Member badge + 1,000 HC · ₱2,500 → Lifetime Pro Pass + engraved avatar · ₱10,000 → permanent name on Hall of Fame + 1-on-1 Discord call. Typical gaming campaigns hit 50–200 backers.</div>
            <div class="roc-potential" style="background:rgba(52,211,153,0.12);color:#34d399;">₱100k–₱500k · 30–45 day campaign</div>
            <div class="roc-data">
                Platform: Spark Project (PH) or Kickstarter (global). Requires campaign video + rewards fulfillment plan. Use existing accounts table to track backers post-campaign.
            </div>
        </div>

        <div class="rev-opp-card" style="border-color:rgba(96,165,250,0.2);">
            <span class="roc-rank">#4</span>
            <div class="roc-icon">🏢</div>
            <div class="roc-title">Annual Sponsorship Packages</div>
            <div class="roc-desc">Sell a full-year sponsorship bundle: ₱50k Bronze (logo on 3 tournaments) · ₱150k Silver (all events + bracket watermark) · ₱500k Gold (naming rights + banner ads + hosted segments). Target internet cafes, peripherals brands, energy drinks, telcos — the same ones already advertising on local esports.</div>
            <div class="roc-potential" style="background:rgba(96,165,250,0.12);color:#60a5fa;">₱200k–₱2.5M/yr · 3–5 sponsors</div>
            <div class="roc-data">
                Build a one-page <code>sponsor.php</code> pitch deck with audience metrics. <?= number_format($rev_accounts_total) ?> accounts + <?= number_format($rev_apexcybernet_sessions) ?> last-30d sessions is already a pitchable reach.
            </div>
        </div>

        <div class="rev-opp-card" style="border-color:rgba(248,113,113,0.2);">
            <span class="roc-rank">#5</span>
            <div class="roc-icon">👥</div>
            <div class="roc-title">Angel Syndicate (Gaming Founders Round)</div>
            <div class="roc-desc">Form a syndicate of 10–20 local investors contributing ₱25k–₱100k each. Use a SAFE (Simple Agreement for Future Equity) or convertible note — debt that converts to equity at your next priced round. Network effect: each investor becomes a promoter in their own community.</div>
            <div class="roc-potential" style="background:rgba(248,113,113,0.12);color:#f87171;">₱500k–₱2M · No valuation set yet</div>
            <div class="roc-data">
                Prep: 2-page investor brief, financial projection, SAFE template (standard ~5–20% discount + valuation cap). Target Cebu/Manila gaming community + local SME owners.
            </div>
        </div>

        <div class="rev-opp-card" style="border-color:rgba(251,191,36,0.15);">
            <span class="roc-rank">#6</span>
            <div class="roc-icon">🏛️</div>
            <div class="roc-title">DOST-PCIEERD Startup Grant Fund</div>
            <div class="roc-desc">Apply for DOST-PCIEERD's Startup Grant Fund — up to ₱5M for R&amp;D over an 18-month project. Non-repayable if deliverables hit. You qualify: needs DTI/SEC registration (1–7 years old) + a working prototype (Apex Cybernet is already live).</div>
            <div class="roc-potential" style="background:rgba(251,191,36,0.1);color:#fbbf24;">Up to ₱5M · 18-mo project · Zero dilution</div>
            <div class="roc-data">
                R&amp;D angles that fit: matchmaking/skill-rating algorithm, prediction-pool fraud/collusion detection, esports analytics platform for Philippine orgs. Apply via DPMIS (DOST Project Management Information System) — <strong>not</strong> PhilGEPS. Proposal ~10–20 pages.
            </div>
        </div>

        <div class="rev-opp-card" style="border-color:rgba(52,211,153,0.15);">
            <span class="roc-rank">#7</span>
            <div class="roc-icon">🔒</div>
            <div class="roc-title">HCoin Staking Lock-up</div>
            <div class="roc-desc">Let users lock HC for 30/60/90 days in exchange for a 2–5% yield bonus on unlock. Reduces HC in active circulation, which cuts redemption pressure on the ₱ reserve backing HC sales — smooths cash flow during tournament prize payouts. Not literal capital, but effective float management.</div>
            <div class="roc-potential" style="background:rgba(52,211,153,0.1);color:#34d399;">Float 500k+ HC · Improves cash buffer</div>
            <div class="roc-data">
                Requires: <code>staking_positions</code> table + lock mechanics in send/receive logic. Yield paid from tournament entry fees or marketplace commissions. Best paired with a clear HC→₱ redemption policy.
            </div>
        </div>

        <div class="rev-opp-card" style="border-color:rgba(167,139,250,0.15);">
            <span class="roc-rank">#8</span>
            <div class="roc-icon">💎</div>
            <div class="roc-title">Founder Membership (Lifetime)</div>
            <div class="roc-desc">Sell a capped lifetime membership — e.g. 100 "Founder" slots at ₱5,000 each. Perks: permanent avatar frame, monthly HC stipend (e.g. 500 HC/mo), voting rights on new features, priority support. Scarcity (100 cap) drives urgency. Zero ongoing tech cost — just a flag on the account row.</div>
            <div class="roc-potential" style="background:rgba(167,139,250,0.1);color:#a78bfa;">₱500,000 one-time · Zero dilution</div>
            <div class="roc-data">
                Add <code>founder_tier</code> column to accounts; cron-style monthly HC drop (reuses hc_push). Market as "first 100 only" to drive FOMO.
            </div>
        </div>

        <div class="rev-opp-card" style="border-color:rgba(96,165,250,0.15);">
            <span class="roc-rank">#9</span>
            <div class="roc-icon">📈</div>
            <div class="roc-title">Revenue-Based Financing</div>
            <div class="roc-desc">Once you have ~3 months of recurring revenue, apply to Philippine RBF lenders (e.g. First Circle, Validus, Funding Societies). They loan ₱100k–₱2M against future revenue — repaid as 5–15% of monthly sales until capped. Zero equity dilution.</div>
            <div class="roc-potential" style="background:rgba(96,165,250,0.1);color:#60a5fa;">₱100k–₱2M · No equity</div>
            <div class="roc-data">
                Requires: ≥3 months of payment data (you have PayRex + h_coin_orders), SEC/DTI registration, bank statements. Approval in 2–4 weeks.
            </div>
        </div>

        <div class="rev-opp-card" style="border-color:rgba(248,113,113,0.15);">
            <span class="roc-rank">#10</span>
            <div class="roc-icon">🎟️</div>
            <div class="roc-title">Season Pass Pre-sale</div>
            <div class="roc-desc">Sell a bundled "Season Pass" for ₱999 before a tournament season launches: includes entry to 4 tournaments, 2,000 HC, exclusive avatar, and early access. Collects cash upfront to fund the season's prizes + marketing before the first match.</div>
            <div class="roc-potential" style="background:rgba(248,113,113,0.1);color:#f87171;">₱100k–₱300k per season · Upfront cash</div>
            <div class="roc-data">
                100 passes × ₱999 = ₱99,900 · 300 passes × ₱999 = ₱299,700. Launch 30 days before season start. Track via <code>season_passes</code> table or flag on accounts.
            </div>
        </div>

        <div class="rev-opp-card" style="border-color:rgba(96,165,250,0.2);">
            <span class="roc-rank">#11</span>
            <div class="roc-icon">🇵🇭</div>
            <div class="roc-title">Philippine VC Seed Round</div>
            <div class="roc-desc">Once you have 6+ months of traction, pitch Philippine-active seed VCs. Comparable raises: AcadArena $3.5M (Dec 2024, led by Iterative Capital + Kevin Lin), Mineski Global $10.6M total. Typical check ₱14M–₱56M for 15–25% equity. Partners network = sponsor intros + hiring pipeline.</div>
            <div class="roc-potential" style="background:rgba(96,165,250,0.12);color:#60a5fa;">₱14M–₱56M · 15–25% equity</div>
            <div class="roc-data">
                Targets: <strong>Kickstart Ventures</strong> (Globe Telecom's VC), <strong>Foxmont Capital</strong>, <strong>Rebel Fund</strong>, <strong>Iterative Capital</strong>. Prep: 15-slide deck, 18-month financial model, traction dashboard, data room. Expect 3–6 months from first meeting to term sheet.
            </div>
        </div>

        <div class="rev-opp-card" style="border-color:rgba(251,191,36,0.2);">
            <span class="roc-rank">#12</span>
            <div class="roc-icon">🎮</div>
            <div class="roc-title">Publisher / Game-Studio Partnership</div>
            <div class="roc-desc">Approach the game publishers directly for official tournament partnerships. Publishers routinely fund prize pools (₱50k–₱500k per event) in exchange for branding + data + retention metrics. Common path for regional tournament organizers in PH.</div>
            <div class="roc-potential" style="background:rgba(251,191,36,0.12);color:#fbbf24;">₱50k–₱500k per event · Event-linked</div>
            <div class="roc-data">
                Targets: <strong>Valve</strong> (Dota 2) via Perfect World SEA, <strong>Moonton</strong> (Mobile Legends PH), <strong>Riot Games</strong> (Valorant PH), <strong>Garena</strong> (Free Fire). Pitch: tournament proposal + audience demographics + media kit. Often includes official sanctioning, publisher-approved cosmetics, and marketing amplification.
            </div>
        </div>

        <div class="rev-opp-card" style="border-color:rgba(52,211,153,0.2);">
            <span class="roc-rank">#13</span>
            <div class="roc-icon">🏦</div>
            <div class="roc-title">Venture Debt / Equipment Financing</div>
            <div class="roc-desc">Asset-backed loan against production equipment (cameras, servers, casting rigs) — industry-standard split is debt for CAPEX, equity for working capital. Doesn't dilute the cap table. Typical tenor 24–36 months, rate 10–18% p.a.</div>
            <div class="roc-potential" style="background:rgba(52,211,153,0.12);color:#34d399;">₱200k–₱1M · No equity</div>
            <div class="roc-data">
                Lenders: <strong>BDO Network Bank</strong>, <strong>RCBC SME</strong>, <strong>Security Bank SME</strong> equipment-finance products. Requires: BIR/SEC registration, 6-mo bank statements, invoice for the asset. Best after a DOST grant or VC raise unlocks reserves.
            </div>
        </div>

        <div class="rev-opp-card" style="border-color:rgba(167,139,250,0.2);">
            <span class="roc-rank">#14</span>
            <div class="roc-icon">📺</div>
            <div class="roc-title">Media Rights / Streaming Deal</div>
            <div class="roc-desc">Sell streaming rights for flagship tournaments to Philippine broadcasters and creator platforms. Media rights are 17% of total esports revenue worldwide — and local-language content is underserved in PH.</div>
            <div class="roc-potential" style="background:rgba(167,139,250,0.12);color:#a78bfa;">₱50k–₱300k per season · Recurring if renewed</div>
            <div class="roc-data">
                Candidates: <strong>Kumu</strong>, <strong>TV5 Esports</strong>, <strong>GTV / One Sports</strong>, <strong>Cignal Play</strong>. Package: exclusive-window stream + VOD library + co-marketing. Often complements #12 publisher deals.
            </div>
        </div>

        <div class="rev-opp-card" style="border-color:rgba(248,113,113,0.2);">
            <span class="roc-rank">#15</span>
            <div class="roc-icon">🧡</div>
            <div class="roc-title">Community Patronage (Patreon-style)</div>
            <div class="roc-desc">Creator-economy model: a pure "support Apex Cybernet" tier separate from the Pro Pass. Superfans pay monthly for Discord-only content, behind-the-scenes tournament planning, direct chat with organizers, and voting power on feature priorities. Recurring revenue now sits at the center of creator business models (creator economy hit ~$280B in 2026).</div>
            <div class="roc-potential" style="background:rgba(248,113,113,0.12);color:#f87171;">₱99–₱499/mo · 50–200 patrons typical</div>
            <div class="roc-data">
                Platform: <strong>Patreon</strong> (global, 5–12% fee) or build in-house via subscriptions table. 100 patrons × ₱199 avg = ₱19,900/mo. Works even at small scale because superfans self-select.
            </div>
        </div>

    </div>

    <!-- ══ Decision Log — Re-framed around the café leverage pattern ══ -->
    <div style="margin-top:1.75rem;">
        <div style="font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:#6b7280;margin-bottom:0.75rem;">
            <i class="bi bi-journal-text" style="color:#38bdf8;margin-right:4px;"></i>Decision Log — Leverage-First Capital Strategy
        </div>
        <div style="font-size:0.78rem;color:#9ca3af;margin-bottom:1rem;line-height:1.55;">
            Banks and personal networks refused conventional lending. You responded by inventing a better pattern: the
            <strong style="color:#e5e7eb;">café deal</strong> — ₱100k interest-free loan to an internet café in exchange for perpetual
            tournament hosting rights. The loan returns as cash (commodity); the right stays forever (asset).
            Every strategy below is measured against that template: <strong style="color:#e5e7eb;">does it extract a perpetual right
            or just move cash around?</strong> Textbook ranking doesn't apply — leverage extraction does.
        </div>

        <!-- Prerequisite: codify rights first -->
        <div style="background:rgba(251,191,36,0.06);border:1px solid rgba(251,191,36,0.3);border-radius:12px;padding:0.9rem 1.1rem;margin-bottom:0.85rem;">
            <div style="font-size:0.8rem;font-weight:800;color:#fde68a;margin-bottom:0.35rem;">
                <i class="bi bi-shield-lock-fill" style="margin-right:4px;"></i>Prerequisite · Codify the café rights first
            </div>
            <div style="font-size:0.74rem;color:#9ca3af;line-height:1.6;">
                The arrangement is currently informal. That makes it worth ~0 to an investor, acquirer, or estate executor.
                A 2-page Memorandum of Agreement (MOA) with: <em>(1)</em> "perpetual" defined with a clean term + automatic renewal,
                <em>(2)</em> specific performance — the café is obligated to host Apex Cybernet events, <em>(3)</em> transfer clause — the
                right survives a sale of the café. <strong style="color:#e5e7eb;">~₱5–10k legal fee · 1–2 weeks.</strong>
                Do this <em>before</em> replicating the pattern at other venues — otherwise you keep building on sand.
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr;gap:0.85rem;">

            <!-- Phase 1 -->
            <div style="background:rgba(52,211,153,0.05);border:1px solid rgba(52,211,153,0.2);border-radius:12px;padding:1rem 1.15rem;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.5rem;">
                    <div style="font-size:0.88rem;font-weight:800;color:#86efac;">Phase 1 · Replicate the Café Pattern · <span style="color:#9ca3af;font-weight:600;">Month 0–4</span></div>
                    <div style="font-size:0.7rem;font-weight:700;color:#34d399;background:rgba(52,211,153,0.15);border:1px solid rgba(52,211,153,0.3);border-radius:6px;padding:2px 8px;">Goal: 3 venues + cash runway</div>
                </div>
                <div style="font-size:0.76rem;color:#9ca3af;line-height:1.6;">
                    You already proved the pattern works. The next move isn't a different strategy — it's the same one, deployed 3x.
                    <ul style="margin:0.5rem 0 0;padding-left:1.1rem;">
                        <li><strong style="color:#e5e7eb;">Venue portfolio (replicate the café deal)</strong> — identify 2–3 more cafés/venues with cash-flow gaps. Offer interest-free ₱50–100k loans in exchange for perpetual hosting rights. A 3-venue portfolio is no longer "a guy with a café" — it's a <em>regional tournament network</em>. This is your real moat.</li>
                        <li><strong style="color:#e5e7eb;">#10 Season Pass Pre-sale</strong> — upfront cash funds the hosting loans. Landing page + PayRex in a week.</li>
                        <li><strong style="color:#e5e7eb;">#1 Prize Pool Crowdfund</strong> at each venue's flagship tournament. Uses the hosting rights you now own. 10–15% organizer take.</li>
                        <li><strong style="color:#e5e7eb;">#4 Sponsorships (Bronze ₱50k)</strong> — 2–3 cafés / peripherals / energy brands. Your venue portfolio is the pitch asset.</li>
                    </ul>
                </div>
            </div>

            <!-- Phase 2 -->
            <div style="background:rgba(96,165,250,0.05);border:1px solid rgba(96,165,250,0.2);border-radius:12px;padding:1rem 1.15rem;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.5rem;">
                    <div style="font-size:0.88rem;font-weight:800;color:#93c5fd;">Phase 2 · Monetize the Rights Portfolio · <span style="color:#9ca3af;font-weight:600;">Month 4–10</span></div>
                    <div style="font-size:0.7rem;font-weight:700;color:#60a5fa;background:rgba(96,165,250,0.15);border:1px solid rgba(96,165,250,0.3);border-radius:6px;padding:2px 8px;">Target: ₱1M–₱8M</div>
                </div>
                <div style="font-size:0.76rem;color:#9ca3af;line-height:1.6;">
                    With multiple venues, real participants, and a demonstrated loss-leader flywheel, your leverage goes up. Every strategy here extracts a right from a bigger counterparty.
                    <ul style="margin:0.5rem 0 0;padding-left:1.1rem;">
                        <li><strong style="color:#e5e7eb;">#12 Publisher Tournament Partnership</strong> — same café pattern, bigger counterparty. Moonton / Garena / Riot fund the prize pool, you give branding. You keep the audience data + tournament format. Apply to flagship events per venue.</li>
                        <li><strong style="color:#e5e7eb;">#14 Media Rights Deal</strong> — your native language. Kumu / TV5 / GTV pay for exclusive streaming rights; you keep VOD library rights. Compounds with #12.</li>
                        <li><strong style="color:#e5e7eb;">#6 DOST-PCIEERD SGF grant</strong> — non-dilutive, up to ₱5M, 18-month R&amp;D project. No right-extraction but no dilution either — pure cash injection. Angle: matchmaking or fraud-detection ML.</li>
                        <li><strong style="color:#e5e7eb;">#3 Kickstarter / Spark Project</strong> — use Phase 1 venue portfolio as credibility. One-shot cash; no perpetual right extracted — treat as a marketing campaign that also raises money.</li>
                    </ul>
                </div>
            </div>

            <!-- Phase 3 -->
            <div style="background:rgba(167,139,250,0.05);border:1px solid rgba(167,139,250,0.2);border-radius:12px;padding:1rem 1.15rem;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.5rem;">
                    <div style="font-size:0.88rem;font-weight:800;color:#c4b5fd;">Phase 3 · Strategic Partners Only · <span style="color:#9ca3af;font-weight:600;">Month 10–18</span></div>
                    <div style="font-size:0.7rem;font-weight:700;color:#a78bfa;background:rgba(167,139,250,0.15);border:1px solid rgba(167,139,250,0.3);border-radius:6px;padding:2px 8px;">Only if they bring rights you can't buy</div>
                </div>
                <div style="font-size:0.76rem;color:#9ca3af;line-height:1.6;">
                    VC money is the opposite of your pattern — you sell a right (equity) instead of extracting one. Take it only if the partner brings something you can't buy with cash.
                    <ul style="margin:0.5rem 0 0;padding-left:1.1rem;">
                        <li><strong style="color:#e5e7eb;">#11 Philippine VC — strategic only</strong> — Kickstart Ventures (Globe Telecom relationships), Foxmont (regional scale), Iterative Capital (gaming network). Take their check because of publisher intros, telco partnerships, operator network — not for the cash. If their pitch is just "here's money," decline and go back to Phase 2.</li>
                        <li><strong style="color:#e5e7eb;">Acquirer conversations</strong> — at this stage your venue portfolio + publisher deals + platform are attractive to a regional esports org looking to acquire. Entertain them; use offers as VC-term-sheet leverage.</li>
                    </ul>
                </div>
            </div>

            <!-- Avoid -->
            <div style="background:rgba(248,113,113,0.04);border:1px solid rgba(248,113,113,0.2);border-radius:12px;padding:1rem 1.15rem;">
                <div style="font-size:0.88rem;font-weight:800;color:#fca5a5;margin-bottom:0.5rem;">
                    <i class="bi bi-x-circle-fill" style="margin-right:4px;"></i>Skip — breaks your pattern
                </div>
                <div style="font-size:0.76rem;color:#9ca3af;line-height:1.6;">
                    <ul style="margin:0;padding-left:1.1rem;">
                        <li><strong style="color:#e5e7eb;">#9 Revenue-Based Financing</strong> — RBF lenders are bank-adjacent. Same category that already rejected you. Puts you back in the commodity-borrower role you successfully escaped. <strong>Skip.</strong></li>
                        <li><strong style="color:#e5e7eb;">#13 Venture Debt / Equipment Financing</strong> — same category. Also: equipment-backed debt means you lose the equipment if you default. No thanks. <strong>Skip.</strong></li>
                        <li><strong style="color:#e5e7eb;">#5 Angel Syndicate</strong> — 10–20 small checks = 10–20 monthly-update emails. Fragmented dilution, no strategic leverage from any single investor. If you must take angels, take one lead with value-add. <strong>Skip by default.</strong></li>
                        <li><strong style="color:#e5e7eb;">#2 HCoin Founder's Pre-sale (as structured)</strong> — sells a right (discounted HC) rather than extracting one. Creates two-tier pricing resentment with regular buyers. Merge into #8 instead.</li>
                        <li><strong style="color:#e5e7eb;">#7 HCoin Staking Lock-up</strong> — technical complexity, regulatory gray zone around "yield", no right extracted. Low priority.</li>
                    </ul>
                </div>
            </div>

            <!-- Fits your style — repositioned -->
            <div style="background:rgba(251,191,36,0.04);border:1px solid rgba(251,191,36,0.25);border-radius:12px;padding:1rem 1.15rem;">
                <div style="font-size:0.88rem;font-weight:800;color:#fde68a;margin-bottom:0.5rem;">
                    <i class="bi bi-star-fill" style="margin-right:4px;"></i>Fits your pattern — deploy alongside any phase
                </div>
                <div style="font-size:0.76rem;color:#9ca3af;line-height:1.6;">
                    <ul style="margin:0;padding-left:1.1rem;">
                        <li><strong style="color:#e5e7eb;">#8 Founder Membership (lifetime)</strong> — extracts commitment + cash from superfans in exchange for a permanent identity/role. Rights-shaped. Cap at 100 slots to make it scarce. Launch during Phase 1.</li>
                        <li><strong style="color:#e5e7eb;">#15 Community Patronage</strong> — relational recurring revenue. Small cash but builds the "community depth" narrative VCs care about in Phase 3.</li>
                    </ul>
                </div>
            </div>

        </div>

        <!-- Key insight — rewritten -->
        <div style="margin-top:1rem;font-size:0.76rem;color:#cbd5e1;background:rgba(56,189,248,0.05);border:1px solid rgba(56,189,248,0.2);border-radius:10px;padding:0.9rem 1.05rem;line-height:1.6;">
            <i class="bi bi-lightbulb-fill" style="color:#38bdf8;margin-right:4px;"></i>
            <strong style="color:#e5e7eb;">The meta-insight:</strong>
            The café deal isn't a one-time trick — it's a <strong style="color:#e5e7eb;">repeatable pattern</strong>. Every cash-strapped internet café, co-working
            space, or barangay LAN centre in Cebu/Manila is a candidate for the same trade: "I'll loan you interest-free cash
            over 4 months; in return, my brand hosts tournaments at your venue in perpetuity." Run this 3–5 times and you're no longer
            "a guy with a café" — you're a regional <strong style="color:#e5e7eb;">venue network</strong>, which is literally what a tournament-organizer business
            is valued on at acquisition.
            <br><br>
            Your edge over a textbook founder: you don't pay retail for things you can get with leverage. Keep doing that. The only
            thing to guard against is <em>informal rights</em> — bake a 2-page MOA into every venue deal from day one and the pattern
            compounds for free.
        </div>
    </div>

    </div><!-- /.palantir-body -->
</div><!-- /#pal-revenue -->

<!-- ══ UTM Campaign Links ══ -->
<div class="palantir-section" id="pal-utm">
    <div class="palantir-header" onclick="palToggle('pal-utm')">
        <i class="bi bi-link-45deg pal-icon" style="color:#38bdf8;"></i>
        <span>Campaign Links</span>
        <span class="pal-badge" style="background:rgba(56,189,248,0.15);color:#38bdf8;border-color:rgba(56,189,248,0.25);"><?= count($utm_links) ?> links ready</span>
        <i class="bi bi-chevron-down pal-toggle"></i>
    </div>
    <div class="palantir-body">
        <p style="font-size:0.78rem;color:#9ca3af;margin-bottom:1.1rem;">
            These links are pre-built for your businesses. Copy one, paste it as the destination URL in Facebook/Instagram Ads Manager. Omniscient will automatically track which ad drove the traffic.
        </p>

        <?php
        $by_biz = [];
        foreach ($utm_links as $lnk) $by_biz[$lnk['business']][] = $lnk;
        $biz_labels = ['ocpd'=>'OCPD Paragliding','apexcybernet'=>'Apex Cybernet Tournament','loan'=>'Loan','other'=>'Other'];
        $biz_colors = ['ocpd'=>'#38bdf8','apexcybernet'=>'#a78bfa','loan'=>'#c4b5fd','other'=>'#9ca3af'];
        $platform_icons = ['Facebook'=>'bi-facebook','Instagram'=>'bi-instagram','Email'=>'bi-envelope-fill','Twitter'=>'bi-twitter-x'];
        foreach ($by_biz as $biz => $links):
            $col = $biz_colors[$biz] ?? '#9ca3af';
        ?>
        <div style="margin-bottom:1.25rem;">
            <div style="font-size:0.68rem;font-weight:800;text-transform:uppercase;letter-spacing:0.08em;color:<?= $col ?>;margin-bottom:0.55rem;">
                <?= htmlspecialchars($biz_labels[$biz] ?? strtoupper($biz)) ?>
            </div>
            <div style="display:flex;flex-direction:column;gap:0.45rem;">
            <?php foreach ($links as $lnk):
                $icon = $platform_icons[$lnk['platform']] ?? 'bi-megaphone';
            ?>
            <div style="display:flex;align-items:center;gap:0.65rem;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07);border-radius:9px;padding:0.6rem 0.85rem;">
                <i class="bi <?= $icon ?>" style="color:<?= $col ?>;font-size:0.85rem;flex-shrink:0;"></i>
                <div style="flex:1;min-width:0;">
                    <div style="font-size:0.78rem;font-weight:700;color:#e5e7eb;"><?= htmlspecialchars($lnk['label']) ?></div>
                    <div style="font-size:0.65rem;color:#4b5563;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:1px;" title="<?= htmlspecialchars($lnk['url']) ?>"><?= htmlspecialchars($lnk['url']) ?></div>
                </div>
                <button onclick="copyLink(this,'<?= htmlspecialchars(addslashes($lnk['url'])) ?>')"
                    style="background:rgba(56,189,248,0.1);border:1px solid rgba(56,189,248,0.25);color:#38bdf8;border-radius:6px;padding:0.25rem 0.65rem;font-size:0.72rem;font-weight:700;cursor:pointer;flex-shrink:0;white-space:nowrap;">
                    <i class="bi bi-clipboard"></i> Copy
                </button>
                <a href="activity-bizops.php?del_utm=<?= $lnk['id'] ?>#pal-utm"
                    onclick="return confirm('Delete this link?')"
                    style="color:#4b5563;font-size:0.72rem;text-decoration:none;flex-shrink:0;" title="Delete">
                    <i class="bi bi-trash"></i>
                </a>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Add new link -->
        <div style="border-top:1px solid rgba(255,255,255,0.07);padding-top:1rem;margin-top:0.5rem;">
            <div style="font-size:0.68rem;color:#6b7280;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0.65rem;">Add a new link</div>
            <form method="POST" action="activity-bizops.php#pal-utm">
                <input type="hidden" name="action" value="add_utm">
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0.5rem;margin-bottom:0.5rem;">
                    <input name="label" required placeholder="Label (e.g. OCPD Reel Ad)" style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:7px;color:#e5e7eb;font-size:0.75rem;padding:0.35rem 0.6rem;outline:none;width:100%;">
                    <select name="business" style="background:#111;border:1px solid rgba(255,255,255,0.1);border-radius:7px;color:#e5e7eb;font-size:0.75rem;padding:0.35rem 0.6rem;outline:none;width:100%;">
                        <option value="ocpd">OCPD</option>
                        <option value="apexcybernet">Apex Cybernet</option>
                        <option value="loan">Loan</option>
                        <option value="other">Other</option>
                    </select>
                    <select name="platform" style="background:#111;border:1px solid rgba(255,255,255,0.1);border-radius:7px;color:#e5e7eb;font-size:0.75rem;padding:0.35rem 0.6rem;outline:none;width:100%;">
                        <option>Facebook</option>
                        <option>Instagram</option>
                        <option>Email</option>
                        <option>Twitter</option>
                        <option>TikTok</option>
                    </select>
                    <input name="base_url" required type="url" placeholder="Destination URL" style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:7px;color:#e5e7eb;font-size:0.75rem;padding:0.35rem 0.6rem;outline:none;width:100%;">
                    <input name="utm_source" required placeholder="source (facebook)" style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:7px;color:#e5e7eb;font-size:0.75rem;padding:0.35rem 0.6rem;outline:none;width:100%;">
                    <input name="utm_medium" required placeholder="medium (paid_social)" style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:7px;color:#e5e7eb;font-size:0.75rem;padding:0.35rem 0.6rem;outline:none;width:100%;">
                    <input name="utm_campaign" required placeholder="campaign name" style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:7px;color:#e5e7eb;font-size:0.75rem;padding:0.35rem 0.6rem;outline:none;width:100%;grid-column:span 2;">
                    <input name="utm_content" placeholder="content (optional, e.g. video-1)" style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:7px;color:#e5e7eb;font-size:0.75rem;padding:0.35rem 0.6rem;outline:none;width:100%;">
                </div>
                <button type="submit" style="background:rgba(56,189,248,0.15);border:1px solid rgba(56,189,248,0.3);color:#38bdf8;border-radius:7px;padding:0.35rem 0.9rem;font-size:0.75rem;font-weight:700;cursor:pointer;">
                    <i class="bi bi-plus-lg"></i> Add Link
                </button>
            </form>
        </div>
    </div>
</div>

<!-- ══ Reels Maker — Apex Cybernet.co ══ -->
<div class="palantir-section" id="pal-reels">
    <div class="palantir-header" onclick="palToggle(this)">
        <i class="bi bi-play-circle-fill pal-icon" style="color:#f472b6;"></i>
        <span>Reels Maker</span>
        <span class="pal-badge" style="background:rgba(244,114,182,0.15);color:#f472b6;border-color:rgba(244,114,182,0.25);">Apex Cybernet.co · IG &amp; FB Reels</span>
        <i class="bi bi-chevron-down pal-toggle"></i>
    </div>
    <div class="palantir-body">
    <style>
    .reel-snapshot { display:flex;align-items:center;gap:1rem;flex-wrap:wrap;background:rgba(244,114,182,0.05);border:1px solid rgba(244,114,182,0.15);border-radius:10px;padding:0.75rem 1rem;margin-bottom:1.25rem; }
    .reel-snap-item { text-align:center; }
    .reel-snap-val { font-size:1.15rem;font-weight:900;line-height:1.1; }
    .reel-snap-lbl { font-size:0.58rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:#6b7280;margin-top:2px; }
    .reel-divider { width:1px;height:32px;background:rgba(255,255,255,0.07);flex-shrink:0; }
    .reel-grid { display:grid;grid-template-columns:repeat(3,1fr);gap:1rem; }
    @media(max-width:900px){.reel-grid{grid-template-columns:1fr;}}
    .reel-card { background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.09);border-radius:14px;padding:1.1rem;display:flex;flex-direction:column;gap:0.6rem; }
    .reel-obj { font-size:0.58rem;font-weight:800;text-transform:uppercase;letter-spacing:0.1em;padding:0.18rem 0.55rem;border-radius:99px;display:inline-block;width:fit-content; }
    .reel-label { font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#6b7280;margin-bottom:0.2rem; }
    .reel-hook { font-size:1rem;font-weight:900;color:#f9fafb;line-height:1.3; }
    .reel-script { font-size:0.75rem;color:#d1d5db;line-height:1.6;white-space:pre-line;background:rgba(255,255,255,0.03);border-radius:8px;padding:0.65rem 0.75rem;border:1px solid rgba(255,255,255,0.06); }
    .reel-caption { font-size:0.73rem;color:#d1d5db;line-height:1.55;white-space:pre-line; }
    .reel-tags { font-size:0.68rem;color:#60a5fa;line-height:1.7; }
    .reel-cta-link { font-size:0.65rem;color:#4b5563;word-break:break-all;margin-top:-0.25rem; }
    .reel-actions { display:flex;gap:0.5rem;flex-wrap:wrap;margin-top:auto;padding-top:0.5rem;border-top:1px solid rgba(255,255,255,0.06); }
    .reel-copy-btn { background:rgba(244,114,182,0.1);border:1px solid rgba(244,114,182,0.25);color:#f472b6;border-radius:7px;padding:0.28rem 0.7rem;font-size:0.72rem;font-weight:700;cursor:pointer; }
    .reel-copy-btn:hover { background:rgba(244,114,182,0.18); }
    </style>

    <!-- Data snapshot -->
    <div class="reel-snapshot">
        <div class="reel-snap-item">
            <div class="reel-snap-val" style="color:#f472b6;"><?= htmlspecialchars($reel_best_time) ?></div>
            <div class="reel-snap-lbl">Best Time to Post</div>
        </div>
        <div class="reel-divider"></div>
        <div class="reel-snap-item">
            <div class="reel-snap-val" style="color:#60a5fa;"><?= htmlspecialchars($reel_top_geo) ?></div>
            <div class="reel-snap-lbl">Top Audience Country</div>
        </div>
        <div class="reel-divider"></div>
        <div class="reel-snap-item">
            <div class="reel-snap-val" style="color:#34d399;"><?= number_format($rev_apexcybernet_sessions) ?></div>
            <div class="reel-snap-lbl">Sessions (30d)</div>
        </div>
        <div class="reel-divider"></div>
        <div class="reel-snap-item">
            <div class="reel-snap-val" style="color:#fbbf24;"><?= number_format($rev_teams_total + $rev_solo_total) ?></div>
            <div class="reel-snap-lbl">Registered Players</div>
        </div>
        <div class="reel-divider"></div>
        <div class="reel-snap-item">
            <div class="reel-snap-val" style="color:#a78bfa;"><?= number_format($rev_accounts_total) ?></div>
            <div class="reel-snap-lbl">Total Accounts</div>
        </div>
        <?php if ($reel_predict_pool > 0): ?>
        <div class="reel-divider"></div>
        <div class="reel-snap-item">
            <div class="reel-snap-val" style="color:#38bdf8;"><?= number_format($reel_predict_pool) ?> HC</div>
            <div class="reel-snap-lbl">Active Predict Pool</div>
        </div>
        <?php endif; ?>
    </div>

    <p style="font-size:0.76rem;color:#6b7280;margin-bottom:1.1rem;">
        Three ready-to-record Reel scripts generated from your Apex Cybernet analytics. Each includes a 3-second hook, on-screen text guide, caption, hashtags, and a UTM link to track conversions.
    </p>

    <div class="reel-grid">

        <!-- Reel 1: Awareness -->
        <div class="reel-card" style="border-color:rgba(244,114,182,0.2);">
            <span class="reel-obj" style="background:rgba(244,114,182,0.15);color:#f472b6;">🎬 Awareness · Cold Audience</span>

            <div>
                <div class="reel-label">Hook (First 3 Seconds)</div>
                <div class="reel-hook" id="reel1-hook">POV: You just found the biggest gaming tournament platform in <?= htmlspecialchars($reel_top_geo) ?> 🎮</div>
            </div>

            <div>
                <div class="reel-label">On-Screen Script</div>
                <div class="reel-script" id="reel1-script">0:00 — Hook text on screen: "Ever wanted to get PAID to play games?"
0:03 — Show Apex Cybernet homepage / tournament bracket
0:05 — Text: "Apex Cybernet.co — Free to join"
0:07 — Show predict.php / H-Coins balance screen
0:09 — Text: "Predict match winners → Earn H-Coins"
0:12 — Text: "<?= number_format($rev_accounts_total) ?>+ players already in"
0:15 — CTA: "Join for free → apexcybernet.com"</div>
            </div>

            <div>
                <div class="reel-label">Caption</div>
                <div class="reel-caption" id="reel1-caption">POV: You just found the gaming platform that actually rewards you 🎮💰

Apex Cybernet is a free tournament platform where you can:
🏆 Enter esports tournaments
🔮 Predict match winners &amp; earn H-Coins
🛒 Trade in the marketplace
💸 Cash out your coins

<?= number_format($rev_accounts_total) ?>+ players already registered. Join for free 👇
<?= $reel_utm_awareness ?></div>
            </div>

            <div>
                <div class="reel-label">Hashtags</div>
                <div class="reel-tags" id="reel1-tags">#Apex Cybernet #EsportsPH #GamingTournament #FreeToPlay #MobileGaming #EsportsCommunity #GamingPH #OnlineTournament #GamingLife #HCoins</div>
            </div>

            <div class="reel-actions">
                <button class="reel-copy-btn" onclick="reelCopyAll('reel1-hook','reel1-script','reel1-caption','reel1-tags','<?= addslashes($reel_utm_awareness) ?>',this)">
                    <i class="bi bi-clipboard"></i> Copy Full Script
                </button>
                <button class="reel-copy-btn" onclick="reelCopyCaption('reel1-caption','reel1-tags','<?= addslashes($reel_utm_awareness) ?>',this)">
                    <i class="bi bi-chat-text"></i> Copy Caption
                </button>
            </div>
        </div>

        <!-- Reel 2: Engagement — Predict & Earn -->
        <div class="reel-card" style="border-color:rgba(251,191,36,0.2);">
            <span class="reel-obj" style="background:rgba(251,191,36,0.12);color:#fbbf24;">🔮 Engagement · H-Coins Angle</span>

            <div>
                <div class="reel-label">Hook (First 3 Seconds)</div>
                <div class="reel-hook" id="reel2-hook">I predicted a Dota 2 match winner and earned H-Coins 🤑</div>
            </div>

            <div>
                <div class="reel-label">On-Screen Script</div>
                <div class="reel-script" id="reel2-script">0:00 — Hook: "I just won coins for predicting a Dota 2 match 🤑"
0:03 — Screen record: predict.php showing live match + pool
<?php if ($reel_predict_pool > 0): ?>0:05 — Highlight: "<?= number_format($reel_predict_pool) ?> HC in the pool right now"
<?php else: ?>0:05 — Show the two-team pick UI + wager input
<?php endif; ?>0:07 — Text: "Pick a side. Stake H-Coins."
0:10 — Text: "Winners split the entire pool 🏆"
0:13 — Text: "Use coins to enter tournaments or cash out"
0:16 — CTA: "Predict now → apexcybernet.com"</div>
            </div>

            <div>
                <div class="reel-label">Caption</div>
                <div class="reel-caption" id="reel2-caption">This Apex Cybernet feature is actually insane 🤯

"Predict" lets you stake H-Coins on esports match outcomes. If your team wins — you split the pool with everyone else who picked correctly 💰

✅ Free to join
🎯 Real esports matches (Dota 2, ML, Valorant)
💸 H-Coins = real value (₱1 each)
<?php if ($reel_predict_pool > 0): ?>🔥 <?= number_format($reel_predict_pool) ?> HC in the live pool right now

<?php else: ?>

<?php endif; ?>Try it → <?= $reel_utm_engagement ?></div>
            </div>

            <div>
                <div class="reel-label">Hashtags</div>
                <div class="reel-tags" id="reel2-tags">#ApexCybernetPredict #EsportsBetting #Dota2PH #MLBBPredictions #GamingPH #EarnOnline #HCoins #EsportsPH #PredictAndWin #MobileLegendsph</div>
            </div>

            <div class="reel-actions">
                <button class="reel-copy-btn" onclick="reelCopyAll('reel2-hook','reel2-script','reel2-caption','reel2-tags','<?= addslashes($reel_utm_engagement) ?>',this)">
                    <i class="bi bi-clipboard"></i> Copy Full Script
                </button>
                <button class="reel-copy-btn" onclick="reelCopyCaption('reel2-caption','reel2-tags','<?= addslashes($reel_utm_engagement) ?>',this)">
                    <i class="bi bi-chat-text"></i> Copy Caption
                </button>
            </div>
        </div>

        <!-- Reel 3: Conversion — Tournament Registration -->
        <div class="reel-card" style="border-color:rgba(52,211,153,0.2);">
            <span class="reel-obj" style="background:rgba(52,211,153,0.12);color:#34d399;">✅ Conversion · Join Now</span>

            <div>
                <div class="reel-label">Hook (First 3 Seconds)</div>
                <div class="reel-hook" id="reel3-hook">Registration is OPEN — prize pool TBD 🏆</div>
            </div>

            <div>
                <div class="reel-label">On-Screen Script</div>
                <div class="reel-script" id="reel3-script">0:00 — Hook text: "Tournament registration is OPEN 🚨"
0:02 — Show register.php / tournament bracket visual
0:04 — Text: "Prize pool up for grabs 🏆"
0:06 — Text: "<?= number_format($rev_teams_total) ?> teams registered so far"
0:08 — Show team registration form filling out fast
0:10 — Text: "Double elimination bracket"
0:12 — Text: "₱500 entry · instant confirmation"
0:14 — Urgency: "Slots are filling fast ⚡"
0:16 — CTA: "Register now → apexcybernet.com"</div>
            </div>

            <div>
                <div class="reel-label">Caption</div>
                <div class="reel-caption" id="reel3-caption">Tournament registration is NOW OPEN 🚨🏆

Here's what you get:
🎮 Double elimination bracket
👥 5-player team format
💰 Cash prize TBD OR free paragliding experience
📍 Hide Out Cybernet Cafe, Cebu City

<?= number_format($rev_teams_total) ?> teams already registered. Slots are limited — first come, first served.

Register your team 👇
<?= $reel_utm_conversion ?>

#ApexCybernetTournament #EsportsCebu #DotaTournament #MLBBTournament #GamingPH #EsportsPH #CebuGaming #TournamentPH</div>
            </div>

            <div>
                <div class="reel-label">Hashtags</div>
                <div class="reel-tags" id="reel3-tags">#ApexCybernetTournament #EsportsCebu #DotaTournament #MLBBTournament #GamingPH #EsportsPH #CebuGaming #TournamentPH #EsportsRegistration #CompetitiveGaming</div>
            </div>

            <div class="reel-actions">
                <button class="reel-copy-btn" onclick="reelCopyAll('reel3-hook','reel3-script','reel3-caption','reel3-tags','<?= addslashes($reel_utm_conversion) ?>',this)">
                    <i class="bi bi-clipboard"></i> Copy Full Script
                </button>
                <button class="reel-copy-btn" onclick="reelCopyCaption('reel3-caption','reel3-tags','<?= addslashes($reel_utm_conversion) ?>',this)">
                    <i class="bi bi-chat-text"></i> Copy Caption
                </button>
            </div>
        </div>

    </div><!-- /.reel-grid -->

    <!-- Format & Posting Tips -->
    <div style="margin-top:1.25rem;display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:0.75rem;">
        <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07);border-radius:10px;padding:0.85rem 1rem;">
            <div style="font-size:0.65rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:#6b7280;margin-bottom:0.5rem;"><i class="bi bi-camera-reels" style="color:#f472b6;"></i> Format</div>
            <div style="font-size:0.75rem;color:#9ca3af;line-height:1.7;">9:16 vertical · 15–30 seconds<br>Captions on screen (85% watch muted)<br>First frame = hook text overlay<br>No black bars — fill the screen</div>
        </div>
        <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07);border-radius:10px;padding:0.85rem 1rem;">
            <div style="font-size:0.65rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:#6b7280;margin-bottom:0.5rem;"><i class="bi bi-clock" style="color:#fbbf24;"></i> Best Time to Post</div>
            <div style="font-size:0.75rem;color:#9ca3af;line-height:1.7;">Based on your traffic: <strong style="color:#fbbf24;"><?= htmlspecialchars($reel_best_time) ?></strong><br>Post 3× per week minimum<br>Repost as a Story right after<br>Reply to all comments in first hour</div>
        </div>
        <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07);border-radius:10px;padding:0.85rem 1rem;">
            <div style="font-size:0.65rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:#6b7280;margin-bottom:0.5rem;"><i class="bi bi-graph-up" style="color:#34d399;"></i> Boost Tip</div>
            <div style="font-size:0.75rem;color:#9ca3af;line-height:1.7;">Let it run organic for 24h first.<br>If it hits 500+ views → boost it.<br>Target: <?= htmlspecialchars($reel_top_geo) ?> · 18–35<br>Gaming + Esports interests<br>₱100–₱200/day for 3 days</div>
        </div>
        <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07);border-radius:10px;padding:0.85rem 1rem;">
            <div style="font-size:0.65rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:#6b7280;margin-bottom:0.5rem;"><i class="bi bi-link-45deg" style="color:#60a5fa;"></i> Track Results</div>
            <div style="font-size:0.75rem;color:#9ca3af;line-height:1.7;">Each caption has a UTM link.<br>Omniscient tracks when those links<br>are clicked and which page they land on.<br>Check Campaign Links panel for data.</div>
        </div>
    </div>

    </div><!-- /.palantir-body -->
</div><!-- /#pal-reels -->

<!-- ══ Panel: Decision Log ══ -->
<div class="palantir-section" id="pal-decision-log">
    <div class="palantir-header" onclick="palToggle(this)">
        <i class="bi bi-journal-check pal-icon" style="color:#a78bfa;"></i>
        <span>Decision Log</span>
        <span class="pal-badge" id="dl-badge"><?= $dl_total ?> decisions</span>
        <i class="bi bi-chevron-down pal-toggle"></i>
    </div>
    <div class="palantir-body">

    <!-- Add / Edit Form -->
    <div id="dl-form-wrap" style="background:rgba(167,139,250,0.06);border:1px solid rgba(167,139,250,0.2);border-radius:12px;padding:1rem 1.1rem;margin-bottom:1.1rem;">
        <input type="hidden" id="dl-id" value="0">
        <div style="display:grid;grid-template-columns:160px 1fr;gap:0.6rem;margin-bottom:0.6rem;">
            <div>
                <label style="font-size:0.65rem;color:#6b7280;display:block;margin-bottom:0.25rem;">Date</label>
                <input type="date" id="dl-date" style="width:100%;background:#1a1a2e;border:1px solid rgba(255,255,255,0.1);border-radius:7px;color:#e5e7eb;padding:0.45rem 0.6rem;font-size:0.8rem;">
            </div>
            <div>
                <label style="font-size:0.65rem;color:#6b7280;display:block;margin-bottom:0.25rem;">Decision Title *</label>
                <input type="text" id="dl-title" placeholder="What did you decide?" style="width:100%;background:#1a1a2e;border:1px solid rgba(255,255,255,0.1);border-radius:7px;color:#e5e7eb;padding:0.45rem 0.6rem;font-size:0.8rem;">
            </div>
        </div>
        <div style="margin-bottom:0.75rem;">
            <label style="font-size:0.65rem;color:#6b7280;display:block;margin-bottom:0.25rem;">Context / Why</label>
            <textarea id="dl-context" rows="2" placeholder="Why did you make this decision? What was the situation?" style="width:100%;background:#1a1a2e;border:1px solid rgba(255,255,255,0.1);border-radius:7px;color:#e5e7eb;padding:0.45rem 0.6rem;font-size:0.78rem;resize:vertical;box-sizing:border-box;"></textarea>
        </div>
        <div style="display:flex;gap:0.6rem;align-items:center;">
            <button onclick="dlSave()" style="background:rgba(167,139,250,0.18);border:1px solid rgba(167,139,250,0.4);color:#a78bfa;border-radius:8px;padding:0.45rem 1rem;font-size:0.8rem;font-weight:600;cursor:pointer;">
                <i class="bi bi-save"></i> Save
            </button>
            <button onclick="dlCancelEdit()" id="dl-cancel-btn" style="display:none;background:transparent;border:1px solid rgba(255,255,255,0.1);color:#6b7280;border-radius:8px;padding:0.45rem 0.85rem;font-size:0.8rem;cursor:pointer;">Cancel</button>
            <span id="dl-save-msg" style="font-size:0.75rem;color:#34d399;display:none;"><i class="bi bi-check-circle"></i> Saved</span>
        </div>
    </div>

    <!-- Search -->
    <div style="display:flex;gap:0.5rem;align-items:center;margin-bottom:1rem;">
        <input type="text" id="dl-search" placeholder="Search decisions..." oninput="dlLoad()" style="background:#1a1a2e;border:1px solid rgba(255,255,255,0.1);border-radius:7px;color:#e5e7eb;padding:0.4rem 0.75rem;font-size:0.78rem;min-width:200px;">
        <span id="dl-count-label" style="font-size:0.72rem;color:#6b7280;margin-left:auto;"></span>
    </div>

    <!-- Timeline List -->
    <div id="dl-list" style="display:flex;flex-direction:column;gap:0.75rem;">
        <div style="text-align:center;padding:2rem;color:#4b5563;font-size:0.8rem;"><i class="bi bi-hourglass-split"></i> Loading...</div>
    </div>

    </div><!-- /.palantir-body -->
</div><!-- /#pal-decision-log -->

</div><!-- /.wrap -->
</div><!-- /.omni-main -->
</div><!-- /.omni-layout -->

<script>
function reelCopyAll(hookId, scriptId, captionId, tagsId, utmLink, btn) {
    const hook    = document.getElementById(hookId)?.textContent?.trim() || '';
    const script  = document.getElementById(scriptId)?.textContent?.trim() || '';
    const caption = document.getElementById(captionId)?.textContent?.trim() || '';
    const tags    = document.getElementById(tagsId)?.textContent?.trim() || '';
    const text = '=== HOOK ===\n' + hook + '\n\n=== ON-SCREEN SCRIPT ===\n' + script + '\n\n=== CAPTION ===\n' + caption + '\n\n' + tags + '\n' + utmLink;
    navigator.clipboard?.writeText(text).catch(() => { const t = document.createElement('textarea'); t.value = text; document.body.appendChild(t); t.select(); document.execCommand('copy'); document.body.removeChild(t); });
    const orig = btn.innerHTML; btn.innerHTML = '<i class="bi bi-check-lg"></i> Copied!'; btn.style.color = '#34d399'; btn.style.borderColor = 'rgba(52,211,153,0.4)';
    setTimeout(() => { btn.innerHTML = orig; btn.style.color=''; btn.style.borderColor=''; }, 2000);
}
function reelCopyCaption(captionId, tagsId, utmLink, btn) {
    const caption = document.getElementById(captionId)?.textContent?.trim() || '';
    const tags    = document.getElementById(tagsId)?.textContent?.trim() || '';
    const text = caption + '\n\n' + tags + '\n' + utmLink;
    navigator.clipboard?.writeText(text).catch(() => { const t = document.createElement('textarea'); t.value = text; document.body.appendChild(t); t.select(); document.execCommand('copy'); document.body.removeChild(t); });
    const orig = btn.innerHTML; btn.innerHTML = '<i class="bi bi-check-lg"></i> Copied!'; btn.style.color = '#34d399'; btn.style.borderColor = 'rgba(52,211,153,0.4)';
    setTimeout(() => { btn.innerHTML = orig; btn.style.color=''; btn.style.borderColor=''; }, 2000);
}
</script>

<script>
function copyLink(btn, url) {
    navigator.clipboard?.writeText(url).catch(() => {
        const t = document.createElement('textarea');
        t.value = url; document.body.appendChild(t); t.select();
        document.execCommand('copy'); document.body.removeChild(t);
    });
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-check-lg"></i> Copied!';
    btn.style.color = '#34d399';
    btn.style.borderColor = 'rgba(52,211,153,0.4)';
    setTimeout(() => { btn.innerHTML = orig; btn.style.color=''; btn.style.borderColor=''; }, 1800);
}
</script>

<script>
function palToggle(header) {
    const body = header.nextElementSibling;
    if (!body) return;
    header.classList.toggle('collapsed');
    body.classList.toggle('hidden');
}
</script>

<script>
// ── Decision Log ──
function dlLoad() {
    const q       = document.getElementById('dl-search')?.value || '';
    const outcome = document.getElementById('dl-filter-outcome')?.value || 'all';
    const biz     = document.getElementById('dl-filter-biz')?.value || 'all';
    const params  = new URLSearchParams({ ajax:'dl_list', q, outcome, biz });
    fetch('activity-bizops.php?' + params)
        .then(r => r.json())
        .then(rows => {
            const wrap = document.getElementById('dl-list');
            document.getElementById('dl-count-label').textContent = rows.length + ' decision' + (rows.length !== 1 ? 's' : '');
            document.getElementById('dl-badge').textContent = rows.length + ' decision' + (rows.length !== 1 ? 's' : '');
            if (!rows.length) {
                wrap.innerHTML = '<div style="text-align:center;padding:2.5rem;color:#4b5563;font-size:0.8rem;"><i class="bi bi-journal-x"></i> No decisions logged yet.<br><span style="font-size:0.72rem;opacity:0.7;">Use the form above to log your first decision.</span></div>';
                return;
            }
            wrap.innerHTML = rows.map(r => dlCard(r)).join('');
        })
        .catch(() => {
            document.getElementById('dl-list').innerHTML = '<div style="color:#f87171;font-size:0.78rem;padding:1rem;">Failed to load decisions.</div>';
        });
}

function dlCard(r) {
    const analysisHtml = r.impact_text ? `
        <div style="margin-top:0.65rem;padding-top:0.65rem;border-top:1px solid rgba(167,139,250,0.15);">
            <div style="font-size:0.63rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#a78bfa;margin-bottom:0.35rem;"><i class="bi bi-stars"></i> Analysis</div>
            <div style="font-size:0.76rem;color:#c4b5fd;white-space:pre-wrap;line-height:1.65;">${escHtml(r.impact_text)}</div>
        </div>` : '';

    return `<div id="dl-card-${r.id}" style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:10px;padding:0.85rem 1rem;">
        <div style="display:flex;align-items:flex-start;gap:0.75rem;">
            <div style="flex:1;min-width:0;">
                <div style="font-size:0.88rem;font-weight:700;color:#e5e7eb;margin-bottom:0.2rem;">${escHtml(r.title)}</div>
                <div style="font-size:0.68rem;color:#6b7280;margin-bottom:${r.context_text ? '0.5rem' : '0'};"><i class="bi bi-calendar3"></i> ${escHtml(r.decided_at)}</div>
                ${r.context_text ? `<div style="font-size:0.78rem;color:#9ca3af;white-space:pre-wrap;">${escHtml(r.context_text)}</div>` : ''}
            </div>
            <div style="display:flex;gap:0.4rem;flex-shrink:0;">
                <button onclick="dlEdit(${r.id})" style="background:transparent;border:1px solid rgba(255,255,255,0.1);color:#9ca3af;border-radius:6px;padding:0.3rem 0.55rem;font-size:0.72rem;cursor:pointer;"><i class="bi bi-pencil"></i></button>
                <button onclick="dlDelete(${r.id})" style="background:transparent;border:1px solid rgba(248,113,113,0.2);color:#f87171;border-radius:6px;padding:0.3rem 0.55rem;font-size:0.72rem;cursor:pointer;"><i class="bi bi-trash3"></i></button>
            </div>
        </div>
        ${analysisHtml}
    </div>`;
}

function dlSave() {
    const id    = parseInt(document.getElementById('dl-id').value) || 0;
    const title = document.getElementById('dl-title').value.trim();
    if (!title) { alert('Title is required.'); return; }
    const payload = {
        id,
        decided_at:   document.getElementById('dl-date').value || new Date().toISOString().slice(0,10),
        title,
        context_text: document.getElementById('dl-context').value.trim(),
    };
    fetch('activity-bizops.php?ajax=dl_save', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) })
        .then(r => r.json())
        .then(res => {
            if (!res.ok) { alert('Error: ' + (res.error || 'Unknown')); return; }
            const msg = document.getElementById('dl-save-msg');
            msg.style.display = 'inline';
            setTimeout(() => msg.style.display = 'none', 2000);
            dlCancelEdit();
            dlLoad();
        })
        .catch(() => alert('Save failed. Check connection.'));
}

function dlDelete(id) {
    if (!confirm('Delete this decision?')) return;
    fetch('activity-bizops.php?ajax=dl_delete', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({id}) })
        .then(r => r.json())
        .then(() => dlLoad())
        .catch(() => alert('Delete failed.'));
}

function dlEdit(id) {
    fetch('activity-bizops.php?ajax=dl_list&outcome=all&biz=all&q=')
        .then(r => r.json())
        .then(rows => {
            const r = rows.find(x => x.id == id);
            if (!r) return;
            document.getElementById('dl-id').value      = r.id;
            document.getElementById('dl-date').value    = r.decided_at;
            document.getElementById('dl-title').value   = r.title;
            document.getElementById('dl-context').value = r.context_text || '';
            document.getElementById('dl-cancel-btn').style.display = 'inline-block';
            document.getElementById('dl-form-wrap').scrollIntoView({ behavior:'smooth', block:'nearest' });
        });
}

function dlCancelEdit() {
    document.getElementById('dl-id').value      = '0';
    document.getElementById('dl-date').value    = new Date().toISOString().slice(0,10);
    document.getElementById('dl-title').value   = '';
    document.getElementById('dl-context').value = '';
    document.getElementById('dl-cancel-btn').style.display = 'none';
}

function escHtml(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Set today's date as default and load on page ready
document.addEventListener('DOMContentLoaded', () => {
    const d = document.getElementById('dl-date');
    if (d) d.value = new Date().toISOString().slice(0,10);
    dlLoad();
});
</script>
</body>
</html>
