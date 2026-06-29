<?php
/**
 * admin/omni/pipelines/taxonomy.php
 *
 * Canonical type + relation registry for the Omniscient ontology.
 * Also exposes the core helper API used by every pipeline and by explore.php:
 *   omni_upsert_object(), omni_link(), omni_record_action(),
 *   omni_object_by_ref(), omni_object_by_id(), omni_neighbors(),
 *   omni_start_run(), omni_finish_run(), omni_log().
 *
 * Depends on a $pdo or $apexcybernet_pdo already in scope when helpers are called.
 * Never outputs anything on include.
 */

// ── Canonical object types ──
const OMNI_TYPES = [
    'Person',         // accounts
    'Business',       // apexcybernet, ocpd, loan, alrisha
    'Transaction',    // loan repayments, alrisha sales
    'Loan',           // loan_management_ph.loans
    'Team',           // apexcybernet teams
    'Player',         // solo players
    'Match',          // bracket matches
    'Event',          // activity_logs pageviews / clicks (coarse per session)
    'Decision',       // decision_log BR-XXXX
    'Booking',        // oslobparagliding_db.bookings
    'Asset',          // real assets — equity, deed, IP (harvest targets)
];

// ── Canonical relations ──
const OMNI_RELATIONS = [
    'PAID',           // Person → Person/Business (money out)
    'RECEIVED',       // Person/Business → Person (money in)
    'BORROWED',       // Person → Loan
    'REPAID',         // Person → Loan (partial/full repayment)
    'CAPTAIN_OF',     // Person → Team (explicit team.captain_account_id)
    'MEMBER_OF',      // Person → Team (explicit team.member_N_account_id)
    'REGISTERED_AS',  // Person → Player (explicit solo_players.account_id)
    'PARTICIPATED',   // Person → Team / Match / Event
    'PLAYED_IN',      // Team → Match
    'PLACED',         // Team → Tournament (placement result)
    'BOOKED',         // Person → Booking
    'REFERRED',       // Person → Person (referrer)
    'APPROVED',       // Person (admin) → any object
    'IMPACTS',        // Decision → any object it affects
    'HOSTED',         // Business → Business (e.g. Alrisha hosting Apex Cybernet cafe)
    'MENTIONS',       // Decision → tag/object
    'CONVERTED_TO',   // social asset → real Asset (harvest path, BR-0009)
    'BELONGS_TO',     // any object → Business
];

// ── Canonical action types (writes to omni_actions) ──
const OMNI_ACTIONS = [
    'approve_team',
    'reject_team',
    'rank_team',
    'approve_loan',
    'extend_loan',
    'write_off_loan',
    'close_decision',
    'annotate',
    'propose_conversion', // social → real asset
    'simulate',
];

// ══════════════════════════════════════════════════════════════
// HELPERS
// ══════════════════════════════════════════════════════════════

/**
 * Upsert an object by its canonical ref (e.g. "apexcybernet:person:42").
 * Returns the omni_objects.id.
 */
function omni_upsert_object(PDO $db, array $row): int {
    $ref      = $row['ref']      ?? '';
    $type     = $row['type']     ?? '';
    $business = $row['business'] ?? 'apexcybernet';
    $label    = $row['label']    ?? '';
    $props    = $row['props']    ?? [];
    $src_tbl  = $row['source_table'] ?? '';
    $src_id   = $row['source_id']    ?? '';

    if (!$ref || !$type) throw new InvalidArgumentException('omni_upsert_object: ref and type required');

    $props_json = is_string($props) ? $props : json_encode($props, JSON_UNESCAPED_UNICODE);

    $stmt = $db->prepare(
        "INSERT INTO omni_objects (ref, type, business, label, props, source_table, source_id)
         VALUES (:ref, :type, :biz, :label, :props, :stbl, :sid)
         ON DUPLICATE KEY UPDATE
            type = VALUES(type),
            business = VALUES(business),
            label = VALUES(label),
            props = VALUES(props),
            source_table = VALUES(source_table),
            source_id = VALUES(source_id),
            updated_at = CURRENT_TIMESTAMP"
    );
    $stmt->execute([
        ':ref'   => $ref,
        ':type'  => $type,
        ':biz'   => $business,
        ':label' => $label,
        ':props' => $props_json,
        ':stbl'  => $src_tbl,
        ':sid'   => (string)$src_id,
    ]);

    $id = (int)$db->lastInsertId();
    if ($id > 0) return $id;

    // lastInsertId is 0 on pure update — fetch existing.
    $q = $db->prepare("SELECT id FROM omni_objects WHERE ref = ?");
    $q->execute([$ref]);
    return (int)$q->fetchColumn();
}

/**
 * Create a link between two objects. Idempotent on (from,to,relation,occurred_at).
 * Accepts ids or refs (auto-resolves refs to ids).
 */
