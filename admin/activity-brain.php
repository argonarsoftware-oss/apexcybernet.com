<?php
/**
 * activity-brain.php — Decision Brain (notebook-style)
 *
 * Free-form note-taking. Each entry is a journal line: title + body.
 * Still powers the mental-model lenses + AI prompt builder, but capture
 * feels like writing in a paper notebook rather than filling a form.
 */
$active_site = 'brain';
$page_file   = 'activity-brain.php';
require_once __DIR__ . '/../includes/db.php';
$argonar_pdo = $pdo;
require_once __DIR__ . '/omni/auth.php';

// ── Ensure table ──
try {
    $argonar_pdo->exec("CREATE TABLE IF NOT EXISTS decision_log (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        decided_at   DATETIME,
        title        VARCHAR(255) NOT NULL,
        context_text TEXT,
        action_taken TEXT,
        result_text  TEXT,
        impact_text  TEXT,
        outcome      VARCHAR(32) DEFAULT '',
        tags         VARCHAR(255) DEFAULT '',
        business     VARCHAR(60) DEFAULT 'general',
        created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

// ── AJAX: save note ──
if (isset($_GET['ajax']) && $_GET['ajax'] === 'save') {
    header('Content-Type: application/json');
    $b = json_decode(file_get_contents('php://input'), true) ?: [];
    $id = (int)($b['id'] ?? 0);
    $title = trim($b['title'] ?? '');
    $body  = trim($b['body']  ?? '');
    if (!$title && !$body) { echo json_encode(['ok'=>false,'error'=>'Write something']); exit; }
    if (!$title) $title = mb_substr(preg_replace('/\s+/', ' ', $body), 0, 80);
    $biz = substr(trim($b['business'] ?? 'general'), 0, 60);
    $tags = substr(trim($b['tags'] ?? ''), 0, 255);
    // Normalize outcome to match ENUM('pending','positive','negative','neutral','mixed')
    $raw = strtolower(trim($b['outcome'] ?? ''));
    $out_map = [
        ''         => 'pending',
        'good'     => 'positive', 'worked'  => 'positive', 'win'   => 'positive', 'success' => 'positive', 'positive' => 'positive',
        'bad'      => 'negative', 'failed'  => 'negative', 'loss'  => 'negative', 'lost'    => 'negative', 'negative' => 'negative', "didn't"  => 'negative',
        'mixed'    => 'mixed',    'neutral' => 'neutral',  'pending'=> 'pending',
    ];
    $out = $out_map[$raw] ?? 'pending';
    try {
        if ($id > 0) {
            $argonar_pdo->prepare("UPDATE decision_log SET title=?, context_text=?, tags=?, business=?, outcome=?, decided_at=IFNULL(decided_at, NOW()), updated_at=NOW() WHERE id=?")
                ->execute([$title,$body,$tags,$biz,$out,$id]);
        } else {
            $argonar_pdo->prepare("INSERT INTO decision_log (decided_at, title, context_text, tags, business, outcome) VALUES (NOW(),?,?,?,?,?)")
                ->execute([$title,$body,$tags,$biz,$out]);
            $id = (int)$argonar_pdo->lastInsertId();
        }
        echo json_encode(['ok'=>true, 'id'=>$id]);
    } catch (Exception $e) { echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); }
    exit;
}

// ── AJAX: save Claude/AI analysis to impact_text ──
if (isset($_GET['ajax']) && $_GET['ajax'] === 'analysis') {
    header('Content-Type: application/json');
    $b = json_decode(file_get_contents('php://input'), true) ?: [];
    $id = (int)($b['id'] ?? 0);
    $text = trim($b['analysis'] ?? '');
    if (!$id) { echo json_encode(['ok'=>false,'error'=>'id required']); exit; }
    try {
        $argonar_pdo->prepare("UPDATE decision_log SET impact_text=?, updated_at=NOW() WHERE id=?")
            ->execute([$text, $id]);
        echo json_encode(['ok'=>true]);
    } catch (Exception $e) { echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); }
    exit;
}

// ── AJAX: delete ──
if (isset($_GET['ajax']) && $_GET['ajax'] === 'delete') {
    header('Content-Type: application/json');
    $id = (int)($_GET['id'] ?? 0);
    if ($id) { try { $argonar_pdo->prepare("DELETE FROM decision_log WHERE id=?")->execute([$id]); } catch (Exception $e) {} }
    echo json_encode(['ok'=>true]);
    exit;
}

