<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bracket_logic.php';

$user    = current_user($pdo);
$user_id = $user ? (int)$user['id'] : 0;

const MIN_ENTRY = 10;

$pageTitle       = 'Predict — Match Predictions';
$pageDescription = 'Use H-Coins to predict bracket match outcomes. Pick the winning team and earn from the reward pool.';

$flash_msg  = '';
$flash_type = '';

// ── POST: place / update stake ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_id) {
    $match_id    = (int)($_POST['match_id'] ?? 0);
    $picked_team = trim($_POST['picked_team'] ?? '');
    $wager       = (int)($_POST['wager'] ?? 0);

    // Validate match: must be upcoming or pending (futures)
    $mq = $pdo->prepare("SELECT * FROM matches WHERE id = ? AND status IN ('upcoming', 'pending') AND game = 'dota2'");
    $mq->execute([$match_id]);
    $match = $mq->fetch();

    $is_futures = $match && $match['status'] === 'pending';

    if (!$match) {
        $flash_msg = 'That match is no longer open for predictions.'; $flash_type = 'danger';
    } elseif ($is_futures) {
        // Futures prediction: picked_team must be any real team in this game's bracket
        $valid_team = $pdo->prepare("SELECT 1 FROM teams WHERE game = 'dota2' AND team_name = ? AND status IN ('approved', 'pending')");
        $valid_team->execute([$picked_team]);
        if (!$valid_team->fetch()) {
            $flash_msg = 'Invalid team selection.'; $flash_type = 'danger';
            $match = null; // prevent further processing
        }
    } elseif (!in_array($picked_team, [$match['team1_name'], $match['team2_name']], true)) {
        $flash_msg = 'Invalid team selection.'; $flash_type = 'danger';
        $match = null;
    }

    if ($match && $wager < MIN_ENTRY) {
        $flash_msg = 'Minimum prediction is ' . MIN_ENTRY . ' H-Coins.'; $flash_type = 'danger';
    } elseif ($match) {
        // Check existing stake on this match
        $eq = $pdo->prepare("SELECT id, wager, status FROM match_predictions WHERE account_id = ? AND match_id = ?");
        $eq->execute([$user_id, $match_id]);
        $existing = $eq->fetch();

        if ($existing) {
            $flash_msg = 'You already placed a prediction on this match. Predictions cannot be changed.'; $flash_type = 'warning';
        } else {
            // New stake
            $bal_q = $pdo->prepare("SELECT h_coins FROM accounts WHERE id = ?");
            $bal_q->execute([$user_id]);
            $bal = (int)$bal_q->fetchColumn();

            if ($bal < $wager) {
                $flash_msg = 'Not enough H-Coins. <a href="' . base_url('coins.php') . '" style="color:inherit;text-decoration:underline;">Buy more</a>.';
                $flash_type = 'danger';
            } else {
                $pdo->beginTransaction();
                try {
                    $pdo->prepare("UPDATE accounts SET h_coins = h_coins - ? WHERE id = ? AND h_coins >= ?")->execute([$wager, $user_id, $wager]);
                    $pdo->prepare("INSERT INTO match_predictions (account_id, match_id, picked_team, wager, is_futures) VALUES (?, ?, ?, ?, ?)")->execute([$user_id, $match_id, $picked_team, $wager, $is_futures ? 1 : 0]);
                    $pdo->prepare("INSERT INTO h_coin_transactions (account_id, type, amount, reason, ref) VALUES (?, 'debit', ?, 'match_stake', ?)")->execute([$user_id, $wager, (string)$match_id]);
                    $pdo->commit();
                    $futures_label = $is_futures ? ' (Futures)' : '';
                    $flash_msg = "Prediction placed! Backing <strong>" . htmlspecialchars($picked_team) . "</strong> with {$wager} HC.{$futures_label}";
                    $flash_type = 'success';
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $flash_msg = 'Not enough H-Coins.'; $flash_type = 'danger';
                }
            }
        }
    }
}

// ── Reload balance ──
$h_coins = 0;
if ($user_id) {
    $bq = $pdo->prepare("SELECT h_coins FROM accounts WHERE id = ?");
    $bq->execute([$user_id]);
    $h_coins = (int)$bq->fetchColumn();
}

