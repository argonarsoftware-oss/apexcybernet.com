<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/bracket_logic.php';

header('Content-Type: application/json');

$uid = (int)($_SESSION['account_id'] ?? 0);
if ($uid < 1) {
    echo json_encode(['ok' => false, 'error' => 'Login required']);
    exit;
}

ensure_team_recruiting_column($pdo);

$captained = teams_captained_by($pdo, $uid);
$claimable = teams_claimable_by($pdo, $uid);

// Trim down to what the UI actually needs
$trim = function ($t) {
    return [
        'id'           => (int)$t['id'],
        'game'         => $t['game']        ?? '',
        'team_name'    => $t['team_name']   ?? '',
        'team_logo'    => $t['team_logo']   ?? '',
        'ref_code'     => $t['ref_code']    ?? '',
        'member_1'     => $t['member_1']    ?? '',
        'status'       => $t['status']      ?? '',
        'recruiting'   => !empty($t['recruiting']),
    ];
};

echo json_encode([
    'ok'        => true,
    'captained' => array_map($trim, $captained),
    'claimable' => array_map($trim, $claimable),
]);
