<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$current = current_user($pdo);

// ── Handle POST actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $current) {
    $action = $_POST['action'] ?? '';

    if ($action === 'post') {
        $content = trim($_POST['content'] ?? '');
        $img_url = null;
        if (!empty($_FILES['image']['tmp_name'])) {
            $file  = $_FILES['image'];
            $allow = ['image/jpeg','image/png','image/webp','image/gif'];
            if (in_array($file['type'], $allow) && $file['size'] <= 5 * 1024 * 1024) {
                $ext   = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
                $fname = 'post_' . $current['id'] . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
                $dest  = __DIR__ . '/uploads/posts/' . $fname;
                if (!is_dir(__DIR__ . '/uploads/posts')) @mkdir(__DIR__ . '/uploads/posts', 0755, true);
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $img_url = 'uploads/posts/' . $fname;
                }
            }
        }
        if ($content !== '' || $img_url) {
            $content = mb_substr($content, 0, 2000);
            $pdo->prepare("INSERT INTO feed_posts (account_id, content, image_url) VALUES (?, ?, ?)")
                ->execute([$current['id'], $content, $img_url]);
        }
        header('Location: ' . base_url('timeline.php'));
        exit;
    }

    if ($action === 'like') {
        $pid = (int)($_POST['post_id'] ?? 0);
        if ($pid) {
            $ck = $pdo->prepare("SELECT 1 FROM feed_post_likes WHERE post_id = ? AND account_id = ?");
            $ck->execute([$pid, $current['id']]);
            if ($ck->fetch()) {
                $pdo->prepare("DELETE FROM feed_post_likes WHERE post_id = ? AND account_id = ?")->execute([$pid, $current['id']]);
                $liked = false;
            } else {
                $pdo->prepare("INSERT INTO feed_post_likes (post_id, account_id) VALUES (?, ?)")->execute([$pid, $current['id']]);
                $liked = true;
            }
            $c = $pdo->prepare("SELECT COUNT(*) FROM feed_post_likes WHERE post_id = ?");
            $c->execute([$pid]);
            header('Content-Type: application/json');
            echo json_encode(['liked' => $liked, 'count' => (int)$c->fetchColumn()]);
            exit;
        }
    }

    if ($action === 'comment') {
        $pid = (int)($_POST['post_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        $content = mb_substr($content, 0, 500);
        if ($pid && $content !== '') {
            $pdo->prepare("INSERT INTO feed_post_comments (post_id, account_id, content) VALUES (?, ?, ?)")
                ->execute([$pid, $current['id'], $content]);
        }
        header('Location: ' . base_url('timeline.php') . '#post-' . $pid);
        exit;
    }

    if ($action === 'delete') {
        $pid = (int)($_POST['post_id'] ?? 0);
        if ($pid) {
            $own = $pdo->prepare("SELECT account_id FROM feed_posts WHERE id = ?");
            $own->execute([$pid]);
            $owner = (int)$own->fetchColumn();
            if ($owner === (int)$current['id']) {
                $pdo->prepare("DELETE FROM feed_post_comments WHERE post_id = ?")->execute([$pid]);
                $pdo->prepare("DELETE FROM feed_post_likes WHERE post_id = ?")->execute([$pid]);
                $pdo->prepare("DELETE FROM feed_posts WHERE id = ?")->execute([$pid]);
            }
        }
        header('Location: ' . base_url('timeline.php'));
        exit;
    }
}

$pageTitle       = 'Timeline — Apex Cybernet';
$pageDescription = 'The community feed for Apex Cybernet players.';
$canonicalUrl    = canonical_url('timeline.php');

// ── Load posts ──
$posts = [];
$stmt = $pdo->query("
    SELECT p.id, p.account_id, p.content, p.image_url, p.created_at,
           a.display_name, a.profile_picture, a.ref_code, a.ref_type, a.is_verified,
           (SELECT COUNT(*) FROM feed_post_likes l WHERE l.post_id = p.id) AS likes,
           (SELECT COUNT(*) FROM feed_post_comments c WHERE c.post_id = p.id) AS comments
    FROM feed_posts p
    JOIN accounts a ON a.id = p.account_id
    WHERE a.claim_status = 'approved'
    ORDER BY p.created_at DESC
    LIMIT 50
");
$posts = $stmt->fetchAll();

// Which posts has current user liked?
$my_likes = [];
if ($current && !empty($posts)) {
    $ids = array_column($posts, 'id');
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $st  = $pdo->prepare("SELECT post_id FROM feed_post_likes WHERE account_id = ? AND post_id IN ($ph)");
    $st->execute(array_merge([$current['id']], $ids));
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $pid) $my_likes[$pid] = true;
}

// Load comments for displayed posts
$comments_by_post = [];
if (!empty($posts)) {
    $ids = array_column($posts, 'id');
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $st  = $pdo->prepare("
        SELECT c.id, c.post_id, c.content, c.created_at,
               a.id AS author_id, a.display_name, a.profile_picture, a.is_verified
        FROM feed_post_comments c
        JOIN accounts a ON a.id = c.account_id
        WHERE c.post_id IN ($ph)
        ORDER BY c.created_at ASC
    ");
    $st->execute($ids);
    foreach ($st->fetchAll() as $c) {
        $comments_by_post[$c['post_id']][] = $c;
    }
}

function tl_time_ago(string $dt): string {
    $ts = strtotime($dt);
    $d  = time() - $ts;
    if ($d < 60)     return 'just now';
    if ($d < 3600)   return intdiv($d, 60) . 'm';
    if ($d < 86400)  return intdiv($d, 3600) . 'h';
    if ($d < 604800) return intdiv($d, 86400) . 'd';
    return date('M j, Y', $ts);
}

require_once __DIR__ . '/includes/header.php';

// ── Fetch pinned shortcuts (Apex Cybernet + HIDE OUT + current user's team if they have one) ──
$tl_shortcuts = [];
try {
    $sc = $pdo->prepare("SELECT id, display_name, profile_picture, ref_type, ref_code, is_verified FROM accounts WHERE display_name IN ('Apex Cybernet','HIDE OUT') AND claim_status='approved' ORDER BY CASE display_name WHEN 'Apex Cybernet' THEN 0 WHEN 'HIDE OUT' THEN 1 ELSE 2 END");
    $sc->execute();
    $tl_shortcuts = $sc->fetchAll();
} catch (Exception $e) {}

// ── Current user's team (for profile block flair) ──
$tl_my_team = '';
if ($current && ($current['ref_type'] ?? '') === 'team' && !empty($current['ref_code'])) {
    try {
        $tq = $pdo->prepare("SELECT team_name FROM teams WHERE ref_code = ?");
        $tq->execute([$current['ref_code']]);
        $tl_my_team = (string)$tq->fetchColumn();
    } catch (Exception $e) {}
}

$tl_hc_shown = true;
?>

<style>
/* ── Facebook-style 2-column layout ── */
.tl-layout { max-width: 1180px; margin: 0 auto; display: grid; grid-template-columns: 1fr; gap: 1.25rem; padding: 1.5rem 1rem 5rem; }
.tl-layout .tl { max-width: none; margin: 0; padding: 0; }

/* Left sidebar */
.tl-left { position: sticky; top: 1rem; align-self: start; display: flex; flex-direction: column; gap: 0.35rem; max-height: calc(100vh - 1.5rem); overflow-y: auto; padding-right: 0.25rem; }
.tl-left::-webkit-scrollbar { width: 4px; }
.tl-left::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.08); border-radius: 2px; }
.tl-left-item { display: flex; align-items: center; gap: 0.65rem; padding: 0.5rem 0.65rem; border-radius: 10px; color: var(--text); text-decoration: none; font-size: 0.88rem; font-weight: 600; line-height: 1.2; transition: background 0.15s; }
.tl-left-item:hover { background: rgba(255,255,255,0.05); color: var(--text); }
.tl-left-item.active { background: rgba(124,58,237,0.12); color: #c4b5fd; }
.tl-left-item .tl-left-ico { width: 32px; height: 32px; border-radius: 50%; background: rgba(124,58,237,0.15); display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 0.95rem; color: #a78bfa; }
.tl-left-item.profile .tl-left-ico { border-radius: 50%; overflow: hidden; background: linear-gradient(135deg,#7c3aed,#ec4899); color: #fff; font-weight: 900; }
.tl-left-item.profile .tl-left-ico img { width: 100%; height: 100%; object-fit: cover; }
.tl-left-item .tl-left-sub { font-size: 0.68rem; color: var(--text-muted); font-weight: 600; margin-top: 1px; }
.tl-left-item.profile { padding-top: 0.6rem; padding-bottom: 0.6rem; margin-bottom: 0.4rem; border-bottom: 1px solid rgba(255,255,255,0.06); border-radius: 0; padding-left: 0.65rem; padding-right: 0.65rem; }
.tl-left-item .tl-left-verified { color: #38bdf8; font-size: 0.7rem; margin-left: 0.2rem; }
.tl-left-sec { font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; padding: 0.85rem 0.65rem 0.35rem; margin-top: 0.3rem; border-top: 1px solid rgba(255,255,255,0.06); }
.tl-left-sec:first-of-type { border-top: none; margin-top: 0; padding-top: 0.35rem; }

.tl-left-footer { padding: 0.85rem 0.65rem 0; font-size: 0.7rem; color: var(--text-muted); line-height: 1.5; margin-top: 0.4rem; border-top: 1px solid rgba(255,255,255,0.06); }
.tl-left-footer a { color: var(--text-muted); text-decoration: none; }
.tl-left-footer a:hover { color: var(--accent-light); }
.tl-left-footer .dot { margin: 0 0.25rem; opacity: 0.5; }

@media (max-width: 960px) {
    .tl-layout { grid-template-columns: 1fr; padding: 1rem 0.5rem 5rem; }
    .tl-left { display: none; }
    /* Hide chat sidebar (docked right, launcher, open boxes) on mobile for the timeline */
    .cs-side, .cs-launcher, .cs-toggle, .cs-boxes { display: none !important; }
    body.cs-has-side { padding-right: 0 !important; }
}

.tl { max-width: 640px; margin: 0 auto; padding: 1.75rem 1rem 5rem; }

/* ── Sticky hero ── */
.tl-hero {
    background: linear-gradient(135deg, rgba(124,58,237,0.08) 0%, rgba(76,29,149,0.04) 100%);
    border: 1px solid rgba(124,58,237,0.2); border-radius: 18px;
    padding: 1.25rem 1.5rem; margin-bottom: 1.5rem;
    display: flex; align-items: center; gap: 1rem;
}
.tl-hero-ico { width: 48px; height: 48px; border-radius: 14px; background: linear-gradient(135deg,#7c3aed,#4c1d95); display:flex; align-items:center; justify-content:center; font-size: 1.35rem; color: #fff; box-shadow: 0 8px 24px rgba(124,58,237,0.3); flex-shrink: 0; }
.tl-hero-txt { flex: 1; min-width: 0; }
.tl-hero-title { font-size: 1.35rem; font-weight: 900; color: var(--text); letter-spacing: -0.5px; }
.tl-hero-sub { font-size: 0.8rem; color: var(--text-muted); margin-top: 0.15rem; }

/* ── Avatar (shared) ── */
.tl-av { width: 40px; height: 40px; border-radius: 50%; flex-shrink: 0; background: #2a2d34; display: flex; align-items: center; justify-content: center; color: #9ca3af; overflow: hidden; transition: transform 0.18s, box-shadow 0.18s; }
.tl-av:hover { transform: scale(1.05); box-shadow: 0 4px 16px rgba(124,58,237,0.3); }
.tl-av.team { border-radius: 12px; }
.tl-av img { width: 100%; height: 100%; object-fit: cover; }
.tl-av i.bi-person-fill { font-size: 1.55rem; line-height: 1; margin-bottom: -8px; }

/* ── Composer ── */
.tl-comp {
    background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px;
    padding: 1rem 1.1rem; margin-bottom: 1.25rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    transition: box-shadow 0.2s, border-color 0.2s;
}
.tl-comp:focus-within { border-color: rgba(124,58,237,0.35); box-shadow: 0 4px 20px rgba(124,58,237,0.08); }
.tl-comp-top { display: flex; gap: 0.85rem; align-items: flex-start; }
.tl-comp-ta {
    flex: 1; background: rgba(255,255,255,0.025); border: 1px solid var(--border); border-radius: 22px;
    color: var(--text); padding: 0.7rem 1.1rem; font-size: 0.95rem; font-family: inherit; outline: none;
    resize: none; min-height: 44px; max-height: 220px; transition: all 0.18s;
}
.tl-comp-ta::placeholder { color: var(--text-muted); }
.tl-comp-ta:focus { border-color: var(--accent); background: rgba(124,58,237,0.06); }
.tl-comp-actions { display: flex; align-items: center; gap: 0.4rem; margin-top: 0.85rem; padding-top: 0.85rem; border-top: 1px solid var(--border); }
.tl-comp-tool { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.5rem 0.85rem; background: transparent; border: none; border-radius: 9px; color: var(--text-muted); font-size: 0.82rem; font-weight: 700; cursor: pointer; font-family: inherit; transition: all 0.15s; }
.tl-comp-tool:hover { background: rgba(34,197,94,0.08); color: #34d399; }
.tl-comp-tool i { font-size: 1.1rem; }
.tl-comp-post-btn {
    margin-left: auto; padding: 0.5rem 1.35rem; border: none; border-radius: 10px;
    background: linear-gradient(135deg, #7c3aed, #6d28d9); color: #fff;
    font-size: 0.88rem; font-weight: 800; cursor: pointer; font-family: inherit;
    box-shadow: 0 4px 14px rgba(124,58,237,0.35);
    transition: all 0.18s;
}
.tl-comp-post-btn:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(124,58,237,0.5); }
.tl-comp-post-btn:disabled { background: rgba(124,58,237,0.2); color: rgba(255,255,255,0.4); box-shadow: none; cursor: not-allowed; }
.tl-comp-preview { margin-top: 0.85rem; position: relative; border-radius: 14px; overflow: hidden; display: none; animation: tlFadeIn 0.2s ease-out; }
.tl-comp-preview img { width: 100%; max-height: 360px; object-fit: cover; display: block; }
.tl-comp-preview button { position: absolute; top: 0.6rem; right: 0.6rem; width: 30px; height: 30px; border-radius: 50%; background: rgba(0,0,0,0.75); color: #fff; border: none; font-size: 1rem; cursor: pointer; backdrop-filter: blur(8px); transition: background 0.15s; }
.tl-comp-preview button:hover { background: rgba(239,68,68,0.9); }

/* ── Post ── */
.tl-post {
    background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px;
    margin-bottom: 1.1rem; overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    transition: transform 0.2s, box-shadow 0.2s;
    animation: tlFadeIn 0.3s ease-out;
}
.tl-post:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.35); }

.tl-post-head { display: flex; align-items: center; gap: 0.75rem; padding: 1rem 1.1rem 0.4rem; }
.tl-post-who { flex: 1; min-width: 0; }
.tl-post-name { font-size: 0.92rem; font-weight: 800; color: var(--text); text-decoration: none; display: inline-flex; align-items: center; gap: 0.3rem; }
.tl-post-name:hover { color: var(--accent-light); }
.tl-verified { color: #60a5fa; font-size: 0.9rem; filter: drop-shadow(0 0 4px rgba(96,165,250,0.55)); }
.tl-post-time { font-size: 0.72rem; color: var(--text-muted); margin-top: 2px; display: flex; align-items: center; gap: 0.3rem; }
.tl-post-time i { font-size: 0.7rem; }

.tl-post-menu { position: relative; }
.tl-post-menu-btn { width: 34px; height: 34px; display: flex; align-items: center; justify-content: center; background: transparent; border: none; border-radius: 50%; color: var(--text-muted); font-size: 1.15rem; cursor: pointer; transition: background 0.15s, color 0.15s; }
.tl-post-menu-btn:hover { background: rgba(255,255,255,0.06); color: var(--text); }
.tl-post-menu-drop { position: absolute; top: calc(100% + 4px); right: 0; background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 0.4rem; min-width: 160px; display: none; z-index: 10; box-shadow: 0 10px 32px rgba(0,0,0,0.5); animation: tlFadeIn 0.15s; }
.tl-post-menu-drop.on { display: block; }
.tl-post-menu-drop button { width: 100%; padding: 0.6rem 0.9rem; background: transparent; border: none; color: #f87171; font-size: 0.84rem; font-weight: 600; font-family: inherit; cursor: pointer; text-align: left; border-radius: 8px; display: flex; align-items: center; gap: 0.5rem; transition: background 0.12s; }
.tl-post-menu-drop button:hover { background: rgba(239,68,68,0.1); }

.tl-post-body { padding: 0.2rem 1.1rem 0.85rem; }
.tl-post-text { font-size: 0.95rem; color: var(--text); line-height: 1.6; white-space: pre-wrap; word-wrap: break-word; }
.tl-post-img { width: 100%; max-height: 560px; object-fit: cover; display: block; }

/* ── Stats (likes/comments) ── */
.tl-stats { display: flex; align-items: center; gap: 0.85rem; padding: 0.6rem 1.1rem; font-size: 0.78rem; color: var(--text-muted); }
.tl-stats-likes { display: flex; align-items: center; gap: 0.4rem; cursor: default; }
.tl-stats-ico { width: 20px; height: 20px; border-radius: 50%; background: linear-gradient(135deg, #7c3aed, #6d28d9); display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: 0.65rem; box-shadow: 0 2px 6px rgba(124,58,237,0.4); }

/* ── Action row ── */
.tl-actions { display: flex; border-top: 1px solid var(--border); padding: 0.25rem 0.5rem; }
.tl-action {
    flex: 1; padding: 0.6rem; background: transparent; border: none;
    color: var(--text-muted); font-size: 0.85rem; font-weight: 700; cursor: pointer; font-family: inherit;
    display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem;
    transition: all 0.15s; text-decoration: none; border-radius: 8px;
}
.tl-action:hover { background: rgba(255,255,255,0.04); color: var(--text); }
.tl-action i { font-size: 1.05rem; transition: transform 0.2s; }
.tl-action:hover i { transform: scale(1.1); }
.tl-action.liked { color: var(--accent-light); }
.tl-action.liked i { color: var(--accent-light); animation: tlPop 0.3s ease-out; }
@keyframes tlPop {
    0%   { transform: scale(1); }
    40%  { transform: scale(1.35); }
    100% { transform: scale(1); }
}

/* ── Comments ── */
.tl-cmts { padding: 0.75rem 1.1rem 0.9rem; border-top: 1px solid var(--border); background: rgba(255,255,255,0.015); display: none; }
.tl-cmts.open { display: block; animation: tlFadeIn 0.25s ease-out; }
.tl-cmt { display: flex; gap: 0.6rem; margin-bottom: 0.7rem; }
.tl-cmt:last-of-type { margin-bottom: 0; }
.tl-cmt-av { width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0; background: #2a2d34; display: flex; align-items: center; justify-content: center; color: #9ca3af; overflow: hidden; }
.tl-cmt-av img { width: 100%; height: 100%; object-fit: cover; }
.tl-cmt-av i.bi-person-fill { font-size: 1.2rem; line-height: 1; margin-bottom: -6px; }
.tl-cmt-body { flex: 1; min-width: 0; }
.tl-cmt-bubble { background: rgba(255,255,255,0.045); border-radius: 16px; padding: 0.55rem 0.9rem; display: inline-block; max-width: 100%; }
.tl-cmt-name { font-size: 0.78rem; font-weight: 800; color: var(--text); margin-bottom: 0.15rem; text-decoration: none; display: inline-flex; align-items: center; gap: 0.25rem; }
.tl-cmt-name:hover { color: var(--accent-light); }
.tl-cmt-text { font-size: 0.85rem; color: var(--text); line-height: 1.45; white-space: pre-wrap; word-wrap: break-word; }
.tl-cmt-time { font-size: 0.68rem; color: var(--text-muted); margin-top: 0.25rem; padding-left: 0.9rem; }

/* ── Comment composer ── */
.tl-cmt-form { display: flex; gap: 0.55rem; align-items: center; margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px dashed rgba(255,255,255,0.06); }
.tl-cmt-inp { flex: 1; background: rgba(255,255,255,0.04); border: 1px solid var(--border); border-radius: 20px; padding: 0.55rem 0.95rem; color: var(--text); font-size: 0.85rem; font-family: inherit; outline: none; transition: all 0.15s; }
.tl-cmt-inp:focus { border-color: var(--accent); background: rgba(124,58,237,0.06); }
.tl-cmt-submit { background: transparent; border: none; color: var(--accent-light); font-size: 1.2rem; cursor: pointer; padding: 0.3rem 0.5rem; border-radius: 50%; transition: background 0.15s; }
.tl-cmt-submit:hover { background: rgba(124,58,237,0.12); }

/* ── Empty ── */
.tl-empty { text-align: center; padding: 4rem 1.5rem; color: var(--text-muted); background: var(--bg-card); border: 1px dashed var(--border); border-radius: 16px; }
.tl-empty i { font-size: 3rem; display: block; margin-bottom: 1rem; color: rgba(139,92,246,0.35); }
.tl-empty h4 { font-size: 1rem; font-weight: 800; color: var(--text); margin-bottom: 0.35rem; }
.tl-empty p { font-size: 0.85rem; }

/* ── Login prompt ── */
.tl-loginprompt {
    display: flex; align-items: center; gap: 0.85rem;
    background: linear-gradient(135deg, rgba(124,58,237,0.08), rgba(76,29,149,0.04));
    border: 1px solid rgba(124,58,237,0.22); border-radius: 16px;
    padding: 1.1rem 1.25rem; margin-bottom: 1.25rem;
}
.tl-loginprompt-ico { width: 42px; height: 42px; border-radius: 12px; background: rgba(124,58,237,0.15); color: var(--accent-light); display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0; }
.tl-loginprompt-txt { flex: 1; font-size: 0.88rem; color: var(--text); font-weight: 600; }
.tl-loginprompt-txt span { display: block; font-size: 0.75rem; color: var(--text-muted); font-weight: 500; margin-top: 2px; }
.tl-loginprompt a { background: linear-gradient(135deg, #7c3aed, #6d28d9); color: #fff; padding: 0.5rem 1.1rem; border-radius: 10px; font-size: 0.82rem; font-weight: 800; text-decoration: none; white-space: nowrap; box-shadow: 0 4px 14px rgba(124,58,237,0.35); transition: all 0.18s; }
.tl-loginprompt a:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(124,58,237,0.5); }

@keyframes tlFadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to   { opacity: 1; transform: translateY(0); }
}

@media (max-width: 500px) {
    .tl { padding: 1rem 0.5rem 5rem; }
    .tl-comp, .tl-post, .tl-hero, .tl-loginprompt { border-radius: 14px; }
    .tl-hero { padding: 1rem 1.1rem; }
    .tl-hero-title { font-size: 1.15rem; }
    .tl-hero-ico { width: 40px; height: 40px; font-size: 1.15rem; }
    .tl-post-body, .tl-post-head, .tl-stats, .tl-comp { padding-left: 0.9rem; padding-right: 0.9rem; }
    .tl-cmts { padding-left: 0.9rem; padding-right: 0.9rem; }
}
</style>

<div class="tl-layout">

    <!-- ── Facebook-style left sidebar ── -->

    <div class="tl">

    <div class="tl-hero">
        <div class="tl-hero-ico"><i class="bi bi-activity"></i></div>
        <div class="tl-hero-txt">
            <div class="tl-hero-title">Timeline</div>
            <div class="tl-hero-sub">Share updates, hype, and highlights with the Apex Cybernet community.</div>
        </div>
    </div>

    <?php if ($current): ?>
    <!-- ── Composer ── -->
    <form class="tl-comp" method="POST" enctype="multipart/form-data" id="tlCompForm">
        <input type="hidden" name="action" value="post">
        <div class="tl-comp-top">
            <div class="tl-av <?= $current['ref_type'] === 'team' ? 'team' : '' ?>">
                <?php if (!empty($current['profile_picture'])): ?>
                    <img src="<?= base_url($current['profile_picture']) ?>" alt="">
                <?php else: ?>
                    <i class="bi bi-person-fill"></i>
                <?php endif; ?>
            </div>
            <textarea class="tl-comp-ta" name="content" id="tlCompTa" placeholder="What's on your mind, <?= htmlspecialchars(explode(' ', $current['display_name'] ?: 'Player')[0]) ?>?" oninput="autosize(this);checkPost()"></textarea>
        </div>
        <div class="tl-comp-preview" id="tlCompPrev">
            <img id="tlCompImg" src="">
            <button type="button" onclick="clearImg()"><i class="bi bi-x"></i></button>
        </div>
        <div class="tl-comp-actions">
            <label class="tl-comp-tool">
                <i class="bi bi-image-fill" style="color:#34d399;"></i> Photo
                <input type="file" name="image" accept="image/*" style="display:none;" onchange="previewImg(this)">
            </label>
            <button type="submit" class="tl-comp-post-btn" id="tlPostBtn" disabled><i class="bi bi-send-fill" style="margin-right:0.3rem;"></i>Post</button>
        </div>
    </form>
    <?php else: ?>
    <div class="tl-loginprompt">
        <div class="tl-loginprompt-ico"><i class="bi bi-person-fill-lock"></i></div>
        <div class="tl-loginprompt-txt">Join the conversation<span>Log in to post, like, and comment.</span></div>
        <a href="<?= base_url('login.php') ?>">Log in</a>
    </div>
    <?php endif; ?>

    <?php if (empty($posts)): ?>
    <div class="tl-empty">
        <i class="bi bi-chat-square-dots"></i>
        <h4>No posts yet</h4>
        <p>Be the first to share something with the Apex Cybernet community!</p>
    </div>
    <?php endif; ?>

    <?php foreach ($posts as $p):
        $initials = strtoupper(substr($p['display_name'] ?: 'U', 0, 2));
        $liked    = isset($my_likes[$p['id']]);
        $is_own   = $current && (int)$current['id'] === (int)$p['account_id'];
    ?>
    <div class="tl-post" id="post-<?= $p['id'] ?>">
        <div class="tl-post-head">
            <a href="<?= base_url('profile.php?id=' . $p['account_id']) ?>" style="text-decoration:none;">
                <div class="tl-av <?= $p['ref_type'] === 'team' ? 'team' : '' ?>">
                    <?php if (!empty($p['profile_picture'])): ?>
                        <img src="<?= base_url($p['profile_picture']) ?>" alt="">
                    <?php else: ?>
                        <i class="bi bi-person-fill"></i>
                    <?php endif; ?>
                </div>
            </a>
            <div class="tl-post-who">
                <a href="<?= base_url('profile.php?id=' . $p['account_id']) ?>" class="tl-post-name">
                    <?= htmlspecialchars($p['display_name']) ?><?php if (!empty($p['is_verified'])): ?><i class="bi bi-patch-check-fill tl-verified" title="Verified"></i><?php endif; ?>
                </a>
                <div class="tl-post-time"><i class="bi bi-clock"></i> <?= tl_time_ago($p['created_at']) ?></div>
            </div>
            <?php if ($is_own): ?>
            <div class="tl-post-menu">
                <button class="tl-post-menu-btn" onclick="toggleMenu(<?= $p['id'] ?>)"><i class="bi bi-three-dots"></i></button>
                <div class="tl-post-menu-drop" id="menu-<?= $p['id'] ?>">
                    <form method="POST" onsubmit="return confirm('Delete this post?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="post_id" value="<?= $p['id'] ?>">
                        <button type="submit"><i class="bi bi-trash-fill"></i> Delete post</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($p['content'])): ?>
        <div class="tl-post-body">
            <div class="tl-post-text"><?= htmlspecialchars($p['content']) ?></div>
        </div>
        <?php endif; ?>

        <?php if (!empty($p['image_url'])): ?>
        <img src="<?= base_url($p['image_url']) ?>" class="tl-post-img" alt="">
        <?php endif; ?>

        <?php if ($p['likes'] > 0 || $p['comments'] > 0): ?>
        <div class="tl-stats">
            <?php if ($p['likes'] > 0): ?>
            <span class="tl-stats-likes"><span class="tl-stats-ico"><i class="bi bi-hand-thumbs-up-fill"></i></span> <span id="lc-<?= $p['id'] ?>"><?= $p['likes'] ?></span></span>
            <?php endif; ?>
            <?php if ($p['comments'] > 0): ?>
            <span style="margin-left:auto;"><?= $p['comments'] ?> comment<?= $p['comments'] !== 1 ? 's' : '' ?></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="tl-actions">
            <?php if ($current): ?>
            <button type="button" class="tl-action<?= $liked ? ' liked' : '' ?>" id="like-<?= $p['id'] ?>" onclick="toggleLike(<?= $p['id'] ?>)">
                <i class="bi bi-hand-thumbs-up<?= $liked ? '-fill' : '' ?>"></i> Like
            </button>
            <?php else: ?>
            <a href="<?= base_url('login.php') ?>" class="tl-action"><i class="bi bi-hand-thumbs-up"></i> Like</a>
            <?php endif; ?>
            <button type="button" class="tl-action" onclick="toggleCmts(<?= $p['id'] ?>)"><i class="bi bi-chat"></i> Comment</button>
        </div>

        <div class="tl-cmts<?= !empty($comments_by_post[$p['id']]) ? ' open' : '' ?>" id="cmts-<?= $p['id'] ?>">
            <?php foreach (($comments_by_post[$p['id']] ?? []) as $c):
                $c_init = strtoupper(substr($c['display_name'] ?: 'U', 0, 2));
            ?>
            <div class="tl-cmt">
                <a href="<?= base_url('profile.php?id=' . $c['author_id']) ?>" style="text-decoration:none;">
                    <div class="tl-cmt-av">
                        <?php if (!empty($c['profile_picture'])): ?>
                            <img src="<?= base_url($c['profile_picture']) ?>" alt="">
                        <?php else: ?>
                            <i class="bi bi-person-fill"></i>
                        <?php endif; ?>
                    </div>
                </a>
                <div class="tl-cmt-body">
                    <div class="tl-cmt-bubble">
                        <a href="<?= base_url('profile.php?id=' . $c['author_id']) ?>" class="tl-cmt-name">
                            <?= htmlspecialchars($c['display_name']) ?><?php if (!empty($c['is_verified'])): ?><i class="bi bi-patch-check-fill tl-verified" style="font-size:0.7rem;" title="Verified"></i><?php endif; ?>
                        </a>
                        <div class="tl-cmt-text"><?= htmlspecialchars($c['content']) ?></div>
                    </div>
                    <div class="tl-cmt-time"><?= tl_time_ago($c['created_at']) ?></div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if ($current): ?>
            <form method="POST" class="tl-cmt-form">
                <input type="hidden" name="action" value="comment">
                <input type="hidden" name="post_id" value="<?= $p['id'] ?>">
                <div class="tl-cmt-av">
                    <?php if (!empty($current['profile_picture'])): ?>
                        <img src="<?= base_url($current['profile_picture']) ?>" alt="">
                    <?php else: ?>
                        <i class="bi bi-person-fill"></i>
                    <?php endif; ?>
                </div>
                <input type="text" name="content" class="tl-cmt-inp" placeholder="Write a comment…" maxlength="500" required>
                <button type="submit" class="tl-cmt-submit"><i class="bi bi-send-fill"></i></button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    </div><!-- /.tl -->
</div><!-- /.tl-layout -->

<script>
function autosize(ta) {
    ta.style.height = 'auto';
    ta.style.height = Math.min(ta.scrollHeight, 180) + 'px';
}
function checkPost() {
    var ta  = document.getElementById('tlCompTa');
    var btn = document.getElementById('tlPostBtn');
    var prev = document.getElementById('tlCompPrev');
    if (!btn) return;
    btn.disabled = !(ta.value.trim() || prev.style.display === 'block');
}
function previewImg(input) {
    if (!input.files || !input.files[0]) return;
    var r = new FileReader();
    r.onload = function(e) {
        document.getElementById('tlCompImg').src = e.target.result;
        document.getElementById('tlCompPrev').style.display = 'block';
        checkPost();
    };
    r.readAsDataURL(input.files[0]);
}
function clearImg() {
    document.querySelector('input[name="image"]').value = '';
    document.getElementById('tlCompPrev').style.display = 'none';
    document.getElementById('tlCompImg').src = '';
    checkPost();
}
function toggleCmts(id) {
    document.getElementById('cmts-' + id).classList.toggle('open');
}
function toggleMenu(id) {
    document.querySelectorAll('.tl-post-menu-drop').forEach(function(d) {
        if (d.id !== 'menu-' + id) d.classList.remove('on');
    });
    document.getElementById('menu-' + id).classList.toggle('on');
}
document.addEventListener('click', function(e) {
    if (!e.target.closest('.tl-post-menu')) {
        document.querySelectorAll('.tl-post-menu-drop').forEach(function(d) { d.classList.remove('on'); });
    }
});
function toggleLike(pid) {
    var fd = new FormData();
    fd.append('action', 'like');
    fd.append('post_id', pid);
    fetch('<?= base_url('timeline.php') ?>', { method: 'POST', body: fd, credentials: 'include' })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            var btn  = document.getElementById('like-' + pid);
            var lc   = document.getElementById('lc-' + pid);
            if (d.liked) {
                btn.classList.add('liked');
                btn.querySelector('i').className = 'bi bi-hand-thumbs-up-fill';
            } else {
                btn.classList.remove('liked');
                btn.querySelector('i').className = 'bi bi-hand-thumbs-up';
            }
            if (lc) lc.textContent = d.count;
        })
        .catch(function() {});
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
