<?php
require_once __DIR__ . '/includes/db.php';

$valid_games = [
    'valorant'  => 'Valorant',
    // 'crossfire' => 'CrossFire',  // hidden — data preserved
    'dota2'     => 'Dota 2',
];

$game_slug = $_GET['game'] ?? '';
if (!isset($valid_games[$game_slug])) {
    header('Location: ' . base_url());
    exit;
}

$rank_tiers = [
    'valorant'  => ['Iron', 'Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond', 'Ascendant', 'Immortal', 'Radiant'],
    'crossfire' => ['Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond', 'Master', 'Grand Master'],
    'dota2'     => ['Herald', 'Guardian', 'Crusader', 'Archon', 'Legend', 'Ancient', 'Divine', 'Immortal'],
];

$game_prefixes = [
    'valorant'  => 'VAL',
    'crossfire' => 'CF',
    'dota2'     => 'DOTA',
];

$game_name = $valid_games[$game_slug];
$pageTitle = "Register Your Team — Apex Cybernet $game_name Tournament";
$pageDescription = "Register your $game_name team for the Apex Cybernet Tournament. ₱550/team entry, 5-player team, ₱20,000 cash prize. Double elimination at Apex Cybernet Cafe, Cebu City.";
$canonicalUrl = canonical_url('register.php?game=' . $game_slug);
$extraHead = breadcrumb_jsonld([
    ['name' => 'Home',          'url' => 'https://apexcybernet.com/'],
    ['name' => 'Register Team', 'url' => 'https://apexcybernet.com/register.php?game=' . $game_slug],
]);
$errors = [];

