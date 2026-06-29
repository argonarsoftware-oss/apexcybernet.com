<?php
require_once __DIR__ . '/includes/db.php';

$valid_games = [
    'valorant'  => 'Valorant',
    // 'crossfire' => 'CrossFire',  // hidden — data preserved
    'dota2'     => 'Dota 2',
];

$rank_tiers = [
    'valorant'  => ['Iron', 'Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond', 'Ascendant', 'Immortal', 'Radiant'],
    'crossfire' => ['Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond', 'Master', 'Grand Master'],
    'dota2'     => ['Herald', 'Guardian', 'Crusader', 'Archon', 'Legend', 'Ancient', 'Divine', 'Immortal'],
];

$roles = [
    'valorant'  => ['Duelist', 'Initiator', 'Controller', 'Sentinel', 'Flexible (Any)'],
    'crossfire' => ['Rifler', 'Sniper', 'Support', 'Entry Fragger', 'Flexible (Any)'],
    'dota2'     => ['Carry (Pos 1)', 'Mid (Pos 2)', 'Offlane (Pos 3)', 'Soft Support (Pos 4)', 'Hard Support (Pos 5)', 'Flexible (Any)'],
];

$game_prefixes = [
    'valorant'  => 'VAL',
    'crossfire' => 'CF',
    'dota2'     => 'DOTA',
];

$game_slug = $_GET['game'] ?? '';
if (!isset($valid_games[$game_slug])) {
    header('Location: ' . base_url());
    exit;
}

