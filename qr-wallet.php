<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user($pdo);
$pageTitle = 'QR Wallet';
$pageDescription = 'Your H-Coin QR wallet — show this code to a merchant to pay with H-Coins.';

require_once __DIR__ . '/includes/header.php';
?>

<style>
.qrw-page {
    min-height: calc(100vh - 70px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem 1rem;
}

.qrw-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 24px;
    padding: 2rem;
    width: 100%;
    max-width: 360px;
    text-align: center;
    box-shadow: 0 20px 50px rgba(0,0,0,0.4);
    position: relative;
}

.qrw-header {
    margin-bottom: 1.5rem;
}

.qrw-avatar {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    background: linear-gradient(135deg, var(--accent), #6d28d9);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    font-weight: 800;
    color: #fff;
    margin-bottom: 0.75rem;
}

.qrw-name {
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--text);
}

.qrw-balance {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    background: rgba(124,58,237,0.15);
    border: 1px solid rgba(139,92,246,0.3);
    border-radius: 99px;
    padding: 0.3rem 0.9rem;
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--accent-light);
    margin-top: 0.4rem;
}

.qrw-balance img {
    width: 16px;
    height: 16px;
    object-fit: contain;
}

/* QR canvas container */
.qrw-qr-wrap {
    background: #fff;
    border-radius: 16px;
    padding: 1rem;
    display: inline-block;
    margin: 1.25rem 0;
    position: relative;
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
}

.qrw-qr-wrap canvas {
    display: block;
}


/* Timer */
.qrw-timer {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    font-size: 0.82rem;
    color: var(--text-muted);
    margin-bottom: 0.75rem;
}

.qrw-timer-bar {
    width: 100%;
    height: 4px;
    background: var(--border);
    border-radius: 99px;
    overflow: hidden;
    margin-bottom: 1rem;
}

.qrw-timer-fill {
    height: 100%;
    border-radius: 99px;
    background: var(--accent);
    transition: width 1s linear, background 0.5s;
}

.qrw-hint {
    font-size: 0.78rem;
    color: var(--text-muted);
    line-height: 1.5;
}

.qrw-refresh-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    margin-top: 1rem;
    padding: 0.5rem 1.25rem;
    background: rgba(124,58,237,0.15);
    border: 1px solid rgba(139,92,246,0.3);
    border-radius: 9px;
    color: var(--accent-light);
    font-size: 0.82rem;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
    transition: background 0.2s;
}

.qrw-refresh-btn:hover { background: rgba(124,58,237,0.25); }

.qrw-expired {
    display: none;
    background: rgba(239,68,68,0.1);
    border: 1px solid rgba(239,68,68,0.3);
    border-radius: 10px;
    padding: 0.75rem;
    color: #f87171;
    font-size: 0.85rem;
    font-weight: 700;
    margin: 0.75rem 0;
}

.qrw-back {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.78rem;
    color: var(--text-muted);
    text-decoration: none;
    margin-top: 1.25rem;
    transition: color 0.2s;
}

.qrw-back:hover { color: var(--text); }

/* ── Charge notification ── */
.qrw-charge-toast {
    position: fixed;
    top: 1.25rem;
    left: 50%;
    transform: translateX(-50%) translateY(-120px);
    background: var(--bg-card);
    border: 1.5px solid #22c55e;
    border-radius: 16px;
    padding: 1rem 1.5rem;
    box-shadow: 0 8px 32px rgba(0,0,0,0.5), 0 0 0 1px rgba(34,197,94,0.2);
    display: flex;
    align-items: center;
    gap: 1rem;
    z-index: 9999;
    min-width: 280px;
    max-width: 340px;
    transition: transform 0.4s cubic-bezier(0.34,1.56,0.64,1);
    text-align: left;
}

.qrw-charge-toast.show {
    transform: translateX(-50%) translateY(0);
}

.qrw-charge-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: rgba(34,197,94,0.15);
    border: 1px solid rgba(34,197,94,0.35);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    color: #22c55e;
    flex-shrink: 0;
}

.qrw-charge-body { flex: 1; min-width: 0; }

