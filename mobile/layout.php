<?php
/**
 * mobile/layout.php — shared layout helpers for the /mobile/ section of apexcybernet.com
 */

function m_require_login(): void {
    if (empty($_SESSION['account_id'])) {
        header('Location: ./login.php');
        exit;
    }
}

function m_base(string $path = ''): string {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (str_contains($host, 'localhost')) {
        return '/apexcybernet.com/mobile/' . ltrim($path, '/');
    }
    return '/' . ltrim($path, '/');
}

// URL to the main (non-mobile) site. The mobile section lives under /mobile/ on
// apexcybernet.com; this helper produces fully qualified URLs back to the main site
// for assets, APIs, and pages that aren't duplicated under /mobile/.
function m_main(string $path = ''): string {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (str_contains($host, 'localhost')) {
        return '/apexcybernet.com/' . ltrim($path, '/');
    }
    return 'https://apexcybernet.com/' . ltrim($path, '/');
}

function m_head(string $title, string $extra = ''): void { ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover,maximum-scale=1,user-scalable=no">
<meta name="theme-color" content="#0a0a0f">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<title><?= htmlspecialchars($title) ?> — HCoin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<?= $extra ?>
<style>
:root {
    --bg:#0a0a0f; --surf:#131318; --card:#1a1a24;
    --border:rgba(255,255,255,0.08);
    --accent:#7c3aed; --accent-l:#a78bfa; --accent-dim:rgba(124,58,237,0.15);
    --green:#22c55e; --red:#f87171; --yellow:#f59e0b;
    --text:#e5e7eb; --muted:#6b7280;
    --nav-h:64px;
}
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent;}
html{-webkit-text-size-adjust:100%;}
body{background:var(--bg);color:var(--text);font-family:'Inter',system-ui,sans-serif;min-height:100vh;padding-bottom:calc(var(--nav-h) + env(safe-area-inset-bottom,0px));overflow-x:hidden;font-size:15px;}
a{text-decoration:none;color:inherit;}
input,textarea,select,button{font-family:inherit;}
button{cursor:pointer;}

/* Top bar */
.m-top{display:flex;align-items:center;padding:1rem 1.25rem 0.5rem;gap:0.75rem;}
.m-back{width:36px;height:36px;display:flex;align-items:center;justify-content:center;background:var(--card);border:1px solid var(--border);border-radius:10px;font-size:18px;flex-shrink:0;}
.m-top-title{font-size:1.1rem;font-weight:800;flex:1;}
.m-top-right{font-size:1.25rem;color:var(--accent-l);}

/* Bottom nav */
.m-nav{position:fixed;bottom:0;left:0;right:0;height:var(--nav-h);background:var(--surf);border-top:1px solid var(--border);display:flex;align-items:center;z-index:100;padding-bottom:env(safe-area-inset-bottom,0);}
.m-nav-item{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;color:var(--muted);font-size:10px;font-weight:600;}
.m-nav-item.on{color:var(--accent-l);}
.m-nav-item i{font-size:21px;line-height:1.2;}
.m-nav-scan{flex:1;display:flex;align-items:center;justify-content:center;}
.scan-pill{background:var(--accent);width:50px;height:50px;border-radius:16px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:22px;box-shadow:0 4px 20px rgba(124,58,237,0.45);}

/* Cards */
.card{background:var(--card);border:1px solid var(--border);border-radius:18px;margin:0 1rem 1rem;overflow:hidden;}
.card-body{padding:1.25rem;}
.card-title{font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted);margin-bottom:0.75rem;}