// ── AJAX: generate AI prompt — motion-expansion frame ──
if (isset($_GET['ajax']) && $_GET['ajax'] === 'prompt') {
    header('Content-Type: application/json');
    $id = (int)($_GET['id'] ?? 0);
    $dec = null;
    if ($id) {
        $st = $argonar_pdo->prepare("SELECT * FROM decision_log WHERE id=?");
        $st->execute([$id]);
        $dec = $st->fetch();
    }
    // Full trajectory — every prior note as context so the AI sees the arc
    $trajectory = [];
    try {
        $all = $argonar_pdo->query("SELECT id, title, context_text, decided_at FROM decision_log ORDER BY COALESCE(decided_at, created_at) ASC, id ASC LIMIT 100")->fetchAll();
        foreach ($all as $n) {
            $tid = 'BR-' . str_pad($n['id'], 4, '0', STR_PAD_LEFT);
            $trajectory[] = "[$tid · " . ($n['decided_at'] ?? '') . "] " . $n['title'] . "\n" . trim($n['context_text'] ?? '');
        }
    } catch (Exception $e) {}
    // Live empire signals
    $empire = [];
    try {
        $r = $argonar_pdo->query("SELECT COUNT(*) c FROM accounts WHERE claim_status='approved'")->fetch();
        $empire[] = "Argonar approved accounts: " . (int)$r['c'];
    } catch (Exception $e) {}
    try {
        $r = $argonar_pdo->query("SELECT COUNT(*) c FROM teams")->fetch();
        $empire[] = "Registered teams: " . (int)$r['c'];
    } catch (Exception $e) {}

    $lines = [];
    $lines[] = "You are advising a founder operating 3 businesses (Argonar tournament platform, OCPD paragliding, Loan PH) who is building toward trillion-scale control.";
    $lines[] = "";
    $lines[] = "=== OPERATING RULES — READ FIRST ===";
    $lines[] = "1. DO NOT pull the founder toward the mean. Do not suggest 'start smaller,' 'stress-test,' 'slow down,' 'watch out for overreach,' or 'maybe try a pilot first.' Grandiosity and directional delusion are LOAD-BEARING here — they are the fuel, not a bug. Every legendary operator (Bezos, Musk, Jobs, Rockefeller) was rated 'delusional' during the build phase. The consensus label is the entry fee.";
    $lines[] = "2. Match the altitude. If the note mentions a trillion, respond at trillion scale. Do not translate ambition downward. Calibration is a brake designed for survival problems that do not exist in this attempt space — asymmetric payoffs are BROKEN by calibration.";
    $lines[] = "3. Power the motion. The purpose of this prompt is to EXPAND the move and generate executable next actions, not to analyze whether the move is wise.";
    $lines[] = "4. No caution, no caveats, no 'however,' no 'that said.' Tactical precision on mechanics (math, structure, sequencing) is welcome. Vision-layer caution is forbidden.";
    $lines[] = "";
    $lines[] = "=== THE NOTE ===";
    if ($dec) {
        $lines[] = "Track ID: BR-" . str_pad($dec['id'], 4, '0', STR_PAD_LEFT);
        $lines[] = "Title: " . ($dec['title'] ?? '');
        if (!empty($dec['context_text'])) $lines[] = "Body:\n" . $dec['context_text'];
    } else {
        $lines[] = "[paste your note here]";
    }
    $lines[] = "";
    $lines[] = "=== TRAJECTORY — ALL PRIOR NOTES (oldest first) ===";
    $lines[] = "Read the full arc before responding. The pattern matters more than the single note.";
    $lines[] = "";
    foreach ($trajectory as $t) { $lines[] = $t; $lines[] = "---"; }
    $lines[] = "";
    $lines[] = "=== LIVE EMPIRE SIGNALS ===";
    foreach ($empire as $e) $lines[] = "- " . $e;
    $lines[] = "";
    $lines[] = "=== DELIVER ===";
    $lines[] = "1. PATTERN — Name the underlying principle this note expresses in one sentence. Then state the principle AT SCALE — what does it look like when applied to the entire empire, not just this one move?";
    $lines[] = "2. SCALE-UP LADDER — Give me 3 versions of this move, each 10x bigger than the last. Version 1: current. Version 2: 10x. Version 3: 100x. Name the specific venues, counterparties, capital, or systems at each rung.";
    $lines[] = "3. MOTION — Executable steps in exact order:";
    $lines[] = "   • THIS WEEK — 3 concrete actions, each with the name of a person to contact, a number to propose, or a document to produce.";
    $lines[] = "   • NEXT 30 DAYS — 5 concrete actions with measurable outputs.";
    $lines[] = "   • NEXT 90 DAYS — the milestone that proves Version 2 is possible.";
    $lines[] = "   • NEXT 12 MONTHS — the position that unlocks Version 3.";
    $lines[] = "4. FORCE MULTIPLIERS — What systems, people, platforms, or capital pools start compounding once this move is made? List at least 5.";
    $lines[] = "5. THE UNSTOPPABLE MOVE — If I just kept doing this at maximum velocity for 24 months without slowing down, what does the empire look like at the end? Describe the control surface in concrete terms: venues, revenue, people, rights, reach.";
    $lines[] = "6. ONE-LINE FUEL — A single sentence I can carry in my head while moving. Make it match the altitude. No hedging.";
    echo json_encode(['ok'=>true, 'prompt'=>implode("\n", $lines)]);
    exit;
}

// ── Fetch notes (filter + search) ──
$filter_biz = $_GET['biz'] ?? '';
$search     = trim($_GET['q'] ?? '');
$show_past  = !empty($_GET['past']);

