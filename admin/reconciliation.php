<?php
/**
 * Payment Reconciliation Report
 *
 * Cross-references the local Apex Cybernet database against the listener API to surface:
 *  - Pending registrations whose listener order is paid (should be approved)
 *  - Pending registrations with no order (never opened the payment page)
 *  - Pending registrations with cancelled/expired listener orders (need force-match)
 *  - Approved registrations whose listener order doesn't agree (data drift)
 *  - Unmatched payments in the listener (orphan money sitting in GCash)
 *  - Stale pending registrations (>30 minutes since creation)
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/listener-api.php';

// Token bypass for cron/CLI
if (isset($_GET['token']) && $_GET['token'] === 'apexcybernet-admin-2026-token') {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_username'] = 'admin';
    $_SESSION['admin_role'] = 'admin';
}

if (empty($_SESSION['admin_logged_in'])) {
    header('Location: ' . base_url('admin/'));
    exit;
}

if (($_SESSION['admin_role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo "Forbidden — admin role required.";
    exit;
}

// Build a map of all listener orders, indexed by order_id
function listenerGet2($endpoint) {
    $url = LISTENER_URL . $endpoint;
    $opts = ['http' => [
        'method'  => 'GET',
        'header'  => "X-API-Key: " . LISTENER_API_KEY . "\r\n",
        'timeout' => 10,
        'ignore_errors' => true,
    ]];
    $ctx = stream_context_create($opts);
    $resp = @file_get_contents($url, false, $ctx);
    return $resp ? json_decode($resp, true) : null;
}

// Pull all orders from listener (last 200)
$listener_resp = listenerGet2('/api/orders?limit=200');
$listener_orders_by_id = [];
if ($listener_resp && !empty($listener_resp['orders'])) {
    foreach ($listener_resp['orders'] as $o) {
        $listener_orders_by_id[$o['order_id']] = $o;
    }
}

// Pull unmatched payments
$unmatched_resp = listenerGet2('/api/payments?matched=false&limit=50');
$unmatched_payments = ($unmatched_resp['payments'] ?? []);

// Pull all teams + solos from local DB
$teams = $pdo->query("SELECT id, ref_code, team_name AS name, status, created_at, 'team' AS type FROM teams WHERE ref_code IS NOT NULL AND ref_code != ''")->fetchAll();
$solos = $pdo->query("SELECT id, ref_code, player_name AS name, status, created_at, 'solo' AS type FROM solo_players WHERE ref_code IS NOT NULL AND ref_code != ''")->fetchAll();
$registrations = array_merge($teams, $solos);

// Categorize each registration
$issues = [
    'should_be_approved'   => [], // local pending, listener says paid
    'no_listener_order'    => [], // local pending, no order in listener
    'cancelled_with_pay'   => [], // local pending, listener cancelled but pay_amount exists
    'data_drift'           => [], // local approved but listener doesn't agree
    'stale_pending'        => [], // local pending > 30 min and listener is also pending
    'healthy_pending'      => [], // local pending < 30 min, listener pending
];

$now = time();
foreach ($registrations as $r) {
    $order = $listener_orders_by_id[$r['ref_code']] ?? null;
    $local_status = $r['status'];
    $age_min = ($now - strtotime($r['created_at'])) / 60;

    if ($local_status === 'pending') {
        if (!$order) {
            $issues['no_listener_order'][] = ['reg' => $r];
        } elseif ($order['status'] === 'paid') {
            $issues['should_be_approved'][] = ['reg' => $r, 'order' => $order];
        } elseif (in_array($order['status'], ['cancelled', 'expired'])) {
            $issues['cancelled_with_pay'][] = ['reg' => $r, 'order' => $order];
        } elseif ($age_min > 30) {
            $issues['stale_pending'][] = ['reg' => $r, 'order' => $order, 'age_min' => $age_min];
        } else {
            $issues['healthy_pending'][] = ['reg' => $r, 'order' => $order];
        }
    } elseif ($local_status === 'approved') {
        if ($order && $order['status'] !== 'paid') {
            $issues['data_drift'][] = ['reg' => $r, 'order' => $order];
        }
    }
}

// Count payments matched correctly
$total_pending = count($teams) + count($solos);
$health_score = $total_pending > 0
    ? round((count($issues['healthy_pending']) / $total_pending) * 100)
    : 100;

$pageTitle = 'Reconciliation Report — Apex Cybernet Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= base_url('css/app.css') ?>">
</head>
<body>

<div class="admin-container">
    <div class="admin-header">
        <div>
            <h1><i class="bi bi-clipboard-data"></i> Payment Reconciliation</h1>
            <p style="margin:0; font-size:0.8rem; color:var(--text-muted);">Cross-check local DB vs listener API</p>
        </div>
        <div class="admin-header-actions">
            <a href="<?= base_url('admin/') ?>" class="btn-back-site"><i class="bi bi-arrow-left"></i> Dashboard</a>
            <a href="?<?= http_build_query($_GET) ?>" class="btn-back-site"><i class="bi bi-arrow-clockwise"></i> Refresh</a>
        </div>
    </div>

    <!-- Health summary -->
    <div style="background:var(--bg-card); border:1px solid var(--border); border-radius:12px; padding:1.5rem; margin-bottom:1.5rem;">
        <div style="display:flex; gap:1.5rem; align-items:center; flex-wrap:wrap;">
            <div>
                <div style="font-size:3rem; font-weight:800; color:<?= $health_score >= 90 ? 'var(--success)' : ($health_score >= 70 ? '#fbbf24' : '#f87171') ?>;"><?= $health_score ?>%</div>
                <div style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px;">Health Score</div>
            </div>
            <div style="flex:1; min-width:200px;">
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(140px, 1fr)); gap:0.5rem; font-size:0.85rem;">
                    <div><i class="bi bi-check-circle-fill" style="color:var(--success);"></i> <?= count($issues['healthy_pending']) ?> healthy pending</div>
                    <div><i class="bi bi-arrow-up-circle-fill" style="color:var(--success);"></i> <?= count($issues['should_be_approved']) ?> ready to approve</div>
                    <div><i class="bi bi-exclamation-circle-fill" style="color:#fbbf24;"></i> <?= count($issues['stale_pending']) ?> stale pending</div>
                    <div><i class="bi bi-x-circle-fill" style="color:#f87171;"></i> <?= count($issues['cancelled_with_pay']) ?> cancelled w/ pay</div>
                    <div><i class="bi bi-question-circle-fill" style="color:var(--text-muted);"></i> <?= count($issues['no_listener_order']) ?> no listener order</div>
                    <div><i class="bi bi-shuffle" style="color:#f87171;"></i> <?= count($issues['data_drift']) ?> data drift</div>
                </div>
                <div style="margin-top:0.75rem; font-size:0.75rem; color:var(--text-muted);">
                    <i class="bi bi-info-circle"></i> <?= count($unmatched_payments) ?> unmatched payment(s) in listener
                </div>
            </div>
        </div>
    </div>

    <!-- Issue: should be approved -->
    <?php if (!empty($issues['should_be_approved'])): ?>
    <div class="admin-section" style="margin-bottom:1rem;">
        <h2 style="color:var(--success); font-size:1rem;"><i class="bi bi-arrow-up-circle-fill"></i> Ready to Approve (<?= count($issues['should_be_approved']) ?>)</h2>
        <p style="font-size:0.8rem; color:var(--text-muted); margin-bottom:0.75rem;">Listener says paid, local DB still pending. Backfill cron should pick these up within 60s.</p>
        <table class="admin-table">
            <thead><tr><th>Ref</th><th>Name</th><th>Type</th><th>Listener Sender</th><th>Amount</th></tr></thead>
            <tbody>
                <?php foreach ($issues['should_be_approved'] as $row): ?>
                <tr>
                    <td><code style="color:var(--accent-light);"><?= htmlspecialchars($row['reg']['ref_code']) ?></code></td>
                    <td><?= htmlspecialchars($row['reg']['name']) ?></td>
                    <td><?= $row['reg']['type'] ?></td>
                    <td><?= htmlspecialchars($row['order']['sender_name'] ?? '—') ?></td>
                    <td>₱<?= number_format((float)$row['order']['pay_amount'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Issue: cancelled with pay_amount -->
    <?php if (!empty($issues['cancelled_with_pay'])): ?>
    <div class="admin-section" style="margin-bottom:1rem;">
        <h2 style="color:#f87171; font-size:1rem;"><i class="bi bi-x-circle-fill"></i> Cancelled / Expired Orders (<?= count($issues['cancelled_with_pay']) ?>)</h2>
        <p style="font-size:0.8rem; color:var(--text-muted); margin-bottom:0.75rem;">Listener order is cancelled or expired. If customer paid AFTER cancellation, their payment is in the unmatched list — use rematch or force-match.</p>
        <table class="admin-table">
            <thead><tr><th>Ref</th><th>Name</th><th>Order Status</th><th>Pay Amount</th><th>Created</th></tr></thead>
            <tbody>
                <?php foreach ($issues['cancelled_with_pay'] as $row): ?>
                <tr>
                    <td><code style="color:var(--accent-light);"><?= htmlspecialchars($row['reg']['ref_code']) ?></code></td>
                    <td><?= htmlspecialchars($row['reg']['name']) ?></td>
                    <td><span style="color:#f87171;"><?= htmlspecialchars($row['order']['status']) ?></span></td>
                    <td>₱<?= number_format((float)$row['order']['pay_amount'], 2) ?></td>
                    <td style="font-size:0.7rem; color:var(--text-muted);"><?= date('M j g:ia', strtotime($row['order']['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Issue: stale pending -->
    <?php if (!empty($issues['stale_pending'])): ?>
    <div class="admin-section" style="margin-bottom:1rem;">
        <h2 style="color:#fbbf24; font-size:1rem;"><i class="bi bi-clock-history"></i> Stale Pending (<?= count($issues['stale_pending']) ?>)</h2>
        <p style="font-size:0.8rem; color:var(--text-muted); margin-bottom:0.75rem;">Pending more than 30 minutes — customer probably abandoned or hasn't paid yet.</p>
        <table class="admin-table">
            <thead><tr><th>Ref</th><th>Name</th><th>Type</th><th>Age</th></tr></thead>
            <tbody>
                <?php foreach ($issues['stale_pending'] as $row): ?>
                <tr>
                    <td><code style="color:var(--accent-light);"><?= htmlspecialchars($row['reg']['ref_code']) ?></code></td>
                    <td><?= htmlspecialchars($row['reg']['name']) ?></td>
                    <td><?= $row['reg']['type'] ?></td>
                    <td style="color:#fbbf24;"><?= round($row['age_min']) ?>m</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Issue: no listener order -->
    <?php if (!empty($issues['no_listener_order'])): ?>
    <div class="admin-section" style="margin-bottom:1rem;">
        <h2 style="color:var(--text-muted); font-size:1rem;"><i class="bi bi-question-circle-fill"></i> No Listener Order (<?= count($issues['no_listener_order']) ?>)</h2>
        <p style="font-size:0.8rem; color:var(--text-muted); margin-bottom:0.75rem;">Registered but never opened the payment page. Listener has no record of an order being created.</p>
        <table class="admin-table">
            <thead><tr><th>Ref</th><th>Name</th><th>Type</th><th>Registered</th></tr></thead>
            <tbody>
                <?php foreach ($issues['no_listener_order'] as $row): ?>
                <tr>
                    <td><code style="color:var(--accent-light);"><?= htmlspecialchars($row['reg']['ref_code']) ?></code></td>
                    <td><?= htmlspecialchars($row['reg']['name']) ?></td>
                    <td><?= $row['reg']['type'] ?></td>
                    <td style="font-size:0.7rem; color:var(--text-muted);"><?= date('M j g:ia', strtotime($row['reg']['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Issue: data drift -->
    <?php if (!empty($issues['data_drift'])): ?>
    <div class="admin-section" style="margin-bottom:1rem;">
        <h2 style="color:#f87171; font-size:1rem;"><i class="bi bi-shuffle"></i> Data Drift (<?= count($issues['data_drift']) ?>)</h2>
        <p style="font-size:0.8rem; color:var(--text-muted); margin-bottom:0.75rem;">Local DB says approved but listener disagrees. Manual review recommended.</p>
        <table class="admin-table">
            <thead><tr><th>Ref</th><th>Name</th><th>Local</th><th>Listener</th></tr></thead>
            <tbody>
                <?php foreach ($issues['data_drift'] as $row): ?>
                <tr>
                    <td><code style="color:var(--accent-light);"><?= htmlspecialchars($row['reg']['ref_code']) ?></code></td>
                    <td><?= htmlspecialchars($row['reg']['name']) ?></td>
                    <td><span style="color:var(--success);"><?= $row['reg']['status'] ?></span></td>
                    <td><span style="color:#f87171;"><?= $row['order']['status'] ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Unmatched payments in listener -->
    <?php if (!empty($unmatched_payments)): ?>
    <div class="admin-section" style="margin-bottom:1rem;">
        <h2 style="color:#fbbf24; font-size:1rem;"><i class="bi bi-cash-coin"></i> Unmatched Payments in Listener (<?= count($unmatched_payments) ?>)</h2>
        <p style="font-size:0.8rem; color:var(--text-muted); margin-bottom:0.75rem;">Money received in GCash but not linked to any order. Use rematch or force-match from the main admin dashboard.</p>
        <table class="admin-table">
            <thead><tr><th>Sender</th><th>Phone</th><th>Amount</th><th>Received</th></tr></thead>
            <tbody>
                <?php foreach ($unmatched_payments as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['sender'] ?? '?') ?></td>
                    <td style="font-size:0.75rem; color:var(--text-muted);"><?= htmlspecialchars($p['sender_phone'] ?? '') ?></td>
                    <td style="color:#fbbf24; font-weight:700;">₱<?= number_format((float)$p['amount'], 2) ?></td>
                    <td style="font-size:0.7rem; color:var(--text-muted);"><?= date('M j g:ia', strtotime($p['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php
    $no_issues = empty($issues['should_be_approved'])
        && empty($issues['cancelled_with_pay'])
        && empty($issues['data_drift'])
        && empty($issues['no_listener_order'])
        && empty($unmatched_payments);
    ?>
    <?php if ($no_issues): ?>
    <div class="reg-card" style="text-align:center; padding:2rem;">
        <i class="bi bi-check-circle-fill" style="font-size:3rem; color:var(--success); display:block; margin-bottom:0.75rem;"></i>
        <h3 style="color:var(--success); margin-bottom:0.25rem;">All Good</h3>
        <p style="color:var(--text-muted);">No reconciliation issues found. Local DB and listener API agree on all orders.</p>
    </div>
    <?php endif; ?>

</div>

</body>
</html>