/* Balance */
.bal-hero{text-align:center;padding:2rem 1.5rem 1.25rem;}
.bal-label{font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:var(--muted);}
.bal-val{font-size:3.2rem;font-weight:900;color:#fff;line-height:1.1;margin-top:0.25rem;}
.bal-unit{font-size:1rem;font-weight:700;color:var(--accent-l);vertical-align:super;font-size:1.1rem;}

/* Quick actions */
.quick{display:grid;grid-template-columns:repeat(4,1fr);gap:0.6rem;padding:0 1rem 1rem;}
.quick-btn{display:flex;flex-direction:column;align-items:center;gap:5px;padding:0.85rem 0.3rem;background:var(--card);border:1px solid var(--border);border-radius:14px;font-size:10.5px;font-weight:700;color:var(--text);}
.quick-btn i{font-size:22px;color:var(--accent-l);}
.quick-btn:active{opacity:0.75;}

/* Txns */
.txn-item{display:flex;align-items:center;gap:0.85rem;padding:0.85rem 1.1rem;border-bottom:1px solid var(--border);}
.txn-item:last-child{border-bottom:none;}
.txn-ico{width:38px;height:38px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0;}
.ico-cr{background:rgba(34,197,94,0.12);color:var(--green);}
.ico-dr{background:rgba(248,113,113,0.12);color:var(--red);}
.txn-body{flex:1;min-width:0;}
.txn-lbl{font-size:0.84rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.txn-time{font-size:0.7rem;color:var(--muted);}
.txn-amt{font-size:0.88rem;font-weight:800;white-space:nowrap;}
.amt-cr{color:var(--green);}
.amt-dr{color:var(--red);}

/* Forms */
.m-form{padding:0 1rem;}
.m-field{margin-bottom:1rem;}
.m-lbl{display:block;font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--muted);margin-bottom:0.4rem;}
.m-inp{width:100%;background:var(--surf);border:1.5px solid var(--border);border-radius:12px;padding:0.85rem 1rem;font-size:1rem;color:var(--text);outline:none;}
.m-inp:focus{border-color:var(--accent);}
.m-inp-xl{font-size:1.8rem;font-weight:900;text-align:center;letter-spacing:-0.02em;}
.m-btn{display:block;width:100%;padding:0.95rem;border-radius:14px;font-size:0.95rem;font-weight:800;border:none;text-align:center;}
.m-btn-primary{background:var(--accent);color:#fff;}
.m-btn-primary:active{opacity:0.88;}
.m-btn-ghost{background:var(--card);border:1.5px solid var(--border);color:var(--text);}
.m-btn-red{background:rgba(248,113,113,0.12);border:1px solid rgba(248,113,113,0.3);color:var(--red);}
.m-gap{margin-bottom:0.75rem;}

/* Toast */
.toast{position:fixed;top:env(safe-area-inset-top,0);left:0.75rem;right:0.75rem;margin-top:0.75rem;padding:0.85rem 1rem;border-radius:14px;font-size:0.88rem;font-weight:700;z-index:9999;display:none;animation:slideDown 0.2s ease;}
.toast-ok{background:#14532d;border:1px solid #22c55e;color:#86efac;}
.toast-err{background:#450a0a;border:1px solid #f87171;color:#fca5a5;}
@keyframes slideDown{from{opacity:0;transform:translateY(-10px);}to{opacity:1;transform:translateY(0);}}

/* Tabs */
.m-tabs{display:flex;background:var(--surf);border-radius:12px;padding:3px;margin:0 1rem 1rem;}
.m-tab{flex:1;padding:0.55rem;text-align:center;font-size:0.82rem;font-weight:700;border-radius:9px;color:var(--muted);cursor:pointer;}
.m-tab.on{background:var(--card);color:var(--text);}

/* Chips */
.chip{display:inline-block;padding:0.18rem 0.6rem;border-radius:99px;font-size:0.68rem;font-weight:700;}
.chip-purple{background:var(--accent-dim);color:var(--accent-l);}
.chip-green{background:rgba(34,197,94,0.12);color:var(--green);}
.chip-red{background:rgba(248,113,113,0.12);color:var(--red);}

/* Listings */
.listing{padding:1rem 1.1rem;border-bottom:1px solid var(--border);}
.listing:last-child{border-bottom:none;}
.listing-top{display:flex;align-items:baseline;justify-content:space-between;gap:0.5rem;}
.listing-hc{font-size:1.1rem;font-weight:900;}
.listing-php{font-size:0.88rem;color:var(--accent-l);font-weight:700;}
.listing-meta{font-size:0.73rem;color:var(--muted);margin-top:3px;}
.listing-contact{font-size:0.8rem;color:var(--text);margin-top:0.5rem;background:var(--surf);border-radius:8px;padding:7px 10px;word-break:break-all;}

/* Empty state */
.empty{text-align:center;padding:3rem 1.5rem;color:var(--muted);}
.empty i{font-size:2.5rem;display:block;margin-bottom:0.75rem;}
.empty p{font-size:0.88rem;}

/* QR */
.qr-box{background:#fff;border-radius:20px;padding:1rem;display:inline-flex;}
</style>
<?php } // end m_head

function m_nav(string $active = 'home'): void {
    $b = fn($p) => m_base($p);
    ?>
<nav class="m-nav">
    <a href="<?= $b('') ?>" class="m-nav-item <?= $active==='home'?'on':'' ?>">
        <i class="bi bi-house<?= $active==='home'?'-fill':'' ?>"></i>
        <span>Home</span>
    </a>
    <a href="<?= $b('send.php') ?>" class="m-nav-item <?= $active==='send'?'on':'' ?>">
        <i class="bi bi-arrow-up-circle<?= $active==='send'?'-fill':'' ?>"></i>
        <span>Send</span>
    </a>
    <div class="m-nav-scan">
        <a href="<?= $b('send.php') ?>?mode=scan" class="scan-pill" title="Scan to Pay">
            <i class="bi bi-qr-code-scan"></i>
        </a>
    </div>
    <a href="<?= $b('receive.php') ?>" class="m-nav-item <?= $active==='receive'?'on':'' ?>">
        <i class="bi bi-arrow-down-circle<?= $active==='receive'?'-fill':'' ?>"></i>
        <span>Receive</span>
    </a>
    <a href="<?= $b('market.php') ?>" class="m-nav-item <?= $active==='market'?'on':'' ?>">
        <i class="bi bi-shop<?= $active==='market'?'-fill':'' ?>"></i>
        <span>Market</span>
    </a>
</nav>
<?php } // end m_nav

function m_toast(): void { ?>
<div class="toast toast-ok"  id="toast-ok"></div>
<div class="toast toast-err" id="toast-err"></div>
<script>
function showToast(msg, type) {
    var id = type === 'ok' ? 'toast-ok' : 'toast-err';
    var el = document.getElementById(id);
    el.textContent = msg;
    el.style.display = 'block';
    setTimeout(function(){ el.style.display='none'; }, 3200);
}
</script>
<?php } // end m_toast

function m_foot(string $js = ''): void {
    $uid = (int)($_SESSION['account_id'] ?? 0);
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $is_local = str_contains($host, 'localhost');
    ?>

<!-- HC received receipt overlay (shown by WebSocket push) -->
<div id="hcReceiptOverlay" style="display:none;position:fixed;inset:0;z-index:9990;background:rgba(0,0,0,0.7);backdrop-filter:blur(4px);align-items:flex-end;justify-content:center;">
    <div id="hcReceiptSheet" style="background:var(--card);border-top:1px solid var(--border);border-radius:24px 24px 0 0;padding:2rem 1.5rem 2.5rem;width:100%;max-width:480px;text-align:center;transform:translateY(100%);transition:transform 0.35s cubic-bezier(0.25,0.46,0.45,0.94);">
        <div style="width:56px;height:56px;border-radius:50%;background:rgba(34,197,94,0.15);border:2px solid var(--green);display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;font-size:1.6rem;color:var(--green);">
            <i class="bi bi-check-lg"></i>
        </div>
        <div style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:var(--muted);margin-bottom:0.25rem;">HC Received!</div>
        <div style="font-size:2.8rem;font-weight:900;color:var(--green);letter-spacing:-1px;line-height:1;">+<span id="hcRcptAmount"></span></div>
        <div style="font-size:0.82rem;color:var(--muted);margin-bottom:1.25rem;">HCoins added to your wallet</div>
        <div style="background:var(--bg);border:1px solid var(--border);border-radius:14px;padding:0.9rem 1rem;text-align:left;margin-bottom:1.25rem;">
            <div style="display:flex;justify-content:space-between;font-size:0.8rem;padding:0.3rem 0;border-bottom:1px solid var(--border);">
                <span style="color:var(--muted);">From</span>
                <span style="font-weight:700;" id="hcRcptFrom"></span>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:0.8rem;padding:0.3rem 0;border-bottom:1px solid var(--border);">
                <span style="color:var(--muted);">New Balance</span>
                <span style="font-weight:800;color:var(--accent-l);" id="hcRcptBal"></span>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:0.8rem;padding:0.3rem 0;">
                <span style="color:var(--muted);">Time</span>
                <span style="font-weight:600;" id="hcRcptTime"></span>
            </div>
        </div>
        <button onclick="document.getElementById('hcReceiptOverlay').style.display='none';"
                style="display:block;width:100%;background:var(--accent);color:#fff;border:none;border-radius:12px;padding:0.8rem;font-size:0.9rem;font-weight:800;cursor:pointer;font-family:inherit;">
            <i class="bi bi-check2"></i> Got it
        </button>
    </div>
</div>

<?php if ($uid): ?>
<script>
window.apexcybernetUid = <?= $uid ?>;
// ── Central notification poller (replaces the WebSocket path) ──
// Polls the shared api/notifications.php every 10s. Fires window
// 'apexcybernet:notification' for page-level listeners; shows the HC receipt
// overlay and/or bumps the bell badge depending on what's on the page.
(function() {
    var lastSeenId  = 0;
    var initialized = false;
    var POLL_MS     = 10000;
    var API         = 'https://apexcybernet.com/api/notifications.php?action=list';

    function parseAmount(title) {
        var m = String(title || '').match(/\+([\d,]+)/);
        return m ? parseInt(m[1].replace(/,/g,''), 10) : 0;
    }

    function poll() {
        if (document.hidden) return;
        fetch(API + '&limit=10' + (lastSeenId ? '&since=' + lastSeenId : ''), { credentials: 'include' })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                var notifs = d.notifications || [];
                notifs.slice().reverse().forEach(function(n) {
                    var id = parseInt(n.id);
                    if (id <= lastSeenId) return;

                    // Skip firing events on the first poll — just catch up to current state
                    if (initialized) {
                        var isHcReceived = (n.icon || '').indexOf('coin') !== -1;
                        var payload = {
                            id: id, title: n.title, message: n.message, icon: n.icon, link: n.link,
                            amount: parseAmount(n.title),
                            from: String(n.message || '').replace(/^From\s+/i, ''),
                            time: n.created_at
                        };
                        window.dispatchEvent(new CustomEvent('apexcybernet:notification', { detail: payload }));

                        if (isHcReceived) {
                            if (typeof window.onHcReceived === 'function') window.onHcReceived(payload);
                            if (typeof showHcReceipt === 'function') showHcReceipt(payload);
                        }
                    }
                });

                if (notifs.length) {
                    lastSeenId = notifs.reduce(function(m, x) { return Math.max(m, parseInt(x.id)); }, lastSeenId);
                }
                initialized = true;

                // Mobile bell badge (mobile/index.php renders #mBellBadge)
                var mb = document.getElementById('mBellBadge');
                if (mb) {
                    if (d.unread_count > 0) {
                        mb.textContent = d.unread_count > 99 ? '99+' : String(d.unread_count);
                        mb.style.display = 'flex';
                    } else {
                        mb.style.display = 'none';
                    }
                }
            })
            .catch(function() {});
    }

    poll();
    setInterval(poll, POLL_MS);
})();

function showHcReceipt(d) {
    document.getElementById('hcRcptAmount').textContent = Number(d.amount || 0).toLocaleString();
    document.getElementById('hcRcptFrom').textContent   = d.from || 'Apex Cybernet';
    var balEl = document.getElementById('hcRcptBal');
    if (balEl) {
        balEl.textContent = (d.new_balance != null) ? (Number(d.new_balance).toLocaleString() + ' HC') : '—';
    }
    var t = d.time ? new Date(String(d.time).replace(' ', 'T')) : new Date();
    document.getElementById('hcRcptTime').textContent   = t.toLocaleString([], {month:'short',day:'numeric',hour:'2-digit',minute:'2-digit'});
    var overlay = document.getElementById('hcReceiptOverlay');
    var sheet   = document.getElementById('hcReceiptSheet');
    overlay.style.display = 'flex';
    setTimeout(function() { sheet.style.transform = 'translateY(0)'; }, 20);
}
</script>
<?php endif; ?>

<?= $js ?>
</body>
</html>
<?php } // end m_foot
