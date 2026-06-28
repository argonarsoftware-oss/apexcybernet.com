<?php
require_once __DIR__ . '/../includes/db.php';

if (empty($_SESSION['admin_logged_in'])) {
    header('Location: ' . base_url('admin/'));
    exit;
}

$tab    = $_GET['tab'] ?? 'pending';
$where  = $tab === 'all' ? '' : "WHERE o.status = 'pending'";
$orders = $pdo->query("
    SELECT o.*, a.display_name
    FROM h_coin_orders o
    LEFT JOIN accounts a ON a.id = o.account_id
    $where
    ORDER BY o.id DESC LIMIT 100
")->fetchAll();

$counts = $pdo->query("SELECT status, COUNT(*) FROM h_coin_orders GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$pendingCount = (int)($counts['pending'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>HC Orders — Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
:root{--bg:#0f0f13;--surf:#18181f;--card:#1e1e28;--border:rgba(255,255,255,0.08);--accent:#7c3aed;--green:#22c55e;--red:#f87171;--yellow:#f59e0b;--text:#e5e7eb;--muted:#6b7280;}
*{margin:0;padding:0;box-sizing:border-box;}
body{background:var(--bg);color:var(--text);font-family:'Inter',sans-serif;min-height:100vh;padding:1.5rem;}
h1{font-size:1.4rem;font-weight:800;margin-bottom:1.5rem;display:flex;align-items:center;gap:0.75rem;}
.back{font-size:0.82rem;color:var(--muted);text-decoration:none;display:inline-flex;align-items:center;gap:0.35rem;margin-bottom:1rem;}
.back:hover{color:var(--text);}
.tabs{display:flex;gap:0.5rem;margin-bottom:1.25rem;}
.tab{padding:0.5rem 1.1rem;border-radius:8px;font-size:0.82rem;font-weight:700;text-decoration:none;color:var(--muted);background:var(--card);border:1px solid var(--border);}
.tab.on{background:var(--accent);color:#fff;border-color:var(--accent);}
.badge{display:inline-block;background:var(--yellow);color:#000;border-radius:99px;font-size:0.65rem;padding:1px 6px;margin-left:4px;font-weight:800;vertical-align:middle;}
.info-bar{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:0.85rem 1.1rem;margin-bottom:1.25rem;font-size:0.82rem;color:var(--muted);}
table{width:100%;border-collapse:collapse;background:var(--card);border-radius:14px;overflow:hidden;border:1px solid var(--border);}
th{padding:0.7rem 1rem;text-align:left;font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--muted);border-bottom:1px solid var(--border);}
td{padding:0.85rem 1rem;font-size:0.85rem;border-bottom:1px solid var(--border);vertical-align:middle;}
tr:last-child td{border-bottom:none;}
.chip{display:inline-block;padding:0.2rem 0.65rem;border-radius:99px;font-size:0.7rem;font-weight:700;}
.chip-pending{background:rgba(245,158,11,0.12);color:var(--yellow);}
.chip-paid{background:rgba(34,197,94,0.12);color:var(--green);}
.chip-cancelled{background:rgba(107,114,128,0.15);color:var(--muted);}
.empty{text-align:center;padding:3rem;color:var(--muted);}
</style>
</head>
<body>

<a href="<?= base_url('admin/') ?>" class="back"><i class="bi bi-arrow-left"></i> Back to Admin</a>
<h1>
    <i class="bi bi-wallet2" style="color:var(--accent);"></i>
    HC Buy Orders
    <?php if ($pendingCount): ?><span class="badge"><?= $pendingCount ?> pending</span><?php endif; ?>
</h1>

<div class="info-bar">
    <i class="bi bi-lightning-charge-fill" style="color:var(--green);"></i>
    Payments are processed automatically via the GCash listener webhook — no manual approval needed.
    Pending orders are awaiting payment from the user.
</div>

<div class="tabs">
    <a href="?tab=pending" class="tab <?= $tab==='pending'?'on':'' ?>">
        Pending <?php if($pendingCount): ?><span class="badge"><?= $pendingCount ?></span><?php endif; ?>
    </a>
    <a href="?tab=all" class="tab <?= $tab==='all'?'on':'' ?>">All Orders</a>
</div>

<?php if (empty($orders)): ?>
<div class="empty"><i class="bi bi-inbox" style="font-size:2.5rem;display:block;margin-bottom:0.75rem;"></i>No orders</div>
<?php else: ?>
<table>
    <thead>
        <tr>
            <th>#</th>
            <th>User</th>
            <th>Ref Code</th>
            <th>HC</th>
            <th>Amount</th>
            <th>Status</th>
            <th>Created</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($orders as $o): ?>
    <tr>
        <td style="color:var(--muted);"><?= $o['id'] ?></td>
        <td><strong><?= htmlspecialchars($o['display_name'] ?? '—') ?></strong><br><span style="color:var(--muted);font-size:0.72rem;">uid <?= $o['account_id'] ?></span></td>
        <td style="font-family:monospace;font-size:0.8rem;color:var(--muted);"><?= htmlspecialchars($o['ref_code']) ?></td>
        <td style="color:#a78bfa;font-weight:800;"><?= number_format($o['coins']) ?> HC</td>
        <td><strong>₱<?= number_format($o['peso_amount'], 2) ?></strong></td>
        <td><span class="chip chip-<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span></td>
        <td style="color:var(--muted);font-size:0.78rem;"><?= date('M j, g:i A', strtotime($o['created_at'])) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
</body>
</html>
