<?php
/**
 * admin/omni/simulate.php — cohort-based action runner.
 *
 * Flow: build a cohort with filters → preview rows + count → pick an
 * action template + payload → Simulate (dry-run, status=simulated) or
 * Execute (writes at source, status=executed). All attempts land in
 * omni_actions with a complete audit trail.
 */

$active_site = 'omni';
$page_file   = 'omni/simulate.php';

require_once __DIR__ . '/../../includes/db.php';
$apexcybernet_pdo = $pdo;
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/pipelines/taxonomy.php';
require_once __DIR__ . '/executors.php';

// ── Sidebar stats (same shape as other omni pages) ──
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

// ── Cohort filter ──
$type        = trim((string)($_GET['type']    ?? ''));
$biz         = trim((string)($_GET['biz']     ?? ''));
$label_like  = trim((string)($_GET['label']   ?? ''));
$prop_key    = trim((string)($_GET['pk']      ?? ''));
$prop_op     = trim((string)($_GET['po']      ?? '='));
$prop_val    = trim((string)($_GET['pv']      ?? ''));
$rel_dir     = trim((string)($_GET['rd']      ?? '')); // 'out'|'in'|''
$rel_name    = trim((string)($_GET['rn']      ?? ''));
$rel_ttype   = trim((string)($_GET['rt']      ?? ''));
$limit       = min(max((int)($_GET['limit']   ?? 50), 1), 500);

$where = [];
$binds = [];
if ($type !== '')  { $where[] = 'o.type = :type';         $binds[':type'] = $type; }
if ($biz !== '')   { $where[] = 'o.business = :biz';      $binds[':biz']  = $biz; }
if ($label_like!== '') { $where[] = 'o.label LIKE :lbl';  $binds[':lbl']  = '%' . $label_like . '%'; }
if ($prop_key !== '' && $prop_val !== '') {
    $ops_num = ['=','!=','>','>=','<','<='];
    $op = in_array($prop_op, $ops_num, true) ? $prop_op : '=';
    if (is_numeric($prop_val)) {
        $where[] = "CAST(JSON_UNQUOTE(JSON_EXTRACT(o.props, :pkk)) AS DECIMAL(20,6)) $op :pv";
    } else {
        $where[] = "JSON_UNQUOTE(JSON_EXTRACT(o.props, :pkk)) $op :pv";
    }
    $binds[':pkk'] = '$."' . str_replace('"', '\\"', $prop_key) . '"';
    $binds[':pv']  = $prop_val;
}
if ($rel_dir !== '' && $rel_name !== '') {
    $sub = ($rel_dir === 'out')
        ? "EXISTS (SELECT 1 FROM omni_links l JOIN omni_objects o2 ON o2.id = l.to_id WHERE l.from_id = o.id AND l.relation = :rn" . ($rel_ttype!=='' ? ' AND o2.type = :rt':'') . ")"
        : "EXISTS (SELECT 1 FROM omni_links l JOIN omni_objects o2 ON o2.id = l.from_id WHERE l.to_id = o.id AND l.relation = :rn" . ($rel_ttype!=='' ? ' AND o2.type = :rt':'') . ")";
    $where[] = $sub;
    $binds[':rn'] = $rel_name;
    if ($rel_ttype !== '') $binds[':rt'] = $rel_ttype;
}

$cohort = [];
$cohort_count = 0;
if (!empty($where)) {
    $sql = "SELECT o.id, o.ref, o.type, o.business, o.label, o.props, o.source_table, o.source_id
            FROM omni_objects o
            WHERE " . implode(' AND ', $where) . "
            ORDER BY o.updated_at DESC
            LIMIT $limit";
    $sql_count = "SELECT COUNT(*) FROM omni_objects o WHERE " . implode(' AND ', $where);
    try {
        $st = $apexcybernet_pdo->prepare($sql);
        foreach ($binds as $k=>$v) $st->bindValue($k,$v);
        $st->execute();
        $cohort = $st->fetchAll(PDO::FETCH_ASSOC);

        $stc = $apexcybernet_pdo->prepare($sql_count);
        foreach ($binds as $k=>$v) $stc->bindValue($k,$v);
        $stc->execute();
        $cohort_count = (int)$stc->fetchColumn();
    } catch (Exception $e) {
        $cohort_err = $e->getMessage();
    }
}