.qrw-charge-amount {
    font-size: 1.2rem;
    font-weight: 900;
    color: #f87171;
    line-height: 1.1;
}

.qrw-charge-label {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 0.2rem;
}

.qrw-charge-list {
    margin-top: 0.75rem;
    border-top: 1px solid var(--border);
    padding-top: 0.75rem;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    max-height: 160px;
    overflow-y: auto;
    text-align: left;
}

.qrw-charge-item {
    background: rgba(239,68,68,0.08);
    border: 1px solid rgba(239,68,68,0.2);
    border-radius: 8px;
    padding: 0.5rem 0.75rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.82rem;
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from { opacity: 0; transform: translateX(20px); }
    to   { opacity: 1; transform: translateX(0); }
}

.qrw-charge-item .ci-merchant {
    color: var(--text-muted);
    font-size: 0.75rem;
}

.qrw-charge-item .ci-amount {
    color: #f87171;
    font-weight: 800;
    white-space: nowrap;
}

.qrw-inline-charges {
    margin-top: 0.75rem;
    display: none;
    flex-direction: column;
    gap: 0.4rem;
}

.qrw-inline-charges.has-items { display: flex; }
</style>

<?php
// Generate token server-side for initial render
$uid   = (int)$user['id'];
$ts    = time();
$sig   = hash_hmac('sha256', $uid . ':' . $ts, QR_HMAC_SECRET);
$token = $uid . ':' . $ts . ':' . $sig;
$expires_at = $ts + 120;
$h_coins = (int)($hc_row['h_coins'] ?? 0);

// Re-fetch h_coins (already loaded in dashboard flow but safe to re-fetch)
$hc_q = $pdo->prepare("SELECT h_coins FROM accounts WHERE id = ?");
$hc_q->execute([$uid]);
$h_coins = (int)($hc_q->fetchColumn() ?? 0);

$qr_img_url = base_url('api/qr-token.php') . '?t=' . $ts;
?>

<div class="qrw-page">
    <div class="qrw-card">
        <div class="qrw-header">
            <div class="qrw-avatar"><?= strtoupper(substr($user['display_name'] ?: $user['email'], 0, 2)) ?></div>
            <div class="qrw-name"><?= htmlspecialchars($user['display_name'] ?: $user['email']) ?></div>
            <div>
                <span class="qrw-balance">
                    <img src="<?= base_url('images/hcoin-icon.png') ?>" alt="HC">
                    <span id="qrBalanceDisplay"><?= number_format($h_coins) ?></span> HC
                </span>
            </div>
            <div style="font-size:0.72rem;color:var(--text-muted);margin-top:0.5rem;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;">
                <i class="bi bi-wallet2"></i> Your H-Coin Balance
            </div>
        </div>

        <div class="qrw-timer-bar">
            <div class="qrw-timer-fill" id="timerFill" style="width:100%;"></div>
        </div>

        <div class="qrw-timer">
            <i class="bi bi-clock"></i>
            <span id="timerText">Valid for 2 minutes</span>
        </div>

        <div id="qrExpiredBox" class="qrw-expired">
            <i class="bi bi-exclamation-triangle-fill"></i> QR expired — tap refresh
        </div>

        <div class="qrw-qr-wrap" id="qrWrap">
            <img id="qrImg" src="<?= htmlspecialchars($qr_img_url) ?>"
                 alt="Your QR Code" width="220" height="220"
                 style="display:block; border-radius:4px;">
        </div>

        <div class="qrw-hint">
            Show this QR to a merchant.<br>They will scan it to charge H-Coins from your balance.
        </div>

        <button class="qrw-refresh-btn" onclick="refreshQR()">
            <i class="bi bi-arrow-clockwise"></i> Refresh QR
        </button>

        <br>
        <a href="<?= base_url('dashboard.php') ?>" class="qrw-back">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
    </div>
</div>

<script>
const STATUS_URL = <?= json_encode(base_url('api/qr-status.php')) ?>;
const TOKEN_URL  = <?= json_encode(base_url('api/qr-token.php')) ?>;