$game_name = $valid_games[$game_slug];
$pageTitle = "Solo Player Entry — Apex Cybernet $game_name Tournament";
$pageDescription = "Don't have a team? Enter solo for the Apex Cybernet $game_name Tournament. ₱110 solo entry. Get matched with players of similar rank to form a team automatically.";
$canonicalUrl = canonical_url('matchmaking.php?game=' . $game_slug);
$extraHead = breadcrumb_jsonld([
    ['name' => 'Home',       'url' => 'https://apexcybernet.com/'],
    ['name' => 'Solo Entry', 'url' => 'https://apexcybernet.com/matchmaking.php?game=' . $game_slug],
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
    $real_name      = trim($_POST['real_name'] ?? '');
    $player_name    = trim($_POST['player_name'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $facebook_link  = trim($_POST['facebook_link'] ?? '');
    $rank_tier      = trim($_POST['rank_tier'] ?? '');
    $preferred_role = trim($_POST['preferred_role'] ?? '');

    // Validate
    if ($real_name === '') {
        $errors[] = 'Real name is required.';
    }
    if ($player_name === '') {
        $errors[] = 'In-game name is required.';
    }
    if ($game_slug !== 'crossfire') {
        if ($rank_tier === '') {
            $errors[] = 'Rank is required.';
        } elseif (!in_array($rank_tier, $rank_tiers[$game_slug])) {
            $errors[] = 'Please select a valid rank.';
        }
    }
    if ($preferred_role === '' || !in_array($preferred_role, $roles[$game_slug])) {
        $errors[] = 'Please select your preferred role.';
    }

    // Payment proof is handled on the ticket page after registration
    $upload_path = '';

    // Handle profile photo upload (optional)
    $photo_path = '';
    if (empty($errors) && isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $photo = $_FILES['profile_photo'];
        $photo_allowed = ['image/jpeg', 'image/png', 'image/webp'];
        $photo_max = 2 * 1024 * 1024;

        if (!in_array($photo['type'], $photo_allowed)) {
            $errors[] = 'Profile photo must be JPG, PNG, or WebP.';
        } elseif ($photo['size'] > $photo_max) {
            $errors[] = 'Profile photo is too large. Maximum 2MB.';
        } else {
            $photo_dir = __DIR__ . '/uploads/profile_photos';
            if (!is_dir($photo_dir)) mkdir($photo_dir, 0755, true);
            $photo_ext = pathinfo($photo['name'], PATHINFO_EXTENSION);
            $photo_filename = $game_slug . '_' . preg_replace('/[^a-z0-9]/', '', strtolower($player_name)) . '_' . time() . '.' . $photo_ext;
            if (move_uploaded_file($photo['tmp_name'], $photo_dir . '/' . $photo_filename)) {
                $photo_path = 'uploads/profile_photos/' . $photo_filename;
            }
        }
    }

    // Insert
    if (empty($errors)) {
        try {
            $ref_code = generate_ref_code($pdo, $game_prefixes[$game_slug], 'S');

            // Auto-calculate skill rating from rank (1-10 scale)
            $rank_index = array_search($rank_tier, $rank_tiers[$game_slug]);
            $total_ranks = count($rank_tiers[$game_slug]);
            $admin_rating = ($rank_index !== false) ? (int)round(1 + ($rank_index / max(1, $total_ranks - 1)) * 9) : 5;

            $stmt = $pdo->prepare("INSERT INTO solo_players (game, real_name, player_name, contact_number, facebook_link, rank_tier, preferred_role, profile_photo, ref_code, admin_rating, payment_proof) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $game_slug,
                $real_name,
                $player_name,
                $contact_number,
                $facebook_link,
                $rank_tier,
                $preferred_role,
                $photo_path,
                $ref_code,
                $admin_rating,
                $upload_path,
            ]);

            $_SESSION['ref_code'] = $ref_code;
            header("Location: " . base_url("ticket.php?ref=$ref_code&type=solo&game=$game_slug"));
            exit;
        } catch (Exception $e) {
            $errors[] = 'Registration failed. Please try again. Error: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/he-chrome.php';
?>

<section class="he-page-hero">
    <div class="he-page-eyebrow">Solo entry · <?= htmlspecialchars($game_name) ?></div>
    <h1 class="he-page-title">No team? No problem.</h1>
    <p class="he-page-sub">Enter as a solo, declare your rank and preferred role, and we'll match you with four other players to form a balanced squad.</p>
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
                <div class="he-card-section-label">Player</div>
                <div class="he-field">
                    <label>Real name</label>
                    <input type="text" name="real_name" placeholder="Your full real name"
                           value="<?= htmlspecialchars($_POST['real_name'] ?? '') ?>" required>
                </div>
                <div class="he-field">
                    <label>In-game name (IGN)</label>
                    <input type="text" name="player_name" placeholder="Your gamertag"
                           value="<?= htmlspecialchars($_POST['player_name'] ?? '') ?>" required>
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
                    <label>Profile photo <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
                    <input type="file" name="profile_photo" accept="image/*">
                    <div class="he-field-hint">JPG, PNG, or WebP. Max 2MB. Shown on the registered players list.</div>
                </div>
            </div>

            <div class="he-card-section">
                <div class="he-card-section-label">Skill profile</div>
                <?php if ($game_slug !== 'crossfire'): ?>
                <div class="he-notice">
                    <i class="bi bi-shield-check"></i>
                    <div><strong>Keep it real.</strong> We use your declared rank to seed matchups. Honest entries make for better games and protect your reputation.</div>
                </div>
                <div class="he-field">
                    <label>Rank</label>
                    <select name="rank_tier" required>
                        <option value="">Select your rank</option>
                        <?php foreach ($rank_tiers[$game_slug] as $rank): ?>
                            <option value="<?= htmlspecialchars($rank) ?>" <?= (($_POST['rank_tier'] ?? '') === $rank) ? 'selected' : '' ?>><?= htmlspecialchars($rank) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                    <input type="hidden" name="rank_tier" value="N/A">
                <?php endif; ?>
                <div class="he-field">
                    <label>Preferred role</label>
                    <select name="preferred_role" required>
                        <option value="">Pick your preferred role</option>
                        <?php foreach ($roles[$game_slug] as $role): ?>
                            <option value="<?= htmlspecialchars($role) ?>" <?= (($_POST['preferred_role'] ?? '') === $role) ? 'selected' : '' ?>><?= htmlspecialchars($role) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="he-field-hint">Helps us balance teams. You can renegotiate roles with your squad before the match.</div>
                </div>

                <div class="he-notice danger" style="margin-top:14px;">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div><strong>Rank integrity.</strong> Submit your true rank. Smurfing or misrepresentation will result in disqualification.</div>
                </div>
            </div>

            <div class="he-card-section">
                <div class="he-card-section-label">Entry</div>
                <div style="display:flex; align-items:baseline; justify-content:space-between; padding:14px 16px; background:var(--bg-subtle); border-radius:10px; margin-bottom:10px;">
                    <div>
                        <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.08em; font-weight:700; margin-bottom:2px;">Solo entry fee</div>
                        <div style="font-family:var(--mono); font-variant-numeric:tabular-nums; font-size:24px; font-weight:700; color:var(--text); letter-spacing:-0.025em;">&#8369;110 <span style="font-size:13px; color:var(--text-muted); font-weight:500;">/ player</span></div>
                    </div>
                    <div style="font-size:12px; color:var(--text-muted); max-width:240px; text-align:right;">Pay on the next page via QR Ph. Slot locks when payment clears.</div>
                </div>
                <div class="he-field-hint">PC time at Apex Cybernet Cafe is paid directly to the venue and is separate.</div>
            </div>

            <div class="he-card-section">
                <div class="he-card-section-label">Terms</div>
                <div class="he-prose" style="font-size:13px;">
                    <ul style="margin-left:18px;">
                        <li><strong>Media release.</strong> You consent to photo and video during the event.</li>
                        <li><strong>Fair play.</strong> Cheating or smurfing = disqualification.</li>
                        <li><strong>Violations.</strong> Penalties at organizer discretion.</li>
                        <li><strong>Entry fee.</strong> ₱110 per solo, payable on the next page.</li>
                        <li><strong>Reputation.</strong> Build your name on this stage.</li>
                    </ul>
                </div>
                <label style="display:flex; align-items:flex-start; gap:10px; padding:14px 16px; background:var(--bg-subtle); border-radius:10px; cursor:pointer; font-size:13px;">
                    <input type="checkbox" name="agree_terms" required style="width:auto; margin-top:2px;">
                    <span>I agree to the terms above, the <a href="<?= base_url('terms.php') ?>" target="_blank" style="color:var(--accent-light); text-decoration:underline;">Terms of Service</a>, and the <a href="<?= base_url('privacy.php') ?>" target="_blank" style="color:var(--accent-light); text-decoration:underline;">Privacy Policy</a>.</span>
                </label>
            </div>

            <button type="submit" class="he-btn-primary he-btn-full" style="margin-top:24px;">
                Find me a team <i class="bi bi-arrow-right"></i>
            </button>
            <div style="text-align:center; margin-top:14px;">
                <a href="<?= base_url() ?>" style="font-size:13px; color:var(--text-muted); text-decoration:none;">← Back to tournament</a>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/he-foot.php'; return; ?>
