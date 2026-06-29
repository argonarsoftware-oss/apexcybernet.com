<?php
/**
 * Migration: drop the HCoin subsystem schema.
 *
 * Removes the now-unused tables and columns left behind after the HCoin
 * virtual-currency teardown (wallet, marketplace, merchant/POS, match
 * predictions, season passes). The application no longer reads or writes
 * any of these.
 *
 * SAFETY:
 *  - Defaults to DRY-RUN (lists what it would drop, changes nothing).
 *  - Add &apply=1 to actually execute the DROPs.
 *  - Idempotent: only drops what still exists.
 *
 * Run locally:
 *   curl "http://localhost/apexcybernet.com/migrate_drop_hcoin.php?token=apexcybernet-migrate-2026"
 *   curl "http://localhost/apexcybernet.com/migrate_drop_hcoin.php?token=apexcybernet-migrate-2026&apply=1"
 * Then on production (once reachable), same with the prod host.
 * DELETE this file after running — never leave a migration deployed.
 */

require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/plain; charset=utf-8');

if (($_GET['token'] ?? '') !== 'apexcybernet-migrate-2026') {
    http_response_code(403);
    exit("Forbidden\n");
}

$apply = (($_GET['apply'] ?? '') === '1');
$db    = 'apexcybernet';

// Tables to drop entirely.
$tables = [
    'h_coin_transactions',
    'h_coin_orders',
    'h_coin_sell_orders',
    'qr_tokens_used',
    'marketplace_listings',
    'marketplace_reviews',
    'match_predictions',
    'season_passes',
];

// Columns to drop from surviving tables: [table => [columns...]].
$columns = [
    'accounts' => ['h_coins', 'is_merchant'],
];

echo $apply ? "=== APPLYING (dropping schema) ===\n\n" : "=== DRY RUN (nothing changed — add &apply=1 to execute) ===\n\n";

// ── Tables ──
echo "Tables:\n";
foreach ($tables as $t) {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?"
    );
    $stmt->execute([$db, $t]);
    $exists = (int) $stmt->fetchColumn() > 0;

    if (!$exists) {
        echo "  - $t: not present (skip)\n";
        continue;
    }
    if ($apply) {
        $pdo->exec("DROP TABLE IF EXISTS `$t`");
        echo "  - $t: DROPPED\n";
    } else {
        echo "  - $t: would DROP\n";
    }
}

// ── Columns ──
echo "\nColumns:\n";
foreach ($columns as $table => $cols) {
    foreach ($cols as $col) {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = ? AND table_name = ? AND column_name = ?"
        );
        $stmt->execute([$db, $table, $col]);
        $exists = (int) $stmt->fetchColumn() > 0;

        if (!$exists) {
            echo "  - $table.$col: not present (skip)\n";
            continue;
        }
        if ($apply) {
            $pdo->exec("ALTER TABLE `$table` DROP COLUMN `$col`");
            echo "  - $table.$col: DROPPED\n";
        } else {
            echo "  - $table.$col: would DROP\n";
        }
    }
}

echo "\nDone." . ($apply ? " Schema dropped. Delete this file now." : " Re-run with &apply=1 to execute.") . "\n";
