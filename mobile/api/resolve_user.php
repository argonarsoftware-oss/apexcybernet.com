<?php
require_once __DIR__ . '/../../includes/db.php';
header('Content-Type: application/json');

if (empty($_SESSION['account_id'])) { http_response_code(401); echo json_encode(['error'=>'Not logged in']); exit; }
$uid = (int)($_GET['uid'] ?? 0);
if (!$uid) { echo json_encode(['error'=>'Missing uid']); exit; }

$stmt = $pdo->prepare("SELECT display_name FROM accounts WHERE id = ?");
$stmt->execute([$uid]);
$name = $stmt->fetchColumn();
echo json_encode($name ? ['name' => $name] : ['error' => 'Not found']);
