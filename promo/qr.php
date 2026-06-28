<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Apex Cybernet H-Coins</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;700;800;900&display=swap');

* { margin: 0; padding: 0; box-sizing: border-box; }

html, body {
    width: 100%;
    min-height: 100vh;
    font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
}

body {
    background: #07070f;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
}

/* ───── TARP CARD ───── */
.tarp {
    position: relative;
    width: 680px;
    overflow: hidden;
    border-radius: 40px;
    background: linear-gradient(160deg, #0e0b24 0%, #130e2e 40%, #0a0a1a 100%);
    box-shadow:
        0 0 0 1.5px rgba(139,92,246,0.3),
        0 0 120px rgba(124,58,237,0.25),
        0 40px 100px rgba(0,0,0,0.7);
    padding: 4rem 4rem 3.5rem;
    text-align: center;
    color: #fff;
}

/* Glow blobs */
.blob {
    position: absolute;
    border-radius: 50%;
    filter: blur(80px);
    pointer-events: none;
}
.blob-1 {
    width: 400px; height: 400px;
    background: radial-gradient(circle, rgba(124,58,237,0.35) 0%, transparent 70%);
    top: -120px; left: -80px;
}
.blob-2 {
    width: 300px; height: 300px;
    background: radial-gradient(circle, rgba(234,179,8,0.2) 0%, transparent 70%);
    bottom: -60px; right: -60px;
}
.blob-3 {
    width: 200px; height: 200px;
    background: radial-gradient(circle, rgba(34,211,238,0.15) 0%, transparent 70%);
    top: 50%; right: -40px;
}

/* ── Top brand row ── */
.brand {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    margin-bottom: 3rem;
    position: relative;
    z-index: 1;
}
.brand img {
    width: 28px;
    height: 28px;
    opacity: 0.7;
}
.brand-text {
    font-size: 0.85rem;
    font-weight: 700;
    letter-spacing: 0.25em;
    text-transform: uppercase;
    color: rgba(255,255,255,0.4);
}

/* ── Coin hero ── */
.coin-hero {
    position: relative;
    z-index: 1;
    margin-bottom: 2rem;
}

.coin-ring {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: linear-gradient(135deg, #fbbf24, #f59e0b, #d97706);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    box-shadow:
        0 0 0 8px rgba(251,191,36,0.1),
        0 0 0 16px rgba(251,191,36,0.05),
        0 0 60px rgba(251,191,36,0.4),
        0 8px 32px rgba(0,0,0,0.5);
}

.coin-ring img {
    width: 76px;
    height: 76px;
    object-fit: contain;
    filter: drop-shadow(0 4px 12px rgba(0,0,0,0.4));
}

/* ── Headline ── */
.headline {
    position: relative;
    z-index: 1;
    margin-bottom: 0.6rem;
}

.headline-top {
    font-size: 1rem;
    font-weight: 700;
    letter-spacing: 0.3em;
    text-transform: uppercase;
    color: #fbbf24;
    margin-bottom: 0.75rem;
}

.headline-main {
    font-size: 3.6rem;
    font-weight: 900;
    line-height: 1;
    letter-spacing: -2px;
    background: linear-gradient(135deg, #fff 0%, #e0d7ff 50%, #c4b5fd 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.headline-sub {
    font-size: 1.1rem;
    color: rgba(255,255,255,0.45);
    font-weight: 500;
    margin-top: 0.9rem;
    line-height: 1.6;
}

/* ── Divider ── */
.divider {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 1rem;
    margin: 2.25rem 0;
}
.divider-line {
    flex: 1;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(139,92,246,0.4), transparent);
}
.divider-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: rgba(139,92,246,0.6);
}

/* ── QR block ── */
.qr-section {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 2.5rem;
}

.qr-frame {
    position: relative;
    flex-shrink: 0;
}

/* Corner brackets */
.qr-frame::before,
.qr-frame::after {
    content: '';
    position: absolute;
    width: 24px;
    height: 24px;
    border-color: #7c3aed;
    border-style: solid;
    z-index: 2;
}
.qr-frame::before {
    top: -8px; left: -8px;
    border-width: 3px 0 0 3px;
    border-radius: 4px 0 0 0;
}
.qr-frame::after {
    bottom: -8px; right: -8px;
    border-width: 0 3px 3px 0;
    border-radius: 0 0 4px 0;
}

.qr-frame-inner {
    position: relative;
}
.qr-frame-inner::before,
.qr-frame-inner::after {
    content: '';
    position: absolute;
    width: 24px;
    height: 24px;
    border-color: #fbbf24;
    border-style: solid;
    z-index: 2;
}
.qr-frame-inner::before {
    top: -8px; right: -8px;
    border-width: 3px 3px 0 0;
    border-radius: 0 4px 0 0;
}
.qr-frame-inner::after {
    bottom: -8px; left: -8px;
    border-width: 0 0 3px 3px;
    border-radius: 0 0 0 4px;
}

.qr-bg {
    background: #ffffff;
    border-radius: 16px;
    padding: 14px;
    box-shadow:
        0 0 0 1px rgba(255,255,255,0.1),
        0 8px 40px rgba(0,0,0,0.5),
        0 0 60px rgba(251,191,36,0.08);
}

.qr-bg img {
    display: block;
    width: 200px;
    height: 200px;
    border-radius: 4px;
}

/* Steps beside QR */
.qr-steps {
    text-align: left;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.step {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
}

.step-num {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: rgba(124,58,237,0.2);
    border: 1px solid rgba(139,92,246,0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 800;
    color: #a78bfa;
    flex-shrink: 0;
}

.step-text {
    padding-top: 0.2rem;
}

.step-title {
    font-size: 0.9rem;
    font-weight: 800;
    color: #fff;
    line-height: 1.2;
}

.step-desc {
    font-size: 0.75rem;
    color: rgba(255,255,255,0.4);
    margin-top: 0.2rem;
}

/* ── Bottom bar ── */
.bottom {
    position: relative;
    z-index: 1;
    margin-top: 2.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.url-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(251,191,36,0.1);
    border: 1.5px solid rgba(251,191,36,0.35);
    border-radius: 99px;
    padding: 0.55rem 1.4rem;
    font-size: 1.05rem;
    font-weight: 800;
    color: #fbbf24;
    letter-spacing: 0.04em;
}

.url-badge .dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #fbbf24;
    box-shadow: 0 0 8px #fbbf24;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.4; }
}

.free-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    background: rgba(34,197,94,0.1);
    border: 1px solid rgba(34,197,94,0.3);
    border-radius: 99px;
    padding: 0.55rem 1.1rem;
    font-size: 0.82rem;
    font-weight: 800;
    color: #4ade80;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

/* Print button */
.print-btn {
    position: fixed;
    bottom: 1.5rem;
    right: 1.5rem;
    background: #7c3aed;
    color: #fff;
    border: none;
    border-radius: 12px;
    padding: 0.7rem 1.4rem;
    font-size: 0.9rem;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    box-shadow: 0 4px 20px rgba(124,58,237,0.5);
    transition: background 0.2s;
    z-index: 999;
}
.print-btn:hover { background: #6d28d9; }

/* ── Print ── */
@media print {
    body { background: #07070f; padding: 0; }
    .print-btn { display: none; }
    .tarp {
        border-radius: 0;
        width: 100%;
        box-shadow: none;
        min-height: 100vh;
    }
}
</style>
</head>
<body>

<div class="tarp">
    <!-- Glow blobs -->
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
    <div class="blob blob-3"></div>

    <!-- Brand -->
    <div class="brand">
        <img src="../images/apexcybernet-logo.svg" alt="Apex Cybernet">
        <span class="brand-text">Apex Cybernet</span>
    </div>

    <!-- Coin hero -->
    <div class="coin-hero">
        <div class="coin-ring">
            <img src="../images/hcoin-icon.png" alt="H-Coin">
        </div>
    </div>

    <!-- Headline -->
    <div class="headline">
        <div class="headline-top">Introducing</div>
        <div class="headline-main">H-Coins</div>
        <div class="headline-sub">
            Your digital coin. Earn it. Send it.<br>Pay anywhere with a QR scan.
        </div>
    </div>

    <!-- Divider -->
    <div class="divider">
        <div class="divider-line"></div>
        <div class="divider-dot"></div>
        <div class="divider-line"></div>
    </div>

    <!-- QR + Steps -->
    <div class="qr-section">
        <div class="qr-frame">
            <div class="qr-frame-inner">
                <div class="qr-bg">
                    <img src="qr-image.php?size=800" alt="Scan to join Apex Cybernet">
                </div>
            </div>
        </div>

        <div class="qr-steps">
            <div class="step">
                <div class="step-num">1</div>
                <div class="step-text">
                    <div class="step-title">Scan the QR</div>
                    <div class="step-desc">Open your phone camera</div>
                </div>
            </div>
            <div class="step">
                <div class="step-num">2</div>
                <div class="step-text">
                    <div class="step-title">Register Free</div>
                    <div class="step-desc">Takes under 30 seconds</div>
                </div>
            </div>
            <div class="step">
                <div class="step-num">3</div>
                <div class="step-text">
                    <div class="step-title">Get H-Coins</div>
                    <div class="step-desc">Start earning &amp; spending</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom -->
    <div class="bottom">
        <div class="url-badge">
            <span class="dot"></span>
            apexcybernet.com
        </div>
        <div class="free-badge">
            &#10003; 100% Free
        </div>
    </div>
</div>

<button class="print-btn" onclick="window.print()">
    &#128438; Print / Save PDF
</button>

</body>
</html>
