<!DOCTYPE html>
<html lang="en-PH">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= htmlspecialchars($pageTitle ?? 'Apex Cybernet Tournament — Dota 2 Esports Cebu') ?></title>

    <?php
    // ── SEO defaults ──
    $defaultTitle       = 'Apex Cybernet Tournament — Dota 2 Esports Cebu';
    $defaultDescription = 'Apex Cybernet Dota 2 Tournament — 5v5 double elimination, cash prize TBD. ₱500/team · ₱100/solo entry. May 30, 2026 at PGL Ibabao, Mandaue, Cebu.';
    $effectiveTitle     = $pageTitle ?? $defaultTitle;
    $metaDescription    = $pageDescription ?? $defaultDescription;
    $metaKeywords       = 'Apex Cybernet Tournament, Dota 2 Cebu, Cebu esports, Dota 2 Philippines, gaming tournament Cebu, PGL Ibabao, Mandaue Cebu, double elimination Dota, paid entry esports';
    $metaOgImage        = $ogImage ?? 'https://apexcybernet.com/og-image.php';
    $metaOgImageAlt     = $ogImageAlt ?? 'Apex Cybernet Dota 2 Tournament — Fight for Glory, cash prize TBD';

    // Canonical URL — strip query string AND honor proxied HTTPS
    $isHttps = (
        (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' && $_SERVER['HTTPS'] !== '')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443)
    );
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'apexcybernet.com';
    $reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    // Allow individual pages to override with $canonicalUrl (e.g. include a query param like ?game=dota2)
    $metaCanonical = $canonicalUrl ?? ($scheme . '://' . $host . $reqPath);
    ?>

    <!-- SEO Meta Tags -->
    <meta name="description" content="<?= htmlspecialchars($metaDescription) ?>">
    <meta name="keywords" content="<?= htmlspecialchars($metaKeywords) ?>">
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1">
    <meta name="googlebot" content="index, follow">
    <meta name="author" content="<?= !empty($hideCompany) ? 'Apex Cybernet' : 'Apex Cybernet' ?>">
    <meta name="publisher" content="<?= !empty($hideCompany) ? 'Apex Cybernet' : 'Apex Cybernet' ?>">
    <meta name="theme-color" content="#7c3aed">
    <meta name="application-name" content="Apex Cybernet Tournament">
    <meta name="apple-mobile-web-app-title" content="Apex Cybernet">
    <link rel="canonical" href="<?= htmlspecialchars($metaCanonical) ?>">
    <?php
    require_once __DIR__ . '/seo_config.php';
    $_seo_tracked = seo_is_tracked_page();
    if ($_seo_tracked && GSC_VERIFICATION !== ''):
    ?>
    <meta name="google-site-verification" content="<?= htmlspecialchars(GSC_VERIFICATION) ?>">
    <?php endif; ?>

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="<?= htmlspecialchars($ogType ?? 'website') ?>">
    <meta property="og:site_name" content="Apex Cybernet Tournament">
    <meta property="og:title" content="<?= htmlspecialchars($effectiveTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($metaDescription) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($metaOgImage) ?>">
    <meta property="og:image:secure_url" content="<?= htmlspecialchars($metaOgImage) ?>">
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="<?= htmlspecialchars($metaOgImageAlt) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($metaCanonical) ?>">
    <meta property="og:locale" content="en_PH">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($effectiveTitle) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($metaDescription) ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($metaOgImage) ?>">
    <meta name="twitter:image:alt" content="<?= htmlspecialchars($metaOgImageAlt) ?>">

    <!-- Favicons + PWA manifest -->
    <link rel="icon" type="image/svg+xml" href="<?= base_url('images/favicon.svg') ?>">
    <link rel="alternate icon" href="<?= base_url('images/favicon.svg') ?>">
    <link rel="apple-touch-icon" href="<?= base_url('images/favicon.svg') ?>">
    <link rel="mask-icon" href="<?= base_url('images/favicon.svg') ?>" color="#7c3aed">
    <link rel="manifest" href="<?= base_url('manifest.webmanifest') ?>">

    <!-- Performance hints -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="dns-prefetch" href="https://listener.apexcybernet.com">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= base_url('css/app.css') ?>">

    <!-- Sitewide Organization JSON-LD -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Organization",
        "name": "<?= !empty($hideCompany) ? 'Apex Cybernet' : 'Apex Cybernet' ?>",
        "alternateName": "Apex Cybernet",
        "url": "https://apexcybernet.com",
        "logo": "https://apexcybernet.com/images/apexcybernet-logo.svg",
        "sameAs": [
            "https://www.facebook.com/argonarsoftware"
        ],
        "address": {
            "@type": "PostalAddress",
            "addressLocality": "Cebu City",
            "addressRegion": "Cebu",
            "postalCode": "6000",
            "addressCountry": "PH"
        }
    }
    </script>

    <!-- Sitewide WebSite + SearchAction JSON-LD -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebSite",
        "name": "Apex Cybernet Tournament",
        "url": "https://apexcybernet.com"
    }
    </script>

    <?= $extraHead ?? '' ?>

    <?php if ($_seo_tracked && GA_MEASUREMENT_ID !== ''): ?>
    <!-- Google Analytics 4 -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars(GA_MEASUREMENT_ID) ?>"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', <?= json_encode(GA_MEASUREMENT_ID) ?>, {
            'anonymize_ip': true,
            'user_id': <?= intval($_SESSION['account_id'] ?? 0) ?: 'null' ?>,
            'page_title': <?= json_encode($effectiveTitle) ?>
        });
    </script>
    <?php endif; ?>

    <?php if ($_seo_tracked && META_PIXEL_ID !== ''): ?>
    <!-- Meta (Facebook) Pixel -->
    <script>
    !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
    n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
    document,'script','https://connect.facebook.net/en_US/fbevents.js');
    fbq('init', <?= json_encode(META_PIXEL_ID) ?>);
    fbq('track', 'PageView');
    </script>
    <noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=<?= htmlspecialchars(META_PIXEL_ID) ?>&ev=PageView&noscript=1"/></noscript>
    <?php endif; ?>

    <script>
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    }
    </script>

    <!-- Omniscient Tracker -->
    <script>
    (function(){
        var TRACK = <?= json_encode(base_url('api/track.php')) ?>;
        var UID   = <?= intval($_SESSION['account_id'] ?? 0) ?>;
        var SK = 'arg_sid';
        var sid = localStorage.getItem(SK);
        if (!sid) { try{sid=crypto.randomUUID();}catch(e){sid=Date.now().toString(36)+Math.random().toString(36).slice(2);} try{localStorage.setItem(SK,sid);}catch(e){} }

        function beam(events) {
            if (!events.length) return;
            try {
                var data = JSON.stringify({sid:sid, uid:UID, site:'apexcybernet', sw:screen.width, events:events});
                if (navigator.sendBeacon) navigator.sendBeacon(TRACK, new Blob([data],{type:'application/json'}));
                else { var x=new XMLHttpRequest(); x.open('POST',TRACK,true); x.setRequestHeader('Content-Type','application/json'); x.send(data); }
            } catch(e){}
        }

        // ── UTM from URL (persist in sessionStorage) ──
        var utms = {};
        try {
            var qs = new URLSearchParams(location.search);
            ['utm_source','utm_medium','utm_campaign'].forEach(function(k){ var v=qs.get(k); if(v) utms[k]=v; });
            if (Object.keys(utms).length) try{sessionStorage.setItem('arg_utm',JSON.stringify(utms));}catch(e){}
            else try{utms=JSON.parse(sessionStorage.getItem('arg_utm')||'{}');}catch(e){}
        } catch(e){}

        var batch=[], timer=null;
        function flush(){ if(!batch.length) return; beam(batch.splice(0)); }

        // ── Public hook: other scripts can push custom events via argTrack({...}) ──
        window.argTrack = function(ev) {
            if (!ev || !ev.t) return;
            batch.push(ev);
            clearTimeout(timer); timer=setTimeout(flush, 800);
        };

        // ── Pageview (sent after load for accurate load time) ──
        function sendPageview(lt) {
            var ev={t:'pageview',url:location.href,title:document.title.slice(0,200),ref:document.referrer.slice(0,300)};
            if(lt>0) ev.lt=lt;
            if(Object.keys(utms).length) ev.utm=utms;
            beam([ev]);
        }
        if(document.readyState==='complete') {
            sendPageview(performance&&performance.now?Math.round(performance.now()):0);
        } else {
            window.addEventListener('load',function(){ sendPageview(performance&&performance.now?Math.round(performance.now()):0); });
        }

        // ── Click tracking ──
        document.addEventListener('click',function(e){
            var el=e.target;
            for(var i=0;i<4;i++){
                if(!el||el===document.body) break;
                var tg=(el.tagName||'').toLowerCase();
                if(tg==='a'||tg==='button'||tg==='label'||(el.getAttribute&&el.getAttribute('role')==='button')) break;
                el=el.parentElement;
            }
            if(!el||el===document.body) el=e.target;
            var tg=(el.tagName||'').toLowerCase();
            var text=((el.textContent||el.value||'').trim().slice(0,80)).replace(/\s+/g,' ');
            var href=(el.href||(el.closest&&el.closest('a')&&el.closest('a').href)||'').slice(0,300);
            batch.push({t:'click',url:location.href,tag:tg,text:text,href:href,id:(el.id||'').slice(0,60)});
            clearTimeout(timer); timer=setTimeout(flush,1500);
        },true);

        // ── Scroll depth milestones (25/50/75/100%) ──
        var maxScroll=0, scrollMiles=[25,50,75,100], scrollHit={}, stTimer=null;
        function checkScroll(){
            var h=document.documentElement.scrollHeight-window.innerHeight;
            if(h<=0) return;
            var pct=Math.round((window.scrollY/h)*100);
            if(pct>maxScroll) maxScroll=pct;
            scrollMiles.forEach(function(m){
                if(!scrollHit[m]&&maxScroll>=m){
                    scrollHit[m]=true;
                    batch.push({t:'scroll',url:location.href,sd:m});
                    clearTimeout(timer); timer=setTimeout(flush,1500);
                }
            });
        }
        window.addEventListener('scroll',function(){ if(!stTimer){stTimer=setTimeout(function(){stTimer=null;checkScroll();},300);} },{passive:true});

        // ── Time on page ──
        var tabStart=Date.now(), accumulated=0;
        document.addEventListener('visibilitychange',function(){
            if(document.hidden){
                accumulated+=(Date.now()-tabStart)/1000;
                batch.push({t:'timeonpage',url:location.href,top:Math.round(accumulated)});
                flush();
            } else { tabStart=Date.now(); }
        });

        // ── JS errors ──
        window.addEventListener('error',function(e){
            // Skip cross-origin CDN errors (browser hides details, always "Script error.")
            if(!e.filename && e.message==='Script error.') return;
            batch.push({t:'error',url:location.href,text:((e.message||'').slice(0,80)+'  @'+(e.filename||'?').split('/').pop()+':'+(e.lineno||0)).slice(0,200)});
            clearTimeout(timer); timer=setTimeout(flush,2000);
        });

        window.addEventListener('pagehide',function(){
            if(!document.hidden) accumulated+=(Date.now()-tabStart)/1000;
            if(accumulated>2) batch.push({t:'timeonpage',url:location.href,top:Math.round(accumulated)});
            flush();
        });
        window.addEventListener('beforeunload',flush);
    })();
    </script>
