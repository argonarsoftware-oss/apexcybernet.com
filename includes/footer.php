<footer class="site-footer">
    <div class="container">
        <div style="margin-bottom:0.75rem;">
            <a href="<?= base_url('rules.php') ?>" style="color:var(--accent-light); text-decoration:none; font-size:0.85rem; font-weight:600; margin:0 0.5rem;">Rules</a>
            <span style="color:var(--border);">|</span>
            <a href="<?= base_url('contact.php') ?>" style="color:var(--accent-light); text-decoration:none; font-size:0.85rem; font-weight:600; margin:0 0.5rem;">Contact</a>
            <span style="color:var(--border);">|</span>
            <a href="<?= base_url('terms.php') ?>" style="color:var(--accent-light); text-decoration:none; font-size:0.85rem; font-weight:600; margin:0 0.5rem;">Terms</a>
            <span style="color:var(--border);">|</span>
            <a href="<?= base_url('privacy.php') ?>" style="color:var(--accent-light); text-decoration:none; font-size:0.85rem; font-weight:600; margin:0 0.5rem;">Privacy</a>
        </div>
        &copy; <?= date('Y') ?>. All rights reserved.
    </div>
</footer>

<div class="mobile-sticky-bar" id="mobileStickyBar">
    <a href="#games" class="mobile-sticky-btn">
        <i class="bi bi-controller"></i> Register Now <?php if (isset($dota_slots_left) && $dota_slots_left > 0 && $dota_slots_left <= 8): ?>— <?= $dota_slots_left ?> slot<?= $dota_slots_left !== 1 ? 's' : '' ?> left<?php endif; ?>
    </a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php
$_ws_uid = (int)($_SESSION['account_id'] ?? 0);
$_ws_local = str_contains($_SERVER['HTTP_HOST'] ?? '', 'localhost');
if ($_ws_uid):
?>
<script>
// ── Central notification poller (replaces the WebSocket path) ──
// Polls api/notifications.php?action=list every 10s. For each new item since
// the last poll: prepends to bell, bumps badge, fires window 'apexcybernet:notification'
// so per-page listeners can react.
window.apexcybernetUid = <?= $_ws_uid ?>;
(function() {
    var lastSeenId  = 0;
    var initialized = false;
    var POLL_MS     = 10000;
    var API         = '<?= base_url("api/notifications.php?action=list") ?>';

    function poll() {
        if (document.hidden) return; // skip when tab is backgrounded
        fetch(API + '&limit=10' + (lastSeenId ? '&since=' + lastSeenId : ''))
            .then(function(r) { return r.json(); })
            .then(function(d) {
                var notifs = d.notifications || [];
                // Process in chronological order so events chain naturally
                notifs.slice().reverse().forEach(function(n) {
                    var id = parseInt(n.id);
                    if (id <= lastSeenId) return;

                    // Skip firing events on the first poll — just catch up to current state
                    if (initialized) {
                        // Fire custom event for page-level listeners
                        window.dispatchEvent(new CustomEvent('apexcybernet:notification', { detail: n }));

                        // Prepend to bell dropdown if present
                        var list = document.getElementById('notifList');
                        if (list) {
                            var emptyEl = list.querySelector('.notif-empty');
                            if (emptyEl) emptyEl.remove();
                            var link = n.link || '#';
                            var item = document.createElement('a');
                            item.className = 'notif-item unread';
                            item.href = link;
                            item.innerHTML =
                                '<div class="notif-item-icon"><i class="bi ' + (n.icon || 'bi-bell') + '"></i></div>' +
                                '<div class="notif-item-body">' +
                                    '<div class="notif-item-title"></div>' +
                                    '<div class="notif-item-msg"></div>' +
                                    '<div class="notif-item-time">just now</div>' +
                                '</div>';
                            item.querySelector('.notif-item-title').textContent = n.title || '';
                            item.querySelector('.notif-item-msg').textContent   = n.message || '';
                            list.insertBefore(item, list.firstChild);
                        }

                    }
                });

                if (notifs.length) {
                    var maxId = notifs.reduce(function(m, x) { return Math.max(m, parseInt(x.id)); }, lastSeenId);
                    lastSeenId = maxId;
                }
                initialized = true;

                // Keep bell badge in sync
                var badge = document.getElementById('notifBadge');
                if (badge) {
                    if (d.unread_count > 0) {
                        badge.textContent = d.unread_count > 99 ? '99+' : String(d.unread_count);
                        badge.style.display = '';
                    } else {
                        badge.style.display = 'none';
                    }
                }
            })
            .catch(function() { /* network blip — try again next tick */ });
    }

    // Prime lastSeenId from the already-rendered bell on page load so we only
    // trigger events for items arriving after this page load.
    (function primeLastSeenId() {
        var items = document.querySelectorAll('#notifList .notif-item[data-id]');
        items.forEach(function(el) {
            var id = parseInt(el.getAttribute('data-id'));
            if (id > lastSeenId) lastSeenId = id;
        });
    })();

    poll();
    setInterval(poll, POLL_MS);
})();
</script>
<?php endif; ?>
<?php if (!empty($extraJs)): ?>
    <?php foreach ($extraJs as $js): ?>
        <script src="<?= base_url("js/$js") ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>