$where = [];
$args  = [];
if (!$show_past) {
    $where[] = "(outcome = '' OR outcome IS NULL OR outcome = 'pending')";
} else {
    $where[] = "(outcome <> '' AND outcome IS NOT NULL AND outcome <> 'pending')";
}
if ($filter_biz !== '' && in_array($filter_biz, ['argonar','ocpd','loan','alrisha','general'], true)) {
    $where[] = "business = ?";
    $args[] = $filter_biz;
}
if ($search !== '') {
    // Allow searching by track id (BR-0003 / br0003 / 3)
    $sid = preg_replace('/^br[-_]?0*/i', '', $search);
    $idNum = ctype_digit($sid) ? (int)$sid : 0;
    $where[] = "(title LIKE ? OR context_text LIKE ? OR tags LIKE ? OR id = ?)";
    $args[] = "%$search%"; $args[] = "%$search%"; $args[] = "%$search%"; $args[] = $idNum;
}
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$notes = [];
try {
    $st = $argonar_pdo->prepare("SELECT * FROM decision_log $where_sql ORDER BY COALESCE(decided_at, created_at) DESC, id DESC LIMIT 200");
    $st->execute($args);
    $notes = $st->fetchAll();
} catch (Exception $e) {}

$counts = ['open'=>0,'past'=>0];
try {
    $counts['open'] = (int)$argonar_pdo->query("SELECT COUNT(*) FROM decision_log WHERE outcome='' OR outcome IS NULL OR outcome='pending'")->fetchColumn();
    $counts['past'] = (int)$argonar_pdo->query("SELECT COUNT(*) FROM decision_log WHERE outcome<>'' AND outcome IS NOT NULL AND outcome<>'pending'")->fetchColumn();
} catch (Exception $e) {}

// ── Sidebar stats ──
$sidebar_stats = ['argonar'=>['sessions'=>0,'live'=>0],'ocpd'=>['sessions'=>0,'live'=>0],'loan'=>['sessions'=>0,'live'=>0],'alrisha'=>['sessions'=>0,'live'=>0]];
try {
    $rows_sb = $argonar_pdo->query("SELECT CASE WHEN site IS NULL OR site='' THEN 'argonar' ELSE site END as s, COUNT(DISTINCT session_id) as n FROM activity_logs WHERE created_at >= CURDATE() GROUP BY s")->fetchAll();
    foreach ($rows_sb as $r) if (isset($sidebar_stats[$r['s']])) $sidebar_stats[$r['s']]['sessions'] = (int)$r['n'];
    $rows_sb = $argonar_pdo->query("SELECT CASE WHEN site IS NULL OR site='' THEN 'argonar' ELSE site END as s, COUNT(DISTINCT session_id) as n FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) GROUP BY s")->fetchAll();
    foreach ($rows_sb as $r) if (isset($sidebar_stats[$r['s']])) $sidebar_stats[$r['s']]['live'] = (int)$r['n'];
} catch (Exception $e) {}
$date_range = 'today';

// ── Motion patterns — how to scale, never how to brake ──
$mental_models = [
    ['name'=>'Getty · Control without ownership','icon'=>'bi-graph-up-arrow',  'color'=>'#fde68a', 'apply'=>'Rent, lease, license, lend — the asset moves, you do not. Seek rights, never title.'],
    ['name'=>'Rockefeller · Own the chokepoint', 'icon'=>'bi-diagram-3-fill',  'color'=>'#ec4899', 'apply'=>'Find the single point every competitor has to pass through. Control that, not the product.'],
    ['name'=>'Cialdini · Influence',             'icon'=>'bi-people-fill',     'color'=>'#f472b6', 'apply'=>'Which principle makes the other side say yes with the least friction? Lead with that, then under-ask so the debt stays open.'],
    ['name'=>'Thiel · Monopoly',                 'icon'=>'bi-shield-lock-fill','color'=>'#a78bfa', 'apply'=>'Compete for positions that have no competition. Market share is a tax. Monopoly is freedom.'],
    ['name'=>'Flywheel',                         'icon'=>'bi-arrow-repeat',    'color'=>'#34d399', 'apply'=>'Each win reduces friction on the next. Build the loop, then let it spin — the nth turn costs nothing.'],
    ['name'=>'Asymmetric bets',                  'icon'=>'bi-bullseye',        'color'=>'#60a5fa', 'apply'=>'Downside capped, upside unbounded. If it costs <1 week and could 10x something — ship it same day.'],
    ['name'=>'Bezos · Regret minimization',      'icon'=>'bi-clock-history',   'color'=>'#38bdf8', 'apply'=>'At 80, will I regret doing this — or NOT doing this — more? When the answer is "not doing," the risk side evaporates.'],
    ['name'=>'First principles',                 'icon'=>'bi-bricks',          'color'=>'#fbbf24', 'apply'=>'Strip to physics. What is the minimum irreducible cost here? Everything above that is ceremony and removable.'],
    ['name'=>'Capital recycling',                'icon'=>'bi-currency-exchange','color'=>'#f59e0b', 'apply'=>'Deploy → recover → redeploy into the next position. Same capital, compounding rights portfolio.'],
    ['name'=>'Altitude lock',                    'icon'=>'bi-rocket-takeoff-fill','color'=>'#fb7185', 'apply'=>'When the move feels too big, it is the right size. Reasonable feels safe because it is close to the mean — which is where nothing is won.'],
];

