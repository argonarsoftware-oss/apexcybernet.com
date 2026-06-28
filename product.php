<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$user      = current_user($pdo);
$logged_in = !empty($user);
$uid       = $logged_in ? (int)$user['id'] : 0;
$h_coins   = 0;
if ($logged_in) {
    $bal = $pdo->prepare("SELECT h_coins FROM accounts WHERE id = ?");
    $bal->execute([$uid]);
    $h_coins = (int)$bal->fetchColumn();
}

$id = max(0, (int)($_GET['id'] ?? 0));
if (!$id) { header('Location: ' . base_url('marketplace.php')); exit; }

// Load listing + seller
$lst = $pdo->prepare("
    SELECT l.*, a.display_name AS seller_name, a.created_at AS seller_joined
    FROM marketplace_listings l
    JOIN accounts a ON a.id = l.seller_id
    WHERE l.id = ?
");
$lst->execute([$id]);
$item = $lst->fetch();
if (!$item) { header('Location: ' . base_url('marketplace.php')); exit; }

// Gallery
$images = [];
if (!empty($item['image_path'])) $images[] = $item['image_path'];
if (!empty($item['gallery'])) {
    $extra = json_decode($item['gallery'], true);
    if (is_array($extra)) $images = array_merge($images, $extra);
}
$video_path = !empty($item['video_path']) ? $item['video_path'] : null;
$total_slides = count($images) + ($video_path ? 1 : 0); // video is always last slide

// ── Review submission ──
$review_error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!$logged_in) {
        $review_error = 'You must be logged in to leave a review.';
    } else {
        $rating  = max(1, min(5, (int)($_POST['rating'] ?? 5)));
        $comment = mb_substr(trim($_POST['comment'] ?? ''), 0, 1000);
        try {
            $pdo->prepare("INSERT INTO marketplace_reviews (listing_id, reviewer_id, rating, comment)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment), created_at = NOW()")
                ->execute([$id, $uid, $rating, $comment]);
            header('Location: ' . base_url('product.php?id=' . $id . '&tab=reviews&reviewed=1'));
            exit;
        } catch (Exception $e) { $review_error = 'Could not save review.'; }
    }
}

