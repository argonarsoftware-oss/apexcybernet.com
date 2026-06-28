<?php
/**
 * admin/omni/ask.php — templated insights + pattern builder.
 *
 * Phase 5 without the Claude API. Each "question" is a pre-written SQL
 * over the ontology that produces an answer with evidence. When the LLM
 * layer ships, it will route natural-language questions to these same
 * templates (plus the pattern builder) as tools — the UI stays stable.
 */

$active_site = 'omni';
$page_file   = 'omni/ask.php';

require_once __DIR__ . '/../../includes/db.php';
$apexcybernet_pdo = $pdo;
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/pipelines/taxonomy.php';

// ── Sidebar stats ──
$sidebar_stats = ['apexcybernet'=>['sessions'=>0,'live'=>0],'ocpd'=>['sessions'=>0,'live'=>0],'loan'=>['sessions'=>0,'live'=>0],'alrisha'=>['sessions'=>0,'live'=>0]];
try {
    $rows_sb = $apexcybernet_pdo->query("SELECT CASE WHEN site IS NULL OR site='' THEN 'apexcybernet' ELSE site END as s,
        COUNT(DISTINCT session_id) as n FROM activity_logs WHERE created_at >= CURDATE() GROUP BY s")->fetchAll();
    foreach ($rows_sb as $r) if (isset($sidebar_stats[$r['s']])) $sidebar_stats[$r['s']]['sessions'] = (int)$r['n'];
    $rows_sb = $apexcybernet_pdo->query("SELECT CASE WHEN site IS NULL OR site='' THEN 'apexcybernet' ELSE site END as s,
        COUNT(DISTINCT session_id) as n FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) GROUP BY s")->fetchAll();
    foreach ($rows_sb as $r) if (isset($sidebar_stats[$r['s']])) $sidebar_stats[$r['s']]['live'] = (int)$r['n'];
} catch (Exception $e) {}
$date_range = $_GET['dr'] ?? 'today';

// ── Insight templates ──
$TEMPLATES = [
    'cross_business' => [
        'title' => 'Cross-business Persons',
        'tags'  => ['overlap', 'harvest', 'persons'],
        'why'   => 'Users who appear across ≥2 businesses — the most valuable accounts. Root of every harvest move.',
        'sql'   => "SELECT p.id, p.ref, p.label, p.business,
                           GROUP_CONCAT(DISTINCT b.business ORDER BY b.business SEPARATOR ', ') AS businesses,
                           COUNT(DISTINCT b.business) AS biz_count
                    FROM omni_objects p
                    JOIN omni_links l ON l.from_id = p.id AND l.relation = 'BELONGS_TO'
                    JOIN omni_objects b ON b.id = l.to_id AND b.type = 'Business'
                    WHERE p.type = 'Person'
                    GROUP BY p.id, p.ref, p.label, p.business
                    HAVING biz_count >= 2
                    ORDER BY biz_count DESC, p.label
                    LIMIT 200",
        'cols'  => ['label','businesses','biz_count'],
    ],
    'captains' => [
        'title' => 'Team captains',
        'tags'  => ['teams', 'authority'],
        'why'   => 'Every Person with explicit CAPTAIN_OF links — the authoritative roster of who controls which team.',
        'sql'   => "SELECT p.label AS captain, p.ref,
                           GROUP_CONCAT(t.label ORDER BY t.label SEPARATOR ' · ') AS teams,
                           COUNT(*) AS team_count
                    FROM omni_links l
                    JOIN omni_objects p ON p.id = l.from_id AND p.type = 'Person'
                    JOIN omni_objects t ON t.id = l.to_id   AND t.type = 'Team'
                    WHERE l.relation = 'CAPTAIN_OF'
                    GROUP BY p.id, p.label, p.ref
                    ORDER BY team_count DESC, p.label
                    LIMIT 100",
        'cols'  => ['captain','teams','team_count'],
    ],
    'harvest_candidates' => [
        'title' => 'BR-0009 harvest candidates',
        'tags'  => ['harvest', 'br-0009', 'conversion'],
        'why'   => 'Persons with both paragliding BOOKINGS and Apex Cybernet participation (tournament team or event). Thick cross-biz relationships = right targets to convert social goodwill into real-asset line items.',
        'sql'   => "SELECT p.id, p.ref, p.label,
                           SUM(l.relation='BOOKED')       AS bookings,
                           SUM(l.relation='PARTICIPATED') AS participations,
                           SUM(l.relation='CAPTAIN_OF')   AS captaincies
                    FROM omni_objects p
                    JOIN omni_links l ON l.from_id = p.id
                    WHERE p.type='Person'
                      AND l.relation IN ('BOOKED','PARTICIPATED','CAPTAIN_OF')
                    GROUP BY p.id, p.ref, p.label
                    HAVING bookings > 0 AND (participations > 0 OR captaincies > 0)
                    ORDER BY (bookings + participations + captaincies) DESC
                    LIMIT 100",
        'cols'  => ['label','bookings','participations','captaincies'],
    ],
    'top_hc' => [
        'title' => 'Top HC holders',
        'tags'  => ['wallet', 'whales'],
        'why'   => 'Apex Cybernet Persons ordered by current HC balance. Top of this list holds real purchasing leverage.',
        'sql'   => "SELECT p.id, p.ref, p.label,
                           CAST(JSON_UNQUOTE(JSON_EXTRACT(p.props, '$.h_coins')) AS DECIMAL(20,2)) AS h_coins
                    FROM omni_objects p
                    WHERE p.type='Person' AND p.business='apexcybernet'
                      AND JSON_EXTRACT(p.props, '$.h_coins') IS NOT NULL
                    ORDER BY h_coins DESC
                    LIMIT 50",
        'cols'  => ['label','h_coins'],
    ],
    'dormant_persons' => [
        'title' => 'Dormant apexcybernet Persons (no Event in 7 days)',
        'tags'  => ['retention', 'winback'],
        'why'   => 'Approved apexcybernet accounts with no session activity in the last 7 days. Winback-campaign targets.',
        'sql'   => "SELECT p.id, p.ref, p.label,
                           CAST(JSON_UNQUOTE(JSON_EXTRACT(p.props, '$.h_coins')) AS DECIMAL(20,2)) AS h_coins,
                           JSON_UNQUOTE(JSON_EXTRACT(p.props, '$.claim_status')) AS claim_status
                    FROM omni_objects p
                    WHERE p.type='Person' AND p.business='apexcybernet'
                      AND JSON_UNQUOTE(JSON_EXTRACT(p.props, '$.claim_status')) = 'approved'
                      AND NOT EXISTS (
                          SELECT 1 FROM omni_links l
                          JOIN omni_objects e ON e.id = l.to_id AND e.type='Event'
                          WHERE l.from_id = p.id
                            AND l.relation='PARTICIPATED'
                            AND l.occurred_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                      )
                    ORDER BY h_coins DESC
                    LIMIT 100",
        'cols'  => ['label','h_coins','claim_status'],
    ],
    'loan_apexcybernet_overlap' => [
        'title' => 'Borrowers also on Apex Cybernet',
        'tags'  => ['risk', 'overlap', 'collateral'],
        'why'   => 'Borrowers in the lending book who also have an Apex Cybernet account. Cross-book recovery surface — HC balance is potential offset collateral.',
        'sql'   => "SELECT DISTINCT p.label AS apexcybernet_account, p.ref, p2.label AS loan_account, p2.ref AS loan_ref
                    FROM omni_objects p
                    JOIN omni_links l1 ON l1.from_id = p.id AND l1.relation='BELONGS_TO'
                    JOIN omni_objects b1 ON b1.id = l1.to_id AND b1.type='Business' AND b1.business='apexcybernet'
                    JOIN omni_objects p2 ON LOWER(p2.label) = LOWER(p.label) AND p2.type='Person' AND p2.business='loan'
                    WHERE p.type='Person' AND p.business='apexcybernet'
                    ORDER BY p.label
                    LIMIT 100",
        'cols'  => ['apexcybernet_account','loan_account'],
    ],
    'loans_by_status' => [
        'title' => 'Loans by status',
        'tags'  => ['lending', 'portfolio'],
        'why'   => 'Distribution of the loan book by status. Tracks default / active / closed breakdown at a glance.',
        'sql'   => "SELECT JSON_UNQUOTE(JSON_EXTRACT(props,'$.status')) AS status,
                           COUNT(*) AS loans,
                           SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(props,'$.principal_amount')) AS DECIMAL(20,2))) AS principal
                    FROM omni_objects WHERE type='Loan'
                    GROUP BY status
                    ORDER BY loans DESC",
        'cols'  => ['status','loans','principal'],
    ],
    'recent_decisions' => [
        'title' => 'Recent decisions (BR-XXXX)',
        'tags'  => ['brain', 'strategy'],
        'why'   => 'Latest entries in the brain notebook. Source of truth for strategic direction across businesses.',
        'sql'   => "SELECT label, JSON_UNQUOTE(JSON_EXTRACT(props,'$.br')) AS br,
                           business,
                           JSON_UNQUOTE(JSON_EXTRACT(props,'$.outcome')) AS outcome,
                           updated_at
                    FROM omni_objects WHERE type='Decision'
                    ORDER BY updated_at DESC LIMIT 20",
        'cols'  => ['br','label','business','outcome','updated_at'],
    ],
    'recruiting_teams' => [
        'title' => 'Teams open to recruits',
        'tags'  => ['teams', 'recruiting'],
        'why'   => 'Every team whose captain has flipped the recruiting toggle on. Direct feed for the roster-building UX.',
        'sql'   => "SELECT label, JSON_UNQUOTE(JSON_EXTRACT(props,'$.game')) AS game,
                           JSON_UNQUOTE(JSON_EXTRACT(props,'$.ref_code')) AS ref_code,
                           JSON_UNQUOTE(JSON_EXTRACT(props,'$.status')) AS status
                    FROM omni_objects
                    WHERE type='Team' AND JSON_EXTRACT(props,'$.recruiting') = TRUE
                    ORDER BY updated_at DESC LIMIT 50",
        'cols'  => ['label','game','ref_code','status'],
    ],
    'multi_booking' => [
        'title' => 'Repeat bookers (OCPD)',
        'tags'  => ['loyalty', 'ocpd'],
        'why'   => 'Persons with ≥2 BOOKED links — paragliding customers who come back. Natural upsell targets for cafe/community.',
        'sql'   => "SELECT p.id, p.label, COUNT(*) AS bookings
                    FROM omni_objects p
                    JOIN omni_links l ON l.from_id = p.id AND l.relation='BOOKED'
                    WHERE p.type='Person'
                    GROUP BY p.id, p.label
                    HAVING bookings >= 2
                    ORDER BY bookings DESC
                    LIMIT 100",
        'cols'  => ['label','bookings'],
    ],
    'unclaimed_teams' => [
        'title' => 'Unclaimed teams (no captain linked)',
        'tags'  => ['data-quality', 'captaincy'],
        'why'   => 'Teams where captain_account_id is still NULL — the captain has not claimed them yet (or the account was never created). Candidates to nudge.',
        'sql'   => "SELECT label, JSON_UNQUOTE(JSON_EXTRACT(props,'$.ref_code')) AS ref_code,
                           JSON_UNQUOTE(JSON_EXTRACT(props,'$.status')) AS status,
                           JSON_UNQUOTE(JSON_EXTRACT(props,'$.members[0]')) AS captain_name
                    FROM omni_objects
                    WHERE type='Team'
                      AND (JSON_EXTRACT(props,'$.captain_account_id') IS NULL
                           OR JSON_EXTRACT(props,'$.captain_account_id') = CAST('null' AS JSON))
                    ORDER BY updated_at DESC LIMIT 50",
        'cols'  => ['label','captain_name','ref_code','status'],
    ],
];

