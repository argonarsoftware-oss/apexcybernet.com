<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/layout.php';
m_require_login();

$user = current_user($pdo);
$uid  = (int)$user['id'];
$ts   = time();
$sig  = hash_hmac('sha256', $uid . ':' . $ts, QR_HMAC_SECRET);
$token = $uid . ':' . $ts . ':' . $sig;

m_head('Receive HCoins', '<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>');
?>

<div class="m-top">
    <a href="./" class="m-back"><i class="bi bi-arrow-left"></i></a>
    <div class="m-top-title">Receive HCoins</div>
</div>

<div style="display:flex;flex-direction:column;align-items:center;padding:0.75rem 1.25rem 2rem;text-align:center;">

    <!-- Username -->
    <div style="font-size:1.1rem;font-weight:900;margin-bottom:0.25rem;"><?= htmlspecialchars($user['display_name']) ?></div>
    <div style="font-size:0.75rem;color:var(--muted);margin-bottom:1.5rem;">Ask the sender to scan this QR code</div>

    <!-- QR code -->
    <div style="background:#fff;border-radius:22px;padding:1.1rem;display:inline-flex;box-shadow:0 8px 40px rgba(0,0,0,0.4);">
        <div id="qr-canvas" style="width:220px;height:220px;"></div>
    </div>

    <!-- Timer -->
    <div style="margin-top:1rem;display:flex;align-items:center;gap:0.4rem;font-size:0.78rem;color:var(--muted);">
        <i class="bi bi-clock" style="font-size:14px;"></i>
        Expires in <span id="timer" style="color:var(--yellow);font-weight:800;min-width:2.5rem;">2:00</span>
    </div>

    <!-- Refresh -->
    <button onclick="refreshQR()" id="refresh-btn" class="m-btn m-btn-ghost"
            style="margin-top:1.25rem;width:auto;padding:0.65rem 2rem;font-size:0.85rem;">
        <i class="bi bi-arrow-clockwise"></i> Refresh QR
    </button>

    <!-- Hint -->
    <div style="margin-top:2rem;background:var(--card);border:1px solid var(--border);border-radius:14px;padding:1rem 1.1rem;width:100%;text-align:left;">
        <div style="font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted);margin-bottom:0.6rem;">How it works</div>
        <div style="font-size:0.82rem;color:var(--text);display:flex;flex-direction:column;gap:0.5rem;">
            <div><span style="color:var(--accent-l);font-weight:700;">1.</span> Show this screen to the sender</div>
            <div><span style="color:var(--accent-l);font-weight:700;">2.</span> They tap <strong>Send</strong> → <strong>Scan QR Code</strong></div>
            <div><span style="color:var(--accent-l);font-weight:700;">3.</span> They scan and enter the amount</div>
            <div><span style="color:var(--accent-l);font-weight:700;">4.</span> HCoins arrive instantly</div>
        </div>
    </div>

</div>

<?php m_nav('receive'); m_toast(); m_foot(); ?>
<script>
var currentToken = <?= json_encode($token) ?>;
var remaining = 120;
var timerEl = document.getElementById('timer');
var countdown;

function drawQR(token) {
    var box = document.getElementById('qr-canvas');
    box.innerHTML = '';
    new QRCode(box, {
        text: token,
        width: 220,
        height: 220,
        colorDark: '#000000',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.M
    });
}
drawQR(currentToken);
startCountdown();

function startCountdown() {
    clearInterval(countdown);
    countdown = setInterval(function() {
        remaining--;
        var m = Math.floor(remaining / 60);
        var s = remaining % 60;
        timerEl.textContent = m + ':' + (s < 10 ? '0' : '') + s;
        if (remaining <= 30) timerEl.style.color = 'var(--red)';
        else timerEl.style.color = 'var(--yellow)';
        if (remaining <= 0) { clearInterval(countdown); refreshQR(); }
    }, 1000);
}

function refreshQR() {
    var btn = document.getElementById('refresh-btn');
    btn.disabled = true;
    fetch('./api/qr_token.php')
    .then(function(r) { return r.json(); })
    .then(function(d) {
        btn.disabled = false;
        if (d.token) {
            currentToken = d.token;
            drawQR(currentToken);
            remaining = 120;
            timerEl.style.color = 'var(--yellow)';
            startCountdown();
        }
    })
    .catch(function() {
        btn.disabled = false;
        showToast('Could not refresh — try again', 'err');
    });
}
</script>
