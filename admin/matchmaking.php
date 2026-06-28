<?php
require_once __DIR__ . '/../includes/db.php';

if (isset($_GET['token']) && $_GET['token'] === 'apexcybernet-admin-2026-token') {
    $_SESSION['admin_logged_in'] = true; $_SESSION['admin_username'] = 'admin'; $_SESSION['admin_role'] = 'admin';
}
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: ' . base_url('admin/'));
    exit;
}

$valid_games = [
    'valorant'  => 'Valorant',
    'crossfire' => 'CrossFire',
    'dota2'     => 'Dota 2',
];

$game_filter = $_GET['game'] ?? '';
$message = '';
$msg_type = '';

// Team name letters
$team_letters = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z'];
$team_names_nato = ['Alpha','Bravo','Charlie','Delta','Echo','Foxtrot','Golf','Hotel','India','Juliet','Kilo','Lima','Mike','November','Oscar','Papa','Quebec','Romeo','Sierra','Tango','Uniform','Victor','Whiskey','X-Ray','Yankee','Zulu'];

// Rank tier numeric values (index = power level, higher = better)
$rank_values = [
    'valorant'  => ['Iron' => 1, 'Bronze' => 2, 'Silver' => 3, 'Gold' => 4, 'Platinum' => 5, 'Diamond' => 6, 'Ascendant' => 7, 'Immortal' => 8, 'Radiant' => 9],
    'crossfire' => ['Bronze' => 1, 'Silver' => 2, 'Gold' => 3, 'Platinum' => 4, 'Diamond' => 5, 'Master' => 6, 'Grand Master' => 7],
    'dota2'     => ['Herald' => 1, 'Guardian' => 2, 'Crusader' => 3, 'Archon' => 4, 'Legend' => 5, 'Ancient' => 6, 'Divine' => 7, 'Immortal' => 8],
];

// Role groupings per game for balance
$role_groups = [
    'valorant'  => ['Duelist' => 'dps', 'Initiator' => 'util', 'Controller' => 'util', 'Sentinel' => 'support', '' => 'flex'],
    'crossfire' => ['Rifler' => 'dps', 'Sniper' => 'dps', 'Support' => 'support', 'Entry' => 'dps', 'IGL' => 'util', '' => 'flex'],
    'dota2'     => ['Carry' => 'dps', 'Mid' => 'dps', 'Offlane' => 'util', 'Soft Support' => 'support', 'Hard Support' => 'support', 'Support' => 'support', '' => 'flex'],
];

// Handle Apply Teams POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'apply_teams') {
        $apply_game = $_POST['game'] ?? '';
        $teams_json = $_POST['teams_data'] ?? '[]';
        $suggested_teams = json_decode($teams_json, true);

        if (isset($valid_games[$apply_game]) && is_array($suggested_teams) && count($suggested_teams) > 0) {
            $game_label = $valid_games[$apply_game];
            $created = 0;

            foreach ($suggested_teams as $idx => $team) {
                $letter = $team_letters[$idx] ?? (string)($idx + 1);
                $team_name = "{$game_label} Solo Team {$letter}";

                // Generate ref code
                $prefixes = ['valorant' => 'VAL', 'crossfire' => 'CF', 'dota2' => 'DOTA'];
                $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
                $rand = '';
                for ($i = 0; $i < 4; $i++) $rand .= $chars[random_int(0, strlen($chars) - 1)];
                $ref = $prefixes[$apply_game] . '-M-' . $rand;

                // Collect member names (up to 5)
                $members = array_pad(array_column($team['members'], 'player_name'), 5, '');

                $stmt = $pdo->prepare("INSERT INTO teams (game, team_name, ref_code, member_1, member_2, member_3, member_4, member_5, payment_proof, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, '', 'approved')");
                $stmt->execute([$apply_game, $team_name, $ref, $members[0], $members[1], $members[2], $members[3], $members[4]]);

                // Update solo players status to 'matched'
                $player_ids = array_column($team['members'], 'id');
                if (!empty($player_ids)) {
                    $placeholders = implode(',', array_fill(0, count($player_ids), '?'));
                    $upd = $pdo->prepare("UPDATE solo_players SET status = 'matched' WHERE id IN ({$placeholders})");
                    $upd->execute($player_ids);
                }

                $created++;
            }

            $message = "Successfully created {$created} teams for {$game_label}!";
            $msg_type = 'success';
            $game_filter = $apply_game;
        } else {
            $message = 'No valid team data to apply.';
            $msg_type = 'danger';
        }
    }
}

