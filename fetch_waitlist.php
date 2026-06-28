<?php
require_once __DIR__ . '/includes/db.php';
header('Content-Type: application/json');

// Get ALL teams with ALL statuses — no filter
$teams = $pdo->query("SELECT id, game, team_name, status, payment_proof, created_at FROM teams ORDER BY game, status, created_at ASC")->fetchAll();

// Get ALL solo players with ALL statuses
$solo = $pdo->query("SELECT id, game, player_name, status, payment_proof, created_at FROM solo_players ORDER BY game, status, created_at ASC")->fetchAll();

// Show distinct statuses in use
$team_statuses = $pdo->query("SELECT DISTINCT status FROM teams")->fetchAll(PDO::FETCH_COLUMN);
$solo_statuses = $pdo->query("SELECT DISTINCT status FROM solo_players")->fetchAll(PDO::FETCH_COLUMN);

// Describe both tables to see full column list
$teams_desc = $pdo->query("DESCRIBE teams")->fetchAll();
$solo_desc  = $pdo->query("DESCRIBE solo_players")->fetchAll();

echo json_encode([
    'teams'          => $teams,
    'solo'           => $solo,
    'team_statuses'  => $team_statuses,
    'solo_statuses'  => $solo_statuses,
    'teams_columns'  => array_column($teams_desc, 'Field'),
    'solo_columns'   => array_column($solo_desc,  'Field'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