let expiresAt      = <?= $expires_at ?>;
let pollSince      = <?= $ts ?>;  // server time when page generated
// Count down from elapsed time since timer (re)start, immune to client clock skew.
let timerStartMs   = Date.now();
let timerDuration  = 120;
let timerInterval  = null;
let pollInterval   = null;
let toastTimeout   = null;
let seenCharges    = new Set();
// Any charge ts stored here was already shown in a receipt — never redirect for it again
const shownReceipts = new Set(
    (sessionStorage.getItem('shownReceiptTs') || '').split(',').filter(Boolean)
);

function startTimer() {
    if (timerInterval) clearInterval(timerInterval);
    timerStartMs  = Date.now();
    timerDuration = 120;
    timerInterval = setInterval(() => {
        const elapsed   = Math.floor((Date.now() - timerStartMs) / 1000);
        const remaining = timerDuration - elapsed;
        const fill = document.getElementById('timerFill');
        const text = document.getElementById('timerText');

        if (remaining <= 0) {
            clearInterval(timerInterval);
            fill.style.width = '0%';
            fill.style.background = '#ef4444';
            text.textContent = 'Expired';
            document.getElementById('qrExpiredBox').style.display = 'block';
            document.getElementById('qrImg').style.opacity = '0.15';
            document.getElementById('qrImg').style.filter = 'blur(4px)';
            return;
        }

        const pct = (remaining / timerDuration) * 100;
        fill.style.width = pct + '%';
        fill.style.background = remaining < 20 ? '#ef4444' : remaining < 40 ? '#f59e0b' : '#7c3aed';
        text.textContent = remaining + 's remaining';
    }, 1000);
}

// ── Polling: check for new charges every 3s ──
function startPolling() {
    if (pollInterval) clearInterval(pollInterval);
    pollInterval = setInterval(checkCharges, 3000);
}

async function checkCharges() {
    try {
        const res  = await fetch(STATUS_URL + '?since=' + pollSince, { credentials: 'include' });
        const data = await res.json();
        if (data.error) return;

        // Update balance
        document.getElementById('qrBalanceDisplay').textContent =
            Number(data.h_coins).toLocaleString();

        // Advance pollSince using server-provided timestamp — no client date parsing
        if (data.next_since && data.next_since > pollSince) {
            pollSince = data.next_since;
        }

        // Redirect to receipt page for first NEW charge not already shown
        if (data.charges && data.charges.length > 0) {
            for (const c of data.charges) {
                const key = String(c.ts);
                if (shownReceipts.has(key)) continue; // already showed receipt for this charge
                if (seenCharges.has(key))   continue;
                seenCharges.add(key);
                shownReceipts.add(key);
                // Persist so returning to this page doesn't re-redirect
                sessionStorage.setItem('shownReceiptTs',
                    [...shownReceipts].join(','));
                clearInterval(pollInterval);
                clearInterval(timerInterval);
                window.location.href = <?= json_encode(base_url('qr-receipt.php')) ?> + '?ts=' + c.ts;
                break;
            }
        }
    } catch (e) { /* silent — network blip */ }
}

async function refreshQR() {
    document.getElementById('qrExpiredBox').style.display = 'none';
    document.getElementById('timerText').textContent = 'Refreshing...';

    const img = document.getElementById('qrImg');
    img.style.opacity = '0.4';
    img.style.filter  = '';

    try {
        const res  = await fetch(TOKEN_URL + '?format=json', { credentials: 'include' });
        const data = await res.json();
        if (data.error) { document.getElementById('timerText').textContent = data.error; return; }

        expiresAt = data.expires_at;
        // Derive server-side generation time (expires_at - 120) so the poll
        // window never depends on the client's wall-clock — immune to skew.
        pollSince = data.expires_at - 120;
        document.getElementById('qrBalanceDisplay').textContent = Number(data.h_coins).toLocaleString();

        img.src = TOKEN_URL + '?t=' + data.expires_at;
        img.style.opacity = '1';
        startTimer();
    } catch (e) {
        document.getElementById('timerText').textContent = 'Failed — tap retry';
        img.style.opacity = '1';
    }
}

// Start
startTimer();
startPolling();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
