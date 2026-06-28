<?php
/**
 * sync-hc-transactions.php — h_coin_transactions → Transaction objects.
 *
 * Each row → Transaction object + PAID/RECEIVED links between Persons.
 * Credits from 'purchase'/'welcome_bonus' link from Business(apexcybernet) as issuer.
 */

require_once __DIR__ . '/taxonomy.php';
if (!isset($apexcybernet_pdo)) { require_once __DIR__ . '/../../../includes/db.php'; $apexcybernet_pdo = $pdo; }

$run_id = omni_start_run($apexcybernet_pdo, 'sync-hc-transactions');
$objs = 0; $links = 0; $err = null;

try {
    // Incremental: only process rows newer than the last run's max source_id
    $last_sid = (int)$apexcybernet_pdo->query(
        "SELECT COALESCE(MAX(CAST(source_id AS UNSIGNED)), 0)
         FROM omni_objects
         WHERE type='Transaction' AND source_table='h_coin_transactions'"
    )->fetchColumn();

    try {
        $cols = $apexcybernet_pdo->query("SHOW COLUMNS FROM h_coin_transactions")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) { $cols = []; }
    if (empty($cols)) {
        omni_finish_run($apexcybernet_pdo, $run_id, 0, 0, 'h_coin_transactions table missing');
        return ['pipeline'=>'sync-hc-transactions','objs'=>0,'links'=>0,'err'=>'missing table'];
    }
    $has = fn($c) => in_array($c, $cols, true);
    $has_ref = $has('ref');

    $select = 'id, account_id, type, amount, reason, created_at' . ($has_ref ? ', ref' : '');
    $stmt = $apexcybernet_pdo->prepare("SELECT $select FROM h_coin_transactions WHERE id > ? ORDER BY id ASC LIMIT 20000");
    $stmt->execute([$last_sid]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $biz_apexcybernet = omni_id_for_ref($apexcybernet_pdo, 'global:business:apexcybernet');

    foreach ($rows as $r) {
        $tx_ref = omni_ref('apexcybernet', 'transaction', 'hc:' . $r['id']);
        $amount = (float)$r['amount'];
        $reason = (string)($r['reason'] ?? '');
        $type_d = (string)($r['type'] ?? 'credit');
        $occ    = $r['created_at'] ?? null;

        $label = sprintf('%s %s HC · %s',
            $type_d === 'credit' ? '+' : '−',
            rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.'),
            $reason ?: 'tx'
        );

        $tx_id = omni_upsert_object($apexcybernet_pdo, [
            'ref'          => $tx_ref,
            'type'         => 'Transaction',
            'business'     => 'apexcybernet',
            'label'        => $label,
            'props'        => [
                'currency'   => 'HC',
                'amount'     => $amount,
                'direction'  => $type_d,
                'reason'     => $reason,
                'ref'        => $r['ref'] ?? null,
                'account_id' => (int)$r['account_id'],
                'created_at' => $occ,
            ],
            'source_table' => 'h_coin_transactions',
            'source_id'    => (string)$r['id'],
        ]);
        $objs++;

        $person_ref = omni_ref('apexcybernet', 'person', $r['account_id']);
        $person_id  = omni_id_for_ref($apexcybernet_pdo, $person_ref);

        if ($person_id) {
            if ($type_d === 'credit') {
                // Money in: Person RECEIVED Transaction
                if (omni_link($apexcybernet_pdo, $person_id, $tx_id, 'RECEIVED', ['occurred_at'=>$occ])) $links++;
                if (in_array($reason, ['purchase','welcome_bonus','topup'], true) && $biz_apexcybernet) {
                    if (omni_link($apexcybernet_pdo, $biz_apexcybernet, $tx_id, 'PAID', ['occurred_at'=>$occ])) $links++;
                }
            } else {
                if (omni_link($apexcybernet_pdo, $person_id, $tx_id, 'PAID', ['occurred_at'=>$occ])) $links++;
            }
        }
    }

    omni_finish_run($apexcybernet_pdo, $run_id, $objs, $links);
} catch (Exception $e) {
    $err = $e->getMessage();
    omni_finish_run($apexcybernet_pdo, $run_id, $objs, $links, $err);
}

if (php_sapi_name() === 'cli' || (isset($_GET['verbose']) && $_GET['verbose'])) {
    echo "[sync-hc-transactions] objs=$objs links=$links" . ($err ? " ERR=$err" : "") . "\n";
}
return ['pipeline'=>'sync-hc-transactions','objs'=>$objs,'links'=>$links,'err'=>$err];
