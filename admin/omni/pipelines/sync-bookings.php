<?php
/**
 * sync-bookings.php — external OCPD DB (oslobparagliding_db.bookings) → Booking objects.
 */

require_once __DIR__ . '/taxonomy.php';
if (!isset($apexcybernet_pdo)) { require_once __DIR__ . '/../../../includes/db.php'; $apexcybernet_pdo = $pdo; }

$run_id = omni_start_run($apexcybernet_pdo, 'sync-bookings');
$objs = 0; $links = 0; $err = null;

try {
    $ocpd_pdo = null;
    $env = [];
    foreach ([dirname(__DIR__, 4) . '/oslobparagliding/.env', '/var/www/oslobparagliding/.env'] as $p) {
        if (file_exists($p)) { $env = _load_env($p); break; }
    }
    try {
        $ocpd_pdo = new PDO(
            "mysql:host=" . ($env['DB_HOST'] ?? 'localhost') . ";dbname=" . ($env['DB_NAME'] ?? 'oslobparagliding_db') . ";charset=utf8mb4",
            $env['DB_USER'] ?? 'root', $env['DB_PASS'] ?? '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT]
        );
    } catch (Exception $e) { $ocpd_pdo = null; }

    if (!$ocpd_pdo) {
        omni_finish_run($apexcybernet_pdo, $run_id, 0, 0, 'OCPD DB unreachable');
        return ['pipeline'=>'sync-bookings','objs'=>0,'links'=>0,'err'=>'ocpd unreachable'];
    }

    $biz_ocpd = omni_upsert_object($apexcybernet_pdo, [
        'ref'=>'global:business:ocpd','type'=>'Business','business'=>'ocpd',
        'label'=>'OCPD','props'=>['domain'=>'oslobcebuparagliding.com','kind'=>'paragliding'],
    ]);
    $objs++;

    try {
        $cols = $ocpd_pdo->query("SHOW COLUMNS FROM bookings")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) { $cols = []; }
    if (empty($cols)) {
        omni_finish_run($apexcybernet_pdo, $run_id, $objs, $links, 'bookings table missing');
        return ['pipeline'=>'sync-bookings','objs'=>$objs,'links'=>$links,'err'=>'bookings missing'];
    }

    // Try common id columns
    $id_col = in_array('booking_id', $cols, true) ? 'booking_id'
           : (in_array('id', $cols, true) ? 'id' : null);
    if (!$id_col) {
        omni_finish_run($apexcybernet_pdo, $run_id, $objs, $links, 'no id column on bookings');
        return ['pipeline'=>'sync-bookings','objs'=>$objs,'links'=>$links,'err'=>'no id col'];
    }

    $rows = $ocpd_pdo->query("SELECT * FROM bookings ORDER BY $id_col DESC LIMIT 5000")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $b) {
        $bid_src = $b[$id_col];
        $ref = omni_ref('ocpd','booking',$bid_src);
        $label = ($b['customer_name'] ?? ($b['email'] ?? ('Booking #'.$bid_src)));
        if (!empty($b['booking_date']) || !empty($b['date'])) {
            $label .= ' · ' . ($b['booking_date'] ?? $b['date']);
        }

        $bid = omni_upsert_object($apexcybernet_pdo, [
            'ref'=>$ref,'type'=>'Booking','business'=>'ocpd','label'=>$label,
            'props'=>$b,
            'source_table'=>'bookings','source_id'=>(string)$bid_src,
        ]);
        $objs++;

        if ($biz_ocpd) { if (omni_link($apexcybernet_pdo, $bid, $biz_ocpd, 'BELONGS_TO', ['occurred_at'=>$b['created_at']??null])) $links++; }

        // Match email → apexcybernet Person if possible (cross-business join!)
        $email = strtolower(trim($b['email'] ?? ''));
        if ($email) {
            $q = $apexcybernet_pdo->prepare("SELECT id FROM accounts WHERE LOWER(email) = ? LIMIT 1");
            $q->execute([$email]);
            $aid = $q->fetchColumn();
            if ($aid) {
                $p_ref = omni_ref('apexcybernet','person',$aid);
                $p_id  = omni_id_for_ref($apexcybernet_pdo, $p_ref);
                if ($p_id && omni_link($apexcybernet_pdo, $p_id, $bid, 'BOOKED', ['occurred_at'=>$b['created_at']??null])) {
                    $links++;
                }
            }
        }
    }

    omni_finish_run($apexcybernet_pdo, $run_id, $objs, $links);
} catch (Exception $e) {
    $err = $e->getMessage();
    omni_finish_run($apexcybernet_pdo, $run_id, $objs, $links, $err);
}

if (php_sapi_name() === 'cli' || (isset($_GET['verbose']) && $_GET['verbose'])) {
    echo "[sync-bookings] objs=$objs links=$links" . ($err ? " ERR=$err" : "") . "\n";
}
return ['pipeline'=>'sync-bookings','objs'=>$objs,'links'=>$links,'err'=>$err];
