<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/listener-api.php';
require_once __DIR__ . '/layout.php';
m_require_login();

$user = current_user($pdo);
$uid  = (int)$user['id'];

$PACKAGES = [
    ['coins' => 50,   'price' => 50.00],
    ['coins' => 100,  'price' => 100.00],
    ['coins' => 250,  'price' => 250.00],
    ['coins' => 500,  'price' => 500.00],
];

// ── Cancel order ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $ref = $_SESSION['hc_order_ref'] ?? '';
    if ($ref) {
        $pdo->prepare("UPDATE h_coin_orders SET status='cancelled' WHERE ref_code=? AND account_id=? AND status='pending'")
            ->execute([$ref, $uid]);
        unset($_SESSION['hc_order_ref']);
    }
    header('Location: ' . m_base('buy.php'));
    exit;
}

// ── Create order ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy_package'])) {
    $pkg = $PACKAGES[(int)$_POST['buy_package']] ?? null;
    if ($pkg) {
        $rand = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
        $ref  = 'HC-' . $uid . '-' . $rand;
        $pdo->prepare("UPDATE h_coin_orders SET status='cancelled' WHERE account_id=? AND status='pending'")->execute([$uid]);
        unset($_SESSION['hc_order_ref']);
        $pdo->prepare("INSERT INTO h_coin_orders (account_id, ref_code, coins, peso_amount) VALUES (?,?,?,?)")
            ->execute([$uid, $ref, $pkg['coins'], $pkg['price']]);
        $_SESSION['hc_order_ref'] = $ref;
    }
    header('Location: ' . m_base('buy.php'));
    exit;
}

// ── Resolve active order ──
$activeOrder = false;
$payAmount   = 0;
$orderCoins  = 0;
$slotBusy    = false;
$retryAfter  = 0;

$ref = $_SESSION['hc_order_ref'] ?? '';
if ($ref) {
    $stmt = $pdo->prepare("SELECT * FROM h_coin_orders WHERE ref_code=? AND account_id=? AND status='pending'");
    $stmt->execute([$ref, $uid]);
    $dbOrder = $stmt->fetch();
    if ($dbOrder) {
        $orderCoins = $dbOrder['coins'];
        $res = listenerCreateOrder($ref, (float)$dbOrder['peso_amount'], "Argonar HCoins: {$dbOrder['coins']} HC");
        if ($res) {
            if (!empty($res['success'])) {
                $activeOrder = true;
                $payAmount   = $res['pay_amount'] ?? $dbOrder['peso_amount'];
            } elseif (($res['error'] ?? '') === 'slot_busy') {
                $slotBusy   = true;
                $retryAfter = $res['retry_after'] ?? 60;
            } elseif (strpos($res['error'] ?? '', 'already exists') !== false) {
                $activeOrder = true;
                $ex = listenerGetOrder($ref);
                $payAmount = $ex['order']['pay_amount'] ?? $dbOrder['peso_amount'];
            }
        }
    } else {
        unset($_SESSION['hc_order_ref']);
        $ref = '';
    }
}

// Fresh balance
$stmt = $pdo->prepare("SELECT h_coins FROM accounts WHERE id=?");
$stmt->execute([$uid]);
$hc = (int)$stmt->fetchColumn();

$checkUrl = m_base('api/topup.php?action=check');

m_head('Buy HC');
?>

<div class="m-top">
    <a href="./" class="m-back"><i class="bi bi-arrow-left"></i></a>
    <div class="m-top-title">Buy HCoins</div>
</div>

<div style="text-align:center;padding:0.25rem 1rem 1rem;">
    <div style="display:inline-block;background:var(--accent-dim);border:1px solid rgba(124,58,237,0.3);border-radius:99px;padding:0.35rem 1.1rem;font-size:0.82rem;font-weight:700;color:var(--accent-l);">
        Balance: <?= number_format($hc) ?> HC
    </div>
</div>

<?php if ($activeOrder): ?>
<div style="margin:0 1rem 1rem;background:linear-gradient(135deg,var(--accent),#6366f1);border-radius:18px;padding:1.25rem;text-align:center;color:#fff;">
    <div style="font-size:0.72rem;opacity:0.8;text-transform:uppercase;letter-spacing:0.1em;">Amount to Pay</div>
    <div style="font-size:2.4rem;font-weight:900;margin:0.2rem 0;">₱<?= number_format($payAmount, 2) ?></div>
    <div style="font-size:0.82rem;opacity:0.85;">You receive <strong><?= number_format($orderCoins) ?> HC</strong></div>
