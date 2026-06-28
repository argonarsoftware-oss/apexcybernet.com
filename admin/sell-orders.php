<?php
require_once __DIR__ . '/../includes/db.php';

// Token auth
if (isset($_GET['token']) && $_GET['token'] === 'argonar-admin-2026-token') {
    $_SESSION['admin_logged_in'] = true; $_SESSION['admin_username'] = 'admin'; $_SESSION['admin_role'] = 'admin';
}
// Auth check
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: ' . base_url('admin/'));
    exit;
}

$admin_username = $_SESSION['admin_username'] ?? '';
$admin_role = $_SESSION['admin_role'] ?? '';

// Pending sell orders
$pending = $pdo->query("SELECT s.*, a.display_name, a.email FROM h_coin_sell_orders s JOIN accounts a ON a.id = s.account_id WHERE s.status = 'pending' ORDER BY s.created_at DESC")->fetchAll();

// Recent processed orders (last 30)
$processed = $pdo->query("SELECT s.*, a.display_name, a.email FROM h_coin_sell_orders s JOIN accounts a ON a.id = s.account_id WHERE s.status IN ('paid', 'rejected') ORDER BY s.processed_at DESC LIMIT 30")->fetchAll();

$pageTitle = 'Sell Orders — Admin';
?>
<!DOCTYPE html>
<html lang="en-PH">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= base_url('css/app.css') ?>">
</head>
<body>