// ── POST: run action against cohort ──
$run_results = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action_type']) && !empty($_POST['cohort_ids'])) {
    $mode = ($_POST['mode'] ?? 'simulate') === 'execute' ? 'execute' : 'simulate';
    $action_type = trim((string)$_POST['action_type']);
    $payload_raw = trim((string)($_POST['payload'] ?? '{}'));
    $payload = json_decode($payload_raw, true) ?: [];
    $ids = array_filter(array_map('intval', explode(',', (string)$_POST['cohort_ids'])));
    if (!empty($ids)) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $st = $apexcybernet_pdo->prepare("SELECT * FROM omni_objects WHERE id IN ($ph)");
        $st->execute($ids);
        $targets = $st->fetchAll(PDO::FETCH_ASSOC);

        $ok_count = 0; $err_count = 0; $per_row = [];
        foreach ($targets as $obj) {
            $res = ($mode === 'execute')
                ? omni_execute($apexcybernet_pdo, $obj, $action_type, $payload)
                : omni_simulate($apexcybernet_pdo, $obj, $action_type, $payload);
            if (!empty($res['ok'])) $ok_count++; else $err_count++;
            $per_row[] = [
                'object_id'=>$obj['id'],
                'label'=>$obj['label'],
                'ref'=>$obj['ref'],
                'status'=>$res['status'] ?? '',
                'ok'=>!empty($res['ok']),
                'message'=>$res['message'] ?? '',
            ];
        }
        $run_results = [
            'mode'=>$mode, 'action_type'=>$action_type, 'payload'=>$payload,
            'ok'=>$ok_count, 'err'=>$err_count, 'rows'=>$per_row, 'total'=>count($targets),
        ];
    }
}

