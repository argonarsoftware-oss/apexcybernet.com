<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user($pdo);
$pageTitle = 'Dashboard';
$pageDescription = 'Your tournament dashboard.';

$valid_games = [
    'valorant' => 'Valorant',
    'crossfire' => 'CrossFire',
    'dota2' => 'Dota 2',
];

$rank_tiers = [
    'dota2' => ['Herald', 'Guardian', 'Crusader', 'Archon', 'Legend', 'Ancient', 'Divine', 'Immortal'],
    'valorant' => ['Iron', 'Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond', 'Ascendant', 'Immortal', 'Radiant'],
    'crossfire' => ['Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond', 'Master', 'Grand Master'],
];

// Load registration data
$registration = null;
$team_name = '';
$game = '';
if (!empty($user['ref_code'])) {
    if ($user['ref_type'] === 'team') {
        $stmt = $pdo->prepare("SELECT * FROM teams WHERE ref_code = ?");
        $stmt->execute([$user['ref_code']]);
        $registration = $stmt->fetch();
    } else {
        $stmt = $pdo->prepare("SELECT * FROM solo_players WHERE ref_code = ?");
        $stmt->execute([$user['ref_code']]);
        $registration = $stmt->fetch();
    }
    $team_name = $user['ref_type'] === 'team' ? ($registration['team_name'] ?? '') : ($registration['player_name'] ?? '');
    $game = $registration['game'] ?? '';
}

// Matches
$matches = [];
if ($team_name && $game) {
    $stmt = $pdo->prepare("SELECT * FROM matches WHERE game = ? AND (team1_name = ? OR team2_name = ?) ORDER BY bracket_side ASC, round ASC, match_order ASC");
    $stmt->execute([$game, $team_name, $team_name]);
    $matches = $stmt->fetchAll();
}

// Next upcoming match
$next_match = null;
foreach ($matches as $m) {
    if (in_array($m['status'], ['upcoming', 'live'])) { $next_match = $m; break; }
}

// Announcements
$announcements = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 4")->fetchAll();

// Handle profile edit
$profile_saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_profile'])) {
    $new_display = trim($_POST['display_name'] ?? '');
    $new_bio = trim($_POST['bio'] ?? '');
    if (strlen($new_bio) > 255) $new_bio = substr($new_bio, 0, 255);
    $pdo->prepare("UPDATE accounts SET display_name = ?, bio = ? WHERE id = ?")->execute([$new_display, $new_bio, $user['id']]);
    $profile_saved = true;
    $stmt = $pdo->prepare("SELECT * FROM accounts WHERE id = ?");
    $stmt->execute([$_SESSION['account_id']]);
    $user = $stmt->fetch();
}

// Handle roster edit
$edit_errors = [];
$edit_success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_roster']) && $user['ref_type'] === 'team' && $registration) {
    $new_members = [];
    $new_ranks = [];
    for ($i = 1; $i <= 5; $i++) {
        $new_members[$i] = trim($_POST["member_$i"] ?? '');
        $new_ranks[$i] = trim($_POST["member_rank_$i"] ?? '');
    }
    foreach ($new_members as $i => $m) {
        if ($m === '') $edit_errors[] = "Member $i name is required.";
    }
    if (empty($edit_errors)) {
        $members_data = '';
        for ($i = 1; $i <= 5; $i++) {
            $members_data .= ($i > 1 ? '|' : '') . $new_members[$i] . ':' . $new_ranks[$i];
        }
        $stmt = $pdo->prepare("UPDATE teams SET member_1=?, member_2=?, member_3=?, member_4=?, member_5=?, members_ranks=? WHERE ref_code=?");
        $stmt->execute([$new_members[1], $new_members[2], $new_members[3], $new_members[4], $new_members[5], $members_data, $user['ref_code']]);
        $edit_success = true;
        $stmt = $pdo->prepare("SELECT * FROM teams WHERE ref_code = ?");
        $stmt->execute([$user['ref_code']]);
        $registration = $stmt->fetch();
    }
}

