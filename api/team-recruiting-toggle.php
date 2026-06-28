<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/bracket_logic.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    exit;
}

$uid = (int)($_SESSION['account_id'] ?? 0);
if ($uid < 1) {
    echo json_encode(['ok' => false, 'error' => 'Login required']);
    exit;
}

ensure_team_recruiting_column($pdo);

$team_id = (int)($_POST['team_id'] ?? 0);

// No team_id → fall back to first captained team (single-team captain UX)
if ($team_id <= 0) {
    $mine = teams_captained_by($pdo, $uid);
    if (empty($mine)) {
        echo json_encode(['ok' => false, 'error' => 'You don\'t captain any teams. Add one first.']);
        exit;
    }
    $team_id = (int)$mine[0]['id'];
}

$chk = $pdo->prepare("SELECT id, recruiting, captain_account_id FROM teams WHERE id = ?");
$chk->execute([$team_id]);
$team = $chk->fetch();

if (!$team) {
    echo json_encode(['ok' => false, 'error' => 'Team not found']);
    exit;
}
if ((int)$team['captain_account_id'] !== $uid) {
    echo json_encode(['ok' => false, 'error' => 'Only the captain can toggle recruiting']);
    exit;
}

$new = $team['recruiting'] ? 0 : 1;
$pdo->prepare("UPDATE teams SET recruiting = ? WHERE id = ?")->execute([$new, $team_id]);

echo json_encode(['ok' => true, 'recruiting' => (bool)$new, 'team_id' => $team_id]);
