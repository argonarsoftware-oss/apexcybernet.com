<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$user = current_user($pdo);
$logged_in = !empty($user);
$is_admin = !empty($_SESSION['admin_logged_in']);

$uid     = $logged_in ? (int)$user['id'] : 0;
$h_coins = 0;

if ($logged_in) {
    $bal = $pdo->prepare("SELECT h_coins FROM accounts WHERE id = ?");
    $bal->execute([$uid]);
    $h_coins = (int)$bal->fetchColumn();
}

// Filter
$cat = trim($_GET['cat'] ?? '');
$allowed_cats = ['general','gaming','services','digital','merch','other'];
if (!in_array($cat, $allowed_cats)) $cat = '';

// Listings
$where = "l.status = 'active'";
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

// My listings (only for logged-in users)
$my_listings = [];
if ($logged_in) {
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
}

$categories = [
    'general'  => ['label' => 'General',  'icon' => 'bi-grid'],
    'gaming'   => ['label' => 'Gaming',   'icon' => 'bi-controller'],
    'services' => ['label' => 'Services', 'icon' => 'bi-tools'],
    'digital'  => ['label' => 'Digital',  'icon' => 'bi-file-earmark-code'],
    'merch'    => ['label' => 'Merch',    'icon' => 'bi-bag'],
    'other'    => ['label' => 'Other',    'icon' => 'bi-three-dots'],
];

$pageTitle = 'H-Coin Marketplace';
require_once __DIR__ . '/includes/header.php';
?>
<style>
/* ── Page shell ── */
.mkp-page {
    max-width: 1100px;
    margin: 0 auto;
    padding: 2rem 1.25rem 4rem;
}

/* ── Hero ── */
.mkp-hero {
    background: linear-gradient(135deg, #1a0a3d 0%, #2d1065 50%, #0f0f13 100%);
    border: 1px solid rgba(139,92,246,0.25);
    border-radius: 20px;
    padding: 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1.75rem;
    position: relative;
    overflow: hidden;
}

.mkp-hero::before {
    content: '';
    position: absolute;
    width: 300px; height: 300px;
    background: radial-gradient(circle, rgba(139,92,246,0.2) 0%, transparent 70%);
    right: -60px; top: -80px;
    pointer-events: none;
}

.mkp-hero-left h1 {
    font-size: 1.5rem;
    font-weight: 900;
    color: #fff;
    letter-spacing: -0.5px;
    margin-bottom: 0.35rem;
}

.mkp-hero-left p {
    font-size: 0.82rem;
    color: rgba(255,255,255,0.5);
}

.mkp-balance-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: 99px;
    padding: 0.5rem 1.1rem;
    font-size: 0.9rem;
    font-weight: 800;
    color: #fff;
    white-space: nowrap;
    flex-shrink: 0;
}

.mkp-balance-pill img { width: 18px; height: 18px; object-fit: contain; }

/* ── Top bar: sell button + tabs ── */
.mkp-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1.25rem;
    flex-wrap: wrap;
}

.mkp-tabs {
    display: flex;
    gap: 4px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 3px;
}

.mkp-tab {
    padding: 0.45rem 1rem;
    border-radius: 7px;
    border: none;
    background: transparent;
    color: var(--text-muted);
    font-size: 0.82rem;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.15s;
}

.mkp-tab.active {
    background: var(--accent);
    color: #fff;
    box-shadow: 0 2px 8px rgba(124,58,237,0.4);
}

.mkp-sell-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    background: var(--accent);
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 0.6rem 1.2rem;
    font-size: 0.85rem;
    font-weight: 800;
    cursor: pointer;
    font-family: inherit;
    transition: background 0.2s;
    box-shadow: 0 4px 14px rgba(124,58,237,0.3);
}

.mkp-sell-btn:hover { background: #6d28d9; }

/* ── Category filter ── */
.mkp-cats {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    overflow-x: auto;
    padding-bottom: 4px;
    scrollbar-width: none;
}
.mkp-cats::-webkit-scrollbar { display: none; }

.mkp-cat-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 0.85rem;
    border-radius: 99px;
    border: 1px solid var(--border);
    background: var(--bg-card);
    color: var(--text-muted);
    font-size: 0.78rem;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
    white-space: nowrap;
    text-decoration: none;
    transition: all 0.15s;
}

.mkp-cat-btn:hover {
    border-color: var(--accent);
    color: var(--accent-light);
}

