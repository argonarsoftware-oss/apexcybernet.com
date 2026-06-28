<?php
/**
 * admin/omni/executors.php — action executors for simulate/explore.
 *
 * Two entry points:
 *   omni_simulate($pdo, $object, $action_type, $payload) — records to
 *     omni_actions with status='simulated' and returns a projection
 *     ("would change X from Y to Z"). Never touches source tables.
 *
 *   omni_execute($pdo, $object, $action_type, $payload) — performs the
 *     write at the source table, then records status='executed' with
 *     the actual result. The dispatcher enforces a per-type allowlist:
 *     anything not wired below is recorded as status='proposed' only.
 *
 * Both return an array { ok, status, message, result }.
 */

require_once __DIR__ . '/pipelines/taxonomy.php';
require_once __DIR__ . '/../../includes/pusher.php';

function _omni_proj_credit_hc(PDO $pdo, array $obj, array $p): array {
    if ($obj['type'] !== 'Person' || $obj['business'] !== 'argonar') {
        return ['ok'=>false,'message'=>'credit_hc only applies to argonar Persons'];
    }
    $amount = (int)($p['amount'] ?? 0);
    if ($amount <= 0) return ['ok'=>false,'message'=>'amount must be > 0'];
    $aid = (int)($obj['source_id'] ?? 0);
    $bal = (int)($pdo->query("SELECT h_coins FROM accounts WHERE id = $aid")->fetchColumn() ?: 0);
    return [
        'ok'=>true,
        'message'=>"Would credit $amount HC ($bal → " . ($bal + $amount) . ")",
        'projection'=>['before'=>$bal,'after'=>$bal+$amount,'delta'=>$amount],
    ];
}

function _omni_exec_credit_hc(PDO $pdo, array $obj, array $p): array {
    $proj = _omni_proj_credit_hc($pdo, $obj, $p);
    if (!$proj['ok']) return $proj;

    $amount = (int)$p['amount'];
    $reason = trim((string)($p['reason'] ?? 'admin-credit'));
    $aid    = (int)$obj['source_id'];

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE accounts SET h_coins = h_coins + ? WHERE id = ?")->execute([$amount, $aid]);
        $pdo->prepare("INSERT INTO h_coin_transactions (account_id, type, amount, reason, ref) VALUES (?, 'credit', ?, ?, ?)")
            ->execute([$aid, $amount, $reason, 'omni:' . ($_SESSION['admin_username'] ?? 'admin')]);
        $new_bal = (int)$pdo->query("SELECT h_coins FROM accounts WHERE id = $aid")->fetchColumn();
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['ok'=>false,'message'=>'DB error: ' . $e->getMessage()];
    }

    try {
        hc_push($pdo, $aid, $amount, 'Omniscient', $new_bal, $reason);
    } catch (Throwable $e) { /* notification best-effort */ }

    return [
        'ok'=>true,
        'message'=>"Credited $amount HC. New balance: $new_bal",
        'result'=>['new_balance'=>$new_bal,'delta'=>$amount],
    ];
}

function _omni_proj_debit_hc(PDO $pdo, array $obj, array $p): array {
    if ($obj['type'] !== 'Person' || $obj['business'] !== 'argonar') {
        return ['ok'=>false,'message'=>'debit_hc only applies to argonar Persons'];
    }
    $amount = (int)($p['amount'] ?? 0);
    if ($amount <= 0) return ['ok'=>false,'message'=>'amount must be > 0'];
    $aid = (int)($obj['source_id'] ?? 0);
    $bal = (int)($pdo->query("SELECT h_coins FROM accounts WHERE id = $aid")->fetchColumn() ?: 0);
    if ($bal < $amount) return ['ok'=>false,'message'=>"Balance $bal < debit $amount"];
    return [
        'ok'=>true,
        'message'=>"Would debit $amount HC ($bal → " . ($bal - $amount) . ")",
        'projection'=>['before'=>$bal,'after'=>$bal-$amount,'delta'=>-$amount],
    ];
}

function _omni_exec_debit_hc(PDO $pdo, array $obj, array $p): array {
    $proj = _omni_proj_debit_hc($pdo, $obj, $p);
    if (!$proj['ok']) return $proj;

    $amount = (int)$p['amount'];
    $reason = trim((string)($p['reason'] ?? 'admin-debit'));
    $aid    = (int)$obj['source_id'];

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE accounts SET h_coins = h_coins - ? WHERE id = ? AND h_coins >= ?")
            ->execute([$amount, $aid, $amount]);
        $pdo->prepare("INSERT INTO h_coin_transactions (account_id, type, amount, reason, ref) VALUES (?, 'debit', ?, ?, ?)")
            ->execute([$aid, $amount, $reason, 'omni:' . ($_SESSION['admin_username'] ?? 'admin')]);
        $new_bal = (int)$pdo->query("SELECT h_coins FROM accounts WHERE id = $aid")->fetchColumn();
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['ok'=>false,'message'=>'DB error: ' . $e->getMessage()];
    }
    return ['ok'=>true,'message'=>"Debited $amount HC. New balance: $new_bal",'result'=>['new_balance'=>$new_bal,'delta'=>-$amount]];
}

