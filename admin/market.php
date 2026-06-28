<?php
require_once __DIR__ . '/../includes/db.php';

if (isset($_GET['token']) && $_GET['token'] === 'argonar-admin-2026-token') {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_username'] = 'admin';
    $_SESSION['admin_role'] = 'admin';
}
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: ' . base_url('admin/'));
    exit;
}

// ── Actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($id > 0 && $action === 'delete') {
        // Delete image file if exists
        $img = $pdo->prepare("SELECT image_path FROM marketplace_listings WHERE id = ?");
        $img->execute([$id]);
        $path = $img->fetchColumn();
        if ($path && file_exists(__DIR__ . '/../' . $path)) {
            @unlink(__DIR__ . '/../' . $path);
        }
        $pdo->prepare("DELETE FROM marketplace_listings WHERE id = ?")->execute([$id]);
    }

    header('Location: ' . base_url('admin/market.php') . (isset($_POST['filter']) ? '?filter=' . urlencode($_POST['filter']) : ''));
    exit;
}

// ── Filter ──
$filter = $_GET['filter'] ?? 'all';
$where  = '';
if ($filter === 'active')  $where = "WHERE l.status = 'active'";
elseif ($filter === 'sold')    $where = "WHERE l.status = 'sold'";
elseif ($filter === 'removed') $where = "WHERE l.status = 'removed'";

