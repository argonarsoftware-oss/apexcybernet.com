<?php
require_once __DIR__ . '/../includes/db.php';

// Token auth
if (isset($_GET['token']) && $_GET['token'] === 'argonar-admin-2026-token') {
    $_SESSION['admin_logged_in'] = true; $_SESSION['admin_username'] = 'admin'; $_SESSION['admin_role'] = 'admin';
}
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: ' . base_url('admin/'));
    exit;
}

$type = $_GET['type'] ?? '';
$id   = (int)($_GET['id'] ?? 0);

if (!in_array($type, ['team', 'solo']) || $id <= 0) {
    header('Location: ' . base_url('admin/'));
    exit;
}

$table = $type === 'team' ? 'teams' : 'solo_players';

// Handle POST update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($type === 'team') {
        // Build members_ranks from posted fields
        $mr_parts = [];
        for ($i = 1; $i <= 5; $i++) {
            $mname = trim($_POST["member_name_$i"] ?? '');
            $mrank = trim($_POST["member_rank_$i"] ?? '');
            if ($mname !== '') {
                $mr_parts[] = $mname . ':' . $mrank;
            }
        }
        $members_ranks = implode('|', $mr_parts);

        // Also update individual member columns for backward compat
        $member_names = array_map(function($p) { return explode(':', $p, 2)[0]; }, $mr_parts);

        $stmt = $pdo->prepare("UPDATE teams SET game = ?, team_name = ?, contact_number = ?, facebook_link = ?, members_ranks = ?, member_1 = ?, member_2 = ?, member_3 = ?, member_4 = ?, member_5 = ?, status = ? WHERE id = ?");
        $stmt->execute([
            $_POST['game'] ?? '',
            $_POST['team_name'] ?? '',
            $_POST['contact_number'] ?? '',
            $_POST['facebook_link'] ?? '',
            $members_ranks,
            $member_names[0] ?? '',
            $member_names[1] ?? '',
            $member_names[2] ?? '',
            $member_names[3] ?? '',
            $member_names[4] ?? '',
            $_POST['status'] ?? 'pending',
            $id,
        ]);
    } else {
        // Auto-calculate skill rating from rank
        $rank_tiers = [
            'valorant'  => ['Iron', 'Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond', 'Ascendant', 'Immortal', 'Radiant'],
            'crossfire' => ['Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond', 'Master', 'Grand Master'],
            'dota2'     => ['Herald', 'Guardian', 'Crusader', 'Archon', 'Legend', 'Ancient', 'Divine', 'Immortal'],
        ];
        $edit_game = $_POST['game'] ?? '';
        $edit_rank = $_POST['rank_tier'] ?? '';
        $auto_rating = 5;
        if (isset($rank_tiers[$edit_game])) {
            $idx = array_search($edit_rank, $rank_tiers[$edit_game]);
            $total = count($rank_tiers[$edit_game]);
            if ($idx !== false) {
                $auto_rating = (int)round(1 + ($idx / max(1, $total - 1)) * 9);
            }
        }

        // Handle profile photo upload
        $photo_path = null;
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $photo = $_FILES['profile_photo'];
            $photo_allowed = ['image/jpeg', 'image/png', 'image/webp'];
            if (in_array($photo['type'], $photo_allowed) && $photo['size'] <= 2 * 1024 * 1024) {
                $photo_dir = __DIR__ . '/../uploads/profile_photos';
                if (!is_dir($photo_dir)) mkdir($photo_dir, 0755, true);
                $photo_ext = pathinfo($photo['name'], PATHINFO_EXTENSION);
                $photo_filename = $edit_game . '_' . preg_replace('/[^a-z0-9]/', '', strtolower($_POST['player_name'] ?? '')) . '_' . time() . '.' . $photo_ext;
                if (move_uploaded_file($photo['tmp_name'], $photo_dir . '/' . $photo_filename)) {
                    $photo_path = 'uploads/profile_photos/' . $photo_filename;
                }
            }
        }

        $sql = "UPDATE solo_players SET game = ?, real_name = ?, player_name = ?, contact_number = ?, facebook_link = ?, rank_tier = ?, preferred_role = ?, admin_rating = ?, status = ?";
        $params = [
            $edit_game,
            $_POST['real_name'] ?? '',
            $_POST['player_name'] ?? '',
            $_POST['contact_number'] ?? '',
            $_POST['facebook_link'] ?? '',
            $edit_rank,
            $_POST['preferred_role'] ?? '',
            $auto_rating,
            $_POST['status'] ?? 'pending',
        ];
        if ($photo_path !== null) {
            $sql .= ", profile_photo = ?";
            $params[] = $photo_path;
        }
        $sql .= " WHERE id = ?";
        $params[] = $id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    header('Location: ' . base_url('admin/'));
    exit;
}

