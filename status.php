<?php
require_once __DIR__ . '/includes/db.php';

$game_names = [
    'dota2'     => 'Dota 2',
    'valorant'  => 'Valorant',
    'crossfire' => 'CrossFire',
];

$ref = strtoupper(trim($_GET['ref'] ?? ''));
$searched = ($ref !== '');
$result = null;
$result_type = null;

if ($searched) {
    $stmt = $pdo->prepare("SELECT * FROM teams WHERE UPPER(ref_code) = ? LIMIT 1");
    $stmt->execute([$ref]);
    $row = $stmt->fetch();
    if ($row) {
        $result = $row;
        $result_type = 'team';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM solo_players WHERE UPPER(ref_code) = ? LIMIT 1");
        $stmt->execute([$ref]);
        $row = $stmt->fetch();
        if ($row) {
            $result = $row;
            $result_type = 'solo';
        }
    }
}

/**
 * Map a raw DB status to a display badge.
 */
function status_meta(string $status): array {
    switch ($status) {
        case 'approved':
            return ['label' => 'Confirmed — slot locked in', 'color' => '#22c55e', 'bg' => 'rgba(34,197,94,0.12)', 'border' => 'rgba(34,197,94,0.35)', 'icon' => 'bi-shield-fill-check'];
        case 'matched':
            return ['label' => 'Matched into a team', 'color' => '#60a5fa', 'bg' => 'rgba(59,130,246,0.12)', 'border' => 'rgba(59,130,246,0.35)', 'icon' => 'bi-people-fill'];
        case 'reserved':
            return ['label' => 'Reserved for the next tournament', 'color' => '#a78bfa', 'bg' => 'rgba(167,139,250,0.12)', 'border' => 'rgba(167,139,250,0.35)', 'icon' => 'bi-bookmark-fill'];
        case 'rejected':
            return ['label' => 'Not approved — contact the organizers', 'color' => '#f87171', 'bg' => 'rgba(248,113,113,0.12)', 'border' => 'rgba(248,113,113,0.35)', 'icon' => 'bi-x-circle-fill'];
        case 'pending':
        default:
            return ['label' => 'Pending payment', 'color' => '#f59e0b', 'bg' => 'rgba(245,158,11,0.12)', 'border' => 'rgba(245,158,11,0.35)', 'icon' => 'bi-hourglass-split'];
    }
}

$pageTitle = 'Check Registration Status — Apex Cybernet Tournament';
$pageDescription = 'Enter your reference code to check the status of your Apex Cybernet tournament registration — team or solo, paid or pending.';
$canonicalUrl = canonical_url('status.php');
$extraHead = breadcrumb_jsonld([
    ['name' => 'Home',   'url' => 'https://apexcybernet.com/'],
    ['name' => 'Status', 'url' => 'https://apexcybernet.com/status.php'],
]);

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/he-chrome.php';
?>

<section class="he-page-hero">
    <div class="he-page-eyebrow">Registration status</div>
    <h1 class="he-page-title">Check your reference code.</h1>
    <p class="he-page-sub">Enter the reference code you got when you registered to see if your slot is confirmed or still pending payment.</p>
</section>

