<?php
require_once __DIR__ . '/../includes/db.php';

if (empty($_SESSION['admin_logged_in'])) {
    header('Location: ' . base_url('admin/'));
    exit;
}
$is_owner = (($_SESSION['admin_username'] ?? '') === 'kirfenia');

// ── POST actions ──
$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'adjust_coins') {
        $uid    = (int)($_POST['uid'] ?? 0);
        $amount = (int)($_POST['amount'] ?? 0);
        if ($uid && $amount !== 0) {
            try {
                $pdo->prepare("UPDATE accounts SET h_coins = GREATEST(0, h_coins + ?) WHERE id = ?")
                    ->execute([$amount, $uid]);
                $flash = ['type' => 'ok', 'msg' => ($amount > 0 ? "Added {$amount} HC" : "Deducted ".abs($amount)." HC") . " for account #{$uid}."];
            } catch (Exception $e) {
                $flash = ['type' => 'err', 'msg' => 'DB error: ' . $e->getMessage()];
            }
        }
    }

    if ($action === 'set_coins') {
        $uid = (int)($_POST['uid'] ?? 0);
        $val = max(0, (int)($_POST['value'] ?? 0));
        if ($uid) {
            try {
                $pdo->prepare("UPDATE accounts SET h_coins = ? WHERE id = ?")->execute([$val, $uid]);
                $flash = ['type' => 'ok', 'msg' => "Set HC balance to {$val} for account #{$uid}."];
            } catch (Exception $e) {
                $flash = ['type' => 'err', 'msg' => 'DB error: ' . $e->getMessage()];
            }
        }
    }

    if ($action === 'set_status' && $is_owner) {
        $uid    = (int)($_POST['uid'] ?? 0);
        $status = in_array($_POST['status'] ?? '', ['approved', 'pending', 'banned']) ? $_POST['status'] : null;
        if ($uid && $status) {
            try {
                $pdo->prepare("UPDATE accounts SET claim_status = ? WHERE id = ?")->execute([$status, $uid]);
                $flash = ['type' => 'ok', 'msg' => "Account #{$uid} status set to {$status}."];
            } catch (Exception $e) {
                $flash = ['type' => 'err', 'msg' => 'DB error: ' . $e->getMessage()];
            }
        }
    }

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// ── Filters ──
$search  = trim($_GET['q'] ?? '');
$status  = $_GET['s'] ?? 'all';
$sort    = in_array($_GET['sort'] ?? '', ['id','display_name','h_coins','created_at']) ? $_GET['sort'] : 'id';
$dir     = ($_GET['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
$page    = max(1, (int)($_GET['p'] ?? 1));
$per     = 30;

$where_parts = ['1=1'];
$params = [];

if ($search !== '') {
    $where_parts[] = '(display_name LIKE ? OR email LIKE ? OR id = ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = is_numeric($search) ? (int)$search : -1;
}
if ($status !== 'all') {
    $where_parts[] = 'claim_status = ?';
    $params[] = $status;
}
$where = implode(' AND ', $where_parts);

// Stats
$stats = [];
try {
    $stats = $pdo->query("SELECT
        COUNT(*) AS total,
        SUM(h_coins) AS total_hc,
        SUM(claim_status='approved') AS approved,
        SUM(claim_status='pending') AS pending,
        SUM(claim_status='banned') AS banned,
        SUM(created_at >= CURDATE()) AS today
        FROM accounts")->fetch() ?: [];
} catch (Exception $e) {}

// Count
$total_rows = 0;
try {
    $st = $pdo->prepare("SELECT COUNT(*) FROM accounts WHERE $where");
    $st->execute($params);
    $total_rows = (int)$st->fetchColumn();
} catch (Exception $e) {}
$total_pages = max(1, (int)ceil($total_rows / $per));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per;

// Rows
$rows = [];
try {
    $st = $pdo->prepare("SELECT id, email, display_name, h_coins, ref_type, claim_status, created_at
        FROM accounts WHERE $where ORDER BY $sort $dir LIMIT $per OFFSET $offset");
    $st->execute($params);
    $rows = $st->fetchAll();
} catch (Exception $e) {}

function sort_link(string $col, string $label, string $cur, string $dir, array $extra): string {
    $new_dir = ($cur === $col && $dir === 'asc') ? 'desc' : 'asc';
    $icon = $cur === $col ? ($dir === 'asc' ? ' ↑' : ' ↓') : '';
    $qs = http_build_query(array_merge($extra, ['sort' => $col, 'dir' => $new_dir]));
    return "<a href='?{$qs}' style='color:inherit;text-decoration:none;'>{$label}{$icon}</a>";
}
$extra_qs = array_filter(['q' => $search ?: null, 's' => $status !== 'all' ? $status : null, 'p' => null]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Accounts — Argonar Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
:root {
    --bg:#0f0f13; --surface:#17171f; --surface2:#1e1e28;
    --border:rgba(255,255,255,0.07);
    --accent:#7c3aed; --accent-light:#a78bfa;
    --green:#34d399; --yellow:#fbbf24; --red:#f87171; --blue:#60a5fa;
}
*{box-sizing:border-box;}
body{background:var(--bg);color:#e5e7eb;font-family:'Inter',system-ui,sans-serif;margin:0;font-size:14px;}
a{text-decoration:none;}
.topbar{background:var(--surface);border-bottom:1px solid var(--border);padding:0.75rem 1.5rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap;}
.topbar h1{margin:0;font-size:1.05rem;font-weight:800;color:#fff;}
.topbar a{color:var(--accent-light);font-size:0.82rem;}
.topbar a:hover{color:#fff;}
.wrap{padding:1.5rem;max-width:1400px;}
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:1rem;margin-bottom:1.5rem;}
.kpi{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:1rem 1.2rem;}
.kpi-label{font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:#6b7280;margin-bottom:0.4rem;}
.kpi-val{font-size:1.8rem;font-weight:900;line-height:1;color:var(--kpi-color,#fff);}
.filter-bar{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:0.75rem 1.2rem;margin-bottom:1.25rem;display:flex;gap:0.6rem;flex-wrap:wrap;align-items:center;}
.filter-bar input,.filter-bar select{background:var(--bg);color:#e5e7eb;border:1px solid rgba(255,255,255,0.12);border-radius:8px;padding:0.3rem 0.65rem;font-size:0.8rem;}
.filter-bar input:focus,.filter-bar select:focus{outline:none;border-color:var(--accent);}
.btn-filter{background:var(--accent);color:#fff;border:none;border-radius:8px;padding:0.3rem 0.85rem;font-size:0.8rem;font-weight:700;cursor:pointer;}
.btn-filter:hover{background:#6d28d9;}
.card{background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;}
.card-header{padding:0.7rem 1.2rem;border-bottom:1px solid var(--border);font-weight:700;font-size:0.82rem;color:#d1d5db;display:flex;justify-content:space-between;align-items:center;}
.acc-table{width:100%;border-collapse:collapse;font-size:0.78rem;}
.acc-table th{padding:0.55rem 0.85rem;text-align:left;color:#6b7280;font-weight:600;border-bottom:1px solid var(--border);white-space:nowrap;}
.acc-table td{padding:0.5rem 0.85rem;border-bottom:1px solid rgba(255,255,255,0.04);vertical-align:middle;}
.acc-table tr:last-child td{border-bottom:none;}
.acc-table tr:hover td{background:rgba(255,255,255,0.025);}
.badge-ok{background:rgba(52,211,153,0.15);color:var(--green);border-radius:6px;padding:0.1rem 0.45rem;font-size:0.68rem;font-weight:700;}
.badge-pend{background:rgba(251,191,36,0.15);color:var(--yellow);border-radius:6px;padding:0.1rem 0.45rem;font-size:0.68rem;font-weight:700;}
.badge-ban{background:rgba(248,113,113,0.15);color:var(--red);border-radius:6px;padding:0.1rem 0.45rem;font-size:0.68rem;font-weight:700;}
.hc-val{font-weight:700;color:var(--yellow);}
.btn-sm{background:var(--bg);border:1px solid var(--border);color:#e5e7eb;border-radius:6px;padding:0.15rem 0.5rem;font-size:0.72rem;cursor:pointer;white-space:nowrap;}
.btn-sm:hover{border-color:var(--accent);color:var(--accent-light);}
.btn-danger{border-color:rgba(248,113,113,0.3);color:var(--red);}
.btn-danger:hover{background:rgba(248,113,113,0.08);border-color:var(--red);}
.btn-success{border-color:rgba(52,211,153,0.3);color:var(--green);}
.btn-success:hover{background:rgba(52,211,153,0.08);border-color:var(--green);}
.pagination{display:flex;gap:0.3rem;flex-wrap:wrap;align-items:center;padding:0.75rem 1.2rem;border-top:1px solid var(--border);}
.pg-btn{background:var(--bg);color:#e5e7eb;border:1px solid var(--border);border-radius:6px;padding:0.22rem 0.55rem;font-size:0.76rem;cursor:pointer;}
.pg-btn:hover{border-color:var(--accent);color:var(--accent-light);}
.pg-btn.active{background:var(--accent);color:#fff;border-color:var(--accent);}
.flash{padding:0.7rem 1.2rem;border-radius:10px;margin-bottom:1rem;font-size:0.82rem;font-weight:600;}
.flash.ok{background:rgba(52,211,153,0.12);border:1px solid rgba(52,211,153,0.25);color:var(--green);}
.flash.err{background:rgba(248,113,113,0.12);border:1px solid rgba(248,113,113,0.25);color:var(--red);}
/* Modal */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:1000;align-items:center;justify-content:center;}
.modal-overlay.open{display:flex;}
.modal-box{background:var(--surface);border:1px solid var(--border);border-radius:16px;width:90%;max-width:400px;overflow:hidden;}
.modal-header{padding:0.9rem 1.25rem;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;}
.modal-header h3{margin:0;font-size:0.88rem;font-weight:800;}
.modal-close{background:none;border:none;color:#9ca3af;font-size:1.2rem;cursor:pointer;padding:0;line-height:1;}
.modal-close:hover{color:#fff;}
.modal-body{padding:1.25rem;}
.modal-body label{display:block;font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.4rem;margin-top:0.9rem;}
.modal-body label:first-child{margin-top:0;}
.modal-body input,.modal-body select{width:100%;background:var(--bg);color:#e5e7eb;border:1px solid rgba(255,255,255,0.12);border-radius:8px;padding:0.5rem 0.75rem;font-size:0.85rem;}
.modal-body input:focus,.modal-body select:focus{outline:none;border-color:var(--accent);}
.modal-footer{padding:0.9rem 1.25rem;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:0.5rem;}
.btn-modal-cancel{background:transparent;color:#9ca3af;border:1px solid var(--border);border-radius:8px;padding:0.4rem 0.9rem;font-size:0.82rem;cursor:pointer;}
.btn-modal-cancel:hover{color:#fff;}
.btn-modal-ok{background:var(--accent);color:#fff;border:none;border-radius:8px;padding:0.4rem 0.9rem;font-size:0.82rem;font-weight:700;cursor:pointer;}
.btn-modal-ok:hover{background:#6d28d9;}
</style>
</head>
<body>

<div class="topbar">
    <h1><i class="bi bi-people-fill"></i> Account Management</h1>
    <a href="<?= base_url('admin/') ?>"><i class="bi bi-arrow-left"></i> Admin</a>
    <span style="margin-left:auto; font-size:0.72rem; color:#6b7280;"><?= number_format($stats['total'] ?? 0) ?> total accounts</span>
</div>

<div class="wrap">

<?php if (isset($_GET['flash'])): ?>
<div class="flash ok"><i class="bi bi-check-circle"></i> Action applied.</div>
<?php endif; ?>

<!-- KPI row -->
<div class="kpi-grid">
    <div class="kpi" style="--kpi-color:#fff;">
        <div class="kpi-label"><i class="bi bi-people"></i> Total</div>
        <div class="kpi-val"><?= number_format($stats['total'] ?? 0) ?></div>
    </div>
    <div class="kpi" style="--kpi-color:var(--yellow);">
        <div class="kpi-label"><i class="bi bi-coin"></i> Total HC</div>
        <div class="kpi-val"><?= number_format($stats['total_hc'] ?? 0) ?></div>
    </div>
    <div class="kpi" style="--kpi-color:var(--green);">
        <div class="kpi-label"><i class="bi bi-check-circle"></i> Approved</div>
        <div class="kpi-val"><?= number_format($stats['approved'] ?? 0) ?></div>
    </div>
    <div class="kpi" style="--kpi-color:var(--yellow);">
        <div class="kpi-label"><i class="bi bi-hourglass"></i> Pending</div>
        <div class="kpi-val"><?= number_format($stats['pending'] ?? 0) ?></div>
    </div>
    <div class="kpi" style="--kpi-color:var(--red);">
        <div class="kpi-label"><i class="bi bi-slash-circle"></i> Banned</div>
        <div class="kpi-val"><?= number_format($stats['banned'] ?? 0) ?></div>
    </div>
    <div class="kpi" style="--kpi-color:var(--blue);">
        <div class="kpi-label"><i class="bi bi-person-plus"></i> Today</div>
        <div class="kpi-val"><?= number_format($stats['today'] ?? 0) ?></div>
    </div>
</div>

<!-- Filter bar -->
<form method="get" class="filter-bar">
    <input type="text" name="q" placeholder="Search name, email or ID…" value="<?= htmlspecialchars($search) ?>" style="width:220px;">
    <select name="s">
        <option value="all"      <?= $status==='all'?'selected':'' ?>>All statuses</option>
        <option value="approved" <?= $status==='approved'?'selected':'' ?>>Approved</option>
        <option value="pending"  <?= $status==='pending'?'selected':'' ?>>Pending</option>
        <option value="banned"   <?= $status==='banned'?'selected':'' ?>>Banned</option>
    </select>
    <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
    <input type="hidden" name="dir"  value="<?= htmlspecialchars($dir) ?>">
    <button type="submit" class="btn-filter"><i class="bi bi-funnel"></i> Filter</button>
    <?php if ($search || $status !== 'all'): ?>
    <a href="<?= base_url('admin/accounts.php') ?>" style="color:#9ca3af; font-size:0.76rem;">✕ Clear</a>
    <?php endif; ?>
    <span style="margin-left:auto; font-size:0.76rem; color:#6b7280;"><?= number_format($total_rows) ?> results</span>
</form>

<!-- Table -->
<div class="card">
    <div class="card-header">
        <span><i class="bi bi-list-ul"></i> Accounts</span>
        <span style="font-size:0.7rem;color:#6b7280;">Click <i class="bi bi-coin" style="color:var(--yellow)"></i> to adjust HC</span>
    </div>
    <div style="overflow-x:auto;">
    <table class="acc-table">
        <thead>
            <tr>
                <th><?= sort_link('id', 'ID', $sort, $dir, $extra_qs) ?></th>
                <th><?= sort_link('display_name', 'Name', $sort, $dir, $extra_qs) ?></th>
                <th>Email</th>
                <th><?= sort_link('h_coins', 'HC Balance', $sort, $dir, $extra_qs) ?></th>
                <th>Status</th>
                <th>Type</th>
                <th><?= sort_link('created_at', 'Joined', $sort, $dir, $extra_qs) ?></th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
        <tr><td colspan="8" style="text-align:center;padding:2rem;color:#6b7280;"><i class="bi bi-inbox"></i> No accounts found</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
        <tr>
            <td style="color:#6b7280;">#<?= $r['id'] ?></td>
            <td style="font-weight:600;"><?= htmlspecialchars($r['display_name']) ?></td>
            <td style="color:#9ca3af;"><?= htmlspecialchars($r['email']) ?></td>
            <td>
                <span class="hc-val"><?= number_format($r['h_coins'] ?? 0) ?> HC</span>
            </td>
            <td>
                <?php if ($r['claim_status'] === 'approved'): ?>
                    <span class="badge-ok">Approved</span>
                <?php elseif ($r['claim_status'] === 'pending'): ?>
                    <span class="badge-pend">Pending</span>
                <?php else: ?>
                    <span class="badge-ban"><?= htmlspecialchars($r['claim_status']) ?></span>
                <?php endif; ?>
            </td>
            <td style="color:#6b7280;"><?= htmlspecialchars($r['ref_type'] ?? '—') ?></td>
            <td style="color:#6b7280;white-space:nowrap;"><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
            <td style="white-space:nowrap;">
                <button class="btn-sm" onclick="openCoinModal(<?= $r['id'] ?>, '<?= htmlspecialchars(addslashes($r['display_name'])) ?>', <?= (int)$r['h_coins'] ?>)">
                    <i class="bi bi-coin" style="color:var(--yellow)"></i> HC
                </button>
                <?php if ($is_owner): ?>
                <button class="btn-sm btn-danger" onclick="openStatusModal(<?= $r['id'] ?>, '<?= htmlspecialchars(addslashes($r['display_name'])) ?>', '<?= htmlspecialchars($r['claim_status']) ?>')">
                    <i class="bi bi-shield"></i> Status
                </button>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php
        $qp = http_build_query(array_filter(['q'=>$search?:null,'s'=>$status!=='all'?$status:null,'sort'=>$sort,'dir'=>$dir]));
        $qs = $qp ? '&' : '';
        $start = max(1,$page-4); $end = min($total_pages,$page+4);
        if ($page>1): ?><a href="?<?=$qp.$qs?>p=<?=$page-1?>" class="pg-btn"><i class="bi bi-chevron-left"></i></a><?php endif;
        for ($i=$start;$i<=$end;$i++): ?>
        <a href="?<?=$qp.$qs?>p=<?=$i?>" class="pg-btn <?=$i===$page?'active':''?>"><?=$i?></a>
        <?php endfor;
        if ($page<$total_pages): ?><a href="?<?=$qp.$qs?>p=<?=$page+1?>" class="pg-btn"><i class="bi bi-chevron-right"></i></a><?php endif; ?>
        <span style="font-size:0.72rem;color:#6b7280;margin-left:0.5rem;">Page <?=$page?> of <?=$total_pages?></span>
    </div>
    <?php endif; ?>
</div>

</div><!-- /wrap -->

<!-- HC Adjust Modal -->
<div class="modal-overlay" id="coinModal" onclick="closeModal('coinModal',event)">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="bi bi-coin" style="color:var(--yellow)"></i> Adjust HC Balance</h3>
            <button class="modal-close" onclick="document.getElementById('coinModal').classList.remove('open')">✕</button>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="adjust_coins">
            <input type="hidden" name="uid" id="coinUid">
            <div class="modal-body">
                <div id="coinUserInfo" style="font-size:0.82rem;color:#9ca3af;margin-bottom:0.75rem;"></div>
                <label>Operation</label>
                <select name="op" id="coinOp" onchange="updateAmount()">
                    <option value="add">Add HC</option>
                    <option value="deduct">Deduct HC</option>
                    <option value="set">Set to exact amount</option>
                </select>
                <label>Amount</label>
                <input type="number" name="amount" id="coinAmount" min="1" value="20" style="margin-bottom:0.25rem;">
                <div style="font-size:0.7rem;color:#6b7280;margin-top:0.3rem;">Current: <span id="coinCurrent"></span> HC</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-modal-cancel" onclick="document.getElementById('coinModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn-modal-ok" id="coinSubmitBtn">Apply</button>
            </div>
        </form>
    </div>
</div>

<!-- Status Modal (owner only) -->
<?php if ($is_owner): ?>
<div class="modal-overlay" id="statusModal" onclick="closeModal('statusModal',event)">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="bi bi-shield"></i> Change Account Status</h3>
            <button class="modal-close" onclick="document.getElementById('statusModal').classList.remove('open')">✕</button>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="set_status">
            <input type="hidden" name="uid" id="statusUid">
            <div class="modal-body">
                <div id="statusUserInfo" style="font-size:0.82rem;color:#9ca3af;margin-bottom:0.75rem;"></div>
                <label>New Status</label>
                <select name="status" id="statusSelect">
                    <option value="approved">Approved</option>
                    <option value="pending">Pending</option>
                    <option value="banned">Banned</option>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-modal-cancel" onclick="document.getElementById('statusModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn-modal-ok">Save</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function openCoinModal(uid, name, current) {
    document.getElementById('coinUid').value = uid;
    document.getElementById('coinUserInfo').textContent = 'Account: ' + name + ' (#' + uid + ')';
    document.getElementById('coinCurrent').textContent = current;
    document.getElementById('coinAmount').value = 20;
    document.getElementById('coinModal').classList.add('open');
}
function updateAmount() {
    const op = document.getElementById('coinOp').value;
    const btn = document.getElementById('coinSubmitBtn');
    const form = document.querySelector('#coinModal form');
    if (op === 'set') {
        form.querySelector('input[name="action"]').value = 'set_coins';
        form.querySelector('input[name="amount"]').name = 'value';
        btn.textContent = 'Set Balance';
    } else {
        form.querySelector('input[name="action"]').value = 'adjust_coins';
        const inp = form.querySelector('input[name="value"]');
        if (inp) inp.name = 'amount';
        btn.textContent = op === 'add' ? 'Add HC' : 'Deduct HC';
    }
}
function openStatusModal(uid, name, current) {
    document.getElementById('statusUid').value = uid;
    document.getElementById('statusUserInfo').textContent = 'Account: ' + name + ' (#' + uid + ')';
    document.getElementById('statusSelect').value = current;
    document.getElementById('statusModal').classList.add('open');
}
function closeModal(id, e) {
    if (e.target === document.getElementById(id))
        document.getElementById(id).classList.remove('open');
}
</script>
</body>
</html>
