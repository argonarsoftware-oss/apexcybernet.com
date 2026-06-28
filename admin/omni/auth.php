<?php
/**
 * omni/auth.php
 * Shared auth + helper functions for all Omniscient activity pages.
 * Does NOT output anything.
 * Expects: $active_site already set by the calling page.
 */

function _load_env(string $path): array {
    $env = [];
    if (!file_exists($path)) return $env;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
        if (strpos(trim($ln), '#') === 0 || strpos($ln, '=') === false) continue;
        [$k, $v] = explode('=', $ln, 2);
        $env[trim($k)] = trim($v);
    }
    return $env;
}

$admin_users = [
    'kirfenia' => ['password' => 'Kirfenia123@', 'role' => 'admin'],
    'admin'    => ['password' => 'Kirfenia123@', 'role' => 'admin'],
    'raffy'    => ['password' => 'apexcybernet2026',  'role' => 'staff'],
];
$admin_token = 'apexcybernet-admin-2026-token';
if (isset($_GET['token']) && $_GET['token'] === $admin_token) {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_username'] = 'admin';
    $_SESSION['admin_role'] = 'admin';
}
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: ' . base_url('admin/'));
    exit;
}
if (($_SESSION['admin_username'] ?? '') !== 'kirfenia') {
    header('Location: ' . base_url('admin/'));
    exit;
}

// ── Helper functions ──

function pct_change($cur, $prev): ?float {
    if (!$prev) return null;
    return round((($cur - $prev) / $prev) * 100, 1);
}

function country_flag(string $code): string {
    if (strlen($code) !== 2) return '';
    $code = strtoupper($code);
    return mb_chr(0x1F1E0 + ord($code[0]) - ord('A')) . mb_chr(0x1F1E0 + ord($code[1]) - ord('A'));
}

function short_url(string $url): string {
    $p = parse_url($url);
    $path = $p['path'] ?? '/';
    if (isset($p['query'])) $path .= '?' . $p['query'];
    return $path ?: '/';
}

function trend_badge($cur, $prev): string {
    $pct = pct_change($cur, $prev);
    if ($pct === null) return '';
    $up  = $pct >= 0;
    $col = $up ? '#34d399' : '#f87171';
    $ico = $up ? '↑' : '↓';
    return "<span style='font-size:0.72rem;color:{$col};font-weight:700;margin-left:6px;'>{$ico} " . abs($pct) . "%</span>";
}
