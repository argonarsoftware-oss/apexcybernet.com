<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/layout.php';
m_require_login();

$user = current_user($pdo);
$stmt = $pdo->prepare("SELECT h_coins FROM accounts WHERE id = ?");
$stmt->execute([$user['id']]);
$hc = (int)$stmt->fetchColumn();

$mode = ($_GET['mode'] ?? '') === 'scan' ? 'scan' : 'username';

m_head('Send HCoins', '<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>');
?>

<div class="m-top">
    <a href="./" class="m-back"><i class="bi bi-arrow-left"></i></a>
    <div class="m-top-title">Send HCoins</div>
</div>

<!-- Balance -->
<div style="text-align:center;padding:0.25rem 1.25rem 1rem;">
    <div style="font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:var(--muted);">Your Balance</div>
    <div style="font-size:2.2rem;font-weight:900;color:var(--accent-l);line-height:1.1;margin-top:3px;"><?= number_format($hc) ?><span style="font-size:1rem;font-weight:700;margin-left:4px;">HC</span></div>
</div>

<!-- Mode tabs -->
<div class="m-tabs">
    <div class="m-tab<?= $mode === 'username' ? ' on' : '' ?>" id="tab-username" onclick="switchTab('username')">
        <i class="bi bi-person"></i> By Username
    </div>
    <div class="m-tab<?= $mode === 'scan' ? ' on' : '' ?>" id="tab-scan" onclick="switchTab('scan')">
        <i class="bi bi-qr-code-scan"></i> Scan QR Code
    </div>
</div>

<!-- ── Pane: Username ── -->
<div id="pane-username" style="<?= $mode === 'scan' ? 'display:none;' : '' ?>">
    <div class="m-form">
        <div class="m-field">
            <label class="m-lbl">Recipient Username</label>
            <input class="m-inp" type="text" id="toName" placeholder="e.g. john_doe"
                   autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false">
        </div>
        <div class="m-field">
            <label class="m-lbl">Amount (HC)</label>
            <input class="m-inp m-inp-xl" type="number" id="amount" placeholder="0" min="1" max="<?= $hc ?>">
        </div>

        <!-- Recipient preview -->
        <div id="recipient-preview" style="display:none;background:var(--card);border:1px solid var(--border);border-radius:14px;padding:0.85rem 1rem;margin-bottom:1rem;">
            <div style="display:flex;align-items:center;gap:0.75rem;">
                <div style="width:40px;height:40px;background:var(--accent-dim);border-radius:50%;display:flex;align-items:center;justify-content:center;color:var(--accent-l);font-size:19px;flex-shrink:0;">
                    <i class="bi bi-person-fill"></i>
                </div>
                <div>
                    <div style="font-weight:700;font-size:0.95rem;" id="preview-name"></div>
                    <div style="font-size:0.7rem;color:var(--muted);">Recipient</div>
                </div>
            </div>
        </div>

        <button class="m-btn m-btn-primary" id="sendBtn" disabled>
            <i class="bi bi-send-fill"></i> Send HCoins
        </button>
    </div>
</div>

<!-- ── Pane: Scan QR ── -->
<div id="pane-scan" style="<?= $mode === 'username' ? 'display:none;' : '' ?>">
    <div style="padding:0 1rem 0.5rem;">
        <div id="reader" style="border-radius:16px;overflow:hidden;background:var(--card);min-height:260px;"></div>
        <div id="scan-status" style="text-align:center;font-size:0.78rem;color:var(--muted);padding:0.6rem 0;">
            Point your camera at the recipient's QR code
        </div>
    </div>
</div>

<!-- ── Confirm bottom sheet (after QR scan) ── -->
<div id="confirm-sheet" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.75);z-index:200;align-items:flex-end;">
    <div style="background:var(--surf);border-radius:24px 24px 0 0;padding:1.75rem 1.25rem 2rem;width:100%;">
        <div style="font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:var(--muted);margin-bottom:0.3rem;">Sending to</div>
        <div style="font-size:1.3rem;font-weight:900;margin-bottom:1.5rem;" id="sheet-name">—</div>
        <div class="m-field">
            <label class="m-lbl">Amount (HC)</label>
            <input class="m-inp m-inp-xl" type="number" id="sheet-amount" placeholder="0" min="1" max="<?= $hc ?>">
        </div>
        <div style="font-size:0.75rem;color:var(--muted);margin-bottom:1.25rem;">
            Balance: <strong style="color:var(--accent-l);"><?= number_format($hc) ?> HC</strong>
        </div>
        <button class="m-btn m-btn-primary m-gap" id="sheet-send-btn">
            <i class="bi bi-send-fill"></i> Send HCoins
        </button>
        <button class="m-btn m-btn-ghost" id="sheet-cancel-btn">Cancel</button>
    </div>
</div>

<?php m_nav('send'); m_toast(); m_foot(); ?>
<script>
var hc = <?= $hc ?>;
var currentMode = <?= json_encode($mode) ?>;
var scanner = null;
var scannedToken = null;
var confirmSheet = document.getElementById('confirm-sheet');

