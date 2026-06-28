// Argonar Service Worker
const CACHE_NAME = 'argonar-v1';

// Static assets to pre-cache on install
const PRECACHE = [
    '/css/app.css',
    '/images/argonar-logo.svg',
    '/images/hcoin-icon.png',
    '/images/favicon.svg',
    '/icons/icon.php?size=192',
    '/icons/icon.php?size=512',
    '/offline.html',
];

// Pages to cache on first visit (network-first, cache fallback)
const PAGES = [
    '/bracket.php',
    '/dashboard.php',
    '/leaderboard.php',
    '/predict.php',
];

// ── Install: pre-cache static assets ──
self.addEventListener('install', e => {
    e.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(PRECACHE))
            .then(() => self.skipWaiting())
    );
});

// ── Activate: clear old caches ──
self.addEventListener('activate', e => {
    e.waitUntil(
        caches.keys().then(keys =>
            Promise.all(
                keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k))
            )
        ).then(() => self.clients.claim())
    );
});

// ── Fetch strategy ──
self.addEventListener('fetch', e => {
    const url = new URL(e.request.url);

    // Skip non-GET, cross-origin, and API calls
    if (e.request.method !== 'GET') return;
    if (url.origin !== location.origin)  return;
    if (url.pathname.startsWith('/api/')) return;

    // Static assets (css, images, fonts, icons) — cache-first
    if (
        url.pathname.startsWith('/css/') ||
        url.pathname.startsWith('/images/') ||
        url.pathname.startsWith('/icons/') ||
        url.pathname.match(/\.(png|jpg|jpeg|webp|svg|gif|ico|woff2?|ttf)$/)
    ) {
        e.respondWith(
            caches.match(e.request).then(cached => {
                if (cached) return cached;
                return fetch(e.request).then(res => {
                    const clone = res.clone();
                    caches.open(CACHE_NAME).then(c => c.put(e.request, clone));
                    return res;
                });
            })
        );
        return;
    }

    // PHP pages — network-first, fall back to cache, then offline page
    e.respondWith(
        fetch(e.request)
            .then(res => {
                // Cache successful HTML responses for offline fallback
                if (res.ok && res.headers.get('content-type')?.includes('text/html')) {
                    const clone = res.clone();
                    caches.open(CACHE_NAME).then(c => c.put(e.request, clone));
                }
                return res;
            })
            .catch(() =>
                caches.match(e.request).then(cached =>
                    cached || caches.match('/offline.html')
                )
            )
    );
});