// ── Load all dota2 matches ──
$matches_q = $pdo->query("SELECT * FROM matches WHERE game = 'dota2' ORDER BY bracket_side ASC, round ASC, match_order ASC");
$all_matches = $matches_q->fetchAll();

// ── User's stakes ──
$my_stakes = [];
if ($user_id) {
    $sq = $pdo->prepare("SELECT match_id, picked_team, wager, status FROM match_predictions WHERE account_id = ?");
    $sq->execute([$user_id]);
    foreach ($sq->fetchAll() as $row) {
        $my_stakes[$row['match_id']] = $row;
    }
}

// ── Pool totals per match per team ──
$pools_q = $pdo->query("SELECT match_id, picked_team, SUM(wager) AS total FROM match_predictions WHERE status = 'active' GROUP BY match_id, picked_team");
$pools = [];
foreach ($pools_q->fetchAll() as $row) {
    $pools[$row['match_id']][$row['picked_team']] = (int)$row['total'];
}

// ── Tournament teams for futures prediction dropdown ──
$bracket_teams_q = $pdo->query("SELECT DISTINCT team_name FROM teams WHERE game = 'dota2' AND status IN ('approved', 'pending') ORDER BY team_name ASC");
$bracket_teams = $bracket_teams_q->fetchAll(PDO::FETCH_COLUMN);