// ── Tab switching ──
function switchTab(mode) {
    currentMode = mode;
    document.getElementById('tab-username').className = 'm-tab' + (mode === 'username' ? ' on' : '');
    document.getElementById('tab-scan').className     = 'm-tab' + (mode === 'scan'     ? ' on' : '');
    document.getElementById('pane-username').style.display = mode === 'username' ? '' : 'none';
    document.getElementById('pane-scan').style.display     = mode === 'scan'     ? '' : 'none';
    if (mode === 'scan' && !scanner) startScanner();
    if (mode === 'username' && scanner) { scanner.stop().catch(function(){}); scanner = null; }
}

// ── Username tab ──
var toNameEl  = document.getElementById('toName');
var amountEl  = document.getElementById('amount');
var sendBtn   = document.getElementById('sendBtn');
var previewEl = document.getElementById('recipient-preview');

function validate() {
    var name = toNameEl.value.trim();
    var amt  = parseInt(amountEl.value) || 0;
    sendBtn.disabled = !(name && amt >= 1 && amt <= hc);
    if (name) {
        document.getElementById('preview-name').textContent = name;
        previewEl.style.display = 'block';
    } else {
        previewEl.style.display = 'none';
    }
}
toNameEl.addEventListener('input', validate);
amountEl.addEventListener('input', validate);

sendBtn.addEventListener('click', function() {
    var amt  = parseInt(amountEl.value) || 0;
    var name = toNameEl.value.trim();
    if (!amt || amt < 1 || amt > hc) { showToast('Enter a valid amount', 'err'); return; }
    doSend(null, name, amt);
});

// ── Scan tab ──
function startScanner() {
    document.getElementById('scan-status').textContent = 'Initializing camera…';
    scanner = new Html5Qrcode('reader');
    scanner.start(
        { facingMode: 'environment' },
        { fps: 10, qrbox: { width: 220, height: 220 } },
        function(decoded) {
            document.getElementById('scan-status').textContent = 'QR detected!';
            scanner.stop().catch(function(){});
            scanner = null;
            verifyToken(decoded);
        },
        function(err) {}
    ).then(function() {
        document.getElementById('scan-status').textContent = 'Scanning…';
    }).catch(function() {
        document.getElementById('scan-status').textContent = 'Camera unavailable — switch to By Username';
    });
}

function verifyToken(token) {
    if (!token) return;
    fetch('<?= m_base('../api/send-hcoins.php') ?>', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ token: token, amount: 0 })
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.error && d.error.includes('Amount')) {
            scannedToken = token;
            var uid = token.split(':')[0];
            fetch('<?= m_base('api/resolve_user.php') ?>?uid=' + uid)
            .then(function(r) { return r.json(); })
            .then(function(u) {
                document.getElementById('sheet-name').textContent = u.name || ('User #' + uid);
                document.getElementById('sheet-amount').value = '';
                confirmSheet.style.display = 'flex';
            });
        } else if (d.error && d.error.includes('expired')) {
            showToast('QR expired — ask them to refresh', 'err');
            restartScanner();
        } else if (d.error && d.error.includes('signature')) {
            showToast('Invalid QR code', 'err');
            restartScanner();
        } else {
            showToast(d.error || 'Unknown error verifying QR', 'err');
            restartScanner();
        }
    })
    .catch(function() { showToast('Network error', 'err'); restartScanner(); });
}

function restartScanner() {
    if (currentMode === 'scan' && !scanner) startScanner();
}

// ── Confirm sheet ──
document.getElementById('sheet-cancel-btn').addEventListener('click', function() {
    confirmSheet.style.display = 'none';
    scannedToken = null;
    restartScanner();
});

document.getElementById('sheet-send-btn').addEventListener('click', function() {
    var amt = parseInt(document.getElementById('sheet-amount').value) || 0;
    if (!amt || amt < 1 || amt > hc) {
        showToast('Enter a valid amount (max ' + hc.toLocaleString() + ' HC)', 'err');
        return;
    }
    doSend(scannedToken, null, amt);
});

// ── Shared send logic ──
function doSend(token, toName, amount) {
    var body = { amount: amount };
    if (token) body.token  = token;
    else       body.to_name = toName;

    var btn = token ? document.getElementById('sheet-send-btn') : sendBtn;
    btn.disabled = true;

    fetch('<?= m_base('../api/send-hcoins.php') ?>', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify(body)
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        btn.disabled = false;
        if (d.success) {
            confirmSheet.style.display = 'none';
            scannedToken = null;
            showToast('Sent ' + amount.toLocaleString() + ' HC to ' + d.to + '!', 'ok');
            hc = d.sender_new_balance;
            if (!token) {
                toNameEl.value = '';
                amountEl.value = '';
                previewEl.style.display = 'none';
                validate();
            } else if (currentMode === 'scan') {
                restartScanner();
            }
        } else {
            showToast(d.error || 'Send failed', 'err');
            if (token) {
                confirmSheet.style.display = 'none';
                scannedToken = null;
                restartScanner();
            }
        }
    })
    .catch(function() {
        btn.disabled = false;
        showToast('Network error — try again', 'err');
    });
}

// Auto-start scanner if opened in scan mode
if (currentMode === 'scan') startScanner();
</script>
