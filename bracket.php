<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/bracket_logic.php';

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

$game = isset($_GET['game']) ? ($_GET['game'] ?: null) : 'dota2';
$pageTitle = 'Tournament Brackets — Apex Cybernet';
$pageDescription = 'Live double-elimination brackets for the Apex Cybernet Tournament — winners bracket, losers bracket, grand finals, real-time match updates and scores.';
$canonicalUrl = canonical_url('bracket.php');
$extraHead = breadcrumb_jsonld([
    ['name' => 'Home',             'url' => 'https://apexcybernet.com/'],
    ['name' => 'Tournament Bracket','url' => 'https://apexcybernet.com/bracket.php'],
]);

$autoRefresh = false;
if ($game && isset($valid_games[$game])) {
    $pageTitle = $valid_games[$game] . ' Bracket — Apex Cybernet Tournament';
    $stmt = $pdo->prepare("SELECT * FROM matches WHERE game = ? ORDER BY bracket_side ASC, round ASC, match_order ASC");
    $stmt->execute([$game]);
    $matches = $stmt->fetchAll();

    foreach ($matches as $m) {
        if ($m['status'] === 'live') { $autoRefresh = true; break; }
    }

    $bracket_data = [];
    foreach ($matches as $m) {
        $side = $m['bracket_side'] ?? 'winners';
        $bracket_data[$side][$m['round']][] = $m;
    }

    $rank_values = [
        'valorant'  => ['Iron' => 1, 'Bronze' => 2, 'Silver' => 3, 'Gold' => 4, 'Platinum' => 5, 'Diamond' => 6, 'Ascendant' => 7, 'Immortal' => 8, 'Radiant' => 9],
        'crossfire' => ['Bronze' => 1, 'Silver' => 2, 'Gold' => 3, 'Platinum' => 4, 'Diamond' => 5, 'Master' => 6, 'Grand Master' => 7],
        'dota2'     => ['Herald' => 1, 'Guardian' => 2, 'Crusader' => 3, 'Archon' => 4, 'Legend' => 5, 'Ancient' => 6, 'Divine' => 7, 'Immortal' => 8],
    ];
    $game_ranks = $rank_values[$game] ?? [];

    $team_info = [];
    $ti_stmt = $pdo->prepare("SELECT team_name, team_logo, status, members_ranks FROM teams WHERE game = ?");
    $ti_stmt->execute([$game]);
    foreach ($ti_stmt->fetchAll() as $ti) {
        $sum = 0;
        if (!empty($ti['members_ranks']) && !empty($game_ranks)) {
            foreach (explode('|', $ti['members_ranks']) as $entry) {
                $parts = explode(':', $entry, 2);
                $r = trim($parts[1] ?? '');
                if ($r !== '' && isset($game_ranks[$r])) $sum += $game_ranks[$r];
            }
        }
        // Organizer override: team jakolerns is boosted to Immortal across the board
        if (strtolower(trim($ti['team_name'])) === 'team jakolerns' && !empty($game_ranks)) {
            $sum = max(array_values($game_ranks)) * 5;
        }
        $team_info[$ti['team_name']] = [
            'logo'      => $ti['team_logo'],
            'paid'      => $ti['status'] === 'approved',
            'rank_sum'  => $sum,
        ];
    }

    // Count stats
    $total_matches = count($matches);
    $completed_matches = count(array_filter($matches, fn($m) => $m['status'] === 'completed'));
    $live_matches = count(array_filter($matches, fn($m) => $m['status'] === 'live'));
}

if ($autoRefresh) {
    $extraHead = '<meta http-equiv="refresh" content="15">';
}
include __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/he-chrome.php';
?>