<div class="he-card" style="max-width:680px;">
    <div class="he-card-inner">
        <form method="GET" action="<?= base_url('status.php') ?>">
            <div class="he-card-section">
                <div class="he-card-section-label">Reference code</div>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <input type="text" name="ref" value="<?= htmlspecialchars($ref) ?>"
                           placeholder="e.g. DOTA-T-AB12" required autofocus
                           style="flex:1; min-width:200px; text-transform:uppercase; letter-spacing:0.04em; font-family:var(--mono);">
                    <button type="submit" class="he-btn-primary" style="white-space:nowrap;">
                        <i class="bi bi-search"></i> Check status
                    </button>
                </div>
                <div class="he-field-hint">It looks like <strong>DOTA-T-XXXX</strong> (team) or <strong>DOTA-S-XXXX</strong> (solo). Case doesn't matter.</div>
            </div>
        </form>

        <?php if ($searched && !$result): ?>
            <div class="he-notice danger" style="margin-top:6px;">
                <i class="bi bi-exclamation-circle-fill"></i>
                <div>No registration found for <strong><?= htmlspecialchars($ref) ?></strong>. Double-check the code, or <a href="<?= base_url('contact.php') ?>" style="color:var(--accent-light); text-decoration:underline;">contact us</a> if you think this is a mistake.</div>
            </div>
        <?php elseif ($result): ?>
            <?php
            $status = $result['status'] ?? 'pending';
            $sm = status_meta($status);
            $game_slug = $result['game'] ?? '';
            $game_name = $game_names[$game_slug] ?? ucfirst($game_slug);
            $display_name = $result_type === 'team' ? ($result['team_name'] ?? '') : ($result['player_name'] ?? '');
            $is_pending = ($status === 'pending');
            ?>
            <div class="he-card-section">
                <div class="he-card-section-label">Result</div>
                <div style="border:1px solid <?= $sm['border'] ?>; background:<?= $sm['bg'] ?>; border-radius:14px; padding:20px 22px;">
                    <div style="display:flex; align-items:center; justify-content:space-between; gap:14px; flex-wrap:wrap;">
                        <div style="min-width:0;">
                            <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.08em; font-weight:700; color:var(--text-muted);">
                                <?= $result_type === 'team' ? 'Team' : 'Solo player' ?> · <?= htmlspecialchars($game_name) ?>
                            </div>
                            <div style="font-size:22px; font-weight:800; color:var(--text); letter-spacing:-0.01em; line-height:1.2;"><?= htmlspecialchars($display_name) ?></div>
                            <div style="font-family:var(--mono); font-size:12.5px; color:var(--text-muted); margin-top:2px;"><?= htmlspecialchars($result['ref_code']) ?></div>
                        </div>
                        <span style="display:inline-flex; align-items:center; gap:6px; font-size:13px; font-weight:700; color:<?= $sm['color'] ?>; background:rgba(255,255,255,0.04); border:1px solid <?= $sm['border'] ?>; padding:8px 14px; border-radius:10px; white-space:nowrap;">
                            <i class="bi <?= $sm['icon'] ?>"></i> <?= htmlspecialchars($sm['label']) ?>
                        </span>
                    </div>

                    <?php if ($result_type === 'solo'): ?>
                        <div style="margin-top:12px; font-size:13px; color:var(--text-body);">
                            <i class="bi bi-bar-chart-fill" style="color:var(--text-muted);"></i> Rank: <strong><?= htmlspecialchars($result['rank_tier'] ?? '—') ?></strong>
                            <?php if (!empty($result['preferred_role'])): ?>
                                &nbsp;·&nbsp; <i class="bi bi-joystick" style="color:var(--text-muted);"></i> Role: <strong><?= htmlspecialchars($result['preferred_role']) ?></strong>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php
                        // Build roster (name:rank|...), fallback to member_1..5
                        $roster = [];
                        if (!empty($result['members_ranks'])) {
                            foreach (explode('|', $result['members_ranks']) as $entry) {
                                $parts = explode(':', $entry, 2);
                                $nm = trim($parts[0] ?? '');
                                if ($nm !== '') $roster[] = ['name' => $nm, 'rank' => trim($parts[1] ?? '')];
                            }
                        }
                        if (empty($roster)) {
                            for ($mi = 1; $mi <= 5; $mi++) {
                                if (!empty($result["member_$mi"])) $roster[] = ['name' => trim($result["member_$mi"]), 'rank' => ''];
                            }
                        }
                        if (!empty($roster)): ?>
                            <div style="margin-top:14px; border-top:1px solid var(--border); padding-top:12px;">
                                <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.08em; font-weight:700; color:var(--text-muted); margin-bottom:8px;">Roster</div>
                                <?php foreach ($roster as $ri => $member): ?>
                                    <div style="display:flex; justify-content:space-between; gap:10px; font-size:13px; padding:4px 0; <?= $ri > 0 ? 'border-top:1px solid var(--border);' : '' ?>">
                                        <span style="color:var(--text);">
                                            <?php if ($ri === 0): ?><i class="bi bi-star-fill" style="color:#fbbf24; font-size:11px;" title="Captain"></i> <?php endif; ?>
                                            <?= htmlspecialchars($member['name']) ?>
                                        </span>
                                        <span style="color:var(--text-muted);"><?= $member['rank'] !== '' ? htmlspecialchars($member['rank']) : '—' ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <?php if ($is_pending): ?>
                    <a href="<?= base_url('ticket.php') ?>?ref=<?= urlencode($result['ref_code']) ?>&type=<?= $result_type ?>&game=<?= urlencode($game_slug) ?>"
                       class="he-btn-primary he-btn-full" style="margin-top:14px;">
                        <i class="bi bi-credit-card"></i> Pay now to lock your slot
                    </a>
                    <div class="he-field-hint" style="text-align:center;">Your slot isn't reserved until payment clears.</div>
                <?php elseif ($status === 'approved'): ?>
                    <div class="he-notice" style="margin-top:14px; background:rgba(34,197,94,0.08); border-color:rgba(34,197,94,0.3);">
                        <i class="bi bi-check-circle-fill" style="color:#22c55e;"></i>
                        <div>You're locked in. See you at Apex Cybernet Cafe on <strong>July 11, 2026</strong> — call time 10:00 AM.</div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div style="text-align:center; padding-top:8px;">
            <a href="<?= base_url() ?>" style="font-size:13px; color:var(--text-muted); text-decoration:none;">← Back to tournament</a>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/he-foot.php'; return; ?>