function omni_link(PDO $db, $from, $to, string $relation, array $opts = []): bool {
    $from_id = is_int($from) ? $from : omni_id_for_ref($db, (string)$from);
    $to_id   = is_int($to)   ? $to   : omni_id_for_ref($db, (string)$to);
    if (!$from_id || !$to_id) return false;

    $props       = $opts['props'] ?? [];
    $occurred_at = $opts['occurred_at'] ?? null;

    try {
        $stmt = $db->prepare(
            "INSERT IGNORE INTO omni_links (from_id, to_id, relation, props, occurred_at)
             VALUES (:f, :t, :r, :p, :o)"
        );
        $stmt->execute([
            ':f' => $from_id,
            ':t' => $to_id,
            ':r' => $relation,
            ':p' => json_encode($props, JSON_UNESCAPED_UNICODE),
            ':o' => $occurred_at,
        ]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

function omni_id_for_ref(PDO $db, string $ref): int {
    $q = $db->prepare("SELECT id FROM omni_objects WHERE ref = ?");
    $q->execute([$ref]);
    return (int)$q->fetchColumn();
}

function omni_object_by_ref(PDO $db, string $ref): ?array {
    $q = $db->prepare("SELECT * FROM omni_objects WHERE ref = ?");
    $q->execute([$ref]);
    $r = $q->fetch(PDO::FETCH_ASSOC);
    return $r ?: null;
}

function omni_object_by_id(PDO $db, int $id): ?array {
    $q = $db->prepare("SELECT * FROM omni_objects WHERE id = ?");
    $q->execute([$id]);
    $r = $q->fetch(PDO::FETCH_ASSOC);
    return $r ?: null;
}

/**
 * 1-hop neighbors. Returns [out => [...], in => [...]].
 * Each neighbor row: id, ref, type, label, relation, props, occurred_at
 */
function omni_neighbors(PDO $db, int $object_id, int $limit = 200): array {
    $out = $db->prepare(
        "SELECT o.id, o.ref, o.type, o.business, o.label, l.relation, l.props AS link_props, l.occurred_at
         FROM omni_links l
         JOIN omni_objects o ON o.id = l.to_id
         WHERE l.from_id = ?
         ORDER BY COALESCE(l.occurred_at, l.created_at) DESC
         LIMIT $limit"
    );
    $out->execute([$object_id]);

    $in = $db->prepare(
        "SELECT o.id, o.ref, o.type, o.business, o.label, l.relation, l.props AS link_props, l.occurred_at
         FROM omni_links l
         JOIN omni_objects o ON o.id = l.from_id
         WHERE l.to_id = ?
         ORDER BY COALESCE(l.occurred_at, l.created_at) DESC
         LIMIT $limit"
    );
    $in->execute([$object_id]);

    return [
        'out' => $out->fetchAll(PDO::FETCH_ASSOC),
        'in'  => $in->fetchAll(PDO::FETCH_ASSOC),
    ];
}

/**
 * Record a proposed/executed/simulated action against an object.
 */
function omni_record_action(PDO $db, array $a): int {
    $stmt = $db->prepare(
        "INSERT INTO omni_actions (object_id, action_type, payload, performed_by, status, result)
         VALUES (:oid, :atype, :payload, :who, :status, :result)"
    );
    $stmt->execute([
        ':oid'     => $a['object_id'] ?? null,
        ':atype'   => $a['action_type'],
        ':payload' => json_encode($a['payload'] ?? [], JSON_UNESCAPED_UNICODE),
        ':who'     => $a['performed_by'] ?? ($_SESSION['admin_username'] ?? 'system'),
        ':status'  => $a['status'] ?? 'proposed',
        ':result'  => isset($a['result']) ? json_encode($a['result'], JSON_UNESCAPED_UNICODE) : null,
    ]);
    return (int)$db->lastInsertId();
}

// ── Pipeline run bookkeeping ──

function omni_start_run(PDO $db, string $pipeline): int {
    $db->prepare("INSERT INTO omni_pipeline_runs (pipeline, status) VALUES (?, 'running')")
       ->execute([$pipeline]);
    return (int)$db->lastInsertId();
}

function omni_finish_run(PDO $db, int $run_id, int $objs, int $links, ?string $err = null): void {
    $db->prepare(
        "UPDATE omni_pipeline_runs
         SET finished_at = NOW(),
             status = ?,
             objects_upserted = ?,
             links_upserted = ?,
             notes = ?
         WHERE id = ?"
    )->execute([$err ? 'error' : 'ok', $objs, $links, $err, $run_id]);
}

// ── Refs ──

function omni_ref(string $biz, string $type, $id): string {
    return strtolower($biz) . ':' . strtolower($type) . ':' . (string)$id;
}

// ── .env loader (duplicated from omni/auth.php so pipelines don't require auth) ──
if (!function_exists('_load_env')) {
    function _load_env(string $path): array {
        $env = [];
        if (!file_exists($path)) return $env;
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
            if (strpos(trim($ln), '#') === 0 || strpos($ln, '=') === false) continue;
            [$k, $v] = explode('=', $ln, 2);
            $env[trim($k)] = trim($v);
        }
        return $env;
    }
}
