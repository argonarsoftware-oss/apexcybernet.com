<?php
/**
 * sync-events.php — activity_logs → coarse Event objects (one per session).
 *
 * We do NOT ingest every pageview — that's millions of rows. Instead one Event
 * object per (session_id, site) with start/end/count summary, linked to Person
 * if the session has an account_id.
 */

require_once __DIR__ . '/taxonomy.php';
if (!isset($apexcybernet_pdo)) { require_once __DIR__ . '/../../../includes/db.php'; $apexcybernet_pdo = $pdo; }

$run_id = omni_start_run($apexcybernet_pdo, 'sync-events');
$objs = 0; $links = 0; $err = null;

try {
    // Only sessions from the last 7 days, to keep cost bounded
    $rows = $apexcybernet_pdo->query(
        "SELECT session_id,
                COALESCE(NULLIF(site,''), 'apexcybernet') AS site,
                MAX(account_id) AS account_id,
                MIN(created_at) AS started_at,
                MAX(created_at) AS ended_at,
                COUNT(*) AS event_count
         FROM activity_logs
         WHERE session_id IS NOT NULL
           AND session_id <> ''
           AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         GROUP BY session_id, site
         ORDER BY started_at DESC
         LIMIT 5000"
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $r) {
        $site = $r['site'];
        $biz_ref = 'global:business:' . $site;
        $biz_id  = omni_id_for_ref($apexcybernet_pdo, $biz_ref);

        $ref = omni_ref($site, 'event', $r['session_id'] . ':' . substr($r['started_at'],0,10));
        $duration_sec = max(0, strtotime($r['ended_at']) - strtotime($r['started_at']));
        $label = 'Session ' . substr($r['session_id'], 0, 8) . '… · ' . $r['event_count'] . ' events · ' . $site;

        $eid = omni_upsert_object($apexcybernet_pdo, [
            'ref'=>$ref,'type'=>'Event','business'=>$site,'label'=>$label,
            'props'=>[
                'session_id'=>$r['session_id'],
                'site'=>$site,
                'started_at'=>$r['started_at'],
                'ended_at'=>$r['ended_at'],
                'duration_sec'=>$duration_sec,
                'event_count'=>(int)$r['event_count'],
                'account_id'=>$r['account_id'] ? (int)$r['account_id'] : null,
            ],
            'source_table'=>'activity_logs','source_id'=>$r['session_id'],
        ]);
        $objs++;

        if ($biz_id && omni_link($apexcybernet_pdo, $eid, $biz_id, 'BELONGS_TO', ['occurred_at'=>$r['started_at']])) $links++;

        if (!empty($r['account_id'])) {
            $p_ref = omni_ref('apexcybernet','person',$r['account_id']);
            $p_id  = omni_id_for_ref($apexcybernet_pdo, $p_ref);
            if ($p_id && omni_link($apexcybernet_pdo, $p_id, $eid, 'PARTICIPATED', ['occurred_at'=>$r['started_at']])) {
                $links++;
            }
        }
    }

    omni_finish_run($apexcybernet_pdo, $run_id, $objs, $links);
} catch (Exception $e) {
    $err = $e->getMessage();
    omni_finish_run($apexcybernet_pdo, $run_id, $objs, $links, $err);
}

if (php_sapi_name() === 'cli' || (isset($_GET['verbose']) && $_GET['verbose'])) {
    echo "[sync-events] objs=$objs links=$links" . ($err ? " ERR=$err" : "") . "\n";
}
return ['pipeline'=>'sync-events','objs'=>$objs,'links'=>$links,'err'=>$err];
