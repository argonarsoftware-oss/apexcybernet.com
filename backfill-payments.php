<?php
/**
 * Payment backfill — reconciles pending registrations against the listener API.
 *
 * Catches payments that came in after the user closed the ticket page,
 * since ticket.php's polling only runs while the page is open.
 *
 * Usage:
 *   GET /backfill-payments.php?token=BACKFILL_TOKEN
 *   GET /backfill-payments.php?token=BACKFILL_TOKEN&dry=1   (preview only)
 *
 * Run from cron every 1-5 minutes.
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/listener-api.php';

header('Content-Type: text/plain; charset=utf-8');

// Token gate — change this to a long random string
define('BACKFILL_TOKEN', 'apexcybernet-backfill-2026-secret-token');

if (($_GET['token'] ?? '') !== BACKFILL_TOKEN) {
    http_response_code(403);
    echo "Forbidden\n";
    exit;
}

$dry_run = !empty($_GET['dry']);

echo "=== Apex Cybernet Payment Backfill ===\n";
echo "Started: " . date('Y-m-d H:i:s') . "\n";
echo $dry_run ? "MODE: DRY RUN (no DB changes)\n\n" : "MODE: LIVE\n\n";

/**
 * Verify and approve a single registration.
 * Returns one of: 'approved', 'rejected_*', 'skipped', 'error'
 */
function backfillVerify($pdo, $ref, $type, $dry_run) {
    $result = listenerCheckPayment($ref);

    // Layer 1: API must say paid=true
    if (!$result || empty($result['paid'])) {
        return 'rejected_not_paid';
    }

    // Layer 2: must have a real sender
    $sender = trim((string)($result['sender'] ?? ''));
    $phone  = trim((string)($result['phone'] ?? ''));
    if ($sender === '' && $phone === '') {
        return 'rejected_no_sender';
    }

    // Layer 3: must have received amount
    $received_amount = $result['pay_amount'] ?? $result['amount'] ?? $result['received_amount'] ?? null;
    if ($received_amount === null || (float)$received_amount <= 0) {
        return 'rejected_no_amount';
    }

    // Layer 4: cross-verify with listenerGetOrder
    $existing = listenerGetOrder($ref);
    $order_status = $existing['order']['status'] ?? '';
    $order_paid = ($order_status === 'paid')
        || !empty($existing['order']['paid'])
        || !empty($existing['order']['paid_at']);
    if (!$order_paid) {
        return 'rejected_order_not_paid';
    }

    // Layer 5: amount must match expected (within 0.5 PHP)
    $expected_amount = $existing['order']['pay_amount'] ?? $existing['order']['amount'] ?? null;
    if ($expected_amount === null) {
        return 'rejected_no_expected';
    }
    $diff = abs((float)$received_amount - (float)$expected_amount);
    if ($diff >= 0.5) {
        return 'rejected_amount_mismatch';
    }

    // All layers passed — approve
    if ($dry_run) {
        return 'would_approve';
    }

    $proof = sprintf('GCASH-AUTO-BACKFILL | %s | %s | ₱%s | order=%s',
        $sender ?: '(no name)',
        $phone ?: '(no phone)',
        $received_amount,
        $ref
    );

    if ($type === 'solo') {
        $upd = $pdo->prepare("UPDATE solo_players SET status = 'approved', payment_proof = ? WHERE ref_code = ? AND status = 'pending'");
    } else {
        $upd = $pdo->prepare("UPDATE teams SET status = 'approved', payment_proof = ? WHERE ref_code = ? AND status = 'pending'");
    }
    $upd->execute([$proof, $ref]);

    return $upd->rowCount() > 0 ? 'approved' : 'skipped_not_pending';
}

// Process pending teams
$stats = ['approved' => 0, 'rejected' => 0, 'skipped' => 0];

echo "--- Pending TEAMS ---\n";
$teams = $pdo->query("SELECT ref_code, team_name FROM teams WHERE status = 'pending' AND ref_code IS NOT NULL AND ref_code != ''")->fetchAll();
echo "Found " . count($teams) . " pending team(s)\n\n";

foreach ($teams as $t) {
    $outcome = backfillVerify($pdo, $t['ref_code'], 'team', $dry_run);
    echo sprintf("[%s] %s — %s\n", $t['ref_code'], $t['team_name'], $outcome);
    if ($outcome === 'approved' || $outcome === 'would_approve') $stats['approved']++;
    elseif (strpos($outcome, 'rejected') === 0) $stats['rejected']++;
    else $stats['skipped']++;
}

echo "\n--- Pending SOLO PLAYERS ---\n";
$solos = $pdo->query("SELECT ref_code, player_name FROM solo_players WHERE status = 'pending' AND ref_code IS NOT NULL AND ref_code != ''")->fetchAll();
echo "Found " . count($solos) . " pending solo(s)\n\n";

foreach ($solos as $s) {
    $outcome = backfillVerify($pdo, $s['ref_code'], 'solo', $dry_run);
    echo sprintf("[%s] %s — %s\n", $s['ref_code'], $s['player_name'], $outcome);
    if ($outcome === 'approved' || $outcome === 'would_approve') $stats['approved']++;
    elseif (strpos($outcome, 'rejected') === 0) $stats['rejected']++;
    else $stats['skipped']++;
}

echo "\n=== Summary ===\n";
echo "Approved: {$stats['approved']}\n";
echo "Rejected: {$stats['rejected']}\n";
echo "Skipped:  {$stats['skipped']}\n";
echo "Finished: " . date('Y-m-d H:i:s') . "\n";
