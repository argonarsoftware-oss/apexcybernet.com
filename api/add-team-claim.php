<?php
require_once __DIR__ . '/../includes/db.php';

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

$team_id = (int)($_POST['team_id'] ?? 0);
if ($team_id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'team_id required']);
    exit;
}

$acc = $pdo->prepare("SELECT display_name FROM accounts WHERE id = ? AND claim_status = 'approved'");
$acc->execute([$uid]);
$name = trim((string)$acc->fetchColumn());
if ($name === '') {
    echo json_encode(['ok' => false, 'error' => 'Account not found or not approved']);
    exit;
}

$chk = $pdo->prepare("SELECT id, team_name, member_1, captain_account_id FROM teams WHERE id = ?");
$chk->execute([$team_id]);
$team = $chk->fetch();

if (!$team) {
    echo json_encode(['ok' => false, 'error' => 'Team not found']);
    exit;
}
if (!empty($team['captain_account_id'])) {
    echo json_encode(['ok' => false, 'error' => 'This team has already been claimed']);
    exit;
}
if (trim((string)$team['member_1']) !== $name) {
    echo json_encode(['ok' => false, 'error' => 'Only the captain (member 1) can claim this team']);
    exit;
}

$pdo->prepare("UPDATE teams SET captain_account_id = ? WHERE id = ?")->execute([$uid, $team_id]);

echo json_encode([
    'ok' => true,
    'team_id'   => $team_id,
    'team_name' => $team['team_name'],
]);
