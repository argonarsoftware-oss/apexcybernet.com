<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/layout.php';
m_require_login();

$user = current_user($pdo);

$valid_games = ['valorant' => 'Valorant', 'crossfire' => 'CrossFire', 'dota2' => 'Dota 2'];

$registration = null; $team_name = ''; $game = '';
if (!empty($user['ref_code'])) {
    if ($user['ref_type'] === 'team') {
        $st = $pdo->prepare("SELECT * FROM teams WHERE ref_code = ?");
        $st->execute([$user['ref_code']]);
        $registration = $st->fetch();
    } else {
        $st = $pdo->prepare("SELECT * FROM solo_players WHERE ref_code = ?");
        $st->execute([$user['ref_code']]);
        $registration = $st->fetch();
    }
    $team_name = $user['ref_type'] === 'team' ? ($registration['team_name'] ?? '') : ($registration['player_name'] ?? '');
    $game = $registration['game'] ?? '';
}

$matches = [];
if ($team_name && $game) {
    $st = $pdo->prepare("SELECT * FROM matches WHERE game = ? AND (team1_name = ? OR team2_name = ?) ORDER BY round ASC, match_order ASC");
    $st->execute([$game, $team_name, $team_name]);
    $matches = $st->fetchAll();
}
$next_match = null;
foreach ($matches as $m) {
    if (in_array($m['status'], ['upcoming', 'live'])) { $next_match = $m; break; }
}
$total_matches = count($matches);
$wins = count(array_filter($matches, fn($m) => $m['winner'] === $team_name));
$win_rate = $total_matches > 0 ? round($wins / $total_matches * 100) : null;

