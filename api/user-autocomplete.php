<?php
/**
 * api/user-autocomplete.php
 *
 * GET ?q=<prefix>  (min 2 chars)
 *
 * Returns up to 8 approved accounts matching display_name — prefix
 * matches ranked above substring. Used by register.php member picker.
 */

require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Login-gated: this endpoint lists approved Apex Cybernet accounts. Guests
// should never enumerate users, so return an empty array for them.
if (empty($_SESSION['account_id'])) {
    echo json_encode([]);
    exit;
}

$q = trim((string)($_GET['q'] ?? ''));
if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$like_prefix = $q . '%';
$like_sub    = '%' . $q . '%';

try {
    $stmt = $pdo->prepare(
        "SELECT id, display_name, profile_picture, ref_type
         FROM accounts
         WHERE claim_status = 'approved'
           AND display_name LIKE ?
         ORDER BY
           CASE WHEN display_name LIKE ? THEN 0 ELSE 1 END,
           LENGTH(display_name),
           display_name
         LIMIT 8"
    );
    $stmt->execute([$like_sub, $like_prefix]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo json_encode([]);
}
