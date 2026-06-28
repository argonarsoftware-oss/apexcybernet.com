<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/bracket_logic.php';

if (isset($_GET['token']) && $_GET['token'] === 'apexcybernet-admin-2026-token') {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_username'] = 'admin';
    $_SESSION['admin_role'] = 'admin';
}
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: ' . base_url('admin/'));
    exit;
}

ensure_reserved_columns($pdo);

$valid_games = [
    'valorant'  => 'Valorant',
    'crossfire' => 'CrossFire',
    'dota2'     => 'Dota 2',
];
$game_icons = [
    'valorant'  => 'bi-crosshair',
    'crossfire' => 'bi-bullseye',
    'dota2'     => 'bi-controller',
];

$message = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_waitlist_lock') {
        $g = $_POST['game'] ?? '';
        if (isset($valid_games[$g])) {
            $new = !is_waitlist_locked($pdo, $g);
            set_waitlist_locked($pdo, $g, $new);
            $message = ($new ? 'Locked' : 'Unlocked') . ' waitlist auto-replace for ' . $valid_games[$g] . '.';
            $msg_type = 'success';
        }
    }

    if ($action === 'quick_edit_team') {
        $tid = (int)($_POST['team_id'] ?? 0);
        if ($tid > 0) {
            $cur = $pdo->prepare("SELECT game FROM teams WHERE id = ?");
            $cur->execute([$tid]);
            $cur_row = $cur->fetch();
            $game = $cur_row['game'] ?? 'dota2';
            $rank_tiers = [
                'valorant'  => ['Iron','Bronze','Silver','Gold','Platinum','Diamond','Ascendant','Immortal','Radiant'],
                'crossfire' => ['Bronze','Silver','Gold','Platinum','Diamond','Master','Grand Master'],
                'dota2'     => ['Herald','Guardian','Crusader','Archon','Legend','Ancient','Divine','Immortal'],
            ];
            $allowed = $rank_tiers[$game] ?? [];
            $parts = [];
            $names = [];
            for ($i = 1; $i <= 5; $i++) {
                $n = trim($_POST["member_name_$i"] ?? '');
                $r = trim($_POST["member_rank_$i"] ?? '');
                if ($r !== '' && $allowed && !in_array($r, $allowed)) $r = '';
                $names[] = $n;
                if ($n !== '') $parts[] = $n . ':' . $r;
            }
            $sub = trim($_POST['substitute'] ?? '');
            $members_ranks = implode('|', $parts);
            $upd = $pdo->prepare("UPDATE teams SET member_1=?, member_2=?, member_3=?, member_4=?, member_5=?, substitute=?, members_ranks=? WHERE id = ?");
            $upd->execute([$names[0], $names[1], $names[2], $names[3], $names[4], $sub, $members_ranks, $tid]);
            $message = 'Team roster updated.';
            $msg_type = 'success';
        }
    }

    if ($action === 'quick_edit_solo') {
        $sid = (int)($_POST['solo_id'] ?? 0);
        if ($sid > 0) {
            $cur = $pdo->prepare("SELECT game FROM solo_players WHERE id = ?");
            $cur->execute([$sid]);
            $cur_row = $cur->fetch();
            $game = $cur_row['game'] ?? 'dota2';
            $rank_tiers = [
                'valorant'  => ['Iron','Bronze','Silver','Gold','Platinum','Diamond','Ascendant','Immortal','Radiant'],
                'crossfire' => ['Bronze','Silver','Gold','Platinum','Diamond','Master','Grand Master'],
                'dota2'     => ['Herald','Guardian','Crusader','Archon','Legend','Ancient','Divine','Immortal'],
            ];
            $allowed = $rank_tiers[$game] ?? [];
            $name = trim($_POST['player_name'] ?? '');
            $rank = trim($_POST['rank_tier'] ?? '');
            if ($rank !== '' && $allowed && !in_array($rank, $allowed)) $rank = '';
            $upd = $pdo->prepare("UPDATE solo_players SET player_name=?, rank_tier=? WHERE id=?");
            $upd->execute([$name, $rank, $sid]);
            $message = 'Solo player updated.';
            $msg_type = 'success';
        }
    }

    if ($action === 'toggle_reserve') {
        $type = $_POST['type'] ?? '';
        $id   = (int)($_POST['id'] ?? 0);
        $to_reserved = (int)($_POST['to_reserved'] ?? 0);
        if ($id > 0 && in_array($type, ['team', 'solo'])) {
            $table = $type === 'team' ? 'teams' : 'solo_players';
            $stmt = $pdo->prepare("UPDATE {$table} SET reserved = ? WHERE id = ?");
            $stmt->execute([$to_reserved ? 1 : 0, $id]);
            $label = $type === 'team' ? 'Team' : 'Solo';
            $message = $label . ' ' . ($to_reserved ? 'reserved' : 'unreserved') . '.';
            $msg_type = 'success';
        }
    }
}

