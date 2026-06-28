<?php
require_once __DIR__ . '/includes/db.php';
header('Content-Type: text/plain; charset=utf-8');

if (($_GET['token'] ?? '') !== 'argonar-migrate-2026') {
    http_response_code(403);
    echo "Forbidden\n";
    exit;
}

echo "── migrate_add_season_passes ──\n\n";

// Create season_passes table (idempotent)
$pdo->exec("CREATE TABLE IF NOT EXISTS season_passes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ref_code VARCHAR(32) UNIQUE,
    account_id INT NOT NULL,
    season_label VARCHAR(50) NOT NULL DEFAULT 'Season 1',
    price_paid DECIMAL(10,2) NOT NULL DEFAULT 999.00,
    payment_method VARCHAR(20) DEFAULT 'gcash_manual',
    status ENUM('pending','active','cancelled') NOT NULL DEFAULT 'pending',
    tournaments_max INT NOT NULL DEFAULT 4,
    tournaments_used INT NOT NULL DEFAULT 0,
    hc_bonus INT NOT NULL DEFAULT 2000,
    hc_credited TINYINT(1) NOT NULL DEFAULT 0,
    purchased_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    activated_at DATETIME DEFAULT NULL,
    expires_at DATETIME DEFAULT NULL,
    admin_note TEXT DEFAULT NULL,
    INDEX (account_id),
    INDEX (status),
    INDEX (ref_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

echo "[ok] season_passes table ready\n";

// Verify
$cnt = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'season_passes'")->fetchColumn();
echo "[check] table exists: " . ($cnt ? 'yes' : 'NO') . "\n";

$rows = (int)$pdo->query("SELECT COUNT(*) FROM season_passes")->fetchColumn();
echo "[check] current rows: $rows\n";

echo "\nDone. Delete this migration file after confirming.\n";
