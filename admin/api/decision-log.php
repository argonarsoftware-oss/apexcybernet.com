<?php
require_once __DIR__ . '/../../includes/db.php';
if (($_GET['k'] ?? '') !== 'apexcybernet2026') { http_response_code(403); exit; }
header('Content-Type: application/json');

// POST:
//   - if `id` present → update impact_text on that note
//   - if no `id`       → create a new note from title + context_text (+ optional impact_text)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $id   = (int)($body['id'] ?? 0);
    try {
        if ($id > 0) {
            $text = (string)($body['impact_text'] ?? '');
            $pdo->prepare("UPDATE decision_log SET impact_text = ?, updated_at = NOW() WHERE id = ?")->execute([$text, $id]);
            echo json_encode(['ok'=>true, 'id'=>$id, 'action'=>'updated']);
        } else {
            $title   = trim((string)($body['title'] ?? ''));
            $ctx     = (string)($body['context_text'] ?? '');
            $impact  = (string)($body['impact_text'] ?? '');
            $biz     = substr(trim((string)($body['business'] ?? 'general')), 0, 60);
            $tags    = substr(trim((string)($body['tags'] ?? '')), 0, 255);
            if ($title === '' && $ctx === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'title or context_text required']); exit; }
            if ($title === '') $title = mb_substr(preg_replace('/\s+/', ' ', $ctx), 0, 80);
            $pdo->prepare("INSERT INTO decision_log (decided_at, title, context_text, impact_text, tags, business, outcome) VALUES (CURDATE(), ?, ?, ?, ?, ?, 'pending')")
                ->execute([$title, $ctx, $impact, $tags, $biz]);
            $newId = (int)$pdo->lastInsertId();
            echo json_encode(['ok'=>true, 'id'=>$newId, 'action'=>'created']);
        }
    } catch (Exception $e) {
        http_response_code(500); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

// GET: list all rows
try {
    $rows = $pdo->query("SELECT * FROM decision_log ORDER BY decided_at DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows);
} catch (Exception $e) { echo json_encode([]); }