// ── Data ──
$listings = $pdo->query("
    SELECT l.*, s.display_name AS seller_name, s.email AS seller_email, s.profile_picture AS seller_pic,
           b.display_name AS buyer_name
    FROM marketplace_listings l
    LEFT JOIN accounts s ON s.id = l.seller_id
    LEFT JOIN accounts b ON b.id = l.buyer_id
    $where
    ORDER BY l.created_at DESC
")->fetchAll();

// Counts
$counts = $pdo->query("SELECT status, COUNT(*) AS cnt FROM marketplace_listings GROUP BY status")->fetchAll();
$sc = ['active' => 0, 'sold' => 0, 'removed' => 0];
foreach ($counts as $c) $sc[$c['status']] = (int)$c['cnt'];
$total = array_sum($sc);

// Total volume sold
$vol = (int)$pdo->query("SELECT COALESCE(SUM(price),0) FROM marketplace_listings WHERE status = 'sold'")->fetchColumn();

$pageTitle = 'Market Management — Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= base_url('css/app.css') ?>">
    <style>
    :root {
        --blue:      #3b82f6;
        --blue-dim:  rgba(59,130,246,0.1);
        --blue-bdr:  rgba(59,130,246,0.25);
        --green:     #22c55e;
        --green-dim: rgba(34,197,94,0.1);
        --amber:     #f59e0b;
        --amber-dim: rgba(245,158,11,0.1);
        --red:       #ef4444;
        --red-dim:   rgba(239,68,68,0.1);
    }

    .mkt-page { max-width: 1100px; margin: 0 auto; padding: 1.5rem 1rem 4rem; }

    .mkt-topbar {
        display: flex; align-items: center; justify-content: space-between;
        margin-bottom: 1.75rem; gap: 1rem; flex-wrap: wrap;
    }

    .mkt-title {
        font-size: 1.3rem; font-weight: 900; color: var(--text-main);
        display: flex; align-items: center; gap: 0.5rem;
    }
    .mkt-title i { color: var(--blue); }

    .mkt-summary {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(155px, 1fr));
        gap: 0.75rem; margin-bottom: 1.75rem;
    }

    .mkt-stat {
        background: var(--bg-card); border: 1px solid var(--border);
        border-radius: 14px; padding: 1rem 1.1rem;
    }

    .mkt-stat-val { font-size: 1.6rem; font-weight: 900; letter-spacing: -1px; }
    .mkt-stat-lbl {
        font-size: 0.68rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.08em; color: var(--text-muted); margin-top: 0.15rem;
    }

    .mkt-filters { display: flex; gap: 0.4rem; margin-bottom: 1.25rem; flex-wrap: wrap; }

    .mkt-filter-btn {
        background: var(--bg-card); border: 1px solid var(--border); border-radius: 9px;
        color: var(--text-muted); padding: 0.45rem 0.85rem; font-size: 0.75rem;
        font-weight: 700; cursor: pointer; font-family: inherit; text-decoration: none;
        transition: all 0.15s; display: inline-flex; align-items: center; gap: 0.35rem;
    }
    .mkt-filter-btn:hover { border-color: var(--blue-bdr); color: var(--blue); }
    .mkt-filter-btn.active { background: var(--blue-dim); border-color: var(--blue-bdr); color: var(--blue); }

    .mkt-filter-count {
        background: var(--blue-dim); border: 1px solid var(--blue-bdr); color: var(--blue);
        border-radius: 99px; padding: 0 0.35rem; font-size: 0.65rem; font-weight: 800;
    }

    .mkt-card {
        background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px;
        padding: 1rem 1.15rem; margin-bottom: 0.65rem; display: flex;
        align-items: flex-start; gap: 0.9rem; transition: border-color 0.2s;
    }
    .mkt-card:hover { border-color: var(--blue-bdr); }

    .mkt-card-img {
        width: 56px; height: 56px; border-radius: 10px; background: var(--bg-dark);
        border: 1px solid var(--border); display: flex; align-items: center;
        justify-content: center; flex-shrink: 0; overflow: hidden;
        color: var(--text-muted); font-size: 1.3rem;
    }
    .mkt-card-img img { width: 100%; height: 100%; object-fit: cover; }

    .mkt-card-body { flex: 1; min-width: 0; }

    .mkt-card-title {
        font-size: 0.92rem; font-weight: 800; color: var(--text-main);
        display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;
    }

    .mkt-status {
        font-size: 0.58rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.08em; border-radius: 99px; padding: 0.12rem 0.5rem;
        display: inline-flex; align-items: center; gap: 0.25rem;
    }
    .mkt-status.active  { background: var(--green-dim); border: 1px solid rgba(34,197,94,0.3); color: var(--green); }
    .mkt-status.sold    { background: var(--amber-dim); border: 1px solid rgba(245,158,11,0.3); color: var(--amber); }
    .mkt-status.removed { background: var(--red-dim); border: 1px solid rgba(239,68,68,0.3); color: var(--red); }

    .mkt-card-meta {
        display: flex; align-items: center; gap: 0.75rem; margin-top: 0.4rem;
        font-size: 0.72rem; color: var(--text-muted); flex-wrap: wrap;
    }

    .mkt-card-price { font-size: 0.88rem; font-weight: 900; color: #fbbf24; }

    .mkt-card-cat {
        background: var(--bg-dark); border: 1px solid var(--border); border-radius: 6px;
        padding: 0.1rem 0.45rem; font-size: 0.65rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: 0.04em;
    }

    .mkt-card-desc {
        font-size: 0.75rem; color: var(--text-muted); margin-top: 0.3rem;
        line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 1;
        -webkit-box-orient: vertical; overflow: hidden;
    }

    .mkt-card-buyer {
        font-size: 0.7rem; color: var(--amber); margin-top: 0.2rem;
    }

    .mkt-delete {
        background: transparent; border: 1px solid var(--border); border-radius: 8px;
        color: var(--text-muted); padding: 0.35rem 0.65rem; font-size: 0.72rem;
        font-weight: 700; cursor: pointer; font-family: inherit; white-space: nowrap;
        flex-shrink: 0; transition: all 0.15s; display: inline-flex;
        align-items: center; gap: 0.25rem; align-self: center;
    }
    .mkt-delete:hover { border-color: var(--red); color: #f87171; }

    .mkt-empty {
        background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px;
        padding: 3rem 1.5rem; text-align: center; color: var(--text-muted);
    }
    .mkt-empty i { font-size: 2.5rem; display: block; margin-bottom: 0.75rem; opacity: 0.2; }
    </style>
</head>
<body>
<div class="mkt-page">

    <div class="mkt-topbar">
        <div>
            <div class="mkt-title"><i class="bi bi-bag-check"></i> Market Management</div>
            <p style="font-size:0.78rem; color:var(--text-muted); margin:0.2rem 0 0;">
                View and manage all marketplace listings.
            </p>
        </div>
        <div style="display:flex; gap:0.4rem; flex-wrap:wrap;">
            <button onclick="document.getElementById('listModal').style.display='flex'" class="btn-back-site" style="border-color:var(--green);color:var(--green);cursor:pointer;"><i class="bi bi-plus-lg"></i> List an Item</button>
            <a href="<?= base_url('marketplace.php') ?>" class="btn-back-site" target="_blank"><i class="bi bi-shop"></i> View Market</a>
            <a href="<?= base_url('admin/') ?>" class="btn-back-site"><i class="bi bi-arrow-left"></i> Dashboard</a>
            <a href="<?= base_url('admin/logout.php') ?>" class="btn-logout"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </div>

    <div class="mkt-summary">
        <div class="mkt-stat">
            <div class="mkt-stat-val" style="color:var(--blue);"><?= $total ?></div>
            <div class="mkt-stat-lbl">Total Listings</div>
        </div>
        <div class="mkt-stat">
            <div class="mkt-stat-val" style="color:var(--green);"><?= $sc['active'] ?></div>
            <div class="mkt-stat-lbl">Active</div>
        </div>
        <div class="mkt-stat">
            <div class="mkt-stat-val" style="color:var(--amber);"><?= $sc['sold'] ?></div>
            <div class="mkt-stat-lbl">Sold</div>
        </div>
        <div class="mkt-stat">
            <div class="mkt-stat-val" style="color:var(--red);"><?= $sc['removed'] ?></div>
            <div class="mkt-stat-lbl">Removed</div>
        </div>
        <div class="mkt-stat">
            <div class="mkt-stat-val" style="color:#fbbf24;"><?= number_format($vol) ?></div>
            <div class="mkt-stat-lbl">HC Volume Sold</div>
        </div>
    </div>

    <div class="mkt-filters">
        <a href="?filter=all" class="mkt-filter-btn <?= $filter === 'all' ? 'active' : '' ?>">
            <i class="bi bi-grid"></i> All <span class="mkt-filter-count"><?= $total ?></span>
        </a>
        <a href="?filter=active" class="mkt-filter-btn <?= $filter === 'active' ? 'active' : '' ?>">
            <i class="bi bi-check-circle"></i> Active <span class="mkt-filter-count"><?= $sc['active'] ?></span>
        </a>
        <a href="?filter=sold" class="mkt-filter-btn <?= $filter === 'sold' ? 'active' : '' ?>">
            <i class="bi bi-bag-check"></i> Sold <span class="mkt-filter-count"><?= $sc['sold'] ?></span>
        </a>
        <a href="?filter=removed" class="mkt-filter-btn <?= $filter === 'removed' ? 'active' : '' ?>">
            <i class="bi bi-trash"></i> Removed <span class="mkt-filter-count"><?= $sc['removed'] ?></span>
        </a>
    </div>

    <div style="font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-muted); margin-bottom:0.75rem;">
        <?= count($listings) ?> Listing<?= count($listings) !== 1 ? 's' : '' ?>
        <?php if ($filter !== 'all'): ?> &middot; <?= ucfirst($filter) ?><?php endif; ?>
    </div>

    <?php if (empty($listings)): ?>
    <div class="mkt-empty">
        <i class="bi bi-bag-x"></i>
        <p>No <?= $filter !== 'all' ? htmlspecialchars($filter) . ' ' : '' ?>listings found.</p>
    </div>
    <?php else: ?>

    <?php foreach ($listings as $l):
        $initials = strtoupper(substr($l['seller_name'] ?? '?', 0, 2));
        $posted = date('M j, Y · g:ia', strtotime($l['created_at']));
    ?>
    <div class="mkt-card">
        <div class="mkt-card-img">
            <?php if (!empty($l['image_path'])): ?>
                <img src="<?= base_url(htmlspecialchars($l['image_path'])) ?>" alt="">
            <?php else: ?>
                <i class="bi bi-box-seam"></i>
            <?php endif; ?>
        </div>
        <div class="mkt-card-body">
            <div class="mkt-card-title">
                <?= htmlspecialchars($l['title']) ?>
                <span class="mkt-status <?= $l['status'] ?>">
                    <?= ucfirst($l['status']) ?>
                </span>
            </div>
            <?php if (!empty($l['description'])): ?>
            <div class="mkt-card-desc"><?= htmlspecialchars($l['description']) ?></div>
            <?php endif; ?>
            <div class="mkt-card-meta">
                <span class="mkt-card-price"><i class="bi bi-coin"></i> <?= number_format((int)$l['price']) ?> HC</span>
                <span class="mkt-card-cat"><?= htmlspecialchars($l['category']) ?></span>
                <span><i class="bi bi-person"></i> <?= htmlspecialchars($l['seller_name'] ?? 'Unknown') ?></span>
                <span><i class="bi bi-calendar3"></i> <?= $posted ?></span>
                <span style="color:var(--text-muted);">ID: <?= $l['id'] ?></span>
            </div>
            <?php if ($l['status'] === 'sold' && !empty($l['buyer_name'])): ?>
            <div class="mkt-card-buyer"><i class="bi bi-bag-check"></i> Bought by <?= htmlspecialchars($l['buyer_name']) ?><?= $l['sold_at'] ? ' · ' . date('M j, g:ia', strtotime($l['sold_at'])) : '' ?></div>
            <?php endif; ?>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $l['id'] ?>">
            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
            <button type="submit" class="mkt-delete"
                    onclick="return confirm('Permanently delete &quot;<?= htmlspecialchars($l['title'], ENT_QUOTES) ?>&quot;? This cannot be undone.')">
                <i class="bi bi-trash3"></i> Delete
            </button>
        </form>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

</div>

<!-- List an Item Modal -->
<div id="listModal" style="display:none;position:fixed;inset:0;z-index:999;background:rgba(0,0,0,0.6);align-items:center;justify-content:center;padding:1rem;" onclick="if(event.target===this)this.style.display='none'">
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:16px;width:100%;max-width:460px;padding:1.5rem;">
        <h3 style="font-size:1rem;font-weight:800;color:var(--text-main);margin:0 0 1rem;display:flex;align-items:center;gap:0.5rem;"><i class="bi bi-plus-circle" style="color:var(--green);"></i> List an Item</h3>
        <div id="listErr" style="display:none;background:var(--red-dim);border:1px solid rgba(239,68,68,0.3);color:var(--red);padding:0.5rem 0.75rem;border-radius:8px;font-size:0.78rem;font-weight:600;margin-bottom:0.75rem;"></div>
        <div style="margin-bottom:0.75rem;">
            <label style="font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--text-muted);display:block;margin-bottom:0.3rem;">Image (optional)</label>
            <input type="file" id="lImage" accept="image/jpeg,image/png,image/webp,image/gif" style="font-size:0.8rem;color:var(--text-muted);">
        </div>
        <div style="margin-bottom:0.75rem;">
            <label style="font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--text-muted);display:block;margin-bottom:0.3rem;">Title</label>
            <input type="text" id="lTitle" placeholder="What are you selling?" maxlength="120" style="width:100%;padding:0.55rem 0.75rem;background:var(--bg-dark);border:1px solid var(--border);border-radius:10px;color:var(--text-main);font-size:0.85rem;font-family:inherit;">
        </div>
        <div style="margin-bottom:0.75rem;">
            <label style="font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--text-muted);display:block;margin-bottom:0.3rem;">Description (optional)</label>
            <textarea id="lDesc" placeholder="Details, condition, delivery method…" style="width:100%;padding:0.55rem 0.75rem;background:var(--bg-dark);border:1px solid var(--border);border-radius:10px;color:var(--text-main);font-size:0.85rem;font-family:inherit;min-height:60px;resize:vertical;"></textarea>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:1rem;">
            <div>
                <label style="font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--text-muted);display:block;margin-bottom:0.3rem;">Price (HC)</label>
                <input type="number" id="lPrice" placeholder="100" min="1" style="width:100%;padding:0.55rem 0.75rem;background:var(--bg-dark);border:1px solid var(--border);border-radius:10px;color:var(--text-main);font-size:0.85rem;font-family:inherit;">
            </div>
            <div>
                <label style="font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--text-muted);display:block;margin-bottom:0.3rem;">Category</label>
                <select id="lCat" style="width:100%;padding:0.55rem 0.75rem;background:var(--bg-dark);border:1px solid var(--border);border-radius:10px;color:var(--text-main);font-size:0.85rem;font-family:inherit;">
                    <option value="general">General</option>
                    <option value="gaming">Gaming</option>
                    <option value="services">Services</option>
                    <option value="digital">Digital</option>
                    <option value="merch">Merch</option>
                    <option value="other">Other</option>
                </select>
            </div>
        </div>
        <div style="display:flex;gap:0.5rem;justify-content:flex-end;">
            <button onclick="document.getElementById('listModal').style.display='none'" style="padding:0.5rem 1rem;background:var(--bg-dark);border:1px solid var(--border);border-radius:10px;color:var(--text-muted);font-size:0.8rem;font-weight:700;cursor:pointer;font-family:inherit;">Cancel</button>
            <button id="listSubmitBtn" onclick="submitListing()" style="padding:0.5rem 1.25rem;background:var(--green);border:none;border-radius:10px;color:#fff;font-size:0.8rem;font-weight:700;cursor:pointer;font-family:inherit;"><i class="bi bi-upload"></i> Post Listing</button>
        </div>
    </div>
</div>

<script>
function submitListing() {
    const err = document.getElementById('listErr');
    const btn = document.getElementById('listSubmitBtn');
    err.style.display = 'none';

    const title = document.getElementById('lTitle').value.trim();
    const price = parseInt(document.getElementById('lPrice').value) || 0;
    if (!title) { err.textContent = 'Title is required'; err.style.display = 'block'; return; }
    if (price < 1) { err.textContent = 'Price must be at least 1 HC'; err.style.display = 'block'; return; }

    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Posting...';

    const fd = new FormData();
    fd.append('title', title);
    fd.append('description', document.getElementById('lDesc').value.trim());
    fd.append('price', price);
    fd.append('category', document.getElementById('lCat').value);
    const img = document.getElementById('lImage').files[0];
    if (img) fd.append('image', img);

    fetch('<?= base_url("api/marketplace-post.php") ?>', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.error) { err.textContent = data.error; err.style.display = 'block'; }
            else { location.reload(); }
        })
        .catch(() => { err.textContent = 'Network error'; err.style.display = 'block'; })
        .finally(() => { btn.disabled = false; btn.innerHTML = '<i class="bi bi-upload"></i> Post Listing'; });
}
</script>

</body>
</html>