<?php if (!$game || !isset($valid_games[$game])): ?>
    <section class="he-page-hero">
        <div class="he-page-eyebrow">Brackets</div>
        <h1 class="he-page-title">Pick a game.</h1>
        <p class="he-page-sub">Live double-elimination brackets for every event. Auto-refreshes when matches are live.</p>
    </section>

    <div class="he-card-wide">
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:14px;">
            <?php foreach ($valid_games as $slug => $name): ?>
                <a href="<?= base_url('bracket.php?game=' . $slug) ?>"
                   style="display:flex; align-items:center; gap:14px; padding:20px; background:var(--bg-card); border:1px solid var(--border); border-radius:14px; text-decoration:none; transition:border-color 0.15s; box-shadow:0 1px 2px rgba(15,23,42,0.04);">
                    <div style="width:44px; height:44px; background:var(--bg-subtle); border-radius:10px; display:flex; align-items:center; justify-content:center; color:var(--accent-light); font-size:20px;">
                        <i class="bi <?= $game_icons[$slug] ?>"></i>
                    </div>
                    <div style="flex:1; min-width:0;">
                        <div style="font-weight:700; color:var(--text); font-size:15px;"><?= $name ?></div>
                        <div style="font-size:12.5px; color:var(--text-muted); margin-top:2px;">View bracket →</div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

