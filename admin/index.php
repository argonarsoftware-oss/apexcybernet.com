<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/bracket_logic.php';
ensure_reserved_columns($pdo);
require_once __DIR__ . '/../includes/listener-api.php';

// Admin accounts — username => [password, role]
// Roles: 'admin' (full access), 'staff' (no listener payment dashboard)
// Owner-only features (phone heartbeat) are gated by username === 'kirfenia'
$admin_users = [
    'kirfenia' => ['password' => 'Kirfenia123@', 'role' => 'admin'],
    'admin'    => ['password' => 'Kirfenia123@', 'role' => 'admin'],
    'raffy'    => ['password' => 'apexcybernet2026',  'role' => 'staff'],
];

$admin_token = 'apexcybernet-admin-2026-token';

// Token-based login (for CLI/API access) — defaults to admin role
if (isset($_GET['token']) && $_GET['token'] === $admin_token) {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_username'] = 'admin';
    $_SESSION['admin_role'] = 'admin';
}

// PIN config — accounts that require a PIN after password login
$admin_pins = [
    'kirfenia' => '9998',
];

// ── Auto-create login_attempts table ──
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        username     VARCHAR(60) NOT NULL,
        ip           VARCHAR(60),
        user_agent   VARCHAR(300),
        success      TINYINT(1) DEFAULT 0,
        attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login'])) {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    if (isset($admin_users[$u]) && $admin_users[$u]['password'] === $p) {
        // Log successful login attempt on kirfenia account
        if ($u === 'kirfenia') {
            try {
                $pdo->prepare("INSERT INTO login_attempts (username, ip, user_agent, success) VALUES (?,?,?,1)")
                    ->execute([$u, $_SERVER['REMOTE_ADDR'] ?? '', substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 300)]);
            } catch (Exception $e) {}
        }
        if (isset($admin_pins[$u])) {
            // Password correct, but PIN required — park credentials, show PIN screen
            $_SESSION['admin_pin_pending'] = true;
            $_SESSION['admin_pin_user']    = $u;
        } else {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $u;
            $_SESSION['admin_role']     = $admin_users[$u]['role'];
        }
        header('Location: ' . base_url('admin/'));
        exit;
    } else {
        $login_error = 'Incorrect username or password.';
        // Log failed attempt targeting kirfenia
        if ($u === 'kirfenia') {
            try {
                $pdo->prepare("INSERT INTO login_attempts (username, ip, user_agent, success) VALUES (?,?,?,0)")
                    ->execute([$u, $_SERVER['REMOTE_ADDR'] ?? '', substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 300)]);
            } catch (Exception $e) {}
        }
    }
}

// Handle PIN verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_pin_verify'])) {
    $pin_user = $_SESSION['admin_pin_user'] ?? '';
    $pin_input = $_POST['pin'] ?? '';
    if ($pin_user && isset($admin_pins[$pin_user]) && $pin_input === $admin_pins[$pin_user]) {
        unset($_SESSION['admin_pin_pending'], $_SESSION['admin_pin_user']);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $pin_user;
        $_SESSION['admin_role']     = $admin_users[$pin_user]['role'];
        header('Location: ' . base_url('admin/'));
        exit;
    } else {
        $pin_error = 'Incorrect PIN.';
    }
}

