<?php
require_once __DIR__ . '/includes/db.php';

$valid_games = [
    'valorant'  => 'Valorant',
    'crossfire' => 'CrossFire',
    'dota2'     => 'Dota 2',
];

$pageTitle = 'Hall of Fame — Argonar Dota 2 Tournament Champions';
$pageDescription = 'Champions, all-time records, and award-winning players of the Argonar Dota 2 Tournament. Every match on record. Every win in history.';
$canonicalUrl = canonical_url('leaderboard.php');
$extraHead = breadcrumb_jsonld([
    ['name' => 'Home',         'url' => 'https://argonar.co/'],
    ['name' => 'Hall of Fame', 'url' => 'https://argonar.co/leaderboard.php'],
]);

// Champions — tournament results
$results = $pdo->query("SELECT * FROM tournament_results ORDER BY game ASC, season DESC, placement ASC")->fetchAll();
$grouped = [];
foreach ($results as $r) {
    $grouped[$r['game']][$r['season']][] = $r;
}

// All-time records — W/L from completed matches
$all_teams_raw = $pdo->query("
    SELECT team_name, game,
        SUM(CASE WHEN winner = team_name THEN 1 ELSE 0 END) as wins,
        COUNT(*) as total
    FROM (
        SELECT team1_name AS team_name, game, winner FROM matches WHERE status = 'completed' AND team1_name != 'BYE' AND team1_name != 'TBD'
        UNION ALL
        SELECT team2_name AS team_name, game, winner FROM matches WHERE status = 'completed' AND team2_name != 'BYE' AND team2_name != 'TBD'
    ) AS all_matches
    GROUP BY team_name, game
    ORDER BY wins DESC, total ASC
")->fetchAll();

// Titles — from accounts table
$titled_players = $pdo->query("
    SELECT a.id, a.display_name, a.titles, a.ref_code, a.ref_type,
           COALESCE(t.team_name, s.player_name) AS name
    FROM accounts a
    LEFT JOIN teams t ON a.ref_type = 'team' AND a.ref_code = t.ref_code
    LEFT JOIN solo_players s ON a.ref_type = 'solo' AND a.ref_code = s.ref_code
    WHERE a.titles IS NOT NULL AND a.titles != '' AND a.titles != '[]'
    AND a.claim_status = 'approved'
")->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding: 2rem 1rem;">

    <div class="hero">
        <h1>Hall of Fame</h1>
        <p>The prize gets spent. The name stays here. Every match counts. Every win builds your legacy.</p>
    </div>

    <div style="max-width:800px; margin:0 auto;">

        <!-- Section: Champions -->
        <div style="margin-bottom:2.5rem;">
            <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:1.25rem;">
                <i class="bi bi-trophy-fill" style="font-size:1.5rem; color:#fbbf24;"></i>
                <h2 style="font-size:1.3rem; font-weight:800; margin:0; color:var(--text);">Champions</h2>
            </div>

            <?php if (empty($grouped)): ?>
                <div style="text-align:center; padding:2rem; background:var(--bg-card); border:1px solid var(--border); border-radius:12px;">
                    <i class="bi bi-trophy" style="font-size:2.5rem; color:#fbbf24; display:block; margin-bottom:0.75rem;"></i>
                    <div style="font-weight:700; color:var(--text); margin-bottom:0.25rem;">The Stage is Set</div>
                    <div style="font-size:0.85rem; color:var(--text-muted);">No champions yet — Season 1 is about to begin. Will your name be the first one here?</div>
                    <a href="<?= base_url() ?>#games" class="btn-register" style="display:inline-flex; margin-top:1rem; padding:0.6rem 1.5rem; font-size:0.85rem;">
                        <i class="bi bi-controller"></i> Register Now
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($grouped as $game => $seasons): ?>
                    <?php foreach ($seasons as $season => $placements): ?>
                        <div style="margin-bottom:1rem;">
                            <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:0.5rem; font-weight:600;">
                                <?= htmlspecialchars($valid_games[$game] ?? $game) ?> &middot; <?= htmlspecialchars($season) ?>
                            </div>
                            <?php foreach ($placements as $p):
                                $medal_styles = [
                                    1 => ['bg' => 'rgba(251,191,36,0.1)', 'border' => 'rgba(251,191,36,0.3)', 'color' => '#fbbf24'],
                                    2 => ['bg' => 'rgba(148,163,184,0.1)', 'border' => 'rgba(148,163,184,0.3)', 'color' => '#94a3b8'],
                                    3 => ['bg' => 'rgba(205,127,50,0.1)', 'border' => 'rgba(205,127,50,0.3)', 'color' => '#cd7f32'],
                                ];
                                $ms = $medal_styles[$p['placement']] ?? ['bg' => 'var(--bg-dark)', 'border' => 'var(--border)', 'color' => 'var(--text-muted)'];
                                $place_labels = [1 => 'Champion', 2 => '2nd Place', 3 => '3rd Place'];
                            ?>
                                <div style="display:flex; align-items:center; gap:1rem; background:<?= $ms['bg'] ?>; border:1px solid <?= $ms['border'] ?>; border-radius:12px; padding:0.85rem 1.25rem; margin-bottom:0.5rem;">
                                    <span style="font-size:1.5rem; color:<?= $ms['color'] ?>; width:2rem; text-align:center;">
                                        <i class="bi bi-trophy-fill"></i>
                                    </span>
                                    <div style="flex:1;">
                                        <div style="font-weight:800; color:var(--text);"><?= htmlspecialchars($p['team_name']) ?></div>
                                        <div style="font-size:0.7rem; color:<?= $ms['color'] ?>; font-weight:600; margin-top:0.1rem;">
                                            <i class="bi bi-star-fill"></i> <?= $place_labels[$p['placement']] ?? $p['placement'] . 'th Place' ?>
                                        </div>
                                    </div>
                                    <?php if (!empty($p['prize'])): ?>
                                        <span style="font-size:0.8rem; color:var(--text-muted);"><?= htmlspecialchars($p['prize']) ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Section: All-Time Records -->
        <div style="margin-bottom:2.5rem;">
            <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:1.25rem;">
                <i class="bi bi-bar-chart-fill" style="font-size:1.3rem; color:#60a5fa;"></i>
                <h2 style="font-size:1.3rem; font-weight:800; margin:0; color:var(--text);">All-Time Records</h2>
            </div>

            <?php if (empty($all_teams_raw)): ?>
                <div style="text-align:center; padding:2rem; background:var(--bg-card); border:1px solid var(--border); border-radius:12px;">
                    <i class="bi bi-hourglass-split" style="font-size:2rem; color:var(--text-muted); display:block; margin-bottom:0.5rem;"></i>
                    <div style="font-size:0.85rem; color:var(--text-muted);">No matches played yet. Records will appear once the tournament begins.</div>
                </div>
            <?php else: ?>
                <div style="background:var(--bg-card); border:1px solid var(--border); border-radius:12px; overflow:hidden;">
                    <!-- Header -->
                    <div style="display:flex; padding:0.6rem 1rem; background:rgba(124,58,237,0.08); border-bottom:1px solid var(--border); font-size:0.7rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px;">
                        <span style="width:2rem; text-align:center;">#</span>
                        <span style="flex:1; padding-left:0.5rem;">Team</span>
                        <span style="width:3rem; text-align:center;">W</span>
                        <span style="width:3rem; text-align:center;">L</span>
                        <span style="width:4rem; text-align:center;">Rate</span>
                    </div>
                    <?php foreach ($all_teams_raw as $rank => $tr):
                        $losses = $tr['total'] - $tr['wins'];
                        $rate = $tr['total'] > 0 ? round(($tr['wins'] / $tr['total']) * 100) : 0;
                        $rate_color = $rate >= 70 ? 'var(--success)' : ($rate >= 50 ? '#fbbf24' : '#f87171');
                        $top3 = $rank < 3;
                    ?>
                        <div style="display:flex; align-items:center; padding:0.6rem 1rem; <?= $rank > 0 ? 'border-top:1px solid var(--border);' : '' ?> <?= $top3 ? 'background:rgba(251,191,36,0.03);' : '' ?>">
                            <span style="width:2rem; text-align:center; font-size:0.8rem; font-weight:800; color:<?= $rank === 0 ? '#fbbf24' : ($rank === 1 ? '#94a3b8' : ($rank === 2 ? '#cd7f32' : 'var(--text-muted)')) ?>;">
                                <?= $rank + 1 ?>
                            </span>
                            <div style="flex:1; padding-left:0.5rem; min-width:0;">
                                <span style="font-weight:700; font-size:0.85rem; color:var(--text);"><?= htmlspecialchars($tr['team_name']) ?></span>
                                <span style="font-size:0.65rem; color:var(--text-muted); margin-left:0.35rem;"><?= htmlspecialchars($valid_games[$tr['game']] ?? $tr['game']) ?></span>
                            </div>
                            <span style="width:3rem; text-align:center; font-weight:800; font-size:0.85rem; color:var(--success);"><?= $tr['wins'] ?></span>
                            <span style="width:3rem; text-align:center; font-weight:600; font-size:0.85rem; color:#f87171;"><?= $losses ?></span>
                            <span style="width:4rem; text-align:center; font-weight:700; font-size:0.8rem; color:<?= $rate_color ?>;"><?= $rate ?>%</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Section: Titles & Awards -->
        <div style="margin-bottom:2.5rem;">
            <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:1.25rem;">
                <i class="bi bi-award-fill" style="font-size:1.3rem; color:#fbbf24;"></i>
                <h2 style="font-size:1.3rem; font-weight:800; margin:0; color:var(--text);">Titles & Awards</h2>
            </div>

            <?php if (empty($titled_players)): ?>
                <div style="text-align:center; padding:2rem; background:var(--bg-card); border:1px solid var(--border); border-radius:12px;">
                    <i class="bi bi-award" style="font-size:2rem; color:var(--text-muted); display:block; margin-bottom:0.5rem;"></i>
                    <div style="font-size:0.85rem; color:var(--text-muted);">No titles awarded yet. Titles are earned through exceptional performance — MVP, undefeated runs, and more.</div>
                </div>
            <?php else: ?>
                <?php foreach ($titled_players as $tp):
                    $titles = json_decode($tp['titles'], true) ?: [];
                    $name = !empty($tp['display_name']) ? $tp['display_name'] : ($tp['name'] ?? 'Unknown');
                    if (empty($titles)) continue;
                ?>
                    <div style="background:var(--bg-card); border:1px solid rgba(251,191,36,0.2); border-radius:12px; padding:1rem 1.25rem; margin-bottom:0.5rem; display:flex; align-items:center; gap:1rem; flex-wrap:wrap;">
                        <div style="flex:1; min-width:0;">
                            <a href="<?= base_url('profile.php?id=' . $tp['id']) ?>" style="font-weight:700; font-size:0.95rem; color:var(--text); text-decoration:none;">
                                <?= htmlspecialchars($name) ?>
                            </a>
                            <div style="font-size:0.75rem; color:var(--text-muted);"><?= ucfirst($tp['ref_type']) ?></div>
                        </div>
                        <div style="display:flex; gap:0.35rem; flex-wrap:wrap;">
                            <?php foreach ($titles as $title): ?>
                                <span style="display:inline-flex; align-items:center; gap:0.2rem; font-size:0.7rem; font-weight:700; color:#fbbf24; background:rgba(251,191,36,0.1); border:1px solid rgba(251,191,36,0.25); padding:0.2rem 0.6rem; border-radius:6px;">
                                    <i class="bi bi-award-fill"></i> <?= htmlspecialchars($title) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
