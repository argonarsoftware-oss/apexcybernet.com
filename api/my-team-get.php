<?php
/**
 * api/my-team-get.php
 *
 * GET ?team_id=<id>
 *
 * Returns a team's data as JSON for the register.php autofill flow.
 * Gated: the session account must be the team's captain.
 */

require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$uid = (int)($_SESSION['account_id'] ?? 0);
if ($uid < 1) {
    echo json_encode(['ok' => false, 'error' => 'Login required']);
    exit;
}

$team_id = (int)($_GET['team_id'] ?? 0);
if ($team_id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'team_id required']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM teams WHERE id = ? AND captain_account_id = ?");
    $stmt->execute([$team_id, $uid]);
    $team = $stmt->fetch();
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => 'DB error']);
    exit;
}

if (!$team) {
    echo json_encode(['ok' => false, 'error' => 'Team not found, or you are not the captain']);
    exit;
}

// Parse members_ranks (format: "name:rank|name:rank|...")
$ranks = ['', '', '', '', ''];
if (!empty($team['members_ranks'])) {
    $parts = explode('|', $team['members_ranks']);
    foreach ($parts as $i => $p) {
        if ($i > 4) break;
        $kv = explode(':', $p, 2);
        $ranks[$i] = $kv[1] ?? '';
    }
}

$members = [];
for ($i = 1; $i <= 5; $i++) {
    $members[$i] = [
        'name'       => $team["member_$i"] ?? '',
        'rank'       => $ranks[$i - 1] ?? '',
        'account_id' => (int)($team["member_{$i}_account_id"] ?? 0) ?: null,
    ];
}

echo json_encode([
    'ok'   => true,
    'team' => [
        'id'                    => (int)$team['id'],
        'team_name'             => $team['team_name'] ?? '',
        'game'                  => $team['game'] ?? '',
        'ref_code'              => $team['ref_code'] ?? '',
        'team_logo'             => $team['team_logo'] ?? '',
        'contact_number'        => $team['contact_number'] ?? '',
        'facebook_link'         => $team['facebook_link'] ?? '',
        'members'               => $members,
        'substitute'            => $team['substitute'] ?? '',
        'substitute_account_id' => (int)($team['substitute_account_id'] ?? 0) ?: null,
    ],
]);
