<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user($pdo);

$hc_q = $pdo->prepare("SELECT h_coins FROM accounts WHERE id = ?");
$hc_q->execute([$user['id']]);
$h_coins = (int)$hc_q->fetchColumn();

$uid        = (int)$user['id'];
$ts         = time();
$sig        = hash_hmac('sha256', $uid . ':' . $ts, QR_HMAC_SECRET);
$token      = $uid . ':' . $ts . ':' . $sig;
$expires_at = $ts + 120;

$qr_url = base_url('api/qr-token.php') . '?t=' . $ts;

$pageTitle = 'Receive H-Coins';
require_once __DIR__ . '/includes/header.php';
?>

<style>
.recv-page {
    min-height: calc(100vh - 70px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem 1rem;
}

.recv-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 24px;
    padding: 2rem;
    width: 100%;
    max-width: 360px;
    text-align: center;
    box-shadow: 0 20px 50px rgba(0,0,0,0.4);
}

/* Header */
.recv-header { margin-bottom: 1.5rem; }

.recv-avatar {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    background: linear-gradient(135deg, #16a34a, #15803d);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    font-weight: 800;
    color: #fff;
    margin-bottom: 0.75rem;
}

.recv-name {
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--text);
}

.recv-balance {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    background: rgba(34,197,94,0.12);
    border: 1px solid rgba(34,197,94,0.25);
    border-radius: 99px;
    padding: 0.3rem 0.9rem;
    font-size: 0.9rem;
    font-weight: 700;
    color: #4ade80;
    margin-top: 0.4rem;
}

.recv-balance img { width: 16px; height: 16px; object-fit: contain; }

/* Label above QR */
.recv-label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: var(--text-muted);
    margin-bottom: 0.75rem;
}

/* QR */
.recv-qr-wrap {
    background: #fff;
    border-radius: 16px;
    padding: 1rem;
    display: inline-block;
    margin-bottom: 1rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
    position: relative;
}

.recv-qr-wrap img { display: block; border-radius: 4px; }

/* Timer bar */
.recv-timer-bar {
    width: 100%;
    height: 3px;
    background: var(--border);
    border-radius: 99px;
    overflow: hidden;
    margin-bottom: 0.5rem;
}

.recv-timer-fill {
    height: 100%;
    border-radius: 99px;
    background: #22c55e;
    transition: width 1s linear, background 0.5s;
}

.recv-timer-text {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-bottom: 1.25rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
}

/* Expired */
.recv-expired {
    display: none;
    background: rgba(239,68,68,0.1);
    border: 1px solid rgba(239,68,68,0.3);
    border-radius: 10px;
    padding: 0.65rem;
    color: #f87171;
    font-size: 0.82rem;
    font-weight: 700;
    margin-bottom: 0.75rem;
}

/* Hint */
.recv-hint {
    font-size: 0.78rem;
    color: var(--text-muted);
    line-height: 1.5;
    margin-bottom: 1.25rem;
}

/* Refresh */
.recv-refresh-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.5rem 1.25rem;
    background: rgba(34,197,94,0.1);
    border: 1px solid rgba(34,197,94,0.25);
    border-radius: 9px;
    color: #4ade80;
    font-size: 0.82rem;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
    transition: background 0.2s;
    margin-bottom: 1.25rem;
}
.recv-refresh-btn:hover { background: rgba(34,197,94,0.2); }

