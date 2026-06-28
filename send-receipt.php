<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$user    = current_user($pdo);
$to      = htmlspecialchars(trim($_GET['to'] ?? ''));
$amount  = (int)($_GET['amount'] ?? 0);
$balance = (int)($_GET['balance'] ?? 0);

if (!$to || $amount < 1) {
    header('Location: ' . base_url('dashboard.php'));
    exit;
}

$pageTitle = 'Sent!';
require_once __DIR__ . '/includes/header.php';
?>
<style>
.sr-page {
    min-height: calc(100vh - 70px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem 1rem;
}

.sr-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 24px;
    width: 100%;
    max-width: 360px;
    overflow: hidden;
    box-shadow: 0 20px 50px rgba(0,0,0,0.4);
    animation: popIn 0.4s cubic-bezier(0.34,1.56,0.64,1);
}

@keyframes popIn {
    from { opacity:0; transform:scale(0.9) translateY(20px); }
    to   { opacity:1; transform:scale(1) translateY(0); }
}

.sr-top {
    background: linear-gradient(135deg, #4c1d95, #6d28d9);
    padding: 2.5rem 2rem 2rem;
    text-align: center;
}

.sr-icon {
    width: 64px; height: 64px;
    border-radius: 50%;
    background: rgba(255,255,255,0.15);
    border: 2px solid rgba(255,255,255,0.35);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    color: #fff;
    margin: 0 auto 1rem;
}

.sr-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 2px; color: rgba(255,255,255,0.6); margin-bottom: 0.3rem; }
.sr-amount { font-size: 3rem; font-weight: 900; color: #fff; line-height: 1; letter-spacing: -1px; }
.sr-unit { font-size: 1rem; color: rgba(255,255,255,0.7); font-weight: 600; margin-top: 0.25rem; }

.sr-tear { position: relative; height: 20px; background: var(--bg-card); }
.sr-tear::before {
    content: '';
    position: absolute;
    top: -10px; left: -5px; right: -5px;
    height: 20px;
    background: radial-gradient(circle at 50% 0, var(--bg-card) 10px, transparent 10px) repeat-x;
    background-size: 24px 20px;
}

.sr-body { padding: 0 1.75rem 1.75rem; }

.sr-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.8rem 0;
    border-bottom: 1px dashed var(--border);
    font-size: 0.875rem;
}

.sr-row:last-of-type { border-bottom: none; }
.sr-row .label { color: var(--text-muted); }
.sr-row .value { font-weight: 700; color: var(--text); }
.sr-row .value.purple { color: var(--accent-light); }

.sr-actions { padding: 0 1.75rem 1.75rem; display: flex; flex-direction: column; gap: 0.6rem; }

.sr-btn {
    width: 100%;
    padding: 0.8rem;
    border-radius: 12px;
    border: none;
    font-size: 0.9rem;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    text-decoration: none;
    transition: all 0.2s;
}

.sr-btn.primary { background: var(--accent); color: #fff; box-shadow: 0 4px 14px rgba(124,58,237,0.3); }
.sr-btn.primary:hover { background: #6d28d9; }
.sr-btn.ghost { background: transparent; border: 1px solid var(--border); color: var(--text-muted); }
.sr-btn.ghost:hover { border-color: var(--accent); color: var(--accent-light); }
</style>

<div class="sr-page">
    <div class="sr-card">
        <div class="sr-top">
            <div class="sr-icon"><i class="bi bi-send-fill"></i></div>
            <div class="sr-label">Sent successfully</div>
            <div class="sr-amount"><?= number_format($amount) ?></div>
            <div class="sr-unit">H-Coins</div>
        </div>

        <div class="sr-tear"></div>

        <div class="sr-body">
            <div class="sr-row">
                <span class="label">To</span>
                <span class="value"><?= $to ?></span>
            </div>
            <div class="sr-row">
                <span class="label">From</span>
                <span class="value"><?= htmlspecialchars($user['display_name'] ?: $user['email']) ?></span>
            </div>
            <div class="sr-row">
                <span class="label">Your new balance</span>
                <span class="value purple"><?= number_format($balance) ?> HC</span>
            </div>
            <div class="sr-row">
                <span class="label">Time</span>
                <span class="value" style="font-size:0.8rem; color:var(--text-muted); font-weight:500;"><?= date('M j, Y · g:i A') ?></span>
            </div>
        </div>

        <div class="sr-actions">
            <a href="<?= base_url('dashboard.php') ?>" class="sr-btn primary">
                <i class="bi bi-speedometer2"></i> Go to Dashboard
            </a>
            <a href="<?= base_url('send-hcoins.php') ?>" class="sr-btn ghost">
                <i class="bi bi-send"></i> Send Again
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
