<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/layout.php';
m_require_login();

$user = current_user($pdo);
$uid  = (int)$user['id'];

$bal = $pdo->prepare("SELECT h_coins FROM accounts WHERE id = ?");
$bal->execute([$uid]);
$h_coins = (int)$bal->fetchColumn();

// Category filter
$cat = trim($_GET['cat'] ?? '');
$allowed_cats = ['general','gaming','services','digital','merch','other'];
if (!in_array($cat, $allowed_cats)) $cat = '';

// Browse: active listings
$where  = "l.status = 'active'";
$params = [];
if ($cat) { $where .= " AND l.category = ?"; $params[] = $cat; }

$listings_stmt = $pdo->prepare("
    SELECT l.*, a.display_name AS seller_name
    FROM marketplace_listings l
    JOIN accounts a ON a.id = l.seller_id
    WHERE $where
    ORDER BY l.created_at DESC
    LIMIT 60
");
$listings_stmt->execute($params);
$listings = $listings_stmt->fetchAll();

// My listings
$my_stmt = $pdo->prepare("
    SELECT l.*, a.display_name AS buyer_name
    FROM marketplace_listings l
    LEFT JOIN accounts a ON a.id = l.buyer_id
    WHERE l.seller_id = ?
    ORDER BY l.created_at DESC
    LIMIT 20
");
$my_stmt->execute([$uid]);
$my_listings = $my_stmt->fetchAll();

$categories = [
    'general'  => ['label' => 'General',  'icon' => 'bi-grid'],
    'gaming'   => ['label' => 'Gaming',   'icon' => 'bi-controller'],
    'services' => ['label' => 'Services', 'icon' => 'bi-tools'],
    'digital'  => ['label' => 'Digital',  'icon' => 'bi-file-earmark-code'],
    'merch'    => ['label' => 'Merch',    'icon' => 'bi-bag'],
    'other'    => ['label' => 'Other',    'icon' => 'bi-three-dots'],
];

m_head('Marketplace');
?>

<div class="m-top">
    <a href="./" class="m-back"><i class="bi bi-arrow-left"></i></a>
    <div class="m-top-title">Market</div>
    <div class="m-bal-pill">
        <img src="<?= m_main('images/hcoin-icon.png') ?>" alt="HC" onerror="this.style.display='none'">
        <span id="mkpBalance"><?= number_format($h_coins) ?></span> HC
    </div>
</div>

<!-- Tabs -->
<div class="m-tabs">
    <div class="m-tab on" data-tab="browse" onclick="switchTab('browse',this)">Browse</div>
    <div class="m-tab" data-tab="mine" onclick="switchTab('mine',this)">My Listings</div>
</div>

<!-- Browse tab -->
<div id="tab-browse">
    <!-- Category chips -->
    <div class="m-cats">
        <a href="?" class="m-cat <?= !$cat ? 'on' : '' ?>">
            <i class="bi bi-grid-fill"></i> All
        </a>
        <?php foreach ($categories as $key => $c): ?>
        <a href="?cat=<?= $key ?>" class="m-cat <?= $cat === $key ? 'on' : '' ?>">
            <i class="bi <?= $c['icon'] ?>"></i> <?= $c['label'] ?>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($listings)): ?>
    <div class="empty"><i class="bi bi-shop"></i><p>No listings<?= $cat ? ' in this category' : '' ?> yet.</p></div>
    <?php else: ?>
    <div class="m-grid">
        <?php foreach ($listings as $item):
            $is_own    = (int)$item['seller_id'] === $uid;
            $cat_label = $categories[$item['category']]['label'] ?? 'General';
            $cat_icon  = $categories[$item['category']]['icon']  ?? 'bi-grid';
            $img       = '';
            if (!empty($item['image_path'])) {
                $img = $item['image_path'];
            } elseif (!empty($item['gallery'])) {
                $g = json_decode($item['gallery'], true);
                if (is_array($g) && !empty($g[0])) $img = $g[0];
            }
        ?>
        <a class="m-prod" href="<?= m_main('product.php?id=' . (int)$item['id'] . '&full_once=1') ?>" id="m-card-<?= $item['id'] ?>">
            <?php if ($img): ?>
            <img class="m-prod-img" src="<?= m_main(htmlspecialchars(ltrim($img, '/'))) ?>" alt="<?= htmlspecialchars($item['title']) ?>" loading="lazy">
            <?php else: ?>
            <div class="m-prod-img m-prod-placeholder"><i class="bi <?= $cat_icon ?>"></i></div>
            <?php endif; ?>
            <div class="m-prod-cat"><i class="bi <?= $cat_icon ?>"></i> <?= htmlspecialchars($cat_label) ?></div>
            <div class="m-prod-body">
                <div class="m-prod-title"><?= htmlspecialchars($item['title']) ?></div>
                <div class="m-prod-seller">by <?= htmlspecialchars($item['seller_name'] ?: 'Unknown') ?></div>
                <div class="m-prod-foot">
                    <div class="m-prod-price">
                        <img src="<?= m_main('images/hcoin-icon.png') ?>" alt="HC" onerror="this.style.display='none'">
                        <?= number_format((int)$item['price']) ?>
                        <span class="m-prod-unit">HC</span>
                    </div>
                    <?php if ($is_own): ?>
                    <button class="m-prod-btn own" disabled>Yours</button>
                    <?php else: ?>
                    <button class="m-prod-btn" onclick="event.preventDefault(); event.stopPropagation(); buyItem(<?= (int)$item['id'] ?>, <?= (int)$item['price'] ?>, this);">Buy</button>
                    <?php endif; ?>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- My Listings tab -->
<div id="tab-mine" style="display:none;">
    <?php if (empty($my_listings)): ?>
    <div class="empty">
        <i class="bi bi-box-seam"></i>
        <p>You haven't listed anything.<br><span style="font-size:0.75rem;">Listings are managed by admins.</span></p>
    </div>
    <?php else: ?>
    <div style="padding:0 1rem;">
        <?php foreach ($my_listings as $item): ?>
        <div class="m-myrow">
            <div class="m-myrow-info">
                <div class="m-myrow-title"><?= htmlspecialchars($item['title']) ?></div>
                <div class="m-myrow-meta">
                    <?= number_format((int)$item['price']) ?> HC ·
                    <?= htmlspecialchars($categories[$item['category']]['label'] ?? 'General') ?> ·
                    <?= date('M j', strtotime($item['created_at'])) ?>
                    <?php if ($item['status'] === 'sold' && $item['buyer_name']): ?>
                    · Sold to <?= htmlspecialchars($item['buyer_name']) ?>
                    <?php endif; ?>
                </div>
            </div>
            <span class="chip <?= $item['status'] === 'active' ? 'chip-green' : ($item['status'] === 'sold' ? 'chip-purple' : 'chip-red') ?>">
                <?= ucfirst($item['status']) ?>
            </span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<style>
.m-bal-pill{display:inline-flex;align-items:center;gap:0.35rem;background:var(--card);border:1px solid var(--border);border-radius:99px;padding:0.35rem 0.75rem;font-size:0.78rem;font-weight:800;white-space:nowrap;}
.m-bal-pill img{width:14px;height:14px;object-fit:contain;}

.m-cats{display:flex;gap:0.4rem;padding:0 1rem 0.85rem;overflow-x:auto;scrollbar-width:none;}
.m-cats::-webkit-scrollbar{display:none;}
.m-cat{display:inline-flex;align-items:center;gap:0.3rem;padding:0.4rem 0.8rem;border-radius:99px;border:1px solid var(--border);background:var(--card);color:var(--muted);font-size:0.75rem;font-weight:700;white-space:nowrap;flex-shrink:0;}
.m-cat.on{background:var(--accent-dim);border-color:var(--accent);color:var(--accent-l);}

.m-grid{display:grid;grid-template-columns:1fr 1fr;gap:0.7rem;padding:0 1rem 1rem;}
.m-prod{background:var(--card);border:1px solid var(--border);border-radius:14px;overflow:hidden;display:flex;flex-direction:column;color:var(--text);}
.m-prod:active{opacity:0.85;}
.m-prod-img{width:100%;aspect-ratio:1;object-fit:cover;display:block;background:var(--surf);}
.m-prod-placeholder{display:flex;align-items:center;justify-content:center;font-size:2rem;color:rgba(255,255,255,0.08);}
.m-prod-cat{padding:0.4rem 0.7rem;background:var(--surf);border-bottom:1px solid var(--border);font-size:0.62rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted);display:flex;align-items:center;gap:0.3rem;}
.m-prod-body{padding:0.7rem;flex:1;display:flex;flex-direction:column;gap:0.35rem;}
.m-prod-title{font-size:0.82rem;font-weight:800;line-height:1.25;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
.m-prod-seller{font-size:0.65rem;color:var(--muted);}
.m-prod-foot{display:flex;align-items:center;justify-content:space-between;gap:0.4rem;margin-top:auto;padding-top:0.35rem;}
.m-prod-price{display:flex;align-items:center;gap:0.25rem;font-size:0.92rem;font-weight:900;color:#fbbf24;}
.m-prod-price img{width:13px;height:13px;object-fit:contain;}
.m-prod-unit{font-size:0.6rem;font-weight:700;color:var(--muted);}
.m-prod-btn{padding:0.35rem 0.7rem;background:var(--accent);color:#fff;border:none;border-radius:7px;font-size:0.7rem;font-weight:800;flex-shrink:0;}
.m-prod-btn.own{background:transparent;border:1px solid var(--border);color:var(--muted);}
.m-prod-btn.sold-out{background:rgba(255,255,255,0.05);color:var(--muted);}
.m-prod-btn:disabled{cursor:default;}

.m-myrow{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:0.8rem 1rem;display:flex;align-items:center;gap:0.75rem;margin-bottom:0.5rem;}
.m-myrow-info{flex:1;min-width:0;}
.m-myrow-title{font-size:0.88rem;font-weight:700;}
.m-myrow-meta{font-size:0.7rem;color:var(--muted);margin-top:0.2rem;}

@media (max-width: 340px){
    .m-grid{grid-template-columns:1fr;}
}
</style>

<?php m_nav('market'); m_toast(); m_foot(); ?>
<script>
function switchTab(name, el) {
    ['browse','mine'].forEach(function(t){
        document.getElementById('tab-'+t).style.display = t===name ? '' : 'none';
    });
    document.querySelectorAll('.m-tab').forEach(function(t){ t.classList.remove('on'); });
    el.classList.add('on');
}

async function buyItem(id, price, btn) {
    if (!confirm('Buy this item for ' + price.toLocaleString() + ' HC?')) return;
    btn.disabled = true;
    btn.textContent = '…';
    try {
        const res  = await fetch('<?= m_main('api/marketplace-buy.php') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ listing_id: id }),
            credentials: 'include',
        });
        const data = await res.json();
        if (data.error) {
            showToast(data.error, 'err');
            btn.disabled = false;
            btn.textContent = 'Buy';
            return;
        }
        document.getElementById('mkpBalance').textContent = Number(data.new_balance).toLocaleString();
        const card = document.getElementById('m-card-' + id);
        if (card) { card.style.opacity = '0.45'; card.style.pointerEvents = 'none'; }
        btn.className = 'm-prod-btn sold-out';
        btn.textContent = 'Sold';
        showToast('Purchased for ' + Number(data.price).toLocaleString() + ' HC', 'ok');
    } catch (e) {
        showToast('Network error', 'err');
        btn.disabled = false;
        btn.textContent = 'Buy';
    }
}

// ── Marketplace live feed (polling every 15s) ──
(function(){
    var POLL_MS = 15000;
    var lastTs  = Math.floor(Date.now() / 1000);
    var MY_UID  = <?= $uid ?>;
    var MAIN    = '<?= m_main('') ?>';
    var CAT_ICONS = {general:'bi-grid',gaming:'bi-controller',services:'bi-tools',digital:'bi-file-earmark-code',merch:'bi-bag',other:'bi-three-dots'};
    var CAT_LABELS= {general:'General',gaming:'Gaming',services:'Services',digital:'Digital',merch:'Merch',other:'Other'};

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function(m){
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m];
        });
    }

    function handleCreated(d) {
        if (document.getElementById('m-card-' + d.id)) return;
        var grid = document.querySelector('.m-grid');
        if (!grid) {
            var e = document.querySelector('#tab-browse .empty');
            if (e) location.reload();
            return;
        }
        var icon  = CAT_ICONS[d.category]  || 'bi-grid';
        var label = CAT_LABELS[d.category] || 'General';
        var isOwn = d.seller_id === MY_UID;
        var imgBlock = d.image_path
            ? '<img class="m-prod-img" src="' + MAIN + esc(String(d.image_path).replace(/^\//,'')) + '" alt="' + esc(d.title) + '" loading="lazy">'
            : '<div class="m-prod-img m-prod-placeholder"><i class="bi ' + icon + '"></i></div>';
        var buyBtn = isOwn
            ? '<button class="m-prod-btn own" disabled>Yours</button>'
            : '<button class="m-prod-btn" onclick="event.preventDefault();event.stopPropagation();buyItem(' + d.id + ',' + d.price + ',this);">Buy</button>';
        var a = document.createElement('a');
        a.className = 'm-prod';
        a.id = 'm-card-' + d.id;
        a.href = MAIN + 'product.php?id=' + d.id + '&full_once=1';
        a.innerHTML =
            imgBlock +
            '<div class="m-prod-cat"><i class="bi ' + icon + '"></i> ' + esc(label) + '</div>' +
            '<div class="m-prod-body">' +
            '  <div class="m-prod-title">' + esc(d.title) + '</div>' +
            '  <div class="m-prod-seller">by ' + esc(d.seller_name) + '</div>' +
            '  <div class="m-prod-foot">' +
            '    <div class="m-prod-price"><img src="' + MAIN + 'images/hcoin-icon.png" alt="HC" onerror="this.style.display=\'none\'">' +
                  Number(d.price).toLocaleString() +
            '      <span class="m-prod-unit">HC</span></div>' +
            '    ' + buyBtn +
            '  </div>' +
            '</div>';
        a.style.opacity = '0';
        a.style.transform = 'translateY(-8px)';
        a.style.transition = 'opacity 0.3s, transform 0.3s';
        grid.insertBefore(a, grid.firstChild);
        requestAnimationFrame(function(){ a.style.opacity='1'; a.style.transform='translateY(0)'; });
    }

    function handleSold(d) {
        var card = document.getElementById('m-card-' + d.id);
        if (!card) return;
        card.style.opacity = '0.45';
        card.style.pointerEvents = 'none';
        var btn = card.querySelector('.m-prod-btn');
        if (btn) { btn.className = 'm-prod-btn sold-out'; btn.textContent = 'Sold'; btn.disabled = true; }
    }

    function poll() {
        if (document.hidden) return;
        fetch(MAIN + 'api/marketplace-feed.php?since=' + lastTs, { credentials: 'include' })
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (typeof d.server_ts === 'number') lastTs = d.server_ts;
                (d.events || []).slice().reverse().forEach(function(ev){
                    if (ev.type === 'listing.created') handleCreated(ev);
                    else if (ev.type === 'listing.sold') handleSold(ev);
                });
            })
            .catch(function(){});
    }
    setInterval(poll, POLL_MS);
})();
</script>
