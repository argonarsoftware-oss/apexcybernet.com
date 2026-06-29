<?php
/**
 * TEMP DB inspection / setup — guarded by key. DELETE AFTER USE.
 * Connects to MySQL WITHOUT selecting a database (apexcybernet doesn't exist yet).
 */
header('Content-Type: text/plain');
$KEY = 'axc-db-9f3c2a7e51b4';
if (($_GET['k'] ?? '') !== $KEY) { http_response_code(403); exit("forbidden\n"); }

try {
    $pdo = new PDO("mysql:host=localhost;charset=utf8mb4", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    exit("CONNECT FAILED: " . $e->getMessage() . "\n");
}

$action = $_GET['action'] ?? 'inspect';

if ($action === 'inspect') {
    echo "== MYSQL CONNECT OK (root, no password) ==\n\n";

    echo "== DATABASES ==\n";
    foreach ($pdo->query("SHOW DATABASES") as $r) echo "  " . $r['Database'] . "\n";

    echo "\n== ARGONAR db.php config ==\n";
    $cfg = @file_get_contents('/var/www/argonar/includes/db.php');
    if ($cfg === false) {
        echo "  (cannot read /var/www/argonar/includes/db.php)\n";
    } else {
        foreach (preg_split('/\r?\n/', $cfg) as $line) {
            if (preg_match('/\$(host|dbname|user|pass)\s*=/', $line)) echo "  " . trim($line) . "\n";
        }
    }

    echo "\n== where do accounts/cafe_comments live? ==\n";
    $stmt = $pdo->query("SELECT table_schema, table_name FROM information_schema.tables WHERE table_name IN ('accounts','cafe_comments') ORDER BY table_schema, table_name");
    $any = false;
    foreach ($stmt as $r) { echo "  " . $r['table_schema'] . "." . $r['table_name'] . "\n"; $any = true; }
    if (!$any) echo "  (not found in any database)\n";

    exit;
}

if ($action === 'create') {
    $log = [];

    // 1. Create the database (clean slate, nothing from argonar)
    $pdo->exec("CREATE DATABASE IF NOT EXISTS apexcybernet DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE apexcybernet");
    $log[] = "database `apexcybernet` ready";

    // 2. Load setup.sql tables (strip its CREATE DATABASE / USE lines)
    $sql = @file_get_contents(__DIR__ . '/setup.sql');
    if ($sql === false) { http_response_code(500); exit("cannot read setup.sql\n"); }
    $sql = preg_replace('/CREATE\s+DATABASE[^;]*;/i', '', $sql);
    $sql = preg_replace('/USE\s+\w+\s*;/i', '', $sql);
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        $pdo->exec($stmt);
    }
    $log[] = "setup.sql tables loaded";

    // 3. accounts + cafe_comments (reconstructed from code usage, not argonar)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `accounts` (
        `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `email`           VARCHAR(255) DEFAULT NULL,
        `display_name`    VARCHAR(255) NOT NULL,
        `contact_number`  VARCHAR(32)  DEFAULT NULL,
        `password_hash`   VARCHAR(255) NOT NULL,
        `ref_code`        VARCHAR(64)  DEFAULT NULL,
        `ref_type`        VARCHAR(16)  NOT NULL DEFAULT 'team',
        `claim_status`    VARCHAR(16)  NOT NULL DEFAULT 'pending',
        `titles`          TEXT         DEFAULT NULL,
        `bio`             VARCHAR(255) DEFAULT NULL,
        `gcash_number`    VARCHAR(32)  DEFAULT NULL,
        `profile_picture` VARCHAR(255) DEFAULT NULL,
        `is_verified`     TINYINT(1)   NOT NULL DEFAULT 0,
        `typing_to`       INT          DEFAULT NULL,
        `typing_at`       DATETIME     DEFAULT NULL,
        `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_accounts_display_name` (`display_name`),
        UNIQUE KEY `uq_accounts_email` (`email`),
        UNIQUE KEY `uq_accounts_ref_code` (`ref_code`),
        KEY `idx_accounts_claim_status` (`claim_status`),
        KEY `idx_accounts_ref` (`ref_type`, `ref_code`),
        KEY `idx_accounts_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `cafe_comments` (
        `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `account_id`   INT UNSIGNED NOT NULL,
        `display_name` VARCHAR(255) NOT NULL,
        `message`      TEXT         NOT NULL,
        `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_cafe_comments_account_id` (`account_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $log[] = "accounts + cafe_comments created";

    // 4. Seed one admin account so you can log in
    $adminPass = 'ApexAdmin#2026';
    $hash = password_hash($adminPass, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT IGNORE INTO accounts
        (email, display_name, password_hash, ref_code, ref_type, claim_status, is_verified)
        VALUES (?, 'admin', ?, 'ADMIN0001', 'team', 'approved', 1)");
    $stmt->execute(['admin@apexcybernet.com', $hash]);
    $log[] = "admin account seeded (display_name=admin / password=" . $adminPass . ")";

    echo "== CREATE DONE ==\n";
    foreach ($log as $l) echo "  - " . $l . "\n";
    echo "\n== TABLES IN apexcybernet ==\n";
    foreach ($pdo->query("SHOW TABLES") as $r) echo "  " . array_values($r)[0] . "\n";
    exit;
}

if ($action === 'log') {
    $f = '/var/log/apache2/apexcybernet.com-error.log';
    if (!is_readable($f)) { exit("cannot read $f\n"); }
    $lines = @file($f, FILE_IGNORE_NEW_LINES);
    $tail = array_slice($lines, -40);
    echo "== last 40 lines of $f ==\n";
    echo implode("\n", $tail) . "\n";
    exit;
}

echo "unknown action\n";