</head>
<body>

<?php if (!empty($_SESSION['impersonating'])): ?>
<div style="background:linear-gradient(90deg,#dc2626,#b91c1c); color:#fff; text-align:center; padding:0.4rem 1rem; font-size:0.8rem; font-weight:700; position:relative; z-index:10000; display:flex; align-items:center; justify-content:center; gap:0.75rem;">
    <span><i class="bi bi-person-badge-fill"></i> Impersonating: <?= htmlspecialchars($_SESSION['impersonate_display'] ?? 'Unknown') ?></span>
    <a href="<?= base_url('admin/action.php?action=impersonate_stop') ?>" style="background:rgba(255,255,255,0.2); color:#fff; padding:0.2rem 0.6rem; border-radius:6px; text-decoration:none; font-size:0.72rem; border:1px solid rgba(255,255,255,0.3);">Stop Impersonating</a>
</div>
<?php endif; ?>

<nav class="navbar">
    <div class="container nav-container">
        <a class="navbar-brand" href="<?= base_url() ?>">
            <i class="bi bi-controller"></i> Apex Cybernet <span>Tournament</span>
        </a>
        <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation">
            <i class="bi bi-list"></i>
        </button>
        <div class="nav-links" id="navLinks">
            <a href="<?= base_url() ?>#games" class="nav-link"><i class="bi bi-joystick"></i> Games</a>
            <a href="<?= base_url('bracket.php') ?>" class="nav-link"><i class="bi bi-diagram-3-fill"></i> Bracket</a>
            <a href="<?= base_url('rules.php') ?>" class="nav-link"><i class="bi bi-file-text-fill"></i> Rules</a>
            <a href="<?= base_url('leaderboard.php') ?>" class="nav-link"><i class="bi bi-award-fill"></i> Hall of Fame</a>
            <a href="<?= base_url('dispute.php') ?>" class="nav-link"><i class="bi bi-exclamation-diamond-fill"></i> Disputes</a>
            <?php if (!empty($_SESSION['account_id'])): ?>
                <a href="<?= base_url('dashboard.php') ?>" class="nav-link"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <?php endif; ?>
            <?php if (!empty($_SESSION['account_id'])):
                if (!isset($__nav_user)) {
                    $__nav_u = $pdo->prepare("SELECT display_name, email FROM accounts WHERE id = ?");
                    $__nav_u->execute([$_SESSION['account_id']]);
                    $__nav_user = $__nav_u->fetch();
                }
                $__display = $__nav_user['display_name'] ?? $__nav_user['email'] ?? 'User';
                // Notification count
                $__notif_count = 0;
                try {
                    $__nc = $pdo->prepare("SELECT COUNT(*) FROM user_notifications WHERE account_id = ? AND is_read = 0");
                    $__nc->execute([$_SESSION['account_id']]);
                    $__notif_count = (int)$__nc->fetchColumn();
                } catch (Exception $e) {}
            ?>
                <!-- Notification bell -->
                <div class="notif-bell-wrap">
                    <button class="notif-bell-btn" id="notifBellBtn" aria-label="Notifications">
                        <i class="bi bi-bell-fill"></i>
                        <span class="notif-badge" id="notifBadge" style="<?= $__notif_count > 0 ? '' : 'display:none;' ?>"><?= $__notif_count > 99 ? '99+' : $__notif_count ?></span>
                    </button>
                    <div class="notif-dropdown" id="notifDropdown">
                        <div class="notif-dropdown-header">
                            <span>Notifications</span>
                            <button class="notif-mark-all" id="notifMarkAll" title="Mark all as read"><i class="bi bi-check2-all"></i></button>
                        </div>
                        <div class="notif-list" id="notifList">
                            <div class="notif-empty"><i class="bi bi-bell-slash"></i> No notifications</div>
                        </div>
                    </div>
                </div>

                <div class="user-pill-wrap">
                    <button class="user-pill" id="userPillBtn" aria-expanded="false">
                        <span class="user-pill-avatar"><i class="bi bi-person-fill"></i></span>
                        <span class="user-pill-name"><?= htmlspecialchars($__display) ?></span>
                        <i class="bi bi-chevron-down user-pill-arrow"></i>
                    </button>
                    <div class="user-dropdown" id="userDropdown">
                        <a href="<?= base_url('dashboard.php') ?>" class="user-dropdown-item">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                        <a href="<?= base_url('profile.php') ?>" class="user-dropdown-item">
                            <i class="bi bi-person"></i> Profile
                        </a>
                        <div class="user-dropdown-divider"></div>
                        <a href="<?= base_url('logout.php') ?>" class="user-dropdown-item user-dropdown-logout">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <a href="<?= base_url('login.php') ?>" class="nav-link" style="background:var(--accent); color:#fff; padding:0.3rem 0.9rem; border-radius:8px; font-weight:700;"><i class="bi bi-box-arrow-in-right"></i> Login</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
<script>
function toggleNavMore(e) {
    e.stopPropagation();
    document.getElementById('navMoreDrop').classList.toggle('on');
}
document.addEventListener('click', function(e) {
    var drop = document.getElementById('navMoreDrop');
    if (drop && !e.target.closest('.nav-more')) drop.classList.remove('on');
});
</script>