function _omni_proj_team_status(PDO $pdo, array $obj, array $p, string $new_status): array {
    if ($obj['type'] !== 'Team') return ['ok'=>false,'message'=>'only applies to Team'];
    $tid = (int)$obj['source_id'];
    $cur = $pdo->query("SELECT status FROM teams WHERE id = $tid")->fetchColumn();
    if (!$cur) return ['ok'=>false,'message'=>'Team not found'];
    return ['ok'=>true,'message'=>"Would set status $cur → $new_status",'projection'=>['before'=>$cur,'after'=>$new_status]];
}

function _omni_exec_team_status(PDO $pdo, array $obj, array $p, string $new_status): array {
    $proj = _omni_proj_team_status($pdo, $obj, $p, $new_status);
    if (!$proj['ok']) return $proj;
    $tid = (int)$obj['source_id'];
    $pdo->prepare("UPDATE teams SET status = ? WHERE id = ?")->execute([$new_status, $tid]);
    return ['ok'=>true,'message'=>"Team status now $new_status",'result'=>['status'=>$new_status]];
}

function _omni_proj_close_decision(PDO $pdo, array $obj, array $p): array {
    if ($obj['type'] !== 'Decision') return ['ok'=>false,'message'=>'only applies to Decision'];
    $out = trim((string)($p['outcome'] ?? ''));
    if ($out === '') return ['ok'=>false,'message'=>'outcome required'];
    return ['ok'=>true,'message'=>"Would close BR-" . str_pad($obj['source_id'], 4, '0', STR_PAD_LEFT) . " with outcome=$out"];
}

function _omni_exec_close_decision(PDO $pdo, array $obj, array $p): array {
    $proj = _omni_proj_close_decision($pdo, $obj, $p);
    if (!$proj['ok']) return $proj;
    $did = (int)$obj['source_id'];
    $out = trim((string)$p['outcome']);
    $pdo->prepare("UPDATE decision_log SET outcome = ?, updated_at = NOW() WHERE id = ?")->execute([$out, $did]);
    return ['ok'=>true,'message'=>"Decision closed with outcome=$out",'result'=>['outcome'=>$out]];
}

function _omni_dispatch(PDO $pdo, array $obj, string $action_type, array $payload, bool $execute): array {
    switch ($action_type) {
        case 'credit_hc':    return $execute ? _omni_exec_credit_hc($pdo, $obj, $payload)    : _omni_proj_credit_hc($pdo, $obj, $payload);
        case 'debit_hc':     return $execute ? _omni_exec_debit_hc($pdo, $obj, $payload)     : _omni_proj_debit_hc($pdo, $obj, $payload);
        case 'approve_team': return $execute ? _omni_exec_team_status($pdo,$obj,$payload,'approved') : _omni_proj_team_status($pdo,$obj,$payload,'approved');
        case 'reject_team':  return $execute ? _omni_exec_team_status($pdo,$obj,$payload,'rejected') : _omni_proj_team_status($pdo,$obj,$payload,'rejected');
        case 'close_decision': return $execute ? _omni_exec_close_decision($pdo, $obj, $payload) : _omni_proj_close_decision($pdo, $obj, $payload);
        case 'annotate':
            $note = trim((string)($payload['note'] ?? ''));
            if ($note === '') return ['ok'=>false,'message'=>'note required'];
            return ['ok'=>true,'message'=>'Note recorded'];
        case 'propose_conversion':
            $target = trim((string)($payload['target_asset'] ?? ''));
            if ($target === '') return ['ok'=>false,'message'=>'target_asset required (e.g. "equity", "deed", "MRR contract")'];
            return ['ok'=>true,'message'=>"Conversion proposal recorded: → $target (BR-0009 harvest path)"];
        case 'simulate':
            return ['ok'=>true,'message'=>'Simulation recorded (no source write)'];
        default:
            return ['ok'=>false,'message'=>"No executor for '$action_type' — proposal only."];
    }
}

/**
 * Simulate an action against one object.
 * Records to omni_actions with status='simulated'. Never touches source.
 */
function omni_simulate(PDO $pdo, array $obj, string $action_type, array $payload): array {
    $res = _omni_dispatch($pdo, $obj, $action_type, $payload, false);
    omni_record_action($pdo, [
        'object_id'   => (int)$obj['id'],
        'action_type' => $action_type,
        'payload'     => $payload,
        'status'      => 'simulated',
        'result'      => $res,
    ]);
    return $res + ['status' => 'simulated'];
}

/**
 * Execute an action against one object.
 * If no executor is wired, records status='proposed' and returns ok=false.
 */
function omni_execute(PDO $pdo, array $obj, string $action_type, array $payload): array {
    $res = _omni_dispatch($pdo, $obj, $action_type, $payload, true);
    $status = $res['ok'] ? 'executed' : (str_contains($res['message'] ?? '', 'No executor') ? 'proposed' : 'rejected');
    omni_record_action($pdo, [
        'object_id'   => (int)$obj['id'],
        'action_type' => $action_type,
        'payload'     => $payload,
        'status'      => $status,
        'result'      => $res,
    ]);
    return $res + ['status' => $status];
}
