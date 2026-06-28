<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user($pdo);

$to      = trim($_GET['to']    ?? '');
$token   = trim($_GET['token'] ?? '');
$ref     = trim($_GET['ref']   ?? '');

// Resolve recipient by ref_code (immutable) when provided, else by name.
// Pin the recipient's identity so a mid-flow username swap can't redirect
// the transfer to a different account.
$recipient = null;
if ($token) {
    // QR token flow — decode the uid from the signed token (HMAC verified on send).
    $parts = explode(':', $token);
    if (count($parts) === 3 && ctype_digit($parts[0])) {
        $rq = $pdo->prepare("SELECT id, display_name, profile_picture, ref_code FROM accounts WHERE id = ?");
        $rq->execute([(int)$parts[0]]);
        $recipient = $rq->fetch();
    }
} elseif ($ref !== '') {
    $rq = $pdo->prepare("SELECT id, display_name, profile_picture, ref_code FROM accounts WHERE ref_code = ? AND claim_status = 'approved'");
    $rq->execute([$ref]);
    $recipient = $rq->fetch();
} elseif ($to !== '') {
    $rq = $pdo->prepare("SELECT id, display_name, profile_picture, ref_code FROM accounts WHERE LOWER(TRIM(display_name)) = LOWER(TRIM(?)) AND claim_status = 'approved' ORDER BY id ASC LIMIT 1");
    $rq->execute([$to]);
    $recipient = $rq->fetch();
}

if (!$recipient) {
    header('Location: ' . base_url('send-hcoins.php?err=notfound'));
    exit;
}

$to_name = $recipient['display_name'];
$to_ref  = $recipient['ref_code'];
$to_pic  = $recipient['profile_picture'];

// Fetch sender balance
$bal = $pdo->prepare("SELECT h_coins FROM accounts WHERE id = ?");
$bal->execute([$user['id']]);
$h_coins = (int)$bal->fetchColumn();

$pageTitle = 'Send H-Coins';
require_once __DIR__ . '/includes/header.php';
?>
<style>
.sc-page {
    min-height: calc(100vh - 70px);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 1.5rem 1rem 2rem;
}

.sc-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 24px;
    width: 100%;
    max-width: 400px;
    overflow: hidden;
    box-shadow: 0 20px 50px rgba(0,0,0,0.4);
}

/* ── Recipient header ── */
.sc-recip {
    padding: 2rem 2rem 1.5rem;
    text-align: center;
    background: linear-gradient(160deg, #1a1a2e, #16162a);
    border-bottom: 1px solid var(--border);
}

.sc-recip-label {
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.15em;
    color: var(--text-muted);
    margin-bottom: 1rem;
    font-weight: 700;
}

.sc-recip-avatar {
    width: 72px;
    height: 72px;
    border-radius: 22px;
    background: linear-gradient(135deg, var(--accent), #6d28d9);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
    font-weight: 900;
    color: #fff;
    margin: 0 auto 1rem;
    box-shadow: 0 8px 24px rgba(124,58,237,0.35);
}

.sc-recip-name {
    font-size: 1.3rem;
    font-weight: 800;
    color: var(--text);
    margin-bottom: 0.25rem;
}

.sc-recip-sub {
    font-size: 0.75rem;
    color: var(--text-muted);
}

/* ── Amount section ── */
.sc-body {
    padding: 1.75rem;
}

.sc-balance-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.25rem;
    padding: 0.75rem 1rem;
    background: var(--bg-dark);
    border: 1px solid var(--border);
    border-radius: 12px;
}

.sc-balance-label {
    font-size: 0.75rem;
    color: var(--text-muted);
    font-weight: 600;
}

.sc-balance-value {
    font-size: 0.9rem;
    font-weight: 800;
    color: var(--accent-light);
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.sc-balance-value img {
    width: 14px;
    height: 14px;
    object-fit: contain;
}

/* Amount input */
.sc-amount-wrap {
    text-align: center;
    margin-bottom: 1.25rem;
}

.sc-amount-label {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: var(--text-muted);
    font-weight: 700;
    margin-bottom: 0.6rem;
}

.sc-amount-input-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.sc-currency {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-muted);
}

.sc-amount-input {
    background: none;
    border: none;
    outline: none;
    font-size: 3.5rem;
    font-weight: 900;
    color: var(--text);
    font-family: inherit;
    width: 180px;
    text-align: center;
    caret-color: var(--accent);
    letter-spacing: -2px;
    overflow: hidden;
    -moz-appearance: textfield;
}
.sc-amount-input::-webkit-outer-spin-button,
.sc-amount-input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.sc-amount-input::placeholder {
    color: rgba(255,255,255,0.1);
}

.sc-amount-unit {
    font-size: 1rem;
    font-weight: 700;
    color: var(--text-muted);
    align-self: flex-end;
    padding-bottom: 0.6rem;
}

.sc-amount-line {
    height: 2px;
    border-radius: 99px;
    background: var(--border);
    margin: 0.5rem auto 0;
    max-width: 240px;
    transition: background 0.2s;
}
.sc-amount-input:focus ~ .sc-amount-line,
.sc-amount-wrap:focus-within .sc-amount-line {
    background: var(--accent);
}

/* Quick amounts */
.sc-quick {
    display: flex;
    gap: 0.4rem;
    margin-bottom: 1.25rem;
    flex-wrap: wrap;
}

.sc-quick-btn {
    flex: 1;
    min-width: 52px;
    padding: 0.45rem 0.25rem;
    background: var(--bg-dark);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-muted);
    font-size: 0.78rem;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.15s;
    text-align: center;
}