</div>

<div style="text-align:center;margin-bottom:1rem;">
    <div style="font-size:0.7rem;color:var(--muted);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:0.75rem;">
        Scan with any banking or e-wallet app
    </div>
    <div class="qr-box" style="display:inline-flex;">
        <img src="https://argonar.co/payment/generate-qr.php?amount=<?= number_format($payAmount, 2, '.', '') ?>"
             alt="Payment QR" style="width:200px;height:200px;">
    </div>
    <div style="font-size:0.7rem;color:var(--muted);margin-top:0.6rem;">QR Ph (InstaPay) — Argonar Software</div>
</div>

<div id="status-box" style="margin:0 1rem 1rem;border:2px dashed var(--border);border-radius:14px;padding:1.25rem;text-align:center;">
    <div id="status-waiting">
        <div style="width:24px;height:24px;border:3px solid var(--accent);border-top-color:transparent;border-radius:50%;animation:spin 0.9s linear infinite;margin:0 auto 0.6rem;"></div>
        <div style="font-size:0.9rem;color:var(--muted);">Waiting for payment…</div>
        <div style="font-size:0.72rem;color:var(--muted);margin-top:0.4rem;">Keep this page open</div>
    </div>
    <div id="status-paid" style="display:none;">
        <i class="bi bi-check-circle-fill" style="font-size:2.5rem;color:var(--green);display:block;margin-bottom:0.5rem;"></i>
        <div style="font-weight:800;color:var(--green);">Payment Received!</div>
        <div style="font-size:0.82rem;color:var(--muted);margin-top:0.3rem;">Adding HCoins to your balance…</div>
    </div>
</div>

<div style="margin:0 1rem 1.5rem;text-align:center;">
    <form method="POST">
        <button type="submit" name="cancel_order" value="1"
                onclick="return confirm('Cancel this order?')"
                style="background:rgba(248,113,113,0.1);border:1px solid rgba(248,113,113,0.3);color:var(--red);border-radius:10px;padding:0.55rem 1.25rem;font-size:0.8rem;font-weight:700;cursor:pointer;font-family:inherit;">
            <i class="bi bi-x-circle"></i> Cancel Order
        </button>
    </form>
</div>

<?php elseif ($slotBusy): ?>
<div style="margin:2rem 1rem;text-align:center;">
    <i class="bi bi-hourglass-split" style="font-size:2.5rem;color:var(--yellow);display:block;margin-bottom:0.75rem;"></i>
    <div style="font-weight:700;">Another payment is in progress</div>
    <div style="font-size:0.82rem;color:var(--muted);margin-top:0.4rem;">Retrying in <strong id="slot-secs"><?= $retryAfter ?></strong>s…</div>
</div>

<?php else: ?>

<!-- Tab switcher -->
<div style="display:flex;margin:0 1rem 1rem;border:1.5px solid var(--border);border-radius:12px;overflow:hidden;">
    <button id="tabInstapay" onclick="switchBuyTab('instapay')"
            style="flex:1;padding:0.65rem 0.5rem;font-size:0.8rem;font-weight:700;background:var(--accent);color:#000;border:none;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:0.35rem;">
        <i class="bi bi-qr-code"></i> InstaPay
    </button>
    <button id="tabMerchant" onclick="switchBuyTab('merchant')"
            style="flex:1;padding:0.65rem 0.5rem;font-size:0.8rem;font-weight:700;background:transparent;color:var(--muted);border:none;border-left:1.5px solid var(--border);cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:0.35rem;">
        <i class="bi bi-shop"></i> From Merchant
    </button>
</div>