$pred_stmt = $pdo->prepare("
    SELECT mp.picked_team, mp.wager, mp.status,
           m.team1_name, m.team2_name, m.round, m.bracket_side
    FROM match_predictions mp
    JOIN matches m ON m.id = mp.match_id
    WHERE mp.account_id = ?
    ORDER BY mp.created_at DESC LIMIT 5
");
$pred_stmt->execute([$user['id']]);
$my_preds = $pred_stmt->fetchAll();
$pred_won  = count(array_filter($my_preds, fn($p) => $p['status'] === 'won'));

$announcements = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 4")->fetchAll();

$sp_stmt = $pdo->prepare("SELECT * FROM season_passes WHERE account_id = ? AND status IN ('active','pending') ORDER BY FIELD(status,'active','pending') ASC, id DESC LIMIT 1");
try { $sp_stmt->execute([$user['id']]); $season_pass = $sp_stmt->fetch(); } catch (Exception $e) { $season_pass = null; }

$unread_notifs = 0;
try {
    $un = $pdo->prepare("SELECT COUNT(*) FROM user_notifications WHERE (account_id = ? OR account_id IS NULL) AND is_read = 0");
    $un->execute([$user['id']]);
    $unread_notifs = (int)$un->fetchColumn();
} catch (Exception $e) {}

m_head('Dashboard');
?>

<!-- Top bar -->
<div class="m-top" style="justify-content:space-between;">
    <a href="<?= m_base('') ?>" class="m-back"><i class="bi bi-arrow-left"></i></a>
    <div class="m-top-title" style="text-align:center;">Tournament</div>
    <a href="<?= m_base('notifications.php') ?>" class="m-bell-btn" id="mBellBtn">
        <i class="bi bi-bell-fill"></i>
        <span class="m-bell-badge" id="mBellBadge" style="<?= $unread_notifs > 0 ? '' : 'display:none;' ?>"><?= $unread_notifs > 99 ? '99+' : $unread_notifs ?></span>
    </a>
</div>
<style>
.m-bell-btn{position:relative;width:36px;height:36px;display:flex;align-items:center;justify-content:center;background:var(--card);border:1px solid var(--border);border-radius:10px;font-size:17px;color:var(--accent-l);}
.m-bell-badge{position:absolute;top:-4px;right:-4px;background:#ef4444;color:#fff;font-size:10px;font-weight:800;min-width:16px;height:16px;padding:0 4px;border-radius:99px;display:flex;align-items:center;justify-content:center;border:2px solid var(--bg);line-height:1;}
.live-dot{display:inline-block;width:6px;height:6px;border-radius:50%;background:#f87171;animation:blink 1s step-start infinite;margin-right:2px;}
@keyframes blink{50%{opacity:0;}}
</style>

<!-- Payment alert -->
<?php if ($registration && $registration['status'] === 'pending'): ?>
<div style="margin:0 1rem 1rem;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.28);border-radius:14px;padding:1rem 1.1rem;">
    <div style="font-weight:800;font-size:0.88rem;color:#f87171;margin-bottom:0.2rem;"><i class="bi bi-exclamation-triangle-fill"></i> Payment Required</div>
    <div style="font-size:0.78rem;color:var(--muted);margin-bottom:0.65rem;">Your slot is not confirmed. Pay before Apr 17 or it goes to waitlist.</div>
    <a href="https://apexcybernet.com/ticket.php?ref=<?= urlencode($user['ref_code']) ?>&type=<?= $user['ref_type'] ?>&game=<?= $game ?>"
       style="display:block;background:#dc2626;color:#fff;border-radius:10px;padding:0.7rem;text-align:center;font-weight:800;font-size:0.88rem;">
        <i class="bi bi-qr-code"></i> Pay Now — Secure Your Slot
    </a>
</div>
<?php endif; ?>

<!-- Season pass -->
<?php if (!empty($season_pass)): ?>
<?php if ($season_pass['status'] === 'active'): ?>
<a href="https://apexcybernet.com/season-pass.php" style="display:flex;align-items:center;gap:0.75rem;margin:0 1rem 1rem;padding:0.9rem 1.1rem;background:rgba(251,191,36,0.06);border:1px solid rgba(251,191,36,0.28);border-radius:14px;color:var(--text);text-decoration:none;">
    <div style="width:36px;height:36px;border-radius:10px;background:rgba(251,191,36,0.12);color:#fbbf24;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;"><i class="bi bi-patch-check-fill"></i></div>
    <div style="flex:1;min-width:0;">
        <div style="font-weight:800;font-size:0.85rem;color:#fde68a;">Season 1 Pass · Active</div>
        <div style="font-size:0.7rem;color:var(--muted);"><?= (int)$season_pass['tournaments_max'] - (int)$season_pass['tournaments_used'] ?> of <?= (int)$season_pass['tournaments_max'] ?> entries remaining</div>
    </div>
    <i class="bi bi-chevron-right" style="color:var(--muted);font-size:0.85rem;"></i>
</a>
<?php else: ?>
<a href="https://apexcybernet.com/season-pass.php" style="display:flex;align-items:center;gap:0.75rem;margin:0 1rem 1rem;padding:0.9rem 1.1rem;background:rgba(96,165,250,0.05);border:1px solid rgba(96,165,250,0.22);border-radius:14px;color:var(--text);text-decoration:none;">
    <div style="width:36px;height:36px;border-radius:10px;background:rgba(96,165,250,0.1);color:#60a5fa;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;"><i class="bi bi-clock-history"></i></div>
    <div style="flex:1;min-width:0;">
        <div style="font-weight:800;font-size:0.85rem;color:#93c5fd;">Season 1 Pass · Pending</div>
        <div style="font-size:0.7rem;color:var(--muted);">Ref: <?= htmlspecialchars($season_pass['ref_code']) ?></div>
    </div>
    <i class="bi bi-chevron-right" style="color:var(--muted);font-size:0.85rem;"></i>
</a>
<?php endif; ?>
<?php endif; ?>

<!-- Stat chips -->
<div style="display:grid;grid-template-columns:repeat(2,1fr);gap:0.6rem;padding:0 1rem 1rem;">
    <div style="background:var(--card);border:1px solid var(--border);border-radius:14px;padding:0.9rem 1rem;">
        <div style="font-size:0.62rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin-bottom:0.2rem;">Matches</div>
        <div style="font-size:1.6rem;font-weight:900;color:var(--text);line-height:1;"><?= $total_matches ?></div>
        <div style="font-size:0.7rem;color:var(--muted);"><?= $wins ?>W · <?= $total_matches - $wins ?>L</div>
    </div>
    <div style="background:var(--card);border:1px solid var(--border);border-radius:14px;padding:0.9rem 1rem;">
        <div style="font-size:0.62rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin-bottom:0.2rem;">Win Rate</div>
        <div style="font-size:1.6rem;font-weight:900;color:<?= $win_rate !== null ? '#34d399' : 'var(--muted)' ?>;line-height:1;"><?= $win_rate !== null ? $win_rate . '%' : '—' ?></div>
        <div style="font-size:0.7rem;color:var(--muted);">tournament</div>
    </div>
    <div style="background:var(--card);border:1px solid var(--border);border-radius:14px;padding:0.9rem 1rem;">
        <div style="font-size:0.62rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin-bottom:0.2rem;">Predictions</div>
        <div style="font-size:1.6rem;font-weight:900;color:var(--accent-l);line-height:1;"><?= count($my_preds) ?></div>
        <div style="font-size:0.7rem;color:var(--muted);"><?= $pred_won ?> correct</div>
    </div>
    <div style="background:var(--card);border:1px solid var(--border);border-radius:14px;padding:0.9rem 1rem;">
        <div style="font-size:0.62rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin-bottom:0.2rem;">Game</div>
        <div style="font-size:1rem;font-weight:900;color:var(--text);line-height:1;margin-top:0.25rem;"><?= $game ? htmlspecialchars($valid_games[$game] ?? $game) : '—' ?></div>
        <div style="font-size:0.7rem;color:var(--muted);margin-top:0.2rem;"><?= $team_name ? htmlspecialchars($team_name) : 'Not registered' ?></div>
    </div>
</div>

<!-- Quick actions -->
<div class="quick" style="grid-template-columns:repeat(3,1fr);">
    <a href="https://apexcybernet.com/predict.php" class="quick-btn">
        <i class="bi bi-graph-up-arrow" style="color:#a78bfa;"></i>Predict
    </a>
    <a href="<?= $game ? 'https://apexcybernet.com/bracket.php?game=' . $game : 'https://apexcybernet.com/bracket.php' ?>" class="quick-btn">
        <i class="bi bi-diagram-3-fill" style="color:#60a5fa;"></i>Bracket
    </a>
    <?php if ($registration): ?>
    <a href="https://apexcybernet.com/ticket.php?ref=<?= urlencode($user['ref_code']) ?>&type=<?= $user['ref_type'] ?>&game=<?= $game ?>" class="quick-btn">
        <i class="bi bi-qr-code" style="color:#34d399;"></i>Ticket
    </a>
    <?php else: ?>
    <a href="https://apexcybernet.com/register.php" class="quick-btn">
        <i class="bi bi-plus-circle-fill" style="color:#34d399;"></i>Register
    </a>
    <?php endif; ?>
</div>

<!-- Next match -->
<?php if ($next_match): ?>
<div class="card" style="margin-bottom:1rem;">
    <div class="card-body" style="padding-bottom:0.5rem;">
        <div class="card-title" style="color:<?= $next_match['status'] === 'live' ? '#f87171' : 'var(--muted)' ?>;">
            <?= $next_match['status'] === 'live' ? '<span class="live-dot"></span> LIVE NOW' : 'Next Match' ?>
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:0.5rem;margin-bottom:0.5rem;">
            <div style="flex:1;font-weight:800;font-size:0.95rem;text-align:center;"><?= htmlspecialchars($next_match['team1_name'] ?: 'TBD') ?></div>
            <div style="font-size:0.7rem;font-weight:800;color:var(--muted);background:rgba(255,255,255,0.05);border-radius:5px;padding:0.2rem 0.5rem;flex-shrink:0;">VS</div>
            <div style="flex:1;font-weight:800;font-size:0.95rem;text-align:center;"><?= htmlspecialchars($next_match['team2_name'] ?: 'TBD') ?></div>
        </div>
        <div style="font-size:0.7rem;color:var(--muted);text-align:center;">
            <?= $next_match['bracket_side'] === 'winners' ? 'Winners' : ($next_match['bracket_side'] === 'losers' ? 'Losers' : 'Grand Finals') ?> · Round <?= $next_match['round'] ?>
            <?php if (!empty($next_match['scheduled_at'])): ?> · <?= date('M j, g:ia', strtotime($next_match['scheduled_at'])) ?><?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- My Predictions -->
<div class="card" style="margin-bottom:1rem;">
    <div class="card-body" style="padding-bottom:0;">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <div class="card-title" style="margin-bottom:0;">My Predictions</div>
            <a href="https://apexcybernet.com/predict.php" style="font-size:0.72rem;color:var(--accent-l);font-weight:700;">Place bet</a>
        </div>
    </div>
    <?php if (empty($my_preds)): ?>
    <div class="empty"><i class="bi bi-graph-up"></i><p>No predictions yet</p></div>
    <?php else: foreach ($my_preds as $p):
        $status_colors = ['won'=>'#34d399','lost'=>'#f87171','active'=>'var(--accent-l)','pending'=>'var(--accent-l)'];
        $sc = $status_colors[$p['status']] ?? 'var(--muted)';
    ?>
    <div class="txn-item">
        <div class="txn-ico" style="background:rgba(124,58,237,0.12);color:var(--accent-l);">
            <i class="bi bi-graph-up"></i>
        </div>
        <div class="txn-body">
            <div class="txn-lbl"><?= htmlspecialchars($p['picked_team']) ?></div>
            <div class="txn-time"><?= htmlspecialchars($p['team1_name']) ?> vs <?= htmlspecialchars($p['team2_name']) ?> · R<?= $p['round'] ?></div>
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:2px;">
            <span style="font-size:0.65rem;font-weight:800;color:<?= $sc ?>;text-transform:uppercase;"><?= $p['status'] ?></span>
            <span style="font-size:0.78rem;font-weight:700;color:var(--accent-l);"><?= number_format((int)$p['wager']) ?> HC</span>
        </div>
    </div>
    <?php endforeach; endif; ?>
</div>

<!-- Registration info -->
<?php if ($registration): ?>
<div class="card" style="margin-bottom:1rem;">
    <div class="card-body" style="padding-bottom:0.5rem;">
        <div class="card-title">Registration</div>
        <div style="display:flex;flex-direction:column;gap:0.1rem;font-size:0.84rem;">
            <div style="display:flex;justify-content:space-between;padding:0.45rem 0;border-bottom:1px solid var(--border);">
                <span style="color:var(--muted);"><?= $user['ref_type'] === 'team' ? 'Team' : 'Player' ?></span>
                <span style="font-weight:700;"><?= htmlspecialchars($team_name) ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:0.45rem 0;border-bottom:1px solid var(--border);">
                <span style="color:var(--muted);">Game</span>
                <span style="font-weight:600;"><?= htmlspecialchars($valid_games[$game] ?? $game) ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:0.45rem 0;border-bottom:1px solid var(--border);">
                <span style="color:var(--muted);">Ref Code</span>
                <span style="font-weight:700;color:var(--accent-l);font-family:monospace;letter-spacing:1px;font-size:0.78rem;"><?= htmlspecialchars($user['ref_code']) ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:0.45rem 0;">
                <span style="color:var(--muted);">Status</span>
                <?php
                $st_color = ['confirmed'=>'#34d399','pending'=>'#f87171','waitlist'=>'#f59e0b'][$registration['status']] ?? 'var(--muted)';
                ?>
                <span style="font-weight:700;color:<?= $st_color ?>;"><?= ucfirst($registration['status']) ?></span>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<a href="https://apexcybernet.com/register.php" style="display:flex;align-items:center;justify-content:space-between;margin:0 1rem 1rem;padding:0.9rem 1.1rem;background:var(--card);border:1px solid var(--border);border-radius:14px;color:var(--text);text-decoration:none;">
    <div style="display:flex;align-items:center;gap:0.75rem;">
        <div style="width:36px;height:36px;border-radius:10px;background:var(--accent-dim);color:var(--accent-l);display:flex;align-items:center;justify-content:center;font-size:1rem;"><i class="bi bi-trophy-fill"></i></div>
        <div>
            <div style="font-weight:800;font-size:0.88rem;">Join the Tournament</div>
            <div style="font-size:0.68rem;color:var(--muted);">Register for Season 1</div>
        </div>
    </div>
    <i class="bi bi-arrow-right" style="color:var(--muted);"></i>
</a>
<?php endif; ?>

<!-- Announcements -->
<div class="card" style="margin-bottom:1rem;">
    <div class="card-body" style="padding-bottom:0;">
        <div class="card-title">Announcements</div>
    </div>
    <?php if (empty($announcements)): ?>
    <div class="empty"><i class="bi bi-megaphone"></i><p>No announcements</p></div>
    <?php else:
        $ann_colors = ['urgent'=>'#f87171','schedule'=>'#60a5fa','result'=>'#34d399','news'=>'var(--accent-l)'];
        foreach ($announcements as $ann):
            $c = $ann_colors[$ann['type']] ?? $ann_colors['news'];
    ?>
    <div style="display:flex;gap:0.75rem;padding:0.85rem 1.1rem;border-top:1px solid var(--border);">
        <div style="width:6px;height:6px;border-radius:50%;background:<?= $c ?>;flex-shrink:0;margin-top:0.4rem;"></div>
        <div style="flex:1;min-width:0;">
            <div style="font-weight:700;font-size:0.84rem;color:<?= $c ?>;margin-bottom:0.2rem;"><?= htmlspecialchars($ann['title']) ?></div>
            <div style="font-size:0.78rem;color:var(--muted);line-height:1.45;"><?= nl2br(htmlspecialchars(mb_substr($ann['content'], 0, 120))) ?><?= mb_strlen($ann['content']) > 120 ? '…' : '' ?></div>
            <div style="font-size:0.63rem;color:#374151;margin-top:0.2rem;"><?= date('M j · g:ia', strtotime($ann['created_at'])) ?></div>
        </div>
    </div>
    <?php endforeach; endif; ?>
</div>

<?php m_nav('tournament'); m_toast(); m_foot(); ?>