<script>
// Countdown timer
(function() {
    var timer = document.querySelector('.countdown-timer');
    if (!timer) return;
    var target = new Date(timer.dataset.target + 'T00:00:00').getTime();
    function tick() {
        var now = Date.now();
        var diff = target - now;
        if (diff <= 0) {
            var heading = document.querySelector('.countdown-heading');
            if (heading) heading.textContent = 'Registration is closed!';
            document.getElementById('cdDays').textContent = '0';
            document.getElementById('cdHours').textContent = '00';
            document.getElementById('cdMins').textContent = '00';
            document.getElementById('cdSecs').textContent = '00';
            return;
        }
        var d = Math.floor(diff / 86400000);
        var h = Math.floor((diff % 86400000) / 3600000);
        var m = Math.floor((diff % 3600000) / 60000);
        var s = Math.floor((diff % 60000) / 1000);
        document.getElementById('cdDays').textContent = d;
        document.getElementById('cdHours').textContent = h < 10 ? '0' + h : h;
        document.getElementById('cdMins').textContent = m < 10 ? '0' + m : m;
        document.getElementById('cdSecs').textContent = s < 10 ? '0' + s : s;
    }
    tick();
    setInterval(tick, 1000);
})();

// Copy link
function copyLink(btn) {
    navigator.clipboard.writeText('https://apexcybernet.com').then(function() {
        var orig = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Copied!';
        setTimeout(function() { btn.innerHTML = orig; }, 2000);
    });
}

// Mobile sticky bar - show when scrolled past hero
(function() {
    var bar = document.getElementById('mobileStickyBar');
    var hero = document.querySelector('.hero');
    if (!bar || !hero) return;
    function check() {
        var bottom = hero.getBoundingClientRect().bottom;
        bar.classList.toggle('visible', bottom < 0);
    }
    window.addEventListener('scroll', check, { passive: true });
    check();
})();

// Lazy load video — only play when visible
(function() {
    var vid = document.getElementById('paraVideo');
    if (!vid) return;
    vid.pause();
    var observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) { vid.play(); }
            else { vid.pause(); }
        });
    }, { threshold: 0.3 });
    observer.observe(vid);
})();

// Video sound toggle
function toggleVideoSound(btn) {
    var vid = document.getElementById('paraVideo');
    if (!vid) return;
    vid.muted = !vid.muted;
    document.getElementById('paraVolumeIcon').className = vid.muted ? 'bi bi-volume-mute-fill' : 'bi bi-volume-up-fill';
}

// Nav toggle
(function() {
    var toggle = document.getElementById('navToggle');
    var links = document.getElementById('navLinks');
    if (!toggle || !links) return;
    toggle.addEventListener('click', function() {
        links.classList.toggle('open');
    });
})();