<!-- InstaPay tab -->
<div id="instapayContent">
    <div style="text-align:center;margin-bottom:0.75rem;font-size:0.78rem;color:var(--muted);">
        1 HC = ₱1 &nbsp;·&nbsp; Paid via QR Ph InstaPay &nbsp;·&nbsp; Auto-credited instantly
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.7rem;padding:0 1rem 1.5rem;">
        <?php foreach ($PACKAGES as $i => $pkg): ?>
        <form method="POST">
            <button type="submit" name="buy_package" value="<?= $i ?>"
                    style="width:100%;background:var(--card);border:1.5px solid var(--border);border-radius:16px;padding:1.1rem 0.75rem;cursor:pointer;text-align:center;color:var(--text);display:block;">
                <div style="font-size:1.6rem;font-weight:900;color:var(--accent-l);"><?= number_format($pkg['coins']) ?></div>
                <div style="font-size:0.7rem;color:var(--muted);margin-bottom:0.6rem;font-weight:700;">HC</div>
                <div style="background:var(--accent);color:#fff;border-radius:8px;padding:0.35rem;font-size:0.85rem;font-weight:800;">₱<?= number_format($pkg['price'], 2) ?></div>
            </button>
        </form>
        <?php endforeach; ?>
    </div>
</div>

<!-- From Merchant tab -->
<div id="merchantContent" style="display:none;padding:0 1rem 1.5rem;">
    <div style="text-align:center;margin-bottom:1rem;font-size:0.78rem;color:var(--muted);">
        1 HC = ₱1 &nbsp;·&nbsp; Pay cash to merchant in person &nbsp;·&nbsp; HC credited instantly
    </div>

    <style>
    @keyframes mqrFadeIn { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }
    .mqr-animate { animation: mqrFadeIn 0.3s cubic-bezier(0.25,0.46,0.45,0.94) both; }
    </style>

    <!-- Amount selection -->
    <div id="mqrPicker">
        <div style="font-size:0.7rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:0.6rem;">Select HC amount</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.6rem;margin-bottom:0.75rem;">
            <?php foreach ($PACKAGES as $pkg): ?>
            <button class="mqr-pkg-btn" data-coins="<?= $pkg['coins'] ?>" onclick="setMerchantAmount(<?= $pkg['coins'] ?>, this)"
                    style="background:var(--card);border:1.5px solid var(--border);border-radius:14px;padding:0.85rem 0.5rem;cursor:pointer;text-align:center;color:var(--text);position:relative;transition:border-color 0.15s,background 0.15s;">
                <div style="font-size:1.35rem;font-weight:900;color:var(--accent-l);"><?= number_format($pkg['coins']) ?></div>
                <div style="font-size:0.68rem;color:var(--muted);font-weight:700;margin-bottom:0.35rem;">HC</div>
                <div style="font-size:0.75rem;color:var(--muted);">≈ ₱<?= number_format($pkg['price'], 2) ?> cash</div>
                <span class="mqr-check" style="display:none;position:absolute;top:6px;right:8px;font-size:0.7rem;color:var(--accent);"><i class="bi bi-check-circle-fill"></i></span>
            </button>
            <?php endforeach; ?>
        </div>
        <div style="display:flex;align-items:center;gap:0.5rem;background:var(--card);border:1px solid var(--border);border-radius:10px;padding:0 0.85rem;margin-bottom:0.75rem;">
            <span style="font-size:0.7rem;font-weight:700;color:var(--muted);white-space:nowrap;">Custom HC</span>
            <input type="number" id="mqrCustom" min="1" max="100000" placeholder="e.g. 75"
                   style="flex:1;background:transparent;border:none;color:var(--text);padding:0.65rem 0;font-size:0.88rem;font-family:inherit;outline:none;"
                   oninput="mqrSelectedAmount=parseInt(this.value)||0;document.querySelectorAll('.mqr-pkg-btn').forEach(function(b){b.style.borderColor='var(--border)';b.style.background='var(--card)';b.querySelector('.mqr-check').style.display='none';})">
        </div>
        <button onclick="generateMerchantQR()"
                style="width:100%;background:var(--accent);color:#000;border:none;border-radius:10px;padding:0.75rem;font-size:0.88rem;font-weight:800;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:0.4rem;">
            <i class="bi bi-qr-code-scan"></i> Generate QR
        </button>
    </div>

    <!-- QR display -->
    <div id="mqrDisplay" style="display:none;text-align:center;" class="mqr-animate">
        <div style="font-size:0.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:0.75rem;">
            Show this QR to the merchant
        </div>
        <div style="display:inline-flex;background:#fff;border-radius:16px;padding:12px;margin-bottom:0.75rem;">
            <div id="mqrCode"></div>
        </div>
        <div style="font-size:0.78rem;color:var(--muted);margin-bottom:0.35rem;">
            <strong id="mqrAmountLbl" style="color:var(--accent-l);font-size:1rem;"></strong> HC
            &nbsp;·&nbsp; ≈ ₱<span id="mqrPesoLbl"></span> cash
        </div>
        <div id="mqrTimer" style="font-size:0.72rem;color:var(--muted);margin-bottom:0.85rem;"></div>

        <!-- Waiting indicator -->
        <div id="mqrWaiting" style="display:flex;align-items:center;justify-content:center;gap:0.55rem;background:var(--card);border:1px solid var(--border);border-radius:12px;padding:0.75rem 1rem;margin-bottom:1rem;">
            <div style="width:16px;height:16px;border:2.5px solid var(--border);border-top-color:var(--accent);border-radius:50%;animation:spin 0.85s linear infinite;flex-shrink:0;"></div>
            <div style="text-align:left;">
                <div style="font-size:0.8rem;font-weight:700;color:var(--text);">Waiting for merchant…</div>
                <div style="font-size:0.68rem;color:var(--muted);margin-top:1px;">Keep this screen open</div>
            </div>
        </div>

        <!-- Paid indicator (hidden until WebSocket fires) -->
        <div id="mqrPaid" style="display:none;text-align:center;background:rgba(34,197,94,0.08);border:1.5px solid #22c55e;border-radius:14px;padding:1rem;margin-bottom:1rem;">
            <div style="font-size:1.6rem;font-weight:900;color:#4ade80;">+<span id="mqrPaidAmt"></span> HC</div>
            <div style="font-size:0.8rem;color:#86efac;margin-top:0.25rem;">Received from merchant</div>
            <div style="font-size:0.72rem;color:#6b7280;margin-top:0.2rem;">Balance: <span id="mqrPaidBal"></span> HC</div>
        </div>

        <button onclick="resetMerchantQR()"
                style="background:transparent;border:1px solid var(--border);border-radius:10px;color:var(--muted);padding:0.5rem 1.2rem;font-size:0.8rem;font-weight:700;cursor:pointer;font-family:inherit;">
            <i class="bi bi-arrow-clockwise"></i> New QR
        </button>
    </div>
