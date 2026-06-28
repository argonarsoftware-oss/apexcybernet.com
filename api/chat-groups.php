<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (empty($_SESSION['account_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'not_logged_in']);
    exit;
}

$me     = (int)$_SESSION['account_id'];
$action = $_REQUEST['action'] ?? 'list';

function is_group_member(PDO $pdo, int $gid, int $uid): bool {
    $st = $pdo->prepare("SELECT 1 FROM chat_group_members WHERE group_id = ? AND account_id = ?");
    $st->execute([$gid, $uid]);
    return (bool)$st->fetch();
}

try {
    if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $name    = trim($_POST['name'] ?? '');
        $members = json_decode($_POST['members'] ?? '[]', true);
        if ($name === '' || mb_strlen($name) > 80) {
            http_response_code(400); echo json_encode(['error'=>'invalid_name']); exit;
        }
        if (!is_array($members)) $members = [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $members))));
        // Include the creator, exclude invalids
        $ids = array_values(array_unique(array_merge([$me], $ids)));
        if (count($ids) < 2) {
            http_response_code(400); echo json_encode(['error'=>'need_at_least_one_other_member']); exit;
        }
        // Validate all member ids exist + approved
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $st = $pdo->prepare("SELECT id FROM accounts WHERE id IN ($ph) AND claim_status = 'approved'");
        $st->execute($ids);
        $valid = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        if (count($valid) !== count($ids)) {
            http_response_code(400); echo json_encode(['error'=>'invalid_members']); exit;
        }

        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO chat_groups (name, created_by) VALUES (?, ?)")->execute([$name, $me]);
        $gid = (int)$pdo->lastInsertId();
        $ins = $pdo->prepare("INSERT INTO chat_group_members (group_id, account_id) VALUES (?, ?)");
        foreach ($ids as $uid) $ins->execute([$gid, $uid]);
        $pdo->commit();

        echo json_encode(['ok' => true, 'group_id' => $gid, 'name' => $name, 'member_ids' => $ids]);
        exit;
    }

    if ($action === 'members') {
        $gid = (int)($_GET['group_id'] ?? 0);
        if (!is_group_member($pdo, $gid, $me)) { http_response_code(403); echo json_encode(['error'=>'not_member']); exit; }
        $st = $pdo->prepare("
            SELECT a.id, a.display_name, a.profile_picture, a.ref_type, a.is_verified,
                   (SELECT MAX(created_at) FROM activity_logs WHERE account_id = a.id) AS last_active
            FROM chat_group_members m
            JOIN accounts a ON a.id = m.account_id
            WHERE m.group_id = ?
            ORDER BY a.display_name ASC
        ");
        $st->execute([$gid]);
        $rows = $st->fetchAll();
        $now = time();
        foreach ($rows as &$r) {
            $r['is_online'] = 0;
            if (!empty($r['last_active'])) {
                $t = strtotime($r['last_active']);
                if ($t && ($now - $t) <= 180) $r['is_online'] = 1;
            }
        }
        unset($r);
        $g = $pdo->prepare("SELECT id, name, created_by FROM chat_groups WHERE id = ?");
        $g->execute([$gid]);
        echo json_encode(['members' => $rows, 'group' => $g->fetch()]);
        exit;
    }

    if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $gid = (int)($_POST['group_id'] ?? 0);
        $uid = (int)($_POST['account_id'] ?? 0);
        if (!is_group_member($pdo, $gid, $me)) { http_response_code(403); echo json_encode(['error'=>'not_member']); exit; }
        $ck = $pdo->prepare("SELECT id FROM accounts WHERE id = ? AND claim_status = 'approved'");
        $ck->execute([$uid]);
        if (!$ck->fetch()) { http_response_code(400); echo json_encode(['error'=>'invalid_user']); exit; }
        $pdo->prepare("INSERT IGNORE INTO chat_group_members (group_id, account_id) VALUES (?, ?)")->execute([$gid, $uid]);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $gid = (int)($_POST['group_id'] ?? 0);
        $ck  = $pdo->prepare("SELECT created_by FROM chat_groups WHERE id = ?");
        $ck->execute([$gid]);
        $row = $ck->fetch();
        if (!$row) { http_response_code(404); echo json_encode(['error'=>'not_found']); exit; }
        if ((int)$row['created_by'] !== $me) {
            http_response_code(403); echo json_encode(['error'=>'not_creator']); exit;
        }
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM chat_messages       WHERE group_id = ?")->execute([$gid]);
        $pdo->prepare("DELETE FROM chat_group_reads    WHERE group_id = ?")->execute([$gid]);
        $pdo->prepare("DELETE FROM chat_group_members  WHERE group_id = ?")->execute([$gid]);
        $pdo->prepare("DELETE FROM chat_groups         WHERE id = ?")      ->execute([$gid]);
        $pdo->commit();
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'leave' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $gid = (int)($_POST['group_id'] ?? 0);
        if (!is_group_member($pdo, $gid, $me)) { http_response_code(403); echo json_encode(['error'=>'not_member']); exit; }
        $pdo->prepare("DELETE FROM chat_group_members WHERE group_id = ? AND account_id = ?")->execute([$gid, $me]);
        // If no members left, delete the group
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM chat_group_members WHERE group_id = ?");
        $cnt->execute([$gid]);
        if ((int)$cnt->fetchColumn() === 0) {
            $pdo->prepare("DELETE FROM chat_messages      WHERE group_id = ?")->execute([$gid]);
            $pdo->prepare("DELETE FROM chat_group_reads    WHERE group_id = ?")->execute([$gid]);
            $pdo->prepare("DELETE FROM chat_groups         WHERE id = ?")      ->execute([$gid]);
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    // Default: list my groups
    $st = $pdo->prepare("
        SELECT g.id, g.name, g.created_by, g.created_at,
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
        ORDER BY last_msg_id DESC, g.id DESC
    ");
    $st->execute([$me]);
    $groups = $st->fetchAll();
    foreach ($groups as &$g) {
        $g['unread'] = 0;
        if ($g['last_msg_id'] && $g['last_read_id'] < $g['last_msg_id']) {
            $uc = $pdo->prepare("SELECT COUNT(*) FROM chat_messages WHERE group_id = ? AND id > ? AND sender_id != ?");
            $uc->execute([$g['id'], (int)$g['last_read_id'], $me]);
            $g['unread'] = (int)$uc->fetchColumn();
        }
    }
    unset($g);
    echo json_encode(['groups' => $groups]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