function generate_ref_code($pdo, $prefix, $type) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    for ($attempt = 0; $attempt < 20; $attempt++) {
        $rand = '';
        for ($i = 0; $i < 4; $i++) {
            $rand .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $code = $prefix . '-' . $type . '-' . $rand;
        // Check uniqueness in both tables
        $check1 = $pdo->prepare("SELECT 1 FROM teams WHERE ref_code = ?");
        $check1->execute([$code]);
        $check2 = $pdo->prepare("SELECT 1 FROM solo_players WHERE ref_code = ?");
        $check2->execute([$code]);
        if (!$check1->fetch() && !$check2->fetch()) {
            return $code;
        }
    }
    return $prefix . '-' . $type . '-' . strtoupper(bin2hex(random_bytes(2)));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $team_name = trim($_POST['team_name'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $facebook_link = trim($_POST['facebook_link'] ?? '');
    $substitute = trim($_POST['substitute'] ?? '');
    $members = [];
    $member_ranks = [];
    for ($i = 1; $i <= 5; $i++) {
        $members[$i] = trim($_POST["member_$i"] ?? '');
        $member_ranks[$i] = trim($_POST["member_rank_$i"] ?? '');
    }

    // Validate
    if ($team_name === '') {
        $errors[] = 'Team name is required.';
    }
    foreach ($members as $i => $m) {
        if ($m === '') {
            $errors[] = "Member $i name is required.";
        }
    }

    // Check duplicate team name per game
    if (empty($errors)) {
        $check = $pdo->prepare("SELECT id FROM teams WHERE game = ? AND team_name = ?");
        $check->execute([$game_slug, $team_name]);
        if ($check->fetch()) {
            $errors[] = "Team name \"$team_name\" is already registered for $game_name.";
        }
    }

    // Payment proof is handled on the ticket page after registration
    $upload_path = '';

    // Handle team logo upload (optional)
    $logo_path = '';
    if (empty($errors) && isset($_FILES['team_logo']) && $_FILES['team_logo']['error'] === UPLOAD_ERR_OK) {
        $logo = $_FILES['team_logo'];
        $logo_allowed = ['image/jpeg', 'image/png', 'image/webp'];
        $logo_max = 2 * 1024 * 1024; // 2MB

        if (!in_array($logo['type'], $logo_allowed)) {
            $errors[] = 'Team logo must be JPG, PNG, or WebP.';
        } elseif ($logo['size'] > $logo_max) {
            $errors[] = 'Team logo is too large. Maximum 5MB.';
        } else {
            $logo_dir = __DIR__ . '/uploads/team_logos';
            if (!is_dir($logo_dir)) {
                mkdir($logo_dir, 0755, true);
            }
            $logo_ext = pathinfo($logo['name'], PATHINFO_EXTENSION);
            $logo_filename = $game_slug . '_' . preg_replace('/[^a-z0-9]/', '', strtolower($team_name)) . '_' . time() . '.' . $logo_ext;
            $logo_dest = $logo_dir . '/' . $logo_filename;

            if (move_uploaded_file($logo['tmp_name'], $logo_dest)) {
                $logo_path = 'uploads/team_logos/' . $logo_filename;
            }
        }
    }

    // Insert
    if (empty($errors)) {
        try {
            $ref_code = generate_ref_code($pdo, $game_prefixes[$game_slug], 'T');

            $members_data = '';
            for ($i = 1; $i <= 5; $i++) {
                $members_data .= ($i > 1 ? '|' : '') . $members[$i] . ':' . $member_ranks[$i];
            }

            $stmt = $pdo->prepare("INSERT INTO teams (game, team_name, team_logo, ref_code, contact_number, facebook_link, member_1, member_2, member_3, member_4, member_5, substitute, members_ranks, payment_proof) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $game_slug,
                $team_name,
                $logo_path,
                $ref_code,
                $contact_number,
                $facebook_link,
                $members[1], $members[2], $members[3], $members[4], $members[5],
                $substitute,
                $members_data,
                $upload_path,
            ]);

            $_SESSION['ref_code'] = $ref_code;
            header("Location: " . base_url("ticket.php?ref=$ref_code&type=team&game=$game_slug"));
            exit;
        } catch (Exception $e) {
            $errors[] = 'Registration failed. Please try again. Error: ' . $e->getMessage();
        }
    }
}

$hideCompany = true; // scrub company name from meta/SEO on this page
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/he-chrome.php';
?>

<section class="he-page-hero">
    <div class="he-page-eyebrow">Team registration · <?= htmlspecialchars($game_name) ?></div>
    <h1 class="he-page-title">Lock in your squad.</h1>
    <p class="he-page-sub">Five players, one captain. Substitutes optional. Submit, pay ₱550, and you're seeded into the bracket.</p>
</section>

<div class="he-card">
    <div class="he-card-inner">
        <?php if (!empty($errors)): ?>
            <div class="he-notice danger">
                <i class="bi bi-exclamation-circle-fill"></i>
                <div><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="he-card-section">
                <div class="he-card-section-label">Team</div>
                <div class="he-field">
                    <label>Team name</label>
                    <input type="text" name="team_name" placeholder="e.g. Shadow Wolves"
                           value="<?= htmlspecialchars($_POST['team_name'] ?? '') ?>" required>
                </div>
                <div class="he-field">
                    <label>Contact number <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
                    <input type="tel" name="contact_number" placeholder="09XX XXX XXXX"
                           value="<?= htmlspecialchars($_POST['contact_number'] ?? '') ?>">
                </div>
                <div class="he-field">
                    <label>Facebook profile <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
                    <input type="url" name="facebook_link" placeholder="https://facebook.com/yourprofile"
                           value="<?= htmlspecialchars($_POST['facebook_link'] ?? '') ?>">
                </div>
                <div class="he-field">
                    <label>Team logo <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
                    <input type="file" name="team_logo" accept="image/*">
                    <div class="he-field-hint">JPG, PNG, or WebP. Max 5MB. Shown on the registered-teams list.</div>
                </div>
            </div>

            <div class="he-card-section">
                <div class="he-card-section-label">Roster · 5 players</div>
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <div class="he-field">
                        <label><?= $i === 1 ? 'Team captain' : "Player $i" ?></label>
                        <input type="text" name="member_<?= $i ?>" placeholder="Full name or in-game name"
                               value="<?= htmlspecialchars($_POST["member_$i"] ?? '') ?>" required style="margin-bottom:8px;">
                        <?php if ($game_slug === 'crossfire'): ?>
                            <input type="hidden" name="member_rank_<?= $i ?>" value="N/A">
                        <?php else: ?>
                            <select name="member_rank_<?= $i ?>" required>
                                <option value="">Select rank</option>
                                <?php foreach ($rank_tiers[$game_slug] as $rank): ?>
                                    <option value="<?= htmlspecialchars($rank) ?>" <?= (($_POST["member_rank_$i"] ?? '') === $rank) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($rank) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>
                <div class="he-field">
                    <label>Substitute <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
                    <input type="text" name="substitute" placeholder="Full name or in-game name"
                           value="<?= htmlspecialchars($_POST['substitute'] ?? '') ?>">
                    <div class="he-field-hint">One sub allowed per team. Must be declared before the tournament — undeclared subs can't play.</div>
                </div>

                <div class="he-notice danger" style="margin-top:14px;">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div><strong>Rank integrity.</strong> Submit your true in-game rank. Smurfing, manipulation, or misrepresentation will result in disqualification and a community ban at the organizer's discretion.</div>
                </div>
            </div>

            <div class="he-card-section">
                <div class="he-card-section-label">Entry</div>
                <div style="display:flex; align-items:baseline; justify-content:space-between; padding:14px 16px; background:var(--bg-subtle); border-radius:10px; margin-bottom:10px;">
                    <div>
                        <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.08em; font-weight:700; margin-bottom:2px;">Registration fee</div>
                        <div style="font-family:var(--mono); font-variant-numeric:tabular-nums; font-size:24px; font-weight:700; color:var(--text); letter-spacing:-0.025em;">&#8369;550 <span style="font-size:13px; color:var(--text-muted); font-weight:500;">/ team</span></div>
                    </div>
                    <div style="font-size:12px; color:var(--text-muted); max-width:240px; text-align:right;">Pay on the next page via QR Ph. Your slot locks when payment clears.</div>
                </div>
                <div class="he-field-hint">PC time at the venue is paid directly to Apex Cybernet Cafe and is separate from this fee.</div>
            </div>

            <div class="he-card-section">
                <div class="he-card-section-label">Terms</div>
                <div class="he-prose" style="font-size:13px;">
                    <ul style="margin-left:18px;">
                        <li><strong>Media release.</strong> You consent to photo, video, and live recording during the event.</li>
                        <li><strong>Fair play.</strong> Cheating, smurfing, or unsportsmanlike behavior = disqualification.</li>
                        <li><strong>Violations.</strong> Penalties including DQ, prize forfeiture, and bans at organizer discretion.</li>
                        <li><strong>Entry fee.</strong> ₱550 per team, payable on the next page.</li>
                        <li><strong>Reputation.</strong> Your record on this stage builds your standing in the community.</li>
                    </ul>
                </div>
                <label style="display:flex; align-items:flex-start; gap:10px; padding:14px 16px; background:var(--bg-subtle); border-radius:10px; cursor:pointer; font-size:13px;">
                    <input type="checkbox" name="agree_terms" required style="width:auto; margin-top:2px;">
                    <span>I agree to the terms above, the <a href="<?= base_url('terms.php') ?>" target="_blank" style="color:var(--accent-light); text-decoration:underline;">Terms of Service</a>, and the <a href="<?= base_url('privacy.php') ?>" target="_blank" style="color:var(--accent-light); text-decoration:underline;">Privacy Policy</a>.</span>
                </label>
            </div>

            <button type="submit" class="he-btn-primary he-btn-full" style="margin-top:24px;">
                Submit registration <i class="bi bi-arrow-right"></i>
            </button>
            <div style="text-align:center; margin-top:14px;">
                <a href="<?= base_url() ?>" style="font-size:13px; color:var(--text-muted); text-decoration:none;">← Back to tournament</a>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/he-foot.php'; return; ?>
