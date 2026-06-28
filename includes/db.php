<?php
session_start();

// QR pay HMAC secret — used to sign and verify wallet QR tokens
define('QR_HMAC_SECRET', 'argonar_qr_secret_2026');

$host = 'localhost';
$dbname = 'argonar_construction';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

function flash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Base URL: auto-detect local vs production
function base_url($path = '') {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
        return '/Argonar%20Construction/' . ltrim($path, '/');
    }
    return '/' . ltrim($path, '/');
}

/**
 * Build a BreadcrumbList JSON-LD <script> tag.
 * @param array $crumbs ordered array of ['name' => 'Home', 'url' => 'https://argonar.co/']
 * @return string ready-to-echo <script> tag (or empty string if invalid)
 */
function breadcrumb_jsonld(array $crumbs): string {
    if (empty($crumbs)) return '';
    $items = [];
    foreach (array_values($crumbs) as $i => $c) {
        if (empty($c['name']) || empty($c['url'])) continue;
        $items[] = [
            '@type'    => 'ListItem',
            'position' => $i + 1,
            'name'     => $c['name'],
            'item'     => $c['url'],
        ];
    }
    if (empty($items)) return '';
    $payload = [
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => $items,
    ];
    return '<script type="application/ld+json">' . json_encode($payload, JSON_UNESCAPED_SLASHES) . '</script>';
}

/**
 * Helper to build a sitewide canonical URL for a page path.
 */
function canonical_url(string $path = ''): string {
    return 'https://argonar.co/' . ltrim($path, '/');
}
