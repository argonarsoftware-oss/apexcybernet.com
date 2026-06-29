<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/layout.php';
m_require_login();

$user = current_user($pdo);

$unread_notifs = 0;
try {
    $un = $pdo->prepare("SELECT COUNT(*) FROM user_notifications WHERE (account_id = ? OR account_id IS NULL) AND is_read = 0");
    $un->execute([$user['id']]);
    $unread_notifs = (int)$un->fetchColumn();
} catch (Exception $e) {}

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

<!-- Quick Actions -->
<div class="quick" style="margin-top:1rem;">
    <a href="<?= m_base('tournament.php') ?>" class="quick-btn">
        <i class="bi bi-trophy-fill" style="color:#fbbf24;"></i>Tournament
    </a>
    <a href="<?= m_base('dashboard.php') ?>" class="quick-btn">
        <i class="bi bi-speedometer2"></i>Dashboard
    </a>
    <a href="<?= m_base('notifications.php') ?>" class="quick-btn">
        <i class="bi bi-bell-fill"></i>Alerts
    </a>
    <a href="<?= m_base('profile.php') ?>" class="quick-btn">
        <i class="bi bi-person-fill"></i>Profile
    </a>
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