/* Received toast */
.recv-toast {
    position: fixed;
    left: 50%;
    bottom: 28px;
    transform: translateX(-50%) translateY(120%);
    background: linear-gradient(135deg, #16a34a, #15803d);
    color: #fff;
    padding: 0.85rem 1.25rem;
    border-radius: 14px;
    box-shadow: 0 12px 40px rgba(0,0,0,0.4), 0 0 0 1px rgba(255,255,255,0.08);
    display: flex;
    align-items: center;
    gap: 0.7rem;
    font-weight: 700;
    font-size: 0.88rem;
    opacity: 0;
    pointer-events: none;
    transition: transform 0.4s cubic-bezier(0.34,1.56,0.64,1), opacity 0.3s;
    z-index: 9999;
    max-width: 92vw;
}
.recv-toast.show {
    transform: translateX(-50%) translateY(0);
    opacity: 1;
}
.recv-toast i { font-size: 1.3rem; }
.recv-toast-amount { color: #bbf7d0; font-weight: 900; }

/* Balance flash when it jumps */
@keyframes balancePop {
    0%   { transform: scale(1);   color: var(--text); }
    40%  { transform: scale(1.15); color: #4ade80; }
    100% { transform: scale(1);   color: var(--text); }
}
.recv-balance.flash #recvBalanceDisplay {
    display: inline-block;
    animation: balancePop 0.8s ease;
}

/* Back button */
.recv-back {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--text-muted);
    text-decoration: none;
    background: var(--bg-dark);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 0.55rem 1.25rem;
    transition: all 0.15s;
    font-family: inherit;
}
.recv-back:hover { color: var(--text); background: rgba(255,255,255,0.06); border-color: rgba(255,255,255,0.15); }
</style>

<div class="recv-page">
    <div class="recv-card">

        <div class="recv-header">
            <div class="recv-avatar"><?= strtoupper(substr($user['display_name'] ?: $user['email'], 0, 2)) ?></div>
            <div class="recv-name"><?= htmlspecialchars($user['display_name'] ?: $user['email']) ?></div>
            <div>
                <span class="recv-balance">
                    <img src="<?= base_url('images/hcoin-icon.png') ?>" alt="HC">
                    <span id="recvBalanceDisplay"><?= number_format($h_coins) ?></span> HC
                </span>
            </div>
        </div>

        <div class="recv-label"><i class="bi bi-arrow-down-circle"></i> Your Receive QR</div>

        <div id="recvExpiredBox" class="recv-expired">
            <i class="bi bi-exclamation-triangle-fill"></i> QR expired — tap refresh
        </div>

        <div class="recv-qr-wrap">
            <img id="recvQrImg" src="<?= htmlspecialchars($qr_url) ?>"
                 alt="Your Receive QR" width="220" height="220">
        </div>

        <div class="recv-timer-bar">
            <div class="recv-timer-fill" id="recvTimerFill" style="width:100%;"></div>
        </div>

        <div class="recv-timer-text">
            <i class="bi bi-clock"></i>
            <span id="recvTimerText">Valid for 2 minutes</span>
        </div>

        <div class="recv-hint">
            Show this QR to the person sending you H-Coins.<br>
            They scan it in <strong>Send H-Coins → Scan QR</strong>.
        </div>

        <button class="recv-refresh-btn" onclick="refreshRecvQR()">
            <i class="bi bi-arrow-clockwise"></i> Refresh QR
        </button>

        <br>
        <a href="<?= base_url('dashboard.php') ?>" class="recv-back">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>

    </div>
</div>

<div id="recvToast" class="recv-toast">
    <i class="bi bi-check-circle-fill"></i>
    <div>
        Received <span class="recv-toast-amount" id="recvToastAmount">0</span> HC
        <div id="recvToastFrom" style="font-size:0.72rem; font-weight:600; opacity:0.85;"></div>
    </div>
</div>

<script>
const TOKEN_URL  = <?= json_encode(base_url('api/qr-token.php')) ?>;
const STATUS_URL = <?= json_encode(base_url('api/qr-status.php')) ?>;

let expiresAt     = <?= $expires_at ?>;
let pollSince     = <?= $ts ?>;
// Count down from elapsed time since timer (re)start, immune to client clock skew.
let timerStartMs  = Date.now();
let timerDuration = 120;
let timerInterval = null;

function startTimer() {
    if (timerInterval) clearInterval(timerInterval);
    timerStartMs  = Date.now();
    timerDuration = 120;
    timerInterval = setInterval(() => {
        const elapsed   = Math.floor((Date.now() - timerStartMs) / 1000);
        const remaining = timerDuration - elapsed;
        const fill = document.getElementById('recvTimerFill');
        const text = document.getElementById('recvTimerText');

        if (remaining <= 0) {
            clearInterval(timerInterval);
            fill.style.width = '0%';
            fill.style.background = '#ef4444';
            text.textContent = 'Expired';
            document.getElementById('recvExpiredBox').style.display = 'block';
            document.getElementById('recvQrImg').style.opacity = '0.15';
            document.getElementById('recvQrImg').style.filter = 'blur(4px)';
            return;
        }

        const pct = (remaining / timerDuration) * 100;
        fill.style.width = pct + '%';
        fill.style.background = remaining < 20 ? '#ef4444' : remaining < 40 ? '#f59e0b' : '#22c55e';
        text.textContent = remaining + 's remaining';
    }, 1000);
}

// ── Received-payment toast ──
let recvToastTimer = null;
let seenReceipts   = new Set();
function showReceivedToast(amount, from) {
    const toast  = document.getElementById('recvToast');
    const amtEl  = document.getElementById('recvToastAmount');
    const fromEl = document.getElementById('recvToastFrom');
    amtEl.textContent  = '+' + Number(amount).toLocaleString();
    fromEl.textContent = from ? 'from ' + from : '';
    toast.classList.add('show');
    if (recvToastTimer) clearTimeout(recvToastTimer);
    recvToastTimer = setTimeout(() => toast.classList.remove('show'), 4000);

    // Flash the balance figure so the user feels the change even if they
    // glance away from the toast.
    const balWrap = document.querySelector('.recv-balance');
    balWrap.classList.remove('flash');
    void balWrap.offsetWidth; // reflow, restart the animation
    balWrap.classList.add('flash');
}

// Poll for incoming credits so the balance updates live and notify the user
// the moment money hits their wallet.
function startPolling() {
    setInterval(async () => {
        try {
            const res  = await fetch(STATUS_URL + '?since=' + pollSince, { credentials: 'include' });
            const data = await res.json();
            if (data.error) return;
            if (data.next_since && data.next_since > pollSince) pollSince = data.next_since;

            if (typeof data.h_coins === 'number') {
                document.getElementById('recvBalanceDisplay').textContent =
                    Number(data.h_coins).toLocaleString();
            }

            if (Array.isArray(data.receipts) && data.receipts.length > 0) {
                for (const r of data.receipts) {
                    const key = String(r.ts) + ':' + r.amount;
                    if (seenReceipts.has(key)) continue;
                    seenReceipts.add(key);
                    showReceivedToast(r.amount, r.from);
                }
            }
        } catch(e) {}
    }, 3000);
}

async function refreshRecvQR() {
    document.getElementById('recvExpiredBox').style.display = 'none';
    document.getElementById('recvTimerText').textContent = 'Refreshing...';

    const img = document.getElementById('recvQrImg');
    img.style.opacity = '0.4';
    img.style.filter  = '';

    try {
        const res  = await fetch(TOKEN_URL + '?format=json', { credentials: 'include' });
        const data = await res.json();
        if (data.error) { document.getElementById('recvTimerText').textContent = data.error; return; }

        expiresAt = data.expires_at;
        img.src = TOKEN_URL + '?t=' + data.expires_at;
        img.style.opacity = '1';
        if (data.h_coins !== undefined) {
            document.getElementById('recvBalanceDisplay').textContent =
                Number(data.h_coins).toLocaleString();
        }
        startTimer();
    } catch (e) {
        document.getElementById('recvTimerText').textContent = 'Failed — tap retry';
        img.style.opacity = '1';
    }
}

startTimer();
startPolling();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