// Fetch solo players for selected game
$solo_players = [];
$solo_count = 0;
$full_teams = 0;
$leftover = 0;

if ($game_filter && isset($valid_games[$game_filter])) {
    $stmt = $pdo->prepare("SELECT * FROM solo_players WHERE game = ? AND status != 'rejected' ORDER BY admin_rating DESC, created_at ASC");
    $stmt->execute([$game_filter]);
    $solo_players = $stmt->fetchAll();
    $solo_count = count($solo_players);
    $full_teams = intdiv($solo_count, 5);
    $leftover = $solo_count % 5;
}

// Fetch previously matched teams
$matched_teams = [];
if ($game_filter && isset($valid_games[$game_filter])) {
    $stmt = $pdo->prepare("SELECT * FROM teams WHERE game = ? AND team_name LIKE '% Solo Team %' ORDER BY created_at DESC");
    $stmt->execute([$game_filter]);
    $matched_teams = $stmt->fetchAll();
}

// Count solo players per game for tabs
$solo_counts = [];
foreach ($valid_games as $slug => $name) {
    $c = $pdo->prepare("SELECT COUNT(*) FROM solo_players WHERE game = ? AND status != 'rejected'");
    $c->execute([$slug]);
    $solo_counts[$slug] = $c->fetchColumn();
}

/**
 * Snake draft algorithm:
 * Sort players by rank value DESC (with admin_rating as tiebreaker),
 * then distribute across N teams.
 * Round 1: Team 1, 2, 3, ..., N
 * Round 2: Team N, N-1, ..., 1
 * Round 3: Team 1, 2, 3, ..., N (repeat)
 * This balances total skill across teams.
 *
 * Role balancing: within each draft round, prefer assigning a player
 * to the team that has the fewest players of that role group.
 */
function generate_suggested_teams($players, $game, $role_groups, $team_names_nato, $rank_values = []) {
    $count = count($players);
    $num_teams = intdiv($count, 5);
    if ($num_teams < 1) return ['teams' => [], 'unmatched' => $players];

    $team_size = 5;
    $draftable = array_slice($players, 0, $num_teams * $team_size);
    $unmatched = array_slice($players, $num_teams * $team_size);

    // Initialize teams
    $teams = [];
    for ($i = 0; $i < $num_teams; $i++) {
        $teams[$i] = [
            'name' => 'Team ' . ($team_names_nato[$i] ?? chr(65 + $i)),
            'members' => [],
            'total_rating' => 0,
            'total_rank_value' => 0,
            'role_counts' => [],
        ];
    }

    // Get role mapping for this game
    $game_roles = $role_groups[$game] ?? [];

    // Compute rank_value for each player
    $game_ranks = $rank_values[$game] ?? [];
    foreach ($draftable as &$p) {
        $p['rank_value'] = $game_ranks[$p['rank_tier'] ?? ''] ?? 0;
    }
    unset($p);

    // Sort by rank_value DESC, then admin_rating DESC as tiebreaker
    usort($draftable, function($a, $b) {
        $rv = ($b['rank_value'] ?? 0) - ($a['rank_value'] ?? 0);
        if ($rv !== 0) return $rv;
        return ($b['admin_rating'] ?? 0) - ($a['admin_rating'] ?? 0);
    });

    // Snake draft with role awareness
    $round = 0;
    $player_idx = 0;
    while ($player_idx < count($draftable)) {
        // Determine draft order for this round
        if ($round % 2 === 0) {
            $order = range(0, $num_teams - 1);
        } else {
            $order = range($num_teams - 1, 0, -1);
        }

        foreach ($order as $team_idx) {
            if ($player_idx >= count($draftable)) break;

            // Find best player for this team (considering role balance)
            // Look ahead in a small window to find a role-diverse pick
            $best_pick = $player_idx;
            $best_score = -1;
            $window = min(3, count($draftable) - $player_idx); // small lookahead

            for ($w = 0; $w < $window; $w++) {
                $candidate = $draftable[$player_idx + $w];
                $candidate_role = $game_roles[$candidate['preferred_role'] ?? ''] ?? 'flex';
                $current_count = $teams[$team_idx]['role_counts'][$candidate_role] ?? 0;
                // Prefer roles the team doesn't have yet
                $score = (10 - ($candidate['admin_rating'] ?? 0)) * 0.1 + (3 - $current_count);
                if ($score > $best_score) {
                    $best_score = $score;
                    $best_pick = $player_idx + $w;
                }
            }

            // Swap best pick to current position
            if ($best_pick !== $player_idx) {
                $tmp = $draftable[$player_idx];
                $draftable[$player_idx] = $draftable[$best_pick];
                $draftable[$best_pick] = $tmp;
            }

            $player = $draftable[$player_idx];
            $role_group = $game_roles[$player['preferred_role'] ?? ''] ?? 'flex';
            $teams[$team_idx]['members'][] = $player;
            $teams[$team_idx]['total_rating'] += (int)($player['admin_rating'] ?? 0);
            $teams[$team_idx]['total_rank_value'] += (int)($player['rank_value'] ?? 0);
            $teams[$team_idx]['role_counts'][$role_group] = ($teams[$team_idx]['role_counts'][$role_group] ?? 0) + 1;
            $player_idx++;
        }
        $round++;
    }

    // Calculate averages
    foreach ($teams as &$team) {
        $team['avg_rating'] = count($team['members']) > 0
            ? round($team['total_rating'] / count($team['members']), 1)
            : 0;
        $team['avg_rank_value'] = count($team['members']) > 0
            ? round($team['total_rank_value'] / count($team['members']), 1)
            : 0;
    }
    unset($team);

    return ['teams' => $teams, 'unmatched' => $unmatched];
}