$reserved_teams = $pdo->query("SELECT id, game, team_name, team_logo, status, members_ranks, ref_code FROM teams WHERE reserved = 1 ORDER BY game, team_name")->fetchAll();
$reserved_solos = $pdo->query("SELECT id, game, player_name, rank_tier, preferred_role, profile_photo, status, ref_code FROM solo_players WHERE reserved = 1 ORDER BY game, player_name")->fetchAll();

$rank_tiers = [
    'valorant'  => ['Iron','Bronze','Silver','Gold','Platinum','Diamond','Ascendant','Immortal','Radiant'],
    'crossfire' => ['Bronze','Silver','Gold','Platinum','Diamond','Master','Grand Master'],
    'dota2'     => ['Herald','Guardian','Crusader','Archon','Legend','Ancient','Divine','Immortal'],
];

// All teams for quick-edit (includes reserved too, so admin can fix ranks on anyone)
$all_teams = $pdo->query("SELECT id, game, team_name, member_1, member_2, member_3, member_4, member_5, substitute, members_ranks, status, reserved FROM teams ORDER BY game, team_name")->fetchAll();
$all_solos = $pdo->query("SELECT id, game, player_name, rank_tier, status, reserved FROM solo_players ORDER BY game, player_name")->fetchAll();

$active_teams_by_game = [];
foreach ($valid_games as $slug => $_n) {
    $q = $pdo->prepare("SELECT id, team_name, status FROM teams WHERE game = ? AND reserved = 0 ORDER BY status, team_name");
    $q->execute([$slug]);
    $active_teams_by_game[$slug] = $q->fetchAll();
}
$active_solos_by_game = [];
foreach ($valid_games as $slug => $_n) {
    $q = $pdo->prepare("SELECT id, player_name, status FROM solo_players WHERE game = ? AND reserved = 0 ORDER BY status, player_name");
    $q->execute([$slug]);
    $active_solos_by_game[$slug] = $q->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Tools — Apex Cybernet</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= base_url('css/app.css') ?>">
    <style>
        .qr-tab { background:transparent; border:1px solid var(--border); color:var(--text-muted); padding:0.35rem 0.85rem; border-radius:6px; cursor:pointer; font-family:inherit; font-weight:700; font-size:0.78rem; }
        .qr-tab:hover { color:var(--text); border-color:var(--accent); }
        .qr-tab-active { background:rgba(124,58,237,0.15); color:#c4b5fd; border-color:rgba(124,58,237,0.4); }
        .qr-card { background:var(--bg-dark); border:1px solid var(--border); border-radius:10px; }
        .qr-card summary { display:flex; align-items:center; gap:0.75rem; padding:0.6rem 0.9rem; cursor:pointer; list-style:none; user-select:none; font-size:0.85rem; }
        .qr-card summary::-webkit-details-marker { display:none; }
        .qr-card summary:hover { background:rgba(255,255,255,0.02); }
        .qr-card-title { font-weight:800; color:var(--text); flex:1; }
        .qr-card-meta { font-size:0.72rem; color:var(--text-muted); }
        .qr-caret { color:var(--text-muted); transition:transform 0.15s; }
        .qr-card[open] .qr-caret { transform:rotate(180deg); }
        .qr-form { padding:0.6rem 0.9rem 0.85rem; border-top:1px solid var(--border); display:flex; flex-direction:column; gap:0.45rem; }
        .qr-row { display:grid; grid-template-columns:50px 1fr 160px; gap:0.5rem; align-items:center; }
        .qr-row-num { font-size:0.72rem; color:var(--text-muted); font-weight:700; }
        .qr-row input, .qr-row select {
            background:var(--bg); color:var(--text); border:1px solid var(--border); border-radius:6px;
            padding:0.35rem 0.55rem; font-size:0.82rem; font-family:inherit;
        }
        .qr-row input:focus, .qr-row select:focus { border-color:var(--accent); outline:none; }
        .qr-actions { display:flex; gap:0.5rem; align-items:center; margin-top:0.35rem; }
        .qr-link { color:var(--accent-light); font-size:0.75rem; text-decoration:none; margin-left:auto; }
        .qr-link:hover { color:#c4b5fd; }
    </style>
</head>
<body>

<div class="admin-container">
    <div class="admin-header">
        <div>
            <h1><i class="bi bi-tools"></i> Admin Tools</h1>
            <p style="color:var(--text-muted); font-size:0.9rem; margin-top:0.25rem;">Operational controls — waitlist lock, reserved slots, and game-level switches.</p>
        </div>
        <div class="admin-header-actions">
            <a href="<?= base_url('admin/') ?>" class="btn-back-site"><i class="bi bi-arrow-left"></i> Dashboard</a>
            <a href="<?= base_url('admin/brackets.php') ?>" class="btn-back-site"><i class="bi bi-diagram-3"></i> Brackets</a>
            <a href="<?= base_url() ?>" class="btn-back-site"><i class="bi bi-house"></i> Site</a>
            <a href="<?= base_url('admin/logout.php') ?>" class="btn-logout"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert-custom alert-<?= $msg_type ?>" style="margin-bottom:1.5rem;"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- Waitlist Auto-Replace Lock (per game) -->
    <div class="admin-section">
        <h2><i class="bi bi-lock"></i> Waitlist Auto-Replace Lock</h2>
        <p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:1rem;">
            When <strong>unlocked</strong>, a paid waitlist team automatically jumps ahead of any unpaid main-list team on every page load.
            When <strong>locked</strong>, main-list slots freeze in registration order — paid waitlist teams stay on the waitlist until you unlock or reserve someone out.
        </p>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:0.75rem;">
            <?php foreach ($valid_games as $slug => $name):
                $locked = is_waitlist_locked($pdo, $slug);
            ?>
                <div style="border:1px solid <?= $locked ? 'rgba(251,191,36,0.4)' : 'var(--border)' ?>; border-left:3px solid <?= $locked ? '#fbbf24' : '#22c55e' ?>; border-radius:10px; padding:0.9rem 1rem; background:var(--bg-dark);">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:0.75rem;">
                        <div>
                            <div style="font-weight:800; font-size:1rem;">
                                <i class="bi <?= $game_icons[$slug] ?? 'bi-controller' ?>"></i> <?= htmlspecialchars($name) ?>
                            </div>
                            <div style="font-size:0.75rem; color:<?= $locked ? '#fbbf24' : '#86efac' ?>; font-weight:700; margin-top:0.2rem;">
                                <i class="bi <?= $locked ? 'bi-lock-fill' : 'bi-unlock' ?>"></i>
                                <?= $locked ? 'LOCKED' : 'UNLOCKED' ?>
                            </div>
                        </div>
                        <form method="POST" onsubmit="return confirm('<?= $locked ? "Unlock auto-replace for {$name}? Paid waitlist teams will be allowed to bump unpaid main-list teams again." : "Lock auto-replace for {$name}? Slots will freeze at the current registration order." ?>');">
                            <input type="hidden" name="action" value="toggle_waitlist_lock">
                            <input type="hidden" name="game" value="<?= $slug ?>">
                            <button type="submit" style="background:<?= $locked ? 'linear-gradient(135deg,#22c55e,#16a34a)' : 'linear-gradient(135deg,#fbbf24,#d97706)' ?>; color:#0b0b0b; border:none; padding:0.5rem 1rem; font-size:0.78rem; font-weight:800; border-radius:8px; cursor:pointer; font-family:inherit; display:inline-flex; align-items:center; gap:0.3rem;">
                                <i class="bi <?= $locked ? 'bi-unlock' : 'bi-lock-fill' ?>"></i>
                                <?= $locked ? 'Unlock' : 'Lock' ?>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Quick Rename / Rank Editor -->
    <div class="admin-section">
        <h2><i class="bi bi-pencil-square"></i> Quick Rename / Rank Editor</h2>
        <p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:0.85rem;">
            Rename team members or fix ranks without creating a temp PHP page. Type a team or player name to filter, expand a card to edit.
        </p>
        <input type="text" id="qrSearch" placeholder="Search team or player name…" oninput="qrFilter()"
               style="width:100%; max-width:420px; padding:0.55rem 0.8rem; font-size:0.88rem; background:var(--bg-dark); border:1px solid var(--border); border-radius:8px; color:var(--text); font-family:inherit; margin-bottom:1rem;">

        <div style="display:flex; gap:0.5rem; margin-bottom:0.75rem; font-size:0.8rem;">
            <button type="button" onclick="qrTab('teams')" id="qrTabTeams" class="qr-tab qr-tab-active">Teams (<?= count($all_teams) ?>)</button>
            <button type="button" onclick="qrTab('solos')" id="qrTabSolos" class="qr-tab">Solo Players (<?= count($all_solos) ?>)</button>
        </div>

        <!-- Team cards -->
        <div id="qrTeams" style="display:flex; flex-direction:column; gap:0.5rem;">
            <?php foreach ($all_teams as $t):
                $game_ranks = $rank_tiers[$t['game']] ?? [];
                $mr = [];
                if (!empty($t['members_ranks'])) {
                    foreach (explode('|', $t['members_ranks']) as $e) {
                        $p = explode(':', $e, 2);
                        $mr[] = ['name' => $p[0] ?? '', 'rank' => $p[1] ?? ''];
                    }
                }
                // Pad to 5, also fall back to member_N if members_ranks is empty
                for ($i = count($mr); $i < 5; $i++) {
                    $mr[] = ['name' => $t['member_' . ($i + 1)] ?? '', 'rank' => ''];
                }
                $searchable = strtolower($t['team_name'] . ' ' . implode(' ', array_column($mr, 'name')));
            ?>
                <details class="qr-card" data-search="<?= htmlspecialchars($searchable, ENT_QUOTES) ?>" data-kind="team">
                    <summary>
                        <span class="qr-card-title"><?= htmlspecialchars($t['team_name']) ?></span>
                        <span class="qr-card-meta"><?= htmlspecialchars($valid_games[$t['game']] ?? $t['game']) ?> · <?= $t['status'] === 'approved' ? 'paid' : $t['status'] ?><?= $t['reserved'] ? ' · reserved' : '' ?></span>
                        <i class="bi bi-chevron-down qr-caret"></i>
                    </summary>
                    <form method="POST" class="qr-form">
                        <input type="hidden" name="action" value="quick_edit_team">
                        <input type="hidden" name="team_id" value="<?= $t['id'] ?>">
                        <?php for ($i = 1; $i <= 5; $i++):
                            $m = $mr[$i - 1];
                        ?>
                            <div class="qr-row">
                                <span class="qr-row-num"><?= $i ?><?= $i === 1 ? ' <span style="color:#fbbf24;">(cap)</span>' : '' ?></span>
                                <input type="text" name="member_name_<?= $i ?>" value="<?= htmlspecialchars($m['name']) ?>" placeholder="Member <?= $i ?> name">
                                <select name="member_rank_<?= $i ?>">
                                    <option value="">— rank —</option>
                                    <?php foreach ($game_ranks as $r): ?>
                                        <option value="<?= $r ?>" <?= $m['rank'] === $r ? 'selected' : '' ?>><?= $r ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endfor; ?>
                        <div class="qr-row">
                            <span class="qr-row-num">sub</span>
                            <input type="text" name="substitute" value="<?= htmlspecialchars($t['substitute'] ?? '') ?>" placeholder="Substitute (optional)">
                            <span></span>
                        </div>
                        <div class="qr-actions">
                            <button type="submit" class="mc-save"><i class="bi bi-check-lg"></i> Save</button>
                            <a href="<?= base_url('admin/edit.php?type=team&id=' . $t['id']) ?>" class="qr-link">Full editor →</a>
                        </div>
                    </form>
                </details>
            <?php endforeach; ?>
        </div>

        <!-- Solo cards (hidden by default) -->
        <div id="qrSolos" style="display:none; flex-direction:column; gap:0.5rem;">
            <?php foreach ($all_solos as $s):
                $game_ranks = $rank_tiers[$s['game']] ?? [];
                $searchable = strtolower($s['player_name']);
            ?>
                <details class="qr-card" data-search="<?= htmlspecialchars($searchable, ENT_QUOTES) ?>" data-kind="solo">
                    <summary>
                        <span class="qr-card-title"><?= htmlspecialchars($s['player_name']) ?></span>
                        <span class="qr-card-meta"><?= htmlspecialchars($valid_games[$s['game']] ?? $s['game']) ?> · <?= htmlspecialchars($s['rank_tier'] ?: 'no rank') ?> · <?= $s['status'] === 'approved' ? 'paid' : $s['status'] ?></span>
                        <i class="bi bi-chevron-down qr-caret"></i>
                    </summary>
                    <form method="POST" class="qr-form">
                        <input type="hidden" name="action" value="quick_edit_solo">
                        <input type="hidden" name="solo_id" value="<?= $s['id'] ?>">
                        <div class="qr-row">
                            <span class="qr-row-num">name</span>
                            <input type="text" name="player_name" value="<?= htmlspecialchars($s['player_name']) ?>">
                            <select name="rank_tier">
                                <option value="">— rank —</option>
                                <?php foreach ($game_ranks as $r): ?>
                                    <option value="<?= $r ?>" <?= $s['rank_tier'] === $r ? 'selected' : '' ?>><?= $r ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="qr-actions">
                            <button type="submit" class="mc-save"><i class="bi bi-check-lg"></i> Save</button>
                            <a href="<?= base_url('admin/edit.php?type=solo&id=' . $s['id']) ?>" class="qr-link">Full editor →</a>
                        </div>
                    </form>
                </details>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Reserve tool -->
    <div class="admin-section">
        <h2><i class="bi bi-bookmark-star"></i> Reserved Entries</h2>
        <p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:1rem;">
            Reserved teams / solos are <strong>paid</strong> but can't join the tournament start.
            They're excluded from the bracket and from matchmaking, but shown in the public Reserved section on the home page.
        </p>

        <?php if (empty($reserved_teams) && empty($reserved_solos)): ?>
            <div style="color:var(--text-muted); font-style:italic; padding:0.75rem 0;">No reserved entries yet.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Game</th>
                            <th>Name</th>
                            <th>Ref</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reserved_teams as $t): ?>
                            <tr>
                                <td><span class="member-tag"><i class="bi bi-people-fill"></i> Team</span></td>
                                <td><span class="admin-game-tag admin-game-<?= $t['game'] ?>"><i class="bi <?= $game_icons[$t['game']] ?? 'bi-controller' ?>"></i> <?= htmlspecialchars($valid_games[$t['game']] ?? $t['game']) ?></span></td>
                                <td>
                                    <div style="display:flex; align-items:center; gap:0.5rem;">
                                        <?php if (!empty($t['team_logo'])): ?>
                                            <img src="<?= base_url($t['team_logo']) ?>" alt="" style="width:24px; height:24px; border-radius:6px; object-fit:cover;">
                                        <?php endif; ?>
                                        <strong><?= htmlspecialchars($t['team_name']) ?></strong>
                                    </div>
                                </td>
                                <td><code style="font-size:0.72rem; color:var(--accent-light);"><?= htmlspecialchars($t['ref_code'] ?? '—') ?></code></td>
                                <td><span class="status-badge status-<?= $t['status'] ?>"><?= $t['status'] === 'approved' ? 'Locked In' : ucfirst($t['status']) ?></span></td>
                                <td>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Unreserve this team? They will be put back into the active pool.');">
                                        <input type="hidden" name="action" value="toggle_reserve">
                                        <input type="hidden" name="type" value="team">
                                        <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                        <input type="hidden" name="to_reserved" value="0">
                                        <button type="submit" style="background:rgba(34,197,94,0.15); color:#86efac; border:1px solid rgba(34,197,94,0.35); padding:0.35rem 0.75rem; border-radius:6px; cursor:pointer; font-family:inherit; font-size:0.78rem; font-weight:700;">
                                            <i class="bi bi-bookmark-x"></i> Unreserve
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php foreach ($reserved_solos as $s): ?>
                            <tr>
                                <td><span class="member-tag"><i class="bi bi-person-fill"></i> Solo</span></td>
                                <td><span class="admin-game-tag admin-game-<?= $s['game'] ?>"><i class="bi <?= $game_icons[$s['game']] ?? 'bi-controller' ?>"></i> <?= htmlspecialchars($valid_games[$s['game']] ?? $s['game']) ?></span></td>
                                <td>
                                    <div style="display:flex; align-items:center; gap:0.5rem;">
                                        <?php if (!empty($s['profile_photo'])): ?>
                                            <img src="<?= base_url($s['profile_photo']) ?>" alt="" style="width:24px; height:24px; border-radius:50%; object-fit:cover;">
                                        <?php endif; ?>
                                        <strong><?= htmlspecialchars($s['player_name']) ?></strong>
                                        <span style="font-size:0.72rem; color:var(--accent-light);"><?= htmlspecialchars($s['rank_tier']) ?></span>
                                    </div>
                                </td>
                                <td><code style="font-size:0.72rem; color:var(--accent-light);"><?= htmlspecialchars($s['ref_code'] ?? '—') ?></code></td>
                                <td><span class="status-badge status-<?= $s['status'] ?>"><?= $s['status'] === 'approved' ? 'Locked In' : ucfirst($s['status']) ?></span></td>
                                <td>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Unreserve this solo? They will be put back into the active pool.');">
                                        <input type="hidden" name="action" value="toggle_reserve">
                                        <input type="hidden" name="type" value="solo">
                                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                        <input type="hidden" name="to_reserved" value="0">
                                        <button type="submit" style="background:rgba(34,197,94,0.15); color:#86efac; border:1px solid rgba(34,197,94,0.35); padding:0.35rem 0.75rem; border-radius:6px; cursor:pointer; font-family:inherit; font-size:0.78rem; font-weight:700;">
                                            <i class="bi bi-bookmark-x"></i> Unreserve
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Quick reserve -->
        <h3 style="font-size:1rem; font-weight:700; color:var(--accent-light); margin-top:1.5rem; margin-bottom:0.75rem;">
            <i class="bi bi-bookmark-star-fill"></i> Reserve an active entry
        </h3>
        <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:0.75rem;">
            Pick an active team or solo and reserve them — excludes them from the next bracket generation.
        </p>
        <form method="POST" style="display:flex; gap:0.5rem; flex-wrap:wrap; align-items:center;">
            <input type="hidden" name="action" value="toggle_reserve">
            <input type="hidden" name="to_reserved" value="1">
            <select name="type" id="reserve-type" class="form-select" style="width:auto; padding:0.4rem 0.6rem;" onchange="updateReserveOptions()">
                <option value="team">Team</option>
                <option value="solo">Solo</option>
            </select>
            <select name="id" id="reserve-target" class="form-select" style="width:320px; padding:0.4rem 0.6rem;">
                <?php foreach ($active_teams_by_game as $slug => $rows): ?>
                    <?php foreach ($rows as $r): ?>
                        <option value="<?= $r['id'] ?>" data-type="team"><?= htmlspecialchars($valid_games[$slug]) ?> · <?= htmlspecialchars($r['team_name']) ?> (<?= $r['status'] ?>)</option>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                <?php foreach ($active_solos_by_game as $slug => $rows): ?>
                    <?php foreach ($rows as $r): ?>
                        <option value="<?= $r['id'] ?>" data-type="solo" hidden><?= htmlspecialchars($valid_games[$slug]) ?> · <?= htmlspecialchars($r['player_name']) ?> (<?= $r['status'] ?>)</option>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </select>
            <button type="submit" style="background:linear-gradient(135deg,#a78bfa,#7c3aed); color:white; border:none; padding:0.5rem 1.2rem; font-size:0.85rem; font-weight:700; border-radius:8px; cursor:pointer; font-family:inherit; display:inline-flex; align-items:center; gap:0.35rem;">
                <i class="bi bi-bookmark-star-fill"></i> Reserve
            </button>
        </form>
    </div>
</div>

<script>
function qrTab(which) {
    document.getElementById('qrTeams').style.display = (which === 'teams') ? 'flex' : 'none';
    document.getElementById('qrSolos').style.display = (which === 'solos') ? 'flex' : 'none';
    document.getElementById('qrTabTeams').classList.toggle('qr-tab-active', which === 'teams');
    document.getElementById('qrTabSolos').classList.toggle('qr-tab-active', which === 'solos');
    qrFilter();
}
function qrFilter() {
    const q = (document.getElementById('qrSearch').value || '').trim().toLowerCase();
    document.querySelectorAll('.qr-card').forEach(el => {
        const match = !q || (el.dataset.search || '').indexOf(q) !== -1;
        el.style.display = match ? '' : 'none';
    });
}
function updateReserveOptions() {
    const kind = document.getElementById('reserve-type').value;
    const select = document.getElementById('reserve-target');
    [...select.options].forEach(o => {
        o.hidden = o.dataset.type !== kind;
    });
    const firstVisible = [...select.options].find(o => !o.hidden);
    if (firstVisible) select.value = firstVisible.value;
}
</script>

</body>
</html>
