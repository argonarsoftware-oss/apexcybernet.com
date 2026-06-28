<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/moderators.php';

header('Content-Type: application/json');

if (empty($_SESSION['account_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'not_logged_in']);
    exit;
}

$me     = (int)$_SESSION['account_id'];
$peer   = (int)($_REQUEST['peer_id'] ?? 0);
$gid    = (int)($_REQUEST['group_id'] ?? 0);
$action = $_SERVER['REQUEST_METHOD'] === 'POST' ? 'send' : 'list';

/**
 * Omniscient log: record every chat message into activity_logs as a 'chat' event.
 * Kept cheap on purpose — 1 INSERT, no JOINs/SELECTs. display_name is joined
 * at query time in the analytics UI via accounts, not duplicated here.
 * Request-scoped static cache for sid/ip/ua so we don't recompute per call.
 */
/** Handle image upload, return relative path or null */
function cs_handle_image_upload(int $me): ?string {
    if (empty($_FILES['image']['tmp_name'])) return null;
    $file = $_FILES['image'];
    if (!empty($file['error'])) return null;
    $allow = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($file['type'], $allow)) return null;
    if ($file['size'] > 5 * 1024 * 1024) return null;
    $ext   = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg');
    $fname = 'm_' . $me . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dir   = __DIR__ . '/../uploads/chat';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $dest  = $dir . '/' . $fname;
    if (!move_uploaded_file($file['tmp_name'], $dest)) return null;
    return 'uploads/chat/' . $fname;
}

function cs_log_message(PDO $pdo, int $me, ?int $peer_id, ?int $group_id, string $content): void {
    static $ctx = null;
    static $senderName = null;
    static $peerNames  = [];   // request-scoped cache: peer_id => name
    static $groupNames = [];   // request-scoped cache: group_id => name

    if ($ctx === null) {
        $sid = substr(preg_replace('/[^a-zA-Z0-9_\-]/', '', ($_COOKIE['arg_sid'] ?? '')), 0, 64);
        if (!$sid) $sid = 's' . bin2hex(random_bytes(6));
        $ip = null;
        foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $k) {
            if (!empty($_SERVER[$k])) { $ip = substr(trim(explode(',', $_SERVER[$k])[0]), 0, 45); break; }
        }
        $ctx = [
            'sid' => $sid,
            'ip'  => $ip,
            'ua'  => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512),
            'ref' => substr($_SERVER['HTTP_REFERER']    ?? '', 0, 512),
        ];
    }

    try {
        // Resolve sender display_name (once per request)
        if ($senderName === null) {
            $s = $pdo->prepare("SELECT display_name FROM accounts WHERE id = ?");
            $s->execute([$me]);
            $senderName = $s->fetchColumn() ?: null;
        }

        // Resolve peer/group name (cached per request)
        $recipient_name = null;
        if ($group_id) {
            if (!isset($groupNames[$group_id])) {
                $s = $pdo->prepare("SELECT name FROM chat_groups WHERE id = ?");
                $s->execute([$group_id]);
                $groupNames[$group_id] = $s->fetchColumn() ?: ('#' . $group_id);
            }
            $recipient_name = 'Group: ' . $groupNames[$group_id];
        } elseif ($peer_id) {
            if (!isset($peerNames[$peer_id])) {
                $s = $pdo->prepare("SELECT display_name FROM accounts WHERE id = ?");
                $s->execute([$peer_id]);
                $peerNames[$peer_id] = $s->fetchColumn() ?: ('#' . $peer_id);
            }
            $recipient_name = 'To: ' . $peerNames[$peer_id];
        }

        $el_id   = $group_id ? ('chat-group-' . $group_id) : ('chat-peer-' . $peer_id);
        $el_href = $group_id ? ('group:' . $group_id)      : ('peer:' . $peer_id);

        $pdo->prepare("INSERT INTO activity_logs
            (session_id, site, account_id, display_name, event_type,
             page_url, page_title, element_tag, element_text, element_href, element_id,
             referrer, ip, user_agent)
            VALUES (?, 'argonar', ?, ?, 'chat', ?, ?, 'chat', ?, ?, ?, ?, ?, ?)")
        ->execute([
            $ctx['sid'], $me, $senderName,
            $ctx['ref'], $recipient_name,                // page_title shows the recipient (historical snapshot)
            mb_substr($content, 0, 200), $el_href, $el_id,
            $ctx['ref'], $ctx['ip'], $ctx['ua']
        ]);
    } catch (Exception $e) { error_log('[chat:log] ' . $e->getMessage()); }
}