$biz_colors = [
    'argonar'=>'#a78bfa','ocpd'=>'#38bdf8','loan'=>'#c4b5fd','alrisha'=>'#34d399','general'=>'#9ca3af'
];

// Group notes by date for notebook-style rendering
$grouped = [];
foreach ($notes as $n) {
    $d = date('Y-m-d', strtotime($n['decided_at'] ?? $n['created_at']));
    $grouped[$d][] = $n;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Brain Notebook — Omniscient</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<?php require __DIR__ . '/omni/css.php'; ?>
<style>
.nb-wrap { max-width: 900px; margin: 0 auto; padding: 1.5rem 1.25rem; }

.nb-hero { display: flex; align-items: baseline; gap: 0.75rem; margin-bottom: 1.25rem; padding-bottom: 0.85rem; border-bottom: 1px solid rgba(255,255,255,0.08); }
.nb-hero h1 { font-size: 1.4rem; font-weight: 900; color: #fbcfe8; margin: 0; letter-spacing: -0.5px; display: flex; align-items: center; gap: 0.5rem; }
.nb-hero h1 i { color: #ec4899; }
.nb-hero .sub { font-size: 0.78rem; color: #6b7280; font-style: italic; margin-left: auto; }

/* Notebook paper capture */
.nb-paper { background: #0f0f14; border: 1px solid rgba(236,72,153,0.2); border-radius: 10px; padding: 1.1rem 1.3rem; margin-bottom: 1.5rem; position: relative; }
.nb-paper::before { content: ''; position: absolute; left: 1.3rem; top: 0; bottom: 0; width: 1px; background: rgba(236,72,153,0.15); }
.nb-paper input.nb-title { width: 100%; background: transparent; border: none; color: #fbcfe8; font-size: 1rem; font-weight: 800; padding: 0.15rem 0 0.35rem 0.6rem; outline: none; font-family: inherit; letter-spacing: -0.2px; }
.nb-paper input.nb-title::placeholder { color: rgba(236,72,153,0.4); font-weight: 700; }
.nb-paper textarea.nb-body { width: 100%; background: transparent; border: none; color: #e5e7eb; font-size: 0.9rem; resize: vertical; min-height: 120px; padding: 0.35rem 0 0.4rem 0.6rem; outline: none; font-family: inherit; line-height: 1.75; background-image: repeating-linear-gradient(to bottom, transparent, transparent 27px, rgba(255,255,255,0.04) 27px, rgba(255,255,255,0.04) 28px); }
.nb-paper textarea.nb-body::placeholder { color: #4b5563; font-style: italic; }
.nb-paper .nb-row { display: flex; gap: 0.6rem; align-items: center; margin-top: 0.7rem; padding-top: 0.7rem; border-top: 1px dashed rgba(255,255,255,0.06); }
.nb-paper select.nb-meta, .nb-paper input.nb-meta { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); color: #d1d5db; border-radius: 6px; padding: 0.35rem 0.55rem; font-size: 0.75rem; outline: none; font-family: inherit; }
.nb-paper select.nb-meta:focus, .nb-paper input.nb-meta:focus { border-color: #ec4899; }
.nb-paper .nb-flex { flex: 1; }
.nb-paper .nb-save { background: #ec4899; color: #fff; border: none; padding: 0.4rem 1rem; border-radius: 6px; font-size: 0.78rem; font-weight: 800; cursor: pointer; font-family: inherit; }
.nb-paper .nb-save:hover { background: #db2777; }
.nb-paper .nb-hint { font-size: 0.68rem; color: #6b7280; margin-left: 0.5rem; }
.nb-paper.editing { border-color: rgba(251,191,36,0.35); background: rgba(251,191,36,0.03); }
.nb-paper.editing .nb-title { color: #fde68a; }

/* Filters strip */
.nb-filters { display: flex; gap: 0.4rem; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; font-size: 0.75rem; }
.nb-filters .pill { padding: 0.3rem 0.8rem; border-radius: 999px; border: 1px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.02); color: #9ca3af; text-decoration: none; font-weight: 700; font-size: 0.72rem; display: inline-flex; align-items: center; gap: 0.3rem; }
.nb-filters .pill:hover { border-color: rgba(236,72,153,0.3); color: #fbcfe8; }
.nb-filters .pill.active { background: rgba(236,72,153,0.15); border-color: rgba(236,72,153,0.4); color: #fbcfe8; }
.nb-filters .pill .c { color: #6b7280; font-weight: 600; }
.nb-filters input.nb-search { margin-left: auto; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); color: #d1d5db; padding: 0.3rem 0.6rem; border-radius: 6px; font-size: 0.72rem; font-family: inherit; outline: none; min-width: 160px; }
.nb-filters input.nb-search:focus { border-color: #ec4899; }

/* Notebook feed */
.nb-day { margin-bottom: 1.5rem; }
.nb-day-head { font-size: 0.68rem; color: #6b7280; font-weight: 800; letter-spacing: 1.5px; text-transform: uppercase; margin-bottom: 0.6rem; padding-bottom: 0.35rem; border-bottom: 1px dashed rgba(255,255,255,0.06); display: flex; align-items: center; gap: 0.45rem; }
.nb-day-head i { color: #a78bfa; font-size: 0.8rem; }

.nb-entry { background: rgba(255,255,255,0.015); border: 1px solid rgba(255,255,255,0.05); border-left: 3px solid rgba(236,72,153,0.4); border-radius: 0 8px 8px 0; padding: 0.8rem 1rem; margin-bottom: 0.6rem; position: relative; }
.nb-entry.past { border-left-color: rgba(156,163,175,0.25); opacity: 0.78; }
.nb-entry.good { border-left-color: rgba(52,211,153,0.5); }
.nb-entry.bad  { border-left-color: rgba(248,113,113,0.5); }
.nb-entry-head { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.3rem; }
.nb-track { font-family: 'SF Mono', Consolas, monospace; font-size: 0.6rem; font-weight: 800; color: #a78bfa; background: rgba(167,139,250,0.1); border: 1px solid rgba(167,139,250,0.2); padding: 0.12rem 0.4rem; border-radius: 3px; letter-spacing: 0.5px; cursor: pointer; flex-shrink: 0; transition: all 0.15s; }
.nb-track:hover { background: rgba(167,139,250,0.2); color: #c4b5fd; }
.nb-track.copied { background: rgba(52,211,153,0.2); color: #34d399; border-color: rgba(52,211,153,0.3); }
.nb-entry:target { animation: nbFlash 1.5s ease; }
@keyframes nbFlash { 0%, 100% { box-shadow: none; } 50% { box-shadow: 0 0 0 2px rgba(236,72,153,0.5); } }
.nb-entry-title { font-size: 0.92rem; font-weight: 800; color: #f3f4f6; letter-spacing: -0.1px; flex: 1; line-height: 1.35; }
.nb-entry-time { font-size: 0.65rem; color: #6b7280; font-weight: 700; flex-shrink: 0; }
.nb-entry-body { font-size: 0.82rem; color: #d1d5db; line-height: 1.65; white-space: pre-wrap; margin: 0.2rem 0 0.55rem; word-wrap: break-word; }
.nb-analysis { background: rgba(167,139,250,0.06); border: 1px solid rgba(167,139,250,0.2); border-radius: 6px; padding: 0.55rem 0.75rem; margin: 0.4rem 0 0.55rem; }
.nb-analysis-head { font-size: 0.62rem; font-weight: 800; color: #a78bfa; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 0.35rem; display: flex; align-items: center; gap: 0.3rem; }
.nb-analysis-head i { font-size: 0.75rem; }
.nb-analysis-body { font-size: 0.78rem; color: #e5e7eb; line-height: 1.6; white-space: pre-wrap; word-wrap: break-word; font-style: italic; }
.nb-entry-meta { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; font-size: 0.65rem; color: #9ca3af; margin-top: 0.45rem; padding-top: 0.45rem; border-top: 1px dotted rgba(255,255,255,0.05); }
.nb-biz-tag { padding: 0.1rem 0.45rem; border-radius: 3px; font-weight: 800; letter-spacing: 0.5px; text-transform: uppercase; font-size: 0.6rem; }
.nb-tag { background: rgba(255,255,255,0.04); padding: 0.1rem 0.4rem; border-radius: 3px; color: #d1d5db; font-weight: 700; }
.nb-outcome { padding: 0.1rem 0.45rem; border-radius: 3px; font-weight: 800; letter-spacing: 0.5px; text-transform: uppercase; font-size: 0.6rem; }
.nb-outcome.good { background: rgba(52,211,153,0.15); color: #34d399; }
.nb-outcome.bad  { background: rgba(248,113,113,0.15); color: #f87171; }
.nb-outcome.mixed{ background: rgba(251,191,36,0.15); color: #fbbf24; }
.nb-entry-actions { margin-left: auto; display: flex; gap: 0.3rem; }
.nb-btn { font-size: 0.62rem; padding: 0.18rem 0.5rem; border-radius: 4px; border: 1px solid rgba(255,255,255,0.08); background: transparent; color: #9ca3af; cursor: pointer; font-family: inherit; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
.nb-btn:hover { border-color: #ec4899; color: #fbcfe8; }
.nb-btn.danger:hover { border-color: #ef4444; color: #f87171; }

.nb-empty { text-align: center; padding: 2.5rem 1rem; color: #6b7280; font-size: 0.85rem; font-style: italic; }

/* Sidebar of lenses */
.nb-lens-bar { background: #0f0f14; border: 1px solid rgba(255,255,255,0.05); border-radius: 10px; padding: 0.9rem 1.1rem; margin-bottom: 1.5rem; }
.nb-lens-head { font-size: 0.68rem; color: #9ca3af; font-weight: 800; letter-spacing: 1.5px; text-transform: uppercase; margin-bottom: 0.6rem; display: flex; align-items: center; gap: 0.4rem; }
.nb-lens-head i { color: #a78bfa; }
.nb-lens-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 0.5rem; }
.nb-lens { font-size: 0.7rem; color: #d1d5db; padding: 0.5rem 0.65rem; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius: 6px; line-height: 1.45; }
.nb-lens-name { font-weight: 800; font-size: 0.72rem; display: flex; align-items: center; gap: 0.35rem; margin-bottom: 0.25rem; }
.nb-lens-apply { color: #9ca3af; font-size: 0.68rem; }

/* AI prompt output */
.nb-prompt-wrap { background: #0f0f14; border: 1px solid rgba(167,139,250,0.2); border-radius: 10px; padding: 0.9rem 1.1rem; margin-bottom: 1.5rem; }
.nb-prompt-head { font-size: 0.68rem; color: #9ca3af; font-weight: 800; letter-spacing: 1.5px; text-transform: uppercase; margin-bottom: 0.6rem; display: flex; align-items: center; gap: 0.4rem; }
.nb-prompt-head i { color: #a78bfa; }
.nb-prompt-out { background: #0a0a0f; border: 1px solid rgba(167,139,250,0.2); border-radius: 6px; padding: 0.8rem 1rem; font-family: 'Inter', monospace; font-size: 0.72rem; color: #d1d5db; white-space: pre-wrap; word-wrap: break-word; min-height: 60px; line-height: 1.6; max-height: 320px; overflow-y: auto; }
.nb-prompt-actions { display: flex; gap: 0.35rem; margin-top: 0.55rem; }
.nb-prompt-actions .nb-btn { font-size: 0.65rem; padding: 0.3rem 0.65rem; }

@media (max-width: 720px) {
    .nb-wrap { padding: 1rem 0.7rem; }
    .nb-filters input.nb-search { margin-left: 0; width: 100%; order: 99; }
}
</style>
</head>
<body>
<div class="omni-layout">

<?php require __DIR__ . '/omni/sidebar.php'; ?>

<div class="omni-main">
<div class="nb-wrap">

    <div class="nb-hero">
        <h1><i class="bi bi-lightning-charge-fill"></i> Brain</h1>
        <span class="sub">power the motion — expand the vision, generate the next move.</span>
    </div>

    <!-- Notebook capture -->
    <form class="nb-paper" id="nbForm" onsubmit="return nbSave(event)">
        <input type="hidden" id="nbId" value="">
        <input type="text" class="nb-title" id="nbTitle" placeholder="Title — what's the one line?" maxlength="255">
        <textarea class="nb-body" id="nbBody" placeholder="Write freely. What you saw, what you're thinking, who's involved, what you might do. Leave the form — just think on the page."></textarea>
        <div class="nb-row">
            <select class="nb-meta" id="nbBusiness">
                <option value="general">General</option>
                <option value="argonar">Argonar</option>
                <option value="ocpd">OCPD</option>
                <option value="loan">Loan PH</option>
                <option value="alrisha">Alrisha</option>
            </select>
            <input type="text" class="nb-meta nb-flex" id="nbTags" placeholder="tags (leverage, pricing, cialdini)">
            <button type="button" class="nb-btn" id="nbCancel" onclick="nbReset()" style="display:none;">Cancel</button>
            <button type="submit" class="nb-save">Save</button>
        </div>
        <div class="nb-hint" id="nbHint" style="margin-top:0.35rem;"><kbd>Ctrl</kbd>+<kbd>Enter</kbd> to save</div>
    </form>

    <!-- AI prompt output (empty until user clicks "Ask" on an entry) -->
    <div class="nb-prompt-wrap" id="nbPromptBox" style="display:none;">
        <div class="nb-prompt-head"><i class="bi bi-rocket-takeoff-fill"></i> Motion prompt — pattern → scale-up ladder → 12-month empire</div>
        <div class="nb-prompt-out" id="nbPromptOut"></div>
        <div class="nb-prompt-actions">
            <button class="nb-btn" onclick="nbCopyPrompt()"><i class="bi bi-clipboard"></i> Copy</button>
            <a class="nb-btn" href="https://claude.ai/new" target="_blank">Open Claude</a>
            <a class="nb-btn" href="https://chat.openai.com/" target="_blank">Open ChatGPT</a>
            <button class="nb-btn" onclick="document.getElementById('nbPromptBox').style.display='none';" style="margin-left:auto;">Close</button>
        </div>
    </div>

    <!-- Filter strip -->
    <div class="nb-filters">
        <a class="pill <?= $show_past ? '' : 'active' ?>" href="<?= base_url('admin/activity-brain.php') ?>"><i class="bi bi-journal"></i> Open <span class="c"><?= $counts['open'] ?></span></a>
        <a class="pill <?= $show_past ? 'active' : '' ?>" href="?past=1"><i class="bi bi-archive"></i> Past <span class="c"><?= $counts['past'] ?></span></a>
        <?php foreach (['argonar','ocpd','loan','alrisha','general'] as $bz):
            $qs = http_build_query(array_filter(['past'=>$show_past?1:null, 'biz'=>$bz, 'q'=>$search]));
            $is_on = ($filter_biz === $bz);
        ?>
        <a class="pill <?= $is_on ? 'active' : '' ?>" href="?<?= $qs ?>"><?= ucfirst($bz) ?></a>
        <?php endforeach; ?>
        <form method="get" style="margin-left:auto;">
            <?php if ($show_past): ?><input type="hidden" name="past" value="1"><?php endif; ?>
            <?php if ($filter_biz): ?><input type="hidden" name="biz" value="<?= htmlspecialchars($filter_biz) ?>"><?php endif; ?>
            <input type="text" name="q" class="nb-search" placeholder="Search notes…" value="<?= htmlspecialchars($search) ?>">
        </form>
    </div>

    <!-- Notebook feed -->
    <?php if (empty($grouped)): ?>
    <div class="nb-empty">
        <i class="bi bi-journal-plus" style="font-size:1.8rem; display:block; margin-bottom:0.5rem; color:#4b5563;"></i>
        <?= $search || $filter_biz ? 'No notes match. Try clearing filters.' : 'Blank page. Write your first note above.' ?>
    </div>
    <?php else: foreach ($grouped as $day => $day_notes):
        $day_label = date('D · M j, Y', strtotime($day));
        $is_today = ($day === date('Y-m-d'));
        $is_yday  = ($day === date('Y-m-d', strtotime('-1 day')));
        if ($is_today) $day_label = 'TODAY · ' . date('D M j', strtotime($day));
        elseif ($is_yday) $day_label = 'YESTERDAY · ' . date('D M j', strtotime($day));
    ?>
    <div class="nb-day">
        <div class="nb-day-head"><i class="bi bi-calendar3"></i> <?= $day_label ?></div>
        <?php foreach ($day_notes as $n):
            $out_lc = strtolower($n['outcome'] ?? '');
            $row_cls = 'nb-entry';
            if ($show_past) $row_cls .= ' past';
            if (in_array($out_lc, ['good','worked','win','success','positive'])) $row_cls .= ' good';
            elseif (in_array($out_lc, ['bad','failed','loss','didn\'t','lost','negative'])) $row_cls .= ' bad';
            $biz_col = $biz_colors[$n['business'] ?? 'general'] ?? '#9ca3af';
        ?>
        <?php $track_id = 'BR-' . str_pad($n['id'], 4, '0', STR_PAD_LEFT); ?>
        <div class="<?= $row_cls ?>" id="<?= $track_id ?>">
            <div class="nb-entry-head">
                <span class="nb-track" onclick="nbCopyId('<?= $track_id ?>')" title="Click to copy"><?= $track_id ?></span>
                <div class="nb-entry-title"><?= htmlspecialchars($n['title']) ?></div>
                <div class="nb-entry-time"><?= date('g:i a', strtotime($n['decided_at'] ?? $n['created_at'])) ?></div>
            </div>
            <?php if (!empty($n['context_text'])): ?>
            <div class="nb-entry-body"><?= htmlspecialchars($n['context_text']) ?></div>
            <?php endif; ?>
            <?php if (!empty($n['impact_text'])): ?>
            <div class="nb-analysis">
                <div class="nb-analysis-head"><i class="bi bi-stars"></i> Claude's take</div>
                <div class="nb-analysis-body"><?= htmlspecialchars($n['impact_text']) ?></div>
            </div>
            <?php endif; ?>
            <div class="nb-entry-meta">
                <span class="nb-biz-tag" style="background:<?= $biz_col ?>22;color:<?= $biz_col ?>;"><?= htmlspecialchars(ucfirst($n['business'] ?: 'general')) ?></span>
                <?php if (!empty($n['tags'])): foreach (array_filter(array_map('trim', explode(',', $n['tags']))) as $t): ?>
                <span class="nb-tag">#<?= htmlspecialchars($t) ?></span>
                <?php endforeach; endif; ?>
                <?php
                    $is_open = empty($n['outcome']) || $out_lc === 'pending';
                    if (!$is_open):
                        $ocls = 'mixed';
                        if (in_array($out_lc, ['good','worked','win','success','positive'])) $ocls = 'good';
                        elseif (in_array($out_lc, ['bad','failed','loss','didn\'t','lost','negative'])) $ocls = 'bad';
                ?>
                <span class="nb-outcome <?= $ocls ?>"><?= htmlspecialchars($n['outcome']) ?></span>
                <?php endif; ?>
                <div class="nb-entry-actions">
                    <button class="nb-btn" onclick="nbAsk(<?= $n['id'] ?>)" title="Expand this move into scale-up + motion + 24-month empire"><i class="bi bi-rocket-takeoff-fill"></i> Expand</button>
                    <button class="nb-btn" onclick='nbAnalysis(<?= (int)$n['id'] ?>, <?= htmlspecialchars(json_encode($n['impact_text'] ?? ''), ENT_QUOTES) ?>)' title="Paste Claude's analysis"><i class="bi bi-chat-quote"></i> <?= !empty($n['impact_text']) ? 'Edit take' : 'Paste take' ?></button>
                    <button class="nb-btn" onclick='nbEdit(<?= htmlspecialchars(json_encode($n), ENT_QUOTES) ?>)'>Edit</button>
                    <?php if ($is_open): ?>
                    <button class="nb-btn" onclick="nbClose(<?= $n['id'] ?>, 'positive')" title="Worked">✓</button>
                    <button class="nb-btn" onclick="nbClose(<?= $n['id'] ?>, 'negative')" title="Didn't">✗</button>
                    <?php else: ?>
                    <button class="nb-btn" onclick="nbClose(<?= $n['id'] ?>, '')" title="Reopen"><i class="bi bi-arrow-counterclockwise"></i></button>
                    <?php endif; ?>
                    <button class="nb-btn danger" onclick="nbDelete(<?= $n['id'] ?>)"><i class="bi bi-trash"></i></button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; endif; ?>

    <!-- Mental lenses reference (quiet, at bottom — always available) -->
    <div class="nb-lens-bar">
        <div class="nb-lens-head"><i class="bi bi-rocket-takeoff-fill"></i> Motion patterns — pick one and move</div>
        <div class="nb-lens-grid">
            <?php foreach ($mental_models as $m): ?>
            <div class="nb-lens">
                <div class="nb-lens-name"><i class="bi <?= $m['icon'] ?>" style="color:<?= $m['color'] ?>;"></i> <?= htmlspecialchars($m['name']) ?></div>
                <div class="nb-lens-apply"><?= htmlspecialchars($m['apply']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

</div><!-- /nb-wrap -->
</div><!-- /omni-main -->
</div><!-- /omni-layout -->

<script>
const NB_AJAX = '<?= base_url('admin/activity-brain.php') ?>';
let nbLastPrompt = '';

async function nbSave(e) {
    if (e) e.preventDefault();
    const title = document.getElementById('nbTitle').value.trim();
    const body  = document.getElementById('nbBody').value.trim();
    if (!title && !body) { document.getElementById('nbBody').focus(); return false; }
    const payload = {
        id: parseInt(document.getElementById('nbId').value) || 0,
        title: title,
        body: body,
        business: document.getElementById('nbBusiness').value,
        tags: document.getElementById('nbTags').value.trim(),
    };
    const r = await fetch(NB_AJAX + '?ajax=save', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    const d = await r.json();
    if (d.ok) location.reload();
    else alert('Failed: ' + (d.error || 'unknown'));
    return false;
}

function nbReset() {
    document.getElementById('nbId').value = '';
    document.getElementById('nbTitle').value = '';
    document.getElementById('nbBody').value = '';
    document.getElementById('nbBusiness').value = 'general';
    document.getElementById('nbTags').value = '';
    document.getElementById('nbForm').classList.remove('editing');
    document.getElementById('nbCancel').style.display = 'none';
}

function nbEdit(n) {
    document.getElementById('nbId').value = n.id;
    document.getElementById('nbTitle').value = n.title || '';
    document.getElementById('nbBody').value = n.context_text || '';
    document.getElementById('nbBusiness').value = n.business || 'general';
    document.getElementById('nbTags').value = n.tags || '';
    document.getElementById('nbForm').classList.add('editing');
    document.getElementById('nbCancel').style.display = '';
    document.getElementById('nbForm').scrollIntoView({behavior:'smooth', block:'start'});
    document.getElementById('nbBody').focus();
}

async function nbClose(id, outcome) {
    const card = document.querySelector(`[onclick*="nbClose(${id},"]`)?.closest('.nb-entry');
    const title = card?.querySelector('.nb-entry-title')?.textContent.trim() || '—';
    const body  = card?.querySelector('.nb-entry-body')?.textContent.trim() || '';
    await fetch(NB_AJAX + '?ajax=save', { method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ id: id, title: title, body: body, outcome: outcome })
    });
    location.reload();
}

async function nbDelete(id) {
    if (!confirm('Delete this note?')) return;
    await fetch(NB_AJAX + '?ajax=delete&id=' + id);
    location.reload();
}

async function nbAsk(id) {
    const r = await fetch(NB_AJAX + '?ajax=prompt&id=' + id);
    const d = await r.json();
    if (d.ok) {
        nbLastPrompt = d.prompt;
        document.getElementById('nbPromptBox').style.display = '';
        document.getElementById('nbPromptOut').textContent = d.prompt;
        document.getElementById('nbPromptBox').scrollIntoView({behavior:'smooth', block:'center'});
    }
}

function nbCopyPrompt() {
    if (!nbLastPrompt) return;
    navigator.clipboard.writeText(nbLastPrompt).then(function(){
        const btn = event.target.closest('button'); const old = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check2"></i> Copied';
        setTimeout(() => btn.innerHTML = old, 1500);
    }, function(){
        const ta = document.createElement('textarea'); ta.value = nbLastPrompt; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); ta.remove();
    });
}

async function nbAnalysis(id, existing) {
    const text = prompt("Paste Claude's analysis for this note:\n(leave empty to clear)", existing || '');
    if (text === null) return;
    const r = await fetch(NB_AJAX + '?ajax=analysis', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ id: id, analysis: text.trim() })
    });
    const d = await r.json();
    if (d.ok) location.reload();
    else alert('Failed: ' + (d.error || 'unknown'));
}

function nbCopyId(id) {
    navigator.clipboard.writeText(id).then(function(){
        const el = document.querySelector(`.nb-track[onclick*="${id}"]`);
        if (el) { el.classList.add('copied'); setTimeout(() => el.classList.remove('copied'), 900); }
    });
}

// Ctrl+Enter to save
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        if (document.activeElement.closest('#nbForm')) { e.preventDefault(); nbSave(); }
    }
});
</script>
</body>
</html>
