<?php
/**
 * sync-alrisha.php — alrisha_snapshots → Business(alrisha) + lightweight
 *                    Transaction projections from the most recent snapshot per company.
 *
 * The snapshot payload is arbitrary JSON pushed by the local Alrisha ERP.
 * We extract a few common shapes defensively: totals, recent sales, products.
 */

require_once __DIR__ . '/taxonomy.php';
if (!isset($apexcybernet_pdo)) { require_once __DIR__ . '/../../../includes/db.php'; $apexcybernet_pdo = $pdo; }

$run_id = omni_start_run($apexcybernet_pdo, 'sync-alrisha');
$objs = 0; $links = 0; $err = null;

try {
    // Business(alrisha) root
    $biz_alrisha = omni_upsert_object($apexcybernet_pdo, [
        'ref'=>'global:business:alrisha','type'=>'Business','business'=>'alrisha',
        'label'=>'Alrisha ERP',
        'props'=>['kind'=>'erp+cafe','source'=>'alrisha_snapshots'],
    ]);
    $objs++;

    // Latest snapshot per company
    try {
        $rows = $apexcybernet_pdo->query(
            "SELECT s.company_id, s.snapshot, s.pushed_at
             FROM alrisha_snapshots s
             INNER JOIN (
                 SELECT company_id, MAX(pushed_at) AS mx
                 FROM alrisha_snapshots GROUP BY company_id
             ) t ON t.company_id = s.company_id AND t.mx = s.pushed_at"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $rows = []; }

    foreach ($rows as $r) {
        $company_id = (int)$r['company_id'];
        $snap = json_decode($r['snapshot'], true) ?: [];

        // Each company = its own sub-Business under alrisha
        $co_ref = omni_ref('alrisha','business', $company_id);
        $co_label = 'Alrisha · Company ' . $company_id;
        if (!empty($snap['company_name'])) $co_label = 'Alrisha · ' . $snap['company_name'];

        $cid = omni_upsert_object($apexcybernet_pdo, [
            'ref'=>$co_ref,'type'=>'Business','business'=>'alrisha','label'=>$co_label,
            'props'=>[
                'company_id'=>$company_id,
                'last_sync'=>$r['pushed_at'],
                'keys'=>array_keys($snap),
                'totals'=>$snap['totals'] ?? null,
                'revenue_today'=>$snap['revenue_today'] ?? null,
                'revenue_month'=>$snap['revenue_month'] ?? null,
            ],
            'source_table'=>'alrisha_snapshots','source_id'=>(string)$company_id,
        ]);
        $objs++;
        if (omni_link($apexcybernet_pdo, $cid, $biz_alrisha, 'BELONGS_TO')) $links++;

        // Recent transactions, if present in the snapshot
        $tx_list = [];
        foreach (['recent_sales','recent_transactions','transactions','sales'] as $k) {
            if (!empty($snap[$k]) && is_array($snap[$k])) { $tx_list = $snap[$k]; break; }
        }
        foreach (array_slice($tx_list, 0, 200) as $tx) {
            if (!is_array($tx)) continue;
            $tx_id_src = (string)($tx['id'] ?? $tx['sale_id'] ?? $tx['transaction_id'] ?? md5(json_encode($tx)));
            $tx_ref = omni_ref('alrisha','transaction', $company_id . ':' . $tx_id_src);
            $amount = (float)($tx['total'] ?? $tx['amount'] ?? $tx['grand_total'] ?? 0);
            $when   = $tx['created_at'] ?? $tx['date'] ?? null;
            $tid = omni_upsert_object($apexcybernet_pdo, [
                'ref'=>$tx_ref,'type'=>'Transaction','business'=>'alrisha',
                'label'=>'₱' . number_format($amount, 2) . ' · ' . ($tx['description'] ?? ($tx['reference'] ?? 'sale')),
                'props'=>['currency'=>'PHP','amount'=>$amount,'direction'=>'credit','raw'=>$tx,'company_id'=>$company_id],
                'source_table'=>'alrisha_snapshots','source_id'=>$company_id . ':' . $tx_id_src,
            ]);
            $objs++;
            if (omni_link($apexcybernet_pdo, $cid, $tid, 'RECEIVED', ['occurred_at'=>$when])) $links++;
        }
    }

    omni_finish_run($apexcybernet_pdo, $run_id, $objs, $links);
} catch (Exception $e) {
    $err = $e->getMessage();
    omni_finish_run($apexcybernet_pdo, $run_id, $objs, $links, $err);
}

if (php_sapi_name() === 'cli' || (isset($_GET['verbose']) && $_GET['verbose'])) {
    echo "[sync-alrisha] objs=$objs links=$links" . ($err ? " ERR=$err" : "") . "\n";
}
return ['pipeline'=>'sync-alrisha','objs'=>$objs,'links'=>$links,'err'=>$err];