// Notification bell
(function() {
    var btn = document.getElementById('notifBellBtn');
    var dd = document.getElementById('notifDropdown');
    var list = document.getElementById('notifList');
    var markAll = document.getElementById('notifMarkAll');
    var badge = document.getElementById('notifBadge');
    if (!btn || !dd) return;

    var loaded = false;

    function timeAgo(dateStr) {
        var diff = (Date.now() - new Date(dateStr).getTime()) / 1000;
        if (diff < 60) return 'just now';
        if (diff < 3600) return Math.floor(diff/60) + 'm ago';
        if (diff < 86400) return Math.floor(diff/3600) + 'h ago';
        return Math.floor(diff/86400) + 'd ago';
    }

    function loadNotifs() {
        fetch('<?= base_url("api/notifications.php?action=list") ?>')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.notifications || !data.notifications.length) {
                    list.innerHTML = '<div class="notif-empty"><i class="bi bi-bell-slash"></i> No notifications</div>';
                    return;
                }
                list.innerHTML = data.notifications.map(function(n) {
                    var cls = 'notif-item' + (n.is_read == 0 ? ' unread' : '');
                    var link = n.link || '#';
                    return '<a class="' + cls + '" href="' + link + '" data-id="' + n.id + '">' +
                        '<div class="notif-item-icon"><i class="bi ' + (n.icon || 'bi-bell') + '"></i></div>' +
                        '<div class="notif-item-body">' +
                            '<div class="notif-item-title">' + n.title + '</div>' +
                            '<div class="notif-item-msg">' + n.message + '</div>' +
                            '<div class="notif-item-time">' + timeAgo(n.created_at) + '</div>' +
                        '</div></a>';
                }).join('');
                if (badge) {
                    if (data.unread_count > 0) {
                        badge.textContent = data.unread_count > 99 ? '99+' : data.unread_count;
                        badge.style.display = '';
                    } else {
                        badge.style.display = 'none';
                    }
                }
                loaded = true;
            }).catch(function() {});
    }

    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        var open = dd.classList.toggle('open');
        // Close user dropdown if open
        var ud = document.getElementById('userDropdown');
        if (ud) ud.classList.remove('open');
        if (open && !loaded) loadNotifs();
    });

    if (markAll) {
        markAll.addEventListener('click', function(e) {
            e.stopPropagation();
            fetch('<?= base_url("api/notifications.php") ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=read_all'
            }).then(function() {
                list.querySelectorAll('.notif-item.unread').forEach(function(el) { el.classList.remove('unread'); });
                if (badge) badge.style.display = 'none';
            });
        });
    }

    document.addEventListener('click', function(e) {
        if (!dd.contains(e.target) && !btn.contains(e.target)) {
            dd.classList.remove('open');
        }
    });

    // Realtime: prepend new notifications as they arrive
    // New notifications arrive via the central poller (below) which dispatches
    // 'apexcybernet:notification' — it handles prepending into #notifList and the
    // badge, so no per-subscription wiring is needed here.
})();

// User pill dropdown
(function() {
    var btn = document.getElementById('userPillBtn');
    var dd = document.getElementById('userDropdown');
    if (!btn || !dd) return;
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        var open = dd.classList.toggle('open');
        btn.setAttribute('aria-expanded', open);
        btn.querySelector('.user-pill-arrow').style.transform = open ? 'rotate(180deg)' : '';
        // Close notification dropdown if open
        var nd = document.getElementById('notifDropdown');
        if (nd) nd.classList.remove('open');
    });
    document.addEventListener('click', function(e) {
        if (!dd.contains(e.target) && !btn.contains(e.target)) {
            dd.classList.remove('open');
            btn.setAttribute('aria-expanded', 'false');
            btn.querySelector('.user-pill-arrow').style.transform = '';
        }
    });
})();
</script>
<?php if (empty($_SESSION['account_id'])): ?>
<!-- Guest Conversion Banner — only for unregistered visitors -->
<div id="guest-join-banner" style="display:none;">
    <div id="gjb-inner">
        <button id="gjb-close" onclick="gjbDismiss()" aria-label="Dismiss">&times;</button>
        <div id="gjb-icon">🏆</div>
        <div id="gjb-heading">Join Apex Cybernet Tournament</div>
        <div id="gjb-sub">Register to lock in your tournament slot. ₱550/team · ₱110/solo entry.</div>
        <div id="gjb-perks">
            <span>🎮 Tournament access</span>
            <span>🏆 Ranked matches</span>
            <span>📅 July 11 · Apex Cybernet Cafe</span>
        </div>
        <a href="<?= base_url('login.php') ?>?tab=register" id="gjb-cta">Create Free Account</a>
        <a href="<?= base_url('login.php') ?>" id="gjb-login">Already have an account? Log in</a>
    </div>
