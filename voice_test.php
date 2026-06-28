<?php
/**
 * voice_test.php — temporary WebRTC diagnostic.
 * Tests in order: speaker output, microphone access, mic→speaker loopback,
 * STUN connectivity (own NAT type), TURN connectivity. DELETE AFTER USE.
 */
?>
<!doctype html>
<html><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Apex Cybernet — Voice Diagnostic</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
body { background:#0a0a0f; color:#e5e7eb; font-family:'Inter',system-ui,sans-serif; padding:1.25rem; max-width:680px; margin:0 auto; line-height:1.5; }
h1 { color:#a78bfa; font-size:1.4rem; margin-bottom:0.25rem; }
.sub { color:#9ca3af; font-size:0.82rem; margin-bottom:1.25rem; }
.card { background:#131318; border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:1rem 1.15rem; margin-bottom:0.85rem; }
.card h2 { font-size:0.78rem; text-transform:uppercase; letter-spacing:1.5px; color:#9ca3af; margin-bottom:0.6rem; font-weight:800; display:flex; align-items:center; gap:0.4rem; }
.card h2 i { color:#a78bfa; }
.row { display:flex; align-items:center; gap:0.6rem; margin:0.5rem 0; flex-wrap:wrap; }
button { background:#7c3aed; color:#fff; border:none; border-radius:8px; padding:0.55rem 1rem; font-size:0.85rem; font-weight:700; cursor:pointer; font-family:inherit; }
button:hover { background:#6d28d9; }
button:disabled { background:#3b3b48; cursor:not-allowed; }
button.danger { background:#dc2626; } button.danger:hover { background:#b91c1c; }
.status { font-size:0.85rem; padding:0.35rem 0.7rem; border-radius:6px; display:inline-flex; align-items:center; gap:0.35rem; font-weight:600; }
.s-ok   { background:rgba(34,197,94,0.12);  color:#34d399; }
.s-bad  { background:rgba(239,68,68,0.12);  color:#f87171; }
.s-warn { background:rgba(251,191,36,0.12); color:#fbbf24; }
.s-info { background:rgba(124,58,237,0.12); color:#a78bfa; }
.meter  { height:14px; background:rgba(255,255,255,0.06); border-radius:7px; overflow:hidden; flex:1; }
.meter > div { height:100%; background:linear-gradient(90deg,#22c55e,#fbbf24,#ef4444); width:0%; transition:width 0.05s linear; }
pre { background:#0a0a0f; padding:0.6rem 0.85rem; border-radius:6px; font-size:0.72rem; color:#d1d5db; max-height:200px; overflow-y:auto; border:1px solid rgba(255,255,255,0.05); margin:0.45rem 0; }
.note { font-size:0.74rem; color:#9ca3af; margin-top:0.4rem; }
audio { width:100%; margin-top:0.5rem; }
.kv { display:grid; grid-template-columns:140px 1fr; gap:0.3rem 0.85rem; font-size:0.78rem; }
.kv div:nth-child(odd) { color:#9ca3af; font-weight:700; }
</style>
</head><body>

<h1><i class="bi bi-mic-fill"></i> Voice Diagnostic</h1>
<div class="sub">If something doesn't pass, that's exactly where the call is failing. Open the browser console (F12) for verbose logs.</div>

<!-- ── Test 1: speaker output ── -->
<div class="card">
    <h2><i class="bi bi-speaker-fill"></i> 1 · Speaker output (can your browser play sound?)</h2>
    <div class="row">
        <button id="btnTone">Play test tone (440 Hz)</button>
        <span class="status s-info" id="toneStatus">not tested</span>
    </div>
    <div class="note">If you don't hear a beep: check browser tab is unmuted, OS volume up, headphones plugged in correctly, output device set right.</div>
</div>

<!-- ── Test 2: mic access ── -->
<div class="card">
    <h2><i class="bi bi-mic-fill"></i> 2 · Microphone access</h2>
    <div class="row">
        <button id="btnMic">Request microphone</button>
        <span class="status s-info" id="micStatus">not tested</span>
    </div>
    <div class="row">
        <span style="font-size:0.78rem;color:#9ca3af;width:60px;">Level:</span>
        <div class="meter"><div id="micMeter"></div></div>
        <span id="micLevel" style="font-size:0.74rem;color:#9ca3af;width:40px;text-align:right;">0</span>
    </div>
    <div class="note">Talk into your mic — the bar should move. If it doesn't, your mic isn't being captured (check OS privacy settings or try a different device).</div>
    <div id="micDevices" style="font-size:0.74rem;color:#9ca3af;margin-top:0.5rem;"></div>
</div>

<!-- ── Test 3: loopback ── -->
<div class="card">
    <h2><i class="bi bi-arrow-repeat"></i> 3 · Loopback (mic → speaker round trip)</h2>
    <div class="row">
        <button id="btnLoop" disabled>Start loopback (5 seconds)</button>
        <span class="status s-info" id="loopStatus">requires test 2</span>
    </div>
    <audio id="loopAudio" controls autoplay playsinline></audio>
    <div class="note">Records 5 seconds of your mic, then plays it back. If you hear yourself, mic+speaker work end-to-end. If not, autoplay or codec is the issue.</div>
</div>

<!-- ── Test 4: STUN/TURN ── -->
<div class="card">
    <h2><i class="bi bi-shield-fill-check"></i> 4 · STUN / TURN connectivity</h2>
    <div class="row">
        <button id="btnIce">Probe ICE servers</button>
        <span class="status s-info" id="iceStatus">not tested</span>
    </div>
    <pre id="iceLog">(click probe)</pre>
    <div class="note">Looking for at least one <code>srflx</code> (STUN) and one <code>relay</code> (TURN) candidate. Without TURN, mobile + corporate users can't connect.</div>
</div>

<!-- ── Test 5: signaling server reachable ── -->
<div class="card">
    <h2><i class="bi bi-broadcast"></i> 5 · Signaling server</h2>
    <div class="row">
        <button id="btnHealth">Ping signaling</button>
        <span class="status s-info" id="healthStatus">not tested</span>
    </div>
    <pre id="healthLog">(click ping)</pre>
</div>

<!-- ── Browser info ── -->
<div class="card">
    <h2><i class="bi bi-info-circle-fill"></i> Browser</h2>
    <div class="kv" id="browserInfo"></div>
</div>

<div style="text-align:center;color:#6b7280;font-size:0.7rem;margin-top:1rem;">
    ⚠️ Delete <code>voice_test.php</code> after diagnosis.
</div>

<script>
function setStatus(id, cls, text) {
    var el = document.getElementById(id);
    el.className = 'status ' + cls;
    el.textContent = text;
}

// ── Test 1: tone ──
document.getElementById('btnTone').onclick = function() {
    try {
        var ctx = new (window.AudioContext || window.webkitAudioContext)();
        var o = ctx.createOscillator(); var g = ctx.createGain();
        o.type = 'sine'; o.frequency.value = 440;
        g.gain.setValueAtTime(0.0001, ctx.currentTime);
        g.gain.exponentialRampToValueAtTime(0.15, ctx.currentTime + 0.05);
        g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.7);
        o.connect(g); g.connect(ctx.destination);
        o.start(); o.stop(ctx.currentTime + 0.7);
        setStatus('toneStatus', 's-ok', '✓ Beep sent. Did you hear it?');
    } catch (e) {
        setStatus('toneStatus', 's-bad', '✗ AudioContext failed: ' + e.message);
    }
};

// ── Test 2: mic ──
var micStream = null;
document.getElementById('btnMic').onclick = async function() {
    setStatus('micStatus', 's-info', 'requesting…');
    try {
        micStream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation:true, noiseSuppression:true, autoGainControl:true } });
        setStatus('micStatus', 's-ok', '✓ Mic granted (' + micStream.getAudioTracks().length + ' track)');
        document.getElementById('btnLoop').disabled = false;
        document.getElementById('loopStatus').textContent = 'ready';

        // Level meter
        var ctx = new (window.AudioContext || window.webkitAudioContext)();
        var src = ctx.createMediaStreamSource(micStream);
        var an = ctx.createAnalyser(); an.fftSize = 256;
        src.connect(an);
        var buf = new Uint8Array(an.frequencyBinCount);
        function loop() {
            an.getByteFrequencyData(buf);
            var sum = 0; for (var i=0;i<buf.length;i++) sum += buf[i];
            var avg = sum / buf.length;
            var pct = Math.min(100, Math.round(avg * 1.6));
            document.getElementById('micMeter').style.width = pct + '%';
            document.getElementById('micLevel').textContent = pct;
            requestAnimationFrame(loop);
        }
        loop();

        // List devices
        var devices = await navigator.mediaDevices.enumerateDevices();
        var lines = devices.filter(d => d.kind==='audioinput' || d.kind==='audiooutput').map(d => d.kind + ': ' + (d.label || '(unnamed)'));
        document.getElementById('micDevices').innerHTML = '<strong>Devices:</strong><br>' + lines.join('<br>');
    } catch (e) {
        setStatus('micStatus', 's-bad', '✗ ' + e.name + ': ' + e.message);
    }
};

// ── Test 3: loopback ──
document.getElementById('btnLoop').onclick = function() {
    if (!micStream) return;
    setStatus('loopStatus', 's-info', 'recording 5s…');
    var rec = new MediaRecorder(micStream);
    var chunks = [];
    rec.ondataavailable = e => { if (e.data.size > 0) chunks.push(e.data); };
    rec.onstop = function() {
        var blob = new Blob(chunks, { type: rec.mimeType || 'audio/webm' });
        var url = URL.createObjectURL(blob);
        var audio = document.getElementById('loopAudio');
        audio.src = url;
        var p = audio.play();
        if (p && p.catch) p.catch(err => { setStatus('loopStatus','s-warn','✗ play() blocked: ' + err.message); return; });
        setStatus('loopStatus', 's-ok', '✓ Recording done. Audio should be playing.');
    };
    rec.start();
    setTimeout(() => rec.stop(), 5000);
};

// ── Test 4: ICE probe ──
document.getElementById('btnIce').onclick = async function() {
    var log = document.getElementById('iceLog'); log.textContent = '';
    setStatus('iceStatus', 's-info', 'probing…');
    var pc = new RTCPeerConnection({
        iceServers: [
            { urls: ['stun:stun.l.google.com:19302', 'stun:stun1.l.google.com:19302'] },
            { urls: 'turn:openrelay.metered.ca:80',         username: 'openrelayproject', credential: 'openrelayproject' },
            { urls: 'turn:openrelay.metered.ca:443',        username: 'openrelayproject', credential: 'openrelayproject' },
            { urls: 'turn:openrelay.metered.ca:443?transport=tcp', username: 'openrelayproject', credential: 'openrelayproject' }
        ]
    });
    var foundHost=false, foundSrflx=false, foundRelay=false;
    pc.onicecandidate = e => {
        if (!e.candidate) {
            var summary = '\n— Summary —\n';
            summary += (foundHost  ? '✓' : '✗') + ' host (local network)\n';
            summary += (foundSrflx ? '✓' : '✗') + ' srflx (STUN — works for friendly NAT)\n';
            summary += (foundRelay ? '✓' : '✗') + ' relay (TURN — needed for strict NAT/mobile)\n';
            log.textContent += summary;
            if (foundRelay) setStatus('iceStatus','s-ok','✓ TURN reachable — voice should work cross-NAT');
            else if (foundSrflx) setStatus('iceStatus','s-warn','⚠ Only STUN — strict NATs (mobile, corp) may fail');
            else setStatus('iceStatus','s-bad','✗ No external connectivity — firewall is blocking');
            return;
        }
        var c = e.candidate.candidate;
        if (c.includes('typ host'))  foundHost  = true;
        if (c.includes('typ srflx')) foundSrflx = true;
        if (c.includes('typ relay')) foundRelay = true;
        log.textContent += c + '\n';
    };
    var dc = pc.createDataChannel('probe');
    var offer = await pc.createOffer();
    await pc.setLocalDescription(offer);
};

// ── Test 5: signaling ──
document.getElementById('btnHealth').onclick = async function() {
    setStatus('healthStatus','s-info','pinging…');
    try {
        var r = await fetch('/rtc/health');
        var t = await r.text();
        document.getElementById('healthLog').textContent = 'HTTP ' + r.status + '\n' + t;
        if (r.ok) setStatus('healthStatus','s-ok','✓ Signaling reachable');
        else      setStatus('healthStatus','s-bad','✗ HTTP ' + r.status);
    } catch (e) {
        document.getElementById('healthLog').textContent = e.message;
        setStatus('healthStatus','s-bad','✗ ' + e.message);
    }
};

// ── Browser info ──
var ua = navigator.userAgent;
var info = document.getElementById('browserInfo');
info.innerHTML = ''
    + '<div>User Agent</div><div style="font-family:monospace;font-size:0.7rem;word-break:break-all;">' + ua + '</div>'
    + '<div>Secure context</div><div>' + (window.isSecureContext ? '✓ HTTPS (WebRTC OK)' : '✗ NOT HTTPS — WebRTC blocked') + '</div>'
    + '<div>getUserMedia</div><div>' + (navigator.mediaDevices && navigator.mediaDevices.getUserMedia ? '✓ supported' : '✗ unsupported') + '</div>'
    + '<div>RTCPeerConnection</div><div>' + (window.RTCPeerConnection ? '✓ supported' : '✗ unsupported') + '</div>'
    + '<div>AudioContext</div><div>' + ((window.AudioContext || window.webkitAudioContext) ? '✓ supported' : '✗ unsupported') + '</div>';
</script>

</body></html>