// ── Recent run history ──
$recent = [];
try {
    $recent = $apexcybernet_pdo->query(
        "SELECT a.id, a.action_type, a.status, a.performed_by, a.performed_at, a.payload, o.label AS target_label
         FROM omni_actions a LEFT JOIN omni_objects o ON o.id = a.object_id
         ORDER BY a.performed_at DESC LIMIT 30"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

function bizc2(string $biz): string {
    $c = ['apexcybernet'=>'#a78bfa','ocpd'=>'#38bdf8','loan'=>'#fbbf24','alrisha'=>'#34d399','global'=>'#94a3b8'];
    return $c[$biz] ?? '#94a3b8';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Simulate — Omniscient</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<?php require __DIR__ . '/css.php'; ?>
<style>
.sim-wrap { padding: 1.25rem 1.5rem; }
.sim-head { display:flex; align-items:center; gap:0.8rem; margin-bottom:1rem; }
.sim-head h1 { margin:0; color:#fff; font-size:1.5rem; }
.sim-card { background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.06); border-radius:10px; padding:1.1rem 1.25rem; margin-bottom:1rem; }
.sim-card h3 { color:#c4b5fd; font-size:0.82rem; text-transform:uppercase; letter-spacing:0.08em; font-weight:800; margin:0 0 0.7rem 0; }
.sim-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:0.5rem 0.8rem; margin-bottom:0.6rem; }
.sim-grid label { font-size:0.72rem; color:#94a3b8; text-transform:uppercase; letter-spacing:0.06em; font-weight:800; display:block; margin-bottom:0.15rem; }
.sim-grid input, .sim-grid select { background:rgba(0,0,0,0.25); border:1px solid rgba(255,255,255,0.08); color:#fff; padding:0.45rem 0.6rem; border-radius:6px; width:100%; font-size:0.82rem; }
.sim-btn { background:rgba(124,58,237,0.18); border:1px solid rgba(124,58,237,0.4); color:#c4b5fd; padding:0.45rem 1rem; border-radius:7px; font-weight:700; cursor:pointer; font-size:0.82rem; }
.sim-btn.primary { background:rgba(52,211,153,0.2); border-color:rgba(52,211,153,0.45); color:#6ee7b7; }
.sim-btn.danger  { background:rgba(239,68,68,0.15); border-color:rgba(239,68,68,0.4); color:#fca5a5; }
.sim-preview { max-height:320px; overflow:auto; background:rgba(0,0,0,0.2); border-radius:6px; padding:0.4rem; }
.sim-row { display:flex; gap:0.6rem; padding:0.45rem 0.6rem; font-size:0.8rem; color:#e2e8f0; border-bottom:1px solid rgba(255,255,255,0.03); }
.sim-row:last-child { border-bottom:0; }
.sim-tag { display:inline-block; font-size:0.62rem; text-transform:uppercase; letter-spacing:0.06em; padding:0.05rem 0.45rem; border-radius:999px; font-weight:800; }
.sim-count { font-size:1.4rem; font-weight:800; color:#fff; }
.sim-meta { font-size:0.78rem; color:#94a3b8; }
.sim-run-ok { color:#6ee7b7; }
.sim-run-err { color:#fca5a5; }
.sim-audit { font-size:0.78rem; font-family:monospace; color:#cbd5e1; padding:0.3rem 0.5rem; background:rgba(0,0,0,0.15); border-radius:4px; margin-bottom:0.25rem; }
</style>
</head>
<body>
<div class="omni-layout">
<?php require __DIR__ . '/sidebar.php'; ?>
<div class="omni-main">
<div class="sim-wrap">

  <div class="sim-head">
    <h1>◈ Simulate <span style="color:#94a3b8;font-size:0.9rem;font-weight:400;">· cohort-level actions</span></h1>
    <a href="explore.php" style="margin-left:auto;color:#c4b5fd;font-size:0.8rem;text-decoration:none;border:1px solid rgba(124,58,237,0.3);padding:0.3rem 0.7rem;border-radius:6px;">Explorer ↗</a>
  </div>

  <!-- Cohort filter -->
  <div class="sim-card">
    <h3>① Build the cohort</h3>
    <form method="get">
      <div class="sim-grid">
        <div>
          <label>Type</label>
          <select name="type">
            <option value="">— any —</option>
            <?php foreach (OMNI_TYPES as $t): ?>
              <option value="<?= htmlspecialchars($t) ?>" <?= $type===$t?'selected':'' ?>><?= htmlspecialchars($t) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Business</label>
          <select name="biz">
            <option value="">— any —</option>
            <?php foreach (['apexcybernet','ocpd','loan','alrisha','global'] as $b): ?>
              <option value="<?= $b ?>" <?= $biz===$b?'selected':'' ?>><?= ucfirst($b) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Label contains</label>
          <input type="text" name="label" value="<?= htmlspecialchars($label_like) ?>" placeholder="substring">
        </div>
        <div>
          <label>Limit</label>
          <input type="number" name="limit" value="<?= (int)$limit ?>" min="1" max="500">
        </div>
      </div>
      <div class="sim-grid" style="grid-template-columns:1fr 90px 1fr;">
        <div>
          <label>Prop key (JSON)</label>
          <input type="text" name="pk" value="<?= htmlspecialchars($prop_key) ?>" placeholder="h_coins, status, principal_amount…">
        </div>
        <div>
          <label>Op</label>
          <select name="po">
            <?php foreach (['=','!=','>','>=','<','<=','LIKE'] as $op): ?>
              <option value="<?= $op ?>" <?= $prop_op===$op?'selected':'' ?>><?= htmlspecialchars($op) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Prop value</label>
          <input type="text" name="pv" value="<?= htmlspecialchars($prop_val) ?>" placeholder="100 / approved / …">
        </div>
      </div>
      <div class="sim-grid" style="grid-template-columns:120px 1fr 1fr;">
        <div>
          <label>Link direction</label>
          <select name="rd">
            <option value="">— none —</option>
            <option value="out" <?= $rel_dir==='out'?'selected':'' ?>>Outbound (→)</option>
            <option value="in"  <?= $rel_dir==='in'?'selected':'' ?>>Inbound (←)</option>
          </select>
        </div>
        <div>
          <label>Relation</label>
          <select name="rn">
            <option value="">— any —</option>
            <?php foreach (OMNI_RELATIONS as $r): ?>
              <option value="<?= $r ?>" <?= $rel_name===$r?'selected':'' ?>><?= $r ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Target type</label>
          <select name="rt">
            <option value="">— any —</option>
            <?php foreach (OMNI_TYPES as $t): ?>
              <option value="<?= $t ?>" <?= $rel_ttype===$t?'selected':'' ?>><?= $t ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div style="display:flex;gap:0.5rem;align-items:center;margin-top:0.8rem;">
        <button type="submit" class="sim-btn primary"><i class="bi bi-funnel"></i> Build cohort</button>
        <?php if (!empty($where)): ?>
          <a href="simulate.php" style="color:#94a3b8;font-size:0.82rem;text-decoration:none;">Clear</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Preview -->
  <?php if (!empty($where)): ?>
  <div class="sim-card">
    <h3>② Preview cohort</h3>
    <?php if (!empty($cohort_err)): ?>
      <div style="color:#fca5a5;font-size:0.85rem;">Filter error: <?= htmlspecialchars($cohort_err) ?></div>
    <?php else: ?>
      <div style="display:flex;align-items:baseline;gap:1rem;margin-bottom:0.7rem;">
        <div class="sim-count"><?= number_format($cohort_count) ?></div>
        <div class="sim-meta">total match · showing <?= count($cohort) ?></div>
      </div>
      <div class="sim-preview">
        <?php foreach ($cohort as $c): ?>
          <div class="sim-row">
            <span class="sim-tag" style="background:<?= bizc2($c['business']) ?>22;color:<?= bizc2($c['business']) ?>;"><?= htmlspecialchars($c['business']) ?></span>
            <span class="sim-tag" style="background:rgba(124,58,237,0.15);color:#c4b5fd;"><?= htmlspecialchars($c['type']) ?></span>
            <a href="explore.php?id=<?= (int)$c['id'] ?>" style="color:#e2e8f0;text-decoration:none;flex:1;min-width:0;"><?= htmlspecialchars($c['label'] ?: $c['ref']) ?></a>
            <span style="color:#64748b;font-family:monospace;font-size:0.7rem;"><?= htmlspecialchars($c['ref']) ?></span>
          </div>
        <?php endforeach; ?>
        <?php if (empty($cohort)): ?>
          <div style="color:#64748b;text-align:center;padding:1rem;">No matches.</div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Action -->
  <?php if (!empty($cohort)): ?>
  <div class="sim-card">
    <h3>③ Choose an action</h3>
    <form method="post">
      <input type="hidden" name="cohort_ids" value="<?= htmlspecialchars(implode(',', array_map(fn($c)=>$c['id'], $cohort))) ?>">
      <div class="sim-grid" style="grid-template-columns:220px 1fr;">
        <div>
          <label>Action</label>
          <select name="action_type" required>
            <?php foreach (OMNI_ACTIONS as $a): ?>
              <option value="<?= $a ?>"><?= $a ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Payload (JSON)</label>
          <input type="text" name="payload" value='{"amount": 10, "reason": "omni-reward"}' placeholder='{"amount": 10, "reason": "text"}'>
        </div>
      </div>
      <div style="display:flex;gap:0.5rem;margin-top:0.6rem;">
        <button type="submit" class="sim-btn" name="mode" value="simulate"><i class="bi bi-play"></i> Simulate (dry-run)</button>
        <button type="submit" class="sim-btn danger"  name="mode" value="execute"
                onclick="return confirm('EXECUTE this action against <?= count($cohort) ?> objects — for real? Writes will hit the source tables.');"><i class="bi bi-lightning-fill"></i> Execute</button>
        <div style="font-size:0.72rem;color:#94a3b8;align-self:center;">
          Dry-run records status=simulated only. Execute writes at source and records status=executed.
          <br>Actions without a wired executor land as status=proposed (audit-only).
        </div>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <!-- Results -->
  <?php if ($run_results): ?>
  <div class="sim-card" style="border-color:<?= $run_results['mode']==='execute'?'rgba(52,211,153,0.35)':'rgba(124,58,237,0.35)' ?>;">
    <h3 style="color:<?= $run_results['mode']==='execute'?'#6ee7b7':'#c4b5fd' ?>;">④ Run results · <?= strtoupper($run_results['mode']) ?> · <?= htmlspecialchars($run_results['action_type']) ?></h3>
    <div style="display:flex;gap:1.2rem;margin-bottom:0.6rem;">
      <div><span class="sim-count sim-run-ok"><?= $run_results['ok'] ?></span> <span class="sim-meta">ok</span></div>
      <div><span class="sim-count sim-run-err"><?= $run_results['err'] ?></span> <span class="sim-meta">failed</span></div>
      <div><span class="sim-count" style="color:#cbd5e1;"><?= $run_results['total'] ?></span> <span class="sim-meta">total</span></div>
    </div>
    <div class="sim-preview">
      <?php foreach ($run_results['rows'] as $r): ?>
        <div class="sim-row">
          <i class="bi <?= $r['ok']?'bi-check-circle-fill':'bi-x-circle-fill' ?>" style="color:<?= $r['ok']?'#6ee7b7':'#fca5a5' ?>;"></i>
          <a href="explore.php?id=<?= (int)$r['object_id'] ?>" style="color:#e2e8f0;text-decoration:none;min-width:200px;"><?= htmlspecialchars($r['label'] ?: $r['ref']) ?></a>
          <span class="sim-tag" style="background:rgba(148,163,184,0.1);color:#94a3b8;"><?= htmlspecialchars($r['status']) ?></span>
          <span style="color:#cbd5e1;flex:1;"><?= htmlspecialchars($r['message']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Recent audit -->
  <div class="sim-card">
    <h3>Recent actions (audit)</h3>
    <?php if (empty($recent)): ?>
      <div class="sim-meta">No actions recorded yet.</div>
    <?php else: ?>
      <?php foreach ($recent as $a): ?>
        <div class="sim-audit">
          <span style="color:#64748b;"><?= htmlspecialchars($a['performed_at']) ?></span>
          · <span style="color:#fbbf24;"><?= htmlspecialchars($a['performed_by']) ?></span>
          · <span style="color:#6ee7b7;"><?= htmlspecialchars($a['action_type']) ?></span>
          · <span style="color:#94a3b8;"><?= htmlspecialchars($a['status']) ?></span>
          <?php if (!empty($a['target_label'])): ?>
            → <span style="color:#c4b5fd;"><?= htmlspecialchars($a['target_label']) ?></span>
          <?php endif; ?>
          <?php if (!empty($a['payload']) && $a['payload'] !== '[]'): ?>
            <div style="color:#64748b;font-size:0.7rem;margin-top:0.1rem;"><?= htmlspecialchars($a['payload']) ?></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div>
</div>
</div>
</body>
</html>
