<?php
require_once __DIR__ . '/../includes/db.php';

// Token auth
if (isset($_GET['token']) && $_GET['token'] === 'apexcybernet-admin-2026-token') {
    $_SESSION['admin_logged_in'] = true; $_SESSION['admin_username'] = 'admin'; $_SESSION['admin_role'] = 'admin';
}
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: ' . base_url('admin/'));
    exit;
}

$file = $_GET['file'] ?? '';

if ($file === '') {
    http_response_code(400);
    echo 'No file specified.';
    exit;
}

// Prevent directory traversal
$file = str_replace(['..', "\0"], '', $file);

$filepath = __DIR__ . '/../' . $file;

if (!file_exists($filepath)) {
    http_response_code(404);
    echo 'File not found.';
    exit;
}

// Determine MIME type
$ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
$mime_types = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'webp' => 'image/webp',
    'pdf'  => 'application/pdf',
];

$mime = $mime_types[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($filepath));

// For images and PDFs, display inline
header('Content-Disposition: inline; filename="' . basename($filepath) . '"');

readfile($filepath);
exit;
