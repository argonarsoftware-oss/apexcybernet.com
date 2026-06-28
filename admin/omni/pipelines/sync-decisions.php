<?php
/**
 * sync-decisions.php — decision_log (BR-XXXX) → Decision objects.
 *
 * Simple projection: each row is a Decision, linked BELONGS_TO its business.
 * Tags are stored on props; the IMPACTS edges are manually proposed via Actions later.
 */

require_once __DIR__ . '/taxonomy.php';
if (!isset($apexcybernet_pdo)) { require_once __DIR__ . '/../../../includes/db.php'; $apexcybernet_pdo = $pdo; }

$run_id = omni_start_run($apexcybernet_pdo, 'sync-decisions');
$objs = 0; $links = 0; $err = null;

try {
    try {
        $rows = $apexcybernet_pdo->query("SELECT * FROM decision_log ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $rows = []; }

    foreach ($rows as $r) {
        $br  = 'BR-' . str_pad((string)$r['id'], 4, '0', STR_PAD_LEFT);
        $biz = $r['business'] ?? 'general';
        $biz_norm = in_array($biz, ['apexcybernet','ocpd','loan','alrisha'], true) ? $biz : 'apexcybernet';

        // Ensure the business object exists
        $biz_id = omni_id_for_ref($apexcybernet_pdo, 'global:business:' . $biz_norm);
        if (!$biz_id) {
            $biz_id = omni_upsert_object($apexcybernet_pdo, [
                'ref'=>'global:business:' . $biz_norm,'type'=>'Business',
                'business'=>$biz_norm,'label'=>ucfirst($biz_norm),
                'props'=>['kind'=>'inferred'],
            ]);
            $objs++;
        }

        $ref = omni_ref('apexcybernet','decision',$r['id']);
        $props = [
            'br'           => $br,
            'title'        => $r['title'] ?? '',
            'context'      => $r['context_text'] ?? '',
            'action_taken' => $r['action_taken'] ?? '',
            'result'       => $r['result_text'] ?? '',
            'impact'       => $r['impact_text'] ?? '',
            'outcome'      => $r['outcome'] ?? '',
            'tags'         => $r['tags'] ?? '',
            'business'     => $biz,
            'decided_at'   => $r['decided_at'] ?? null,
            'updated_at'   => $r['updated_at'] ?? null,
        ];
        $did = omni_upsert_object($apexcybernet_pdo, [
            'ref'=>$ref,'type'=>'Decision','business'=>$biz_norm,
            'label'=>$br . ' · ' . ($r['title'] ?? ''),
            'props'=>$props,
            'source_table'=>'decision_log','source_id'=>(string)$r['id'],
        ]);
        $objs++;

        if (omni_link($apexcybernet_pdo, $did, $biz_id, 'BELONGS_TO', [
            'occurred_at' => $r['decided_at'] ?? ($r['created_at'] ?? null),
        ])) $links++;
    }

    omni_finish_run($apexcybernet_pdo, $run_id, $objs, $links);
} catch (Exception $e) {
    $err = $e->getMessage();
    omni_finish_run($apexcybernet_pdo, $run_id, $objs, $links, $err);
}

if (php_sapi_name() === 'cli' || (isset($_GET['verbose']) && $_GET['verbose'])) {
    echo "[sync-decisions] objs=$objs links=$links" . ($err ? " ERR=$err" : "") . "\n";
}
return ['pipeline'=>'sync-decisions','objs'=>$objs,'links'=>$links,'err'=>$err];
