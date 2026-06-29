<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/bracket_logic.php';
require_once __DIR__ . '/../includes/pusher.php';

header('Content-Type: application/json');

// Token auth
if (isset($_GET['token']) && $_GET['token'] === 'apexcybernet-admin-2026-token') {
    $_SESSION['admin_logged_in'] = true; $_SESSION['admin_username'] = 'admin'; $_SESSION['admin_role'] = 'admin';
}
// Check admin session
if (empty($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// ── Impersonate user (kirfenia only) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'impersonate_start') {
    if (($_SESSION['admin_username'] ?? '') !== 'kirfenia') {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit;
    }
    $target_id = (int)($_POST['id'] ?? 0);
    if ($target_id < 1) {
        echo json_encode(['success' => false, 'error' => 'Invalid account']); exit;
    }
    $acct = $pdo->prepare("SELECT id, display_name, email FROM accounts WHERE id = ?");
    $acct->execute([$target_id]);
    $target = $acct->fetch();
    if (!$target) {
        echo json_encode(['success' => false, 'error' => 'Account not found']); exit;
    }
    // Save admin session so we can restore it later
    $_SESSION['impersonating'] = true;
    $_SESSION['impersonate_admin'] = [
        'admin_logged_in' => $_SESSION['admin_logged_in'],
        'admin_username'  => $_SESSION['admin_username'],
        'admin_role'      => $_SESSION['admin_role'],
    ];
    $_SESSION['impersonate_display'] = $target['display_name'] ?: $target['email'];
    // Set user session to the target account
    $_SESSION['account_id'] = $target['id'];
    header('Location: ' . base_url('dashboard.php'));
    exit;
}

// ── Stop impersonation ──
if (($_GET['action'] ?? '') === 'impersonate_stop' && !empty($_SESSION['impersonating'])) {
    $admin = $_SESSION['impersonate_admin'] ?? [];
    unset($_SESSION['account_id'], $_SESSION['impersonating'], $_SESSION['impersonate_admin'], $_SESSION['impersonate_display']);
    if (!empty($admin)) {
        $_SESSION['admin_logged_in'] = $admin['admin_logged_in'];
        $_SESSION['admin_username']  = $admin['admin_username'];
        $_SESSION['admin_role']      = $admin['admin_role'];
    }
    header('Location: ' . base_url('admin/'));
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$type   = $_POST['type'] ?? '';
$id     = (int)($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';

// Handle dispute actions (form POST, not AJAX)
if ($type === 'dispute' && $id > 0) {
    if ($action === 'review_dispute') {
        $pdo->prepare("UPDATE disputes SET status = 'reviewed' WHERE id = ?")->execute([$id]);
    } elseif ($action === 'close_dispute') {
        $pdo->prepare("UPDATE disputes SET status = 'closed' WHERE id = ?")->execute([$id]);
    }
    header('Location: ' . base_url('admin/'));
    exit;
}

// Handle claim actions (form POST, not AJAX)
if ($type === 'claim' && $id > 0) {
    if ($action === 'approve_claim') {
        $pdo->prepare("UPDATE accounts SET claim_status = 'approved' WHERE id = ?")->execute([$id]);
    } elseif ($action === 'reject_claim') {
        $pdo->prepare("UPDATE accounts SET claim_status = 'rejected' WHERE id = ?")->execute([$id]);
    } elseif ($action === 'delete_claim') {
        $pdo->prepare("DELETE FROM accounts WHERE id = ?")->execute([$id]);
    }
    header('Location: ' . base_url('admin/'));
    exit;
}

// Handle title award (form POST)
if ($type === 'title' && $id > 0) {
    $title = trim($_POST['title'] ?? '');
    if ($action === 'add_title' && $title !== '') {
        $stmt = $pdo->prepare("SELECT titles FROM accounts WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row) {
            $titles = !empty($row['titles']) ? json_decode($row['titles'], true) : [];
            if (!is_array($titles)) $titles = [];
            if (!in_array($title, $titles)) {
                $titles[] = $title;
                $pdo->prepare("UPDATE accounts SET titles = ? WHERE id = ?")->execute([json_encode($titles), $id]);
            }
        }
    } elseif ($action === 'remove_title' && $title !== '') {
        $stmt = $pdo->prepare("SELECT titles FROM accounts WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row) {
            $titles = !empty($row['titles']) ? json_decode($row['titles'], true) : [];
            if (!is_array($titles)) $titles = [];
            $titles = array_values(array_filter($titles, fn($t) => $t !== $title));
            $pdo->prepare("UPDATE accounts SET titles = ? WHERE id = ?")->execute([json_encode($titles), $id]);
        }
    }
    header('Location: ' . base_url('admin/'));
    exit;
}

// Validate inputs
if (!in_array($type, ['team', 'solo'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid type']);
    exit;
}

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid ID']);
    exit;
}

if (!in_array($action, ['approve', 'reject', 'delete', 'rate', 'reserve', 'unreserve'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

$table = $type === 'team' ? 'teams' : 'solo_players';

if ($action === 'reserve' || $action === 'unreserve') {
    ensure_reserved_columns($pdo);
    try {
        $flag = $action === 'reserve' ? 1 : 0;
        $stmt = $pdo->prepare("UPDATE {$table} SET reserved = ? WHERE id = ?");
        $stmt->execute([$flag, $id]);
        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'error' => 'Record not found']);
            exit;
        }
        echo json_encode(['success' => true, 'action' => $action, 'reserved' => (bool)$flag]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
    exit;
}

try {
    if ($action === 'rate') {
        $rating = max(0, min(10, (int)($_POST['rating'] ?? 0)));
        $stmt = $pdo->prepare("UPDATE solo_players SET admin_rating = ? WHERE id = ?");
        $stmt->execute([$rating, $id]);
        echo json_encode(['success' => true, 'action' => 'rate', 'rating' => $rating]);
        exit;
    }

    if ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM {$table} WHERE id = ?");
        $stmt->execute([$id]);
    } else {
        $new_status = $action === 'approve' ? 'approved' : 'rejected';
        $stmt = $pdo->prepare("UPDATE {$table} SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $id]);
    }

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'error' => 'Record not found']);
        exit;
    }

    echo json_encode(['success' => true, 'action' => $action]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