// Group membership check helper
$is_group_member = function(int $g) use ($pdo, $me): bool {
    if ($g <= 0) return false;
    $st = $pdo->prepare("SELECT 1 FROM chat_group_members WHERE group_id = ? AND account_id = ?");
    $st->execute([$g, $me]);
    return (bool)$st->fetch();
};
// Readable = actual member OR moderator. Moderators get read-only access to any group.
$can_read_group = function(int $g) use ($pdo, $me, $is_group_member): bool {
    if ($g <= 0) return false;
    if ($is_group_member($g)) return true;
    if (is_moderator($me)) {
        $st = $pdo->prepare("SELECT 1 FROM chat_groups WHERE id = ?");
        $st->execute([$g]);
        return (bool)$st->fetch();
    }
    return false;
};

// ── GROUP branch (separate flow) ──
if ($gid > 0) {
    // Write actions require actual membership; read allows moderators too
    $is_write = $_SERVER['REQUEST_METHOD'] === 'POST';
    if ($is_write) {
        if (!$is_group_member($gid)) { http_response_code(403); echo json_encode(['error'=>'not_member']); exit; }
    } else {
        if (!$can_read_group($gid)) { http_response_code(403); echo json_encode(['error'=>'not_member']); exit; }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['typing'])) {
        // Typing state for groups uses typing_to = -group_id (negative) so we don't collide with 1-on-1
        $typing = (int)$_POST['typing'];
        if ($typing) $pdo->prepare("UPDATE accounts SET typing_to = ?, typing_at = NOW() WHERE id = ?")->execute([-$gid, $me]);
        else         $pdo->prepare("UPDATE accounts SET typing_to = NULL WHERE id = ?")->execute([$me]);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['unsend_id'])) {
        $mid = (int)$_POST['unsend_id'];
        $chk = $pdo->prepare("SELECT id, image_url FROM chat_messages WHERE id = ? AND sender_id = ? AND group_id = ?");
        $chk->execute([$mid, $me, $gid]);
        $row = $chk->fetch();
        if (!$row) { http_response_code(403); echo json_encode(['error'=>'not_yours']); exit; }
        if (!empty($row['image_url'])) {
            $path = __DIR__ . '/../' . $row['image_url'];
            if (file_exists($path) && str_starts_with(realpath($path) ?: '', realpath(__DIR__ . '/../uploads/chat') ?: '')) @unlink($path);
        }
        $pdo->prepare("DELETE FROM chat_messages WHERE id = ?")->execute([$mid]);
        echo json_encode(['ok' => true, 'unsent_id' => $mid]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $content = mb_substr(trim($_POST['content'] ?? ''), 0, 2000);
        $nonce   = substr(preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)($_POST['nonce'] ?? '')), 0, 40);
        $image   = cs_handle_image_upload($me);
        if ($content === '' && !$image) { http_response_code(400); echo json_encode(['error'=>'empty_message']); exit; }

        if ($nonce && !$image) {
            if (!isset($_SESSION['cs_nonces']) || !is_array($_SESSION['cs_nonces'])) $_SESSION['cs_nonces'] = [];
            $now = time();
            foreach ($_SESSION['cs_nonces'] as $k => $t) if (($now - $t) > 60) unset($_SESSION['cs_nonces'][$k]);
            if (isset($_SESSION['cs_nonces'][$nonce])) {
                $prev = $pdo->prepare("SELECT id, sender_id, group_id, content, image_url, is_read, created_at FROM chat_messages
                    WHERE sender_id = ? AND group_id = ? AND content = ?
                      AND created_at >= DATE_SUB(NOW(), INTERVAL 60 SECOND)
                    ORDER BY id DESC LIMIT 1");
                $prev->execute([$me, $gid, $content]);
                if ($row = $prev->fetch()) { echo json_encode(['message' => $row, 'dedup'=>true]); exit; }
            }
            $_SESSION['cs_nonces'][$nonce] = $now;
        }

        if (!$image) {
            $dup = $pdo->prepare("SELECT id, sender_id, group_id, content, image_url, is_read, created_at FROM chat_messages
                WHERE sender_id = ? AND group_id = ? AND content = ?
                  AND created_at >= DATE_SUB(NOW(), INTERVAL 3 SECOND)
                ORDER BY id DESC LIMIT 1");
            $dup->execute([$me, $gid, $content]);
            if ($row = $dup->fetch()) { echo json_encode(['message' => $row, 'dedup' => true]); exit; }
        }

        $pdo->prepare("INSERT INTO chat_messages (sender_id, recipient_id, group_id, content, image_url) VALUES (?, NULL, ?, ?, ?)")
            ->execute([$me, $gid, $content, $image]);
        $new_id = (int)$pdo->lastInsertId();

        cs_log_message($pdo, $me, null, $gid, $content !== '' ? $content : '[image]');

        $row = $pdo->prepare("SELECT id, sender_id, group_id, content, image_url, is_read, created_at FROM chat_messages WHERE id = ?");
        $row->execute([$new_id]);
        echo json_encode(['message' => $row->fetch()]);
        exit;
    }

    // GET list for groups
    $since = (int)($_GET['since_id'] ?? 0);
    $sql = "SELECT m.id, m.sender_id, m.group_id, m.content, m.image_url, m.created_at,
                   a.display_name AS sender_name, a.profile_picture AS sender_pic, a.ref_type AS sender_type, a.is_verified AS sender_verified
            FROM chat_messages m
            JOIN accounts a ON a.id = m.sender_id
            WHERE m.group_id = ?";
    $params = [$gid];
    if ($since > 0) { $sql .= " AND m.id > ?"; $params[] = $since; }
    $sql .= " ORDER BY m.id ASC LIMIT 200";
    $st = $pdo->prepare($sql); $st->execute($params);
    $messages = $st->fetchAll();

    // Mark as read up to latest message (only for actual group members; moderators snooping don't count)
    if ($is_group_member($gid)) {
        $latest = (int)$pdo->query("SELECT IFNULL(MAX(id),0) FROM chat_messages WHERE group_id = {$gid}")->fetchColumn();
        $pdo->prepare("INSERT INTO chat_group_reads (group_id, account_id, last_read_message_id) VALUES (?,?,?)
            ON DUPLICATE KEY UPDATE last_read_message_id = GREATEST(last_read_message_id, VALUES(last_read_message_id))")
            ->execute([$gid, $me, $latest]);
    }

    $g = $pdo->prepare("SELECT id, name FROM chat_groups WHERE id = ?");
    $g->execute([$gid]);
    $group = $g->fetch();

    // Currently typing members (excluding me) — typing_to = -group_id
    $typ = $pdo->prepare("SELECT id, display_name FROM accounts
        WHERE id IN (SELECT account_id FROM chat_group_members WHERE group_id = ?)
          AND id != ? AND typing_to = ? AND typing_at >= DATE_SUB(NOW(), INTERVAL 6 SECOND)");
    $typ->execute([$gid, $me, -$gid]);
    $typing_now = $typ->fetchAll();

    echo json_encode([
        'messages'    => $messages,
        'group'       => $group,
        'peer_typing' => !empty($typing_now),
        'typers'      => $typing_now,
    ]);
    exit;
}

// ── Typing state update (lightweight POST) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['typing'])) {
    $typing = (int)$_POST['typing'];
    if ($typing && $peer > 0) {
        $pdo->prepare("UPDATE accounts SET typing_to = ?, typing_at = NOW() WHERE id = ?")->execute([$peer, $me]);
    } else {
        $pdo->prepare("UPDATE accounts SET typing_to = NULL WHERE id = ?")->execute([$me]);
    }
    echo json_encode(['ok' => true]);
    exit;
}

if ($peer <= 0 || $peer === $me) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_peer']);
    exit;
}

