<?php
/**
 * admin/omni/pipelines/run.php — master pipeline runner.
 *
 * Auth: cron token (?k=argonar-omni-2026) OR logged-in admin (kirfenia).
 * Runs every sync-*.php in a safe order and prints a summary.
 *
 * Usage:
 *   curl -s 'https://argonar.co/admin/omni/pipelines/run.php?k=argonar-omni-2026'
 *   Local: http://localhost/Argonar%20Construction/admin/omni/pipelines/run.php?k=argonar-omni-2026
 */

header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/taxonomy.php';

$argonar_pdo = $pdo;

const OMNI_CRON_KEY = 'argonar-omni-2026';

$ok_token = isset($_GET['k']) && hash_equals(OMNI_CRON_KEY, $_GET['k']);
$ok_admin = !empty($_SESSION['admin_logged_in']) && ($_SESSION['admin_username'] ?? '') === 'kirfenia';
$ok_cli   = php_sapi_name() === 'cli';

if (!$ok_token && !$ok_admin && !$ok_cli) {
    http_response_code(403);
    echo "forbidden\n";
    exit;
}

$only = $_GET['only'] ?? null; // optional: ?only=sync-accounts

// Order matters: accounts before tx (for person links), business roots first
$order = [
    'sync-accounts.php',
    'sync-hc-transactions.php',
    'sync-tournaments.php',
    'sync-decisions.php',
    'sync-marketplace.php',
    'sync-events.php',
    'sync-alrisha.php',
    'sync-loans.php',
    'sync-bookings.php',
];

echo "── Omniscient pipeline run · " . date('Y-m-d H:i:s') . " ──\n\n";

$started = microtime(true);
$total_objs = 0; $total_links = 0; $errors = [];

foreach ($order as $script) {
    if ($only && !str_contains($script, $only)) continue;
    $path = __DIR__ . '/' . $script;
    if (!file_exists($path)) { echo "  skip $script (missing)\n"; continue; }

    $t0 = microtime(true);
    $res = include $path;
    $dt = round((microtime(true) - $t0) * 1000);

    if (!is_array($res)) { $res = ['pipeline'=>$script,'objs'=>0,'links'=>0,'err'=>'no return']; }
    $line = sprintf("  %-26s objs=%-5d links=%-5d %5dms",
        $res['pipeline'] ?? $script, $res['objs'] ?? 0, $res['links'] ?? 0, $dt);
    if (!empty($res['err'])) { $line .= "  ERR=" . $res['err']; $errors[] = $res; }
    echo $line . "\n";

    $total_objs  += (int)($res['objs']  ?? 0);
    $total_links += (int)($res['links'] ?? 0);
}

$elapsed = round((microtime(true) - $started) * 1000);

echo "\n── Summary ──\n";
echo "  objects upserted: $total_objs\n";
echo "  links upserted:   $total_links\n";
echo "  elapsed:          {$elapsed}ms\n";
echo "  errors:           " . count($errors) . "\n";

// Graph totals
try {
    $by_type = $argonar_pdo->query("SELECT type, COUNT(*) c FROM omni_objects GROUP BY type ORDER BY c DESC")->fetchAll(PDO::FETCH_KEY_PAIR);
    echo "\n── Ontology totals ──\n";
    foreach ($by_type as $t => $c) echo "  $t: $c\n";
    $link_total = (int)$argonar_pdo->query("SELECT COUNT(*) FROM omni_links")->fetchColumn();
    echo "  (links: $link_total)\n";
} catch (Exception $e) {}