<div class="admin-wrap" style="max-width:900px; margin:0 auto; padding:1.5rem 1rem 4rem;">

    <!-- Header -->
    <div class="admin-header" style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1.5rem; flex-wrap:wrap; gap:0.75rem;">
        <div>
            <h1 style="font-size:1.3rem; font-weight:800; color:var(--text-main); margin:0;">
                <i class="bi bi-coin" style="color:#f59e0b;"></i> H-Coin Sell Orders
            </h1>
            <p style="font-size:0.78rem; color:var(--text-muted); margin:0.25rem 0 0;">
                Process user coin sell-back requests. Rate: ₱0.85/coin.
            </p>
        </div>
        <div style="display:flex; gap:0.4rem; flex-wrap:wrap;">
            <a href="<?= base_url('admin/') ?>" class="btn-back-site"><i class="bi bi-arrow-left"></i> Dashboard</a>
            <a href="<?= base_url('admin/brackets.php') ?>" class="btn-back-site"><i class="bi bi-diagram-3"></i> Brackets</a>
        </div>
    </div>

    <!-- Pending Orders -->
    <?php if (!empty($pending)): ?>
    <div class="admin-section" style="margin-bottom:1.5rem;">
        <div class="admin-section-header">
            <h2 style="color:#fbbf24;"><i class="bi bi-clock-history"></i> Pending <span class="admin-count"><?= count($pending) ?></span></h2>
        </div>
        <div class="admin-panel-body">
            <?php foreach ($pending as $so): ?>
            <div id="sell-row-<?= $so['id'] ?>" style="display:flex; align-items:center; gap:0.75rem; padding:0.85rem 0; border-bottom:1px solid var(--border); flex-wrap:wrap;">
                <div style="flex:1; min-width:140px;">
                    <div style="font-weight:700; font-size:0.85rem; color:var(--text-main);"><?= htmlspecialchars($so['display_name']) ?></div>
                    <div style="font-size:0.72rem; color:var(--text-muted);"><?= htmlspecialchars($so['email']) ?></div>
                </div>
                <div style="min-width:110px;">
                    <div style="font-size:0.7rem; color:var(--text-muted);">GCash</div>
                    <div style="font-weight:700; font-size:0.9rem; color:var(--accent-light); font-family:monospace;"><?= htmlspecialchars($so['gcash_number']) ?></div>
                </div>
                <div style="text-align:center; min-width:70px;">
                    <div style="font-size:0.7rem; color:var(--text-muted);">Coins</div>
                    <div style="font-weight:800; font-size:1rem; color:var(--text-main);"><?= number_format((int)$so['coins']) ?></div>
                </div>
                <div style="text-align:center; min-width:80px;">
                    <div style="font-size:0.7rem; color:var(--text-muted);">Send</div>
                    <div style="font-weight:800; font-size:1.1rem; color:#34d399;">₱<?= number_format((float)$so['peso_amount'], 2) ?></div>
                </div>
                <div style="font-size:0.72rem; color:var(--text-muted); min-width:80px;">
                    <?= date('M j, g:ia', strtotime($so['created_at'])) ?>
                </div>
                <div style="display:flex; gap:0.4rem;">
                    <button class="btn-admin btn-approve" onclick="processSellOrder(<?= $so['id'] ?>, 'paid')" style="padding:0.45rem 0.75rem; font-size:0.8rem;">
                        <i class="bi bi-check-lg"></i> Mark Paid
                    </button>
                    <button class="btn-admin btn-reject" onclick="processSellOrder(<?= $so['id'] ?>, 'rejected')" style="padding:0.45rem 0.75rem; font-size:0.8rem;">
                        <i class="bi bi-x-lg"></i> Reject
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php else: ?>
    <div style="text-align:center; padding:2.5rem 1rem; background:var(--card-bg); border:1px solid var(--border); border-radius:12px; margin-bottom:1.5rem;">
        <i class="bi bi-check-circle" style="font-size:2.5rem; color:#34d399;"></i>
        <div style="font-weight:700; color:var(--text-main); margin-top:0.5rem;">No pending sell orders</div>
        <div style="font-size:0.8rem; color:var(--text-muted); margin-top:0.25rem;">All caught up.</div>
    </div>
    <?php endif; ?>

    <!-- Processed History -->
    <?php if (!empty($processed)): ?>
    <div class="admin-section">
        <div class="admin-section-header">
            <h2><i class="bi bi-clock-fill" style="color:var(--text-muted);"></i> Recent History</h2>
        </div>
        <div class="admin-panel-body">
            <div class="table-responsive">
                <table class="admin-table" style="font-size:0.8rem;">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>GCash #</th>
                            <th>Coins</th>
                            <th>Peso</th>
                            <th>Status</th>
                            <th>Processed</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($processed as $po): ?>
                        <tr style="opacity:<?= $po['status'] === 'rejected' ? '0.5' : '1' ?>;">
                            <td>
                                <div style="font-weight:600;"><?= htmlspecialchars($po['display_name']) ?></div>
                                <div style="font-size:0.7rem; color:var(--text-muted);"><?= htmlspecialchars($po['email']) ?></div>
                            </td>
                            <td style="font-family:monospace;"><?= htmlspecialchars($po['gcash_number']) ?></td>
                            <td style="font-weight:700;"><?= number_format((int)$po['coins']) ?> HC</td>
                            <td style="font-weight:700;">₱<?= number_format((float)$po['peso_amount'], 2) ?></td>
                            <td>
                                <?php if ($po['status'] === 'paid'): ?>
                                    <span style="color:#34d399; font-weight:700;"><i class="bi bi-check-circle-fill"></i> Paid</span>
                                <?php else: ?>
                                    <span style="color:#f87171; font-weight:700;"><i class="bi bi-x-circle-fill"></i> Rejected</span>
                                <?php endif; ?>
                            </td>
                            <td style="color:var(--text-muted);"><?= $po['processed_at'] ? date('M j, g:ia', strtotime($po['processed_at'])) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
function processSellOrder(id, action) {
    var label = action === 'paid' ? 'mark as paid (GCash sent)' : 'reject and refund coins';
    if (!confirm('Are you sure you want to ' + label + '?')) return;

    var formData = new FormData();
    formData.append('action', 'sell_order_' + action);
    formData.append('id', id);

    fetch('<?= base_url("admin/action.php") ?>', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                var row = document.getElementById('sell-row-' + id);
                if (row) {
                    row.style.transition = 'opacity 0.3s';
                    row.style.opacity = '0';
                    setTimeout(function() { row.remove(); }, 300);
                }
            } else {
                alert('Error: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(function(err) { alert('Request failed: ' + err.message); });
}
</script>

</body>
</html>
