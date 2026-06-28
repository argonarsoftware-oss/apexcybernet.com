<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/layout.php';

$current = null;
if (!empty($_SESSION['account_id'])) {
    try {
        $s = $pdo->prepare("SELECT * FROM accounts WHERE id = ?");
        $s->execute([$_SESSION['account_id']]);
        $current = $s->fetch();
    } catch (Exception $e) {}
}

$account_id = (int)($_GET['id'] ?? 0);
if ($account_id <= 0 && $current) {
    $account_id = (int)$current['id'];
} elseif ($account_id <= 0) {
    header('Location: ' . m_base('login.php'));
    exit;
}
$is_own = $current && (int)$current['id'] === $account_id;

// Handle profile edit (own only)
$profile_saved = false;
$profile_errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile']) && $is_own) {
    $new_display = trim($_POST['display_name'] ?? '');
    $new_bio     = trim($_POST['bio']           ?? '');
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

    $new_pic = null;
    if (!empty($_FILES['profile_picture']['tmp_name'])) {
        $file  = $_FILES['profile_picture'];
        $allow = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($file['type'], $allow)) {
            $profile_errors[] = 'Photo must be JPG, PNG, WebP, or GIF.';
        } elseif ($file['size'] > 3 * 1024 * 1024) {
            $profile_errors[] = 'Photo must be under 3 MB.';
        } else {
            $ext   = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
            $fname = 'avatar_' . $account_id . '_' . time() . '.' . strtolower($ext);
            $dest  = __DIR__ . '/../uploads/avatars/' . $fname;
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $new_pic = 'uploads/avatars/' . $fname;
            } else {
                $profile_errors[] = 'Failed to save photo.';
            }
        }
    }

    if (empty($profile_errors)) {
        if ($new_pic) {
            if (!empty($current['profile_picture'])) {
                $old = __DIR__ . '/../' . $current['profile_picture'];
                if (file_exists($old)) @unlink($old);
            }
            $pdo->prepare("UPDATE accounts SET display_name=?,bio=?,contact_number=?,gcash_number=?,profile_picture=? WHERE id=?")
                ->execute([$new_display, $new_bio, $new_contact, $new_gcash, $new_pic, $account_id]);
        } else {
            $pdo->prepare("UPDATE accounts SET display_name=?,bio=?,contact_number=?,gcash_number=? WHERE id=?")
                ->execute([$new_display, $new_bio, $new_contact, $new_gcash, $account_id]);
        }
        $profile_saved = true;
        $s = $pdo->prepare("SELECT * FROM accounts WHERE id = ?");
        $s->execute([$account_id]);
        $current = $s->fetch();
    }
}

$stmt = $pdo->prepare("SELECT * FROM accounts WHERE id = ? AND claim_status = 'approved'");
$stmt->execute([$account_id]);
$profile = $stmt->fetch();
if (!$profile) {
    header('Location: ' . m_base(''));
    exit;
}

$valid_games = ['valorant' => 'Valorant', 'crossfire' => 'CrossFire', 'dota2' => 'Dota 2'];

$registration = null;
if ($profile['ref_type'] === 'team') {
    $st = $pdo->prepare("SELECT * FROM teams WHERE ref_code = ?");
    $st->execute([$profile['ref_code']]);
    $registration = $st->fetch();
} else {
    $st = $pdo->prepare("SELECT * FROM solo_players WHERE ref_code = ?");
    $st->execute([$profile['ref_code']]);
    $registration = $st->fetch();
}
$team_name    = $profile['ref_type'] === 'team' ? ($registration['team_name'] ?? '') : ($registration['player_name'] ?? '');
$game         = $registration['game'] ?? '';
$display_name = !empty($profile['display_name']) ? $profile['display_name'] : $team_name;

$matches = [];
if ($team_name && $game) {
    $st = $pdo->prepare("SELECT * FROM matches WHERE game = ? AND status = 'completed' AND (team1_name = ? OR team2_name = ?) ORDER BY round ASC");
    $st->execute([$game, $team_name, $team_name]);
    $matches = $st->fetchAll();
}
$wins = 0; $losses = 0;
foreach ($matches as $m) {
    if ($m['winner'] === $team_name) $wins++; else $losses++;
}

