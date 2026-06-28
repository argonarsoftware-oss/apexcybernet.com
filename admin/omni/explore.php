<?php
/**
 * admin/omni/explore.php
 *
 * Single-surface ontology browser.
 *   ?q=<text>               — search label / ref
 *   ?type=Person|Loan|...   — filter by type
 *   ?biz=argonar|loan|...   — filter by business
 *   ?id=<obj_id>            — detail view
 *   ?ref=<ref>              — detail by ref
 *   ?action=<type>&object_id=...  — record an action (POST)
 *   ?ajax=graph&id=<id>     — JSON for neighborhood graph
 */

$active_site = 'omni';
$page_file   = 'omni/explore.php';

require_once __DIR__ . '/../../includes/db.php';
$argonar_pdo = $pdo;
require_once __DIR__ . '/auth.php';                 // kirfenia gate
require_once __DIR__ . '/pipelines/taxonomy.php';

// ── POST: record an action ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    $action_type = trim($_POST['action_type']);
    $object_id   = (int)($_POST['object_id'] ?? 0);
    $payload_raw = trim($_POST['payload'] ?? '{}');
    $payload     = json_decode($payload_raw, true) ?: [];
    $status      = $_POST['execute'] ?? null === '1' ? 'proposed' : 'proposed'; // execute handled in Phase 4
    if ($object_id && $action_type) {
        omni_record_action($argonar_pdo, [
            'object_id'   => $object_id,
            'action_type' => $action_type,
            'payload'     => $payload,
            'status'      => 'proposed',
        ]);
    }
    header('Location: explore.php?id=' . $object_id . '&a=ok');
    exit;
}

// ── AJAX: neighborhood graph JSON ──
if (isset($_GET['ajax']) && $_GET['ajax'] === 'graph') {
    header('Content-Type: application/json');
    $id = (int)($_GET['id'] ?? 0);
    $n  = omni_neighbors($argonar_pdo, $id, 80);
    $root = omni_object_by_id($argonar_pdo, $id);
    echo json_encode(['root'=>$root, 'neighbors'=>$n]);
    exit;
}