// Show PIN screen if pending
if (!empty($_SESSION['admin_pin_pending'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>PIN Verification — Admin</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <link rel="stylesheet" href="<?= base_url('css/app.css') ?>">
        <style>
        .pin-dots { display:flex; gap:0.75rem; justify-content:center; margin:1.5rem 0; }
        .pin-dot {
            width:48px; height:48px; border-radius:12px; border:2px solid var(--border);
            background:var(--bg-dark); display:flex; align-items:center; justify-content:center;
            font-size:1.5rem; font-weight:900; color:var(--accent-light); transition:border-color 0.15s;
        }
        .pin-dot.filled { border-color:var(--accent); background:rgba(124,58,237,0.1); }
        .pin-pad { display:grid; grid-template-columns:repeat(3,1fr); gap:0.5rem; max-width:260px; margin:0 auto; }
        .pin-key {
            padding:0.9rem; border:1px solid var(--border); border-radius:12px; background:var(--bg-card);
            color:var(--text); font-size:1.2rem; font-weight:800; cursor:pointer; font-family:inherit;
            transition:all 0.1s; text-align:center;
        }
        .pin-key:hover { border-color:var(--accent); background:rgba(124,58,237,0.08); }
        .pin-key:active { transform:scale(0.95); }
        .pin-key.backspace { font-size:1rem; color:var(--text-muted); }
        .pin-key.empty { visibility:hidden; cursor:default; }
        </style>
    </head>
    <body>
        <div style="max-width:400px; margin:60px auto; padding:0 1rem;">
            <div class="reg-card" style="text-align:center;">
                <h2><i class="bi bi-shield-lock-fill" style="color:var(--accent);"></i></h2>
                <h3 style="font-weight:800; margin-bottom:0.25rem;">Enter PIN</h3>
                <p class="subtitle" style="font-size:0.8rem; color:var(--text-muted);">
                    Welcome back, <strong><?= htmlspecialchars($_SESSION['admin_pin_user'] ?? '') ?></strong>
                </p>
                <?php if (!empty($pin_error)): ?>
                    <div class="alert-custom alert-danger"><?= htmlspecialchars($pin_error) ?></div>
                <?php endif; ?>
                <div class="pin-dots">
                    <div class="pin-dot" id="d0"></div>
                    <div class="pin-dot" id="d1"></div>
                    <div class="pin-dot" id="d2"></div>
                    <div class="pin-dot" id="d3"></div>
                </div>
                <div class="pin-pad">
                    <?php for ($i = 1; $i <= 9; $i++): ?>
                    <button class="pin-key" onclick="pinKey('<?= $i ?>')"><?= $i ?></button>
                    <?php endfor; ?>
                    <div class="pin-key empty"></div>
                    <button class="pin-key" onclick="pinKey('0')">0</button>
                    <button class="pin-key backspace" onclick="pinBack()"><i class="bi bi-backspace"></i></button>
                </div>
                <form method="POST" id="pinForm" style="display:none;">
                    <input type="hidden" name="admin_pin_verify" value="1">
                    <input type="hidden" name="pin" id="pinInput">
                </form>
                <div style="margin-top:1.5rem;">
                    <a href="<?= base_url('admin/logout.php') ?>" style="font-size:0.75rem; color:var(--text-muted);">
                        <i class="bi bi-arrow-left"></i> Back to login
                    </a>
                </div>
            </div>
        </div>
        <script>
        let pin = '';
        function updateDots() {
            for (let i = 0; i < 4; i++) {
                const d = document.getElementById('d' + i);
                if (i < pin.length) { d.classList.add('filled'); d.textContent = '\u2022'; }
                else { d.classList.remove('filled'); d.textContent = ''; }
            }
        }
        function pinKey(n) {
            if (pin.length >= 4) return;
            pin += n;
            updateDots();
            if (pin.length === 4) {
                document.getElementById('pinInput').value = pin;
                setTimeout(() => document.getElementById('pinForm').submit(), 200);
            }
        }
        function pinBack() {
            pin = pin.slice(0, -1);
            updateDots();
        }
        </script>
    </body>
    </html>
    <?php
    exit;
}

// Show login form if not authenticated
if (empty($_SESSION['admin_logged_in'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login — Apex Cybernet Tournament</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <link rel="stylesheet" href="<?= base_url('css/app.css') ?>">
    </head>
    <body>
        <div style="max-width:400px; margin:80px auto; padding:0 1rem;">
            <div class="reg-card" style="text-align:center;">
                <h2><i class="bi bi-shield-lock"></i> Admin Login</h2>
                <p class="subtitle">Enter your credentials to access the dashboard</p>
                <?php if (!empty($login_error)): ?>
                    <div class="alert-custom alert-danger"><?= htmlspecialchars($login_error) ?></div>
                <?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="admin_login" value="1">
                    <div class="mb-3">
                        <input type="text" name="username" class="form-control" placeholder="Username" required autofocus autocomplete="username">
                    </div>
                    <div class="mb-3">
                        <input type="password" name="password" class="form-control" placeholder="Password" required autocomplete="current-password">
                    </div>
                    <button type="submit" class="btn-submit">Login</button>
                </form>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Convenience flag — used to gate the listener payment dashboard
$is_admin = (($_SESSION['admin_role'] ?? '') === 'admin');
// Owner-only flag — used to gate the phone heartbeat indicator
$is_owner = (($_SESSION['admin_username'] ?? '') === 'kirfenia');

// ── Login attempt alerts (kirfenia only) ──
$login_alerts = [];
if ($is_owner) {
    try {
        $login_alerts = $pdo->query(
            "SELECT * FROM login_attempts WHERE username='kirfenia'
             ORDER BY attempted_at DESC LIMIT 20"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// --- Admin Dashboard ---

$game_filter = $_GET['game'] ?? 'all';
$valid_games = [
    'valorant'  => 'Valorant',
    'crossfire' => 'CrossFire',
    'dota2'     => 'Dota 2',
];

$game_icons = [
    'valorant'  => 'bi-crosshair',
    'crossfire' => 'bi-bullseye',
    'dota2'     => 'bi-shield-shaded',
];

// Handle admin add team
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_add_team'])) {
    $add_game = $_POST['add_game'] ?? '';
    $add_team = trim($_POST['add_team_name'] ?? '');
    $add_status = $_POST['add_status'] ?? 'approved';

    if (isset($valid_games[$add_game]) && $add_team !== '') {
        $prefixes = ['valorant' => 'VAL', 'crossfire' => 'CF', 'dota2' => 'DOTA'];
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $rand = '';
        for ($i = 0; $i < 4; $i++) $rand .= $chars[random_int(0, strlen($chars) - 1)];
        $ref = $prefixes[$add_game] . '-T-' . $rand;

        $stmt = $pdo->prepare("INSERT INTO teams (game, team_name, ref_code, member_1, member_2, member_3, member_4, member_5, payment_proof, status) VALUES (?, ?, ?, '', '', '', '', '', '', ?)");
        $stmt->execute([$add_game, $add_team, $ref, $add_status]);
        header('Location: ' . base_url('admin/?game=' . $add_game));
        exit;
    }
}

// Summary counts
$total_teams = (int)$pdo->query("SELECT COUNT(*) FROM teams")->fetchColumn();
$total_solo  = (int)$pdo->query("SELECT COUNT(*) FROM solo_players")->fetchColumn();
$approved_total = (int)$pdo->query("SELECT (SELECT COUNT(*) FROM teams WHERE status='approved') + (SELECT COUNT(*) FROM solo_players WHERE status='approved')")->fetchColumn();
$pending_payments = (int)$pdo->query("SELECT (SELECT COUNT(*) FROM teams WHERE status='pending') + (SELECT COUNT(*) FROM solo_players WHERE status='pending')")->fetchColumn();
$rejected_total = (int)$pdo->query("SELECT (SELECT COUNT(*) FROM teams WHERE status='rejected') + (SELECT COUNT(*) FROM solo_players WHERE status='rejected')")->fetchColumn();

// Per-game counts
$game_stats = [];
foreach ($valid_games as $slug => $name) {
    $tc = (int)$pdo->prepare("SELECT COUNT(*) FROM teams WHERE game = ?");
    $tc = $pdo->prepare("SELECT COUNT(*) FROM teams WHERE game = ?");
    $tc->execute([$slug]);
    $team_count = (int)$tc->fetchColumn();

    $sc = $pdo->prepare("SELECT COUNT(*) FROM solo_players WHERE game = ?");
    $sc->execute([$slug]);
    $solo_count = (int)$sc->fetchColumn();

    $ac = $pdo->prepare("SELECT COUNT(*) FROM teams WHERE game = ? AND status = 'approved'");
    $ac->execute([$slug]);
    $approved_teams = (int)$ac->fetchColumn();

    $game_stats[$slug] = [
        'teams' => $team_count,
        'solos' => $solo_count,
        'approved' => $approved_teams,
        'effective' => $approved_teams + floor($solo_count / 5),
    ];
}

// Fetch teams
if ($game_filter !== 'all' && isset($valid_games[$game_filter])) {
    $stmt = $pdo->prepare("SELECT * FROM teams WHERE game = ? ORDER BY created_at DESC");
    $stmt->execute([$game_filter]);
} else {
    $stmt = $pdo->query("SELECT * FROM teams ORDER BY created_at DESC");
}
$teams = $stmt->fetchAll();

// Fetch solo players
if ($game_filter !== 'all' && isset($valid_games[$game_filter])) {
    $stmt2 = $pdo->prepare("SELECT * FROM solo_players WHERE game = ? ORDER BY created_at DESC");
    $stmt2->execute([$game_filter]);
} else {
    $stmt2 = $pdo->query("SELECT * FROM solo_players ORDER BY created_at DESC");
}
$solos = $stmt2->fetchAll();

// Disputes
$open_disputes = (int)$pdo->query("SELECT COUNT(*) FROM disputes WHERE status = 'open'")->fetchColumn();
$disputes = $pdo->query("SELECT * FROM disputes ORDER BY created_at DESC LIMIT 10")->fetchAll();

// Account claims
$pending_claims = $pdo->query("SELECT * FROM accounts WHERE claim_status = 'pending' ORDER BY created_at DESC")->fetchAll();
$all_claims = $pdo->query("SELECT * FROM accounts ORDER BY created_at DESC LIMIT 50")->fetchAll();

// Announcements
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_announcement'])) {
    $ann_title = trim($_POST['ann_title'] ?? '');
    $ann_content = trim($_POST['ann_content'] ?? '');
    $ann_type = $_POST['ann_type'] ?? 'news';
    if ($ann_title !== '' && $ann_content !== '') {
        $stmt = $pdo->prepare("INSERT INTO announcements (title, content, type) VALUES (?, ?, ?)");
        $stmt->execute([$ann_title, $ann_content, $ann_type]);
        header('Location: ' . base_url('admin/'));
        exit;
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_announcement'])) {
    $pdo->prepare("DELETE FROM announcements WHERE id = ?")->execute([(int)$_POST['ann_id']]);
    header('Location: ' . base_url('admin/'));
    exit;
}
$all_announcements = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC")->fetchAll();

// ── Notifications ──
// Auto-create table
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    $n_title   = trim($_POST['notif_title'] ?? '');
    $n_message = trim($_POST['notif_message'] ?? '');
    $n_icon    = trim($_POST['notif_icon'] ?? 'bi-bell');
    $n_link    = trim($_POST['notif_link'] ?? '') ?: null;
    $n_target  = $_POST['notif_target'] ?? 'all';

    if ($n_title !== '' && $n_message !== '') {
        if ($n_target === 'all') {
            // Broadcast: insert one row per active user
            $users = $pdo->query("SELECT id FROM accounts WHERE claim_status = 'approved'")->fetchAll();
            $ins = $pdo->prepare("INSERT INTO user_notifications (account_id, title, message, icon, link) VALUES (?, ?, ?, ?, ?)");
            foreach ($users as $u) {
                $ins->execute([$u['id'], $n_title, $n_message, $n_icon, $n_link]);
            }
        } else {
            $target_id = (int)$n_target;
            if ($target_id > 0) {
                $pdo->prepare("INSERT INTO user_notifications (account_id, title, message, icon, link) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$target_id, $n_title, $n_message, $n_icon, $n_link]);
            }
        }
        header('Location: ' . base_url('admin/'));
        exit;
    }
}

// Recent notifications sent
$recent_notifs = $pdo->query("SELECT n.*, a.display_name FROM user_notifications n LEFT JOIN accounts a ON a.id = n.account_id ORDER BY n.created_at DESC LIMIT 20")->fetchAll();
// All accounts for dropdown
$all_accounts = $pdo->query("SELECT id, display_name, email FROM accounts WHERE claim_status = 'approved' ORDER BY display_name ASC")->fetchAll();

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

if ($is_admin) {
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
}

// Recent activity (last 5 registrations)
$recent = $pdo->query("
    (SELECT 'team' as type, team_name as name, game, status, created_at FROM teams ORDER BY created_at DESC LIMIT 5)
    UNION ALL
    (SELECT 'solo' as type, player_name as name, game, status, created_at FROM solo_players ORDER BY created_at DESC LIMIT 5)
    ORDER BY created_at DESC LIMIT 8
")->fetchAll();

$pageTitle = 'Admin Dashboard — Apex Cybernet Tournament';
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

<?php if ($is_owner && !empty($login_alerts)): ?>
<?php
    $failed  = array_filter($login_alerts, fn($r) => !$r['success']);
    $success = array_filter($login_alerts, fn($r) =>  $r['success']);
    $new_failed = array_filter($failed, fn($r) => strtotime($r['attempted_at']) > (time() - 86400));
?>
<?php if (!empty($new_failed) || count($success) > 1): ?>
<div id="login-alert-bar" style="background:rgba(248,113,113,0.1);border-bottom:1px solid rgba(248,113,113,0.3);padding:0.6rem 1.25rem;display:flex;align-items:flex-start;gap:0.85rem;flex-wrap:wrap;">
    <i class="bi bi-shield-exclamation" style="color:#f87171;font-size:1.1rem;flex-shrink:0;margin-top:2px;"></i>
    <div style="flex:1;min-width:0;">
        <div style="font-size:0.8rem;font-weight:700;color:#f87171;margin-bottom:0.3rem;">
            <?php if (!empty($new_failed)): ?>
                <?= count($new_failed) ?> failed login attempt<?= count($new_failed) > 1 ? 's' : '' ?> on your account in the last 24 hours
            <?php else: ?>
                Login activity on your kirfenia account
            <?php endif; ?>
        </div>
        <div style="display:flex;flex-direction:column;gap:0.2rem;">
        <?php foreach (array_slice($login_alerts, 0, 8) as $a): ?>
            <div style="font-size:0.73rem;color:<?= $a['success'] ? '#34d399' : '#fca5a5' ?>;">
                <i class="bi bi-<?= $a['success'] ? 'check-circle' : 'x-circle' ?>"></i>
                <strong><?= $a['success'] ? 'Success' : 'Failed' ?></strong>
                &nbsp;·&nbsp;<?= htmlspecialchars($a['ip']) ?>
                &nbsp;·&nbsp;<?= htmlspecialchars(date('M j, g:ia', strtotime($a['attempted_at']))) ?>
                &nbsp;·&nbsp;<span style="color:#6b7280;font-size:0.68rem;"><?= htmlspecialchars(substr($a['user_agent'] ?? '', 0, 80)) ?></span>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
    <button onclick="document.getElementById('login-alert-bar').style.display='none'"
        style="background:transparent;border:none;color:#6b7280;font-size:1rem;cursor:pointer;flex-shrink:0;padding:0;">
        <i class="bi bi-x-lg"></i>
    </button>
</div>
<?php endif; ?>
<?php endif; ?>

<div class="admin-container">
    <!-- Header -->
    <div class="admin-header">
        <div>
            <h1><i class="bi bi-speedometer2"></i> Admin Dashboard</h1>
            <p style="margin:0; font-size:0.8rem; color:var(--text-muted);">
                Tournament Management &middot;
                <span style="color:<?= $is_admin ? '#fbbf24' : 'var(--accent-light)' ?>;">
                    <i class="bi bi-person-fill"></i> <?= htmlspecialchars($_SESSION['admin_username'] ?? '') ?>
                    (<?= htmlspecialchars($_SESSION['admin_role'] ?? '') ?>)
                </span>
            </p>
        </div>
        <div class="admin-header-actions">
            <a href="<?= base_url('admin/brackets.php') ?>" class="btn-back-site"><i class="bi bi-diagram-3"></i> Brackets</a>
            <a href="<?= base_url('admin/tools.php') ?>" class="btn-back-site" style="border-color:rgba(167,139,250,0.35); color:#a78bfa;"><i class="bi bi-tools"></i> Admin Tools</a>
            <?php if ($is_owner): ?>
            <?php endif; ?>
            <a href="<?= base_url('admin/chat.php') ?>" class="btn-back-site" style="border-color:rgba(167,139,250,0.35); color:#a78bfa;"><i class="bi bi-chat-dots-fill"></i> Chat</a>
            <a href="<?= base_url('admin/matchmaking.php') ?>" class="btn-back-site"><i class="bi bi-puzzle"></i> Matchmaking</a>
            <a href="<?= base_url('admin/accounts.php') ?>" class="btn-back-site" style="border-color:rgba(139,92,246,0.35); color:#a78bfa;"><i class="bi bi-people-fill"></i> Accounts</a>
            <?php if ($is_admin): ?>
                <a href="<?= base_url('admin/reconciliation.php') ?>" class="btn-back-site"><i class="bi bi-clipboard-data"></i> Reconcile</a>
            <?php endif; ?>
            <a href="<?= base_url() ?>" class="btn-back-site"><i class="bi bi-arrow-left"></i> Site</a>
            <a href="<?= base_url('admin/logout.php') ?>" class="btn-logout"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </div>

    <!-- Overview Stats -->
    <div class="summary-cards">
        <div class="summary-card">
            <div class="summary-icon"><i class="bi bi-people-fill"></i></div>
            <div class="summary-info">
                <div class="summary-number"><?= $total_teams ?></div>
                <div class="summary-label">Teams</div>
            </div>
        </div>
        <div class="summary-card">
            <div class="summary-icon" style="background:rgba(59,130,246,0.15); color:#3b82f6;"><i class="bi bi-person-fill"></i></div>
            <div class="summary-info">
                <div class="summary-number"><?= $total_solo ?></div>
                <div class="summary-label">Solo Players</div>
            </div>
        </div>
        <div class="summary-card" style="border-color:rgba(34,197,94,0.3);">
            <div class="summary-icon" style="background:rgba(34,197,94,0.15); color:var(--success);"><i class="bi bi-check-circle-fill"></i></div>
            <div class="summary-info">
                <div class="summary-number"><?= $approved_total ?></div>
                <div class="summary-label">Locked In</div>
            </div>
        </div>
        <div class="summary-card summary-card-warning">
            <div class="summary-icon"><i class="bi bi-clock-fill"></i></div>
            <div class="summary-info">
                <div class="summary-number"><?= $pending_payments ?></div>
                <div class="summary-label">Pending</div>
            </div>
        </div>
        <?php if ($open_disputes > 0): ?>
        <div class="summary-card" style="border-color:rgba(239,68,68,0.3);">
            <div class="summary-icon" style="background:rgba(239,68,68,0.15); color:var(--danger);"><i class="bi bi-flag-fill"></i></div>
            <div class="summary-info">
                <div class="summary-number"><?= $open_disputes ?></div>
                <div class="summary-label">Open Disputes</div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($is_admin): ?>
    <!-- Live Payment Listener (admin role only) -->
    <div class="admin-section" style="margin-bottom:1.5rem;">
        <div class="admin-section-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem;">
            <h2 style="margin:0;"><i class="bi bi-broadcast" style="color:#fbbf24;"></i> Live Payment Listener</h2>
            <div style="display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;">
                <?php if ($is_owner): ?>
                    <?php
                    // Phone heartbeat status pill — kirfenia only
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
                <span style="font-size:0.7rem; color:var(--text-muted);">listener.argonar.co</span>
                <button onclick="rematchPayments()" style="background:#fbbf24; color:#000; border:none; padding:0.4rem 0.85rem; border-radius:8px; font-size:0.75rem; font-weight:700; cursor:pointer;">
                    <i class="bi bi-arrow-repeat"></i> Rematch Now
                </button>
                <a href="?game=<?= $game_filter ?>" style="background:rgba(255,255,255,0.08); color:var(--text); padding:0.4rem 0.75rem; border-radius:8px; font-size:0.75rem; text-decoration:none;">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </a>
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
    <?php endif; ?>

    <!-- Per-Game Breakdown -->
    <div class="admin-game-cards">
        <?php foreach ($valid_games as $slug => $name):
            $gs = $game_stats[$slug];
            $max = 16;
            $pct = min(100, round(($gs['effective'] / $max) * 100));
        ?>
        <a href="<?= base_url('admin/?game=' . $slug) ?>" class="admin-game-card <?= $game_filter === $slug ? 'admin-game-card-active' : '' ?>">
            <div class="admin-game-card-header">
                <i class="bi <?= $game_icons[$slug] ?>"></i>
                <span><?= $name ?></span>
            </div>
            <div class="admin-game-card-stats">
                <div class="admin-game-stat">
                    <span class="admin-game-stat-num"><?= $gs['teams'] ?></span>
                    <span class="admin-game-stat-label">Teams</span>
                </div>
                <div class="admin-game-stat">
                    <span class="admin-game-stat-num"><?= $gs['solos'] ?></span>
                    <span class="admin-game-stat-label">Solo</span>
                </div>
                <div class="admin-game-stat">
                    <span class="admin-game-stat-num"><?= $gs['effective'] ?>/<?= $max ?></span>
                    <span class="admin-game-stat-label">Slots</span>
                </div>
            </div>
            <div class="admin-game-bar">
                <div class="admin-game-bar-fill" style="width:<?= $pct ?>%"></div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Recent Activity + Quick Add -->
    <div class="admin-two-col">
        <!-- Recent Activity -->
        <div class="admin-panel">
            <div class="admin-panel-header">
                <i class="bi bi-clock-history"></i> Recent Registrations
            </div>
            <div class="admin-panel-body">
                <?php if (empty($recent)): ?>
                    <p class="no-data">No registrations yet.</p>
                <?php else: ?>
                    <?php foreach ($recent as $r): ?>
                        <div class="admin-activity-item">
                            <div class="admin-activity-icon">
                                <i class="bi <?= $r['type'] === 'team' ? 'bi-people-fill' : 'bi-person-fill' ?>"></i>
                            </div>
                            <div class="admin-activity-info">
                                <div class="admin-activity-name"><?= htmlspecialchars($r['name']) ?></div>
                                <div class="admin-activity-meta">
                                    <?= $valid_games[$r['game']] ?? $r['game'] ?> &middot; <?= $r['type'] === 'team' ? 'Team' : 'Solo' ?> &middot; <?= date('M j, g:ia', strtotime($r['created_at'])) ?>
                                </div>
                            </div>
                            <span class="status-badge status-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Add Team -->
        <div class="admin-panel">
            <div class="admin-panel-header">
                <i class="bi bi-plus-circle"></i> Quick Add Team
            </div>
            <div class="admin-panel-body">
                <form method="POST">
                    <input type="hidden" name="admin_add_team" value="1">
                    <div class="mb-3">
                        <label style="font-size:0.75rem; color:var(--text-muted); display:block; margin-bottom:0.3rem;">Game</label>
                        <select name="add_game" class="form-control form-select" required>
                            <?php foreach ($valid_games as $slug => $name): ?>
                                <option value="<?= $slug ?>"><?= $name ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label style="font-size:0.75rem; color:var(--text-muted); display:block; margin-bottom:0.3rem;">Team Name</label>
                        <input type="text" name="add_team_name" class="form-control" placeholder="Enter team name" required>
                    </div>
                    <div class="mb-3">
                        <label style="font-size:0.75rem; color:var(--text-muted); display:block; margin-bottom:0.3rem;">Status</label>
                        <select name="add_status" class="form-control form-select">
                            <option value="approved">Locked In</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-approve" style="width:100%; justify-content:center; padding:0.6rem 1rem; font-size:0.85rem;">
                        <i class="bi bi-plus-lg"></i> Add Team
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Announcements -->
    <div class="admin-panel" style="margin-bottom:1.5rem;">
        <div class="admin-panel-header">
            <i class="bi bi-megaphone-fill"></i> Announcements
        </div>
        <div class="admin-panel-body">
            <form method="POST" style="margin-bottom:1rem;">
                <input type="hidden" name="post_announcement" value="1">
                <div class="mb-2">
                    <input type="text" name="ann_title" class="form-control" placeholder="Title" required style="font-size:0.85rem;">
                </div>
                <div class="mb-2">
                    <textarea name="ann_content" class="form-control" placeholder="Content" rows="2" required style="font-size:0.85rem;"></textarea>
                </div>
                <div style="display:flex; gap:0.5rem;">
                    <select name="ann_type" class="form-control form-select" style="flex:1; font-size:0.8rem;">
                        <option value="news">News</option>
                        <option value="schedule">Schedule</option>
                        <option value="result">Result</option>
                        <option value="urgent">Urgent</option>
                    </select>
                    <button type="submit" class="btn-approve" style="padding:0.5rem 1rem; font-size:0.85rem; white-space:nowrap;">
                        <i class="bi bi-send"></i> Post
                    </button>
                </div>
            </form>
            <?php if (!empty($all_announcements)): ?>
                <?php foreach ($all_announcements as $ann): ?>
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; padding:0.5rem 0; border-top:1px solid var(--border);">
                        <div style="flex:1; min-width:0;">
                            <div style="font-size:0.8rem; font-weight:600; color:var(--text);"><?= htmlspecialchars($ann['title']) ?></div>
                            <div style="font-size:0.7rem; color:var(--text-muted);"><?= htmlspecialchars(substr($ann['content'], 0, 80)) ?><?= strlen($ann['content']) > 80 ? '...' : '' ?></div>
                            <div style="font-size:0.6rem; color:var(--text-muted);"><?= $ann['type'] ?> &middot; <?= date('M j, g:ia', strtotime($ann['created_at'])) ?></div>
                        </div>
                        <form method="POST" style="flex-shrink:0; margin-left:0.5rem;">
                            <input type="hidden" name="delete_announcement" value="1">
                            <input type="hidden" name="ann_id" value="<?= $ann['id'] ?>">
                            <button type="submit" class="btn-delete" title="Delete" onclick="return confirm('Delete this announcement?')"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="no-data">No announcements yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Notifications Panel (kirfenia only) -->
    <?php if ($is_owner): ?>
    <div class="admin-panel" style="margin-bottom:1.5rem;">
        <div class="admin-panel-header">
            <i class="bi bi-bell-fill"></i> Send Notification
        </div>
        <div class="admin-panel-body">
            <form method="POST">
                <input type="hidden" name="send_notification" value="1">
                <div class="mb-2">
                    <select name="notif_target" class="form-control form-select" style="font-size:0.8rem;">
                        <option value="all">All Users (Broadcast)</option>
                        <?php foreach ($all_accounts as $acc): ?>
                        <option value="<?= $acc['id'] ?>"><?= htmlspecialchars($acc['display_name']) ?> — <?= htmlspecialchars($acc['email']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <input type="text" name="notif_title" class="form-control" placeholder="Notification title" required style="font-size:0.85rem;">
                </div>
                <div class="mb-2">
                    <textarea name="notif_message" class="form-control" placeholder="Message" rows="2" required style="font-size:0.85rem;"></textarea>
                </div>
                <div style="display:flex; gap:0.5rem;">
                    <select name="notif_icon" class="form-control form-select" style="flex:1; font-size:0.8rem;">
                        <option value="bi-bell">Bell</option>
                        <option value="bi-megaphone">Announcement</option>
                        <option value="bi-trophy">Tournament</option>
                        <option value="bi-exclamation-triangle">Warning</option>
                        <option value="bi-gift">Reward</option>
                        <option value="bi-shield-check">System</option>
                    </select>
                    <input type="text" name="notif_link" class="form-control" placeholder="Link (optional)" style="flex:2; font-size:0.8rem;">
                    <button type="submit" class="btn-approve" style="padding:0.5rem 1rem; font-size:0.85rem; white-space:nowrap;">
                        <i class="bi bi-send"></i> Send
                    </button>
                </div>
            </form>

            <?php if (!empty($recent_notifs)): ?>
            <div style="margin-top:1rem; max-height:250px; overflow-y:auto;">
                <div style="font-size:0.65rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-muted); margin-bottom:0.5rem;">Recent (last 20)</div>
                <?php foreach ($recent_notifs as $rn): ?>
                <div style="display:flex; gap:0.5rem; align-items:flex-start; padding:0.4rem 0; border-top:1px solid var(--border); font-size:0.75rem;">
                    <i class="bi <?= htmlspecialchars($rn['icon']) ?>" style="color:var(--accent-light); margin-top:0.1rem;"></i>
                    <div style="flex:1; min-width:0;">
                        <div style="font-weight:700; color:var(--text);"><?= htmlspecialchars($rn['title']) ?></div>
                        <div style="color:var(--text-muted); font-size:0.68rem;"><?= htmlspecialchars(substr($rn['message'], 0, 60)) ?><?= strlen($rn['message']) > 60 ? '...' : '' ?></div>
                        <div style="font-size:0.6rem; color:var(--text-muted);">
                            To: <?= $rn['display_name'] ? htmlspecialchars($rn['display_name']) : 'Broadcast' ?>
                            &middot; <?= date('M j, g:ia', strtotime($rn['created_at'])) ?>
                            &middot; <?= $rn['is_read'] ? '<span style="color:var(--success);">Read</span>' : '<span style="color:var(--accent-light);">Unread</span>' ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filter Tabs -->
    <div class="filter-tabs">
        <a href="<?= base_url('admin/') ?>" class="filter-tab <?= $game_filter === 'all' ? 'active' : '' ?>">
            <i class="bi bi-grid-3x3-gap"></i> All
        </a>
        <?php foreach ($valid_games as $slug => $name): ?>
            <a href="<?= base_url('admin/?game=' . $slug) ?>" class="filter-tab <?= $game_filter === $slug ? 'active' : '' ?>">
                <i class="bi <?= $game_icons[$slug] ?>"></i> <?= $name ?>
                <span class="filter-tab-count"><?= $game_stats[$slug]['teams'] + $game_stats[$slug]['solos'] ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Teams Table -->
    <div class="admin-section">
        <div class="admin-section-header">
            <h2><i class="bi bi-people"></i> Teams <span class="admin-count"><?= count($teams) ?></span></h2>
        </div>
        <?php if (empty($teams)): ?>
            <p class="no-data">No teams found.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Ref</th>
                            <th>Game</th>
                            <th>Team</th>
                            <th>Members</th>
                            <th>Status</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teams as $t): ?>
                            <tr id="team-row-<?= $t['id'] ?>">
                                <td><code style="font-size:0.7rem; color:var(--accent-light);"><?= htmlspecialchars($t['ref_code'] ?? '—') ?></code></td>
                                <td>
                                    <span class="admin-game-tag admin-game-<?= $t['game'] ?>">
                                        <i class="bi <?= $game_icons[$t['game']] ?? 'bi-controller' ?>"></i>
                                        <?= htmlspecialchars($valid_games[$t['game']] ?? $t['game']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display:flex; align-items:center; gap:0.5rem;">
                                        <?php if (!empty($t['team_logo'])): ?>
                                            <img src="<?= base_url($t['team_logo']) ?>" alt="" style="width:24px; height:24px; border-radius:6px; object-fit:cover;">
                                        <?php endif; ?>
                                        <strong><?= htmlspecialchars($t['team_name']) ?></strong>
                                    </div>
                                </td>
                                <td class="members-cell">
                                    <?php
                                    $members_data = !empty($t['members_ranks']) ? explode('|', $t['members_ranks']) : [];
                                    if (!empty($members_data) && $members_data[0] !== ':'):
                                        foreach ($members_data as $mi => $entry):
                                            $parts = explode(':', $entry, 2);
                                            $mname = $parts[0] ?? '';
                                            $mrank = $parts[1] ?? '';
                                            if (empty($mname)) continue;
                                    ?>
                                            <span class="member-tag"><?= $mi === 0 ? '<i class="bi bi-star-fill" style="color:#fbbf24;font-size:0.55rem;" title="Captain"></i> ' : '' ?><?= htmlspecialchars($mname) ?><?= !empty($mrank) ? ' <span style="color:var(--accent-light);font-size:0.65rem;">(' . htmlspecialchars($mrank) . ')</span>' : '' ?></span>
                                    <?php
                                        endforeach;
                                    else:
                                        for ($i = 1; $i <= 5; $i++):
                                            if (empty($t["member_$i"])) continue;
                                    ?>
                                            <span class="member-tag"><?= htmlspecialchars($t["member_$i"]) ?></span>
                                    <?php
                                        endfor;
                                    endif;
                                    ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= $t['status'] ?>" id="team-status-<?= $t['id'] ?>">
                                        <?= $t['status'] === 'approved' ? 'Locked In' : ucfirst($t['status']) ?>
                                    </span>
                                    <?php if (!empty($t['reserved'])): ?>
                                        <span class="status-badge" style="background:rgba(124,58,237,0.18); color:#c4b5fd; border:1px solid rgba(124,58,237,0.4); margin-top:0.2rem; display:inline-block;">
                                            <i class="bi bi-bookmark-star-fill"></i> Reserved
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:0.75rem; color:var(--text-muted);">
                                    <?= date('M j', strtotime($t['created_at'])) ?><br>
                                    <span style="font-size:0.65rem;"><?= date('g:ia', strtotime($t['created_at'])) ?></span>
                                </td>
                                <td class="actions-cell" id="team-actions-<?= $t['id'] ?>">
                                    <a href="<?= base_url('admin/view.php?type=team&id=' . $t['id']) ?>" class="btn-view" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="<?= base_url('admin/edit.php?type=team&id=' . $t['id']) ?>" class="btn-edit" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php if ($t['status'] === 'pending'): ?>
                                        <button class="btn-approve" onclick="doAction('team', <?= $t['id'] ?>, 'approve')" title="Lock In">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                        <button class="btn-reject" onclick="doAction('team', <?= $t['id'] ?>, 'reject')" title="Reject">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    <?php endif; ?>
                                    <?php if (empty($t['reserved'])): ?>
                                        <button onclick="doAction('team', <?= $t['id'] ?>, 'reserve')" title="Reserve — exclude from bracket (paid but can't join start)" style="background:rgba(124,58,237,0.15); color:#a78bfa; border:1px solid rgba(124,58,237,0.35); padding:0.3rem 0.55rem; border-radius:6px; cursor:pointer; font-family:inherit;">
                                            <i class="bi bi-bookmark-star"></i>
                                        </button>
                                    <?php else: ?>
                                        <button onclick="doAction('team', <?= $t['id'] ?>, 'unreserve')" title="Unreserve — put back in active pool" style="background:rgba(34,197,94,0.15); color:#86efac; border:1px solid rgba(34,197,94,0.35); padding:0.3rem 0.55rem; border-radius:6px; cursor:pointer; font-family:inherit;">
                                            <i class="bi bi-bookmark-x"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn-delete" onclick="doAction('team', <?= $t['id'] ?>, 'delete')" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Solo Players Table -->
    <div class="admin-section">
        <div class="admin-section-header">
            <h2><i class="bi bi-person"></i> Solo Players <span class="admin-count"><?= count($solos) ?></span></h2>
        </div>
        <?php if (empty($solos)): ?>
            <p class="no-data">No solo players found.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Ref</th>
                            <th>Game</th>
                            <th>Player</th>
                            <th>Rank</th>
                            <th>Role</th>
                            <th>Skill</th>
                            <th>Status</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($solos as $s): ?>
                            <tr id="solo-row-<?= $s['id'] ?>">
                                <td><code style="font-size:0.7rem; color:var(--accent-light);"><?= htmlspecialchars($s['ref_code'] ?? '—') ?></code></td>
                                <td>
                                    <span class="admin-game-tag admin-game-<?= $s['game'] ?>">
                                        <i class="bi <?= $game_icons[$s['game']] ?? 'bi-controller' ?>"></i>
                                        <?= htmlspecialchars($valid_games[$s['game']] ?? $s['game']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display:flex; align-items:center; gap:0.5rem;">
                                        <?php if (!empty($s['profile_photo'])): ?>
                                            <img src="<?= base_url($s['profile_photo']) ?>" alt="" style="width:28px; height:28px; border-radius:50%; object-fit:cover;">
                                        <?php endif; ?>
                                        <div>
                                            <strong><?= htmlspecialchars($s['player_name']) ?></strong>
                                            <?php if (!empty($s['real_name'])): ?>
                                                <div style="font-size:0.7rem; color:var(--text-muted);"><?= htmlspecialchars($s['real_name']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="member-tag"><?= htmlspecialchars($s['rank_tier']) ?></span></td>
                                <td style="font-size:0.8rem;"><?= htmlspecialchars($s['preferred_role'] ?? '—') ?></td>
                                <td>
                                    <div class="skill-gauge">
                                        <input type="number" min="0" max="10" value="<?= (int)($s['admin_rating'] ?? 0) ?>" id="rating-<?= $s['id'] ?>">
                                        <button onclick="saveRating(<?= $s['id'] ?>)"><i class="bi bi-check-lg"></i></button>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= $s['status'] ?>" id="solo-status-<?= $s['id'] ?>">
                                        <?= $s['status'] === 'approved' ? 'Locked In' : ucfirst($s['status']) ?>
                                    </span>
                                    <?php if (!empty($s['reserved'])): ?>
                                        <span class="status-badge" style="background:rgba(124,58,237,0.18); color:#c4b5fd; border:1px solid rgba(124,58,237,0.4); margin-top:0.2rem; display:inline-block;">
                                            <i class="bi bi-bookmark-star-fill"></i> Reserved
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:0.75rem; color:var(--text-muted);">
                                    <?= date('M j', strtotime($s['created_at'])) ?><br>
                                    <span style="font-size:0.65rem;"><?= date('g:ia', strtotime($s['created_at'])) ?></span>
                                </td>
                                <td class="actions-cell" id="solo-actions-<?= $s['id'] ?>">
                                    <a href="<?= base_url('admin/view.php?type=solo&id=' . $s['id']) ?>" class="btn-view" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="<?= base_url('admin/edit.php?type=solo&id=' . $s['id']) ?>" class="btn-edit" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php if ($s['status'] === 'pending'): ?>
                                        <button class="btn-approve" onclick="doAction('solo', <?= $s['id'] ?>, 'approve')" title="Lock In">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                        <button class="btn-reject" onclick="doAction('solo', <?= $s['id'] ?>, 'reject')" title="Reject">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    <?php endif; ?>
                                    <?php if (empty($s['reserved'])): ?>
                                        <button onclick="doAction('solo', <?= $s['id'] ?>, 'reserve')" title="Reserve — exclude from matchmaking (paid but can't join start)" style="background:rgba(124,58,237,0.15); color:#a78bfa; border:1px solid rgba(124,58,237,0.35); padding:0.3rem 0.55rem; border-radius:6px; cursor:pointer; font-family:inherit;">
                                            <i class="bi bi-bookmark-star"></i>
                                        </button>
                                    <?php else: ?>
                                        <button onclick="doAction('solo', <?= $s['id'] ?>, 'unreserve')" title="Unreserve — put back in active pool" style="background:rgba(34,197,94,0.15); color:#86efac; border:1px solid rgba(34,197,94,0.35); padding:0.3rem 0.55rem; border-radius:6px; cursor:pointer; font-family:inherit;">
                                            <i class="bi bi-bookmark-x"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn-delete" onclick="doAction('solo', <?= $s['id'] ?>, 'delete')" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Disputes -->
    <?php if (!empty($disputes)): ?>
    <div class="admin-section">
        <div class="admin-section-header">
            <h2><i class="bi bi-flag-fill" style="color:var(--danger);"></i> Disputes <span class="admin-count"><?= count($disputes) ?></span></h2>
        </div>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>From</th>
                        <th>Ref</th>
                        <th>Subject</th>
                        <th>Message</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($disputes as $d): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($d['player_name']) ?></strong></td>
                            <td><code style="font-size:0.7rem;"><?= htmlspecialchars($d['ref_code'] ?: '—') ?></code></td>
                            <td><?= htmlspecialchars($d['subject']) ?></td>
                            <td style="max-width:250px; font-size:0.8rem; color:var(--text-muted);" title="<?= htmlspecialchars($d['message']) ?>">
                                <?= htmlspecialchars(substr($d['message'], 0, 80)) ?><?= strlen($d['message']) > 80 ? '...' : '' ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?= $d['status'] === 'open' ? 'pending' : ($d['status'] === 'reviewed' ? 'approved' : 'rejected') ?>">
                                    <?= ucfirst($d['status']) ?>
                                </span>
                            </td>
                            <td style="font-size:0.75rem; color:var(--text-muted);"><?= date('M j, g:ia', strtotime($d['created_at'])) ?></td>
                            <td>
                                <form method="POST" action="<?= base_url('admin/action.php') ?>" style="display:inline;">
                                    <input type="hidden" name="type" value="dispute">
                                    <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                    <?php if ($d['status'] === 'open'): ?>
                                        <button name="action" value="review_dispute" class="btn-approve" title="Mark Reviewed"><i class="bi bi-check-lg"></i></button>
                                    <?php endif; ?>
                                    <button name="action" value="close_dispute" class="btn-reject" title="Close"><i class="bi bi-x-lg"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Accounts -->
    <?php if (!empty($all_claims)): ?>
    <div class="admin-section">
        <div class="admin-section-header">
            <h2><i class="bi bi-people-fill" style="color:var(--accent-light);"></i> Accounts <span class="admin-count"><?= count($all_claims) ?> total</span></h2>
        </div>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Ref Code</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_claims as $claim): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($claim['display_name'] ?: '—') ?></strong></td>
                            <td style="font-size:0.78rem;"><?= htmlspecialchars($claim['email']) ?></td>
                            <td><code style="font-size:0.7rem; color:var(--accent-light);"><?= htmlspecialchars($claim['ref_code']) ?></code></td>
                            <td>
                                <span class="status-badge status-<?= $claim['claim_status'] === 'approved' ? 'approved' : ($claim['claim_status'] === 'rejected' ? 'rejected' : 'pending') ?>">
                                    <?= $claim['claim_status'] === 'approved' ? 'Active' : ucfirst($claim['claim_status']) ?>
                                </span>
                            </td>
                            <td style="font-size:0.75rem; color:var(--text-muted);"><?= date('M j, g:ia', strtotime($claim['created_at'])) ?></td>
                            <td>
                                <div style="display:inline-flex; gap:0.25rem;">
                                    <?php if ($is_owner && $claim['claim_status'] === 'approved'): ?>
                                    <form method="POST" action="<?= base_url('admin/action.php') ?>" style="display:inline;">
                                        <input type="hidden" name="action" value="impersonate_start">
                                        <input type="hidden" name="id" value="<?= $claim['id'] ?>">
                                        <button type="submit" class="btn-approve" title="Impersonate <?= htmlspecialchars($claim['display_name'] ?: $claim['email']) ?>" style="padding:0.25rem 0.5rem; font-size:0.7rem;" onclick="return confirm('Impersonate <?= htmlspecialchars($claim['display_name'] ?: $claim['email']) ?>?')"><i class="bi bi-person-badge"></i></button>
                                    </form>
                                    <?php endif; ?>
                                    <form method="POST" action="<?= base_url('admin/action.php') ?>" style="display:inline;">
                                        <input type="hidden" name="type" value="claim">
                                        <input type="hidden" name="id" value="<?= $claim['id'] ?>">
                                        <button name="action" value="delete_claim" class="btn-delete" title="Delete account" onclick="return confirm('Delete this account? This cannot be undone.')"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Award Titles -->
    <?php if (!empty($all_claims)): ?>
    <div class="admin-section">
        <div class="admin-section-header">
            <h2><i class="bi bi-award-fill" style="color:#fbbf24;"></i> Award Titles</h2>
        </div>
        <div class="admin-panel-body">
            <form method="POST" action="<?= base_url('admin/action.php') ?>" style="display:flex; gap:0.5rem; align-items:flex-end; flex-wrap:wrap; margin-bottom:1rem;">
                <input type="hidden" name="type" value="title">
                <input type="hidden" name="action" value="add_title">
                <div style="flex:1; min-width:150px;">
                    <label style="font-size:0.7rem; color:var(--text-muted); display:block; margin-bottom:0.2rem;">Player Account</label>
                    <select name="id" class="form-control form-select" required style="font-size:0.8rem;">
                        <?php foreach ($all_claims as $c): ?>
                            <?php if ($c['claim_status'] === 'approved'): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['email']) ?> (<?= $c['ref_code'] ?>)</option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="flex:1; min-width:150px;">
                    <label style="font-size:0.7rem; color:var(--text-muted); display:block; margin-bottom:0.2rem;">Title</label>
                    <input type="text" name="title" class="form-control" placeholder="e.g. Season 1 MVP" required style="font-size:0.8rem;">
                </div>
                <button type="submit" class="btn-approve" style="padding:0.5rem 1rem; font-size:0.8rem; white-space:nowrap;">
                    <i class="bi bi-award-fill"></i> Award
                </button>
            </form>

            <?php
            // Show current titles
            foreach ($all_claims as $c):
                $c_titles = !empty($c['titles']) ? json_decode($c['titles'], true) : [];
                if (empty($c_titles)) continue;
            ?>
                <div style="display:flex; align-items:center; gap:0.5rem; padding:0.4rem 0; border-top:1px solid var(--border); flex-wrap:wrap;">
                    <span style="font-size:0.8rem; font-weight:600; color:var(--text); min-width:100px;"><?= htmlspecialchars($c['email']) ?></span>
                    <?php foreach ($c_titles as $t): ?>
                        <form method="POST" action="<?= base_url('admin/action.php') ?>" style="display:inline;">
                            <input type="hidden" name="type" value="title">
                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <input type="hidden" name="title" value="<?= htmlspecialchars($t) ?>">
                            <button name="action" value="remove_title" class="btn-delete" style="font-size:0.65rem; padding:0.15rem 0.4rem;" title="Remove title"
                                    onclick="return confirm('Remove title?')">
                                <i class="bi bi-award-fill" style="color:#fbbf24;"></i> <?= htmlspecialchars($t) ?> <i class="bi bi-x"></i>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// ── Listener API helpers ──
function rematchPayments() {
    const btn = event.target.closest('button');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Rematching...';
    fetch('https://listener.argonar.co/api/payments/rematch', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-API-Key': 'kirfenia123'
        },
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
        headers: {
            'Content-Type': 'application/json',
            'X-API-Key': 'kirfenia123'
        },
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

function doAction(type, id, action) {
    if (!confirm('Are you sure you want to ' + action + ' this ' + (type === 'team' ? 'team' : 'player') + '?')) {
        return;
    }

    const formData = new FormData();
    formData.append('type', type);
    formData.append('id', id);
    formData.append('action', action);

    fetch('<?= base_url("admin/action.php") ?>', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            if (action === 'delete') {
                document.getElementById(type + '-row-' + id).remove();
            } else if (action === 'reserve' || action === 'unreserve') {
                location.reload();
            } else {
                const statusEl = document.getElementById(type + '-status-' + id);
                const newStatus = action === 'approve' ? 'approved' : 'rejected';
                statusEl.className = 'status-badge status-' + newStatus;
                statusEl.textContent = newStatus === 'approved' ? 'Locked In' : 'Rejected';

                const actionsEl = document.getElementById(type + '-actions-' + id);
                actionsEl.querySelector('.btn-approve')?.remove();
                actionsEl.querySelector('.btn-reject')?.remove();
            }
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(err => {
        alert('Request failed: ' + err.message);
    });
}

function saveRating(id) {
    const val = document.getElementById('rating-' + id).value;
    const formData = new FormData();
    formData.append('type', 'solo');
    formData.append('id', id);
    formData.append('action', 'rate');
    formData.append('rating', val);

    fetch('<?= base_url("admin/action.php") ?>', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            alert('Error: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(err => {
        alert('Request failed: ' + err.message);
    });
}

</script>

</body>
</html>