try {
    // Verify peer exists and is approved
    $ck = $pdo->prepare("SELECT id, display_name, profile_picture, ref_type, is_verified FROM accounts WHERE id = ? AND claim_status = 'approved'");
    $ck->execute([$peer]);
    $peer_row = $ck->fetch();
    if (!$peer_row) {
        http_response_code(404);
        echo json_encode(['error' => 'peer_not_found']);
        exit;
    }

    // ── Unsend (only own messages) ──
    if ($action === 'send' && !empty($_POST['unsend_id'])) {
        $mid = (int)$_POST['unsend_id'];
        $chk = $pdo->prepare("SELECT id, image_url FROM chat_messages WHERE id = ? AND sender_id = ? AND recipient_id = ?");
        $chk->execute([$mid, $me, $peer]);
        $row = $chk->fetch();
        if (!$row) { http_response_code(403); echo json_encode(['error' => 'not_yours']); exit; }
        if (!empty($row['image_url'])) {
            $path = __DIR__ . '/../' . $row['image_url'];
            if (file_exists($path) && str_starts_with(realpath($path) ?: '', realpath(__DIR__ . '/../uploads/chat') ?: '')) @unlink($path);
        }
        $pdo->prepare("DELETE FROM chat_messages WHERE id = ?")->execute([$mid]);
        echo json_encode(['ok' => true, 'unsent_id' => $mid]);
        exit;
    }

    if ($action === 'send') {
        $content = trim($_POST['content'] ?? '');
        $content = mb_substr($content, 0, 2000);
        $nonce   = preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)($_POST['nonce'] ?? ''));
        $nonce   = substr($nonce, 0, 40);
        $image   = cs_handle_image_upload($me);
        if ($content === '' && !$image) {
            http_response_code(400);
            echo json_encode(['error' => 'empty_message']);
            exit;
        }

        // Dedupe only applies when there's no image (image sends are always unique)
        if ($nonce && !$image) {
            if (!isset($_SESSION['cs_nonces']) || !is_array($_SESSION['cs_nonces'])) $_SESSION['cs_nonces'] = [];
            $now = time();
            foreach ($_SESSION['cs_nonces'] as $k => $t) if (($now - $t) > 60) unset($_SESSION['cs_nonces'][$k]);
            if (isset($_SESSION['cs_nonces'][$nonce])) {
                $prev = $pdo->prepare("SELECT id, sender_id, recipient_id, content, image_url, is_read, created_at
                    FROM chat_messages
                    WHERE sender_id = ? AND recipient_id = ? AND content = ?
                      AND created_at >= DATE_SUB(NOW(), INTERVAL 60 SECOND)
                    ORDER BY id DESC LIMIT 1");
                $prev->execute([$me, $peer, $content]);
                if ($row = $prev->fetch()) {
                    echo json_encode(['message' => $row, 'dedup' => true]);
                    exit;
                }
            }
            $_SESSION['cs_nonces'][$nonce] = $now;
        }

        if (!$image) {
            $dup_st = $pdo->prepare("SELECT id, sender_id, recipient_id, content, image_url, is_read, created_at
                FROM chat_messages
                WHERE sender_id = ? AND recipient_id = ? AND content = ?
                  AND created_at >= DATE_SUB(NOW(), INTERVAL 3 SECOND)
                ORDER BY id DESC LIMIT 1");
            $dup_st->execute([$me, $peer, $content]);
            if ($row = $dup_st->fetch()) {
                echo json_encode(['message' => $row, 'dedup' => true]);
                exit;
            }
        }

        $pdo->prepare("INSERT INTO chat_messages (sender_id, recipient_id, content, image_url) VALUES (?, ?, ?, ?)")
            ->execute([$me, $peer, $content, $image]);
        $new_id = (int)$pdo->lastInsertId();

        cs_log_message($pdo, $me, $peer, null, $content !== '' ? $content : '[image]');

        $row = $pdo->prepare("SELECT id, sender_id, recipient_id, content, image_url, is_read, created_at FROM chat_messages WHERE id = ?");
        $row->execute([$new_id]);
        echo json_encode(['message' => $row->fetch()]);
        exit;
    }

    // list
    $since = (int)($_GET['since_id'] ?? 0);
    $sql = "SELECT id, sender_id, recipient_id, content, image_url, is_read, created_at
            FROM chat_messages
            WHERE ((sender_id = ? AND recipient_id = ?) OR (sender_id = ? AND recipient_id = ?))";
    $params = [$me, $peer, $peer, $me];
    if ($since > 0) {
        $sql .= " AND id > ?";
        $params[] = $since;
    }
    $sql .= " ORDER BY id ASC LIMIT 200";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $messages = $stmt->fetchAll();

    // Mark messages from peer as read
    $pdo->prepare("UPDATE chat_messages SET is_read = 1 WHERE sender_id = ? AND recipient_id = ? AND is_read = 0")
        ->execute([$peer, $me]);

    // Track which of MY messages the peer has already read (for read-receipt display)
    $seen_st = $pdo->prepare("SELECT MAX(id) FROM chat_messages WHERE sender_id = ? AND recipient_id = ? AND is_read = 1");
    $seen_st->execute([$me, $peer]);
    $last_seen_by_peer = (int)$seen_st->fetchColumn();

    // Peer typing indicator: is the peer currently typing to me?
    $typ_st = $pdo->prepare("SELECT (typing_to = ? AND typing_at >= DATE_SUB(NOW(), INTERVAL 6 SECOND)) AS is_typing FROM accounts WHERE id = ?");
    $typ_st->execute([$me, $peer]);
    $peer_typing = (int)$typ_st->fetchColumn() === 1;

    echo json_encode([
        'messages'          => $messages,
        'peer'              => $peer_row,
        'last_seen_by_peer' => $last_seen_by_peer,
        'peer_typing'       => $peer_typing,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
