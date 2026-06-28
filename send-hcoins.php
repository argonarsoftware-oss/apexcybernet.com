<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user($pdo);
$bal_stmt = $pdo->prepare("SELECT h_coins FROM accounts WHERE id = ?");
$bal_stmt->execute([$user['id']]);
$h_coins = (int)$bal_stmt->fetchColumn();

$pageTitle = 'Send H-Coins';
require_once __DIR__ . '/includes/header.php';
?>

<style>
.snd-page {
    min-height: calc(100vh - 70px);
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 0 0 3rem;
}

/* ── Hero header ── */
.snd-hero {
    width: 100%;
    background: linear-gradient(160deg, #1e0a4a 0%, #2d0f6b 50%, #1a0a3d 100%);
    border-bottom: 1px solid rgba(139,92,246,0.2);
    padding: 2.5rem 1.5rem 2rem;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.snd-hero::before {
    content: '';
    position: absolute;
    width: 300px; height: 300px;
    background: radial-gradient(circle, rgba(139,92,246,0.25) 0%, transparent 70%);
    top: -80px; left: 50%;
    transform: translateX(-50%);
    pointer-events: none;
}

.snd-hero-icon {
    width: 60px;
    height: 60px;
    border-radius: 18px;
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.15);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: #fff;
    margin-bottom: 1rem;
    backdrop-filter: blur(8px);
}

.snd-hero-title {
    font-size: 1.5rem;
    font-weight: 900;
    color: #fff;
    letter-spacing: -0.5px;
    margin-bottom: 0.75rem;
}

.snd-hero-balance {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: 99px;
    padding: 0.4rem 1rem;
    font-size: 0.88rem;
    font-weight: 700;
    color: rgba(255,255,255,0.85);
}

.snd-hero-balance img {
    width: 16px; height: 16px;
    object-fit: contain;
}

.snd-hero-balance strong {
    color: #fff;
}

/* ── Content wrapper ── */
.snd-content {
    width: 100%;
    max-width: 420px;
    padding: 1.75rem 1.25rem 0;
}

/* ── Method selector ── */
.snd-methods {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
    margin-bottom: 1.75rem;
}

.snd-method-btn {
    background: var(--bg-card);
    border: 1.5px solid var(--border);
    border-radius: 16px;
    padding: 1.1rem 0.75rem;
    cursor: pointer;
    font-family: inherit;
    text-align: center;
    transition: all 0.2s;
    color: var(--text-muted);
    -webkit-tap-highlight-color: transparent;
}

.snd-method-btn:hover {
    border-color: rgba(139,92,246,0.4);
    color: var(--text);
}

.snd-method-btn.active {
    background: rgba(124,58,237,0.12);
    border-color: var(--accent);
    color: var(--accent-light);
    box-shadow: 0 0 0 3px rgba(124,58,237,0.1);
}

.snd-method-icon {
    font-size: 1.6rem;
    margin-bottom: 0.4rem;
    display: block;
    line-height: 1;
}

.snd-method-label {
    font-size: 0.78rem;
    font-weight: 800;
    letter-spacing: 0.01em;
}

.snd-method-sub {
    font-size: 0.66rem;
    margin-top: 0.2rem;
    opacity: 0.6;
}

/* ── Panel ── */
.snd-panel {
    animation: panelIn 0.2s ease;
}

@keyframes panelIn {
    from { opacity: 0; transform: translateY(8px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ── Error message ── */
.snd-error {
    display: none;
    background: rgba(239,68,68,0.08);
    border: 1px solid rgba(239,68,68,0.25);
    border-radius: 12px;
    padding: 0.75rem 1rem;
    margin-bottom: 1rem;
    font-size: 0.82rem;
    color: #fca5a5;
    align-items: center;
    gap: 0.5rem;
}
.snd-error.show { display: flex; }

/* ── Username input ── */
.snd-field-wrap {
    position: relative;
    margin-bottom: 1rem;
}

.snd-field-label {
    font-size: 0.68rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: var(--text-muted);
    margin-bottom: 0.5rem;
    display: block;
}

.snd-input {
    width: 100%;
    background: var(--bg-card);
    border: 1.5px solid var(--border);
    border-radius: 14px;
    color: var(--text);
    padding: 0.9rem 1.1rem;
    font-size: 1rem;
    font-family: inherit;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.snd-input:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(124,58,237,0.12);
}

.snd-input::placeholder { color: #374151; }

.snd-lookup-btn {
    width: 100%;
    padding: 0.9rem;
    background: var(--accent);
    color: #fff;
    border: none;
    border-radius: 14px;
    font-size: 0.95rem;
    font-weight: 800;
    cursor: pointer;
    font-family: inherit;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    transition: background 0.2s, transform 0.1s;
    box-shadow: 0 4px 16px rgba(124,58,237,0.3);
}

.snd-lookup-btn:hover   { background: #6d28d9; }
.snd-lookup-btn:active  { transform: scale(0.98); }

/* ── Camera panel ── */
.snd-cam-wrap {
    position: relative;
    width: 100%;
    aspect-ratio: 1;
    background: #000;
    border-radius: 20px;
    overflow: hidden;
    margin-bottom: 1rem;
    box-shadow: 0 8px 32px rgba(0,0,0,0.5);
}

.snd-cam-wrap video {
    width: 100%; height: 100%;
    object-fit: cover;
}

.snd-scan-overlay {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
    background: radial-gradient(ellipse at center, transparent 35%, rgba(0,0,0,0.55) 70%);
}

.snd-scan-frame {
    width: 58%;
    height: 58%;
    position: relative;
}

/* Corner brackets */
.snd-scan-frame::before,
.snd-scan-frame::after,
.snd-scan-frame > span::before,
.snd-scan-frame > span::after {
    content: '';
    position: absolute;
    width: 22px; height: 22px;
    border-color: #fff;
    border-style: solid;
}
.snd-scan-frame::before      { top:0;    left:0;  border-width: 3px 0 0 3px; border-radius: 4px 0 0 0; }
.snd-scan-frame::after       { top:0;    right:0; border-width: 3px 3px 0 0; border-radius: 0 4px 0 0; }
.snd-scan-frame > span::before { bottom:0; left:0;  border-width: 0 0 3px 3px; border-radius: 0 0 0 4px; }
.snd-scan-frame > span::after  { bottom:0; right:0; border-width: 0 3px 3px 0; border-radius: 0 0 4px 0; }

.snd-scan-line {
    position: absolute;
    left: 0; right: 0;
    height: 2px;
    background: linear-gradient(90deg, transparent, var(--accent-light), transparent);
    animation: scanMove 2s ease-in-out infinite;
    top: 5%;
}
@keyframes scanMove { 0%,100%{top:5%} 50%{top:90%} }

.snd-cam-status {
    position: absolute;
    bottom: 0;
    left: 0; right: 0;
    padding: 1rem;
    text-align: center;
    font-size: 0.78rem;
    font-weight: 700;
    color: #fff;
    background: linear-gradient(transparent, rgba(0,0,0,0.6));
}

.snd-cam-hint {
    text-align: center;
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-bottom: 0.75rem;
    line-height: 1.5;
}

.snd-cancel-btn {
    width: 100%;
    padding: 0.75rem;
    background: transparent;
    border: 1.5px solid var(--border);
    border-radius: 12px;
    color: var(--text-muted);
    font-size: 0.85rem;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.2s;
}
.snd-cancel-btn:hover { border-color: #ef4444; color: #f87171; }

/* ── Back button ── */
.snd-back {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--text-muted);
    text-decoration: none;
    margin-top: 1.5rem;
    background: var(--bg-dark);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 0.55rem 1.25rem;
    transition: all 0.15s;
    font-family: inherit;
}
.snd-back:hover { color: var(--text); background: rgba(255,255,255,0.06); border-color: rgba(255,255,255,0.15); }

.hidden { display: none !important; }

/* Spinner */
.spinner {
    display: inline-block;
    width: 15px; height: 15px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>

<!-- jsQR -->
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>

<div class="snd-page">

    <!-- Hero -->
    <div class="snd-hero">
        <div class="snd-hero-icon"><i class="bi bi-send-fill"></i></div>
        <div class="snd-hero-title">Send H-Coins</div>
        <div class="snd-hero-balance">
            <img src="<?= base_url('images/hcoin-icon.png') ?>" alt="HC">
            Balance: <strong><?= number_format($h_coins) ?> HC</strong>
        </div>
    </div>

    <div class="snd-content">

        <!-- Method selector -->
        <div class="snd-methods">
            <button class="snd-method-btn active" id="btnUsername" onclick="switchTab('username')">
                <span class="snd-method-icon"><i class="bi bi-person-fill"></i></span>
                <div class="snd-method-label">By Username</div>
                <div class="snd-method-sub">Type their name</div>
            </button>
            <button class="snd-method-btn" id="btnScan" onclick="switchTab('scan')">
                <span class="snd-method-icon"><i class="bi bi-qr-code-scan"></i></span>
                <div class="snd-method-label">Scan QR</div>
                <div class="snd-method-sub">Point at their code</div>
            </button>
        </div>

        <!-- Error -->
        <div class="snd-error" id="sndError">
            <i class="bi bi-exclamation-circle-fill"></i>
            <span id="sndErrorText"></span>
        </div>

        <!-- Username panel -->
        <div id="panelUsername" class="snd-panel">
            <label class="snd-field-label" for="toUsername">Recipient username</label>
            <div class="snd-field-wrap">
                <input type="text" id="toUsername" class="snd-input"
                       placeholder="e.g. hillfront"
                       autocomplete="off" autocorrect="off" spellcheck="false">
            </div>
            <button class="snd-lookup-btn" id="lookupBtn" onclick="lookupUsername()">
                <i class="bi bi-search"></i> Find & Continue
            </button>
        </div>

        <!-- Scan panel -->
        <div id="panelScan" class="snd-panel hidden">
            <div class="snd-cam-wrap">
                <video id="camVideo" autoplay playsinline muted></video>
                <canvas id="camCanvas" style="display:none;"></canvas>
                <div class="snd-scan-overlay">
                    <div class="snd-scan-frame">
                        <span></span>
                        <div class="snd-scan-line"></div>
                    </div>
                </div>
                <div class="snd-cam-status" id="camStatus">Point at their QR Wallet or Receive QR</div>
            </div>
            <p class="snd-cam-hint">Ask the recipient to open <strong>Receive H-Coins</strong> and show their QR.</p>
            <button class="snd-cancel-btn" onclick="switchTab('username')">
                <i class="bi bi-x-circle"></i> Cancel
            </button>
        </div>

        <a href="<?= base_url('dashboard.php') ?>" class="snd-back">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>

    </div>
</div>

<script>
const API_LOOKUP  = <?= json_encode(base_url('api/qr-lookup.php')) ?>;
const CONFIRM_URL = <?= json_encode(base_url('send-confirm.php')) ?>;

let currentTab   = 'username';
let camStream    = null;
let scanInterval = null;

// ── Method switch ──
function switchTab(tab) {
    currentTab = tab;
    document.getElementById('btnUsername').classList.toggle('active', tab === 'username');
    document.getElementById('btnScan').classList.toggle('active', tab === 'scan');
    document.getElementById('panelUsername').classList.toggle('hidden', tab !== 'username');
    document.getElementById('panelScan').classList.toggle('hidden', tab !== 'scan');
    hideError();

    if (tab === 'scan') startCamera();
    else                stopCamera();
}

// ── Redirect to confirm ──
function goConfirm(name, token, ref) {
    stopCamera();
    const params = new URLSearchParams({ to: name });
    if (ref)   params.set('ref',   ref);
    if (token) params.set('token', token);
    window.location.href = CONFIRM_URL + '?' + params.toString();
}

// ── Username lookup ──
async function lookupUsername() {
    const name = document.getElementById('toUsername').value.trim();
    if (!name) { showError('Enter a username'); return; }

    const btn = document.getElementById('lookupBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Looking up...';

    try {
        const res  = await fetch(<?= json_encode(base_url('api/user-lookup.php')) ?>, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username: name }),
            credentials: 'include',
        });
        const data = await res.json();
        if (data.error) {
            showError(data.error);
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-search"></i> Find & Continue';
            return;
        }
        goConfirm(data.display_name, null, data.ref_code);
    } catch (e) {
        showError('Network error — try again');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-search"></i> Find & Continue';
    }
}

// ── Camera ──
async function startCamera() {
    try {
        camStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment', width: { ideal: 640 }, height: { ideal: 640 } }
        });
        const video = document.getElementById('camVideo');
        video.srcObject = camStream;
        video.play();
        video.addEventListener('loadeddata', startScanLoop, { once: true });
    } catch (e) {
        showError('Camera access denied — use username instead');
        switchTab('username');
    }
}

function stopCamera() {
    if (scanInterval) { clearInterval(scanInterval); scanInterval = null; }
    if (camStream)    { camStream.getTracks().forEach(t => t.stop()); camStream = null; }
}

function startScanLoop() {
    if (scanInterval) clearInterval(scanInterval);
    scanInterval = setInterval(scanFrame, 200);
}

function scanFrame() {
    const video  = document.getElementById('camVideo');
    const canvas = document.getElementById('camCanvas');
    if (!video || video.readyState !== video.HAVE_ENOUGH_DATA) return;

    canvas.width  = video.videoWidth;
    canvas.height = video.videoHeight;
    const ctx  = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0);
    const img  = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const code = jsQR(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' });

    if (code && code.data) {
        document.getElementById('camStatus').textContent = 'QR detected — verifying...';
        clearInterval(scanInterval); scanInterval = null;
        lookupToken(code.data);
    }
}

async function lookupToken(token) {
    try {
        const res  = await fetch(API_LOOKUP, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token }),
        });
        const data = await res.json();

        if (data.error) {
            showError(data.error);
            document.getElementById('camStatus').textContent = 'Point at their QR Wallet or Receive QR';
            startScanLoop();
            return;
        }

        goConfirm(data.display_name, token);
    } catch (e) {
        showError('Network error — try again');
        startScanLoop();
    }
}

// ── Helpers ──
function showError(msg) {
    document.getElementById('sndErrorText').textContent = msg;
    document.getElementById('sndError').classList.add('show');
}
function hideError() {
    document.getElementById('sndError').classList.remove('show');
}

document.getElementById('toUsername').addEventListener('keydown', e => {
    if (e.key === 'Enter') lookupUsername();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