</div>
<style>
#guest-join-banner {
    position: fixed;
    bottom: 1.5rem;
    right: 1.5rem;
    z-index: 9999;
    animation: gjbSlideIn 0.35s cubic-bezier(0.34,1.56,0.64,1) forwards;
}
@keyframes gjbSlideIn {
    from { opacity: 0; transform: translateY(24px) scale(0.96); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
}
#gjb-inner {
    background: linear-gradient(145deg, #1a1a2e, #16213e);
    border: 1px solid rgba(124,58,237,0.4);
    border-radius: 18px;
    padding: 1.5rem 1.6rem 1.3rem;
    width: 280px;
    box-shadow: 0 16px 48px rgba(0,0,0,0.55), 0 0 0 1px rgba(124,58,237,0.15);
    position: relative;
    text-align: center;
}
#gjb-close {
    position: absolute;
    top: 0.7rem;
    right: 0.85rem;
    background: none;
    border: none;
    color: #6b7280;
    font-size: 1.2rem;
    cursor: pointer;
    line-height: 1;
    padding: 0;
}
#gjb-close:hover { color: #e5e7eb; }
#gjb-icon { font-size: 2rem; margin-bottom: 0.5rem; }
#gjb-heading {
    font-size: 1.05rem;
    font-weight: 900;
    color: #f9fafb;
    margin-bottom: 0.4rem;
    letter-spacing: -0.01em;
}
#gjb-sub {
    font-size: 0.75rem;
    color: #9ca3af;
    margin-bottom: 0.85rem;
    line-height: 1.5;
}
#gjb-perks {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    margin-bottom: 1rem;
}
#gjb-perks span {
    font-size: 0.72rem;
    color: #c4b5fd;
    background: rgba(124,58,237,0.1);
    border-radius: 6px;
    padding: 0.28rem 0.6rem;
    font-weight: 600;
}
#gjb-cta {
    display: block;
    background: linear-gradient(135deg, #7c3aed, #6d28d9);
    color: #fff;
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 800;
    padding: 0.65rem 1rem;
    border-radius: 10px;
    margin-bottom: 0.55rem;
    letter-spacing: 0.01em;
    transition: opacity 0.15s;
}
#gjb-cta:hover { opacity: 0.88; color: #fff; }
#gjb-login {
    display: block;
    font-size: 0.68rem;
    color: #6b7280;
    text-decoration: none;
}
#gjb-login:hover { color: #a78bfa; }
@media (max-width: 480px) {
    #guest-join-banner { bottom: 5rem; right: 0.75rem; }
    #gjb-inner { width: 260px; }
}
</style>
<script>
(function() {
    const DISMISS_KEY = 'gjb_dismissed';
    const SEEN_KEY    = 'gjb_seen_count';
    // Don't show again if dismissed in last 3 days
    const dismissedAt = parseInt(localStorage.getItem(DISMISS_KEY) || '0');
    if (Date.now() - dismissedAt < 3 * 24 * 60 * 60 * 1000) return;

    const seenCount = parseInt(localStorage.getItem(SEEN_KEY) || '0');
    // Don't show more than 3 times total
    if (seenCount >= 3) return;

    // Show after 30 seconds of browsing
    setTimeout(function() {
        const el = document.getElementById('guest-join-banner');
        if (el) {
            el.style.display = 'block';
            localStorage.setItem(SEEN_KEY, seenCount + 1);
        }
    }, 30000);
})();

function gjbDismiss() {
    const el = document.getElementById('guest-join-banner');
    if (el) {
        el.style.animation = 'none';
        el.style.opacity = '0';
        el.style.transform = 'translateY(16px)';
        el.style.transition = 'opacity 0.2s, transform 0.2s';
        setTimeout(() => el.remove(), 250);
    }
    localStorage.setItem('gjb_dismissed', Date.now());
}
</script>
<?php endif; ?>


</body>
</html>
