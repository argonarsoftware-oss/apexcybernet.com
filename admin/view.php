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

// Load record
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

$pageTitle = 'View ' . ($type === 'team' ? 'Team' : 'Solo Player') . ' — Argonar Tournament';
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
            <h1><i class="bi bi-eye"></i> View <?= $type === 'team' ? 'Team' : 'Solo Player' ?></h1>
        </div>
        <div class="admin-header-actions">
            <a href="<?= base_url('admin/') ?>" class="btn-back-site"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
            <a href="<?= base_url('admin/edit.php?type=' . $type . '&id=' . $id) ?>" class="btn-edit" style="padding:0.5rem 1rem; font-size:0.85rem;"><i class="bi bi-pencil"></i> Edit</a>
            <button class="btn-delete" style="padding:0.5rem 1rem; font-size:0.85rem;" onclick="deleteRecord()"><i class="bi bi-trash"></i> Delete</button>
        </div>
    </div>

    <?php if ($type === 'team'): ?>
        <!-- Team View -->
        <div class="view-card">
            <div class="view-card-header">
                <h2><i class="bi bi-people-fill"></i> Team Details</h2>
                <span class="status-badge status-<?= $record['status'] ?>"><?= ucfirst($record['status']) ?></span>
            </div>
            <div class="view-card-body">
                <div class="view-row">
                    <span class="view-label">Ref Code</span>
                    <span class="view-value"><code><?= htmlspecialchars($record['ref_code'] ?? '—') ?></code></span>
                </div>
                <div class="view-row">
                    <span class="view-label">Game</span>
                    <span class="view-value"><?= htmlspecialchars($valid_games[$record['game']] ?? $record['game']) ?></span>
                </div>
                <div class="view-row">
                    <span class="view-label">Team Name</span>
                    <span class="view-value"><strong><?= htmlspecialchars($record['team_name']) ?></strong></span>
                </div>
                <div class="view-row">
                    <span class="view-label">Contact Number</span>
                    <span class="view-value"><?= htmlspecialchars($record['contact_number'] ?? '—') ?: '—' ?></span>
                </div>
                <div class="view-row">
                    <span class="view-label">Facebook</span>
                    <span class="view-value">
                        <?php if (!empty($record['facebook_link'])): ?>
                            <a href="<?= htmlspecialchars($record['facebook_link']) ?>" target="_blank" rel="noopener" style="color:var(--accent-light);">
                                <i class="bi bi-facebook"></i> <?= htmlspecialchars($record['facebook_link']) ?>
                            </a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </span>
                </div>

                <?php if (!empty($record['team_logo'])): ?>
                    <div class="view-row">
                        <span class="view-label">Team Logo</span>
                        <span class="view-value">
                            <img src="<?= base_url($record['team_logo']) ?>" alt="Team Logo" style="max-width:120px; max-height:120px; border-radius:8px; border:1px solid var(--border);">
                        </span>
                    </div>
                <?php endif; ?>

                <div class="view-row">
                    <span class="view-label">Registered</span>
                    <span class="view-value"><?= htmlspecialchars($record['created_at'] ?? '—') ?></span>
                </div>
            </div>
        </div>

        <!-- Members Card -->
        <div class="view-card">
            <div class="view-card-header">
                <h2><i class="bi bi-person-badge"></i> Members</h2>
            </div>
            <div class="view-card-body">
                <?php
                $mr_entries = !empty($record['members_ranks']) ? explode('|', $record['members_ranks']) : [];
                $mi = 0;
                foreach ($mr_entries as $entry):
                    $parts = explode(':', $entry, 2);
                    $mname = trim($parts[0] ?? '');
                    $mrank = trim($parts[1] ?? '');
                    if (empty($mname)) continue;
                    $mi++;
                ?>
                    <div class="view-row">
                        <span class="view-label"><?= $mi === 1 ? '<i class="bi bi-star-fill" style="color:#fbbf24;font-size:0.7rem;"></i> Captain' : 'Member ' . $mi ?></span>
                        <span class="view-value">
                            <?= htmlspecialchars($mname) ?>
                            <?php if (!empty($mrank)): ?>
                                <span class="member-rank-badge"><?= htmlspecialchars($mrank) ?></span>
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endforeach;
                if ($mi === 0): ?>
                    <p style="color:var(--text-muted); padding:0.5rem 0;">No members listed.</p>
                <?php endif; ?>
            </div>
        </div>

    <?php else: ?>
        <!-- Solo Player View -->
        <div class="view-card">
            <div class="view-card-header">
                <h2><i class="bi bi-person-fill"></i> Player Details</h2>
                <span class="status-badge status-<?= $record['status'] ?>"><?= ucfirst($record['status']) ?></span>
            </div>
            <div class="view-card-body">
                <div class="view-row">
                    <span class="view-label">Ref Code</span>
                    <span class="view-value"><code><?= htmlspecialchars($record['ref_code'] ?? '—') ?></code></span>
                </div>
                <div class="view-row">
                    <span class="view-label">Game</span>
                    <span class="view-value"><?= htmlspecialchars($valid_games[$record['game']] ?? $record['game']) ?></span>
                </div>
                <div class="view-row">
                    <span class="view-label">Real Name</span>
                    <span class="view-value"><?= htmlspecialchars($record['real_name'] ?? '—') ?: '—' ?></span>
                </div>
                <div class="view-row">
                    <span class="view-label">IGN</span>
                    <span class="view-value"><strong><?= htmlspecialchars($record['player_name']) ?></strong></span>
                </div>
                <?php if (!empty($record['profile_photo'])): ?>
                <div class="view-row">
                    <span class="view-label">Profile Photo</span>
                    <span class="view-value">
                        <img src="<?= base_url($record['profile_photo']) ?>" alt="" style="width:80px; height:80px; border-radius:50%; object-fit:cover; border:2px solid var(--border);">
                    </span>
                </div>
                <?php endif; ?>
                <div class="view-row">
                    <span class="view-label">Contact Number</span>
                    <span class="view-value"><?= htmlspecialchars($record['contact_number'] ?? '—') ?: '—' ?></span>
                </div>
                <div class="view-row">
                    <span class="view-label">Facebook</span>
                    <span class="view-value">
                        <?php if (!empty($record['facebook_link'])): ?>
                            <a href="<?= htmlspecialchars($record['facebook_link']) ?>" target="_blank" rel="noopener" style="color:var(--accent-light);">
                                <i class="bi bi-facebook"></i> <?= htmlspecialchars($record['facebook_link']) ?>
                            </a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </span>
                </div>
                <div class="view-row">
                    <span class="view-label">Rank</span>
                    <span class="view-value"><?= htmlspecialchars($record['rank_tier'] ?? '—') ?></span>
                </div>
                <div class="view-row">
                    <span class="view-label">Preferred Role</span>
                    <span class="view-value"><?= htmlspecialchars($record['preferred_role'] ?? '—') ?: '—' ?></span>
                </div>
                <div class="view-row">
                    <span class="view-label">Skill Gauge</span>
                    <span class="view-value">
                        <?php $rating = (int)($record['admin_rating'] ?? 0); ?>
                        <div class="skill-bar-container">
                            <div class="skill-bar-fill" style="width: <?= $rating * 10 ?>%;"></div>
                        </div>
                        <span style="margin-left:0.5rem; font-weight:600;"><?= $rating ?>/10</span>
                    </span>
                </div>
                <div class="view-row">
                    <span class="view-label">Registered</span>
                    <span class="view-value"><?= htmlspecialchars($record['created_at'] ?? '—') ?></span>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div>

<script>
function deleteRecord() {
    if (!confirm('Are you sure you want to delete this <?= $type === 'team' ? 'team' : 'player' ?>? This action cannot be undone.')) {
        return;
    }

    const formData = new FormData();
    formData.append('type', '<?= $type ?>');
    formData.append('id', <?= $id ?>);
    formData.append('action', 'delete');

    fetch('<?= base_url("admin/action.php") ?>', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            window.location.href = '<?= base_url("admin/") ?>';
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(err => {
        alert('Request failed: ' + err.message);
    });
}
</script>

</body>
</html>