// Avatar initials
$initials = strtoupper(substr($user['display_name'] ?: $user['email'], 0, 2));

require_once __DIR__ . '/includes/header.php';

// Compute quick stats
$total_matches  = count($matches);
$wins           = count(array_filter($matches, fn($m) => $m['winner'] === $team_name));
$win_rate       = $total_matches > 0 ? round($wins / $total_matches * 100) : null;
?>

<style>
.db { max-width: 1180px; margin: 0 auto; padding: 1.5rem 1.25rem 5rem; }

/* ── Top bar ── */
.db-top {
    display: flex; align-items: center; gap: 1rem;
    padding-bottom: 1.25rem; margin-bottom: 1.5rem;
    border-bottom: 1px solid var(--border);
}
.db-av {
    width: 42px; height: 42px; border-radius: 12px; flex-shrink: 0;
    background: linear-gradient(135deg, #7c3aed, #4c1d95);
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; font-weight: 900; color: #fff; letter-spacing: -1px;
}
.db-welcome { flex: 1; min-width: 0; }
.db-welcome-name { font-size: 1rem; font-weight: 800; color: var(--text); }
.db-welcome-sub { font-size: 0.72rem; color: var(--text-muted); margin-top: 1px; }
.db-top-actions { display: flex; gap: 0.5rem; flex-shrink: 0; }
.db-top-btn {
    display: inline-flex; align-items: center; gap: 0.35rem;
    padding: 0.4rem 0.85rem; border: 1px solid var(--border);
    border-radius: 8px; font-size: 0.75rem; font-weight: 600;
    color: var(--text-muted); text-decoration: none; background: transparent;
    cursor: pointer; font-family: inherit; transition: all 0.15s;
}
.db-top-btn:hover { border-color: var(--accent); color: var(--accent-light); }
.db-top-btn.danger:hover { border-color: #ef4444; color: #f87171; }

/* ── Stat cards ── */
.db-stats {
    display: grid; grid-template-columns: repeat(4, 1fr);
    gap: 1rem; margin-bottom: 1.5rem;
}
.db-stat {
    background: var(--bg-card); border: 1px solid var(--border);
    border-radius: 14px; padding: 1rem 1.1rem;
    display: flex; flex-direction: column; gap: 0.2rem;
    transition: border-color 0.18s;
}
.db-stat:hover { border-color: rgba(139,92,246,0.35); }
.db-stat-ico {
    font-size: 1.1rem; margin-bottom: 0.3rem;
}
.db-stat-val {
    font-size: 1.75rem; font-weight: 900; line-height: 1; color: var(--text);
}
.db-stat-lbl {
    font-size: 0.65rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 1px; color: var(--text-muted);
}
.db-stat-sub { font-size: 0.68rem; color: var(--text-muted); margin-top: 0.15rem; }

/* ── Alert ── */
.db-alert {
    display: flex; gap: 1rem; align-items: flex-start;
    background: rgba(239,68,68,0.07); border: 1px solid rgba(239,68,68,0.28);
    border-radius: 12px; padding: 1rem 1.25rem; margin-bottom: 1.25rem;
}
.db-alert-icon { color: #f87171; font-size: 1.1rem; flex-shrink: 0; padding-top: 0.1rem; }
.db-alert-title { font-weight: 800; font-size: 0.88rem; color: #f87171; margin-bottom: 0.2rem; }
.db-alert-text { font-size: 0.8rem; color: var(--text-muted); line-height: 1.5; }
.db-alert-cta {
    display: inline-flex; align-items: center; gap: 0.4rem; margin-top: 0.6rem;
    padding: 0.45rem 1.1rem; background: #dc2626; color: #fff;
    border-radius: 8px; font-size: 0.8rem; font-weight: 700; text-decoration: none;
    transition: background 0.15s;
}
.db-alert-cta:hover { background: #b91c1c; }

/* ── Main 2-col grid ── */
.db-grid {
    display: grid; grid-template-columns: 1fr 1fr;
    gap: 1.25rem; margin-bottom: 1.25rem;
}

/* ── Card ── */
.db-card {
    background: var(--bg-card); border: 1px solid var(--border);
    border-radius: 14px; overflow: hidden;
    display: flex; flex-direction: column;
}
.db-card-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 0.85rem 1.1rem 0;
}
.db-card-label {
    font-size: 0.63rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 1.5px; color: var(--text-muted);
}
.db-card-link {
    font-size: 0.7rem; color: var(--accent-light); text-decoration: none; font-weight: 600;
}
.db-card-link:hover { text-decoration: underline; }
.db-card-body { padding: 0.85rem 1.1rem; flex: 1; }

/* ── Announcement rows ── */
.db-ann { display: flex; gap: 0.7rem; align-items: flex-start; padding: 0.65rem 1.1rem; border-top: 1px solid var(--border); }
.db-ann-dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; margin-top: 0.45rem; }
.db-ann-title { font-size: 0.82rem; font-weight: 700; margin-bottom: 0.1rem; }
.db-ann-body { font-size: 0.75rem; color: var(--text-muted); line-height: 1.45; }
.db-ann-date { font-size: 0.6rem; color: #374151; margin-top: 0.15rem; }

/* ── Next match card ── */
.db-match-card {
    background: rgba(124,58,237,0.07); border: 1px solid rgba(124,58,237,0.25);
    border-radius: 10px; padding: 0.85rem 1rem; margin-bottom: 0.6rem;
}
.db-match-vs { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem; }
.db-match-team { flex: 1; font-size: 0.88rem; font-weight: 800; color: var(--text); text-align: center; }
.db-match-vs-badge { font-size: 0.65rem; font-weight: 800; color: var(--text-muted); background: rgba(255,255,255,0.05); border-radius: 4px; padding: 0.2rem 0.4rem; flex-shrink: 0; }

/* ── Reg rows ── */
.db-reg-row { display: flex; justify-content: space-between; align-items: center; padding: 0.6rem 1.1rem; border-top: 1px solid var(--border); font-size: 0.82rem; }
.db-reg-lbl { color: var(--text-muted); }
.db-reg-val { font-weight: 600; color: var(--text); }
.db-reg-val.code { color: var(--accent-light); font-family: monospace; letter-spacing: 1px; font-size: 0.78rem; }

/* ── Form ── */
.db-form { padding: 0.85rem 1.1rem; }
.db-field { margin-bottom: 0.65rem; }
.db-field label { display: block; font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); margin-bottom: 0.3rem; }
.db-field input {
    width: 100%; background: rgba(255,255,255,0.03); border: 1px solid var(--border);
    border-radius: 8px; color: var(--text); padding: 0.5rem 0.8rem;
    font-size: 0.83rem; font-family: inherit; outline: none; transition: border-color 0.15s;
}
.db-field input:focus { border-color: var(--accent); }
.db-save {
    display: inline-flex; align-items: center; gap: 0.35rem;
    padding: 0.48rem 1.1rem; background: var(--accent); color: #fff;
    border: none; border-radius: 8px; font-size: 0.8rem; font-weight: 700;
    cursor: pointer; font-family: inherit; transition: background 0.15s;
}
.db-save:hover { background: #6d28d9; }
.db-ok { display: flex; align-items: center; gap: 0.4rem; background: rgba(34,197,94,0.08); border: 1px solid rgba(34,197,94,0.22); border-radius: 7px; padding: 0.5rem 0.85rem; margin-bottom: 0.65rem; font-size: 0.8rem; color: #34d399; }
.db-err { display: flex; align-items: center; gap: 0.4rem; background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.22); border-radius: 7px; padding: 0.5rem 0.85rem; margin-bottom: 0.65rem; font-size: 0.8rem; color: #f87171; }
.db-empty { padding: 1.5rem; text-align: center; color: var(--text-muted); font-size: 0.82rem; }
.db-empty i { font-size: 1.4rem; display: block; margin-bottom: 0.4rem; }

/* ── Roster form ── */
.db-roster { padding: 0.85rem 1.1rem; }
.db-roster-row { display: flex; gap: 0.4rem; align-items: center; margin-bottom: 0.45rem; }
.db-roster-num { font-size: 0.7rem; color: var(--text-muted); width: 1.4rem; text-align: center; flex-shrink: 0; }
.db-roster-row input, .db-roster-row select {
    background: rgba(255,255,255,0.03); border: 1px solid var(--border); border-radius: 8px;
    color: var(--text); padding: 0.45rem 0.65rem; font-size: 0.8rem; font-family: inherit; outline: none; transition: border-color 0.15s;
}
.db-roster-row input:focus, .db-roster-row select:focus { border-color: var(--accent); }
.db-roster-row input { flex: 1; }
.db-roster-row select { width: 120px; flex-shrink: 0; }
.db-roster-row select option { background: var(--bg-card); }

@media (max-width: 900px) {
    .db-stats { grid-template-columns: repeat(2, 1fr); }
    .db-grid { grid-template-columns: 1fr; }
}
@media (max-width: 480px) {
    .db-stats { grid-template-columns: repeat(2, 1fr); }
}
</style>

<div class="db">

    <!-- ── Top bar ── -->
    <div class="db-top">
        <div class="db-av"><?= htmlspecialchars($initials) ?></div>
        <div class="db-welcome">
            <div class="db-welcome-name">Hey, <?= htmlspecialchars($user['display_name'] ?: 'there') ?></div>
            <div class="db-welcome-sub"><?= htmlspecialchars($user['email']) ?><?php if (!empty($user['bio'])): ?> · <?= htmlspecialchars($user['bio']) ?><?php endif; ?></div>
        </div>
        <div class="db-top-actions">
            <a href="<?= base_url('profile.php') ?>" class="db-top-btn"><i class="bi bi-person"></i> Profile</a>
            <a href="<?= base_url('logout.php') ?>" class="db-top-btn danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </div>

    <!-- ── Payment Alert ── -->
    <?php if ($registration && $registration['status'] === 'pending'): ?>
    <div class="db-alert">
        <div class="db-alert-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
        <div style="flex:1;">
            <div class="db-alert-title">Slot NOT confirmed — payment required</div>
            <div class="db-alert-text">Your registration is pending. Pay before April 17 or your slot goes to the waitlist.</div>
            <a href="<?= base_url('ticket.php?ref=' . urlencode($user['ref_code']) . '&type=' . $user['ref_type'] . '&game=' . $game) ?>" class="db-alert-cta">
                <i class="bi bi-qr-code"></i> Pay Now — Secure Your Slot
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Stats row ── -->
    <div class="db-stats">
        <div class="db-stat">
            <div class="db-stat-ico" style="color:#60a5fa;"><i class="bi bi-controller"></i></div>
            <div class="db-stat-val"><?= $total_matches ?></div>
            <div class="db-stat-lbl">Matches</div>
            <div class="db-stat-sub"><?= $wins ?> win<?= $wins !== 1 ? 's' : '' ?></div>
        </div>
        <div class="db-stat">
            <div class="db-stat-ico" style="color:<?= $win_rate !== null ? '#34d399' : 'var(--text-muted)' ?>;"><i class="bi bi-bar-chart-fill"></i></div>
            <div class="db-stat-val" style="color:<?= $win_rate !== null ? '#34d399' : 'var(--text-muted)' ?>;"><?= $win_rate !== null ? $win_rate . '%' : '—' ?></div>
            <div class="db-stat-lbl">Win Rate</div>
            <div class="db-stat-sub">tournament</div>
        </div>
    </div>

    <!-- ── Row 1: Tournament + Announcements ── -->
    <div class="db-grid">

        <!-- Tournament / Next match card -->
        <div class="db-card">
            <div class="db-card-head">
                <span class="db-card-label">Tournament</span>
                <?php if ($game): ?><a href="<?= base_url('bracket.php?game=' . $game) ?>" class="db-card-link"><i class="bi bi-diagram-3"></i> Bracket</a><?php endif; ?>
            </div>
            <div class="db-card-body">
                <?php if ($registration): ?>

                    <?php if ($next_match): ?>
                    <div style="font-size:0.62rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);margin-bottom:0.5rem;">
                        <?= $next_match['status'] === 'live' ? '<span style="color:#f87171;"><span class="live-dot"></span> LIVE NOW</span>' : 'Next Match' ?>
                    </div>
                    <div class="db-match-card">
                        <div class="db-match-vs">
                            <div class="db-match-team"><?= htmlspecialchars($next_match['team1_name'] ?: 'TBD') ?></div>
                            <div class="db-match-vs-badge">VS</div>
                            <div class="db-match-team"><?= htmlspecialchars($next_match['team2_name'] ?: 'TBD') ?></div>
                        </div>
                        <div style="font-size:0.68rem;color:var(--text-muted);text-align:center;">
                            <?= $next_match['bracket_side'] === 'winners' ? 'Winners' : ($next_match['bracket_side'] === 'losers' ? 'Losers' : 'Grand Finals') ?> · Round <?= $next_match['round'] ?>
                            <?php if (!empty($next_match['scheduled_at'])): ?> · <?= date('M j, g:ia', strtotime($next_match['scheduled_at'])) ?><?php endif; ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <div style="font-size:0.62rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);margin-bottom:0.75rem;">Registration</div>
                    <?php endif; ?>

                    <div style="font-size:0.8rem;display:flex;flex-direction:column;gap:0.1rem;">
                        <div style="display:flex;justify-content:space-between;padding:0.45rem 0;border-bottom:1px solid var(--border);">
                            <span style="color:var(--text-muted);">Team</span>
                            <span style="font-weight:700;"><?= htmlspecialchars($team_name) ?></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:0.45rem 0;border-bottom:1px solid var(--border);">
                            <span style="color:var(--text-muted);">Game</span>
                            <span style="font-weight:600;"><?= htmlspecialchars($valid_games[$game] ?? $game) ?></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:0.45rem 0;border-bottom:1px solid var(--border);">
                            <span style="color:var(--text-muted);">Ref Code</span>
                            <span style="font-weight:700;color:var(--accent-light);font-family:monospace;"><?= htmlspecialchars($user['ref_code']) ?></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:0.45rem 0;">
                            <span style="color:var(--text-muted);">Status</span>
                            <span class="status-badge status-<?= $registration['status'] ?>" style="font-size:0.72rem;"><?= ucfirst($registration['status']) ?></span>
                        </div>
                    </div>
                    <?php if ($registration['status'] === 'pending'): ?>
                    <a href="<?= base_url('ticket.php?ref=' . urlencode($user['ref_code']) . '&type=' . $user['ref_type'] . '&game=' . $game) ?>" class="db-alert-cta" style="margin-top:0.85rem;width:fit-content;">
                        <i class="bi bi-qr-code"></i> Pay Now
                    </a>
                    <?php endif; ?>

                <?php else: ?>
                <div class="db-empty"><i class="bi bi-controller"></i> No tournament registration yet.<br><a href="<?= base_url('register.php') ?>" style="color:var(--accent-light);">Register for Season 1</a></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Announcements -->
        <div class="db-card">
            <div class="db-card-head">
                <span class="db-card-label">Announcements</span>
            </div>
            <?php if (!empty($announcements)):
                $ann_colors = ['urgent' => '#f87171', 'schedule' => '#60a5fa', 'result' => '#34d399', 'news' => 'var(--accent-light)'];
                foreach ($announcements as $ann):
                    $c = $ann_colors[$ann['type']] ?? $ann_colors['news'];
            ?>
            <div class="db-ann">
                <div class="db-ann-dot" style="background:<?= $c ?>;"></div>
                <div style="flex:1;min-width:0;">
                    <div class="db-ann-title" style="color:<?= $c ?>;"><?= htmlspecialchars($ann['title']) ?></div>
                    <div class="db-ann-body"><?= nl2br(htmlspecialchars($ann['content'])) ?></div>
                    <div class="db-ann-date"><?= date('M j, Y · g:ia', strtotime($ann['created_at'])) ?></div>
                </div>
            </div>
            <?php endforeach; else: ?>
            <div class="db-empty"><i class="bi bi-megaphone"></i> No announcements</div>
            <?php endif; ?>
        </div>

    </div>

    <!-- ── Row 3: Edit Profile + Roster ── -->
    <div class="db-grid">

        <!-- Edit Profile -->
        <div class="db-card">
            <div class="db-card-head"><span class="db-card-label">Edit Profile</span></div>
            <div class="db-form">
                <?php if ($profile_saved): ?><div class="db-ok"><i class="bi bi-check-circle-fill"></i> Profile saved.</div><?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="edit_profile" value="1">
                    <div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:0.65rem;"><?= htmlspecialchars($user['email']) ?></div>
                    <div class="db-field">
                        <label>Display Name</label>
                        <input type="text" name="display_name" value="<?= htmlspecialchars($user['display_name'] ?? '') ?>" placeholder="Your in-game name">
                    </div>
                    <div class="db-field">
                        <label>Bio <span style="font-weight:400;text-transform:none;letter-spacing:0;">(max 255 chars)</span></label>
                        <input type="text" name="bio" value="<?= htmlspecialchars($user['bio'] ?? '') ?>" placeholder="Your motto or tagline" maxlength="255">
                    </div>
                    <button type="submit" class="db-save"><i class="bi bi-check-lg"></i> Save</button>
                </form>
            </div>
        </div>

        <!-- Edit Roster (team only) -->
        <?php if ($registration && $user['ref_type'] === 'team'): ?>
        <div class="db-card">
            <div class="db-card-head"><span class="db-card-label">Edit Roster</span></div>
            <div class="db-roster">
                <?php if ($edit_success): ?><div class="db-ok"><i class="bi bi-check-circle-fill"></i> Roster updated.</div><?php endif; ?>
                <?php if (!empty($edit_errors)): ?><div class="db-err"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars(implode(' ', $edit_errors)) ?></div><?php endif; ?>
                <?php
                    $current_members = !empty($registration['members_ranks']) ? explode('|', $registration['members_ranks']) : [];
                    $game_ranks = $rank_tiers[$game] ?? [];
                ?>
                <form method="POST">
                    <input type="hidden" name="edit_roster" value="1">
                    <?php for ($i = 1; $i <= 5; $i++):
                        $entry = $current_members[$i - 1] ?? ':';
                        $parts = explode(':', $entry, 2);
                        $mname = $parts[0] ?? ''; $mrank = $parts[1] ?? '';
                    ?>
                    <div class="db-roster-row">
                        <span class="db-roster-num"><?= $i === 1 ? '<i class="bi bi-star-fill" style="color:#fbbf24;font-size:0.6rem;"></i>' : $i ?></span>
                        <input type="text" name="member_<?= $i ?>" placeholder="Player <?= $i ?>" value="<?= htmlspecialchars($_POST["member_$i"] ?? $mname) ?>" required>
                        <select name="member_rank_<?= $i ?>">
                            <option value="">Rank</option>
                            <?php foreach ($game_ranks as $r): ?>
                                <option value="<?= $r ?>" <?= ($_POST["member_rank_$i"] ?? $mrank) === $r ? 'selected' : '' ?>><?= $r ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endfor; ?>
                    <button type="submit" class="db-save" style="margin-top:0.4rem;"><i class="bi bi-pencil"></i> Update Roster</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

    </div>

</div>

<style>
.live-dot { display:inline-block; width:6px; height:6px; border-radius:50%; background:#f87171; animation:blink 1s step-start infinite; margin-right:2px; }
@keyframes blink { 50% { opacity:0; } }
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