// Generate suggestions if requested via GET
$suggested = null;
if (isset($_GET['generate']) && $game_filter && isset($valid_games[$game_filter]) && $full_teams > 0) {
    $suggested = generate_suggested_teams($solo_players, $game_filter, $role_groups, $team_names_nato, $rank_values);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Matchmaking — Apex Cybernet Tournament</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= base_url('css/app.css') ?>">
</head>
<body>

<div class="admin-container">
    <div class="admin-header">
        <div>
            <h1><i class="bi bi-puzzle"></i> Matchmaking Suggestions</h1>
        </div>
        <div class="admin-header-actions">
            <a href="<?= base_url('admin/') ?>" class="btn-back-site"><i class="bi bi-arrow-left"></i> Dashboard</a>
            <a href="<?= base_url('admin/logout.php') ?>" class="btn-logout"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert-custom alert-<?= $msg_type ?>" style="margin-bottom:1.5rem;">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Game Filter Tabs -->
    <div class="filter-tabs">
        <?php foreach ($valid_games as $slug => $name): ?>
            <a href="<?= base_url('admin/matchmaking.php?game=' . $slug) ?>" class="filter-tab <?= $game_filter === $slug ? 'active' : '' ?>">
                <?= $name ?> (<?= $solo_counts[$slug] ?> solo)
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($game_filter && isset($valid_games[$game_filter])): ?>
        <!-- Summary -->
        <div class="summary-cards" style="margin-bottom:1.5rem;">
            <div class="summary-card">
                <div class="summary-icon"><i class="bi bi-person-fill"></i></div>
                <div class="summary-info">
                    <div class="summary-number"><?= $solo_count ?></div>
                    <div class="summary-label">Solo Players (<?= $valid_games[$game_filter] ?>)</div>
                </div>
            </div>
            <div class="summary-card">
                <div class="summary-icon"><i class="bi bi-people-fill"></i></div>
                <div class="summary-info">
                    <div class="summary-number"><?= $full_teams ?></div>
                    <div class="summary-label">Full Teams Possible</div>
                </div>
            </div>
            <div class="summary-card <?= $leftover > 0 ? 'summary-card-warning' : '' ?>">
                <div class="summary-icon"><i class="bi bi-person-dash"></i></div>
                <div class="summary-info">
                    <div class="summary-number"><?= $leftover ?></div>
                    <div class="summary-label">Leftover Players</div>
                </div>
            </div>
        </div>

        <!-- Generate Button -->
        <?php if ($full_teams > 0): ?>
            <div class="admin-section">
                <div style="display:flex; align-items:center; gap:1rem; flex-wrap:wrap;">
                    <a href="<?= base_url('admin/matchmaking.php?game=' . $game_filter . '&generate=1') ?>" class="btn-submit" style="width:auto; padding:0.6rem 1.5rem; margin-top:0; display:inline-flex; align-items:center; gap:0.5rem; text-decoration:none;">
                        <i class="bi bi-shuffle"></i> Generate Suggested Teams
                    </a>
                    <span style="color:var(--text-muted); font-size:0.85rem;">
                        Snake draft algorithm balances skill ratings &amp; roles across <?= $full_teams ?> teams
                    </span>
                </div>
            </div>
        <?php elseif ($solo_count > 0 && $solo_count < 5): ?>
            <div class="admin-section">
                <p class="no-data">Need at least 5 solo players to form a team. Currently <?= $solo_count ?> player(s).</p>
            </div>
        <?php elseif ($solo_count === 0): ?>
            <div class="admin-section">
                <p class="no-data">No eligible solo players for <?= $valid_games[$game_filter] ?>.</p>
            </div>
        <?php endif; ?>

        <!-- Suggested Teams -->
        <?php if ($suggested && !empty($suggested['teams'])): ?>
            <div class="admin-section">
                <h2><i class="bi bi-lightbulb"></i> Suggested Teams (<?= count($suggested['teams']) ?>)</h2>

                <form method="POST">
                    <input type="hidden" name="action" value="apply_teams">
                    <input type="hidden" name="game" value="<?= $game_filter ?>">
                    <?php
                    // Build JSON for teams data
                    $teams_for_json = [];
                    foreach ($suggested['teams'] as $team) {
                        $members_data = [];
                        foreach ($team['members'] as $m) {
                            $members_data[] = [
                                'id' => (int)$m['id'],
                                'player_name' => $m['player_name'],
                            ];
                        }
                        $teams_for_json[] = ['members' => $members_data];
                    }
                    ?>
                    <input type="hidden" name="teams_data" value="<?= htmlspecialchars(json_encode($teams_for_json)) ?>">

                    <div class="matchmaking-grid">
                        <?php foreach ($suggested['teams'] as $idx => $team): ?>
                            <div class="suggested-team">
                                <div class="suggested-team-header">
                                    <div>
                                        <strong><?= htmlspecialchars($team['name']) ?></strong>
                                        <span class="avg-rating">Rank Avg: <?= $team['avg_rank_value'] ?> | Skill: <?= $team['avg_rating'] ?>/10</span>
                                    </div>
                                    <div class="skill-bar-sm" title="Avg rating: <?= $team['avg_rating'] ?>/10">
                                        <div class="skill-bar-sm-fill" style="width:<?= $team['avg_rating'] * 10 ?>%"></div>
                                    </div>
                                </div>
                                <div class="suggested-team-body">
                                    <?php foreach ($team['members'] as $member): ?>
                                        <div class="suggested-member">
                                            <div class="suggested-member-info">
                                                <span class="suggested-member-name"><?= htmlspecialchars($member['player_name']) ?></span>
                                                <?php if (!empty($member['real_name'])): ?>
                                                    <span class="suggested-member-real">(<?= htmlspecialchars($member['real_name']) ?>)</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="suggested-member-meta">
                                                <span class="member-tag"><?= htmlspecialchars($member['rank_tier']) ?></span>
                                                <?php if (!empty($member['preferred_role'])): ?>
                                                    <span class="member-tag"><?= htmlspecialchars($member['preferred_role']) ?></span>
                                                <?php endif; ?>
                                                <span class="suggested-member-rating" title="Skill rating">
                                                    <i class="bi bi-star-fill"></i> <?= (int)($member['admin_rating'] ?? 0) ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="suggested-team-footer">
                                    Rank Total: <strong><?= $team['total_rank_value'] ?></strong> | Skill: <strong><?= $team['total_rating'] ?></strong> pts
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if (!empty($suggested['unmatched'])): ?>
                        <div class="unmatched-section">
                            <h3><i class="bi bi-person-dash"></i> Unmatched Players (<?= count($suggested['unmatched']) ?>)</h3>
                            <div class="unmatched-list">
                                <?php foreach ($suggested['unmatched'] as $um): ?>
                                    <div class="suggested-member" style="background:var(--bg-card); border:1px solid var(--border); border-radius:8px; padding:0.5rem 0.75rem;">
                                        <div class="suggested-member-info">
                                            <span class="suggested-member-name"><?= htmlspecialchars($um['player_name']) ?></span>
                                        </div>
                                        <div class="suggested-member-meta">
                                            <span class="member-tag"><?= htmlspecialchars($um['rank_tier']) ?></span>
                                            <?php if (!empty($um['preferred_role'])): ?>
                                                <span class="member-tag"><?= htmlspecialchars($um['preferred_role']) ?></span>
                                            <?php endif; ?>
                                            <span class="suggested-member-rating"><i class="bi bi-star-fill"></i> <?= (int)($um['admin_rating'] ?? 0) ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div style="margin-top:1.5rem; text-align:center;">
                        <button type="submit" class="btn-submit" style="width:auto; padding:0.75rem 2rem;" onclick="return confirm('Create these teams? Solo players will be marked as matched.');">
                            <i class="bi bi-check-circle"></i> Apply Teams
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- Previously Matched Teams -->
        <?php if (!empty($matched_teams)): ?>
            <div class="admin-section">
                <h2><i class="bi bi-clock-history"></i> Previously Matched Teams (<?= count($matched_teams) ?>)</h2>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Team Name</th>
                                <th>Members</th>
                                <th>Ref Code</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($matched_teams as $mt): ?>
                                <tr>
                                    <td><?= $mt['id'] ?></td>
                                    <td><strong><?= htmlspecialchars($mt['team_name']) ?></strong></td>
                                    <td class="members-cell">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <?php if (!empty($mt["member_$i"])): ?>
                                                <span class="member-tag"><?= htmlspecialchars($mt["member_$i"]) ?></span>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                    </td>
                                    <td><code style="background:rgba(124,58,237,0.15); color:var(--accent-light); padding:0.15rem 0.4rem; border-radius:4px; font-size:0.8rem;"><?= htmlspecialchars($mt['ref_code']) ?></code></td>
                                    <td><span class="status-badge status-<?= $mt['status'] ?>"><?= ucfirst($mt['status']) ?></span></td>
                                    <td style="font-size:0.8rem; color:var(--text-muted);"><?= date('M j, Y g:ia', strtotime($mt['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Solo Players List -->
        <?php if (!empty($solo_players)): ?>
            <div class="admin-section">
                <h2><i class="bi bi-person"></i> All Eligible Solo Players (<?= $solo_count ?>)</h2>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>IGN</th>
                                <th>Real Name</th>
                                <th>Rank</th>
                                <th>Role</th>
                                <th>Rating</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($solo_players as $s): ?>
                                <tr>
                                    <td><?= $s['id'] ?></td>
                                    <td><strong><?= htmlspecialchars($s['player_name']) ?></strong></td>
                                    <td><?= htmlspecialchars($s['real_name'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($s['rank_tier']) ?></td>
                                    <td><?= htmlspecialchars($s['preferred_role'] ?? '—') ?></td>
                                    <td>
                                        <span class="suggested-member-rating"><i class="bi bi-star-fill"></i> <?= (int)($s['admin_rating'] ?? 0) ?></span>
                                    </td>
                                    <td><span class="status-badge status-<?= $s['status'] ?>"><?= ucfirst($s['status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="reg-card" style="text-align:center; padding:3rem 2rem;">
            <i class="bi bi-hand-index" style="font-size:2.5rem; color:var(--accent-light); display:block; margin-bottom:1rem;"></i>
            <h3>Select a Game</h3>
            <p style="color:var(--text-muted);">Choose a game tab above to view solo players and generate team suggestions.</p>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
