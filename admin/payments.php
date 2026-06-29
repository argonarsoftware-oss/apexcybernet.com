<?php
/**
 * Live Payment Listener — recent GCash orders, unmatched payments, phone heartbeat.
 * Split out of the admin dashboard (admin/index.php). Admin role required.
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
$is_owner = (($_SESSION['admin_username'] ?? '') === 'kirfenia');

// ── Listener API: recent orders & unmatched payments ──
function listenerGet($endpoint) {
    $url = LISTENER_URL . $endpoint;
    $opts = ['http' => [
        'method'  => 'GET',
        'header'  => "X-API-Key: " . LISTENER_API_KEY . "\r\n",
        'timeout' => 5,
        'ignore_errors' => true,
    ]];
    $ctx = stream_context_create($opts);
    $resp = @file_get_contents($url, false, $ctx);
    return $resp ? json_decode($resp, true) : null;
}

$listener_orders = [];
$listener_unmatched = [];
$listener_devices = [];
$listener_error = null;

$ord_resp = listenerGet('/api/orders?limit=20');
if ($ord_resp && !empty($ord_resp['success'])) {
    $listener_orders = $ord_resp['orders'] ?? [];
} else {
    $listener_error = 'Could not reach listener API';
}
$pay_resp = listenerGet('/api/payments?matched=false&limit=20');
if ($pay_resp && !empty($pay_resp['success'])) {
    $listener_unmatched = $pay_resp['payments'] ?? [];
}
if ($is_owner) {
    $hb_resp = listenerGet('/api/heartbeat/status');
    if ($hb_resp && !empty($hb_resp['success'])) {
        $listener_devices = $hb_resp['devices'] ?? [];
    }
}

$pageTitle = 'Live Payment Listener — Apex Cybernet Admin';
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
            <h1><i class="bi bi-broadcast" style="color:#fbbf24;"></i> Live Payment Listener</h1>
            <p style="margin:0; font-size:0.8rem; color:var(--text-muted);">Recent GCash orders &amp; unmatched payments · listener.argonar.co</p>
        </div>
        <div class="admin-header-actions">
            <a href="<?= base_url('admin/') ?>" class="btn-back-site"><i class="bi bi-arrow-left"></i> Dashboard</a>
            <a href="<?= base_url('admin/reconciliation.php') ?>" class="btn-back-site"><i class="bi bi-clipboard-data"></i> Reconciliation</a>
            <a href="?" class="btn-back-site"><i class="bi bi-arrow-clockwise"></i> Refresh</a>
        </div>
    </div>

    <div class="admin-section" style="margin-bottom:1.5rem;">
        <div class="admin-section-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem;">
            <h2 style="margin:0;"><i class="bi bi-broadcast" style="color:#fbbf24;"></i> Listener Status</h2>
            <div style="display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;">
                <?php if ($is_owner): ?>
                    <?php
                    $any_healthy = false;
                    $phone_summary = '';
                    foreach ($listener_devices as $d) {
                        if (!empty($d['healthy'])) { $any_healthy = true; }
                        $age = (int)($d['last_seen_age_seconds'] ?? 9999);
                        $age_text = $age < 60 ? "{$age}s" : ($age < 3600 ? round($age/60).'m' : round($age/3600).'h');
                        $batt = $d['battery_pct'] ?? null;
                        $phone_summary .= ($d['device_name'] ?? 'phone') . ' · ' . $age_text . ' ago' . ($batt !== null ? " · {$batt}%" : '') . "\n";
                    }
                    $phone_color = $any_healthy ? 'var(--success)' : '#f87171';
                    $phone_label = empty($listener_devices)
                        ? '⚫ No phone'
                        : ($any_healthy ? '🟢 Phone OK' : '🔴 Phone DOWN');
                    $first_device = $listener_devices[0] ?? null;
                    ?>
                    <span title="<?= htmlspecialchars(trim($phone_summary)) ?>"
                          style="display:inline-flex; align-items:center; gap:0.35rem; font-size:0.7rem; font-weight:700; color:<?= $phone_color ?>; background:rgba(255,255,255,0.04); border:1px solid <?= $phone_color ?>; padding:0.25rem 0.6rem; border-radius:6px;">
                        <span><?= $phone_label ?></span>
                        <?php if ($first_device): ?>
                            <span style="opacity:0.6; font-weight:400;">·</span>
                            <?php
                                $age = (int)($first_device['last_seen_age_seconds'] ?? 0);
                                $age_text = $age < 60 ? "{$age}s ago" : ($age < 3600 ? round($age/60).'m ago' : round($age/3600).'h ago');
                            ?>
                            <span style="font-weight:400;"><?= $age_text ?></span>
                            <?php if ($first_device['battery_pct'] !== null): ?>
                                <span style="opacity:0.6; font-weight:400;">·</span>
                                <?php
                                    $batt = (int)$first_device['battery_pct'];
                                    $batt_icon = $batt >= 75 ? 'bi-battery-full' : ($batt >= 50 ? 'bi-battery-half' : ($batt >= 20 ? 'bi-battery-half' : 'bi-battery'));
                                    $batt_color = $batt >= 50 ? '' : ($batt >= 20 ? '#fbbf24' : '#f87171');
                                ?>
                                <span style="font-weight:600; <?= $batt_color ? "color:$batt_color" : '' ?>">
                                    <i class="bi <?= $batt_icon ?>"></i> <?= $batt ?>%
                                </span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
                <button onclick="rematchPayments()" style="background:#fbbf24; color:#000; border:none; padding:0.4rem 0.85rem; border-radius:8px; font-size:0.75rem; font-weight:700; cursor:pointer;">
                    <i class="bi bi-arrow-repeat"></i> Rematch Now
                </button>
            </div>
        </div>

        <?php if ($listener_error): ?>
            <div class="alert-custom alert-danger" style="margin:0.5rem 0;">
                <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($listener_error) ?>
            </div>
        <?php endif; ?>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(360px, 1fr)); gap:1rem; margin-top:0.75rem;">

            <!-- Recent Orders -->
            <div style="background:var(--bg-card); border:1px solid var(--border); border-radius:10px; padding:1rem;">
                <div style="font-size:0.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px; margin-bottom:0.75rem;">
                    <i class="bi bi-receipt"></i> Recent Orders (<?= count($listener_orders) ?>)
                </div>
                <?php if (empty($listener_orders)): ?>
                    <div style="font-size:0.8rem; color:var(--text-muted); text-align:center; padding:1rem 0;">No orders.</div>
                <?php else: ?>
                    <div style="max-height:380px; overflow-y:auto;">
                    <?php foreach ($listener_orders as $o):
                        $st = $o['status'] ?? '';
                        $color = ['paid' => 'var(--success)', 'pending' => '#fbbf24', 'cancelled' => '#f87171', 'expired' => '#94a3b8'][$st] ?? 'var(--text-muted)';
                        $icon = ['paid' => 'check-circle-fill', 'pending' => 'clock-fill', 'cancelled' => 'x-circle-fill', 'expired' => 'hourglass-bottom'][$st] ?? 'circle';
                    ?>
                        <div style="display:flex; align-items:center; gap:0.5rem; padding:0.5rem 0; border-bottom:1px solid rgba(255,255,255,0.04); font-size:0.75rem;">
                            <i class="bi bi-<?= $icon ?>" style="color:<?= $color ?>; width:1rem;"></i>
                            <div style="flex:1; min-width:0;">
                                <div style="font-weight:700; color:var(--accent-light); font-family:monospace;"><?= htmlspecialchars($o['order_id']) ?></div>
                                <div style="color:var(--text-muted); font-size:0.7rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($o['description'] ?? '') ?></div>
                                <?php if (!empty($o['sender_name'])): ?>
                                    <div style="color:var(--success); font-size:0.65rem;"><i class="bi bi-person"></i> <?= htmlspecialchars($o['sender_name']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div style="text-align:right; flex-shrink:0;">
                                <div style="font-weight:700; color:var(--text);">₱<?= number_format((float)$o['pay_amount'], 2) ?></div>
                                <div style="font-size:0.6rem; color:<?= $color ?>; text-transform:uppercase; font-weight:700;"><?= $st ?></div>
                                <div style="font-size:0.6rem; color:var(--text-muted);"><?= date('M j g:ia', strtotime($o['created_at'])) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Unmatched Payments -->
            <div style="background:var(--bg-card); border:1px solid <?= count($listener_unmatched) > 0 ? 'rgba(239,68,68,0.4)' : 'var(--border)' ?>; border-radius:10px; padding:1rem;">
                <div style="font-size:0.75rem; font-weight:700; color:<?= count($listener_unmatched) > 0 ? '#f87171' : 'var(--text-muted)' ?>; text-transform:uppercase; letter-spacing:1px; margin-bottom:0.75rem;">
                    <i class="bi bi-exclamation-triangle-fill"></i> Unmatched Payments (<?= count($listener_unmatched) ?>)
                </div>
                <?php if (empty($listener_unmatched)): ?>
                    <div style="font-size:0.8rem; color:var(--text-muted); text-align:center; padding:1rem 0;">All payments are matched.</div>
                <?php else: ?>
                    <div style="max-height:380px; overflow-y:auto;">
                    <?php foreach ($listener_unmatched as $p): ?>
                        <div style="padding:0.6rem; background:rgba(239,68,68,0.05); border:1px solid rgba(239,68,68,0.2); border-radius:8px; margin-bottom:0.5rem; font-size:0.75rem;">
                            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:0.5rem; margin-bottom:0.35rem;">
                                <div style="flex:1; min-width:0;">
                                    <div style="font-weight:700; color:var(--text);"><?= htmlspecialchars($p['sender'] ?? 'Unknown') ?></div>
                                    <div style="color:var(--text-muted); font-size:0.7rem;"><?= htmlspecialchars($p['sender_phone'] ?? '') ?></div>
                                </div>
                                <div style="font-weight:800; color:#fbbf24; font-size:1rem; white-space:nowrap;">₱<?= number_format((float)$p['amount'], 2) ?></div>
                            </div>
                            <div style="font-size:0.6rem; color:var(--text-muted); margin-bottom:0.35rem;">
                                <?= date('M j, g:ia', strtotime($p['created_at'])) ?>
                            </div>
                            <button onclick="forceMatchPrompt('<?= htmlspecialchars($p['payment_id'], ENT_QUOTES) ?>', '<?= number_format((float)$p['amount'], 2) ?>', '<?= htmlspecialchars($p['sender'] ?? '', ENT_QUOTES) ?>')"
                                    style="background:#dc2626; color:#fff; border:none; padding:0.3rem 0.65rem; border-radius:6px; font-size:0.65rem; font-weight:700; cursor:pointer; width:100%;">
                                <i class="bi bi-link-45deg"></i> Force Match to Order
                            </button>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<script>
function rematchPayments() {
    const btn = event.target.closest('button');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Rematching...';
    fetch('https://listener.argonar.co/api/payments/rematch', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-API-Key': 'kirfenia123' },
        body: JSON.stringify({ limit: 200, lookback_hours: 168 })
    })
    .then(r => r.json())
    .then(data => {
        alert('Rematch complete!\nProcessed: ' + (data.processed || 0) + '\nMatched: ' + (data.matched || 0) + '\nStill unmatched: ' + (data.remaining_unmatched || 0));
        location.reload();
    })
    .catch(err => {
        alert('Rematch failed: ' + err.message);
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Rematch Now';
    });
}

function forceMatchPrompt(paymentId, amount, sender) {
    const orderId = prompt('Force match payment ₱' + amount + ' from ' + sender + '\n\nEnter the order ref code (e.g. DOTA-T-XXXX):');
    if (!orderId || orderId.trim() === '') return;
    fetch('https://listener.argonar.co/api/payments/force-match', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-API-Key': 'kirfenia123' },
        body: JSON.stringify({ payment_id: paymentId, order_id: orderId.trim().toUpperCase() })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Matched! Order ' + orderId + ' is now paid.');
            location.reload();
        } else {
            alert('Force match failed: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(err => alert('Request failed: ' + err.message));
}
</script>
</body>
</html>
