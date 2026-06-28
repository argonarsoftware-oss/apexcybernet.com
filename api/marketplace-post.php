<?php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

if (empty($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Only admins can create listings']);
    exit;
}

// Accepts multipart/form-data (for image upload) or JSON
$is_multipart = strpos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart') !== false;

if ($is_multipart) {
    $title       = trim($_POST['title']       ?? '');
    $description = trim($_POST['description'] ?? '');
    $price       = (int)($_POST['price']      ?? 0);
    $category    = trim($_POST['category']    ?? 'general');
} else {
    $body        = json_decode(file_get_contents('php://input'), true);
    $title       = trim($body['title']       ?? '');
    $description = trim($body['description'] ?? '');
    $price       = (int)($body['price']      ?? 0);
    $category    = trim($body['category']    ?? 'general');
}

$allowed_cats = ['general','gaming','services','digital','merch','other'];

if (!$title)                              { echo json_encode(['error' => 'Title is required']); exit; }
if (strlen($title) > 120)                { echo json_encode(['error' => 'Title too long (max 120 chars)']); exit; }
if ($price < 1)                          { echo json_encode(['error' => 'Price must be at least 1 HC']); exit; }
if ($price > 1000000)                    { echo json_encode(['error' => 'Price too high']); exit; }
if (!in_array($category, $allowed_cats)) { $category = 'general'; }

// Use account_id if logged in as user, otherwise look up admin's account
$uid = (int)($_SESSION['account_id'] ?? 0);
if (!$uid && !empty($_SESSION['admin_username'])) {
    $lookup = $pdo->prepare("SELECT id FROM accounts WHERE display_name = ? LIMIT 1");
    $lookup->execute([$_SESSION['admin_username']]);
    $uid = (int)$lookup->fetchColumn();
}

// Handle image upload
$image_path = null;
if (!empty($_FILES['image']['tmp_name'])) {
    $file  = $_FILES['image'];
    $allow = ['image/jpeg','image/png','image/webp','image/gif'];
    if (!in_array($file['type'], $allow)) {
        echo json_encode(['error' => 'Image must be JPG, PNG, WebP, or GIF']); exit;
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['error' => 'Image must be under 5 MB']); exit;
    }
    $ext   = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg');
    $fname = 'item_' . $uid . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest  = __DIR__ . '/../uploads/marketplace/' . $fname;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        echo json_encode(['error' => 'Failed to save image']); exit;
    }
    $image_path = 'uploads/marketplace/' . $fname;
}

$stmt = $pdo->prepare("
    INSERT INTO marketplace_listings (seller_id, title, description, image_path, price, category)
    VALUES (?, ?, ?, ?, ?, ?)
");
$stmt->execute([$uid, $title, $description, $image_path, $price, $category]);
$new_id = (int)$pdo->lastInsertId();

// Clients discover this via api/marketplace-feed.php polling (every 15s).
echo json_encode(['success' => true, 'id' => $new_id]);