// ── Run template or pattern ──
$mode = $_GET['mode'] ?? '';
$tpl_id = $_GET['t'] ?? '';
$results = null; $cols = []; $title = ''; $sql_used = ''; $err = null;

if ($mode === 'template' && isset($TEMPLATES[$tpl_id])) {
    $tpl = $TEMPLATES[$tpl_id];
    $title = $tpl['title'];
    $sql_used = preg_replace('/\s+/', ' ', trim($tpl['sql']));
    try {
        $results = $apexcybernet_pdo->query($tpl['sql'])->fetchAll(PDO::FETCH_ASSOC);
        $cols = $tpl['cols'];
    } catch (Exception $e) { $err = $e->getMessage(); }
}

if ($mode === 'pattern') {
    $type       = trim((string)($_GET['type']  ?? ''));
    $biz        = trim((string)($_GET['biz']   ?? ''));
    $label_like = trim((string)($_GET['label'] ?? ''));
    $limit      = min(max((int)($_GET['limit']?? 100), 1), 500);

    $where = []; $binds = [];
    if ($type !== '')       { $where[] = 'type = :t'; $binds[':t'] = $type; }
    if ($biz !== '')        { $where[] = 'business = :b'; $binds[':b'] = $biz; }
    if ($label_like !== '') { $where[] = 'label LIKE :l'; $binds[':l'] = '%' . $label_like . '%'; }

    $sql = "SELECT id, ref, type, business, label, updated_at FROM omni_objects"
         . (!empty($where) ? ' WHERE ' . implode(' AND ', $where) : '')
         . " ORDER BY updated_at DESC LIMIT $limit";
    $title = 'Pattern browse';
    $sql_used = $sql;
    try {
        $st = $apexcybernet_pdo->prepare($sql);
        foreach ($binds as $k=>$v) $st->bindValue($k, $v);
        $st->execute();
        $results = $st->fetchAll(PDO::FETCH_ASSOC);
        $cols = ['business','type','label','updated_at'];
    } catch (Exception $e) { $err = $e->getMessage(); }
}

