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
    $COLLATE = 'utf8mb4_general_ci'; // single consistent collation across the whole DB

    // 1. Clean slate — drop and recreate with one consistent collation.
    //    Safe: no real data yet (only a seeded admin). Avoids collation-mix errors.
    $pdo->exec("DROP DATABASE IF EXISTS apexcybernet");
    $pdo->exec("CREATE DATABASE apexcybernet DEFAULT CHARACTER SET utf8mb4 COLLATE $COLLATE");
    $pdo->exec("USE apexcybernet");
    $log[] = "database `apexcybernet` recreated ($COLLATE)";

    // 2. Load setup.sql tables (strip its CREATE DATABASE / USE lines)
    $sql = @file_get_contents(__DIR__ . '/setup.sql');
    if ($sql === false) { http_response_code(500); exit("cannot read setup.sql\n"); }
    $sql = preg_replace('/CREATE\s+DATABASE[^;]*;/i', '', $sql);
    $sql = preg_replace('/USE\s+\w+\s*;/i', '', $sql);
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        $pdo->exec($stmt);
    }
    $log[] = "setup.sql tables loaded";

    // 3. Add columns the live code expects but setup.sql omits (reconstructed from code).
    //    Plain ALTERs (fresh tables); duplicate-column errors (1060) ignored for idempotency.
    $alters = [
        "ALTER TABLE teams ADD COLUMN members_ranks VARCHAR(512) NOT NULL DEFAULT ''",
        "ALTER TABLE teams ADD COLUMN contact_number VARCHAR(50) NOT NULL DEFAULT ''",
        "ALTER TABLE teams ADD COLUMN facebook_link VARCHAR(255) NOT NULL DEFAULT ''",
        "ALTER TABLE teams ADD COLUMN captain_account_id INT NULL DEFAULT NULL",
        "ALTER TABLE teams ADD COLUMN recruiting TINYINT(1) NOT NULL DEFAULT 0",
        "ALTER TABLE teams ADD COLUMN reserved TINYINT(1) NOT NULL DEFAULT 0",
        "ALTER TABLE solo_players ADD COLUMN contact_number VARCHAR(50) NOT NULL DEFAULT ''",
        "ALTER TABLE solo_players ADD COLUMN facebook_link VARCHAR(255) NOT NULL DEFAULT ''",
        "ALTER TABLE solo_players ADD COLUMN admin_rating TINYINT NOT NULL DEFAULT 5",
        "ALTER TABLE solo_players ADD COLUMN account_id INT NULL DEFAULT NULL",
        "ALTER TABLE solo_players ADD COLUMN reserved TINYINT(1) NOT NULL DEFAULT 0",
        "ALTER TABLE matches ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
    ];
    foreach ($alters as $a) {
        try { $pdo->exec($a); }
        catch (PDOException $e) { if ($e->errorInfo[1] != 1060) throw $e; }
    }
    $log[] = count($alters) . " missing columns added";

    // 4. accounts + cafe_comments (reconstructed from code usage, not argonar)
    $pdo->exec("CREATE TABLE accounts (
        id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
        email           VARCHAR(255) DEFAULT NULL,
        display_name    VARCHAR(255) NOT NULL,
        contact_number  VARCHAR(32)  DEFAULT NULL,
        password_hash   VARCHAR(255) NOT NULL,
        ref_code        VARCHAR(64)  DEFAULT NULL,
        ref_type        VARCHAR(16)  NOT NULL DEFAULT 'team',
        claim_status    VARCHAR(16)  NOT NULL DEFAULT 'pending',
        titles          TEXT         DEFAULT NULL,
        bio             VARCHAR(255) DEFAULT NULL,
        gcash_number    VARCHAR(32)  DEFAULT NULL,
        profile_picture VARCHAR(255) DEFAULT NULL,
        is_verified     TINYINT(1)   NOT NULL DEFAULT 0,
        typing_to       INT          DEFAULT NULL,
        typing_at       DATETIME     DEFAULT NULL,
        created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_accounts_display_name (display_name),
        UNIQUE KEY uq_accounts_email (email),
        UNIQUE KEY uq_accounts_ref_code (ref_code),
        KEY idx_accounts_claim_status (claim_status),
        KEY idx_accounts_ref (ref_type, ref_code),
        KEY idx_accounts_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=$COLLATE");

    $pdo->exec("CREATE TABLE cafe_comments (
        id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
        account_id   INT UNSIGNED NOT NULL,
        display_name VARCHAR(255) NOT NULL,
        message      TEXT         NOT NULL,
        created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_cafe_comments_account_id (account_id),
        KEY idx_cafe_comments_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=$COLLATE");
    $log[] = "accounts + cafe_comments created";

    // 5. announcements (dashboard) — referenced but never auto-created
    $pdo->exec("CREATE TABLE announcements (
        id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
        title      VARCHAR(255) NOT NULL DEFAULT '',
        content    TEXT         NOT NULL,
        type       VARCHAR(32)  NOT NULL DEFAULT 'info',
        created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_announcements_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=$COLLATE");
    $log[] = "announcements created";

    // 6. Seed one admin account so you can log in
    $adminPass = 'ApexAdmin#2026';
    $hash = password_hash($adminPass, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO accounts
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

if ($action === 'probe') {
    @ini_set('display_errors', '1');
    error_reporting(E_ALL);
    $target = __DIR__ . '/' . basename($_GET['file'] ?? 'index.php');
    register_shutdown_function(function () {
        $e = error_get_last();
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: text/plain');
        if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            echo "FATAL: " . $e['message'] . "\n  in " . $e['file'] . ":" . $e['line'] . "\n";
        }
    });
    ob_start();
    try {
        require $target;
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: text/plain');
        echo "RAN WITHOUT FATAL (target=" . basename($target) . ")\n";
    } catch (Throwable $t) {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: text/plain');
        echo "THROWABLE: " . $t->getMessage() . "\n  in " . $t->getFile() . ":" . $t->getLine() . "\n";
    }
    exit;
}

echo "unknown action\n";
