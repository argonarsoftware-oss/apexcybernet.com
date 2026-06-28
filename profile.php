<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bracket_logic.php';

$current = current_user($pdo);

// No id param → redirect to own profile if logged in
$account_id = (int)($_GET['id'] ?? 0);
if ($account_id <= 0 && $current) {
    $account_id = (int)$current['id'];
} elseif ($account_id <= 0) {
    header('Location: ' . base_url('login.php'));
    exit;
}

$is_own = $current && (int)$current['id'] === $account_id;

// Handle profile edit (own profile only)
$profile_saved = false;
$profile_errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile']) && $is_own) {
    $new_display = trim($_POST['display_name'] ?? '');
    $new_bio     = trim($_POST['bio']          ?? '');
    $new_contact = trim($_POST['contact_number'] ?? '');
    $new_gcash   = trim($_POST['gcash_number']   ?? '');

    if ($new_display === '') {
        $profile_errors[] = 'Username is required.';
    } elseif (strtolower(trim($new_display)) !== strtolower(trim($current['display_name'] ?? ''))) {
        // Case-insensitive + trimmed duplicate check so "kirfenia" and "Kirfenia"
        // cannot coexist (prevents username-impersonation attacks on HCoin sends).
        $dup = $pdo->prepare("SELECT 1 FROM accounts WHERE LOWER(TRIM(display_name)) = LOWER(TRIM(?)) AND id != ?");
        $dup->execute([$new_display, $account_id]);
        if ($dup->fetch()) $profile_errors[] = 'That username is already taken.';
    }
    if (strlen($new_bio) > 255) $new_bio = substr($new_bio, 0, 255);

    // Profile picture upload
    $new_pic = null;
    if (!empty($_FILES['profile_picture']['tmp_name'])) {
        $file  = $_FILES['profile_picture'];
        $allow = ['image/jpeg','image/png','image/webp','image/gif'];
        if (!in_array($file['type'], $allow)) {
            $profile_errors[] = 'Profile picture must be JPG, PNG, WebP, or GIF.';
        } elseif ($file['size'] > 3 * 1024 * 1024) {
            $profile_errors[] = 'Profile picture must be under 3 MB.';
        } else {
            $ext     = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
            $fname   = 'avatar_' . $account_id . '_' . time() . '.' . strtolower($ext);
            $dest    = __DIR__ . '/uploads/avatars/' . $fname;
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $new_pic = 'uploads/avatars/' . $fname;
            } else {
                $profile_errors[] = 'Failed to save profile picture.';
            }
        }
    }

    if (empty($profile_errors)) {
        if ($new_pic) {
            // Delete old picture if exists
            if (!empty($current['profile_picture'])) {
                $old = __DIR__ . '/' . $current['profile_picture'];
                if (file_exists($old)) @unlink($old);
            }
            $pdo->prepare("UPDATE accounts SET display_name = ?, bio = ?, contact_number = ?, gcash_number = ?, profile_picture = ? WHERE id = ?")
                ->execute([$new_display, $new_bio, $new_contact, $new_gcash, $new_pic, $account_id]);
        } else {
            $pdo->prepare("UPDATE accounts SET display_name = ?, bio = ?, contact_number = ?, gcash_number = ? WHERE id = ?")
                ->execute([$new_display, $new_bio, $new_contact, $new_gcash, $account_id]);
        }
        $profile_saved = true;
        // Reload
        $stmt = $pdo->prepare("SELECT * FROM accounts WHERE id = ?");
        $stmt->execute([$account_id]);
        $current = $stmt->fetch();
    }
}

$stmt = $pdo->prepare("SELECT * FROM accounts WHERE id = ? AND claim_status = 'approved'");
$stmt->execute([$account_id]);
$profile = $stmt->fetch();

if (!$profile) {
    header('Location: ' . base_url());
    exit;
}

$valid_games = [
    'valorant' => 'Valorant',
    'crossfire' => 'CrossFire',
    'dota2' => 'Dota 2',
];

// Load registration
$registration = null;
if ($profile['ref_type'] === 'team') {
    $stmt = $pdo->prepare("SELECT * FROM teams WHERE ref_code = ?");
    $stmt->execute([$profile['ref_code']]);
    $registration = $stmt->fetch();
    if (!$registration) {
        $cap = teams_captained_by($pdo, (int)$profile['id']);
        $registration = $cap[0] ?? null;
    }
} else {
    $stmt = $pdo->prepare("SELECT * FROM solo_players WHERE ref_code = ?");
    $stmt->execute([$profile['ref_code']]);
    $registration = $stmt->fetch();
}