</div>

<div style="margin:0 1rem;padding:0.75rem 1rem;background:var(--card);border:1px solid var(--border);border-radius:12px;font-size:0.72rem;color:var(--muted);line-height:1.6;">
    <i class="bi bi-info-circle" style="color:var(--accent-l);"></i>
    HCoins are virtual reward currency for use on Argonar only. Not legal tender.
</div>
<?php endif; ?>


<?php m_nav(); m_toast(); m_foot(); ?>
<style>@keyframes spin{to{transform:rotate(360deg);}}</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
<?php if ($activeOrder): ?>
(function(){
    var checkUrl = <?= json_encode($checkUrl) ?>;
    var poll = setInterval(function(){
        fetch(checkUrl).then(function(r){return r.json();}).then(function(d){
            if (d.paid) {
                clearInterval(poll);
                document.getElementById('status-waiting').style.display='none';
                document.getElementById('status-paid').style.display='block';
                var box=document.getElementById('status-box');
                box.style.borderColor='var(--green)';
                box.style.background='rgba(34,197,94,0.05)';
                setTimeout(function(){ location.href='./'; }, 2200);
            }
        }).catch(function(){});
    }, 3000);
    setTimeout(function(){ location.reload(); }, 300000);
})();
<?php endif; ?>
<?php if ($slotBusy): ?>
(function(){
    var n=<?= $retryAfter ?>, el=document.getElementById('slot-secs');
    var t=setInterval(function(){ n--; if(el)el.textContent=n; if(n<=0){clearInterval(t);location.reload();} },1000);
})();
<?php endif; ?>

<?php if (!$activeOrder && !$slotBusy): ?>
// ── Buy tab switcher ──
var mqrSelectedAmount = 0;
var mqrTimerInterval  = null;
var mqrQrObj          = null;

function switchBuyTab(tab) {
    var isInstapay = (tab === 'instapay');
    document.getElementById('tabInstapay').style.background   = isInstapay ? 'var(--accent)'     : 'transparent';
    document.getElementById('tabInstapay').style.color        = isInstapay ? '#000'              : 'var(--muted)';
    document.getElementById('tabMerchant').style.background   = isInstapay ? 'transparent'       : 'var(--accent)';
    document.getElementById('tabMerchant').style.color        = isInstapay ? 'var(--muted)'      : '#000';
    document.getElementById('instapayContent').style.display  = isInstapay ? '' : 'none';
    document.getElementById('merchantContent').style.display  = isInstapay ? 'none' : '';
}

