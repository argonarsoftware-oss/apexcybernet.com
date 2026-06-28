<?php
/**
 * One-shot migration: create qr_tokens_used table for QR single-use dedup.
 * Visit this page once in the browser, then delete the file in a follow-up commit.
 */
require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS qr_tokens_used (
        user_id INT NOT NULL,
        ts INT NOT NULL,
        used_at INT NOT NULL,
        PRIMARY KEY (user_id, ts),
        KEY idx_used_at (used_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "OK: qr_tokens_used table created (or already existed).\n";

    $rows = $pdo->query("SHOW TABLES LIKE 'qr_tokens_used'")->fetchAll();
    echo "Verified: " . count($rows) . " matching table(s).\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "FAILED: " . $e->getMessage() . "\n";
}