<?php else: ?>
    <section class="he-page-hero">
        <div class="he-page-eyebrow">
            <a href="<?= base_url('bracket.php') ?>" style="color:var(--text-muted); text-decoration:none;">All brackets</a> · <?= htmlspecialchars($valid_games[$game]) ?>
        </div>
        <h1 class="he-page-title"><?= htmlspecialchars($valid_games[$game]) ?> bracket.</h1>
        <?php if (!empty($bracket_data)): ?>
            <p class="he-page-sub" style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                <?php if ($live_matches > 0): ?>
                    <span style="display:inline-flex; align-items:center; gap:6px; padding:4px 10px; background:rgba(185,28,28,0.10); color:var(--danger); border:1px solid rgba(185,28,28,0.25); border-radius:999px; font-weight:600; font-size:12.5px;">
                        <span style="width:7px;height:7px;border-radius:50%;background:var(--danger);box-shadow:0 0 0 3px rgba(185,28,28,0.20);animation:pulse 2s infinite;"></span>
                        <?= $live_matches ?> live
                    </span>
                <?php endif; ?>
                <span style="font-family:var(--mono); font-variant-numeric:tabular-nums; font-size:14px; color:var(--text-body);"><?= $completed_matches ?>/<?= $total_matches ?> matches played</span>
            </p>
        <?php else: ?>
            <p class="he-page-sub">Live double elimination · winners, losers, grand finals.</p>
        <?php endif; ?>
    </section>

    <?php if (empty($bracket_data)): ?>
        <div class="he-card" style="max-width:560px;">
            <div class="he-card-inner" style="text-align:center; padding:48px 32px;">
                <div style="font-size:36px; color:var(--text-subtle); margin-bottom:14px;">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <h2 style="margin:0 0 8px; font-size:20px; letter-spacing:-0.02em; color:var(--text);">Bracket coming soon</h2>
                <p style="margin:0; color:var(--text-muted); font-size:14px;">The bracket is generated once enough teams have registered. Check back closer to tournament day.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="he-card-wide">
            <div class="he-notice" style="margin-bottom:18px;">
                <i class="bi bi-info-circle-fill" style="color:var(--accent-light);"></i>
                <div>All registered teams are shown. Entry is <strong>₱550/team · ₱110/solo</strong>. Slots are first-come, first-served. The bracket is a live preview and re-seeds as more teams register; it locks at the registration cut-off.</div>
            </div>

        <?php
        $side_meta = [
            'winners' => ['label' => 'Winners Bracket', 'icon' => 'bi-trophy-fill',    'class' => 'winners'],
            'losers'  => ['label' => 'Losers Bracket',  'icon' => 'bi-arrow-repeat',    'class' => 'losers'],
            'grand'   => ['label' => 'Grand Finals',    'icon' => 'bi-star-fill',       'class' => 'grand'],
        ];
        foreach (['winners', 'losers', 'grand'] as $side):
            if (empty($bracket_data[$side])) continue;
            $meta = $side_meta[$side];
            $side_rounds = $bracket_data[$side];
        ?>
        <div class="bracket-section">
            <div class="bracket-section-header bracket-section-<?= $meta['class'] ?>">
                <i class="bi <?= $meta['icon'] ?>"></i>
                <span><?= $meta['label'] ?></span>
                <span class="bracket-section-count"><?= array_sum(array_map('count', $side_rounds)) ?> matches</span>
            </div>
            <div class="bracket-container">
                <?php
                $round_keys = array_keys($side_rounds);
                $total_rounds = count($round_keys);
                $rendered_rounds = 0;
                foreach ($round_keys as $idx => $round_num):
                    $round_matches = $side_rounds[$round_num];

                    // Filter out BYE matches
                    $visible_matches = array_filter($round_matches, function($m) {
                        return $m['team1_name'] !== 'BYE' && $m['team2_name'] !== 'BYE';
                    });
                    if (empty($visible_matches)) continue;

                    // Round label & format from bracket_logic
                    $label = bracketRoundName($side, $round_num);
                    $format = bracketMatchFormat($side, $round_num);
                ?>
                    <?php if ($rendered_rounds > 0): ?>
                        <div class="bracket-connector-col">
                            <div class="bracket-connector-line"></div>
                        </div>
                    <?php endif; ?>
                    <div class="bracket-round">
                        <div class="bracket-round-title"><?= $label ?> <span class="bracket-format-badge"><?= $format ?></span></div>
                        <?php foreach ($visible_matches as $m):
                            $t1_info = $team_info[$m['team1_name']] ?? null;
                            $t2_info = $team_info[$m['team2_name']] ?? null;
                            $is_live = $m['status'] === 'live';
                            $is_done = $m['status'] === 'completed';
                            $t1_is_tbd = empty($m['team1_name']) || $m['team1_name'] === 'TBD';
                            $t2_is_tbd = empty($m['team2_name']) || $m['team2_name'] === 'TBD';
                            $both_tbd = $t1_is_tbd && $t2_is_tbd;
                        ?>
                            <?php if ($both_tbd): ?>
                                <!-- Compact TBD placeholder -->
                                <div class="bracket-match tbd-match">
                                    <div class="tbd-placeholder">
                                        <i class="bi bi-clock-history"></i>
                                        <span>Awaiting results</span>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="bracket-match <?= $m['status'] ?>">
                                    <?php if ($is_live): ?>
                                        <div class="bracket-match-live-bar"></div>
                                    <?php endif; ?>
                                    <!-- Team 1 -->
                                    <div class="team-row <?= ($m['winner'] && $m['winner'] === $m['team1_name']) ? 'winner' : (($is_done && $m['winner'] && $m['winner'] !== $m['team1_name']) ? 'loser' : '') ?>">
                                        <?php if ($t1_is_tbd): ?>
                                            <div class="bracket-team-logo-placeholder"></div>
                                            <span class="team-name tbd">TBD</span>
                                        <?php else: ?>
                                            <?php if ($t1_info && !empty($t1_info['logo'])): ?>
                                                <img src="<?= base_url($t1_info['logo']) ?>" alt="" class="bracket-team-logo" loading="lazy" decoding="async">
                                            <?php else: ?>
                                                <div class="bracket-team-logo-placeholder"></div>
                                            <?php endif; ?>
                                            <span class="team-name"><?= htmlspecialchars($m['team1_name']) ?></span>
                                            <?php if ($t1_info && !empty($t1_info['rank_sum'])): ?>
                                                <span class="team-rank-sum" title="Team Power — sum of member rank tiers">Power <?= (int)$t1_info['rank_sum'] ?></span>
                                            <?php endif; ?>
                                            <?php if (strtolower(trim($m['team1_name'])) === 'team jakolerns'): ?>
                                                <span style="font-size:0.6rem; font-weight:700; color:#fbbf24; background:rgba(251,191,36,0.15); border:1px solid rgba(251,191,36,0.4); padding:0.1rem 0.4rem; border-radius:4px; white-space:nowrap;" title="Rank raised to Immortal by organizers">★ Immortal</span>
                                            <?php endif; ?>
                                            <?php if ($t1_info && !$t1_info['paid']): ?>
                                                <span class="bracket-unpaid" title="Unpaid — may be replaced"><i class="bi bi-exclamation-circle-fill"></i></span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <span class="team-score"><?= $m['team1_score'] ?></span>
                                    </div>
                                    <div class="team-row-divider"></div>
                                    <!-- Team 2 -->
                                    <div class="team-row <?= ($m['winner'] && $m['winner'] === $m['team2_name']) ? 'winner' : (($is_done && $m['winner'] && $m['winner'] !== $m['team2_name']) ? 'loser' : '') ?>">
                                        <?php if ($t2_is_tbd): ?>
                                            <div class="bracket-team-logo-placeholder"></div>
                                            <span class="team-name tbd">TBD</span>
                                        <?php else: ?>
                                            <?php if ($t2_info && !empty($t2_info['logo'])): ?>
                                                <img src="<?= base_url($t2_info['logo']) ?>" alt="" class="bracket-team-logo" loading="lazy" decoding="async">
                                            <?php else: ?>
                                                <div class="bracket-team-logo-placeholder"></div>
                                            <?php endif; ?>
                                            <span class="team-name"><?= htmlspecialchars($m['team2_name']) ?></span>
                                            <?php if ($t2_info && !empty($t2_info['rank_sum'])): ?>
                                                <span class="team-rank-sum" title="Team Power — sum of member rank tiers">Power <?= (int)$t2_info['rank_sum'] ?></span>
                                            <?php endif; ?>
                                            <?php if (strtolower(trim($m['team2_name'])) === 'team jakolerns'): ?>
                                                <span style="font-size:0.6rem; font-weight:700; color:#fbbf24; background:rgba(251,191,36,0.15); border:1px solid rgba(251,191,36,0.4); padding:0.1rem 0.4rem; border-radius:4px; white-space:nowrap;" title="Rank raised to Immortal by organizers">★ Immortal</span>
                                            <?php endif; ?>
                                            <?php if ($t2_info && !$t2_info['paid']): ?>
                                                <span class="bracket-unpaid" title="Unpaid — may be replaced"><i class="bi bi-exclamation-circle-fill"></i></span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <span class="team-score"><?= $m['team2_score'] ?></span>
                                    </div>
                                    <div class="match-footer">
                                        <?php if ($is_live): ?>
                                            <span class="match-status match-status-live"><span class="live-dot"></span> LIVE</span>
                                        <?php elseif ($is_done): ?>
                                            <span class="match-status match-status-completed"><i class="bi bi-check-circle-fill"></i> Done</span>
                                        <?php else: ?>
                                            <span class="match-status match-status-pending">Upcoming</span>
                                        <?php endif; ?>
                                        <?php if ($m['scheduled_at']): ?>
                                            <span class="match-time"><i class="bi bi-clock"></i> <?= date('M j, g:i A', strtotime($m['scheduled_at'])) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <?php $rendered_rounds++; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        </div><!-- /.he-card-wide -->

    <?php if ($autoRefresh): ?>
        <div style="max-width:1120px; margin:0 auto; padding:16px 24px 0; text-align:center; font-size:12.5px; color:var(--text-muted); display:flex; align-items:center; justify-content:center; gap:8px;">
            <i class="bi bi-arrow-repeat" style="animation:spin 1.5s linear infinite;"></i>
            Auto-refreshing every 15s · <?= date('g:i:s A') ?>
            <style>@keyframes spin{to{transform:rotate(360deg);}}</style>
        </div>
    <?php endif; ?>

    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/includes/he-foot.php'; return; ?>