// ── Sidebar stats (same shape as other omni pages) ──
$sidebar_stats = ['argonar'=>['sessions'=>0,'live'=>0],'ocpd'=>['sessions'=>0,'live'=>0],'loan'=>['sessions'=>0,'live'=>0],'alrisha'=>['sessions'=>0,'live'=>0]];
try {
    $rows_sb = $argonar_pdo->query("SELECT CASE WHEN site IS NULL OR site='' THEN 'argonar' ELSE site END as s,
        COUNT(DISTINCT session_id) as n FROM activity_logs WHERE created_at >= CURDATE() GROUP BY s")->fetchAll();
    foreach ($rows_sb as $r) if (isset($sidebar_stats[$r['s']])) $sidebar_stats[$r['s']]['sessions'] = (int)$r['n'];
    $rows_sb = $argonar_pdo->query("SELECT CASE WHEN site IS NULL OR site='' THEN 'argonar' ELSE site END as s,
        COUNT(DISTINCT session_id) as n FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) GROUP BY s")->fetchAll();
    foreach ($rows_sb as $r) if (isset($sidebar_stats[$r['s']])) $sidebar_stats[$r['s']]['live'] = (int)$r['n'];
} catch (Exception $e) {}
$date_range = $_GET['dr'] ?? 'today';

// ── Query state ──
$q    = trim((string)($_GET['q']    ?? ''));
$type = trim((string)($_GET['type'] ?? ''));
$biz  = trim((string)($_GET['biz']  ?? ''));
$sel_id  = (int)($_GET['id']  ?? 0);
$sel_ref = trim((string)($_GET['ref'] ?? ''));
if (!$sel_id && $sel_ref) $sel_id = omni_id_for_ref($argonar_pdo, $sel_ref);

// ── Totals banner ──
$totals = ['objects'=>0,'links'=>0,'actions'=>0,'businesses'=>0,'by_type'=>[]];
try {
    $totals['objects'] = (int)$argonar_pdo->query("SELECT COUNT(*) FROM omni_objects")->fetchColumn();
    $totals['links']   = (int)$argonar_pdo->query("SELECT COUNT(*) FROM omni_links")->fetchColumn();
    $totals['actions'] = (int)$argonar_pdo->query("SELECT COUNT(*) FROM omni_actions")->fetchColumn();
    $totals['businesses'] = (int)$argonar_pdo->query("SELECT COUNT(DISTINCT business) FROM omni_objects")->fetchColumn();
    $totals['by_type'] = $argonar_pdo->query("SELECT type, COUNT(*) c FROM omni_objects GROUP BY type ORDER BY c DESC")->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {}

// ── Search results (or browse-by-type list) ──
$results = [];
$where = []; $binds = [];
if ($q !== '') {
    $where[] = "(label LIKE :q OR ref LIKE :q)";
    $binds[':q'] = '%' . $q . '%';
}
if ($type !== '') { $where[] = "type = :type"; $binds[':type'] = $type; }
if ($biz !== '')  { $where[] = "business = :biz"; $binds[':biz'] = $biz; }
$sql = "SELECT id, ref, type, business, label, updated_at FROM omni_objects";
if ($where) $sql .= " WHERE " . implode(' AND ', $where);
$sql .= " ORDER BY updated_at DESC LIMIT 100";
try {
    $st = $argonar_pdo->prepare($sql);
    foreach ($binds as $k=>$v) $st->bindValue($k,$v);
    $st->execute();
    $results = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $results = []; }

// ── Detail view ──
$selected = null; $neighbors = ['in'=>[],'out'=>[]]; $actions_recent = [];
if ($sel_id) {
    $selected = omni_object_by_id($argonar_pdo, $sel_id);
    if ($selected) {
        $neighbors = omni_neighbors($argonar_pdo, $sel_id, 200);
        try {
            $st = $argonar_pdo->prepare("SELECT * FROM omni_actions WHERE object_id = ? ORDER BY performed_at DESC LIMIT 50");
            $st->execute([$sel_id]);
            $actions_recent = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}
    }
}

// ── Context-aware action menu ──
$available_actions = [];
if ($selected) {
    $t = $selected['type'];
    $b = $selected['business'];
    if ($t === 'Person') {
        $available_actions[] = ['credit_hc','Credit HC',   'amount, reason'];
        $available_actions[] = ['debit_hc', 'Debit HC',    'amount, reason'];
        $available_actions[] = ['annotate', 'Add note',    'note'];
    }
    if ($t === 'Team') {
        $available_actions[] = ['approve_team', 'Approve team', ''];
        $available_actions[] = ['reject_team',  'Reject team',  'reason'];
        $available_actions[] = ['rank_team',    'Rank / seed',  'rank'];
    }
    if ($t === 'Loan') {
        $available_actions[] = ['approve_loan',    'Approve loan',    ''];
        $available_actions[] = ['extend_loan',     'Extend term',     'months'];
        $available_actions[] = ['write_off_loan',  'Write off',       'reason'];
    }
    if ($t === 'Decision') {
        $available_actions[] = ['close_decision', 'Close decision',   'outcome'];
    }
    if (in_array($t, ['Person','Team','Listing','Booking'])) {
        $available_actions[] = ['propose_conversion', 'Propose social→real conversion (BR-0009)', 'target_asset, note'];
    }
    $available_actions[] = ['simulate', 'Simulate this write', 'any payload — records status=simulated'];
}

$props_decoded = [];
if ($selected && !empty($selected['props'])) {
    $props_decoded = json_decode($selected['props'], true) ?: [];
}

// Build a merged timeline from in+out links
$timeline = [];
foreach ($neighbors['out'] as $n) $timeline[] = ['dir'=>'out', 'n'=>$n, 'at'=>$n['occurred_at']];
foreach ($neighbors['in']  as $n) $timeline[] = ['dir'=>'in',  'n'=>$n, 'at'=>$n['occurred_at']];
usort($timeline, function($a,$b){ return strcmp((string)$b['at'], (string)$a['at']); });

$biz_color = [
    'argonar' => '#a78bfa',
    'ocpd'    => '#38bdf8',
    'loan'    => '#fbbf24',
    'alrisha' => '#34d399',
    'global'  => '#94a3b8',
];
function bizc(string $biz): string {
    $c = ['argonar'=>'#a78bfa','ocpd'=>'#38bdf8','loan'=>'#fbbf24','alrisha'=>'#34d399','global'=>'#94a3b8'];
    return $c[$biz] ?? '#94a3b8';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Explorer — Omniscient</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<?php require __DIR__ . '/css.php'; ?>
<style>
.expl-wrap { padding: 1.25rem 1.5rem; max-width: 100%; }
.expl-banner { display:flex; gap:1rem; flex-wrap:wrap; margin-bottom:1rem; }
.expl-tile { background:rgba(255,255,255,0.025); border:1px solid rgba(255,255,255,0.06); border-radius:10px; padding:0.7rem 1rem; min-width:130px; }
.expl-tile .n { font-size:1.4rem; font-weight:800; color:#fff; }
.expl-tile .l { font-size:0.72rem; color:#94a3b8; text-transform:uppercase; letter-spacing:0.08em; }
.expl-search { display:flex; gap:0.5rem; flex-wrap:wrap; margin-bottom:1rem; }
.expl-search input, .expl-search select { background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); color:#fff; padding:0.5rem 0.75rem; border-radius:8px; }
.expl-search input[type=text] { min-width:320px; }
.expl-search button { background:rgba(124,58,237,0.18); border:1px solid rgba(124,58,237,0.4); color:#c4b5fd; padding:0.5rem 1rem; border-radius:8px; font-weight:700; cursor:pointer; }
.expl-grid { display:grid; grid-template-columns: 380px 1fr; gap:1.25rem; }
@media (max-width: 1100px) { .expl-grid { grid-template-columns: 1fr; } }
.expl-list { background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.06); border-radius:10px; padding:0.5rem; max-height:80vh; overflow-y:auto; }
.expl-item { display:block; padding:0.55rem 0.7rem; border-radius:6px; color:#e2e8f0; text-decoration:none; border-bottom:1px solid rgba(255,255,255,0.03); }
.expl-item:hover { background:rgba(255,255,255,0.04); color:#fff; }
.expl-item.active { background:rgba(124,58,237,0.12); color:#fff; }
.expl-item .lbl { font-weight:600; font-size:0.88rem; }
.expl-item .sub { font-size:0.7rem; color:#94a3b8; }
.expl-tag { display:inline-block; font-size:0.62rem; text-transform:uppercase; letter-spacing:0.06em; padding:0.05rem 0.45rem; border-radius:999px; margin-right:0.3rem; font-weight:800; }
.expl-detail { background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.06); border-radius:10px; padding:1.25rem; }
.expl-detail h2 { color:#fff; font-size:1.35rem; margin:0 0 0.35rem 0; }
.expl-detail .ref { font-family: "SFMono-Regular", ui-monospace, monospace; font-size:0.78rem; color:#94a3b8; word-break:break-all; }
.expl-section { margin-top:1.5rem; }
.expl-section h3 { font-size:0.8rem; text-transform:uppercase; letter-spacing:0.08em; color:#94a3b8; font-weight:800; margin-bottom:0.5rem; }
.expl-props { display:grid; grid-template-columns: 160px 1fr; gap:0.35rem 1rem; font-size:0.82rem; }
.expl-props .k { color:#94a3b8; }
.expl-props .v { color:#e2e8f0; word-break:break-word; }
.expl-props .v pre { margin:0; background:rgba(0,0,0,0.2); padding:0.4rem 0.55rem; border-radius:6px; font-size:0.72rem; color:#cbd5e1; max-height:200px; overflow:auto; white-space:pre-wrap; }
.expl-links { display:flex; flex-direction:column; gap:0.3rem; }
.expl-link { display:flex; align-items:center; justify-content:space-between; padding:0.45rem 0.65rem; background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.04); border-radius:6px; font-size:0.82rem; }
.expl-link a { color:#c4b5fd; text-decoration:none; }
.expl-link a:hover { color:#fff; }
.expl-rel { display:inline-block; font-family:monospace; font-size:0.68rem; color:#fbbf24; padding:0.05rem 0.4rem; border:1px solid rgba(251,191,36,0.3); border-radius:4px; margin-right:0.4rem; }
.expl-time { font-size:0.7rem; color:#94a3b8; }
.expl-empty { padding:2rem; text-align:center; color:#64748b; }
.expl-action { background:rgba(52,211,153,0.08); border:1px solid rgba(52,211,153,0.25); border-radius:8px; padding:0.9rem; margin-top:0.8rem; }
.expl-action select, .expl-action input { background:rgba(0,0,0,0.2); border:1px solid rgba(255,255,255,0.08); color:#fff; padding:0.4rem 0.55rem; border-radius:6px; font-size:0.82rem; margin-right:0.4rem; }
.expl-action button { background:rgba(52,211,153,0.25); border:1px solid rgba(52,211,153,0.5); color:#6ee7b7; padding:0.4rem 0.9rem; border-radius:6px; font-weight:700; cursor:pointer; }
.expl-audit { background:rgba(0,0,0,0.15); border-radius:6px; padding:0.4rem 0.6rem; font-size:0.78rem; color:#cbd5e1; margin-bottom:0.3rem; font-family:monospace; }
.expl-audit .when { color:#64748b; }
.expl-audit .who { color:#fbbf24; }
.expl-audit .atype { color:#6ee7b7; }
</style>
</head>
<body>
<div class="omni-layout">
<?php require __DIR__ . '/sidebar.php'; ?>
<div class="omni-main">
<div class="expl-wrap">

  <div style="display:flex;align-items:center;gap:0.8rem;margin-bottom:0.6rem;">
    <h1 style="margin:0;color:#fff;font-size:1.5rem;">◈ Explorer <span style="color:#94a3b8;font-size:0.9rem;font-weight:400;">· the ontology</span></h1>
    <button type="button" onclick="document.getElementById('expl-manual').style.display = document.getElementById('expl-manual').style.display === 'none' ? 'block' : 'none';" style="margin-left:auto;background:rgba(124,58,237,0.12);color:#c4b5fd;border:1px solid rgba(124,58,237,0.3);font-size:0.8rem;padding:0.3rem 0.7rem;border-radius:6px;cursor:pointer;">
      <i class="bi bi-book"></i> How to use
    </button>
    <a href="pipelines/run.php?k=argonar-omni-2026" target="_blank" style="color:#6ee7b7;text-decoration:none;font-size:0.8rem;border:1px solid rgba(52,211,153,0.3);padding:0.3rem 0.7rem;border-radius:6px;">Run pipelines ↗</a>
  </div>

  <!-- Manual / usage guide (collapsed by default) -->
  <div id="expl-manual" style="display:none; background:rgba(124,58,237,0.04); border:1px solid rgba(124,58,237,0.2); border-radius:10px; padding:1.1rem 1.3rem; margin-bottom:1.1rem; font-size:0.86rem; color:#e2e8f0; line-height:1.6;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.6rem;">
      <h3 style="margin:0;color:#c4b5fd;font-size:0.95rem;">Explorer manual — how this surface works</h3>
      <button type="button" onclick="document.getElementById('expl-manual').style.display='none';" style="background:none;border:none;color:#94a3b8;font-size:1.2rem;cursor:pointer;padding:0;line-height:1;">&times;</button>
    </div>

    <p style="margin:0 0 0.8rem 0;"><b style="color:#c4b5fd;">What this is.</b> A single surface over the ontology — every Person, Transaction, Team, Loan, Decision, Booking, Listing across all four businesses (Argonar · OCPD · Loan · Alrisha) is a typed <em>object</em>, and every relationship between them is a typed <em>link</em>. You search the graph, not the tables.</p>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:0.8rem 1.4rem;margin-bottom:0.8rem;">
      <div>
        <div style="color:#fbbf24;font-weight:800;font-size:0.78rem;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0.3rem;">① Totals banner</div>
        Top row shows ontology size. <b>Click any type tile</b> (Person, Loan, Team…) to browse just that type.
      </div>
      <div>
        <div style="color:#fbbf24;font-weight:800;font-size:0.78rem;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0.3rem;">② Search bar</div>
        Free text matches label + ref. Narrow with <b>type</b> + <b>business</b> filters. Example: <code style="font-family:monospace;color:#6ee7b7;">q=kierl · type=Person · biz=argonar</code>.
      </div>
      <div>
        <div style="color:#fbbf24;font-weight:800;font-size:0.78rem;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0.3rem;">③ Results list</div>
        Left column. Each row shows business badge + type badge + label. Click to open detail on the right.
      </div>
      <div>
        <div style="color:#fbbf24;font-weight:800;font-size:0.78rem;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0.3rem;">④ Detail view</div>
        Right column. Properties, outbound links (→), inbound links (←), actions, and a merged timeline. <b>Click any linked object</b> to pivot to it.
      </div>
    </div>

    <div style="background:rgba(0,0,0,0.15);border-radius:6px;padding:0.7rem 0.9rem;margin-bottom:0.8rem;">
      <div style="color:#6ee7b7;font-weight:800;font-size:0.78rem;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0.3rem;">What the relations mean</div>
      <div style="font-size:0.8rem;color:#cbd5e1;">
        <code style="color:#fbbf24;">PAID / RECEIVED</code> — HC/PHP money flow ·
        <code style="color:#fbbf24;">BORROWED / REPAID</code> — loan life cycle ·
        <code style="color:#fbbf24;">CAPTAIN_OF</code> — explicit team ownership ·
        <code style="color:#fbbf24;">PARTICIPATED</code> — Person on a Team/Match/session ·
        <code style="color:#fbbf24;">BOOKED</code> — Person → OCPD Booking (cross-business join) ·
        <code style="color:#fbbf24;">SOLD / BOUGHT</code> — marketplace activity ·
        <code style="color:#fbbf24;">BELONGS_TO</code> — object → Business (scope) ·
        <code style="color:#fbbf24;">IMPACTS</code> — Decision → affected object (from BR-XXXX notes) ·
        <code style="color:#fbbf24;">CONVERTED_TO</code> — social asset → real Asset (harvest path, BR-0009).
      </div>
    </div>

    <div style="background:rgba(52,211,153,0.06);border:1px solid rgba(52,211,153,0.2);border-radius:6px;padding:0.7rem 0.9rem;margin-bottom:0.8rem;">
      <div style="color:#6ee7b7;font-weight:800;font-size:0.78rem;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0.3rem;">Recipes — questions to answer by clicking around</div>
      <ul style="margin:0; padding-left:1.2rem; color:#cbd5e1; font-size:0.8rem;">
        <li><b>"Who captains which teams?"</b> — filter <em>type=Team</em>, open any team, follow the <code>← CAPTAIN_OF</code> link backward to the captain Person.</li>
        <li><b>"Which Argonar users also book paragliding?"</b> — filter <em>type=Person · biz=argonar</em>, open a person, look for <code>→ BOOKED</code> edges. Cross-business overlap = harvest candidate.</li>
        <li><b>"Who has defaulted loans?"</b> — filter <em>type=Loan</em>, sort by timestamp, open rows where status != active. The captain/borrower Person is one hop away via <code>← BORROWED</code>.</li>
        <li><b>"What decisions touch this person?"</b> — open the Person, scroll to inbound; any <code>IMPACTS</code> edge from a Decision (BR-XXXX) appears there.</li>
        <li><b>"Is this marketplace listing sold, and to whom?"</b> — filter <em>type=Listing</em>, open it, check for outbound <code>→</code> + inbound <code>← BOUGHT</code>.</li>
      </ul>
    </div>

    <div style="background:rgba(251,191,36,0.05);border:1px solid rgba(251,191,36,0.18);border-radius:6px;padding:0.7rem 0.9rem;margin-bottom:0.8rem;">
      <div style="color:#fbbf24;font-weight:800;font-size:0.78rem;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0.3rem;">Actions — proposing writes</div>
      On any object detail, an Actions section lets you record a proposed change: credit HC, approve a team, extend a loan, close a decision, or (special) <em>propose a social→real conversion</em> for BR-0009 harvest tracking. <b>Everything you submit here lands as status=proposed in <code>omni_actions</code></b> — a clean audit trail. Actual execution at source tables ships in Phase 4 (Simulation).
    </div>

    <div style="background:rgba(56,189,248,0.05);border:1px solid rgba(56,189,248,0.18);border-radius:6px;padding:0.7rem 0.9rem;">
      <div style="color:#38bdf8;font-weight:800;font-size:0.78rem;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0.3rem;">Keeping it fresh</div>
      Pipelines run every 15 min via cron. To refresh manually, hit <b>Run pipelines ↗</b> (top right) — it re-reads every source table and upserts the graph. New data ≤ 15 min old is normal.
    </div>
  </div>

  <!-- Totals banner -->
  <div class="expl-banner">
    <div class="expl-tile"><div class="n"><?= number_format($totals['objects']) ?></div><div class="l">Objects</div></div>
    <div class="expl-tile"><div class="n"><?= number_format($totals['links']) ?></div><div class="l">Links</div></div>
    <div class="expl-tile"><div class="n"><?= number_format($totals['actions']) ?></div><div class="l">Actions</div></div>
    <div class="expl-tile"><div class="n"><?= number_format($totals['businesses']) ?></div><div class="l">Businesses</div></div>
    <?php foreach ($totals['by_type'] as $t => $c): ?>
      <a href="?type=<?= urlencode($t) ?>" style="text-decoration:none;">
        <div class="expl-tile" style="cursor:pointer;">
          <div class="n" style="font-size:1.1rem;"><?= number_format($c) ?></div>
          <div class="l"><?= htmlspecialchars($t) ?></div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- Search -->
  <form method="get" class="expl-search">
    <input type="text" name="q" placeholder="Search name, ref, anything…" value="<?= htmlspecialchars($q) ?>">
    <select name="type">
      <option value="">All types</option>
      <?php foreach (OMNI_TYPES as $t): ?>
      <option value="<?= htmlspecialchars($t) ?>" <?= $type===$t?'selected':'' ?>><?= htmlspecialchars($t) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="biz">
      <option value="">All businesses</option>
      <?php foreach (['argonar','ocpd','loan','alrisha','global'] as $b): ?>
      <option value="<?= $b ?>" <?= $biz===$b?'selected':'' ?>><?= ucfirst($b) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit">Search</button>
    <?php if ($q || $type || $biz): ?>
      <a href="explore.php" style="color:#94a3b8;padding:0.5rem 0.7rem;">Clear</a>
    <?php endif; ?>
  </form>

  <div class="expl-grid">

    <!-- Results list -->
    <div class="expl-list">
      <?php if (empty($results)): ?>
        <div class="expl-empty">No objects. Run pipelines first.</div>
      <?php endif; ?>
      <?php foreach ($results as $r): ?>
        <a class="expl-item <?= $sel_id==$r['id']?'active':'' ?>" href="?id=<?= (int)$r['id'] ?>&q=<?= urlencode($q) ?>&type=<?= urlencode($type) ?>&biz=<?= urlencode($biz) ?>">
          <div class="lbl">
            <span class="expl-tag" style="background:<?= bizc($r['business']) ?>22;color:<?= bizc($r['business']) ?>;"><?= htmlspecialchars($r['business']) ?></span>
            <span class="expl-tag" style="background:rgba(124,58,237,0.15);color:#c4b5fd;"><?= htmlspecialchars($r['type']) ?></span>
            <?= htmlspecialchars($r['label'] ?: $r['ref']) ?>
          </div>
          <div class="sub"><?= htmlspecialchars($r['ref']) ?> · <?= htmlspecialchars($r['updated_at']) ?></div>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- Detail -->
    <div class="expl-detail">
      <?php if (!$selected): ?>
        <div class="expl-empty">
          <div style="font-size:2.4rem;margin-bottom:0.4rem;">◈</div>
          <div style="font-size:1rem;color:#94a3b8;">Select an object or search to inspect.</div>
        </div>
      <?php else: ?>
        <?php if (!empty($_GET['a']) && $_GET['a']==='ok'): ?>
          <div style="background:rgba(52,211,153,0.15);border:1px solid rgba(52,211,153,0.3);color:#6ee7b7;padding:0.5rem 0.8rem;border-radius:6px;margin-bottom:1rem;font-size:0.85rem;">Action recorded.</div>
        <?php endif; ?>

        <div style="display:flex;align-items:flex-start;gap:1rem;flex-wrap:wrap;">
          <div style="flex:1;min-width:280px;">
            <div>
              <span class="expl-tag" style="background:<?= bizc($selected['business']) ?>22;color:<?= bizc($selected['business']) ?>;"><?= htmlspecialchars($selected['business']) ?></span>
              <span class="expl-tag" style="background:rgba(124,58,237,0.15);color:#c4b5fd;"><?= htmlspecialchars($selected['type']) ?></span>
            </div>
            <h2><?= htmlspecialchars($selected['label'] ?: $selected['ref']) ?></h2>
            <div class="ref"><?= htmlspecialchars($selected['ref']) ?></div>
            <div style="font-size:0.75rem;color:#64748b;margin-top:0.3rem;">
              Source: <?= htmlspecialchars($selected['source_table']) ?>#<?= htmlspecialchars($selected['source_id']) ?>
              · Updated <?= htmlspecialchars($selected['updated_at']) ?>
            </div>
          </div>
          <div style="font-size:0.72rem;color:#64748b;text-align:right;">
            in-links: <b style="color:#cbd5e1;"><?= count($neighbors['in']) ?></b><br>
            out-links: <b style="color:#cbd5e1;"><?= count($neighbors['out']) ?></b>
          </div>
        </div>

        <!-- Properties -->
        <div class="expl-section">
          <h3>Properties</h3>
          <?php if (empty($props_decoded)): ?>
            <div style="color:#64748b;font-size:0.8rem;">—</div>
          <?php else: ?>
            <div class="expl-props">
              <?php foreach ($props_decoded as $k => $v): ?>
                <div class="k"><?= htmlspecialchars((string)$k) ?></div>
                <div class="v">
                  <?php if (is_array($v) || is_object($v)): ?>
                    <pre><?= htmlspecialchars(json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                  <?php else: ?>
                    <?= htmlspecialchars((string)$v) ?>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Outbound -->
        <div class="expl-section">
          <h3>→ Outbound (<?= count($neighbors['out']) ?>)</h3>
          <div class="expl-links">
            <?php if (empty($neighbors['out'])): ?>
              <div style="color:#64748b;font-size:0.8rem;">—</div>
            <?php endif; ?>
            <?php foreach ($neighbors['out'] as $n): ?>
              <div class="expl-link">
                <div>
                  <span class="expl-rel"><?= htmlspecialchars($n['relation']) ?></span>
                  <span class="expl-tag" style="background:<?= bizc($n['business']) ?>22;color:<?= bizc($n['business']) ?>;"><?= htmlspecialchars($n['type']) ?></span>
                  <a href="?id=<?= (int)$n['id'] ?>"><?= htmlspecialchars($n['label'] ?: $n['ref']) ?></a>
                </div>
                <span class="expl-time"><?= htmlspecialchars($n['occurred_at'] ?? '') ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Inbound -->
        <div class="expl-section">
          <h3>← Inbound (<?= count($neighbors['in']) ?>)</h3>
          <div class="expl-links">
            <?php if (empty($neighbors['in'])): ?>
              <div style="color:#64748b;font-size:0.8rem;">—</div>
            <?php endif; ?>
            <?php foreach ($neighbors['in'] as $n): ?>
              <div class="expl-link">
                <div>
                  <span class="expl-tag" style="background:<?= bizc($n['business']) ?>22;color:<?= bizc($n['business']) ?>;"><?= htmlspecialchars($n['type']) ?></span>
                  <a href="?id=<?= (int)$n['id'] ?>"><?= htmlspecialchars($n['label'] ?: $n['ref']) ?></a>
                  <span class="expl-rel" style="color:#38bdf8;border-color:rgba(56,189,248,0.3);"><?= htmlspecialchars($n['relation']) ?></span>
                </div>
                <span class="expl-time"><?= htmlspecialchars($n['occurred_at'] ?? '') ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Actions -->
        <div class="expl-section">
          <h3>Actions (proposed → audited)</h3>
          <form method="post" class="expl-action">
            <input type="hidden" name="object_id" value="<?= (int)$selected['id'] ?>">
            <select name="action_type" required>
              <?php foreach ($available_actions as $a): ?>
                <option value="<?= htmlspecialchars($a[0]) ?>"><?= htmlspecialchars($a[1]) ?> <?= $a[2]?'('.$a[2].')':'' ?></option>
              <?php endforeach; ?>
            </select>
            <input type="text" name="payload" placeholder='{"amount": 20, "reason": "reward"}' style="min-width:320px;">
            <button type="submit">Record action</button>
            <div style="margin-top:0.5rem;font-size:0.72rem;color:#94a3b8;">
              Actions are recorded with status=proposed. Execution layer ships in Phase 4 (Simulation). For now this is the audit trail.
            </div>
          </form>

          <?php if (!empty($actions_recent)): ?>
            <div style="margin-top:0.8rem;">
              <?php foreach ($actions_recent as $a): ?>
                <div class="expl-audit">
                  <span class="when"><?= htmlspecialchars($a['performed_at']) ?></span>
                  · <span class="who"><?= htmlspecialchars($a['performed_by']) ?></span>
                  · <span class="atype"><?= htmlspecialchars($a['action_type']) ?></span>
                  · <span style="color:#94a3b8;"><?= htmlspecialchars($a['status']) ?></span>
                  <?php if (!empty($a['payload']) && $a['payload'] !== '[]'): ?>
                    <div style="color:#64748b;margin-top:0.15rem;"><?= htmlspecialchars($a['payload']) ?></div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Timeline -->
        <?php if (!empty($timeline)): ?>
        <div class="expl-section">
          <h3>Timeline (<?= count($timeline) ?>)</h3>
          <div class="expl-links">
            <?php foreach (array_slice($timeline, 0, 40) as $t): $n = $t['n']; ?>
              <div class="expl-link">
                <div>
                  <span style="color:<?= $t['dir']==='out'?'#fbbf24':'#38bdf8' ?>;font-family:monospace;font-size:0.75rem;"><?= $t['dir']==='out'?'→':'←' ?></span>
                  <span class="expl-rel"><?= htmlspecialchars($n['relation']) ?></span>
                  <a href="?id=<?= (int)$n['id'] ?>"><?= htmlspecialchars($n['label'] ?: $n['ref']) ?></a>
                </div>
                <span class="expl-time"><?= htmlspecialchars($t['at'] ?? '') ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

      <?php endif; ?>
    </div>
  </div>
</div>
</div>
</div>
</body>
</html>