// Load current record
$stmt = $pdo->prepare("SELECT * FROM {$table} WHERE id = ?");
$stmt->execute([$id]);
$record = $stmt->fetch();

if (!$record) {
    header('Location: ' . base_url('admin/'));
    exit;
}

$valid_games = [
    'valorant'  => 'Valorant',
    'crossfire' => 'CrossFire',
    'dota2'     => 'Dota 2',
];

$pageTitle = 'Edit ' . ($type === 'team' ? 'Team' : 'Solo Player') . ' — Argonar Tournament';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
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
            <h1><i class="bi bi-pencil-square"></i> Edit <?= $type === 'team' ? 'Team' : 'Solo Player' ?></h1>
        </div>
        <div class="admin-header-actions">
            <a href="<?= base_url('admin/') ?>" class="btn-back-site"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
        </div>
    </div>

    <div class="admin-section" style="max-width:700px;">
        <form method="POST" enctype="multipart/form-data">
            <div style="display:grid; gap:1rem;">
                <!-- Game -->
                <div>
                    <label style="font-size:0.8rem; color:var(--text-muted); display:block; margin-bottom:0.3rem;">Game</label>
                    <select name="game" class="form-control form-select" required>
                        <?php foreach ($valid_games as $slug => $name): ?>
                            <option value="<?= $slug ?>" <?= ($record['game'] ?? '') === $slug ? 'selected' : '' ?>><?= $name ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($type === 'team'): ?>
                    <!-- Team Name -->
                    <div>
                        <label style="font-size:0.8rem; color:var(--text-muted); display:block; margin-bottom:0.3rem;">Team Name</label>
                        <input type="text" name="team_name" class="form-control" value="<?= htmlspecialchars($record['team_name'] ?? '') ?>" required>
                    </div>
                    <!-- Contact Number -->
                    <div>
                        <label style="font-size:0.8rem; color:var(--text-muted); display:block; margin-bottom:0.3rem;">Contact Number</label>
                        <input type="text" name="contact_number" class="form-control" value="<?= htmlspecialchars($record['contact_number'] ?? '') ?>">
                    </div>
                    <!-- Facebook Link -->
                    <div>
                        <label style="font-size:0.8rem; color:var(--text-muted); display:block; margin-bottom:0.3rem;">Facebook Link</label>
                        <input type="text" name="facebook_link" class="form-control" value="<?= htmlspecialchars($record['facebook_link'] ?? '') ?>">
                    </div>
                    <!-- Members with Ranks -->
                    <?php
                    $mr_data = [];
                    if (!empty($record['members_ranks'])) {
                        foreach (explode('|', $record['members_ranks']) as $entry) {
                            $parts = explode(':', $entry, 2);
                            $mr_data[] = ['name' => $parts[0] ?? '', 'rank' => $parts[1] ?? ''];
                        }
                    }
                    // Pad to 5 slots
                    while (count($mr_data) < 5) {
                        $mr_data[] = ['name' => '', 'rank' => ''];
                    }
                    ?>
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem;">
                            <div>
                                <label style="font-size:0.8rem; color:var(--text-muted); display:block; margin-bottom:0.3rem;">Member <?= $i ?> <?= $i === 1 ? '(Captain)' : '' ?></label>
                                <input type="text" name="member_name_<?= $i ?>" class="form-control" value="<?= htmlspecialchars($mr_data[$i-1]['name']) ?>" placeholder="IGN">
                            </div>
                            <div>
                                <label style="font-size:0.8rem; color:var(--text-muted); display:block; margin-bottom:0.3rem;">Rank</label>
                                <input type="text" name="member_rank_<?= $i ?>" class="form-control" value="<?= htmlspecialchars($mr_data[$i-1]['rank']) ?>" placeholder="e.g. Diamond 2">
                            </div>
                        </div>
                    <?php endfor; ?>
                <?php else: ?>
                    <!-- Real Name -->
                    <div>
                        <label style="font-size:0.8rem; color:var(--text-muted); display:block; margin-bottom:0.3rem;">Real Name</label>
                        <input type="text" name="real_name" class="form-control" value="<?= htmlspecialchars($record['real_name'] ?? '') ?>">
                    </div>
                    <!-- Player Name (IGN) -->
                    <div>
                        <label style="font-size:0.8rem; color:var(--text-muted); display:block; margin-bottom:0.3rem;">Player Name (IGN)</label>
                        <input type="text" name="player_name" class="form-control" value="<?= htmlspecialchars($record['player_name'] ?? '') ?>" required>
                    </div>
                    <!-- Contact Number -->
                    <div>
                        <label style="font-size:0.8rem; color:var(--text-muted); display:block; margin-bottom:0.3rem;">Contact Number</label>
                        <input type="text" name="contact_number" class="form-control" value="<?= htmlspecialchars($record['contact_number'] ?? '') ?>">
                    </div>
                    <!-- Facebook Link -->
                    <div>
                        <label style="font-size:0.8rem; color:var(--text-muted); display:block; margin-bottom:0.3rem;">Facebook Link</label>
                        <input type="text" name="facebook_link" class="form-control" value="<?= htmlspecialchars($record['facebook_link'] ?? '') ?>">
                    </div>
                    <!-- Rank/Tier -->
                    <div>
                        <label style="font-size:0.8rem; color:var(--text-muted); display:block; margin-bottom:0.3rem;">Rank / Tier</label>
                        <input type="text" name="rank_tier" class="form-control" value="<?= htmlspecialchars($record['rank_tier'] ?? '') ?>">
                    </div>
                    <!-- Preferred Role -->
                    <div>
                        <label style="font-size:0.8rem; color:var(--text-muted); display:block; margin-bottom:0.3rem;">Preferred Role</label>
                        <input type="text" name="preferred_role" class="form-control" value="<?= htmlspecialchars($record['preferred_role'] ?? '') ?>">
                    </div>
                    <!-- Profile Photo -->
                    <div>
                        <label style="font-size:0.8rem; color:var(--text-muted); display:block; margin-bottom:0.3rem;">Profile Photo</label>
                        <?php if (!empty($record['profile_photo'])): ?>
                            <div style="margin-bottom:0.5rem;">
                                <img src="<?= base_url($record['profile_photo']) ?>" alt="" style="width:60px; height:60px; border-radius:50%; object-fit:cover; border:2px solid var(--border);">
                            </div>
                        <?php endif; ?>
                        <input type="file" name="profile_photo" class="form-control" accept="image/*">
                        <div style="font-size:0.7rem; color:var(--text-muted); margin-top:0.3rem;">Leave empty to keep current photo. JPG/PNG/WebP, max 2MB.</div>
                    </div>
                <?php endif; ?>

                <!-- Status -->
                <div>
                    <label style="font-size:0.8rem; color:var(--text-muted); display:block; margin-bottom:0.3rem;">Status</label>
                    <select name="status" class="form-control form-select">
                        <option value="pending" <?= ($record['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= ($record['status'] ?? '') === 'approved' ? 'selected' : '' ?>>Locked In</option>
                        <option value="rejected" <?= ($record['status'] ?? '') === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </div>

                <div style="margin-top:0.5rem;">
                    <button type="submit" class="btn-submit" style="margin-right:0.5rem;">
                        <i class="bi bi-check-lg"></i> Save Changes
                    </button>
                    <a href="<?= base_url('admin/') ?>" style="color:var(--text-muted); text-decoration:none; font-size:0.9rem;">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>

</body>
</html>
