<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/layout.php';
m_require_login();

$user = current_user($pdo);
$stmt = $pdo->prepare("SELECT h_coins FROM accounts WHERE id = ?");
$stmt->execute([$user['id']]);
$hc = (int)$stmt->fetchColumn();

$txns = $pdo->prepare("SELECT type, amount, reason, ref, created_at FROM h_coin_transactions
    WHERE account_id = ? ORDER BY id DESC LIMIT 10");
$txns->execute([$user['id']]);
$transactions = $txns->fetchAll();

$unread_notifs = 0;
try {
    $un = $pdo->prepare("SELECT COUNT(*) FROM user_notifications WHERE (account_id = ? OR account_id IS NULL) AND is_read = 0");
    $un->execute([$user['id']]);
    $unread_notifs = (int)$un->fetchColumn();
} catch (Exception $e) {}

function txn_label(array $t): string {
    if ($t['reason'] === 'send')     return 'Sent to ' . ltrim($t['ref'] ?? '', 'to:');
    if ($t['reason'] === 'received') return 'Received from ' . ltrim($t['ref'] ?? '', 'from:');
    return ucfirst($t['reason'] ?? 'Transaction');
}
function txn_time(string $dt): string {
    $ts = strtotime($dt);
    $diff = time() - $ts;
    if ($diff < 60)    return 'just now';
    if ($diff < 3600)  return (int)($diff/60) . 'm ago';
    if ($diff < 86400) return (int)($diff/3600) . 'h ago';
    return date('M j', $ts);
}

m_head('Home');
?>

<div class="m-top" style="justify-content:space-between;">
    <div>
        <div style="font-size:0.72rem;color:var(--muted);">Good <?= date('H') < 12 ? 'morning' : (date('H') < 18 ? 'afternoon' : 'evening') ?>,</div>
        <div style="font-weight:800;font-size:1rem;"><?= htmlspecialchars($user['display_name']) ?></div>
    </div>
    <div style="display:flex;align-items:center;gap:0.6rem;">
        <a href="<?= m_base('notifications.php') ?>" class="m-bell-btn" id="mBellBtn" title="Notifications">
            <i class="bi bi-bell-fill"></i>
            <span class="m-bell-badge" id="mBellBadge" style="<?= $unread_notifs > 0 ? '' : 'display:none;' ?>"><?= $unread_notifs > 99 ? '99+' : $unread_notifs ?></span>
        </a>
            <a href="<?= m_base('profile.php') ?>" style="width:36px;height:36px;display:flex;align-items:center;justify-content:center;background:var(--card);border:1px solid var(--border);border-radius:10px;font-size:17px;color:var(--accent-l);" title="Profile"><i class="bi bi-person-fill"></i></a>
        <a href="<?= m_base('logout.php') ?>" class="m-top-right" title="Logout" style="display:flex;align-items:center;gap:0.3rem;font-size:0.82rem;font-weight:700;"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>
</div>
<style>
.m-bell-btn{position:relative;width:36px;height:36px;display:flex;align-items:center;justify-content:center;background:var(--card);border:1px solid var(--border);border-radius:10px;font-size:17px;color:var(--accent-l);}
.m-bell-badge{position:absolute;top:-4px;right:-4px;background:#ef4444;color:#fff;font-size:10px;font-weight:800;min-width:16px;height:16px;padding:0 4px;border-radius:99px;display:flex;align-items:center;justify-content:center;border:2px solid var(--bg);line-height:1;}
</style>

<!-- Balance -->
<div class="bal-hero">
    <div class="bal-label">HCoin Balance</div>
    <div class="bal-val"><?= number_format($hc) ?> <span class="bal-unit">HC</span></div>
    <div style="margin-top:0.5rem;font-size:0.78rem;color:var(--muted);">≈ ₱<?= number_format($hc, 2) ?> est. market value</div>
</div>

<!-- Quick Actions -->
<div class="quick">
    <a href="<?= m_base('send.php') ?>" class="quick-btn">
        <i class="bi bi-arrow-up-circle-fill"></i>Send
    </a>
    <a href="<?= m_base('receive.php') ?>" class="quick-btn">
        <i class="bi bi-arrow-down-circle-fill"></i>Receive
    </a>
    <a href="<?= m_base('pay.php') ?>" class="quick-btn">
        <i class="bi bi-qr-code-scan"></i>Scan
    </a>
    <a href="<?= m_base('market.php') ?>" class="quick-btn">
        <i class="bi bi-shop-fill"></i>Market
    </a>
</div>
<div class="quick" style="grid-template-columns:repeat(2,1fr);padding-top:0;">
    <a href="<?= m_base('buy.php') ?>" class="quick-btn">
        <i class="bi bi-plus-circle-fill" style="color:#22c55e;"></i>Buy HC
    </a>
    <a href="<?= m_base('dashboard.php') ?>" class="quick-btn">
        <i class="bi bi-trophy-fill" style="color:#fbbf24;"></i>Tournament
    </a>
</div>

<!-- Recent Transactions -->
<div class="card">
    <div class="card-body" style="padding-bottom:0.5rem;">
        <div class="card-title">Recent Activity</div>
    </div>
    <?php if (empty($transactions)): ?>
    <div class="empty"><i class="bi bi-clock-history"></i><p>No transactions yet</p></div>
    <?php endif; ?>
    <?php foreach ($transactions as $t):
        $cr = $t['type'] === 'credit';
    ?>
    <div class="txn-item">
        <div class="txn-ico <?= $cr ? 'ico-cr' : 'ico-dr' ?>">
            <i class="bi bi-arrow-<?= $cr ? 'down' : 'up' ?>"></i>
        </div>
        <div class="txn-body">
            <div class="txn-lbl"><?= htmlspecialchars(txn_label($t)) ?></div>
            <div class="txn-time"><?= txn_time($t['created_at']) ?></div>
        </div>
        <div class="txn-amt <?= $cr ? 'amt-cr' : 'amt-dr' ?>">
            <?= $cr ? '+' : '-' ?><?= number_format($t['amount']) ?> HC
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- View full site -->
<a href="https://apexcybernet.com/?prefer_full=1" style="display:flex;align-items:center;justify-content:space-between;margin:0.5rem 1rem 1rem;padding:0.9rem 1.1rem;background:var(--card);border:1px solid var(--border);border-radius:14px;color:var(--text);text-decoration:none;">
    <div style="display:flex;align-items:center;gap:0.75rem;">
        <div style="width:36px;height:36px;border-radius:10px;background:var(--accent-dim);color:var(--accent-l);display:flex;align-items:center;justify-content:center;font-size:1rem;">
            <i class="bi bi-display"></i>
        </div>
        <div>
            <div style="font-weight:800;font-size:0.88rem;">Open Full Site</div>
            <div style="font-size:0.68rem;color:var(--muted);margin-top:1px;">Switch to the desktop experience</div>
        </div>
    </div>
    <i class="bi bi-arrow-up-right" style="color:var(--muted);"></i>
</a>

<?php m_nav('home'); m_toast(); m_foot(); ?>
<!-- Bell badge is kept in sync by the central poller in mobile/layout.php -->
