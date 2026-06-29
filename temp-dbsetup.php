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

echo "unknown action\n";
