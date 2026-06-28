<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/moderators.php';

header('Content-Type: application/json');

$me     = (int)($_SESSION['account_id'] ?? 0);
$search = trim($_GET['q'] ?? '');

try {
    if ($me > 0) {
        // Logged-in: include per-user last message + unread, sort by recent convo
        $sql = "
            SELECT a.id, a.display_name, a.profile_picture, a.ref_type, a.is_verified, a.ref_code,
                   COALESCE(t.team_name, sp.player_name) AS flair_team,
                   COALESCE(t.game, sp.game)             AS flair_game,
                   (SELECT MAX(created_at) FROM activity_logs WHERE account_id = a.id) AS last_active,
                   (SELECT content    FROM chat_messages WHERE (sender_id = ? AND recipient_id = a.id) OR (sender_id = a.id AND recipient_id = ?) ORDER BY id DESC LIMIT 1) AS last_message,
                   (SELECT image_url  FROM chat_messages WHERE (sender_id = ? AND recipient_id = a.id) OR (sender_id = a.id AND recipient_id = ?) ORDER BY id DESC LIMIT 1) AS last_image,
                   (SELECT created_at FROM chat_messages WHERE (sender_id = ? AND recipient_id = a.id) OR (sender_id = a.id AND recipient_id = ?) ORDER BY id DESC LIMIT 1) AS last_time,
                   (SELECT COUNT(*)   FROM chat_messages WHERE sender_id = a.id AND recipient_id = ? AND is_read = 0) AS unread
            FROM accounts a
            LEFT JOIN teams        t  ON a.ref_type = 'team' AND t.ref_code = a.ref_code
            LEFT JOIN solo_players sp ON a.ref_type = 'solo' AND sp.ref_code = a.ref_code
            WHERE a.claim_status = 'approved' AND a.id != ?
        ";
        $params = [$me, $me, $me, $me, $me, $me, $me, $me];
        if ($search !== '') {
            $sql .= " AND a.display_name LIKE ?";
            $params[] = '%' . $search . '%';
        }
        $sql .= " ORDER BY
            CASE WHEN a.display_name IN ('Apex Cybernet','HIDE OUT') THEN 0 ELSE 1 END ASC,
            CASE a.display_name WHEN 'Apex Cybernet' THEN 0 WHEN 'HIDE OUT' THEN 1 ELSE 2 END ASC,
            CASE WHEN (SELECT MAX(id) FROM chat_messages WHERE (sender_id = ? AND recipient_id = a.id) OR (sender_id = a.id AND recipient_id = ?)) IS NULL THEN 1 ELSE 0 END ASC,
            (SELECT MAX(id) FROM chat_messages WHERE (sender_id = ? AND recipient_id = a.id) OR (sender_id = a.id AND recipient_id = ?)) DESC,
            a.is_verified DESC,
            a.display_name ASC
            LIMIT 100";
        array_push($params, $me, $me, $me, $me);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll();

        $total_unread = (int)$pdo->query("SELECT COUNT(*) FROM chat_messages WHERE recipient_id = {$me} AND is_read = 0")->fetchColumn();
    } else {
        // Guest: directory-only, no messaging data
        $sql = "
            SELECT a.id, a.display_name, a.profile_picture, a.ref_type, a.is_verified, a.ref_code,
                   COALESCE(t.team_name, sp.player_name) AS flair_team,
                   COALESCE(t.game, sp.game)             AS flair_game,
                   (SELECT MAX(created_at) FROM activity_logs WHERE account_id = a.id) AS last_active,
                   NULL AS last_message, NULL AS last_time, 0 AS unread
            FROM accounts a
            LEFT JOIN teams        t  ON a.ref_type = 'team' AND t.ref_code = a.ref_code
            LEFT JOIN solo_players sp ON a.ref_type = 'solo' AND sp.ref_code = a.ref_code
            WHERE a.claim_status = 'approved'
        ";
        $params = [];
        if ($search !== '') {
            $sql .= " AND a.display_name LIKE ?";
            $params[] = '%' . $search . '%';
        }
        $sql .= " ORDER BY
            CASE WHEN a.display_name IN ('Apex Cybernet','HIDE OUT') THEN 0 ELSE 1 END ASC,
            CASE a.display_name WHEN 'Apex Cybernet' THEN 0 WHEN 'HIDE OUT' THEN 1 ELSE 2 END ASC,
            a.is_verified DESC, a.display_name ASC LIMIT 100";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll();
        $total_unread = 0;
    }

    // Mark online if last activity within 3 minutes
    $now = time();
    foreach ($users as &$u) {
        $u['is_online'] = 0;
        if (!empty($u['last_active'])) {
            $ts = strtotime($u['last_active']);
            if ($ts && ($now - $ts) <= 180) $u['is_online'] = 1;
        }
    }
    unset($u);

    // ── Groups: users see only the groups they belong to ──
    $groups = [];
    if ($me > 0) {
        $gq = $pdo->prepare("
            SELECT g.id, g.name, g.created_by,
                   (SELECT COUNT(*) FROM chat_group_members mm WHERE mm.group_id = g.id) AS member_count,
                   (SELECT content    FROM chat_messages WHERE group_id = g.id ORDER BY id DESC LIMIT 1) AS last_message,
                   (SELECT created_at FROM chat_messages WHERE group_id = g.id ORDER BY id DESC LIMIT 1) AS last_time,
                   (SELECT sender_id  FROM chat_messages WHERE group_id = g.id ORDER BY id DESC LIMIT 1) AS last_sender,
                   (SELECT MAX(id)    FROM chat_messages WHERE group_id = g.id) AS last_msg_id,
                   COALESCE(r.last_read_message_id, 0) AS last_read_id
            FROM chat_group_members m
            JOIN chat_groups g ON g.id = m.group_id
            LEFT JOIN chat_group_reads r ON r.group_id = g.id AND r.account_id = m.account_id
            WHERE m.account_id = ?
        ");
        $gq->execute([$me]);
        foreach ($gq->fetchAll() as $g) {
            $g['admin_view'] = 0;
            $g['unread'] = 0;
            if ($g['last_msg_id'] && (int)$g['last_read_id'] < (int)$g['last_msg_id']) {
                $uc = $pdo->prepare("SELECT COUNT(*) FROM chat_messages WHERE group_id = ? AND id > ? AND sender_id != ?");
                $uc->execute([$g['id'], (int)$g['last_read_id'], $me]);
                $g['unread'] = (int)$uc->fetchColumn();
            }
            $groups[] = $g;
            $total_unread += $g['unread'];
        }
    }

    echo json_encode([
        'users'        => $users,
        'groups'       => $groups,
        'total_unread' => $total_unread,
        'guest'        => $me === 0,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