// Group matches by bracket side, then round
$sections = [];
$side_labels = ['winners' => 'Winners Bracket', 'losers' => 'Losers Bracket', 'grand' => 'Grand Finals'];
foreach ($all_matches as $m) {
    $key = $side_labels[$m['bracket_side']] ?? ucfirst($m['bracket_side']) . ' Bracket';
    $sections[$key][$m['bracket_side'] . ':' . $m['round']][] = $m;
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
.predict-wrap { max-width: 720px; margin: 2rem auto; padding: 0 1rem 4rem; }

.predict-hero {
    background: linear-gradient(135deg, #1e1b4b 0%, #312e81 60%, #1e1b4b 100%);
    border-radius: 16px; padding: 1.75rem 1.5rem; text-align: center; margin-bottom: 1.75rem;
    border: 1px solid rgba(139,92,246,0.3);
}
.predict-hero h1 { font-size: 1.5rem; font-weight: 800; color: #fff; margin: 0 0 0.35rem; }
.predict-hero p  { color: var(--text-muted); font-size: 0.85rem; margin: 0; }
.balance-pill {
    display: inline-flex; align-items: center; gap: 0.4rem;
    background: rgba(139,92,246,0.2); border: 1px solid rgba(139,92,246,0.4);
    border-radius: 99px; padding: 0.3rem 0.9rem; font-size: 0.85rem; color: var(--accent-light);
    font-weight: 700; margin-top: 0.65rem;
}

.section-label {
    font-size: 0.82rem; font-weight: 700; margin: 1.75rem 0 0.6rem;
    padding: 0.55rem 0.85rem; border-radius: 8px; display: flex; align-items: center; gap: 0.5rem;
}
.section-label-winners { background: rgba(34,197,94,0.08); border: 1px solid rgba(34,197,94,0.2); color: var(--success); }
.section-label-losers  { background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.2); color: #fbbf24; }
.section-label-grand   { background: rgba(124,58,237,0.08); border: 1px solid rgba(124,58,237,0.2); color: var(--accent-light); }
.round-label {
    font-size: 0.72rem; color: var(--text-muted); font-weight: 600; margin: 0.85rem 0 0.4rem;
    padding-left: 0.25rem; display: flex; align-items: center;
}

.match-card {
    background: var(--card-bg); border: 1px solid var(--border);
    border-radius: 14px; padding: 1.1rem 1.25rem; margin-bottom: 0.75rem;
}
.match-card.is-completed { opacity: 0.7; }
.match-card.is-live { border-color: rgba(239,68,68,0.4); }

.matchup {
    display: flex; align-items: center; gap: 0.5rem;
    justify-content: space-between; margin-bottom: 0.85rem;
}
.team-side {
    flex: 1; text-align: center;
}
.team-name {
    font-weight: 800; font-size: 0.9rem; color: var(--text-main);
    word-break: break-word; line-height: 1.3;
}
.team-pool {
    font-size: 0.72rem; color: var(--text-muted); margin-top: 0.2rem;
}
.team-mult { font-size: 0.78rem; font-weight: 700; color: #34d399; }
.vs-badge {
    font-size: 0.7rem; font-weight: 800; color: var(--text-muted);
    background: var(--bg-dark); border-radius: 6px; padding: 0.25rem 0.5rem;
    flex-shrink: 0;
}

.odds-row {
    display: grid; grid-template-columns: 1fr 6px 1fr; gap: 0; margin-bottom: 0.85rem;
    background: rgba(255,255,255,0.04); border-radius: 8px; overflow: hidden; height: 6px;
}
.odds-fill-a { background: var(--accent); transition: width 0.3s; }
.odds-fill-b { background: #f59e0b; transition: width 0.3s; }

.pick-row { display: flex; gap: 0.5rem; margin-bottom: 0.6rem; }
.pick-btn {
    flex: 1; padding: 0.55rem 0.5rem; border-radius: 8px; border: 1.5px solid var(--border);
    background: transparent; color: var(--text-muted); font-size: 0.78rem; font-weight: 700;
    cursor: pointer; transition: all 0.15s; font-family: inherit; text-align: center;
}
.pick-btn:hover { border-color: var(--accent); color: var(--accent-light); background: rgba(139,92,246,0.07); }
.pick-btn.active-a { border-color: var(--accent); background: rgba(139,92,246,0.15); color: var(--accent-light); }
.pick-btn.active-b { border-color: #f59e0b; background: rgba(245,158,11,0.12); color: #fbbf24; }

.stake-row { display: flex; gap: 0.5rem; align-items: center; }
.stake-input {
    flex: 1; background: var(--input-bg); border: 1.5px solid var(--border);
    border-radius: 8px; padding: 0.45rem 0.65rem; font-size: 0.85rem;
    color: var(--text-main); font-family: inherit;
}
.stake-input:focus { outline: none; border-color: var(--accent); }
.stake-submit {
    background: var(--accent); color: #fff; border: none; border-radius: 8px;
    padding: 0.45rem 1rem; font-weight: 700; font-size: 0.8rem; cursor: pointer;
    white-space: nowrap; font-family: inherit;
}
.stake-submit:hover { opacity: 0.88; }
.stake-hint { font-size: 0.72rem; color: var(--text-muted); margin-top: 0.35rem; }

.my-stake {
    display: flex; align-items: center; gap: 0.5rem; font-size: 0.82rem;
    background: rgba(52,211,153,0.08); border: 1px solid rgba(52,211,153,0.25);
    border-radius: 8px; padding: 0.5rem 0.75rem; margin-bottom: 0.6rem; color: #34d399;
}
.my-stake.lost { background: rgba(239,68,68,0.08); border-color: rgba(239,68,68,0.25); color: #f87171; }

.result-badge {
    display: flex; align-items: center; gap: 0.5rem; font-size: 0.8rem;
    color: var(--text-muted); margin-bottom: 0.4rem;
}
.winner-tag {
    background: rgba(251,191,36,0.15); border: 1px solid rgba(251,191,36,0.35);
    border-radius: 6px; padding: 0.15rem 0.5rem; font-size: 0.72rem;
    font-weight: 700; color: #fbbf24;
}
.live-dot {
    width: 7px; height: 7px; border-radius: 50%; background: #f87171;
    display: inline-block; margin-right: 0.3rem;
    animation: blink 1s step-start infinite;
}
@keyframes blink { 50% { opacity: 0; } }

.login-prompt {
    text-align: center; padding: 0.6rem; font-size: 0.82rem;
    border-top: 1px solid var(--border); margin-top: 0.5rem;
}

.no-matches { color: var(--text-muted); font-size: 0.85rem; font-style: italic; text-align: center; padding: 2rem; }

.quick-hc {
    background: rgba(139,92,246,0.1); border: 1px solid rgba(139,92,246,0.25);
    border-radius: 6px; padding: 0.15rem 0.55rem; font-size: 0.72rem;
    font-weight: 700; color: var(--accent-light); cursor: pointer; font-family: inherit;
    transition: all 0.15s;
}
.quick-hc:hover { background: rgba(139,92,246,0.2); border-color: var(--accent); }

.login-modal-bg {
    display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65);
    z-index: 1000; align-items: center; justify-content: center; padding: 1rem;
}
.login-modal-bg.open { display: flex; }
.login-modal {
    background: var(--card-bg, #1a1a24); border: 1px solid var(--border);
    border-radius: 18px; padding: 2rem 1.75rem; max-width: 380px; width: 100%;
    text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,0.5);
    animation: loginModalIn 0.25s cubic-bezier(0.34,1.56,0.64,1);
}
@keyframes loginModalIn {
    from { opacity: 0; transform: scale(0.9) translateY(20px); }
    to   { opacity: 1; transform: scale(1) translateY(0); }
}
.login-modal i.icon { font-size: 2.5rem; color: var(--accent-light, #a78bfa); display: block; margin-bottom: 0.75rem; }
.login-modal h3 { font-size: 1.1rem; font-weight: 800; color: var(--text-main, #e4e4e7); margin-bottom: 0.4rem; }
.login-modal p { font-size: 0.82rem; color: var(--text-muted); margin-bottom: 1.25rem; line-height: 1.5; }
.login-modal-btn {
    display: inline-flex; align-items: center; gap: 0.4rem;
    background: var(--accent, #7c3aed); color: #fff; border: none; border-radius: 10px;
    padding: 0.7rem 1.5rem; font-size: 0.9rem; font-weight: 800;
    cursor: pointer; font-family: inherit; text-decoration: none;
    transition: background 0.2s; box-shadow: 0 4px 14px rgba(124,58,237,0.3);
}
.login-modal-btn:hover { background: #6d28d9; color: #fff; }
.login-modal-dismiss {
    display: block; margin-top: 0.85rem; font-size: 0.75rem;
    color: var(--text-muted); cursor: pointer; background: none; border: none; font-family: inherit;
}

.pari-note {
    background: rgba(59,130,246,0.07); border: 1px solid rgba(59,130,246,0.2);
    border-radius: 10px; padding: 0.85rem 1.1rem; font-size: 0.8rem; color: var(--text-muted);
    margin-bottom: 1.5rem;
}

.futures-select {
    appearance: none; -webkit-appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2394a3b8' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 0.65rem center;
    padding-right: 2rem;
}
.futures-select option { background: var(--card-bg, #1a1a24); color: var(--text-main); }
</style>

<div class="predict-wrap">
    <div class="predict-hero">
        <h1><i class="bi bi-trophy-fill" style="color:#fbbf24;"></i> Match Predictions</h1>
        <p>Pick the winner of each match. Use H-Coins to enter predictions and earn from the reward pool.</p>
        <?php if ($user): ?>
        <div class="balance-pill">
            <img src="<?= base_url('images/hcoin-icon.png') ?>" alt="HC" style="width:18px; height:18px; object-fit:contain; vertical-align:middle;"> <?= number_format($h_coins) ?> HC
            &nbsp;·&nbsp; <a href="<?= base_url('coins.php') ?>" style="color:inherit;text-decoration:underline;">Get more</a>
        </div>
        <?php else: ?>
        <div class="balance-pill">
            <i class="bi bi-eye"></i> Viewing live odds &nbsp;·&nbsp;
            <a href="<?= base_url('login.php') ?>" style="color:inherit;text-decoration:underline;">Log in to predict</a>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($flash_msg): ?>
        <div class="alert-custom alert-<?= $flash_type ?>" style="margin-bottom:1.25rem;"><?= $flash_msg ?></div>
    <?php endif; ?>

    <div class="pari-note">
        <strong style="color:var(--text-main);">How it works:</strong>
        Predictions on each match go into a pool. Winners share the reward pool — proportional to their entry. Minimum prediction: <?= MIN_ENTRY ?> HC.
        Once placed, your prediction is locked and cannot be changed.
        <br><br>
        <strong style="color:#38bdf8;"><i class="bi bi-graph-up-arrow"></i> Futures:</strong>
        Predict future rounds before teams advance. Back a team to reach and win a specific future match. If they make it and win — you earn from the pool. If they don't advance, your stake is lost.
    </div>

    <?php if (empty($all_matches)): ?>
        <p class="no-matches"><i class="bi bi-hourglass-split"></i> No matches scheduled yet. Check back soon.</p>
    <?php else: ?>

    <?php foreach ($sections as $section_name => $rounds):
        // Determine section style from name
        $section_class = 'section-label-winners';
        $section_icon = 'bi-trophy-fill';
        if (stripos($section_name, 'Losers') !== false) { $section_class = 'section-label-losers'; $section_icon = 'bi-arrow-repeat'; }
        elseif (stripos($section_name, 'Grand') !== false) { $section_class = 'section-label-grand'; $section_icon = 'bi-star-fill'; }
    ?>
        <div class="section-label <?= $section_class ?>"><i class="bi <?= $section_icon ?>"></i> <?= htmlspecialchars($section_name) ?></div>

        <?php foreach ($rounds as $round_key => $matches):
            // Extract bracket_side and round from key "winners:1"
            [$rk_side, $rk_round] = explode(':', $round_key);
            $round_name = bracketRoundName($rk_side, (int)$rk_round);
            $round_format = bracketMatchFormat($rk_side, (int)$rk_round);
        ?>
            <div class="round-label"><?= $round_name ?> <span style="background:rgba(124,58,237,0.15); color:var(--accent-light); padding:0.1rem 0.4rem; border-radius:4px; font-size:0.6rem; font-weight:800; letter-spacing:0.5px; margin-left:0.3rem;"><?= $round_format ?></span></div>

            <?php foreach ($matches as $m):
                $mid       = (int)$m['id'];
                $t1        = $m['team1_name'];
                $t2        = $m['team2_name'];
                $status    = $m['status'];
                $winner    = $m['winner'];
                $is_open   = ($status === 'upcoming');
                $is_live   = ($status === 'live');
                $is_done   = ($status === 'completed');
                $is_pending = ($status === 'pending');
                $is_tbd    = ($t1 === 'TBD' || $t2 === 'TBD' || $t1 === '' || $t2 === '');
                $is_futures_match = $is_pending && $is_tbd;

                $pool      = $pools[$mid] ?? [];
                // For futures matches, pools are keyed by team name (not t1/t2 since those are TBD)
                $pool_t1   = (int)($pool[$t1] ?? 0);
                $pool_t2   = (int)($pool[$t2] ?? 0);
                // Include all pools for futures (teams not yet in the match)
                $pool_sum_all = 0;
                foreach ($pool as $pt) $pool_sum_all += (int)$pt;
                $pool_sum  = $is_futures_match ? $pool_sum_all : ($pool_t1 + $pool_t2);

                $pct_t1    = $pool_sum > 0 ? round($pool_t1 / $pool_sum * 100) : 50;
                $pct_t2    = 100 - $pct_t1;

                $mult_t1   = ($pool_t1 > 0 && $pool_sum > 0) ? round($pool_sum * 0.90 / $pool_t1, 2) : '—';
                $mult_t2   = ($pool_t2 > 0 && $pool_sum > 0) ? round($pool_sum * 0.90 / $pool_t2, 2) : '—';

                $my_stake  = $my_stakes[$mid] ?? null;
                $can_stake = $user && $is_open && !$is_tbd && !$my_stake;
                $can_futures = $user && $is_futures_match && !$my_stake;

                $card_class = $is_done ? 'is-completed' : ($is_live ? 'is-live' : '');
            ?>
            <div class="match-card <?= $card_class ?>">

                <?php if ($is_live): ?>
                    <div style="font-size:0.72rem; color:#f87171; font-weight:700; margin-bottom:0.5rem;">
                        <span class="live-dot"></span> LIVE
                    </div>
                <?php elseif ($is_done): ?>
                    <div class="result-badge">
                        <i class="bi bi-check-circle-fill" style="color:#34d399;"></i>
                        Result: <span class="winner-tag"><?= htmlspecialchars($winner) ?> wins</span>
                        <span style="margin-left:auto; font-size:0.7rem;"><?= $m['team1_score'] ?>–<?= $m['team2_score'] ?></span>
                    </div>
                <?php elseif ($is_futures_match): ?>
                    <div style="font-size:0.72rem; color:#38bdf8; font-weight:700; margin-bottom:0.5rem;">
                        <i class="bi bi-graph-up-arrow"></i> FUTURES
                        <span style="font-weight:400; opacity:0.7; margin-left:0.3rem;"><?= bracketRoundName($m['bracket_side'], (int)$m['round']) ?> M<?= $m['match_order'] ?></span>
                    </div>
                <?php elseif (!empty($m['scheduled_at'])): ?>
                    <div style="font-size:0.7rem; color:var(--text-muted); margin-bottom:0.5rem;">
                        <i class="bi bi-clock"></i> <?= date('M j, g:ia', strtotime($m['scheduled_at'])) ?>
                    </div>
                <?php endif; ?>

                <!-- Matchup header -->
                <?php if ($is_futures_match): ?>
                <div class="matchup">
                    <div class="team-side">
                        <div class="team-name" style="opacity:0.4;">TBD</div>
                    </div>
                    <div class="vs-badge">VS</div>
                    <div class="team-side">
                        <div class="team-name" style="opacity:0.4;">TBD</div>
                    </div>
                </div>
                <?php if ($pool_sum > 0): ?>
                <div style="font-size:0.72rem; color:var(--text-muted); margin-bottom:0.85rem; text-align:center;">
                    <i class="bi bi-coin" style="color:#fbbf24;"></i> <?= number_format($pool_sum) ?> HC in futures pool
                    · <?= count($pool) ?> team<?= count($pool) !== 1 ? 's' : '' ?> backed
                </div>
                <?php endif; ?>
                <?php else: ?>
                <div class="matchup">
                    <div class="team-side">
                        <div class="team-name"><?= htmlspecialchars($t1 ?: 'TBD') ?></div>
                        <div class="team-pool">
                            <?php if ($pool_t1 > 0): ?>
                                <span class="team-mult"><?= $mult_t1 ?>×</span>
                                · <?= number_format($pool_t1) ?> HC
                            <?php else: ?>
                                <span style="font-size:0.68rem;">No stakes</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="vs-badge">VS</div>
                    <div class="team-side">
                        <div class="team-name"><?= htmlspecialchars($t2 ?: 'TBD') ?></div>
                        <div class="team-pool">
                            <?php if ($pool_t2 > 0): ?>
                                <span class="team-mult" style="color:#f59e0b;"><?= $mult_t2 ?>×</span>
                                · <?= number_format($pool_t2) ?> HC
                            <?php else: ?>
                                <span style="font-size:0.68rem;">No stakes</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Odds bar -->
                <?php if ($pool_sum > 0): ?>
                <div style="display:flex; height:5px; border-radius:4px; overflow:hidden; margin-bottom:0.85rem; gap:2px;">
                    <div style="flex:<?= $pct_t1 ?>; background:var(--accent); border-radius:4px 0 0 4px;"></div>
                    <div style="flex:<?= $pct_t2 ?>; background:#f59e0b; border-radius:0 4px 4px 0;"></div>
                </div>
                <?php endif; ?>
                <?php endif; ?>

                <!-- My existing stake -->
                <?php if ($my_stake): ?>
                    <?php
                    $won  = $is_done && $my_stake['picked_team'] === $winner;
                    $lost = $is_done && $my_stake['picked_team'] !== $winner;
                    $my_pool_total = (int)($pool[$my_stake['picked_team']] ?? 0);
                    $est_payout = ($my_pool_total > 0 && $pool_sum > 0)
                        ? round(($my_stake['wager'] / $my_pool_total) * ($pool_sum * 0.90))
                        : 0;
                    ?>
                    <div class="my-stake <?= $lost ? 'lost' : '' ?>" <?= $is_futures_match ? 'style="background:rgba(56,189,248,0.08);border-color:rgba(56,189,248,0.25);color:#38bdf8;"' : '' ?>>
                        <i class="bi bi-<?= $won ? 'trophy-fill' : ($lost ? 'x-circle-fill' : ($is_futures_match ? 'graph-up-arrow' : 'check-circle-fill')) ?>"></i>
                        <?= $is_futures_match ? '<span style="font-size:0.68rem;opacity:0.7;">FUTURES</span> ' : '' ?>
                        Backing <strong><?= htmlspecialchars($my_stake['picked_team']) ?></strong>
                        · <?= number_format((int)$my_stake['wager']) ?> HC
                        <?php if ($is_futures_match): ?>
                            · Awaiting advancement
                            <span style="margin-left:auto; font-size:0.7rem; opacity:0.7;"><i class="bi bi-hourglass-split"></i></span>
                        <?php elseif ($is_open): ?>
                            · Est. <strong><?= number_format($est_payout) ?> HC</strong> if correct
                            <span style="margin-left:auto; font-size:0.7rem; opacity:0.7;"><i class="bi bi-lock-fill"></i> Locked</span>
                        <?php elseif ($won): ?>
                            · <strong style="color:#fbbf24;">You won!</strong>
                        <?php elseif ($lost): ?>
                            · <strong>Incorrect pick</strong>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Stake form (upcoming + logged in) -->
                <?php if ($can_stake): ?>
                <form method="POST" class="stake-form" data-mid="<?= $mid ?>">
                    <input type="hidden" name="match_id" value="<?= $mid ?>">
                    <div class="pick-row">
                        <button type="button" class="pick-btn"
                                data-team="<?= htmlspecialchars($t1) ?>" data-side="a"
                                onclick="pickTeam(this, '<?= htmlspecialchars(addslashes($t1)) ?>')">
                            <?= htmlspecialchars($t1) ?>
                        </button>
                        <button type="button" class="pick-btn"
                                data-team="<?= htmlspecialchars($t2) ?>" data-side="b"
                                onclick="pickTeam(this, '<?= htmlspecialchars(addslashes($t2)) ?>')">
                            <?= htmlspecialchars($t2) ?>
                        </button>
                    </div>
                    <input type="hidden" name="picked_team" class="picked-team-input" value="">
                    <div class="stake-row">
                        <input type="number" name="wager" class="stake-input" min="<?= MIN_ENTRY ?>"
                               max="<?= $h_coins ?>" placeholder="HC to predict (min <?= MIN_ENTRY ?>)">
                        <button type="submit" class="stake-submit">
                            <i class="bi bi-send"></i> Predict
                        </button>
                    </div>
                    <div class="stake-hint">Balance: <?= number_format($h_coins) ?> HC</div>
                </form>
                <?php elseif (!$user && $is_open && !$is_tbd): ?>
                <div class="stake-form">
                    <div class="pick-row">
                        <button type="button" class="pick-btn" data-side="a"
                                onclick="guestPick(this)">
                            <?= htmlspecialchars($t1) ?>
                        </button>
                        <button type="button" class="pick-btn" data-side="b"
                                onclick="guestPick(this)">
                            <?= htmlspecialchars($t2) ?>
                        </button>
                    </div>
                    <div class="stake-row">
                        <input type="number" class="stake-input" min="<?= MIN_ENTRY ?>" placeholder="HC to predict (min <?= MIN_ENTRY ?>)" onfocus="showLoginDialog()">
                        <button type="button" class="stake-submit" onclick="showLoginDialog()">
                            <i class="bi bi-send"></i> Predict
                        </button>
                    </div>
                    <div class="stake-hint" style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                        <span>Suggested:</span>
                        <button type="button" class="quick-hc" onclick="showLoginDialog()">10 HC</button>
                        <button type="button" class="quick-hc" onclick="showLoginDialog()">25 HC</button>
                        <button type="button" class="quick-hc" onclick="showLoginDialog()">50 HC</button>
                        <button type="button" class="quick-hc" onclick="showLoginDialog()">100 HC</button>
                    </div>
                </div>
                <?php elseif ($can_futures): ?>
                <!-- Futures prediction form -->
                <form method="POST" class="stake-form futures-form" data-mid="<?= $mid ?>">
                    <input type="hidden" name="match_id" value="<?= $mid ?>">
                    <div style="margin-bottom:0.5rem;">
                        <label style="font-size:0.7rem; color:#38bdf8; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">
                            <i class="bi bi-graph-up-arrow"></i> Pick the team you think will be here & win
                        </label>
                        <select name="picked_team" class="stake-input futures-select" required style="margin-top:0.3rem; width:100%; cursor:pointer;">
                            <option value="">— Select a team —</option>
                            <?php foreach ($bracket_teams as $bt): ?>
                                <option value="<?= htmlspecialchars($bt) ?>"><?= htmlspecialchars($bt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="stake-row">
                        <input type="number" name="wager" class="stake-input" min="<?= MIN_ENTRY ?>"
                               max="<?= $h_coins ?>" placeholder="HC to predict (min <?= MIN_ENTRY ?>)">
                        <button type="submit" class="stake-submit" style="background:#0ea5e9;">
                            <i class="bi bi-graph-up-arrow"></i> Futures Predict
                        </button>
                    </div>
                    <div class="stake-hint">Balance: <?= number_format($h_coins) ?> HC · Higher risk: stake is lost if your team doesn't reach this match</div>
                </form>
                <?php elseif (!$user && $is_futures_match): ?>
                <!-- Guest futures prompt -->
                <div style="text-align:center; padding:0.5rem 0; border-top:1px solid var(--border); margin-top:0.3rem;">
                    <span style="font-size:0.78rem; color:#38bdf8;"><i class="bi bi-graph-up-arrow"></i> Futures prediction available</span>
                    <span style="font-size:0.75rem; color:var(--text-muted);"> · </span>
                    <a href="#" onclick="showLoginDialog();return false;" style="font-size:0.75rem; color:var(--accent-light); text-decoration:underline;">Log in to predict</a>
                </div>
                <?php elseif ($is_tbd && !$is_futures_match): ?>
                <div style="font-size:0.78rem; color:var(--text-muted); text-align:center; padding-top:0.4rem;">
                    Teams TBD — predictions open once teams are confirmed
                </div>
                <?php endif; ?>

            </div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if (!$user): ?>
<div class="login-modal-bg" id="loginModal" onclick="if(event.target===this)closeLoginDialog()">
    <div class="login-modal">
        <i class="bi bi-shield-lock-fill icon"></i>
        <h3>Login Required</h3>
        <p>You need an account to place predictions and use H-Coins. Log in or create an account to get started.</p>
        <a href="<?= base_url('login.php') ?>" class="login-modal-btn">
            <i class="bi bi-box-arrow-in-right"></i> Log In / Sign Up
        </a>
        <button class="login-modal-dismiss" onclick="closeLoginDialog()">Maybe later</button>
    </div>
</div>
<?php endif; ?>

<script>
function showLoginDialog() {
    var m = document.getElementById('loginModal');
    if (m) m.classList.add('open');
}
function closeLoginDialog() {
    var m = document.getElementById('loginModal');
    if (m) m.classList.remove('open');
}
function guestPick(btn) {
    var row = btn.closest('.pick-row');
    row.querySelectorAll('.pick-btn').forEach(function(b) {
        b.classList.remove('active-a', 'active-b');
    });
    btn.classList.add(btn.dataset.side === 'a' ? 'active-a' : 'active-b');
    showLoginDialog();
}

function pickTeam(btn, teamName) {
    var form = btn.closest('form');
    form.querySelectorAll('.pick-btn').forEach(function(b) {
        b.classList.remove('active-a', 'active-b');
    });
    var side = btn.dataset.side;
    btn.classList.add(side === 'a' ? 'active-a' : 'active-b');
    form.querySelector('.picked-team-input').value = teamName;
}

// Prevent submit if no team picked
document.querySelectorAll('.stake-form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        if (!form.querySelector('.picked-team-input').value) {
            e.preventDefault();
            alert('Please pick a team first.');
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
