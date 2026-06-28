<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user($pdo);

// Look up the latest qr_payment debit for this user (or by ts param)
$ts = (int)($_GET['ts'] ?? 0);

if ($ts > 0) {
    $since_dt = date('Y-m-d H:i:s', $ts - 5);
    $until_dt = date('Y-m-d H:i:s', $ts + 10);
    $stmt = $pdo->prepare("
        SELECT amount, ref, created_at
        FROM h_coin_transactions
        WHERE account_id = ?
          AND type = 'debit'
          AND reason = 'qr_payment'
          AND created_at BETWEEN ? AND ?
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$user['id'], $since_dt, $until_dt]);
} else {
    // Fallback: most recent qr_payment
    $stmt = $pdo->prepare("
        SELECT amount, ref, created_at
        FROM h_coin_transactions
        WHERE account_id = ?
          AND type = 'debit'
          AND reason = 'qr_payment'
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$user['id']]);
}

$txn = $stmt->fetch();

if (!$txn) {
    header('Location: ' . base_url('dashboard.php'));
    exit;
}

$merchant   = strpos($txn['ref'], 'to:') === 0 ? substr($txn['ref'], 3) : $txn['ref'];
$amount     = (int)$txn['amount'];
$txn_time   = $txn['created_at'];

// Current balance
$bal_stmt = $pdo->prepare("SELECT h_coins FROM accounts WHERE id = ?");
$bal_stmt->execute([$user['id']]);
$balance = (int)$bal_stmt->fetchColumn();

$pageTitle = 'Payment Receipt';
require_once __DIR__ . '/includes/header.php';
?>

<style>
.receipt-page {
    min-height: calc(100vh - 70px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem 1rem;
}

.receipt-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 24px;
    width: 100%;
    max-width: 380px;
    overflow: hidden;
    box-shadow: 0 20px 50px rgba(0,0,0,0.4);
    animation: popIn 0.4s cubic-bezier(0.34,1.56,0.64,1);
}

@keyframes popIn {
    from { opacity: 0; transform: scale(0.9) translateY(20px); }
    to   { opacity: 1; transform: scale(1) translateY(0); }
}

/* Green header band */
.receipt-top {
    background: linear-gradient(135deg, #15803d, #166534);
    padding: 2.5rem 2rem 2rem;
    text-align: center;
    position: relative;
}

.receipt-check {
    width: 64px;
    height: 64px;
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

.receipt-label {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 2px;
    color: rgba(255,255,255,0.6);
    margin-bottom: 0.3rem;
}

.receipt-amount {
    font-size: 3rem;
    font-weight: 900;
    color: #fff;
    line-height: 1;
    letter-spacing: -1px;
}

.receipt-unit {
    font-size: 1rem;
    color: rgba(255,255,255,0.7);
    font-weight: 600;
    margin-top: 0.25rem;
}

/* Tear line */
.receipt-tear {
    position: relative;
    height: 20px;
    background: var(--bg-card);
}

.receipt-tear::before {
    content: '';
    position: absolute;
    top: -10px;
    left: -5px;
    right: -5px;
    height: 20px;
    background: radial-gradient(circle at 50% 0, var(--bg-card) 10px, transparent 10px) repeat-x;
    background-size: 24px 20px;
}

/* Detail rows */
.receipt-body {
    padding: 0 1.75rem 1.75rem;
}

.receipt-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.8rem 0;
    border-bottom: 1px dashed var(--border);
    font-size: 0.875rem;
}

.receipt-row:last-of-type { border-bottom: none; }

.receipt-row .r-label {
    color: var(--text-muted);
    font-weight: 500;
}

.receipt-row .r-value {
    font-weight: 700;
    color: var(--text);
    text-align: right;
}

.receipt-row .r-value.green { color: #22c55e; }
.receipt-row .r-value.red   { color: #f87171; }
.receipt-row .r-value.muted { color: var(--text-muted); font-weight: 500; font-size: 0.8rem; }

/* Buttons */
.receipt-actions {
    padding: 0 1.75rem 1.75rem;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

.receipt-btn {
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

.receipt-btn.primary {
    background: var(--accent);
    color: #fff;
    box-shadow: 0 4px 14px rgba(124,58,237,0.3);
}

.receipt-btn.primary:hover { background: #6d28d9; }

.receipt-btn.ghost {
    background: transparent;
    border: 1px solid var(--border);
    color: var(--text-muted);
}

.receipt-btn.ghost:hover { border-color: var(--accent); color: var(--accent-light); }
</style>

<div class="receipt-page">
    <div class="receipt-card">

        <div class="receipt-top">
            <div class="receipt-check"><i class="bi bi-check-lg"></i></div>
            <div class="receipt-label">Amount charged</div>
            <div class="receipt-amount">−<?= number_format($amount) ?></div>
            <div class="receipt-unit">H-Coins</div>
        </div>

        <div class="receipt-tear"></div>

        <div class="receipt-body">
            <div class="receipt-row">
                <span class="r-label">Merchant</span>
                <span class="r-value"><?= htmlspecialchars($merchant) ?></span>
            </div>
            <div class="receipt-row">
                <span class="r-label">Your account</span>
                <span class="r-value"><?= htmlspecialchars($user['display_name'] ?: $user['email']) ?></span>
            </div>
            <div class="receipt-row">
                <span class="r-label">Remaining balance</span>
                <span class="r-value green"><?= number_format($balance) ?> HC</span>
            </div>
            <div class="receipt-row">
                <span class="r-label">Date &amp; time</span>
                <span class="r-value muted"><?= date('M j, Y · g:i A', strtotime($txn_time)) ?></span>
            </div>
        </div>

        <div class="receipt-actions">
            <a href="<?= base_url('dashboard.php') ?>" class="receipt-btn primary">
                <i class="bi bi-speedometer2"></i> Go to Dashboard
            </a>
            <a href="<?= base_url('qr-wallet.php') ?>" class="receipt-btn ghost">
                <i class="bi bi-qr-code"></i> Back to QR Wallet
            </a>
        </div>

    </div>
</div>

<script>
// Mark this charge as already receipted so qr-wallet.php never redirects for it again
(function() {
    var ts = '<?= (int)$ts ?>';
    if (!ts) return;
    var existing = (sessionStorage.getItem('shownReceiptTs') || '').split(',').filter(Boolean);
    if (existing.indexOf(ts) === -1) existing.push(ts);
    sessionStorage.setItem('shownReceiptTs', existing.join(','));
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