.mkp-cat-btn.active {
    background: rgba(124,58,237,0.15);
    border-color: var(--accent);
    color: var(--accent-light);
}

/* ── Listings grid ── */
.mkp-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 1rem;
}

.mkp-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    transition: border-color 0.2s, transform 0.2s;
}

.mkp-card:hover {
    border-color: rgba(139,92,246,0.4);
    transform: translateY(-2px);
}

/* ── Card image / gallery ── */
.mkp-card-img {
    width: 100%;
    height: 160px;
    object-fit: cover;
    display: block;
}
.mkp-card-gallery {
    position: relative;
    border-bottom: 1px solid var(--border);
    overflow: hidden;
}
.mkp-card-gallery .mkp-card-img {
    display: none;
}
.mkp-card-gallery .mkp-card-img.active {
    display: block;
}
.gallery-arrow {
    position: absolute; top: 50%; transform: translateY(-50%);
    background: rgba(0,0,0,0.55); border: none; color: #fff;
    width: 26px; height: 26px; border-radius: 50%; font-size: 0.85rem;
    cursor: pointer; display: none; align-items: center; justify-content: center;
    z-index: 2; transition: background 0.15s;
}
.gallery-arrow:hover { background: rgba(124,58,237,0.75); }
.gallery-arrow.prev { left: 6px; }
.gallery-arrow.next { right: 6px; }
.mkp-card-gallery:hover .gallery-arrow { display: flex; }
.gallery-dots {
    position: absolute; bottom: 5px; left: 50%; transform: translateX(-50%);
    display: flex; gap: 4px;
}
.gallery-dot {
    width: 5px; height: 5px; border-radius: 50%;
    background: rgba(255,255,255,0.35); cursor: pointer; border: none; padding: 0;
    transition: background 0.15s;
}
.gallery-dot.active { background: #fff; }

.mkp-card-img-placeholder {
    width: 100%;
    height: 120px;
    background: var(--bg-dark);
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: rgba(255,255,255,0.07);
}

.mkp-card-cat {
    padding: 0.6rem 0.9rem;
    background: var(--bg-dark);
    border-bottom: 1px solid var(--border);
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.mkp-card-body {
    padding: 1rem;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.mkp-card-title {
    font-size: 0.95rem;
    font-weight: 800;
    color: var(--text);
    margin-bottom: 0.4rem;
    line-height: 1.3;
}

.mkp-card-desc {
    font-size: 0.78rem;
    color: var(--text-muted);
    line-height: 1.5;
    flex: 1;
    margin-bottom: 0.85rem;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.mkp-card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.mkp-price {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 1.1rem;
    font-weight: 900;
    color: #fbbf24;
}

.mkp-price img { width: 16px; height: 16px; object-fit: contain; }
.mkp-price-unit { font-size: 0.72rem; font-weight: 600; color: var(--text-muted); }

.mkp-seller {
    font-size: 0.68rem;
    color: var(--text-muted);
    margin-top: 0.35rem;
}

.mkp-buy-btn {
    padding: 0.5rem 1rem;
    background: var(--accent);
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 0.78rem;
    font-weight: 800;
    cursor: pointer;
    font-family: inherit;
    transition: background 0.15s;
    white-space: nowrap;
    flex-shrink: 0;
}

.mkp-buy-btn:hover    { background: #6d28d9; }
.mkp-buy-btn.own      { background: transparent; border: 1px solid var(--border); color: var(--text-muted); cursor: default; }
.mkp-buy-btn.sold-out { background: rgba(255,255,255,0.05); color: var(--text-muted); cursor: default; }

/* ── Empty state ── */
.mkp-empty {
    grid-column: 1/-1;
    text-align: center;
    padding: 4rem 1rem;
    color: var(--text-muted);
}
.mkp-empty i { font-size: 2.5rem; display: block; margin-bottom: 0.75rem; opacity: 0.3; }
.mkp-empty p { font-size: 0.88rem; }

/* ── My listings ── */
.mkp-my-section { margin-top: 2.5rem; }

.mkp-section-title {
    font-size: 1rem;
    font-weight: 800;
    color: var(--text);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.mkp-my-row {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 0.9rem 1.1rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 0.5rem;
}

.mkp-my-info { flex: 1; min-width: 0; }
.mkp-my-title { font-size: 0.9rem; font-weight: 700; color: var(--text); }
.mkp-my-meta  { font-size: 0.72rem; color: var(--text-muted); margin-top: 0.2rem; }

.mkp-status-badge {
    font-size: 0.68rem;
    font-weight: 700;
    padding: 0.25rem 0.65rem;
    border-radius: 99px;
    white-space: nowrap;
}
.mkp-status-badge.active  { background: rgba(34,197,94,0.12);  color: #4ade80;  border: 1px solid rgba(34,197,94,0.25); }
.mkp-status-badge.sold    { background: rgba(251,191,36,0.12); color: #fbbf24;  border: 1px solid rgba(251,191,36,0.25); }
.mkp-status-badge.removed { background: rgba(239,68,68,0.1);   color: #f87171;  border: 1px solid rgba(239,68,68,0.2); }

.mkp-remove-btn {
    background: transparent;
    border: 1px solid var(--border);
    color: var(--text-muted);
    border-radius: 7px;
    padding: 0.3rem 0.7rem;
    font-size: 0.72rem;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.15s;
    flex-shrink: 0;
}
.mkp-remove-btn:hover { border-color: #ef4444; color: #f87171; }

/* ── Post modal ── */
.mkp-modal-bg {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.7);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}
.mkp-modal-bg.open { display: flex; }

.mkp-modal {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 20px;
    width: 100%;
    max-width: 440px;
    padding: 1.75rem;
    box-shadow: 0 20px 60px rgba(0,0,0,0.6);
    animation: modalIn 0.25s cubic-bezier(0.34,1.56,0.64,1);
}

@keyframes modalIn {
    from { opacity: 0; transform: scale(0.92) translateY(20px); }
    to   { opacity: 1; transform: scale(1) translateY(0); }
}

.mkp-modal-title {
    font-size: 1.1rem;
    font-weight: 800;
    margin-bottom: 1.25rem;
}

.mkp-form-field { margin-bottom: 1rem; }

.mkp-form-field label {
    display: block;
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--text-muted);
    margin-bottom: 0.4rem;
}

.mkp-form-field input,
.mkp-form-field textarea,
.mkp-form-field select {
    width: 100%;
    background: var(--bg-dark);
    border: 1.5px solid var(--border);
    border-radius: 10px;
    color: var(--text);
    padding: 0.7rem 1rem;
    font-size: 0.9rem;
    font-family: inherit;
    outline: none;
    transition: border-color 0.2s;
}

.mkp-form-field input:focus,
.mkp-form-field textarea:focus,
.mkp-form-field select:focus { border-color: var(--accent); }
.mkp-form-field input::placeholder,
.mkp-form-field textarea::placeholder { color: #374151; }
.mkp-form-field textarea { resize: vertical; min-height: 80px; }

.mkp-modal-actions {
    display: flex;
    gap: 0.6rem;
    margin-top: 1.25rem;
}

.mkp-modal-submit {
    flex: 1;
    padding: 0.8rem;
    background: var(--accent);
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: 0.9rem;
    font-weight: 800;
    cursor: pointer;
    font-family: inherit;
    transition: background 0.2s;
    box-shadow: 0 4px 14px rgba(124,58,237,0.3);
}
.mkp-modal-submit:hover    { background: #6d28d9; }
.mkp-modal-submit:disabled { opacity: 0.45; cursor: not-allowed; }

.mkp-modal-cancel {
    padding: 0.8rem 1.2rem;
    background: transparent;
    border: 1px solid var(--border);
    border-radius: 10px;
    color: var(--text-muted);
    font-size: 0.9rem;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
}

.mkp-modal-error {
    display: none;
    background: rgba(239,68,68,0.08);
    border: 1px solid rgba(239,68,68,0.25);
    border-radius: 8px;
    padding: 0.6rem 0.9rem;
    color: #fca5a5;
    font-size: 0.8rem;
    margin-bottom: 0.75rem;
}
.mkp-modal-error.show { display: block; }

/* ── Toast ── */
.mkp-toast {
    position: fixed;
    top: 1rem; left: 50%;
    transform: translateX(-50%) translateY(-80px);
    background: var(--bg-card);
    border: 1.5px solid #22c55e;
    border-radius: 12px;
    padding: 0.75rem 1.25rem;
    font-size: 0.85rem;
    font-weight: 700;
    color: #4ade80;
    z-index: 2000;
    transition: transform 0.35s cubic-bezier(0.34,1.56,0.64,1);
    white-space: nowrap;
    box-shadow: 0 8px 24px rgba(0,0,0,0.4);
}
.mkp-toast.show { transform: translateX(-50%) translateY(0); }

/* Image upload zone */
.mkp-img-zone {
    border: 2px dashed var(--border);
    border-radius: 12px;
    padding: 1.25rem 1rem;
    text-align: center;
    cursor: pointer;
    transition: border-color 0.2s, background 0.2s;
    position: relative;
    overflow: hidden;
    background: var(--bg-dark);
}
.mkp-img-zone:hover { border-color: var(--accent); background: rgba(124,58,237,0.05); }
.mkp-img-zone.has-img { padding: 0; border-style: solid; border-color: var(--accent); }
.mkp-img-zone input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
.mkp-img-zone-label { font-size: 0.8rem; color: var(--text-muted); pointer-events: none; }
.mkp-img-zone-label i { display: block; font-size: 1.75rem; margin-bottom: 0.35rem; opacity: 0.4; }
.mkp-img-preview { width: 100%; height: 180px; object-fit: cover; display: block; border-radius: 10px; }
.mkp-img-clear {
    position: absolute; top: 6px; right: 6px;
    background: rgba(0,0,0,0.65); color: #fff;
    border: none; border-radius: 50%; width: 26px; height: 26px;
    font-size: 0.8rem; cursor: pointer; display: none;
    align-items: center; justify-content: center;
    z-index: 2;
}
.mkp-img-zone.has-img .mkp-img-clear { display: flex; }

/* Spinner */
.spinner { display: inline-block; width: 14px; height: 14px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

@media (max-width: 600px) {
    .mkp-hero { flex-direction: column; align-items: flex-start; }
    .mkp-grid { grid-template-columns: 1fr 1fr; gap: 0.75rem; }
    .mkp-toolbar { flex-direction: column; align-items: stretch; }
    .mkp-sell-btn { justify-content: center; }
}
@media (max-width: 420px) {
    .mkp-grid { grid-template-columns: 1fr; }
}
</style>

<div class="mkp-page">

    <!-- Hero -->
    <div class="mkp-hero">
        <div class="mkp-hero-left">
            <h1><i class="bi bi-shop"></i> H-Coin Market</h1>
            <p>Buy and sell anything — H-Coins only.</p>
        </div>
        <?php if ($logged_in): ?>
        <div class="mkp-balance-pill">
            <img src="<?= base_url('images/hcoin-icon.png') ?>" alt="HC">
            <span id="mkpBalance"><?= number_format($h_coins) ?></span> HC
        </div>
        <?php else: ?>
        <a href="<?= base_url('login.php') ?>" class="mkp-balance-pill" style="text-decoration:none;">
            <i class="bi bi-box-arrow-in-right"></i> Login
        </a>
        <?php endif; ?>
    </div>

    <!-- Toolbar -->
    <div class="mkp-toolbar">
        <div class="mkp-tabs">
            <button class="mkp-tab active" id="tabBrowse" onclick="showPanel('browse')">
                <i class="bi bi-grid"></i> Browse
            </button>
        </div>
    </div>

    <!-- Browse panel -->
    <div id="panelBrowse">
        <!-- Category filter -->
        <div class="mkp-cats">
            <a href="<?= base_url('marketplace.php') ?>" class="mkp-cat-btn <?= !$cat ? 'active' : '' ?>">
                <i class="bi bi-grid-fill"></i> All
            </a>
            <?php foreach ($categories as $key => $c): ?>
            <a href="<?= base_url('marketplace.php') ?>?cat=<?= $key ?>" class="mkp-cat-btn <?= $cat === $key ? 'active' : '' ?>">
                <i class="bi <?= $c['icon'] ?>"></i> <?= $c['label'] ?>
            </a>
            <?php endforeach; ?>
        </div>

        <div class="mkp-grid">
            <?php if (empty($listings)): ?>
            <div class="mkp-empty">
                <i class="bi bi-shop"></i>
                <p>No listings yet<?= $cat ? ' in this category' : '' ?>.<br>Be the first to sell something!</p>
            </div>
            <?php else: ?>
            <?php foreach ($listings as $item):
                $is_own = (int)$item['seller_id'] === $uid;
                $cat_label = $categories[$item['category']]['label'] ?? 'General';
                $cat_icon  = $categories[$item['category']]['icon']  ?? 'bi-grid';
            ?>
            <div class="mkp-card" id="card-<?= $item['id'] ?>" onclick="window.location='<?= base_url('product.php?id=') . (int)$item['id'] ?>'" style="cursor:pointer;">
                <?php
                $gallery_imgs = [];
                if (!empty($item['image_path'])) $gallery_imgs[] = $item['image_path'];
                if (!empty($item['gallery'])) {
                    $extra = json_decode($item['gallery'], true);
                    if (is_array($extra)) $gallery_imgs = array_merge($gallery_imgs, $extra);
                }
                ?>
                <?php if (!empty($gallery_imgs)): ?>
                <div class="mkp-card-gallery" data-gallery="<?= $item['id'] ?>">
                    <?php foreach ($gallery_imgs as $gi => $gp): ?>
                    <img class="mkp-card-img <?= $gi===0?'active':'' ?>"
                         src="<?= base_url(htmlspecialchars($gp)) ?>"
                         alt="<?= htmlspecialchars($item['title']) ?> <?= $gi+1 ?>" loading="lazy">
                    <?php endforeach; ?>
                    <?php if (count($gallery_imgs) > 1): ?>
                    <button class="gallery-arrow prev" onclick="galleryNav(event,<?= $item['id'] ?>,-1)">&#8249;</button>
                    <button class="gallery-arrow next" onclick="galleryNav(event,<?= $item['id'] ?>,1)">&#8250;</button>
                    <div class="gallery-dots">
                        <?php foreach ($gallery_imgs as $gi => $gp): ?>
                        <button class="gallery-dot <?= $gi===0?'active':'' ?>" onclick="galleryGoto(event,<?= $item['id'] ?>,<?= $gi ?>)"></button>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="mkp-card-img-placeholder"><i class="bi <?= $cat_icon ?>"></i></div>
                <?php endif; ?>
                <div class="mkp-card-cat">
                    <i class="bi <?= $cat_icon ?>"></i> <?= htmlspecialchars($cat_label) ?>
                </div>
                <div class="mkp-card-body">
                    <div class="mkp-card-title"><?= htmlspecialchars($item['title']) ?></div>
                    <?php if ($item['description']): ?>
                    <div class="mkp-card-desc"><?= htmlspecialchars($item['description']) ?></div>
                    <?php endif; ?>
                    <div class="mkp-seller">by <?= htmlspecialchars($item['seller_name'] ?: 'Unknown') ?></div>
                    <div class="mkp-card-footer" style="margin-top:0.85rem;">
                        <div>
                            <div class="mkp-price">
                                <img src="<?= base_url('images/hcoin-icon.png') ?>" alt="HC">
                                <?= number_format((int)$item['price']) ?>
                                <span class="mkp-price-unit">HC</span>
                            </div>
                        </div>
                        <?php if (!$logged_in): ?>
                        <a href="<?= base_url('login.php') ?>" class="mkp-buy-btn" style="text-decoration:none;">Login to Buy</a>
                        <?php elseif ($is_own): ?>
                        <button class="mkp-buy-btn own" disabled>Yours</button>
                        <?php else: ?>
                        <button class="mkp-buy-btn" onclick="event.stopPropagation(); buyItem(<?= $item['id'] ?>, <?= (int)$item['price'] ?>, this)">
                            Buy
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- My listings panel -->
    <div id="panelMine" style="display:none;">
        <?php if (empty($my_listings)): ?>
        <div class="mkp-empty" style="grid-column:unset;">
            <i class="bi bi-box-seam"></i>
            <p>You haven't listed anything yet.</p>
        </div>
        <?php else: ?>
        <?php foreach ($my_listings as $item): ?>
        <div class="mkp-my-row" id="my-<?= $item['id'] ?>">
            <div class="mkp-my-info">
                <div class="mkp-my-title"><?= htmlspecialchars($item['title']) ?></div>
                <div class="mkp-my-meta">
                    <?= number_format((int)$item['price']) ?> HC ·
                    <?= htmlspecialchars($categories[$item['category']]['label'] ?? 'General') ?> ·
                    <?= date('M j', strtotime($item['created_at'])) ?>
                    <?php if ($item['status'] === 'sold' && $item['buyer_name']): ?>
                    · Sold to <?= htmlspecialchars($item['buyer_name']) ?>
                    <?php endif; ?>
                </div>
            </div>
            <span class="mkp-status-badge <?= $item['status'] ?>">
                <?= ucfirst($item['status']) ?>
            </span>
            <?php if ($item['status'] === 'active'): ?>
            <button class="mkp-remove-btn" onclick="removeItem(<?= $item['id'] ?>, this)">
                <i class="bi bi-trash"></i>
            </button>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<!-- Post modal -->
<div class="mkp-modal-bg" id="mkpModalBg" onclick="if(event.target===this)closeModal()">
    <div class="mkp-modal">
        <div class="mkp-modal-title"><i class="bi bi-plus-circle"></i> List an Item</div>

        <div class="mkp-modal-error" id="mkpModalErr"></div>

        <!-- Product image -->
        <div class="mkp-form-field">
            <label>Product Image <span style="font-weight:400;text-transform:none;letter-spacing:0;">(optional · max 5 MB)</span></label>
            <div class="mkp-img-zone" id="imgZone" onclick="document.getElementById('fImage').click()">
                <div class="mkp-img-zone-label" id="imgZoneLabel">
                    <i class="bi bi-image"></i>
                    Click to upload a photo
                </div>
                <img class="mkp-img-preview" id="imgPreview" style="display:none;" alt="">
                <input type="file" id="fImage" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none;" onchange="previewImage(this)">
                <button type="button" class="mkp-img-clear" id="imgClearBtn" onclick="clearImage(event)" title="Remove image">
                    <i class="bi bi-x"></i>
                </button>
            </div>
        </div>

        <div class="mkp-form-field">
            <label>Title</label>
            <input type="text" id="fTitle" placeholder="What are you selling?" maxlength="120">
        </div>
        <div class="mkp-form-field">
            <label>Description <span style="font-weight:400;text-transform:none;letter-spacing:0;">(optional)</span></label>
            <textarea id="fDesc" placeholder="Details, condition, delivery method…"></textarea>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
            <div class="mkp-form-field">
                <label>Price (HC)</label>
                <input type="number" id="fPrice" placeholder="100" min="1">
            </div>
            <div class="mkp-form-field">
                <label>Category</label>
                <select id="fCat">
                    <?php foreach ($categories as $key => $c): ?>
                    <option value="<?= $key ?>"><?= $c['label'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="mkp-modal-actions">
            <button class="mkp-modal-cancel" onclick="closeModal()">Cancel</button>
            <button class="mkp-modal-submit" id="mkpSubmitBtn" onclick="postListing()">
                <i class="bi bi-upload"></i> Post Listing
            </button>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="mkp-toast" id="mkpToast"></div>

<script>
const API_POST   = <?= json_encode(base_url('api/marketplace-post.php')) ?>;
const API_BUY    = <?= json_encode(base_url('api/marketplace-buy.php')) ?>;
const API_REMOVE = <?= json_encode(base_url('api/marketplace-remove.php')) ?>;
const MY_UID     = <?= $uid ?>;

// ── Tabs ──
function showPanel(p) {
    document.getElementById('panelBrowse').style.display = p === 'browse' ? '' : 'none';
    document.getElementById('panelMine').style.display   = p === 'mine'   ? '' : 'none';
    document.getElementById('tabBrowse').classList.toggle('active', p === 'browse');
    document.getElementById('tabMine').classList.toggle('active', p === 'mine');
}

// ── Image preview ──
function previewImage(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        const preview = document.getElementById('imgPreview');
        const zone    = document.getElementById('imgZone');
        const label   = document.getElementById('imgZoneLabel');
        preview.src = e.target.result;
        preview.style.display = 'block';
        label.style.display   = 'none';
        zone.classList.add('has-img');
    };
    reader.readAsDataURL(input.files[0]);
}

function clearImage(e) {
    e.stopPropagation();
    const input   = document.getElementById('fImage');
    const preview = document.getElementById('imgPreview');
    const zone    = document.getElementById('imgZone');
    const label   = document.getElementById('imgZoneLabel');
    input.value       = '';
    preview.src       = '';
    preview.style.display = 'none';
    label.style.display   = '';
    zone.classList.remove('has-img');
}

// ── Modal ──
function openModal() {
    document.getElementById('mkpModalBg').classList.add('open');
    document.getElementById('fTitle').focus();
}
function closeModal() {
    document.getElementById('mkpModalBg').classList.remove('open');
    document.getElementById('mkpModalErr').classList.remove('show');
    clearImage({ stopPropagation: () => {} });
    document.getElementById('fTitle').value = '';
    document.getElementById('fDesc').value  = '';
    document.getElementById('fPrice').value = '';
}

// ── Post listing ──
async function postListing() {
    const title = document.getElementById('fTitle').value.trim();
    const desc  = document.getElementById('fDesc').value.trim();
    const price = parseInt(document.getElementById('fPrice').value, 10);
    const cat   = document.getElementById('fCat').value;
    const errEl = document.getElementById('mkpModalErr');
    errEl.classList.remove('show');

    if (!title)          { errEl.textContent = 'Enter a title'; errEl.classList.add('show'); return; }
    if (!price || price < 1) { errEl.textContent = 'Enter a valid price'; errEl.classList.add('show'); return; }

    const btn = document.getElementById('mkpSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Posting…';

    // Use FormData to support image upload
    const fd = new FormData();
    fd.append('title', title);
    fd.append('description', desc);
    fd.append('price', price);
    fd.append('category', cat);
    const imgFile = document.getElementById('fImage').files[0];
    if (imgFile) fd.append('image', imgFile);

    try {
        const res  = await fetch(API_POST, {
            method: 'POST',
            body: fd,
            credentials: 'include',
        });
        const data = await res.json();
        if (data.error) {
            errEl.textContent = data.error;
            errEl.classList.add('show');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-upload"></i> Post Listing';
            return;
        }
        closeModal();
        toast('Listing posted! ✓');
        setTimeout(() => location.reload(), 1200);
    } catch (e) {
        errEl.textContent = 'Network error — try again';
        errEl.classList.add('show');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-upload"></i> Post Listing';
    }
}

// ── Buy ──
async function buyItem(id, price, btn) {
    if (!confirm('Buy this item for ' + price.toLocaleString() + ' HC?')) return;
    btn.disabled = true;
    btn.textContent = '…';

    try {
        const res  = await fetch(API_BUY, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ listing_id: id }),
            credentials: 'include',
        });
        const data = await res.json();
        if (data.error) { alert(data.error); btn.disabled = false; btn.textContent = 'Buy'; return; }

        // Update balance display
        document.getElementById('mkpBalance').textContent = Number(data.new_balance).toLocaleString();
        // Mark card as sold
        const card = document.getElementById('card-' + id);
        if (card) { card.style.opacity = '0.45'; card.style.pointerEvents = 'none'; }
        btn.className = 'mkp-buy-btn sold-out';
        btn.textContent = 'Sold';
        toast('Purchased: ' + data.item + ' for ' + Number(data.price).toLocaleString() + ' HC');
    } catch (e) {
        alert('Network error — try again');
        btn.disabled = false;
        btn.textContent = 'Buy';
    }
}

// ── Remove listing ──
async function removeItem(id, btn) {
    if (!confirm('Remove this listing?')) return;
    btn.disabled = true;
    try {
        const res  = await fetch(API_REMOVE, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ listing_id: id }),
            credentials: 'include',
        });
        const data = await res.json();
        if (data.error) { alert(data.error); btn.disabled = false; return; }
        const row = document.getElementById('my-' + id);
        if (row) row.style.display = 'none';
        toast('Listing removed');
    } catch (e) {
        alert('Network error');
        btn.disabled = false;
    }
}

// ── Toast ──
let toastTimer;
function toast(msg) {
    const el = document.getElementById('mkpToast');
    el.textContent = msg;
    el.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => el.classList.remove('show'), 3000);
}

// Close modal on Escape
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

// ── Image gallery ──
function galleryNav(e, id, dir) {
    e.stopPropagation();
    const wrap = document.querySelector('[data-gallery="'+id+'"]');
    if (!wrap) return;
    const imgs = wrap.querySelectorAll('.mkp-card-img');
    const dots = wrap.querySelectorAll('.gallery-dot');
    let cur = [...imgs].findIndex(i => i.classList.contains('active'));
    imgs[cur].classList.remove('active');
    if (dots[cur]) dots[cur].classList.remove('active');
    cur = (cur + dir + imgs.length) % imgs.length;
    imgs[cur].classList.add('active');
    if (dots[cur]) dots[cur].classList.add('active');
}
function galleryGoto(e, id, idx) {
    e.stopPropagation();
    const wrap = document.querySelector('[data-gallery="'+id+'"]');
    if (!wrap) return;
    const imgs = wrap.querySelectorAll('.mkp-card-img');
    const dots = wrap.querySelectorAll('.gallery-dot');
    imgs.forEach((i,n) => { i.classList.toggle('active', n===idx); });
    dots.forEach((d,n) => { d.classList.toggle('active', n===idx); });
}

// ── Marketplace live feed (polling api/marketplace-feed.php every 15s) ──
(function() {
    const POLL_MS = 15000;
    let lastTs = Math.floor(Date.now() / 1000); // only fetch events after page load

    const CAT_ICONS  = { general: 'bi-grid', gaming: 'bi-controller', services: 'bi-tools', digital: 'bi-file-earmark-code', merch: 'bi-bag', other: 'bi-three-dots' };
    const CAT_LABELS = { general: 'General', gaming: 'Gaming', services: 'Services', digital: 'Digital', merch: 'Merch', other: 'Other' };

    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, m =>
            ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
    }

    function handleCreated(d) {
        if (document.getElementById('card-' + d.id)) return;
        const grid = document.querySelector('.mkp-grid');
        if (!grid) return;
        const icon  = CAT_ICONS[d.category]  || 'bi-grid';
        const label = CAT_LABELS[d.category] || 'General';
        const imgBlock = d.image_path
            ? '<img class="mkp-card-img active" src="/' + esc(d.image_path).replace(/^\//,'') + '" alt="' + esc(d.title) + '">'
            : '<div class="mkp-card-img-placeholder"><i class="bi ' + icon + '"></i></div>';
        const buyBtn = d.seller_id === MY_UID
            ? '<button class="mkp-buy-btn own" disabled>Yours</button>'
            : (MY_UID
                ? '<button class="mkp-buy-btn" onclick="event.stopPropagation(); buyItem(' + d.id + ',' + d.price + ', this)">Buy</button>'
                : '<a href="/login.php" class="mkp-buy-btn" style="text-decoration:none;">Login to Buy</a>');
        const card = document.createElement('div');
        card.className = 'mkp-card';
        card.id = 'card-' + d.id;
        card.style.cursor = 'pointer';
        card.onclick = () => window.location = '/product.php?id=' + d.id;
        card.innerHTML =
            (d.image_path ? '<div class="mkp-card-gallery" data-gallery="' + d.id + '">' + imgBlock + '</div>' : imgBlock) +
            '<div class="mkp-card-cat"><i class="bi ' + icon + '"></i> ' + esc(label) + '</div>' +
            '<div class="mkp-card-body">' +
            '  <div class="mkp-card-title">' + esc(d.title) + '</div>' +
            (d.description ? '  <div class="mkp-card-desc">' + esc(d.description) + '</div>' : '') +
            '  <div class="mkp-seller">by ' + esc(d.seller_name) + '</div>' +
            '  <div class="mkp-card-footer" style="margin-top:0.85rem;">' +
            '    <div><div class="mkp-price"><img src="/images/hcoin-icon.png" alt="HC">' +
                  Number(d.price).toLocaleString() + ' <span class="mkp-price-unit">HC</span></div></div>' +
            '    ' + buyBtn +
            '  </div>' +
            '</div>';
        card.style.opacity = '0';
        card.style.transform = 'translateY(-8px)';
        card.style.transition = 'opacity 0.3s, transform 0.3s';
        grid.insertBefore(card, grid.firstChild);
        requestAnimationFrame(() => { card.style.opacity = '1'; card.style.transform = 'translateY(0)'; });
        const emptyEl = grid.querySelector('.mkp-empty');
        if (emptyEl) emptyEl.remove();
    }

    function handleSold(d) {
        const card = document.getElementById('card-' + d.id);
        if (!card) return;
        card.style.opacity = '0.45';
        card.style.pointerEvents = 'none';
        const btn = card.querySelector('.mkp-buy-btn');
        if (btn) { btn.className = 'mkp-buy-btn sold-out'; btn.textContent = 'Sold'; btn.disabled = true; }
    }

    function poll() {
        if (document.hidden) return;
        fetch('/api/marketplace-feed.php?since=' + lastTs)
            .then(r => r.json())
            .then(d => {
                if (typeof d.server_ts === 'number') lastTs = d.server_ts;
                (d.events || []).slice().reverse().forEach(ev => {
                    if (ev.type === 'listing.created') handleCreated(ev);
                    else if (ev.type === 'listing.sold') handleSold(ev);
                });
            })
            .catch(() => {});
    }
    setInterval(poll, POLL_MS);
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
