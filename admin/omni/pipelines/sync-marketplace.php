<?php
/**
 * sync-marketplace.php — marketplace_listings → Listing objects + SOLD/BOUGHT links.
 */

require_once __DIR__ . '/taxonomy.php';
if (!isset($apexcybernet_pdo)) { require_once __DIR__ . '/../../../includes/db.php'; $apexcybernet_pdo = $pdo; }

$run_id = omni_start_run($apexcybernet_pdo, 'sync-marketplace');
$objs = 0; $links = 0; $err = null;

try {
    try {
        $cols = $apexcybernet_pdo->query("SHOW COLUMNS FROM marketplace_listings")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) { $cols = []; }
    if (empty($cols)) {
        omni_finish_run($apexcybernet_pdo, $run_id, 0, 0, 'marketplace_listings missing');
        return ['pipeline'=>'sync-marketplace','objs'=>0,'links'=>0,'err'=>'missing'];
    }

    $biz_apexcybernet = omni_id_for_ref($apexcybernet_pdo, 'global:business:apexcybernet');
    $rows = $apexcybernet_pdo->query("SELECT * FROM marketplace_listings")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $l) {
        $ref = omni_ref('apexcybernet','listing',$l['id']);
        $lid = omni_upsert_object($apexcybernet_pdo, [
            'ref'=>$ref,'type'=>'Listing','business'=>'apexcybernet',
            'label'=>($l['title'] ?? ('Listing #'.$l['id'])) . ' · ' . (isset($l['price']) ? ('₱'.number_format((float)$l['price'],2)) : ''),
            'props'=>$l,
            'source_table'=>'marketplace_listings','source_id'=>(string)$l['id'],
        ]);
        $objs++;
        if ($biz_apexcybernet) { if (omni_link($apexcybernet_pdo, $lid, $biz_apexcybernet, 'BELONGS_TO')) $links++; }

        if (!empty($l['seller_id'])) {
            $s_ref = omni_ref('apexcybernet','person',$l['seller_id']);
            $s_id = omni_id_for_ref($apexcybernet_pdo, $s_ref);
            if ($s_id && omni_link($apexcybernet_pdo, $s_id, $lid, 'SOLD', ['occurred_at'=>$l['created_at']??null])) $links++;
        }
        if (!empty($l['buyer_id'])) {
            $b_ref = omni_ref('apexcybernet','person',$l['buyer_id']);
            $b_id = omni_id_for_ref($apexcybernet_pdo, $b_ref);
            if ($b_id && omni_link($apexcybernet_pdo, $b_id, $lid, 'BOUGHT', ['occurred_at'=>$l['sold_at']??($l['created_at']??null)])) $links++;
        }
    }

    omni_finish_run($apexcybernet_pdo, $run_id, $objs, $links);
} catch (Exception $e) {
    $err = $e->getMessage();
    omni_finish_run($apexcybernet_pdo, $run_id, $objs, $links, $err);
}

if (php_sapi_name() === 'cli' || (isset($_GET['verbose']) && $_GET['verbose'])) {
    echo "[sync-marketplace] objs=$objs links=$links" . ($err ? " ERR=$err" : "") . "\n";
}
return ['pipeline'=>'sync-marketplace','objs'=>$objs,'links'=>$links,'err'=>$err];