// Reviews
$reviews = [];
try {
    $rv = $pdo->prepare("
        SELECT r.*, a.display_name AS reviewer_name
        FROM marketplace_reviews r JOIN accounts a ON a.id = r.reviewer_id
        WHERE r.listing_id = ? ORDER BY r.created_at DESC LIMIT 50
    ");
    $rv->execute([$id]);
    $reviews = $rv->fetchAll();
} catch (Exception $e) {}

$avg_rating    = count($reviews) ? round(array_sum(array_column($reviews, 'rating')) / count($reviews), 1) : 0;
$rating_dist   = array_fill(1, 5, 0);
foreach ($reviews as $r) $rating_dist[(int)$r['rating']]++;
$user_reviewed = $logged_in && in_array($uid, array_column($reviews, 'reviewer_id'));

// Seller listing count
$seller_count = 0;
try {
    $sc = $pdo->prepare("SELECT COUNT(*) FROM marketplace_listings WHERE seller_id = ? AND status = 'active'");
    $sc->execute([$item['seller_id']]);
    $seller_count = (int)$sc->fetchColumn();
} catch (Exception $e) {}

// Related
$related = [];
try {
    $rel = $pdo->prepare("
        SELECT l.*, a.display_name AS seller_name
        FROM marketplace_listings l JOIN accounts a ON a.id = l.seller_id
        WHERE l.status = 'active' AND l.category = ? AND l.id != ?
        ORDER BY l.created_at DESC LIMIT 6
    ");
    $rel->execute([$item['category'], $id]);
    $related = $rel->fetchAll();
} catch (Exception $e) {}

$categories = [
    'general'  => ['label' => 'General',  'icon' => 'bi-grid',             'color' => '#9ca3af'],
    'gaming'   => ['label' => 'Gaming',   'icon' => 'bi-controller',       'color' => '#a78bfa'],
    'services' => ['label' => 'Services', 'icon' => 'bi-briefcase',        'color' => '#60a5fa'],
    'digital'  => ['label' => 'Digital',  'icon' => 'bi-cloud-download',   'color' => '#34d399'],
    'merch'    => ['label' => 'Merch',    'icon' => 'bi-bag',              'color' => '#fb923c'],
    'other'    => ['label' => 'Other',    'icon' => 'bi-three-dots',       'color' => '#9ca3af'],
];
$cat       = $categories[$item['category']] ?? $categories['general'];
$cat_label = $cat['label'];
$cat_icon  = $cat['icon'];
$cat_color = $cat['color'];

$is_own    = $logged_in && (int)$item['seller_id'] === $uid;
$is_active = $item['status'] === 'active';
$can_buy   = $logged_in && !$is_own && $is_active;
$short_tab = $_GET['tab'] ?? 'desc';

$pageTitle       = htmlspecialchars($item['title']) . ' — Argonar Marketplace';
$pageDescription = mb_strimwidth(strip_tags($item['description'] ?? ''), 0, 160, '…') ?: 'Buy with HCoins on Argonar Marketplace.';
if (!empty($images[0])) $ogImage = base_url($images[0]);

require_once __DIR__ . '/includes/header.php';
?>
<style>
/* ── Reset / base ── */
:root {
    --surface: #17171f; --surface2: #1e1e28; --bg-dark: #0f0f13;
    --border: rgba(255,255,255,0.07); --border2: rgba(255,255,255,0.04);
    --accent: #7c3aed; --accent-light: #a78bfa;
    --yellow: #fbbf24; --green: #34d399; --red: #f87171; --blue: #60a5fa;
    --text: #e5e7eb; --muted: #9ca3af; --dim: #6b7280;
}

/* ── Breadcrumb ── */
.prd-breadcrumb {
    padding: 0.65rem 1.5rem;
    font-size: 0.76rem;
    color: var(--dim);
    border-bottom: 1px solid var(--border);
    background: rgba(255,255,255,0.015);
    display: flex; align-items: center; gap: 0.35rem; flex-wrap: wrap;
}
.prd-breadcrumb a { color: var(--muted); transition: color 0.15s; }
.prd-breadcrumb a:hover { color: var(--accent-light); }
.prd-breadcrumb .sep { color: var(--dim); opacity: 0.4; }

/* ── Page ── */
.prd-page { max-width: 1180px; margin: 0 auto; padding: 1.75rem 1.25rem 4rem; }

/* ── Hero section ── */
.prd-hero {
    display: grid;
    grid-template-columns: 500px 1fr;
    gap: 2.5rem;
    margin-bottom: 2rem;
}
@media (max-width: 900px) { .prd-hero { grid-template-columns: 1fr; } }

/* ── Gallery ── */
.gallery-col {}
.gallery-stage {
    position: relative;
    border-radius: 16px;
    overflow: hidden;
    background: var(--surface);
    border: 1px solid var(--border);
    aspect-ratio: 1/1;
    cursor: zoom-in;
    user-select: none;
}
.gallery-stage img {
    width: 100%; height: 100%;
    object-fit: contain;
    display: block;
    padding: 1.5rem;
    transition: transform 0.3s ease;
}
.gallery-stage:hover img { transform: scale(1.03); }
.gallery-stage .g-arrow {
    position: absolute; top: 50%; transform: translateY(-50%);
    background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.1);
    color: #fff; width: 38px; height: 38px; border-radius: 50%;
    font-size: 1rem; cursor: pointer; display: none;
    align-items: center; justify-content: center; z-index: 3;
    transition: background 0.15s;
}
.gallery-stage:hover .g-arrow { display: flex; }
.gallery-stage .g-arrow:hover { background: var(--accent); border-color: var(--accent); }
.gallery-stage .g-arrow.prev { left: 10px; }
.gallery-stage .g-arrow.next { right: 10px; }
.gallery-counter {
    position: absolute; bottom: 12px; right: 14px;
    background: rgba(0,0,0,0.55); backdrop-filter: blur(4px);
    color: #e5e7eb; font-size: 0.7rem; font-weight: 700;
    padding: 0.2rem 0.6rem; border-radius: 99px;
    border: 1px solid rgba(255,255,255,0.1);
}
.gallery-dots {
    position: absolute; bottom: 12px; left: 50%; transform: translateX(-50%);
    display: flex; gap: 5px;
}
.g-dot {
    width: 6px; height: 6px; border-radius: 50%;
    background: rgba(255,255,255,0.3); cursor: pointer; border: none; padding: 0;
    transition: all 0.15s;
}
.g-dot.on { background: #fff; transform: scale(1.3); }
.thumbs-row {
    display: flex; gap: 0.5rem; margin-top: 0.65rem; flex-wrap: wrap;
}
.g-thumb {
    width: 68px; height: 68px; border-radius: 10px;
    background: var(--surface); border: 2px solid var(--border);
    object-fit: contain; padding: 5px; cursor: pointer;
    transition: border-color 0.15s, transform 0.12s;
}
.g-thumb:hover { border-color: var(--accent-light); transform: translateY(-1px); }
.g-thumb.on { border-color: var(--accent); }
.g-thumb-video {
    width: 68px; height: 68px; border-radius: 10px;
    background: #0f0f13; border: 2px solid var(--border);
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    flex-direction: column; gap: 3px; flex-shrink: 0;
    transition: border-color 0.15s, transform 0.12s;
}
.g-thumb-video:hover { border-color: var(--accent-light); transform: translateY(-1px); }
.g-thumb-video.on { border-color: var(--accent); }
.g-thumb-video i { font-size: 1.3rem; color: var(--accent-light); }
.g-thumb-video span { font-size: 0.55rem; font-weight: 700; color: var(--dim); text-transform: uppercase; letter-spacing: 0.04em; }
/* Video slide in gallery stage */
.gallery-stage video {
    width: 100%; height: 100%;
    object-fit: contain; display: none;
    border-radius: 16px;
}
.gallery-stage.video-active { cursor: default; }
.gallery-stage.video-active img { display: none; }
.gallery-stage.video-active video { display: block; }
.gallery-stage.video-active:hover img { transform: none; }

/* ── Info col ── */
.info-col { display: flex; flex-direction: column; }
.cat-pill {
    display: inline-flex; align-items: center; gap: 0.35rem;
    font-size: 0.7rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase;
    border-radius: 99px; padding: 0.22rem 0.75rem;
    border: 1px solid; margin-bottom: 0.9rem; width: fit-content;
}
.prd-title {
    font-size: 1.45rem; font-weight: 900; line-height: 1.25;
    color: #fff; margin-bottom: 0.9rem; letter-spacing: -0.01em;
}
/* Rating row */
.rating-row { display: flex; align-items: center; gap: 0.6rem; margin-bottom: 1.2rem; flex-wrap: wrap; }
.stars-sm { display: flex; gap: 1px; }
.stars-sm i { font-size: 0.82rem; color: var(--yellow); }
.stars-sm i.off { color: #2d2d3a; }
.rating-num { font-weight: 800; font-size: 0.88rem; color: var(--yellow); }
.sep-dot { color: var(--dim); }
.review-link { font-size: 0.8rem; color: var(--muted); border-bottom: 1px dotted rgba(255,255,255,0.15); padding-bottom: 1px; cursor: pointer; }
.review-link:hover { color: var(--accent-light); }

/* Price box */
.price-box {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 1.25rem 1.4rem;
    margin-bottom: 1.25rem;
    position: relative;
    overflow: hidden;
}
.price-box::before {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse at 0% 0%, rgba(251,191,36,0.06) 0%, transparent 60%);
    pointer-events: none;
}
.price-tag {
    display: flex; align-items: baseline; gap: 0.45rem; margin-bottom: 0.5rem;
}
.price-tag img { width: 30px; height: 30px; object-fit: contain; align-self: center; }
.price-amount { font-size: 2.4rem; font-weight: 900; color: var(--yellow); line-height: 1; letter-spacing: -0.02em; }
.price-unit { font-size: 1rem; font-weight: 700; color: rgba(251,191,36,0.6); }
.status-pill {
    display: inline-flex; align-items: center; gap: 0.35rem;
    font-size: 0.72rem; font-weight: 700; border-radius: 99px;
    padding: 0.2rem 0.65rem; border: 1px solid;
}
.pill-active { color: var(--green); border-color: rgba(52,211,153,0.25); background: rgba(52,211,153,0.08); }
.pill-sold   { color: var(--red);   border-color: rgba(248,113,113,0.25); background: rgba(248,113,113,0.08); }

/* Seller card */
.seller-box {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 1rem 1.2rem;
    display: flex; align-items: center; gap: 0.85rem;
    margin-bottom: 1.25rem;
    transition: border-color 0.15s;
}
.seller-box:hover { border-color: rgba(167,139,250,0.25); }
.seller-ava {
    width: 44px; height: 44px; border-radius: 12px; flex-shrink: 0;
    background: linear-gradient(135deg, var(--accent) 0%, #312e81 100%);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem; font-weight: 900; color: #fff;
    letter-spacing: -0.02em;
}
.seller-nm { font-weight: 800; font-size: 0.9rem; color: var(--text); }
.seller-meta { font-size: 0.7rem; color: var(--dim); margin-top: 3px; }
.seller-stats { margin-left: auto; text-align: right; }
.seller-stats .sv { font-size: 1.1rem; font-weight: 900; color: var(--accent-light); }
.seller-stats .sl { font-size: 0.65rem; color: var(--dim); }

/* Buy button */
.btn-buy {
    width: 100%; padding: 1rem 1.5rem;
    background: linear-gradient(135deg, var(--accent) 0%, #4338ca 100%);
    color: #fff; border: none; border-radius: 14px;
    font-size: 1rem; font-weight: 800; letter-spacing: 0.01em;
    cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.6rem;
    transition: opacity 0.15s, transform 0.12s, box-shadow 0.15s;
    box-shadow: 0 4px 20px rgba(124,58,237,0.3);
    margin-bottom: 0.65rem;
}
.btn-buy:hover:not(:disabled) { opacity: 0.92; transform: translateY(-1px); box-shadow: 0 6px 28px rgba(124,58,237,0.45); }
.btn-buy:active:not(:disabled) { transform: translateY(0); }
.btn-buy:disabled { opacity: 0.35; cursor: not-allowed; box-shadow: none; }
.btn-login {
    width: 100%; padding: 0.9rem 1.5rem;
    background: transparent; color: var(--accent-light);
    border: 1px solid rgba(124,58,237,0.35); border-radius: 14px;
    font-size: 0.95rem; font-weight: 700; text-align: center;
    display: block; cursor: pointer; transition: background 0.15s;
    margin-bottom: 0.65rem;
}
.btn-login:hover { background: rgba(124,58,237,0.08); }
.bal-note {
    text-align: center; font-size: 0.74rem; color: var(--dim);
    display: flex; align-items: center; justify-content: center; gap: 0.35rem;
}
.bal-note img { width: 14px; height: 14px; object-fit: contain; }
.bal-note .bv { font-weight: 700; color: var(--yellow); }
.bal-note .ins { color: var(--red); font-size: 0.7rem; }

/* ── Body tabs ── */
.prd-tabs-bar {
    display: flex; gap: 0; border-bottom: 1px solid var(--border);
    margin-bottom: 1.75rem;
}
.prd-tab {
    background: none; border: none; color: var(--dim);
    font-size: 0.9rem; font-weight: 700; padding: 0.8rem 1.5rem;
    cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -1px;
    transition: color 0.15s; display: flex; align-items: center; gap: 0.4rem;
}
.prd-tab:hover:not(.on) { color: var(--muted); }
.prd-tab.on { color: #fff; border-bottom-color: var(--accent); }
.tab-count {
    background: rgba(124,58,237,0.15); color: var(--accent-light);
    border-radius: 99px; padding: 0 0.45rem; font-size: 0.68rem; font-weight: 800;
}
.tab-body { display: none; }
.tab-body.on { display: block; }

/* ── Description ── */
.desc-box {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 1.5rem 1.75rem;
    font-size: 0.88rem; line-height: 1.8;
    color: #d1d5db; white-space: pre-wrap; word-break: break-word;
}
.desc-box:empty::before { content: 'No description provided.'; color: var(--dim); }

/* ── Reviews ── */
.reviews-wrap {}
.rv-summary {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 16px; padding: 1.5rem 1.75rem;
    display: flex; align-items: center; gap: 2rem;
    margin-bottom: 1.5rem; flex-wrap: wrap;
}
.rv-big { text-align: center; min-width: 80px; }
.rv-big .num { font-size: 3.5rem; font-weight: 900; color: var(--yellow); line-height: 1; letter-spacing: -0.03em; }
.rv-big .stars-lg { display: flex; gap: 3px; justify-content: center; margin: 4px 0; }
.rv-big .stars-lg i { font-size: 1rem; color: var(--yellow); }
.rv-big .stars-lg i.off { color: #2d2d3a; }
.rv-big .lbl { font-size: 0.72rem; color: var(--dim); }
.rv-bars { flex: 1; min-width: 200px; display: flex; flex-direction: column; gap: 0.35rem; }
.rv-bar-row { display: flex; align-items: center; gap: 0.5rem; font-size: 0.74rem; color: var(--muted); }
.rv-bar-row .n { width: 8px; text-align: right; flex-shrink: 0; }
.rv-bar-track { flex: 1; height: 8px; background: rgba(255,255,255,0.05); border-radius: 99px; overflow: hidden; }
.rv-bar-fill { height: 100%; border-radius: 99px; background: linear-gradient(90deg, var(--yellow), #f59e0b); transition: width 0.5s ease; }
.rv-bar-cnt { width: 24px; text-align: right; flex-shrink: 0; color: var(--dim); font-size: 0.7rem; }

.rv-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 14px; padding: 1.1rem 1.3rem; margin-bottom: 0.75rem;
    transition: border-color 0.15s;
}
.rv-card:hover { border-color: rgba(255,255,255,0.1); }
.rv-card-top { display: flex; align-items: center; gap: 0.7rem; margin-bottom: 0.65rem; }
.rv-ava {
    width: 36px; height: 36px; border-radius: 10px; flex-shrink: 0;
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.82rem; font-weight: 900; color: #fff;
}
.rv-name { font-weight: 700; font-size: 0.85rem; color: var(--text); }
.rv-date { font-size: 0.7rem; color: var(--dim); margin-left: auto; }
.rv-stars { display: flex; gap: 2px; margin-bottom: 0.5rem; }
.rv-stars i { font-size: 0.78rem; color: var(--yellow); }
.rv-stars i.off { color: #2d2d3a; }
.rv-comment { font-size: 0.84rem; color: #d1d5db; line-height: 1.65; }
.rv-empty { color: var(--dim); font-size: 0.85rem; padding: 1rem 0; }

/* Review form */
.rv-form-box {
    background: var(--surface); border: 1px solid rgba(124,58,237,0.2);
    border-radius: 16px; padding: 1.5rem 1.75rem;
    margin-top: 1.5rem;
}
.rv-form-box h4 {
    font-size: 0.95rem; font-weight: 800; margin: 0 0 1.1rem;
    color: var(--text); display: flex; align-items: center; gap: 0.5rem;
}
.star-row { display: flex; gap: 5px; margin-bottom: 1rem; cursor: pointer; }
.star-row i { font-size: 1.8rem; color: #2d2d3a; transition: color 0.1s, transform 0.1s; }
.star-row i.on { color: var(--yellow); }
.star-row i:hover { transform: scale(1.15); }
.fld-label { display: block; font-size: 0.71rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--dim); margin-bottom: 0.4rem; }
.rv-textarea {
    width: 100%; background: var(--bg-dark); color: var(--text);
    border: 1px solid rgba(255,255,255,0.09); border-radius: 10px;
    padding: 0.7rem 0.9rem; font-size: 0.85rem; resize: vertical;
    min-height: 95px; font-family: inherit; line-height: 1.6;
}
.rv-textarea:focus { outline: none; border-color: var(--accent); }
.btn-rv-submit {
    margin-top: 0.9rem;
    background: var(--accent); color: #fff; border: none;
    border-radius: 10px; padding: 0.6rem 1.5rem;
    font-size: 0.85rem; font-weight: 700; cursor: pointer;
    transition: background 0.15s;
}
.btn-rv-submit:hover { background: #6d28d9; }
.rv-ok { background: rgba(52,211,153,0.08); border: 1px solid rgba(52,211,153,0.2); color: var(--green); border-radius: 10px; padding: 0.55rem 0.85rem; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.85rem; }
.rv-err { color: var(--red); font-size: 0.8rem; margin-bottom: 0.6rem; }

/* ── Related ── */
.related-section { margin-top: 3rem; }
.section-title {
    font-size: 1rem; font-weight: 800; color: #fff;
    margin-bottom: 0.25rem; display: flex; align-items: center; gap: 0.5rem;
}
.section-sub { font-size: 0.78rem; color: var(--dim); margin-bottom: 1rem; }
.rel-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 1rem; }
.rel-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 14px; overflow: hidden;
    display: flex; flex-direction: column;
    text-decoration: none; color: inherit;
    transition: border-color 0.2s, transform 0.15s, box-shadow 0.15s;
}
.rel-card:hover {
    border-color: rgba(124,58,237,0.35); transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}
.rel-img { width: 100%; height: 130px; object-fit: contain; padding: 0.6rem; background: #12121a; display: block; }
.rel-ph  { width: 100%; height: 130px; background: #12121a; display: flex; align-items: center; justify-content: center; font-size: 1.75rem; color: rgba(255,255,255,0.06); }
.rel-body { padding: 0.7rem 0.85rem; flex: 1; }
.rel-title { font-size: 0.78rem; font-weight: 600; color: var(--text); margin-bottom: 0.35rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4; }
.rel-price { display: flex; align-items: center; gap: 0.3rem; font-size: 0.82rem; font-weight: 900; color: var(--yellow); }
.rel-price img { width: 13px; height: 13px; object-fit: contain; }

/* ── Lightbox ── */
.lb { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.93); z-index: 3000; align-items: center; justify-content: center; }
.lb.open { display: flex; }
.lb-img { max-width: 90vw; max-height: 88vh; object-fit: contain; border-radius: 10px; }
.lb-close { position: absolute; top: 1rem; right: 1.25rem; background: rgba(255,255,255,0.08); border: none; color: #fff; width: 38px; height: 38px; border-radius: 50%; font-size: 1.1rem; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.lb-close:hover { background: rgba(255,255,255,0.16); }
.lb-nav { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(255,255,255,0.08); border: none; color: #fff; width: 48px; height: 48px; border-radius: 50%; font-size: 1.3rem; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.lb-nav:hover { background: var(--accent); }
.lb-nav.prev { left: 1.25rem; }
.lb-nav.next { right: 1.25rem; }
.lb-counter { position: absolute; bottom: 1.25rem; left: 50%; transform: translateX(-50%); background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); color: #e5e7eb; font-size: 0.75rem; font-weight: 700; padding: 0.3rem 0.85rem; border-radius: 99px; border: 1px solid rgba(255,255,255,0.1); }

/* ── Toast ── */
.toast-wrap { position: fixed; bottom: 1.75rem; left: 50%; transform: translateX(-50%); z-index: 4000; pointer-events: none; }
.toast { background: var(--surface2); border: 1px solid var(--border); color: var(--text); padding: 0.7rem 1.35rem; border-radius: 99px; font-size: 0.82rem; font-weight: 600; opacity: 0; transform: translateY(12px); transition: all 0.25s; pointer-events: none; white-space: nowrap; }
.toast.show { opacity: 1; transform: translateY(0); }
.toast.ok  { border-color: rgba(52,211,153,0.4); color: var(--green); }
.toast.err { border-color: rgba(248,113,113,0.4); color: var(--red); }
</style>

<!-- Breadcrumb -->
<div class="prd-breadcrumb">
    <a href="<?= base_url('marketplace.php') ?>">Marketplace</a>
    <span class="sep">›</span>
    <a href="<?= base_url('marketplace.php?cat='.urlencode($item['category'])) ?>"><?= htmlspecialchars($cat_label) ?></a>
    <span class="sep">›</span>
    <span style="color:var(--muted);"><?= htmlspecialchars(mb_strimwidth($item['title'], 0, 55, '…')) ?></span>
</div>

<div class="prd-page">
<div class="prd-hero">

    <!-- Gallery -->
    <div class="gallery-col">
        <div class="gallery-stage" id="gStage" onclick="stageClick()">
            <?php if (!empty($images)): ?>
            <img id="gMain" src="<?= base_url(htmlspecialchars($images[0])) ?>" alt="<?= htmlspecialchars($item['title']) ?>">
            <?php endif; ?>
            <?php if ($video_path): ?>
            <video id="gVideo" src="<?= base_url(htmlspecialchars($video_path)) ?>"
                   controls preload="metadata" playsinline
                   onclick="event.stopPropagation()"></video>
            <?php endif; ?>
            <?php if ($total_slides > 1): ?>
            <button class="g-arrow prev" onclick="event.stopPropagation(); gNav(-1)">&#8249;</button>
            <button class="g-arrow next" onclick="event.stopPropagation(); gNav(1)">&#8250;</button>
            <div class="gallery-counter" id="gCounter">1 / <?= $total_slides ?></div>
            <div class="gallery-dots" id="gDots">
                <?php for ($gi=0; $gi < $total_slides; $gi++): ?>
                <button class="g-dot <?= $gi===0?'on':'' ?>" onclick="event.stopPropagation(); gGoto(<?= $gi ?>)"></button>
                <?php endfor; ?>
            </div>
            <?php elseif (empty($images)): ?>
            <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:5rem;color:rgba(255,255,255,0.05);">
                <i class="bi <?= $cat_icon ?>"></i>
            </div>
            <?php endif; ?>
        </div>
        <?php if ($total_slides > 1): ?>
        <div class="thumbs-row">
            <?php foreach ($images as $gi => $gp): ?>
            <img class="g-thumb <?= $gi===0?'on':'' ?>" src="<?= base_url(htmlspecialchars($gp)) ?>"
                 onclick="gGoto(<?= $gi ?>)" alt="View <?= $gi+1 ?>">
            <?php endforeach; ?>
            <?php if ($video_path): ?>
            <div class="g-thumb-video" id="thumbVideo" onclick="gGoto(<?= count($images) ?>)">
                <i class="bi bi-play-circle-fill"></i>
                <span>Video</span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Info -->
    <div class="info-col">

        <span class="cat-pill" style="color:<?= $cat_color ?>;border-color:<?= $cat_color ?>33;background:<?= $cat_color ?>11;">
            <i class="bi <?= $cat_icon ?>"></i> <?= htmlspecialchars($cat_label) ?>
        </span>

        <h1 class="prd-title"><?= htmlspecialchars($item['title']) ?></h1>

        <!-- Rating row -->
        <?php if (count($reviews) > 0): ?>
        <div class="rating-row">
            <div class="stars-sm">
                <?php for ($s=1;$s<=5;$s++): ?>
                <i class="bi bi-star<?= $s<=round($avg_rating)?'-fill':'' ?> <?= $s>round($avg_rating)?'off':'' ?>"></i>
                <?php endfor; ?>
            </div>
            <span class="rating-num"><?= $avg_rating ?></span>
            <span class="sep-dot">·</span>
            <span class="review-link" onclick="showTab('reviews')"><?= count($reviews) ?> review<?= count($reviews)!==1?'s':'' ?></span>
        </div>
        <?php else: ?>
        <div class="rating-row">
            <div class="stars-sm">
                <?php for ($s=1;$s<=5;$s++): ?><i class="bi bi-star off"></i><?php endfor; ?>
            </div>
            <span style="font-size:0.78rem;color:var(--dim);">No reviews yet</span>
        </div>
        <?php endif; ?>

        <!-- Price -->
        <div class="price-box">
            <div class="price-tag">
                <img src="<?= base_url('images/hcoin-icon.png') ?>" alt="HC">
                <span class="price-amount"><?= number_format((int)$item['price']) ?></span>
                <span class="price-unit">HC</span>
            </div>
            <span class="status-pill <?= $is_active?'pill-active':'pill-sold' ?>">
                <?= $is_active ? '<i class="bi bi-circle-fill" style="font-size:0.5rem;"></i> Available' : '<i class="bi bi-check-circle-fill" style="font-size:0.7rem;"></i> Sold' ?>
            </span>
        </div>

        <!-- Seller -->
        <div class="seller-box">
            <div class="seller-ava"><?= mb_strtoupper(mb_substr($item['seller_name'],0,2)) ?></div>
            <div>
                <div class="seller-nm"><?= htmlspecialchars($item['seller_name']) ?></div>
                <div class="seller-meta">
                    <i class="bi bi-shop"></i> Seller &nbsp;·&nbsp;
                    <i class="bi bi-calendar3"></i> Joined <?= date('M Y', strtotime($item['seller_joined'])) ?>
                </div>
            </div>
            <div class="seller-stats">
                <div class="sv"><?= $seller_count ?></div>
                <div class="sl">active<br>listing<?= $seller_count!==1?'s':'' ?></div>
            </div>
        </div>

        <!-- CTA -->
        <?php if (!$is_active): ?>
        <button class="btn-buy" disabled><i class="bi bi-check-circle-fill"></i> Already Sold</button>
        <?php elseif (!$logged_in): ?>
        <a href="<?= base_url('login.php') ?>" class="btn-login"><i class="bi bi-box-arrow-in-right"></i> Login to Buy</a>
        <?php elseif ($is_own): ?>
        <button class="btn-buy" disabled style="background:rgba(255,255,255,0.06);box-shadow:none;"><i class="bi bi-bag-check"></i> This is your listing</button>
        <?php else: ?>
        <button class="btn-buy" id="buyBtn" onclick="doBuy()">
            <i class="bi bi-bag-fill"></i> Buy Now
            <span style="opacity:0.7;font-size:0.85rem;font-weight:600;">— <?= number_format((int)$item['price']) ?> HC</span>
        </button>
        <div class="bal-note">
            <img src="<?= base_url('images/hcoin-icon.png') ?>" alt="HC">
            Your balance: <span class="bv"><?= number_format($h_coins) ?> HC</span>
            <?php if ($h_coins < (int)$item['price']): ?>
            <span class="ins">· Insufficient</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- Tabs -->
<div class="prd-tabs-bar">
    <button class="prd-tab <?= $short_tab==='desc'?'on':'' ?>" onclick="showTab('desc')" id="tb-desc">
        <i class="bi bi-file-text"></i> Description
    </button>
    <button class="prd-tab <?= $short_tab==='reviews'?'on':'' ?>" onclick="showTab('reviews')" id="tb-reviews">
        <i class="bi bi-star"></i> Reviews
        <?php if (count($reviews)): ?><span class="tab-count"><?= count($reviews) ?></span><?php endif; ?>
    </button>
</div>

<!-- Description tab -->
<div class="tab-body <?= $short_tab==='desc'?'on':'' ?>" id="tab-desc">
    <div class="desc-box"><?= htmlspecialchars($item['description'] ?? '') ?></div>
</div>

<!-- Reviews tab -->
<div class="tab-body <?= $short_tab==='reviews'?'on':'' ?>" id="tab-reviews">
    <div class="reviews-wrap">

        <?php if (count($reviews) > 0): ?>
        <div class="rv-summary">
            <div class="rv-big">
                <div class="num"><?= number_format($avg_rating, 1) ?></div>
                <div class="stars-lg">
                    <?php for ($s=1;$s<=5;$s++): ?>
                    <i class="bi bi-star<?= $s<=round($avg_rating)?'-fill':'' ?> <?= $s>round($avg_rating)?'off':'' ?>"></i>
                    <?php endfor; ?>
                </div>
                <div class="lbl"><?= count($reviews) ?> reviews</div>
            </div>
            <div class="rv-bars">
                <?php for ($r=5;$r>=1;$r--): $c=$rating_dist[$r]??0; $pct=count($reviews)?round($c/count($reviews)*100):0; ?>
                <div class="rv-bar-row">
                    <span class="n"><?= $r ?></span>
                    <i class="bi bi-star-fill" style="color:var(--yellow);font-size:0.65rem;flex-shrink:0;"></i>
                    <div class="rv-bar-track"><div class="rv-bar-fill" style="width:<?= $pct ?>%;"></div></div>
                    <span class="rv-bar-cnt"><?= $c ?></span>
                </div>
                <?php endfor; ?>
            </div>
        </div>

        <?php foreach ($reviews as $rv): ?>
        <div class="rv-card">
            <div class="rv-card-top">
                <div class="rv-ava"><?= mb_strtoupper(mb_substr($rv['reviewer_name'],0,2)) ?></div>
                <div>
                    <div class="rv-name"><?= htmlspecialchars($rv['reviewer_name']) ?></div>
                    <div class="rv-stars">
                        <?php for ($s=1;$s<=5;$s++): ?><i class="bi bi-star<?= $s<=(int)$rv['rating']?'-fill':'' ?> <?= $s>(int)$rv['rating']?'off':'' ?>"></i><?php endfor; ?>
                    </div>
                </div>
                <span class="rv-date"><?= date('M j, Y', strtotime($rv['created_at'])) ?></span>
            </div>
            <?php if ($rv['comment']): ?>
            <div class="rv-comment"><?= htmlspecialchars($rv['comment']) ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <?php else: ?>
        <div class="rv-empty">No reviews yet — be the first to leave one below.</div>
        <?php endif; ?>

        <!-- Write review -->
        <?php if ($logged_in && !$user_reviewed): ?>
        <div class="rv-form-box">
            <h4><i class="bi bi-pencil-square" style="color:var(--accent-light);"></i> Write a Review</h4>
            <?php if ($review_error): ?><div class="rv-err"><?= htmlspecialchars($review_error) ?></div><?php endif; ?>
            <?php if (isset($_GET['reviewed'])): ?><div class="rv-ok"><i class="bi bi-check-circle-fill"></i> Your review was saved!</div><?php endif; ?>
            <form method="post">
                <input type="hidden" name="submit_review" value="1">
                <input type="hidden" name="rating" id="rvRating" value="5">
                <label class="fld-label">Your rating</label>
                <div class="star-row" id="rvStars">
                    <?php for ($s=1;$s<=5;$s++): ?><i class="bi bi-star-fill on" data-v="<?= $s ?>"></i><?php endfor; ?>
                </div>
                <label class="fld-label" for="rvComment">Comment <span style="text-transform:none;font-weight:400;">(optional)</span></label>
                <textarea class="rv-textarea" name="comment" id="rvComment" placeholder="Share your experience with this product…"></textarea>
                <button type="submit" class="btn-rv-submit"><i class="bi bi-send-fill"></i> Post Review</button>
            </form>
        </div>
        <?php elseif ($logged_in && $user_reviewed): ?>
        <div style="margin-top:1.25rem;font-size:0.82rem;color:var(--dim);"><i class="bi bi-check-circle"></i> You've already reviewed this product.</div>
        <?php elseif (!$logged_in): ?>
        <div style="margin-top:1.25rem;font-size:0.85rem;color:var(--dim);">
            <a href="<?= base_url('login.php') ?>" style="color:var(--accent-light);font-weight:700;">Log in</a> to leave a review.
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- Related products -->
<?php if (!empty($related)): ?>
<div class="related-section">
    <div class="section-title"><i class="bi <?= $cat_icon ?>" style="color:<?= $cat_color ?>;"></i> More in <?= htmlspecialchars($cat_label) ?></div>
    <div class="section-sub">Other listings you might like</div>
    <div class="rel-grid">
        <?php foreach ($related as $rel):
            $ri = [];
            if (!empty($rel['image_path'])) $ri[] = $rel['image_path'];
            if (!empty($rel['gallery'])) { $ex=json_decode($rel['gallery'],true); if(is_array($ex)) $ri=array_merge($ri,$ex); }
            $rc = $categories[$rel['category']] ?? $categories['general'];
        ?>
        <a class="rel-card" href="<?= base_url('product.php?id='.(int)$rel['id']) ?>">
            <?php if (!empty($ri)): ?>
            <img class="rel-img" src="<?= base_url(htmlspecialchars($ri[0])) ?>" alt="<?= htmlspecialchars($rel['title']) ?>" loading="lazy">
            <?php else: ?>
            <div class="rel-ph"><i class="bi <?= $rc['icon'] ?>"></i></div>
            <?php endif; ?>
            <div class="rel-body">
                <div class="rel-title"><?= htmlspecialchars($rel['title']) ?></div>
                <div class="rel-price">
                    <img src="<?= base_url('images/hcoin-icon.png') ?>" alt="HC">
                    <?= number_format((int)$rel['price']) ?> HC
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

</div><!-- /prd-page -->

<!-- Lightbox -->
<div class="lb" id="lb" onclick="if(event.target===this)closeLb()">
    <button class="lb-close" onclick="closeLb()">✕</button>
    <button class="lb-nav prev" onclick="event.stopPropagation(); gNav(-1); if(gIdx<imgs.length) document.getElementById('lbImg').src=imgs[gIdx];">&#8249;</button>
    <img class="lb-img" id="lbImg" src="" alt="">
    <button class="lb-nav next" onclick="event.stopPropagation(); gNav(1); if(gIdx<imgs.length) document.getElementById('lbImg').src=imgs[gIdx];">&#8250;</button>
    <div class="lb-counter" id="lbCounter"></div>
</div>

<div class="toast-wrap"><div class="toast" id="toast"></div></div>

<script>
// ── Gallery ──
const imgs = <?= json_encode(array_map(fn($p) => base_url($p), $images)) ?>;
const videoSrc = <?= $video_path ? json_encode(base_url($video_path)) : 'null' ?>;
const totalSlides = <?= $total_slides ?>;
let gIdx = 0;

function gGoto(i) {
    gIdx = i;
    const stage = document.getElementById('gStage');
    const vid = document.getElementById('gVideo');
    const isVideo = videoSrc && i >= imgs.length;
    stage.classList.toggle('video-active', isVideo);
    if (isVideo) {
        if (vid) vid.play();
    } else {
        if (vid) { vid.pause(); vid.currentTime = 0; }
        const m = document.getElementById('gMain');
        if (m) m.src = imgs[i];
    }
    document.querySelectorAll('.g-thumb').forEach((t,n) => t.classList.toggle('on', n===i));
    const vt = document.getElementById('thumbVideo');
    if (vt) vt.classList.toggle('on', isVideo);
    document.querySelectorAll('.g-dot').forEach((d,n) => d.classList.toggle('on', n===i));
    const c = document.getElementById('gCounter');
    if (c) c.textContent = (i+1) + ' / ' + totalSlides;
}
function gNav(d) { gGoto((gIdx + d + totalSlides) % totalSlides); }
function stageClick() {
    if (videoSrc && gIdx >= imgs.length) return; // don't open lightbox for video
    openLb(gIdx);
}

// ── Lightbox ──
function openLb(i) {
    if (!imgs.length) return;
    document.getElementById('lbImg').src = imgs[i];
    const lbc = document.getElementById('lbCounter');
    if (lbc && imgs.length > 1) lbc.textContent = (i+1) + ' / ' + imgs.length;
    document.getElementById('lb').classList.add('open');
}
function closeLb() { document.getElementById('lb').classList.remove('open'); }
document.addEventListener('keydown', e => {
    if (!document.getElementById('lb').classList.contains('open')) return;
    if (e.key === 'ArrowLeft')  { gNav(-1); if (gIdx < imgs.length) document.getElementById('lbImg').src = imgs[gIdx]; }
    if (e.key === 'ArrowRight') { gNav(1);  if (gIdx < imgs.length) document.getElementById('lbImg').src = imgs[gIdx]; }
    if (e.key === 'Escape')     closeLb();
});

// ── Tabs ──
function showTab(name) {
    document.querySelectorAll('.prd-tab').forEach(b => b.classList.remove('on'));
    document.querySelectorAll('.tab-body').forEach(p => p.classList.remove('on'));
    document.getElementById('tab-' + name).classList.add('on');
    document.getElementById('tb-' + name).classList.add('on');
    history.replaceState(null,'', '<?= base_url('product.php?id='.$id) ?>&tab=' + name);
}
<?php if (isset($_GET['reviewed'])): ?>showTab('reviews');<?php endif; ?>

// ── Star picker ──
const rvStars = document.querySelectorAll('#rvStars i');
const rvInput = document.getElementById('rvRating');
if (rvStars.length) {
    rvStars.forEach(s => {
        s.addEventListener('mouseover', () => rvStars.forEach((e,i) => e.classList.toggle('on', i < s.dataset.v)));
        s.addEventListener('click',     () => { rvInput.value = s.dataset.v; rvStars.forEach((e,i) => e.classList.toggle('on', i < s.dataset.v)); });
    });
    document.getElementById('rvStars').addEventListener('mouseleave', () => {
        const v = parseInt(rvInput.value);
        rvStars.forEach((e,i) => e.classList.toggle('on', i < v));
    });
}

// ── Buy ──
function doBuy() {
    const btn = document.getElementById('buyBtn');
    btn.disabled = true;
    btn.innerHTML = '<span style="opacity:.55">Processing…</span>';
    fetch('<?= base_url('api/marketplace-buy.php') ?>', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ listing_id: <?= $id ?> })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            showToast('Purchase complete! −' + Number(d.price).toLocaleString() + ' HC', 'ok');
            setTimeout(() => location.reload(), 1800);
        } else {
            showToast(d.error || 'Purchase failed', 'err');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-bag-fill"></i> Buy Now <span style="opacity:.7;font-size:.85rem;font-weight:600;">— <?= number_format((int)$item['price']) ?> HC</span>';
        }
    })
    .catch(() => { showToast('Network error', 'err'); btn.disabled = false; });
}

// ── Toast ──
let _tt;
function showToast(msg, type) {
    const el = document.getElementById('toast');
    el.textContent = msg; el.className = 'toast show ' + (type||'');
    clearTimeout(_tt); _tt = setTimeout(() => el.classList.remove('show'), 3200);
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