.sc-quick-btn:hover {
    border-color: var(--accent);
    color: var(--accent-light);
    background: rgba(124,58,237,0.1);
}

/* Note field */
.sc-note-wrap {
    margin-bottom: 1.5rem;
}

.sc-note-label {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--text-muted);
    display: block;
    margin-bottom: 0.4rem;
}

.sc-note-input {
    width: 100%;
    background: var(--bg-dark);
    border: 1px solid var(--border);
    border-radius: 10px;
    color: var(--text);
    padding: 0.65rem 1rem;
    font-size: 0.85rem;
    font-family: inherit;
    outline: none;
    transition: border-color 0.2s;
    resize: none;
}

.sc-note-input:focus { border-color: var(--accent); }
.sc-note-input::placeholder { color: #374151; }

/* Error */
.sc-error {
    display: none;
    background: rgba(239,68,68,0.1);
    border: 1px solid rgba(239,68,68,0.3);
    border-radius: 10px;
    padding: 0.65rem 1rem;
    color: #fca5a5;
    font-size: 0.82rem;
    margin-bottom: 1rem;
    align-items: center;
    gap: 0.5rem;
}
.sc-error.show { display: flex; }

/* Send button */
.sc-send-btn {
    width: 100%;
    padding: 1rem;
    background: var(--accent);
    color: #fff;
    border: none;
    border-radius: 14px;
    font-size: 1rem;
    font-weight: 800;
    cursor: pointer;
    font-family: inherit;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    transition: all 0.2s;
    box-shadow: 0 6px 20px rgba(124,58,237,0.35);
    letter-spacing: 0.01em;
}
.sc-send-btn:hover   { background: #6d28d9; }
.sc-send-btn:disabled { opacity: 0.45; cursor: not-allowed; box-shadow: none; }

/* Back button */
.sc-back {
    margin-top: 1.25rem;
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
.sc-back:hover { color: var(--text); background: rgba(255,255,255,0.06); border-color: rgba(255,255,255,0.15); }

/* Spinner */
.spinner {
    display: inline-block;
    width: 16px; height: 16px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>

<div class="sc-page">
    <div class="sc-card">

        <!-- Recipient -->
        <div class="sc-recip">
            <div class="sc-recip-label">Sending to</div>
            <div class="sc-recip-avatar" style="<?= !empty($to_pic) ? 'background:transparent;padding:0;overflow:hidden;' : '' ?>">
                <?php if (!empty($to_pic)): ?>
                    <img src="<?= base_url($to_pic) ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">
                <?php else: ?>
                    <?= strtoupper(substr(htmlspecialchars($to_name), 0, 2)) ?>
                <?php endif; ?>
            </div>
            <div class="sc-recip-name"><?= htmlspecialchars($to_name) ?></div>
            <div class="sc-recip-sub" style="font-family:'SF Mono',Consolas,monospace;font-size:0.72rem;color:#a78bfa;background:rgba(124,58,237,0.08);border:1px solid rgba(124,58,237,0.25);padding:0.2rem 0.55rem;border-radius:999px;display:inline-block;margin-top:0.3rem;letter-spacing:0.5px;" title="Immutable account code — this is who the HCoins go to, even if they rename.">
                <i class="bi bi-shield-check" style="margin-right:0.2rem;"></i> <?= htmlspecialchars($to_ref) ?>
            </div>
        </div>

        <!-- Amount + details -->
        <div class="sc-body">

            <div class="sc-balance-row">
                <span class="sc-balance-label">Your balance</span>
                <span class="sc-balance-value">
                    <img src="<?= base_url('images/hcoin-icon.png') ?>" alt="HC">
                    <span id="senderBalance"><?= number_format($h_coins) ?></span> HC
                </span>
            </div>

            <div class="sc-amount-wrap">
                <div class="sc-amount-label">Amount</div>
                <div class="sc-amount-input-wrap">
                    <span class="sc-currency"></span>
                    <input type="number" id="scAmount" class="sc-amount-input"
                           placeholder="0" min="1" max="<?= $h_coins ?>"
                           autofocus inputmode="numeric">
                    <span class="sc-amount-unit">HC</span>
                </div>
                <div class="sc-amount-line"></div>
            </div>

            <div class="sc-quick">
                <button class="sc-quick-btn" onclick="setAmt(50)">50</button>
                <button class="sc-quick-btn" onclick="setAmt(100)">100</button>
                <button class="sc-quick-btn" onclick="setAmt(250)">250</button>
                <button class="sc-quick-btn" onclick="setAmt(500)">500</button>
                <button class="sc-quick-btn" onclick="setAmt(1000)">1K</button>
            </div>

            <div class="sc-note-wrap">
                <label class="sc-note-label" for="scNote">Note <span style="font-weight:400;text-transform:none;letter-spacing:0;">(optional)</span></label>
                <textarea id="scNote" class="sc-note-input" rows="2"
                          placeholder="What's this for?"></textarea>
            </div>

            <div class="sc-error" id="scError">
                <i class="bi bi-exclamation-circle"></i>
                <span id="scErrorText"></span>
            </div>

            <button class="sc-send-btn" id="scSendBtn" onclick="doSend()">
                <i class="bi bi-send-fill"></i> Send H-Coins
            </button>
        </div>
    </div>

    <a href="<?= base_url('send-hcoins.php') ?>" class="sc-back">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<script>
const API_SEND   = <?= json_encode(base_url('api/send-hcoins.php')) ?>;
const RECEIPT_URL = <?= json_encode(base_url('send-receipt.php')) ?>;
const TO_NAME    = <?= json_encode($to_name) ?>;
const TO_REF     = <?= json_encode($to_ref) ?>;
const QR_TOKEN   = <?= json_encode($token) ?>;
const MY_BALANCE = <?= $h_coins ?>;

function setAmt(n) {
    document.getElementById('scAmount').value = n;
    document.getElementById('scAmount').focus();
}

function showError(msg) {
    document.getElementById('scErrorText').textContent = msg;
    document.getElementById('scError').classList.add('show');
}
function hideError() {
    document.getElementById('scError').classList.remove('show');
}

async function doSend() {
    hideError();
    const amount = parseInt(document.getElementById('scAmount').value, 10);

    if (!amount || amount < 1)       { showError('Enter an amount to send'); return; }
    if (amount > MY_BALANCE)         { showError('Not enough H-Coins (balance: ' + MY_BALANCE.toLocaleString() + ' HC)'); return; }

    const btn = document.getElementById('scSendBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Sending...';

    const body = { amount };
    if (QR_TOKEN)     body.token       = QR_TOKEN;
    else if (TO_REF)  body.to_ref_code = TO_REF;
    else              body.to_name     = TO_NAME;

    try {
        const res  = await fetch(API_SEND, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
            credentials: 'include',
        });
        const data = await res.json();

        if (!data.success) {
            showError(data.error || 'Send failed — try again');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-send-fill"></i> Send H-Coins';
            return;
        }

        window.location.href = RECEIPT_URL +
            '?to='      + encodeURIComponent(data.to) +
            '&amount='  + data.amount +
            '&balance=' + data.sender_new_balance;

    } catch (e) {
        showError('Network error — try again');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send-fill"></i> Send H-Coins';
    }
}

// Allow Enter to submit
document.getElementById('scAmount').addEventListener('keydown', e => {
    if (e.key === 'Enter') doSend();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
