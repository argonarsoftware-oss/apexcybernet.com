<?php
// Omniscient entry point — redirect to Apex Cybernet page (default)
require_once __DIR__ . '/../includes/db.php';
$site = $_GET['site'] ?? 'apexcybernet';
$map  = [
    'apexcybernet' => 'activity-apexcybernet.php',
    'loan'    => 'activity-loan.php',
    'alrisha' => 'activity-alrisha.php',
    'bizops'  => 'activity-bizops.php',
    'brain'   => 'activity-brain.php',
    'health'  => 'activity-health.php',
];
$target = $map[$site] ?? 'activity-apexcybernet.php';
// Preserve any extra query params (dr, ev, etc.) except 'site'
$params = $_GET;
unset($params['site']);
$qs = $params ? '?' . http_build_query($params) : '';
header('Location: ' . base_url('admin/' . $target) . $qs);
exit;
