<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/layout.php';
m_require_login();

$user = current_user($pdo);
$uid  = (int)$user['id'];

// Auto-create table (same schema as api/notifications.php)
$pdo->exec("CREATE TABLE IF NOT EXISTS user_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT DEFAULT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    icon VARCHAR(50) NOT NULL DEFAULT 'bi-bell',
    link VARCHAR(255) DEFAULT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY (account_id), KEY (is_read), KEY (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Mark all as read on load (user came here to see them)
$pdo->prepare("UPDATE user_notifications SET is_read = 1 WHERE account_id = ? AND is_read = 0")
    ->execute([$uid]);

$notifs = $pdo->prepare("
    SELECT id, title, message, icon, link, is_read, created_at
    FROM user_notifications
    WHERE account_id = ? OR account_id IS NULL
    ORDER BY created_at DESC
    LIMIT 50
");
$notifs->execute([$uid]);
$items = $notifs->fetchAll();

function m_time_ago(string $dt): string {
    $diff = time() - strtotime($dt);
    if ($diff < 60)    return 'just now';
    if ($diff < 3600)  return (int)($diff/60) . 'm ago';
    if ($diff < 86400) return (int)($diff/3600) . 'h ago';
    if ($diff < 604800) return (int)($diff/86400) . 'd ago';
    return date('M j', strtotime($dt));
}

m_head('Notifications');
?>

<div class="m-top">
    <a href="./" class="m-back"><i class="bi bi-arrow-left"></i></a>
    <div class="m-top-title">Notifications</div>
</div>

<div id="mNotifList">
<?php if (empty($items)): ?>
    <div class="empty"><i class="bi bi-bell-slash"></i><p>No notifications yet.</p></div>
<?php else: ?>
    <?php foreach ($items as $n): ?>
    <a class="m-notif" href="<?= htmlspecialchars($n['link'] ?: '#') ?>">
        <div class="m-notif-ico"><i class="bi <?= htmlspecialchars($n['icon'] ?: 'bi-bell') ?>"></i></div>
        <div class="m-notif-body">
            <div class="m-notif-title"><?= htmlspecialchars($n['title']) ?></div>
            <div class="m-notif-msg"><?= htmlspecialchars($n['message']) ?></div>
            <div class="m-notif-time"><?= m_time_ago($n['created_at']) ?></div>
        </div>
    </a>
    <?php endforeach; ?>
<?php endif; ?>
</div>

<style>
.m-notif{display:flex;align-items:flex-start;gap:0.85rem;padding:1rem 1.1rem;border-bottom:1px solid var(--border);color:var(--text);}
.m-notif:active{background:rgba(124,58,237,0.04);}
.m-notif-ico{width:40px;height:40px;border-radius:12px;background:var(--accent-dim);color:var(--accent-l);display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;}
.m-notif-body{flex:1;min-width:0;}
.m-notif-title{font-size:0.88rem;font-weight:800;}
.m-notif-msg{font-size:0.78rem;color:var(--muted);margin-top:2px;line-height:1.35;}
.m-notif-time{font-size:0.68rem;color:var(--muted);margin-top:5px;}
</style>

<?php m_nav('home'); m_toast(); m_foot(); ?>
<script>
// New notifications arrive via the 'argonar:notification' CustomEvent
// fired by the central poller in mobile/layout.php.
(function(){
    function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(m){return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m];});}
    window.addEventListener('argonar:notification', function(e) {
        var n = e.detail;
        var list = document.getElementById('mNotifList');
        if (!list) return;
        var emptyEl = list.querySelector('.empty');
        if (emptyEl) emptyEl.remove();
        var a = document.createElement('a');
        a.className = 'm-notif';
        a.href = n.link || '#';
        a.innerHTML =
            '<div class="m-notif-ico"><i class="bi ' + esc(n.icon || 'bi-bell') + '"></i></div>' +
            '<div class="m-notif-body">' +
            '  <div class="m-notif-title">' + esc(n.title) + '</div>' +
            '  <div class="m-notif-msg">' + esc(n.message) + '</div>' +
            '  <div class="m-notif-time">just now</div>' +
            '</div>';
        list.insertBefore(a, list.firstChild);
    });
})();
</script>
