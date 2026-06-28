<?php
/**
 * sync-accounts.php — accounts → Person objects.
 *
 * Reads: argonar_pdo.accounts
 * Writes: omni_objects (type=Person, business=argonar)
 *         + BELONGS_TO link Person → Business(argonar)
 */

require_once __DIR__ . '/taxonomy.php';
if (!isset($argonar_pdo)) { require_once __DIR__ . '/../../../includes/db.php'; $argonar_pdo = $pdo; }

$run_id = omni_start_run($argonar_pdo, 'sync-accounts');
$objs = 0; $links = 0; $err = null;

try {
    // Ensure Business(argonar) exists first
    $biz_argonar_id = omni_upsert_object($argonar_pdo, [
        'ref'      => 'global:business:argonar',
        'type'     => 'Business',
        'business' => 'argonar',
        'label'    => 'Argonar',
        'props'    => ['domain' => 'argonar.co', 'kind' => 'tournaments+wallet'],
    ]);
    $objs++;

    // Column discovery (defensive — schema is not in setup.sql)
    $cols = $argonar_pdo->query("SHOW COLUMNS FROM accounts")->fetchAll(PDO::FETCH_COLUMN);
    $has = fn($c) => in_array($c, $cols, true);

    $select = ['id'];
    foreach (['display_name','email','h_coins','ref_code','claim_status','created_at','gcash_number','contact_number'] as $c) {
        if ($has($c)) $select[] = $c;
    }
    $sql = "SELECT " . implode(',', $select) . " FROM accounts";
    $rows = $argonar_pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $r) {
        $label = $r['display_name'] ?? ($r['email'] ?? ('Account #' . $r['id']));
        $ref   = omni_ref('argonar', 'person', $r['id']);

        $props = [];
        foreach (['email','h_coins','ref_code','claim_status','gcash_number','contact_number','created_at'] as $k) {
            if (array_key_exists($k, $r)) $props[$k] = $r[$k];
        }

        $obj_id = omni_upsert_object($argonar_pdo, [
            'ref'          => $ref,
            'type'         => 'Person',
            'business'     => 'argonar',
            'label'        => $label,
            'props'        => $props,
            'source_table' => 'accounts',
            'source_id'    => (string)$r['id'],
        ]);
        $objs++;

        if (omni_link($argonar_pdo, $obj_id, $biz_argonar_id, 'BELONGS_TO')) $links++;
    }

    omni_finish_run($argonar_pdo, $run_id, $objs, $links);
} catch (Exception $e) {
    $err = $e->getMessage();
    omni_finish_run($argonar_pdo, $run_id, $objs, $links, $err);
}

if (php_sapi_name() === 'cli' || (isset($_GET['verbose']) && $_GET['verbose'])) {
    echo "[sync-accounts] objs=$objs links=$links" . ($err ? " ERR=$err" : "") . "\n";
}
return ['pipeline'=>'sync-accounts','objs'=>$objs,'links'=>$links,'err'=>$err];