$placements = [];
$st = $pdo->prepare("SELECT * FROM tournament_results WHERE team_name = ? ORDER BY season DESC, placement ASC");
$st->execute([$team_name]);
$placements = $st->fetchAll();

$titles = !empty($profile['titles']) ? json_decode($profile['titles'], true) : [];

$pred_stats = $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status='won' THEN 1 ELSE 0 END) as won, SUM(CASE WHEN status='lost' THEN 1 ELSE 0 END) as lost FROM match_predictions WHERE account_id = ?");
$pred_stats->execute([$account_id]);
$pstats = $pred_stats->fetch();

$initials_pf = strtoupper(substr($display_name, 0, 2));
$avatar_radius = $profile['ref_type'] === 'team' ? '18px' : '50%';

m_head(htmlspecialchars($display_name) . ' — Profile');
?>

<style>
.pf-cover{width:100%;height:140px;position:relative;background:linear-gradient(135deg,#1e0533 0%,#3b0764 35%,#1e1b4b 65%,#0f172a 100%);overflow:hidden;flex-shrink:0;}
.pf-cover-art{position:absolute;inset:0;background:radial-gradient(ellipse at 20% 60%,rgba(124,58,237,0.5) 0%,transparent 55%),radial-gradient(ellipse at 80% 30%,rgba(109,40,217,0.35) 0%,transparent 45%);}
.pf-cover-art::after{content:'';position:absolute;inset:0;background-image:linear-gradient(rgba(139,92,246,0.06) 1px,transparent 1px),linear-gradient(90deg,rgba(139,92,246,0.06) 1px,transparent 1px);background-size:40px 40px;}
.pf-av-wrap{margin-top:-44px;flex-shrink:0;}
.pf-av,.pf-av-team{width:88px;height:88px;border:3px solid var(--bg);box-shadow:0 0 0 2px rgba(139,92,246,0.4),0 6px 24px rgba(0,0,0,0.5);object-fit:cover;display:block;}
.pf-av{border-radius:50%;}
.pf-av-team{border-radius:18px;}
.pf-av-init{width:88px;height:88px;background:linear-gradient(135deg,#7c3aed,#4c1d95);border:3px solid var(--bg);box-shadow:0 0 0 2px rgba(139,92,246,0.4),0 6px 24px rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:900;color:#fff;letter-spacing:-2px;}
.pf-stat-g{display:grid;grid-template-columns:repeat(2,1fr);gap:0.5rem;padding:0 1rem 1rem;}
.pf-sm{background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:10px;padding:0.6rem 0.65rem;text-align:center;}
.pf-sm-v{font-size:1.25rem;font-weight:900;color:var(--text);line-height:1;}
.pf-sm-l{font-size:0.6rem;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:0.5px;margin-top:0.15rem;}
.pf-title-badge{display:inline-flex;align-items:center;gap:0.2rem;font-size:0.62rem;font-weight:700;color:#fbbf24;background:rgba(251,191,36,0.1);border:1px solid rgba(251,191,36,0.22);padding:0.18rem 0.45rem;border-radius:5px;}
</style>

<!-- Top bar -->
<div class="m-top" style="justify-content:space-between;">
    <a href="javascript:history.back()" class="m-back"><i class="bi bi-arrow-left"></i></a>
    <div class="m-top-title" style="text-align:center;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:55vw;"><?= htmlspecialchars($display_name) ?></div>
    <?php if ($is_own): ?>
    <a href="<?= m_base('dashboard.php') ?>" style="width:36px;height:36px;display:flex;align-items:center;justify-content:center;background:var(--card);border:1px solid var(--border);border-radius:10px;font-size:16px;color:var(--accent-l);"><i class="bi bi-speedometer2"></i></a>
    <?php else: ?>
    <div style="width:36px;"></div>
    <?php endif; ?>
</div>

<!-- Cover -->
<div class="pf-cover"><div class="pf-cover-art"></div></div>

<!-- Identity -->
<div style="padding:0 1rem 0.75rem;">
    <div style="display:flex;align-items:flex-end;gap:0.85rem;margin-bottom:0.75rem;">
        <!-- Avatar -->
        <div class="pf-av-wrap">
            <?php if (!empty($profile['profile_picture'])): ?>
                <img src="https://argonar.co/<?= htmlspecialchars($profile['profile_picture']) ?>" class="pf-av" style="border-radius:<?= $avatar_radius ?>;" alt="">
            <?php elseif ($profile['ref_type'] === 'team' && !empty($registration['team_logo'])): ?>
                <img src="https://argonar.co/<?= htmlspecialchars($registration['team_logo']) ?>" class="pf-av-team" alt="">
            <?php elseif ($profile['ref_type'] === 'solo' && !empty($registration['profile_photo'])): ?>
                <img src="https://argonar.co/<?= htmlspecialchars($registration['profile_photo']) ?>" class="pf-av" alt="">
            <?php else: ?>
                <div class="pf-av-init" style="border-radius:<?= $avatar_radius ?>;"><?= htmlspecialchars($initials_pf) ?></div>
            <?php endif; ?>
        </div>
        <!-- Name + buttons -->
        <div style="flex:1;min-width:0;padding-bottom:0.35rem;">
            <?php if ($is_own): ?>
            <button onclick="document.getElementById('editSection').scrollIntoView({behavior:'smooth'})"
                    style="float:right;background:var(--accent);color:#fff;border:none;border-radius:9px;padding:0.38rem 0.75rem;font-size:0.75rem;font-weight:800;font-family:inherit;cursor:pointer;margin-left:0.5rem;">
                <i class="bi bi-pencil"></i> Edit
            </button>
            <?php endif; ?>
            <div style="font-size:1.3rem;font-weight:900;line-height:1.1;color:var(--text);"><?= htmlspecialchars($display_name) ?></div>
        </div>
    </div>

    <!-- Meta -->
    <div style="font-size:0.78rem;color:var(--muted);margin-bottom:0.3rem;">
        <?= $profile['ref_type'] === 'team' ? '<i class="bi bi-people-fill"></i> Team' : '<i class="bi bi-person-fill"></i> Solo' ?>
        <?php if ($game): ?> · <?= htmlspecialchars($valid_games[$game] ?? $game) ?><?php endif; ?>
    </div>
    <?php if (!empty($profile['bio'])): ?>
    <div style="font-size:0.8rem;color:rgba(167,139,250,0.8);font-style:italic;margin-bottom:0.35rem;">"<?= htmlspecialchars($profile['bio']) ?>"</div>
    <?php endif; ?>
    <?php if (!empty($titles)): ?>
    <div style="display:flex;flex-wrap:wrap;gap:0.3rem;">
        <?php foreach ($titles as $t): ?><span class="pf-title-badge"><i class="bi bi-award-fill"></i> <?= htmlspecialchars($t) ?></span><?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Stats grid -->
<div class="pf-stat-g">
    <div class="pf-sm">
        <div class="pf-sm-v" style="color:#fbbf24;"><?= number_format((int)$profile['h_coins']) ?></div>
        <div class="pf-sm-l">H-Coins</div>
    </div>
    <div class="pf-sm">
        <div class="pf-sm-v"><?= count($matches) ?></div>
        <div class="pf-sm-l">Matches</div>
    </div>
    <div class="pf-sm">
        <div class="pf-sm-v" style="color:#34d399;"><?= $wins ?></div>
        <div class="pf-sm-l">Wins</div>
    </div>
    <div class="pf-sm">
        <div class="pf-sm-v" style="color:#f87171;"><?= $losses ?></div>
        <div class="pf-sm-l">Losses</div>
    </div>
    <?php if ((int)$pstats['total'] > 0): ?>
    <div class="pf-sm">
        <div class="pf-sm-v" style="color:var(--accent-l);"><?= (int)$pstats['won'] ?></div>
        <div class="pf-sm-l">Pred Won</div>
    </div>
    <div class="pf-sm">
        <div class="pf-sm-v"><?= (int)$pstats['total'] ?></div>
        <div class="pf-sm-l">Pred Total</div>
    </div>
    <?php endif; ?>
</div>

<!-- Alerts -->
<?php if ($profile_saved): ?>
<div style="margin:0 1rem 0.75rem;background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.22);border-radius:10px;padding:0.65rem 1rem;font-size:0.82rem;color:#34d399;"><i class="bi bi-check-circle-fill"></i> Profile saved.</div>
<?php endif; ?>
<?php if (!empty($profile_errors)): ?>
<div style="margin:0 1rem 0.75rem;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.22);border-radius:10px;padding:0.65rem 1rem;font-size:0.82rem;color:#f87171;"><?= implode('<br>', $profile_errors) ?></div>
<?php endif; ?>

<!-- Hall of Fame -->
<?php if (!empty($placements)): ?>
<div class="card" style="margin-bottom:1rem;">
    <div class="card-body" style="padding-bottom:0;"><div class="card-title">Hall of Fame</div></div>
    <?php foreach ($placements as $p):
        $medal = [1=>'#fbbf24',2=>'#94a3b8',3=>'#cd7f32'];
        $color = $medal[$p['placement']] ?? 'var(--muted)';
    ?>
    <div class="txn-item">
        <div style="font-size:1.1rem;color:<?= $color ?>;flex-shrink:0;width:32px;text-align:center;">
            <?php if ($p['placement'] <= 3): ?><i class="bi bi-trophy-fill"></i><?php else: ?><span style="font-size:0.78rem;font-weight:800;"><?= $p['placement'] ?>th</span><?php endif; ?>
        </div>
        <div class="txn-body">
            <div class="txn-lbl"><?= htmlspecialchars($p['season']) ?></div>
            <div class="txn-time"><?= htmlspecialchars($valid_games[$p['game']] ?? $p['game']) ?><?php if (!empty($p['prize'])): ?> · <?= htmlspecialchars($p['prize']) ?><?php endif; ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Match History -->
<?php if (!empty($matches)): ?>
<div class="card" style="margin-bottom:1rem;">
    <div class="card-body" style="padding-bottom:0;">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <div class="card-title" style="margin-bottom:0;">Match History</div>
            <span style="font-size:0.68rem;color:var(--muted);"><?= count($matches) ?> · <?= $wins ?>W <?= $losses ?>L</span>
        </div>
    </div>
    <?php foreach ($matches as $match):
        $is_t1 = $match['team1_name'] === $team_name;
        $opp   = $is_t1 ? $match['team2_name'] : $match['team1_name'];
        $ms    = $is_t1 ? $match['team1_score'] : $match['team2_score'];
        $os    = $is_t1 ? $match['team2_score'] : $match['team1_score'];
        $won   = $match['winner'] === $team_name;
        $sl    = $match['bracket_side'] === 'winners' ? 'W' : ($match['bracket_side'] === 'losers' ? 'L' : 'GF');
    ?>
    <div class="txn-item">
        <div class="txn-ico" style="background:<?= $won ? 'rgba(34,197,94,0.12)' : 'rgba(248,113,113,0.12)' ?>;color:<?= $won ? 'var(--green)' : 'var(--red)' ?>;">
            <?= $won ? 'W' : 'L' ?>
        </div>
        <div class="txn-body">
            <div class="txn-lbl">vs <?= htmlspecialchars($opp ?: 'TBD') ?></div>
            <div class="txn-time"><?= $sl ?> · Round <?= $match['round'] ?></div>
        </div>
        <div class="txn-amt" style="color:<?= $won ? 'var(--green)' : 'var(--red)' ?>;"><?= $ms ?> – <?= $os ?></div>
    </div>
    <?php endforeach; ?>
</div>
<?php elseif (empty($placements)): ?>
<div class="card" style="margin-bottom:1rem;">
    <div class="empty"><i class="bi bi-hourglass-split"></i><p>No match history yet</p></div>
</div>
<?php endif; ?>

<!-- Roster (team only) -->
<?php if ($profile['ref_type'] === 'team' && !empty($registration['members_ranks'])): ?>
<div class="card" style="margin-bottom:1rem;">
    <div class="card-body" style="padding-bottom:0;"><div class="card-title">Roster</div></div>
    <?php
    $members = explode('|', $registration['members_ranks']);
    foreach ($members as $mi => $entry):
        $parts = explode(':', $entry, 2);
        $mname = $parts[0] ?? ''; $mrank = $parts[1] ?? '';
        if (empty($mname)) continue;
    ?>
    <div class="txn-item">
        <div class="txn-ico" style="background:rgba(124,58,237,0.12);color:var(--accent-l);font-size:0.8rem;font-weight:800;">
            <?= $mi === 0 ? '<i class="bi bi-star-fill" style="font-size:11px;"></i>' : ($mi + 1) ?>
        </div>
        <div class="txn-body">
            <div class="txn-lbl"><?= htmlspecialchars($mname) ?></div>
        </div>
        <div style="font-size:0.75rem;color:var(--muted);font-weight:600;"><?= !empty($mrank) ? htmlspecialchars($mrank) : '—' ?></div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Edit Profile (own only) -->
<?php if ($is_own): ?>
<div class="card" id="editSection" style="margin-bottom:1rem;">
    <div class="card-body" style="padding-bottom:0;"><div class="card-title">Edit Profile</div></div>
    <div class="m-form" style="padding-top:0.75rem;">
        <form method="POST" enctype="multipart/form-data">
            <!-- Photo upload -->
            <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1rem;">
                <div id="avatarPreview" style="width:64px;height:64px;border-radius:<?= $avatar_radius ?>;overflow:hidden;border:2px solid var(--accent);flex-shrink:0;background:var(--card);display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:1.6rem;">
                    <?php if (!empty($profile['profile_picture'])): ?><img src="https://argonar.co/<?= htmlspecialchars($profile['profile_picture']) ?>" style="width:100%;height:100%;object-fit:cover;"><?php else: ?><i class="bi bi-person-fill"></i><?php endif; ?>
                </div>
                <div>
                    <label style="display:inline-flex;align-items:center;gap:0.3rem;cursor:pointer;background:rgba(124,58,237,0.1);border:1px solid rgba(124,58,237,0.3);border-radius:8px;padding:0.4rem 0.75rem;font-size:0.75rem;color:var(--accent-l);font-weight:700;">
                        <i class="bi bi-camera-fill"></i> Change Photo
                        <input type="file" name="profile_picture" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none;" onchange="previewAv(this)">
                    </label>
                    <div style="font-size:0.62rem;color:var(--muted);margin-top:0.15rem;">JPG, PNG, WebP · max 3MB</div>
                </div>
            </div>
            <div class="m-field">
                <label class="m-lbl">Username</label>
                <input type="text" name="display_name" class="m-inp" value="<?= htmlspecialchars($profile['display_name'] ?? '') ?>" required>
            </div>
            <div class="m-field">
                <label class="m-lbl">Bio</label>
                <textarea name="bio" class="m-inp" rows="2" maxlength="255" style="resize:vertical;" placeholder="Your motto..."><?= htmlspecialchars($profile['bio'] ?? '') ?></textarea>
            </div>
            <div class="m-field">
                <label class="m-lbl">Contact Number</label>
                <input type="text" name="contact_number" class="m-inp" value="<?= htmlspecialchars($profile['contact_number'] ?? '') ?>" placeholder="09XXXXXXXXX">
            </div>
            <div class="m-field">
                <label class="m-lbl">GCash Number</label>
                <input type="text" name="gcash_number" class="m-inp" value="<?= htmlspecialchars($profile['gcash_number'] ?? '') ?>" placeholder="09XXXXXXXXX">
            </div>
            <div style="margin-bottom:1.25rem;">
                <button type="submit" name="save_profile" value="1" class="m-btn m-btn-primary"><i class="bi bi-check-lg"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>
<script>
function previewAv(input) {
    if (!input.files || !input.files[0]) return;
    var r = new FileReader();
    r.onload = function(e) {
        document.getElementById('avatarPreview').innerHTML = '<img src="' + e.target.result + '" style="width:100%;height:100%;object-fit:cover;">';
    };
    r.readAsDataURL(input.files[0]);
}
</script>
<?php endif; ?>

<?php m_nav(''); m_toast(); m_foot(); ?>