$team_name = $profile['ref_type'] === 'team' ? ($registration['team_name'] ?? '') : ($registration['player_name'] ?? '');
$game = $registration['game'] ?? '';
$display_name = !empty($profile['display_name']) ? $profile['display_name'] : $team_name;

// Get match history
$matches = [];
if ($team_name && $game) {
    $stmt = $pdo->prepare("SELECT * FROM matches WHERE game = ? AND status = 'completed' AND (team1_name = ? OR team2_name = ?) ORDER BY round ASC");
    $stmt->execute([$game, $team_name, $team_name]);
    $matches = $stmt->fetchAll();
}

$wins = 0;
$losses = 0;
foreach ($matches as $m) {
    if ($m['winner'] === $team_name) $wins++;
    else $losses++;
}

// Get tournament placements
$placements = [];
$stmt = $pdo->prepare("SELECT * FROM tournament_results WHERE team_name = ? ORDER BY season DESC, placement ASC");
$stmt->execute([$team_name]);
$placements = $stmt->fetchAll();

// Parse titles
$titles = !empty($profile['titles']) ? json_decode($profile['titles'], true) : [];

// Prediction stats
$pred_stats = $pdo->prepare("SELECT
    COUNT(*) as total,
    SUM(CASE WHEN status = 'won' THEN 1 ELSE 0 END) as won,
    SUM(CASE WHEN status = 'lost' THEN 1 ELSE 0 END) as lost
    FROM match_predictions WHERE account_id = ?");
$pred_stats->execute([$account_id]);
$pstats = $pred_stats->fetch();

$pageTitle = htmlspecialchars($display_name) . ' — Player Profile';
$pageDescription = $display_name . '\'s tournament profile and match history.';

require_once __DIR__ . '/includes/header.php';
?>

<style>
/* ── Cover ── */
.pf-cover {
    width: 100%; height: 320px; position: relative;
    background: #3b0f78;
    overflow: hidden;
}
.pf-cover-art {
    position: absolute; inset: 0;
    background:
        radial-gradient(ellipse at 0% 50%,   rgba(109,40,217,0.9)  0%, transparent 55%),
        radial-gradient(ellipse at 100% 50%,  rgba(109,40,217,0.9)  0%, transparent 55%),
        radial-gradient(ellipse at 50% 50%,   rgba(124,58,237,0.6)  0%, transparent 70%),
        radial-gradient(ellipse at 50% 100%,  rgba(76,29,149,0.8)   0%, transparent 50%);
}
.pf-cover-art::after {
    content: ''; position: absolute; inset: 0;
    background-image: linear-gradient(rgba(139,92,246,0.06) 1px, transparent 1px), linear-gradient(90deg, rgba(139,92,246,0.06) 1px, transparent 1px);
    background-size: 44px 44px;
}

/* ── Identity row ── */
.pf-id-wrap { max-width: 1000px; margin: 0 auto; padding: 0 2rem; position: relative; }
.pf-id {
    display: flex; align-items: flex-end; gap: 1.5rem;
    padding: 0 0 1.1rem; border-bottom: 1px solid var(--border);
    flex-wrap: wrap; min-height: 100px;
}
.pf-av-wrap { margin-top: -84px; flex-shrink: 0; position: relative; z-index: 2; }
.pf-av-wrap.pf-av-editable { cursor: pointer; }

/* Edit Profile modal */
.pf-modal {
    position: fixed; inset: 0; z-index: 9999;
    background: rgba(0,0,0,0.65);
    backdrop-filter: blur(4px);
    display: none; align-items: flex-start; justify-content: center;
    padding: 3rem 1rem 2rem; overflow-y: auto;
    animation: pfModalFade 0.18s ease;
}
.pf-modal.on { display: flex; }
@keyframes pfModalFade { from { opacity: 0; } to { opacity: 1; } }
.pf-modal-dialog {
    width: 100%; max-width: 560px;
    animation: pfModalRise 0.22s cubic-bezier(0.2, 0.8, 0.2, 1);
}
@keyframes pfModalRise { from { opacity: 0; transform: translateY(14px) scale(0.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
.pf-modal-dialog .pf-card { margin: 0; }
.pf-modal-close {
    margin-left: auto;
    background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);
    color: var(--text-muted); width: 30px; height: 30px; border-radius: 50%;
    cursor: pointer; font-size: 0.9rem;
    display: inline-flex; align-items: center; justify-content: center;
    transition: background 0.15s, color 0.15s;
}
.pf-modal-close:hover { background: rgba(239,68,68,0.12); color: #f87171; border-color: rgba(239,68,68,0.3); }
.pf-cancel {
    background: transparent; border: 1px solid rgba(255,255,255,0.12);
    color: var(--text-muted);
    padding: 0.45rem 0.95rem; border-radius: 8px;
    font-size: 0.82rem; font-weight: 700; cursor: pointer;
    font-family: inherit;
}
.pf-cancel:hover { border-color: rgba(255,255,255,0.25); color: var(--text); }
body.pf-modal-open { overflow: hidden; }
.pf-av-overlay {
    position: absolute; inset: 5px;
    border-radius: 50%;
    background: rgba(0,0,0,0.55);
    display: flex; align-items: center; justify-content: center;
    flex-direction: column; gap: 0.3rem;
    color: #fff; font-weight: 800; font-size: 0.82rem; letter-spacing: 0.5px;
    opacity: 0; transition: opacity 0.18s ease;
    pointer-events: none;
}
.pf-av-overlay i { font-size: 1.6rem; }
.pf-av-editable:hover .pf-av-overlay, .pf-av-editable:focus-within .pf-av-overlay { opacity: 1; }
/* Match overlay shape to team avatar when applicable */
.pf-av-wrap:has(.pf-av-team) .pf-av-overlay { border-radius: 28px; }
.pf-av {
    width: 168px; height: 168px; border-radius: 50%;
    border: 5px solid var(--bg); box-shadow: 0 0 0 3px rgba(139,92,246,0.45), 0 10px 36px rgba(0,0,0,0.55);
    object-fit: cover; display: block; background: var(--bg-card);
}
.pf-av-initials {
    width: 168px; height: 168px; border-radius: 50%;
    background: #2a2d34;
    border: 5px solid var(--bg); box-shadow: 0 0 0 3px rgba(139,92,246,0.45), 0 10px 36px rgba(0,0,0,0.55);
    display: flex; align-items: flex-end; justify-content: center; overflow: hidden;
    color: #9ca3af; position: relative;
}
.pf-av-initials::before {
    content: ''; position: absolute; left: 50%; top: 28%;
    transform: translateX(-50%);
    width: 34%; height: 34%;
    border-radius: 50%; background: #9ca3af;
}
.pf-av-initials::after {
    content: ''; position: absolute; left: 50%; bottom: -4%;
    transform: translateX(-50%);
    width: 72%; height: 52%;
    border-radius: 50%; background: #9ca3af;
}
.pf-av-team {
    width: 168px; height: 168px; border-radius: 28px;
    border: 5px solid var(--bg); box-shadow: 0 0 0 3px rgba(139,92,246,0.45), 0 10px 36px rgba(0,0,0,0.55);
    object-fit: cover; display: block; background: var(--bg-card);
}
.pf-id-info { flex: 1; min-width: 0; padding-bottom: 0.3rem; }
.pf-id-name { font-size: 1.85rem; font-weight: 900; color: var(--text); line-height: 1.1; margin-bottom: 0.3rem; }
.pf-id-meta { font-size: 0.82rem; color: var(--text-muted); }
.pf-id-bio { font-size: 0.82rem; color: rgba(167,139,250,0.75); margin-top: 0.3rem; font-style: italic; }
.pf-id-titles { display: flex; gap: 0.4rem; flex-wrap: wrap; margin-top: 0.55rem; }
.pf-title-badge { display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.67rem; font-weight: 700; color: #fbbf24; background: rgba(251,191,36,0.1); border: 1px solid rgba(251,191,36,0.25); padding: 0.2rem 0.55rem; border-radius: 5px; }
.pf-id-btns { display: flex; gap: 0.5rem; padding-bottom: 0.4rem; flex-shrink: 0; }
.pf-btn { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.45rem 1rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-decoration: none; background: transparent; cursor: pointer; font-family: inherit; transition: all 0.15s; }
.pf-btn.primary { background: var(--accent); color: #fff; border-color: var(--accent); }
.pf-btn.primary:hover { background: #6d28d9; }
.pf-btn:hover { border-color: var(--accent); color: var(--accent-light); }

/* ── Tabs ── */
.pf-tabs-wrap { max-width: 1000px; margin: 0 auto; padding: 0 2rem; border-bottom: 1px solid var(--border); }
.pf-tabs { display: flex; gap: 0.2rem; overflow-x: auto; scrollbar-width: none; }
.pf-tabs::-webkit-scrollbar { display: none; }
.pf-tab { padding: 0.8rem 1rem; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); border-bottom: 3px solid transparent; white-space: nowrap; cursor: pointer; transition: all 0.15s; text-decoration: none; }
.pf-tab:hover { color: var(--text); }
.pf-tab.active { color: var(--accent-light); border-bottom-color: var(--accent); }

/* ── Body ── */
.pf-body { max-width: 1000px; margin: 1.25rem auto 5rem; padding: 0 2rem; display: grid; grid-template-columns: 300px 1fr; gap: 1.25rem; align-items: start; }
.pf-left { min-width: 0; }
.pf-right { min-width: 0; }

/* ── Card ── */
.pf-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 14px; overflow: hidden; margin-bottom: 1rem; }
.pf-card-head { padding: 0.85rem 1.1rem 0; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; display: flex; align-items: center; justify-content: space-between; }
.pf-card-body { padding: 0.85rem 1.1rem; }
.pf-info-row { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 1.1rem; border-top: 1px solid var(--border); font-size: 0.82rem; }
.pf-info-lbl { color: var(--text-muted); }
.pf-info-val { font-weight: 600; color: var(--text); }

/* ── Stat mini cards ── */
.pf-stat-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.6rem; padding: 0.85rem 1.1rem; }
.pf-stat-mini { background: rgba(255,255,255,0.03); border: 1px solid var(--border); border-radius: 10px; padding: 0.65rem 0.75rem; text-align: center; }
.pf-stat-mini-val { font-size: 1.35rem; font-weight: 900; color: var(--text); line-height: 1; }
.pf-stat-mini-lbl { font-size: 0.62rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 0.15rem; }

/* ── Match rows ── */
.pf-match { display: flex; align-items: center; gap: 0.75rem; padding: 0.65rem 1.1rem; border-top: 1px solid var(--border); font-size: 0.82rem; }
.pf-match-result { font-size: 0.7rem; font-weight: 800; width: 1.6rem; text-align: center; flex-shrink: 0; }
.pf-match-score { font-weight: 800; font-size: 0.88rem; flex-shrink: 0; }

/* ── Placement rows ── */
.pf-place { display: flex; align-items: center; gap: 0.85rem; padding: 0.65rem 1.1rem; border-top: 1px solid var(--border); font-size: 0.82rem; }

/* ── Roster rows ── */
.pf-roster-row { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 1.1rem; border-top: 1px solid var(--border); font-size: 0.82rem; }

/* ── Edit form ── */
.pf-form { padding: 0.85rem 1.1rem; }
.pf-field { margin-bottom: 0.7rem; }
.pf-field label { display: block; font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); margin-bottom: 0.3rem; }
.pf-field input, .pf-field textarea {
    width: 100%; background: rgba(255,255,255,0.03); border: 1px solid var(--border);
    border-radius: 8px; color: var(--text); padding: 0.5rem 0.8rem;
    font-size: 0.83rem; font-family: inherit; outline: none; transition: border-color 0.15s;
}
.pf-field input:focus, .pf-field textarea:focus { border-color: var(--accent); }
.pf-field textarea { resize: vertical; }
.pf-save { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.48rem 1.1rem; background: var(--accent); color: #fff; border: none; border-radius: 8px; font-size: 0.8rem; font-weight: 700; cursor: pointer; font-family: inherit; transition: background 0.15s; }
.pf-save:hover { background: #6d28d9; }
.pf-ok { display: flex; align-items: center; gap: 0.4rem; background: rgba(34,197,94,0.08); border: 1px solid rgba(34,197,94,0.22); border-radius: 7px; padding: 0.5rem 0.85rem; margin-bottom: 0.65rem; font-size: 0.8rem; color: #34d399; }
.pf-err { display: flex; align-items: center; gap: 0.4rem; background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.22); border-radius: 7px; padding: 0.5rem 0.85rem; margin-bottom: 0.65rem; font-size: 0.8rem; color: #f87171; }

@media (max-width: 800px) {
    .pf-body { grid-template-columns: 1fr; }
    .pf-cover { height: 200px; }
    .pf-av, .pf-av-initials, .pf-av-team { width: 120px; height: 120px; border-width: 4px; }
    .pf-av-initials { font-size: 2.4rem; }
    .pf-av-team { border-radius: 22px; }
    .pf-av-wrap { margin-top: -56px; }
    .pf-id-name { font-size: 1.4rem; }
    .pf-id-wrap, .pf-tabs-wrap, .pf-body { padding-left: 1rem; padding-right: 1rem; }
}
</style>

<?php
$avatar_radius = $profile['ref_type'] === 'team' ? '22px' : '50%';
$initials_pf   = strtoupper(substr($display_name, 0, 2));
?>

<!-- ── Cover ── -->
<div class="pf-cover"><div class="pf-cover-art"></div></div>

<!-- ── Identity row ── -->
<div class="pf-id-wrap">
    <div class="pf-id">
        <div class="pf-av-wrap<?= $is_own ? ' pf-av-editable' : '' ?>" <?= $is_own ? 'onclick="pfPickAvatar()" title="Change profile photo"' : '' ?>>
            <?php if (!empty($profile['profile_picture'])): ?>
                <img src="<?= base_url($profile['profile_picture']) ?>" class="pf-av" alt="<?= htmlspecialchars($display_name) ?>">
            <?php elseif ($profile['ref_type'] === 'team' && !empty($registration['team_logo'])): ?>
                <img src="<?= base_url($registration['team_logo']) ?>" class="pf-av-team" alt="">
            <?php elseif ($profile['ref_type'] === 'solo' && !empty($registration['profile_photo'])): ?>
                <img src="<?= base_url($registration['profile_photo']) ?>" class="pf-av" alt="">
            <?php else: ?>
                <div class="pf-av-initials" style="<?= $profile['ref_type'] === 'team' ? 'border-radius:28px;' : '' ?>" aria-label="<?= htmlspecialchars($display_name) ?>"></div>
            <?php endif; ?>
            <?php if ($is_own): ?>
            <div class="pf-av-overlay">
                <i class="bi bi-camera-fill"></i>
                <span>Update</span>
            </div>
            <?php endif; ?>
        </div>
        <div class="pf-id-info">
            <div class="pf-id-name">
                <?= htmlspecialchars($display_name) ?><?php if (!empty($profile['is_verified'])): ?><i class="bi bi-patch-check-fill" title="Verified" style="color:#60a5fa;font-size:1.2rem;margin-left:0.4rem;filter:drop-shadow(0 0 4px rgba(96,165,250,0.5));vertical-align:-2px;"></i><?php endif; ?>
            </div>
            <div class="pf-id-meta">
                <?= $profile['ref_type'] === 'team' ? '<i class="bi bi-people-fill"></i> Team' : '<i class="bi bi-person-fill"></i> Solo Player' ?>
                · <?= htmlspecialchars($valid_games[$game] ?? ($game ?: 'No game')) ?>
            </div>
            <?php if (!empty($profile['bio'])): ?>
                <div class="pf-id-bio">"<?= htmlspecialchars($profile['bio']) ?>"</div>
            <?php endif; ?>
            <?php if (!empty($titles)): ?>
            <div class="pf-id-titles">
                <?php foreach ($titles as $title): ?>
                    <span class="pf-title-badge"><i class="bi bi-award-fill"></i> <?= htmlspecialchars($title) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="pf-id-btns">
            <?php if ($is_own): ?>
                <a href="<?= base_url('dashboard.php') ?>" class="pf-btn"><i class="bi bi-speedometer2"></i> Dashboard</a>
                <button class="pf-btn primary" onclick="pfOpenEdit()"><i class="bi bi-pencil"></i> Edit Profile</button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── Tabs ── -->
<div class="pf-tabs-wrap">
    <div class="pf-tabs">
        <span class="pf-tab active">Overview</span>
        <?php if (!empty($matches)): ?><span class="pf-tab">Matches</span><?php endif; ?>
        <?php if ((int)$pstats['total'] > 0): ?><span class="pf-tab">Predictions</span><?php endif; ?>
        <?php if ($profile['ref_type'] === 'team' && !empty($registration['members_ranks'])): ?><span class="pf-tab">Roster</span><?php endif; ?>
    </div>
</div>

<!-- ── Body ── -->
<div class="pf-body">

    <!-- LEFT sidebar -->
    <div class="pf-left">

        <!-- Intro / About card -->
        <div class="pf-card">
            <div class="pf-card-head">About</div>
            <div class="pf-card-body">
                <?php if (!empty($profile['bio'])): ?>
                    <div style="font-size:0.85rem;color:var(--text-muted);line-height:1.6;margin-bottom:0.75rem;font-style:italic;">"<?= htmlspecialchars($profile['bio']) ?>"</div>
                <?php endif; ?>
                <div style="font-size:0.82rem;display:flex;flex-direction:column;gap:0.5rem;">
                    <div><i class="bi bi-controller" style="color:var(--accent-light);width:1.2rem;"></i> <?= htmlspecialchars($valid_games[$game] ?? 'No game') ?></div>
                    <div><i class="bi bi-<?= $profile['ref_type'] === 'team' ? 'people-fill' : 'person-fill' ?>" style="color:var(--accent-light);width:1.2rem;"></i> <?= ucfirst($profile['ref_type']) ?></div>
                    <div><i class="bi bi-calendar3" style="color:var(--accent-light);width:1.2rem;"></i> Joined <?= date('M Y', strtotime($profile['created_at'])) ?></div>
                    <?php if ($is_own && !empty($profile['gcash_number'])): ?>
                    <div style="display:flex;align-items:center;gap:0.4rem;">
                        <i class="bi bi-phone" style="color:#34d399;width:1.2rem;"></i>
                        GCash: <?= htmlspecialchars($profile['gcash_number']) ?>
                        <span title="Only visible to you" style="display:inline-flex;align-items:center;gap:0.2rem;font-size:0.62rem;font-weight:700;color:var(--text-muted);background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:4px;padding:0.1rem 0.35rem;flex-shrink:0;"><i class="bi bi-lock-fill" style="font-size:0.6rem;"></i> Only you</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Stats card -->
        <div class="pf-card">
            <div class="pf-card-head">Stats</div>
            <div class="pf-stat-grid">
                <?php if ($is_own): ?>
                <div class="pf-stat-mini">
                    <div class="pf-stat-mini-val" style="color:#fbbf24;"><?= number_format((int)$profile['h_coins']) ?></div>
                    <div class="pf-stat-mini-lbl">H-Coins <span title="Only visible to you" style="display:inline-flex;align-items:center;font-size:0.58rem;color:var(--text-muted);margin-left:0.2rem;"><i class="bi bi-lock-fill"></i></span></div>
                </div>
                <?php endif; ?>
                <div class="pf-stat-mini">
                    <div class="pf-stat-mini-val"><?= count($matches) ?></div>
                    <div class="pf-stat-mini-lbl">Matches</div>
                </div>
                <div class="pf-stat-mini">
                    <div class="pf-stat-mini-val" style="color:#34d399;"><?= $wins ?></div>
                    <div class="pf-stat-mini-lbl">Wins</div>
                </div>
                <div class="pf-stat-mini">
                    <div class="pf-stat-mini-val" style="color:#f87171;"><?= $losses ?></div>
                    <div class="pf-stat-mini-lbl">Losses</div>
                </div>
                <?php if ((int)$pstats['total'] > 0): ?>
                <div class="pf-stat-mini">
                    <div class="pf-stat-mini-val" style="color:var(--accent-light);"><?= (int)$pstats['won'] ?></div>
                    <div class="pf-stat-mini-lbl">Pred Won</div>
                </div>
                <div class="pf-stat-mini">
                    <div class="pf-stat-mini-val"><?= (int)$pstats['total'] ?></div>
                    <div class="pf-stat-mini-lbl">Pred Total</div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Roster (team only) -->
        <?php if ($profile['ref_type'] === 'team' && !empty($registration['members_ranks'])): ?>
        <div class="pf-card">
            <div class="pf-card-head">Roster</div>
            <?php
            $members = explode('|', $registration['members_ranks']);
            foreach ($members as $mi => $entry):
                $parts = explode(':', $entry, 2);
                $mname = $parts[0] ?? ''; $mrank = $parts[1] ?? '';
                if (empty($mname)) continue;
            ?>
            <div class="pf-roster-row">
                <span><?= $mi === 0 ? '<i class="bi bi-star-fill" style="color:#fbbf24;font-size:0.55rem;margin-right:0.3rem;"></i>' : '' ?><?= htmlspecialchars($mname) ?></span>
                <span style="color:var(--text-muted);font-size:0.75rem;"><?= !empty($mrank) ? htmlspecialchars($mrank) : '—' ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>

    <!-- RIGHT main feed -->
    <div class="pf-right">

        <!-- Alerts -->
        <?php if ($profile_saved): ?>
            <div class="pf-ok" style="margin-bottom:1rem;"><i class="bi bi-check-circle-fill"></i> Profile saved.</div>
        <?php endif; ?>
        <?php if (!empty($profile_errors)): ?>
            <div class="pf-err" style="margin-bottom:1rem;"><?= implode('<br>', $profile_errors) ?></div>
        <?php endif; ?>

        <!-- Tournament Placements -->
        <?php if (!empty($placements)): ?>
        <div class="pf-card">
            <div class="pf-card-head">Hall of Fame</div>
            <?php foreach ($placements as $p):
                $medal = [1 => '#fbbf24', 2 => '#94a3b8', 3 => '#cd7f32'];
                $color = $medal[$p['placement']] ?? 'var(--text-muted)';
            ?>
            <div class="pf-place">
                <span style="font-size:1.15rem;color:<?= $color ?>;flex-shrink:0;">
                    <?php if ($p['placement'] <= 3): ?><i class="bi bi-trophy-fill"></i><?php else: ?><span style="font-size:0.82rem;font-weight:800;"><?= $p['placement'] ?>th</span><?php endif; ?>
                </span>
                <div style="flex:1;">
                    <div style="font-weight:700;font-size:0.85rem;"><?= htmlspecialchars($p['season']) ?></div>
                    <div style="font-size:0.72rem;color:var(--text-muted);"><?= htmlspecialchars($valid_games[$p['game']] ?? $p['game']) ?></div>
                </div>
                <?php if (!empty($p['prize'])): ?><span style="font-size:0.75rem;color:var(--text-muted);"><?= htmlspecialchars($p['prize']) ?></span><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Match History -->
        <?php if (!empty($matches)): ?>
        <div class="pf-card">
            <div class="pf-card-head">Match History <span style="font-weight:400;font-size:0.7rem;color:var(--text-muted);text-transform:none;letter-spacing:0;"><?= count($matches) ?> matches · <?= $wins ?>W <?= $losses ?>L</span></div>
            <?php foreach ($matches as $match):
                $is_t1 = $match['team1_name'] === $team_name;
                $opp   = $is_t1 ? $match['team2_name'] : $match['team1_name'];
                $ms    = $is_t1 ? $match['team1_score'] : $match['team2_score'];
                $os    = $is_t1 ? $match['team2_score'] : $match['team1_score'];
                $won   = $match['winner'] === $team_name;
                $sl    = $match['bracket_side'] === 'winners' ? 'W' : ($match['bracket_side'] === 'losers' ? 'L' : 'GF');
            ?>
            <div class="pf-match">
                <div class="pf-match-result" style="color:<?= $won ? '#34d399' : '#f87171' ?>;"><?= $won ? 'W' : 'L' ?></div>
                <div style="flex:1;min-width:0;">
                    <div style="font-weight:600;">vs <?= htmlspecialchars($opp ?: 'TBD') ?></div>
                    <div style="font-size:0.67rem;color:var(--text-muted);"><?= $sl ?> · Round <?= $match['round'] ?></div>
                </div>
                <div class="pf-match-score" style="color:<?= $won ? '#34d399' : '#f87171' ?>;"><?= $ms ?> – <?= $os ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php elseif (empty($placements)): ?>
        <div class="pf-card">
            <div class="pf-card-body" style="text-align:center;padding:2rem;color:var(--text-muted);">
                <i class="bi bi-hourglass-split" style="font-size:1.5rem;display:block;margin-bottom:0.5rem;"></i>
                No match history yet. The journey begins when the tournament starts.
            </div>
        </div>
        <?php endif; ?>

        <!-- Edit Profile modal (own only) -->
        <?php if ($is_own): ?>
        <div class="pf-modal" id="editModal" onclick="pfModalBackdrop(event)" aria-hidden="true">
        <div class="pf-modal-dialog">
        <div class="pf-card" id="editSection">
            <div class="pf-card-head" style="display:flex;align-items:center;">
                <span>Edit Profile</span>
                <button type="button" class="pf-modal-close" onclick="pfCloseEdit()" aria-label="Close" title="Close">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="pf-form">
                <form method="POST" enctype="multipart/form-data">
                    <!-- Photo upload -->
                    <div style="display:flex;align-items:center;gap:1.1rem;margin-bottom:1rem;">
                        <div id="avatarPreview" style="width:72px;height:72px;border-radius:<?= $avatar_radius ?>;overflow:hidden;border:3px solid var(--accent);flex-shrink:0;background:var(--bg-card);display:flex;align-items:center;justify-content:center;color:var(--text-muted);font-size:1.8rem;box-shadow:0 4px 16px rgba(124,58,237,0.3);">
                            <?php if (!empty($profile['profile_picture'])): ?><img src="<?= base_url($profile['profile_picture']) ?>" style="width:100%;height:100%;object-fit:cover;"><?php else: ?><i class="bi bi-person-fill"></i><?php endif; ?>
                        </div>
                        <div>
                            <label style="display:inline-flex;align-items:center;gap:0.35rem;cursor:pointer;background:rgba(124,58,237,0.1);border:1px solid rgba(124,58,237,0.3);border-radius:8px;padding:0.42rem 0.8rem;font-size:0.75rem;color:var(--accent-light);font-weight:700;">
                                <i class="bi bi-camera-fill"></i> Change Photo
                                <input type="file" name="profile_picture" id="pfPhotoInput" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none;" onchange="previewAvatar(this)">
                            </label>
                            <div style="font-size:0.63rem;color:var(--text-muted);margin-top:0.2rem;">JPG, PNG, WebP · max 3MB</div>
                        </div>
                    </div>
                    <div class="pf-field">
                        <label>Username</label>
                        <input type="text" name="display_name" value="<?= htmlspecialchars($profile['display_name'] ?? '') ?>" required>
                    </div>
                    <div class="pf-field">
                        <label>Bio</label>
                        <textarea name="bio" rows="2" maxlength="255" placeholder="Tell us about yourself..."><?= htmlspecialchars($profile['bio'] ?? '') ?></textarea>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.65rem;">
                        <div class="pf-field">
                            <label>Contact Number</label>
                            <input type="text" name="contact_number" value="<?= htmlspecialchars($profile['contact_number'] ?? '') ?>" placeholder="09XXXXXXXXX">
                        </div>
                        <div class="pf-field">
                            <label>GCash Number</label>
                            <input type="text" name="gcash_number" value="<?= htmlspecialchars($profile['gcash_number'] ?? '') ?>" placeholder="09XXXXXXXXX">
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:0.75rem;margin-top:0.25rem;flex-wrap:wrap;">
                        <button type="submit" name="save_profile" value="1" class="pf-save"><i class="bi bi-check-lg"></i> Save Changes</button>
                        <button type="button" class="pf-cancel" onclick="pfCloseEdit()">Cancel</button>
                        <span style="font-size:0.7rem;color:var(--text-muted);">Email and ref code cannot be changed.</span>
                    </div>
                </form>
            </div>
        </div>
        </div><!-- /.pf-modal-dialog -->
        </div><!-- /.pf-modal -->
        <?php endif; ?>

    </div>

</div>

<script>
function previewAvatar(input) {
    if (!input.files || !input.files[0]) return;
    var reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('avatarPreview').innerHTML = '<img src="' + e.target.result + '" style="width:100%;height:100%;object-fit:cover;">';
        // Also update the big hero avatar so the change is visible immediately
        var wrap = document.querySelector('.pf-av-wrap');
        if (wrap) {
            var heroImg = wrap.querySelector('img.pf-av, img.pf-av-team');
            if (heroImg) {
                heroImg.src = e.target.result;
            } else {
                var initials = wrap.querySelector('.pf-av-initials');
                if (initials) {
                    initials.outerHTML = '<img src="' + e.target.result + '" class="pf-av" alt="">';
                }
            }
        }
    };
    reader.readAsDataURL(input.files[0]);
}
function pfOpenEdit() {
    var m = document.getElementById('editModal');
    if (!m) return;
    m.classList.add('on');
    m.setAttribute('aria-hidden', 'false');
    document.body.classList.add('pf-modal-open');
    // Focus first input for keyboard users
    var first = m.querySelector('input[name="display_name"]');
    if (first) setTimeout(function(){ first.focus(); }, 30);
}
function pfCloseEdit() {
    var m = document.getElementById('editModal');
    if (!m) return;
    m.classList.remove('on');
    m.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('pf-modal-open');
}
function pfModalBackdrop(e) {
    if (e.target && e.target.id === 'editModal') pfCloseEdit();
}
function pfPickAvatar() {
    // Opens the Edit Profile modal and immediately triggers the file picker.
    pfOpenEdit();
    var input = document.getElementById('pfPhotoInput');
    if (input) setTimeout(function(){ input.click(); }, 80);
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        var m = document.getElementById('editModal');
        if (m && m.classList.contains('on')) pfCloseEdit();
    }
});
<?php if (!empty($profile_errors)): ?>
// Reopen the modal automatically when the last submit had errors
pfOpenEdit();
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
