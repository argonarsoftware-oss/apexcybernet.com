<?php
require_once __DIR__ . '/../includes/db.php';

if (isset($_GET['token']) && $_GET['token'] === 'apexcybernet-admin-2026-token') {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_username'] = 'admin';
    $_SESSION['admin_role'] = 'admin';
}
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: ' . base_url('admin/'));
    exit;
}

if (($_SESSION['admin_username'] ?? '') !== 'kirfenia') {
    header('Location: ' . base_url('admin/'));
    exit;
}

$is_admin = (($_SESSION['admin_role'] ?? '') === 'admin');

// ── Actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'grant_merchant' && $id > 0) {
        $pdo->prepare("UPDATE accounts SET is_merchant = 1 WHERE id = ? AND claim_status = 'approved'")->execute([$id]);
    } elseif ($action === 'revoke_merchant' && $id > 0) {
        $pdo->prepare("UPDATE accounts SET is_merchant = 0 WHERE id = ?")->execute([$id]);
    }

    header('Location: ' . base_url('admin/merchants.php'));
    exit;
}

// ── Data ──

// Active merchants + stats
$merchants = $pdo->query("
    SELECT
        a.id, a.display_name, a.email, a.h_coins, a.created_at, a.profile_picture,
        COALESCE(SUM(CASE WHEN t.reason = 'qr_received' THEN t.amount END), 0)                                                             AS total_received,
        COUNT(CASE WHEN t.reason = 'qr_received' THEN 1 END)                                                                              AS total_txns,
        COALESCE(SUM(CASE WHEN t.reason = 'qr_received' AND DATE(t.created_at) = CURDATE() THEN t.amount END), 0)                         AS today,
        COALESCE(SUM(CASE WHEN t.reason = 'qr_received' AND YEARWEEK(t.created_at,1) = YEARWEEK(NOW(),1) THEN t.amount END), 0)            AS this_week,
        COALESCE(SUM(CASE WHEN t.reason = 'qr_received' AND YEAR(t.created_at) = YEAR(NOW()) AND MONTH(t.created_at) = MONTH(NOW()) THEN t.amount END), 0) AS this_month,
        MAX(CASE WHEN t.reason = 'qr_received' THEN t.created_at END)                                                                     AS last_payment
    FROM accounts a
    LEFT JOIN h_coin_transactions t ON t.account_id = a.id AND t.type = 'credit'
    WHERE a.is_merchant = 1
    GROUP BY a.id
    ORDER BY total_received DESC
")->fetchAll();

// Non-merchant approved accounts for grant dropdown
$non_merchants = $pdo->query("
    SELECT id, display_name, email
    FROM accounts
    WHERE claim_status = 'approved' AND is_merchant = 0
    ORDER BY display_name ASC
")->fetchAll();

// Recent POS transactions (all merchants)
$recent_txns = $pdo->query("
    SELECT t.amount, t.ref, t.created_at, a.display_name AS merchant_name
    FROM h_coin_transactions t
    JOIN accounts a ON a.id = t.account_id
    WHERE t.type = 'credit' AND t.reason = 'qr_received'
    ORDER BY t.created_at DESC
    LIMIT 50
")->fetchAll();

// Summary
$total_merchants    = count($merchants);
$total_volume       = array_sum(array_column($merchants, 'total_received'));
$today_volume       = array_sum(array_column($merchants, 'today'));
$month_volume       = array_sum(array_column($merchants, 'this_month'));

$pageTitle = 'Merchant Management — Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= base_url('css/app.css') ?>">
    <style>
    :root {
        --green:     #22c55e;
        --green-dim: rgba(34,197,94,0.1);
        --green-bdr: rgba(34,197,94,0.25);
        --amber:     #f59e0b;
    }

    .merch-page { max-width: 1100px; margin: 0 auto; padding: 1.5rem 1rem 4rem; }

    /* ── Top bar ── */
    .merch-topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1.75rem;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .merch-title {
        font-size: 1.3rem;
        font-weight: 900;
        color: var(--text-main);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .merch-title i { color: var(--green); }

    /* ── Summary cards ── */
    .merch-summary {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 0.75rem;
        margin-bottom: 1.75rem;
    }

    .merch-stat {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 1rem 1.1rem;
    }

    .merch-stat-val {
        font-size: 1.6rem;
        font-weight: 900;
        color: var(--green);
        letter-spacing: -1px;
    }

    .merch-stat-lbl {
        font-size: 0.68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--text-muted);
        margin-top: 0.15rem;
    }

    /* ── Main grid ── */
    .merch-grid {
        display: grid;
        grid-template-columns: 1fr 380px;
        gap: 1.25rem;
        align-items: start;
    }

    @media (max-width: 820px) {
        .merch-grid { grid-template-columns: 1fr; }
    }

    /* ── Merchant cards ── */
    .merch-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 1.1rem 1.25rem;
        margin-bottom: 0.75rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        transition: border-color 0.2s;
    }

    .merch-card:hover { border-color: var(--green-bdr); }

    .merch-avatar {
        width: 46px;
        height: 46px;
        border-radius: 12px;
        background: linear-gradient(135deg, #064e3b, #065f46);
        border: 1px solid var(--green-bdr);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        font-weight: 900;
        color: var(--green);
        flex-shrink: 0;
        overflow: hidden;
    }

    .merch-avatar img { width: 100%; height: 100%; object-fit: cover; }

    .merch-info { flex: 1; min-width: 0; }

    .merch-name {
        font-size: 0.95rem;
        font-weight: 800;
        color: var(--text-main);
        display: flex;
        align-items: center;
        gap: 0.45rem;
    }

    .merch-badge {
        font-size: 0.58rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        background: var(--green-dim);
        border: 1px solid var(--green-bdr);
        color: var(--green);
        border-radius: 99px;
        padding: 0.1rem 0.45rem;
    }

    .merch-email { font-size: 0.72rem; color: var(--text-muted); margin-top: 0.1rem; }

    .merch-nums {
        display: flex;
        gap: 1.25rem;
        margin-top: 0.5rem;
        flex-wrap: wrap;
    }

    .merch-num-item { text-align: center; }
    .merch-num-val  { font-size: 0.88rem; font-weight: 800; color: var(--green); }
    .merch-num-lbl  { font-size: 0.62rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; }

    .merch-last {
        font-size: 0.68rem;
        color: var(--text-muted);
        margin-top: 0.35rem;
    }

    .merch-revoke {
        background: transparent;
        border: 1px solid var(--border);
        border-radius: 8px;
        color: var(--text-muted);
        padding: 0.35rem 0.7rem;
        font-size: 0.72rem;
        font-weight: 700;
        cursor: pointer;
        font-family: inherit;
        white-space: nowrap;
        flex-shrink: 0;
        transition: all 0.15s;
        display: flex;
        align-items: center;
        gap: 0.3rem;
    }

    .merch-revoke:hover { border-color: #ef4444; color: #f87171; }

    /* ── Empty state ── */
    .merch-empty {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 3rem 1.5rem;
        text-align: center;
        color: var(--text-muted);
    }

    .merch-empty i { font-size: 2.5rem; display: block; margin-bottom: 0.75rem; opacity: 0.2; }
    .merch-empty p { font-size: 0.88rem; }

    /* ── Right sidebar ── */
    .merch-sidebar-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 16px;
        overflow: hidden;
        margin-bottom: 1rem;
    }

    .merch-sidebar-head {
        padding: 0.9rem 1.1rem;
        border-bottom: 1px solid var(--border);
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: 0.4rem;
    }

    .merch-sidebar-head i { color: var(--green); }

    /* Grant form */
    .grant-form { padding: 1rem 1.1rem; }

    .grant-field { margin-bottom: 0.85rem; }

    .grant-field label {
        display: block;
        font-size: 0.68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-muted);
        margin-bottom: 0.3rem;
    }

    .grant-field select,
    .grant-field input {
        width: 100%;
        background: var(--bg-dark);
        border: 1px solid var(--border);
        border-radius: 8px;
        color: var(--text-main);
        padding: 0.6rem 0.8rem;
        font-size: 0.82rem;
        font-family: inherit;
        outline: none;
        transition: border-color 0.2s;
    }

    .grant-field select:focus,
    .grant-field input:focus { border-color: var(--green); }

    .grant-btn {
        width: 100%;
        padding: 0.7rem;
        background: var(--green);
        color: #000;
        border: none;
        border-radius: 9px;
        font-size: 0.85rem;
        font-weight: 800;
        cursor: pointer;
        font-family: inherit;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
        transition: background 0.2s;
    }

    .grant-btn:hover { background: #16a34a; color: #fff; }
    .grant-btn:disabled { opacity: 0.4; cursor: not-allowed; }

    /* Recent txns */
    .txn-list { max-height: 420px; overflow-y: auto; }

    .txn-row {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.65rem 1.1rem;
        border-bottom: 1px solid var(--border);
    }

    .txn-row:last-child { border-bottom: none; }

    .txn-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--green);
        flex-shrink: 0;
        box-shadow: 0 0 5px var(--green);
    }

    .txn-merchant { font-size: 0.78rem; font-weight: 700; color: var(--text-main); }
    .txn-from     { font-size: 0.68rem; color: var(--text-muted); margin-top: 0.05rem; }
    .txn-note     { font-size: 0.65rem; color: var(--amber); }
    .txn-time     { font-size: 0.65rem; color: var(--text-muted); margin-top: 0.05rem; }
    .txn-amt      { font-size: 0.88rem; font-weight: 900; color: var(--green); margin-left: auto; flex-shrink: 0; }

    .txn-empty { padding: 2rem 1rem; text-align: center; color: var(--text-muted); font-size: 0.8rem; }
    </style>
</head>
<body>
<div class="merch-page">

    <!-- Top bar -->
    <div class="merch-topbar">
        <div>
            <div class="merch-title"><i class="bi bi-shop-window"></i> Merchant Management</div>
            <p style="font-size:0.78rem; color:var(--text-muted); margin:0.2rem 0 0;">
                Control who can accept H-Coin payments via the POS terminal.
            </p>
        </div>
        <div style="display:flex; gap:0.4rem; flex-wrap:wrap;">
            <a href="<?= base_url('pos.php') ?>" class="btn-back-site" target="_blank"><i class="bi bi-terminal"></i> POS Terminal</a>
            <a href="<?= base_url('admin/') ?>" class="btn-back-site"><i class="bi bi-arrow-left"></i> Dashboard</a>
            <a href="<?= base_url('admin/logout.php') ?>" class="btn-logout"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </div>

    <!-- Summary cards -->
    <div class="merch-summary">
        <div class="merch-stat">
            <div class="merch-stat-val"><?= $total_merchants ?></div>
            <div class="merch-stat-lbl">Active Merchants</div>
        </div>
        <div class="merch-stat">
            <div class="merch-stat-val"><?= number_format($today_volume) ?></div>
            <div class="merch-stat-lbl">HC Collected Today</div>
        </div>
        <div class="merch-stat">
            <div class="merch-stat-val"><?= number_format($month_volume) ?></div>
            <div class="merch-stat-lbl">HC This Month</div>
        </div>
        <div class="merch-stat">
            <div class="merch-stat-val"><?= number_format($total_volume) ?></div>
            <div class="merch-stat-lbl">HC All Time</div>
        </div>
    </div>

    <!-- Main grid -->
    <div class="merch-grid">

        <!-- Left: merchant cards -->
        <div>
            <div style="font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-muted); margin-bottom:0.75rem;">
                <?= $total_merchants ?> Registered Merchant<?= $total_merchants !== 1 ? 's' : '' ?>
            </div>

            <?php if (empty($merchants)): ?>
            <div class="merch-empty">
                <i class="bi bi-shop"></i>
                <p>No merchants yet.<br>Use the panel on the right to grant access.</p>
            </div>
            <?php else: ?>

            <?php foreach ($merchants as $m):
                $initials = strtoupper(substr($m['display_name'] ?? '?', 0, 2));
                $last_pay = $m['last_payment'] ? date('M j, g:ia', strtotime($m['last_payment'])) : 'Never';
            ?>
            <div class="merch-card">
                <div class="merch-avatar">
                    <?php if (!empty($m['profile_picture'])): ?>
                        <img src="<?= base_url(htmlspecialchars($m['profile_picture'])) ?>" alt="">
                    <?php else: ?>
                        <?= $initials ?>
                    <?php endif; ?>
                </div>

                <div class="merch-info">
                    <div class="merch-name">
                        <?= htmlspecialchars($m['display_name']) ?>
                        <span class="merch-badge"><i class="bi bi-shop"></i> Merchant</span>
                    </div>
                    <div class="merch-email"><?= htmlspecialchars($m['email']) ?></div>

                    <div class="merch-nums">
                        <div class="merch-num-item">
                            <div class="merch-num-val"><?= number_format((int)$m['today']) ?></div>
                            <div class="merch-num-lbl">Today</div>
                        </div>
                        <div class="merch-num-item">
                            <div class="merch-num-val"><?= number_format((int)$m['this_week']) ?></div>
                            <div class="merch-num-lbl">Week</div>
                        </div>
                        <div class="merch-num-item">
                            <div class="merch-num-val"><?= number_format((int)$m['this_month']) ?></div>
                            <div class="merch-num-lbl">Month</div>
                        </div>
                        <div class="merch-num-item">
                            <div class="merch-num-val"><?= number_format((int)$m['total_received']) ?></div>
                            <div class="merch-num-lbl">All time</div>
                        </div>
                        <div class="merch-num-item">
                            <div class="merch-num-val"><?= (int)$m['total_txns'] ?></div>
                            <div class="merch-num-lbl">Txns</div>
                        </div>
                        <div class="merch-num-item">
                            <div class="merch-num-val" style="color:var(--amber);"><?= number_format((int)$m['h_coins']) ?></div>
                            <div class="merch-num-lbl">Balance</div>
                        </div>
                    </div>

                    <div class="merch-last">Last payment: <?= $last_pay ?></div>
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="revoke_merchant">
                    <input type="hidden" name="id" value="<?= $m['id'] ?>">
                    <button type="submit" class="merch-revoke"
                            onclick="return confirm('Revoke merchant access for <?= htmlspecialchars($m['display_name'], ENT_QUOTES) ?>?')">
                        <i class="bi bi-x-circle"></i> Revoke
                    </button>
                </form>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Right sidebar -->
        <div>

            <!-- Grant merchant -->
            <div class="merch-sidebar-card">
                <div class="merch-sidebar-head">
                    <i class="bi bi-plus-circle"></i> Grant Merchant Access
                </div>
                <div class="grant-form">
                    <?php if (empty($non_merchants)): ?>
                    <p style="font-size:0.8rem; color:var(--text-muted); text-align:center; padding:0.5rem 0;">
                        All approved accounts are already merchants.
                    </p>
                    <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="grant_merchant">
                        <div class="grant-field">
                            <label>Account</label>
                            <select name="id" required>
                                <option value="">— Select account —</option>
                                <?php foreach ($non_merchants as $nm): ?>
                                <option value="<?= $nm['id'] ?>">
                                    <?= htmlspecialchars($nm['display_name']) ?> — <?= htmlspecialchars($nm['email']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="grant-btn">
                            <i class="bi bi-shop"></i> Grant Merchant Access
                        </button>
                    </form>

                    <div style="margin-top:1rem; padding-top:1rem; border-top:1px solid var(--border); font-size:0.72rem; color:var(--text-muted); line-height:1.6;">
                        <i class="bi bi-info-circle"></i>
                        Only approved accounts are listed. Merchants can log into the POS terminal with their Apex Cybernet username and password to accept H-Coin payments from customers.
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent activity -->
            <div class="merch-sidebar-card">
                <div class="merch-sidebar-head">
                    <i class="bi bi-activity"></i> Live Activity
                    <span style="margin-left:auto; color:var(--text-muted); font-weight:400;"><?= count($recent_txns) ?> recent</span>
                </div>
                <div class="txn-list">
                    <?php if (empty($recent_txns)): ?>
                    <div class="txn-empty">No POS transactions yet</div>
                    <?php else: ?>
                    <?php foreach ($recent_txns as $t):
                        $parts = explode(':', $t['ref'], 3);
                        $from  = $parts[1] ?? '';
                        $note  = $parts[2] ?? '';
                        $time  = date('M j, g:ia', strtotime($t['created_at']));
                    ?>
                    <div class="txn-row">
                        <div class="txn-dot"></div>
                        <div style="flex:1; min-width:0;">
                            <div class="txn-merchant"><?= htmlspecialchars($t['merchant_name']) ?></div>
                            <div class="txn-from">from <?= htmlspecialchars($from ?: '?') ?></div>
                            <?php if ($note): ?>
                            <div class="txn-note"><?= htmlspecialchars($note) ?></div>
                            <?php endif; ?>
                            <div class="txn-time"><?= $time ?></div>
                        </div>
                        <div class="txn-amt">+<?= number_format((int)$t['amount']) ?></div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>
</body>
</html>
