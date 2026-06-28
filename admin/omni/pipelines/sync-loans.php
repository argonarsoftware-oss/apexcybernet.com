<?php
/**
 * sync-loans.php — loans + borrowers (external Loan DB) → Loan + Person objects.
 *
 * Loan DB lives in loan_management_ph, connected via .env loader pattern
 * copied from activity-bizops.php.
 */

require_once __DIR__ . '/taxonomy.php';
if (!isset($apexcybernet_pdo)) { require_once __DIR__ . '/../../../includes/db.php'; $apexcybernet_pdo = $pdo; }

$run_id = omni_start_run($apexcybernet_pdo, 'sync-loans');
$objs = 0; $links = 0; $err = null;

try {
    // Connect to Loan DB
    $loan_pdo = null;
    $env = [];
    foreach ([dirname(__DIR__, 4) . '/loan-management/.env', '/var/www/loan-management/.env'] as $p) {
        if (file_exists($p)) { $env = _load_env($p); break; }
    }
    try {
        $loan_pdo = new PDO(
            "mysql:host=" . ($env['DB_HOST'] ?? 'localhost') . ";dbname=" . ($env['DB_NAME'] ?? 'loan_management_ph') . ";charset=utf8mb4",
            $env['DB_USER'] ?? 'root',
            $env['DB_PASS'] ?? '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT]
        );
    } catch (Exception $e) { $loan_pdo = null; }

    if (!$loan_pdo) {
        omni_finish_run($apexcybernet_pdo, $run_id, 0, 0, 'Loan DB unreachable');
        return ['pipeline'=>'sync-loans','objs'=>0,'links'=>0,'err'=>'loan DB unreachable'];
    }

    // Ensure Business(loan)
    $biz_loan_id = omni_upsert_object($apexcybernet_pdo, [
        'ref'=>'global:business:loan','type'=>'Business','business'=>'loan',
        'label'=>'Loan PH',
        'props'=>['domain'=>'argonarsoftware.com','kind'=>'lending'],
    ]);
    $objs++;

    // Borrowers → Person(loan)
    try {
        $bcols = $loan_pdo->query("SHOW COLUMNS FROM borrowers")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) { $bcols = []; }
    $bhas = fn($c) => in_array($c, $bcols, true);

    if ($bcols) {
        $sel = ['borrower_id'];
        foreach (['first_name','last_name','email','phone','contact_number','created_at'] as $c) {
            if ($bhas($c)) $sel[] = $c;
        }
        $bs = $loan_pdo->query("SELECT " . implode(',', $sel) . " FROM borrowers")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($bs as $b) {
            $name = trim(($b['first_name'] ?? '') . ' ' . ($b['last_name'] ?? '')) ?: ($b['email'] ?? ('Borrower #' . $b['borrower_id']));
            $ref  = omni_ref('loan', 'person', $b['borrower_id']);
            $pid = omni_upsert_object($apexcybernet_pdo, [
                'ref'=>$ref,'type'=>'Person','business'=>'loan','label'=>$name,
                'props'=>$b,
                'source_table'=>'borrowers','source_id'=>(string)$b['borrower_id'],
            ]);
            $objs++;
            if (omni_link($apexcybernet_pdo, $pid, $biz_loan_id, 'BELONGS_TO')) $links++;
        }
    }

    // Loans → Loan object + BORROWED link
    try {
        $lcols = $loan_pdo->query("SHOW COLUMNS FROM loans")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) { $lcols = []; }
    $lhas = fn($c) => in_array($c, $lcols, true);

    if ($lcols) {
        $id_col = $lhas('loan_id') ? 'loan_id' : 'id';
        $sel = [$id_col . ' AS loan_id'];
        foreach (['borrower_id','principal_amount','interest_rate','term_months','status','disbursed_at','due_date','created_at'] as $c) {
            if ($lhas($c)) $sel[] = $c;
        }
        $ls = $loan_pdo->query("SELECT " . implode(',', $sel) . " FROM loans")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($ls as $l) {
            $p = (float)($l['principal_amount'] ?? 0);
            $label = '₱' . number_format($p, 2) . ' · ' . ($l['status'] ?? '');
            $ref = omni_ref('loan','loan',$l['loan_id']);
            $lid = omni_upsert_object($apexcybernet_pdo, [
                'ref'=>$ref,'type'=>'Loan','business'=>'loan','label'=>$label,
                'props'=>$l,
                'source_table'=>'loans','source_id'=>(string)$l['loan_id'],
            ]);
            $objs++;

            if (!empty($l['borrower_id'])) {
                $p_ref = omni_ref('loan','person',$l['borrower_id']);
                $pid = omni_id_for_ref($apexcybernet_pdo, $p_ref);
                if ($pid && omni_link($apexcybernet_pdo, $pid, $lid, 'BORROWED', ['occurred_at'=>$l['disbursed_at'] ?? ($l['created_at'] ?? null)])) {
                    $links++;
                }
            }
            if (omni_link($apexcybernet_pdo, $lid, $biz_loan_id, 'BELONGS_TO')) $links++;
        }
    }

    omni_finish_run($apexcybernet_pdo, $run_id, $objs, $links);
} catch (Exception $e) {
    $err = $e->getMessage();
    omni_finish_run($apexcybernet_pdo, $run_id, $objs, $links, $err);
}

if (php_sapi_name() === 'cli' || (isset($_GET['verbose']) && $_GET['verbose'])) {
    echo "[sync-loans] objs=$objs links=$links" . ($err ? " ERR=$err" : "") . "\n";
}
return ['pipeline'=>'sync-loans','objs'=>$objs,'links'=>$links,'err'=>$err];