function setMerchantAmount(n, btn) {
    mqrSelectedAmount = n;
    document.getElementById('mqrCustom').value = '';
    document.querySelectorAll('.mqr-pkg-btn').forEach(function(b) {
        var selected = (b === btn);
        b.style.borderColor = selected ? 'var(--accent)' : 'var(--border)';
        b.style.background  = selected ? 'rgba(124,58,237,0.08)' : 'var(--card)';
        b.querySelector('.mqr-check').style.display = selected ? '' : 'none';
    });
}

function generateMerchantQR() {
    var hc = mqrSelectedAmount || parseInt(document.getElementById('mqrCustom').value) || 0;
    if (hc < 1) { alert('Select or enter an HC amount first.'); return; }

    fetch(<?= json_encode(m_base('api/buy-qr.php')) ?> + '?hcoins=' + hc)
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.error) { alert(d.error); return; }
            var picker  = document.getElementById('mqrPicker');
            var display = document.getElementById('mqrDisplay');
            picker.style.transition = 'opacity 0.2s';
            picker.style.opacity    = '0';
            setTimeout(function() {
                picker.style.display    = 'none';
                picker.style.opacity    = '';
                picker.style.transition = '';

                document.getElementById('mqrAmountLbl').textContent = hc.toLocaleString();
                document.getElementById('mqrPesoLbl').textContent   = hc.toLocaleString();

                // Render QR
                var box = document.getElementById('mqrCode');
                box.innerHTML = '';
                mqrQrObj = new QRCode(box, {
                    text:   d.token,
                    width:  260,
                    height: 260,
                    colorDark:  '#000000',
                    colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.M,
                });

                // Countdown
                var secsLeft = d.expires_in || 300;
                if (mqrTimerInterval) clearInterval(mqrTimerInterval);
                mqrTimerInterval = setInterval(function(){
                    secsLeft--;
                    var m = Math.floor(secsLeft / 60), s = secsLeft % 60;
                    var el = document.getElementById('mqrTimer');
                    if (el) el.textContent = 'Expires in ' + m + ':' + (s < 10 ? '0' : '') + s;
                    if (secsLeft <= 0) {
                        clearInterval(mqrTimerInterval);
                        if (el) el.textContent = 'QR expired — tap New QR to refresh';
                    }
                }, 1000);

                display.classList.remove('mqr-animate');
                void display.offsetWidth;
                display.classList.add('mqr-animate');
                display.style.display = '';
            }, 200);
        })
        .catch(function(){ alert('Network error — try again'); });
}

function resetMerchantQR() {
    if (mqrTimerInterval) { clearInterval(mqrTimerInterval); mqrTimerInterval = null; }
    mqrSelectedAmount = 0;
    document.getElementById('mqrCustom').value  = '';
    document.getElementById('mqrCode').innerHTML = '';
    document.getElementById('mqrPicker').style.display  = '';
    document.getElementById('mqrDisplay').style.display = 'none';
    document.getElementById('mqrWaiting').style.display = '';
    document.getElementById('mqrPaid').style.display    = 'none';
    document.querySelectorAll('.mqr-pkg-btn').forEach(function(b) {
        b.style.borderColor = 'var(--border)';
        b.style.background  = 'var(--card)';
        b.querySelector('.mqr-check').style.display = 'none';
    });
}

// WebSocket hook — fired by layout.php when HC is received
window.onHcReceived = function(d) {
    var display = document.getElementById('mqrDisplay');
    if (!display || display.style.display === 'none') return; // not showing a QR right now
    if (mqrTimerInterval) { clearInterval(mqrTimerInterval); mqrTimerInterval = null; }
    document.getElementById('mqrWaiting').style.display  = 'none';
    document.getElementById('mqrTimer').textContent      = '';
    document.getElementById('mqrPaidAmt').textContent    = Number(d.amount).toLocaleString();
    document.getElementById('mqrPaidBal').textContent    = Number(d.new_balance).toLocaleString();
    document.getElementById('mqrPaid').style.display     = '';
    setTimeout(function() { location.href = <?= json_encode(m_base('')) ?>; }, 2500);
};
<?php endif; ?>
</script>