function bizc3(string $biz): string {
    $c = ['apexcybernet'=>'#a78bfa','ocpd'=>'#38bdf8','loan'=>'#fbbf24','alrisha'=>'#34d399','global'=>'#94a3b8'];
    return $c[$biz] ?? '#94a3b8';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ask — Omniscient</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<?php require __DIR__ . '/css.php'; ?>
<style>
.ask-wrap { padding: 1.25rem 1.5rem; }
.ask-head { display:flex; align-items:center; gap:0.8rem; margin-bottom:1rem; }
.ask-head h1 { margin:0; color:#fff; font-size:1.5rem; }
.ask-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap:0.9rem; margin-bottom:1.4rem; }
.ask-card { background:rgba(255,255,255,0.025); border:1px solid rgba(255,255,255,0.06); border-radius:10px; padding:1rem 1.1rem; display:flex; flex-direction:column; }
.ask-card h3 { color:#c4b5fd; font-size:0.95rem; margin:0 0 0.35rem 0; font-weight:800; }
.ask-card .why { font-size:0.8rem; color:#94a3b8; line-height:1.5; margin-bottom:0.6rem; flex:1; }
.ask-card .tags { display:flex; gap:0.3rem; flex-wrap:wrap; margin-bottom:0.7rem; }
.ask-card .tag { font-size:0.62rem; letter-spacing:0.06em; text-transform:uppercase; padding:0.1rem 0.45rem; border-radius:999px; background:rgba(124,58,237,0.1); color:#c4b5fd; font-weight:800; }
.ask-card .run { background:rgba(52,211,153,0.15); color:#6ee7b7; border:1px solid rgba(52,211,153,0.35); padding:0.4rem 0.8rem; border-radius:6px; text-decoration:none; font-size:0.82rem; font-weight:700; align-self:flex-start; }
.ask-card .run:hover { background:rgba(52,211,153,0.25); }
.ask-result { background:rgba(255,255,255,0.025); border:1px solid rgba(255,255,255,0.06); border-radius:10px; padding:1.1rem 1.25rem; margin-bottom:1.2rem; }
.ask-result h2 { color:#fff; font-size:1.1rem; margin:0 0 0.7rem 0; }
.ask-sql { background:rgba(0,0,0,0.3); border-radius:6px; padding:0.5rem 0.7rem; color:#cbd5e1; font-family:monospace; font-size:0.72rem; overflow-x:auto; white-space:pre-wrap; margin-bottom:0.7rem; }
.ask-table { width:100%; border-collapse:collapse; font-size:0.83rem; }
.ask-table th { text-align:left; padding:0.4rem 0.6rem; color:#94a3b8; font-size:0.7rem; text-transform:uppercase; letter-spacing:0.06em; border-bottom:1px solid rgba(255,255,255,0.08); }
.ask-table td { padding:0.4rem 0.6rem; color:#e2e8f0; border-bottom:1px solid rgba(255,255,255,0.03); }
.ask-table tr:hover td { background:rgba(255,255,255,0.02); }
.ask-tag { display:inline-block; font-size:0.62rem; text-transform:uppercase; letter-spacing:0.06em; padding:0.05rem 0.45rem; border-radius:999px; font-weight:800; }
.pb-form label { font-size:0.72rem; color:#94a3b8; text-transform:uppercase; letter-spacing:0.06em; font-weight:800; display:block; margin-bottom:0.15rem; }
.pb-form input, .pb-form select { background:rgba(0,0,0,0.25); border:1px solid rgba(255,255,255,0.08); color:#fff; padding:0.45rem 0.6rem; border-radius:6px; font-size:0.82rem; }
.pb-form button { background:rgba(124,58,237,0.2); border:1px solid rgba(124,58,237,0.4); color:#c4b5fd; padding:0.5rem 1rem; border-radius:7px; font-weight:700; cursor:pointer; font-size:0.82rem; }
</style>
</head>
<body>
<div class="omni-layout">
<?php require __DIR__ . '/sidebar.php'; ?>
<div class="omni-main">
<div class="ask-wrap">

  <div class="ask-head">
    <h1>◈ Ask <span style="color:#94a3b8;font-size:0.9rem;font-weight:400;">· templated insights over the ontology</span></h1>
    <a href="explore.php" style="margin-left:auto;color:#c4b5fd;font-size:0.8rem;text-decoration:none;border:1px solid rgba(124,58,237,0.3);padding:0.3rem 0.7rem;border-radius:6px;">Explorer ↗</a>
    <a href="simulate.php" style="color:#6ee7b7;font-size:0.8rem;text-decoration:none;border:1px solid rgba(52,211,153,0.3);padding:0.3rem 0.7rem;border-radius:6px;">Simulate ↗</a>
  </div>

  <!-- Result -->
  <?php if ($results !== null): ?>
    <div class="ask-result">
      <h2><?= htmlspecialchars($title) ?> <span style="color:#94a3b8;font-size:0.85rem;font-weight:400;">· <?= count($results) ?> rows</span></h2>
      <?php if ($err): ?>
        <div style="color:#fca5a5;"><?= htmlspecialchars($err) ?></div>
      <?php elseif (empty($results)): ?>
        <div style="color:#94a3b8;">No matches.</div>
      <?php else: ?>
        <table class="ask-table">
          <thead><tr>
            <?php foreach ($cols as $c): ?><th><?= htmlspecialchars($c) ?></th><?php endforeach; ?>
          </tr></thead>
          <tbody>
            <?php foreach ($results as $r): ?>
              <tr>
                <?php foreach ($cols as $c): ?>
                  <td>
                    <?php $v = $r[$c] ?? ''; if ($c === 'business'): ?>
                      <span class="ask-tag" style="background:<?= bizc3($v) ?>22;color:<?= bizc3($v) ?>;"><?= htmlspecialchars($v) ?></span>
                    <?php elseif (in_array($c, ['label','apexcybernet_account','loan_account','captain','br','teams']) && !empty($r['ref'] ?? $r['loan_ref'] ?? '')): ?>
                      <a href="explore.php?ref=<?= urlencode((string)($r['ref'] ?? $r['loan_ref'])) ?>" style="color:#e2e8f0;text-decoration:none;"><?= htmlspecialchars((string)$v) ?></a>
                    <?php else: ?>
                      <?= htmlspecialchars((string)$v) ?>
                    <?php endif; ?>
                  </td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
      <?php if ($sql_used): ?>
        <details style="margin-top:0.9rem;">
          <summary style="cursor:pointer;color:#94a3b8;font-size:0.74rem;">Show SQL</summary>
          <div class="ask-sql" style="margin-top:0.4rem;"><?= htmlspecialchars($sql_used) ?></div>
        </details>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <!-- Templates -->
  <h2 style="color:#c4b5fd;font-size:0.95rem;text-transform:uppercase;letter-spacing:0.08em;font-weight:800;margin:0 0 0.6rem 0;">Quick questions</h2>
  <div class="ask-grid">
    <?php foreach ($TEMPLATES as $id => $tpl): ?>
      <div class="ask-card">
        <h3><?= htmlspecialchars($tpl['title']) ?></h3>
        <div class="tags">
          <?php foreach ($tpl['tags'] as $tag): ?>
            <span class="tag"><?= htmlspecialchars($tag) ?></span>
          <?php endforeach; ?>
        </div>
        <div class="why"><?= htmlspecialchars($tpl['why']) ?></div>
        <a class="run" href="?mode=template&t=<?= urlencode($id) ?>"><i class="bi bi-play-fill"></i> Run</a>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Pattern builder -->
  <h2 style="color:#c4b5fd;font-size:0.95rem;text-transform:uppercase;letter-spacing:0.08em;font-weight:800;margin:0 0 0.6rem 0;">Pattern builder</h2>
  <div class="ask-result pb-form">
    <form method="get">
      <input type="hidden" name="mode" value="pattern">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:0.6rem;margin-bottom:0.6rem;">
        <div>
          <label>Type</label>
          <select name="type">
            <option value="">— any —</option>
            <?php foreach (OMNI_TYPES as $t): ?>
              <option value="<?= $t ?>" <?= ($_GET['type']??'')===$t?'selected':'' ?>><?= $t ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Business</label>
          <select name="biz">
            <option value="">— any —</option>
            <?php foreach (['apexcybernet','ocpd','loan','alrisha','global'] as $b): ?>
              <option value="<?= $b ?>" <?= ($_GET['biz']??'')===$b?'selected':'' ?>><?= ucfirst($b) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Label contains</label>
          <input type="text" name="label" value="<?= htmlspecialchars($_GET['label'] ?? '') ?>" placeholder="substring">
        </div>
        <div>
          <label>Limit</label>
          <input type="number" name="limit" value="<?= (int)($_GET['limit'] ?? 100) ?>" min="1" max="500">
        </div>
      </div>
      <button type="submit"><i class="bi bi-search"></i> Browse</button>
      <span style="color:#94a3b8;font-size:0.72rem;margin-left:0.6rem;">For deeper queries, clone a template and tweak its SQL — tools/ai.php will auto-use these same templates.</span>
    </form>
  </div>

</div>
</div>
</div>
</body>
</html>
