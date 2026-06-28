<?php
/**
 * JSON API — returns all Dota 2 participant data grouped by status for reel generation.
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../includes/db.php';

$MAX_SLOTS = 24;

// All teams ordered by registration date
$all_teams = $pdo->query("
    SELECT team_name, status, payment_proof, created_at
    FROM teams
    WHERE game = 'dota2' AND status != 'rejected'
    ORDER BY created_at ASC
")->fetchAll();

// Categorize teams
$confirmed = [];
$pending   = [];
$waitlist  = [];
$slot_count = 0;

foreach ($all_teams as $t) {
    if ($t['status'] === 'approved') {
        $confirmed[] = $t;
        $slot_count++;
    } elseif ($t['status'] === 'pending') {
        if ($slot_count < $MAX_SLOTS) {
            $pending[] = $t;
            $slot_count++;
        } else {
            $waitlist[] = $t;
        }
    }
}

// Solo players
$solos_paid = $pdo->query("
    SELECT player_name, rank_tier, preferred_role, status, created_at
    FROM solo_players
    WHERE game = 'dota2' AND status IN ('matched', 'approved')
    ORDER BY created_at ASC
")->fetchAll();

$solos_pending = $pdo->query("
    SELECT player_name, rank_tier, preferred_role, status, created_at
    FROM solo_players
    WHERE game = 'dota2' AND status = 'pending'
    ORDER BY created_at ASC
")->fetchAll();

$total_slots_used = count($confirmed) + count($pending);

echo json_encode([
    'confirmed'        => $confirmed,
    'pending'          => $pending,
    'waitlist'         => $waitlist,
    'solos_paid'       => $solos_paid,
    'solos_pending'    => $solos_pending,
    'total_slots'      => $MAX_SLOTS,
    'slots_used'       => $total_slots_used,
    'slots_remaining'  => max(0, $MAX_SLOTS - $total_slots_used),
    'tournament' => [
        'name'              => 'Apex Cybernet Dota 2 Tournament',
        'date'              => '2026-04-19',
        'registration_end'  => '2026-04-19',
        'call_time'         => '12:00 PM',
        'start_time'        => '1:00 PM',
        'venue'             => 'Hide Out Cybernet Cafe',
        'location'          => 'Brgy. Inayawan, Cebu City',
        'prize'             => "TBD Cash",
        'format'            => '5v5 Double Elimination',
        'entry_fee_team'    => "\u{20B1}250/team (50% OFF)",
        'entry_fee_solo'    => "\u{20B1}50/solo (50% OFF)",
        'url'               => 'https://apexcybernet.com',
    ],
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
