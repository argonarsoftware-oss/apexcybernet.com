<?php
/**
 * POST /api/voice-token.php  { group_id }   — voice room for a group
 *                          OR { peer_id }    — voice room for a 1-on-1 DM
 *
 * Issues a short-lived signed token that the browser uses to subscribe to the
 * voice signaling SSE stream.
 *  - Group rooms: requester must be a member of that group.
 *  - DM rooms:   requester must be different from peer (no self-DM); both must be approved accounts.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/voice_config.php';

header('Content-Type: application/json');

if (empty($_SESSION['account_id'])) { http_response_code(401); echo json_encode(['error'=>'not_logged_in']); exit; }
$me   = (int)$_SESSION['account_id'];
$gid  = (int)($_POST['group_id'] ?? $_GET['group_id'] ?? 0);
$peer = (int)($_POST['peer_id']  ?? $_GET['peer_id']  ?? 0);

if ($gid <= 0 && $peer <= 0) { http_response_code(400); echo json_encode(['error'=>'missing_room']); exit; }

try {
    $room_id = '';
    if ($gid > 0) {
        // Group room
        $chk = $pdo->prepare("SELECT 1 FROM chat_group_members WHERE group_id = ? AND account_id = ?");
        $chk->execute([$gid, $me]);
        if (!$chk->fetch()) { http_response_code(403); echo json_encode(['error'=>'not_member']); exit; }
        $room_id = voice_group_room($gid);
    } else {
        // DM room
        if ($peer === $me) { http_response_code(400); echo json_encode(['error'=>'self_dm']); exit; }
        $chk = $pdo->prepare("SELECT 1 FROM accounts WHERE id = ? AND claim_status = 'approved'");
        $chk->execute([$peer]);
        if (!$chk->fetch()) { http_response_code(404); echo json_encode(['error'=>'peer_not_found']); exit; }
        $room_id = voice_dm_room($me, $peer);
    }

    $st = $pdo->prepare("SELECT display_name FROM accounts WHERE id = ?");
    $st->execute([$me]);
    $display_name = $st->fetchColumn() ?: ('User ' . $me);

    $token = voice_issue_token($me, $room_id, (string)$display_name, 600);
    if (!$token) { http_response_code(500); echo json_encode(['error'=>'secret_missing','hint'=>'voice signaling not deployed yet']); exit; }

    echo json_encode([
        'ok'           => true,
        'token'        => $token,
        'peer_id'      => $me,
        'room_id'      => $room_id,
        'display_name' => $display_name,
        'ttl'          => 600,
        'ice_servers'  => voice_ice_servers(3600), // 1-hour TURN creds
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
