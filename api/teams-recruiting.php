<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/bracket_logic.php';

header('Content-Type: application/json');

ensure_team_recruiting_column($pdo);
ensure_reserved_columns($pdo);

try {
    $stmt = $pdo->query("
        SELECT t.id, t.game, t.team_name, t.team_logo, t.ref_code, t.status,
               t.member_1, t.member_2, t.member_3, t.member_4, t.member_5, t.substitute,
               a.id AS captain_account_id, a.display_name AS captain_name, a.profile_picture AS captain_photo
        FROM teams t
        LEFT JOIN accounts a ON a.ref_type = 'team' AND a.ref_code = t.ref_code AND a.claim_status = 'approved'
        WHERE t.recruiting = 1 AND t.reserved = 0
        ORDER BY t.created_at DESC
    ");
    $rows = $stmt->fetchAll();

    $out = [];
    foreach ($rows as $r) {
        $members_filled = 0;
        for ($i = 1; $i <= 5; $i++) {
            if (!empty(trim($r["member_$i"] ?? ''))) $members_filled++;
        }
        $open_slots = max(0, 5 - $members_filled);
        $out[] = [
            'id'           => (int)$r['id'],
            'game'         => $r['game'],
            'team_name'    => $r['team_name'],
            'team_logo'    => $r['team_logo'],
            'ref_code'     => $r['ref_code'],
            'paid'         => $r['status'] === 'approved',
            'members_filled' => $members_filled,
            'open_slots'   => $open_slots,
            'has_substitute' => !empty(trim($r['substitute'] ?? '')),
            'captain_id'   => $r['captain_account_id'] ? (int)$r['captain_account_id'] : null,
            'captain_name' => $r['captain_name'],
            'captain_photo' => $r['captain_photo'],
        ];
    }

    echo json_encode(['teams' => $out], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['teams' => [], 'error' => 'lookup failed']);
}
