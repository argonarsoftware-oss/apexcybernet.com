<?php
/**
 * Voice call invite API.
 *
 * POST  ?action=invite   { peer_id }                  → create invite, returns id + room_id
 * POST  ?action=respond  { invite_id, decision: accept|decline|cancel }
 * GET   ?action=poll                                  → returns { incoming: [...], outgoing: [...] }
 *
 * Invites auto-expire after 35 seconds (clients show 30s ring + 5s grace).
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/voice_config.php';

header('Content-Type: application/json');

if (empty($_SESSION['account_id'])) { http_response_code(401); echo json_encode(['error'=>'not_logged_in']); exit; }
$me     = (int)$_SESSION['account_id'];
$action = $_REQUEST['action'] ?? 'poll';

try {
    // Auto-timeout stale pending invites (any caller/callee, idempotent garbage collection)
    $pdo->exec("UPDATE voice_invites SET status='timeout', responded_at=NOW()
        WHERE status='pending' AND created_at < DATE_SUB(NOW(), INTERVAL 35 SECOND)");

    if ($action === 'invite') {
        $peer = (int)($_POST['peer_id'] ?? 0);
        if ($peer <= 0 || $peer === $me) { http_response_code(400); echo json_encode(['error'=>'invalid_peer']); exit; }
        $chk = $pdo->prepare("SELECT 1 FROM accounts WHERE id = ? AND claim_status = 'approved'");
        $chk->execute([$peer]);
        if (!$chk->fetch()) { http_response_code(404); echo json_encode(['error'=>'peer_not_found']); exit; }
        // Cancel any stale outgoing invite I have to this peer
        $pdo->prepare("UPDATE voice_invites SET status='cancelled', responded_at=NOW()
            WHERE caller_id = ? AND callee_id = ? AND status='pending'")->execute([$me, $peer]);
        $room = voice_dm_room($me, $peer);
        $pdo->prepare("INSERT INTO voice_invites (caller_id, callee_id, room_id) VALUES (?, ?, ?)")->execute([$me, $peer, $room]);
        $iid = (int)$pdo->lastInsertId();
        echo json_encode(['ok' => true, 'invite_id' => $iid, 'room_id' => $room]);
        exit;
    }

    if ($action === 'respond') {
        $iid     = (int)($_POST['invite_id'] ?? 0);
        $dec     = $_POST['decision'] ?? '';
        if (!in_array($dec, ['accept','decline','cancel'], true)) { http_response_code(400); echo json_encode(['error'=>'invalid_decision']); exit; }
        $st = $pdo->prepare("SELECT * FROM voice_invites WHERE id = ?");
        $st->execute([$iid]);
        $inv = $st->fetch();
        if (!$inv) { http_response_code(404); echo json_encode(['error'=>'invite_not_found']); exit; }

        // accept/decline must come from callee; cancel must come from caller
        if ($dec === 'cancel') {
            if ((int)$inv['caller_id'] !== $me) { http_response_code(403); echo json_encode(['error'=>'not_caller']); exit; }
        } else {
            if ((int)$inv['callee_id'] !== $me) { http_response_code(403); echo json_encode(['error'=>'not_callee']); exit; }
        }
        if ($inv['status'] !== 'pending') { echo json_encode(['ok'=>true,'status'=>$inv['status']]); exit; }

        $newStatus = ['accept'=>'accepted','decline'=>'declined','cancel'=>'cancelled'][$dec];
        $pdo->prepare("UPDATE voice_invites SET status = ?, responded_at = NOW() WHERE id = ?")->execute([$newStatus, $iid]);
        echo json_encode(['ok' => true, 'status' => $newStatus, 'room_id' => $inv['room_id']]);
        exit;
    }

    // GET poll: incoming pending + my recently-responded outgoing
    $inc = $pdo->prepare("
        SELECT v.id, v.caller_id, v.callee_id, v.room_id, v.status, v.created_at,
               a.display_name AS caller_name, a.profile_picture AS caller_pic, a.ref_type AS caller_type, a.is_verified AS caller_verified
        FROM voice_invites v
        JOIN accounts a ON a.id = v.caller_id
        WHERE v.callee_id = ? AND v.status = 'pending'
        ORDER BY v.id DESC LIMIT 5");
    $inc->execute([$me]);
    $incoming = $inc->fetchAll();

    $out = $pdo->prepare("
        SELECT v.id, v.caller_id, v.callee_id, v.room_id, v.status, v.created_at, v.responded_at,
               a.display_name AS callee_name
        FROM voice_invites v
        JOIN accounts a ON a.id = v.callee_id
        WHERE v.caller_id = ?
          AND (v.status = 'pending'
               OR (v.responded_at IS NOT NULL AND v.responded_at >= DATE_SUB(NOW(), INTERVAL 8 SECOND)))
        ORDER BY v.id DESC LIMIT 5");
    $out->execute([$me]);
    $outgoing = $out->fetchAll();

    echo json_encode(['incoming' => $incoming, 'outgoing' => $outgoing]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
