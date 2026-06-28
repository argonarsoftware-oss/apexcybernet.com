<?php
/**
 * POST /api/alrisha-sync.php
 * Receives an ERP snapshot pushed from local Alrisha instance.
 * Stores in alrisha_snapshots table for Omniscient dashboard to read.
 */
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Sync-Token');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

define('ALRISHA_SYNC_TOKEN', 'alrisha-push-apexcybernet-2026');

// ── Auth ──
$token = $_SERVER['HTTP_X_SYNC_TOKEN'] ?? $_GET['token'] ?? '';
if ($token !== ALRISHA_SYNC_TOKEN) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST only']);
    exit;
}

// ── Auto-create table ──
$pdo->exec("CREATE TABLE IF NOT EXISTS alrisha_snapshots (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    company_id  INT DEFAULT 0,
    snapshot    LONGTEXT NOT NULL,
    pushed_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (company_id),
    INDEX (pushed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── Read body ──
$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

$company_id = (int)($body['company_id'] ?? 0);
$snapshot   = json_encode($body);

// ── Store (keep last 48 hours, delete older) ──
try {
    $pdo->prepare("INSERT INTO alrisha_snapshots (company_id, snapshot, pushed_at) VALUES (?, ?, NOW())")
        ->execute([$company_id, $snapshot]);
    $pdo->prepare("DELETE FROM alrisha_snapshots WHERE pushed_at < DATE_SUB(NOW(), INTERVAL 48 HOUR)")
        ->execute();
    echo json_encode(['ok' => true, 'stored_at' => date('Y-m-d H:i:s')]);
} catch (Exception $e) {
    error_log('[alrisha-sync] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'DB error']);
}
