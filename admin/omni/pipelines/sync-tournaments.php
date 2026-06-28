<?php
/**
 * sync-tournaments.php — teams, solo_players, matches, tournament_results
 *                        → Team / Player / Match / Event objects + links.
 */

require_once __DIR__ . '/taxonomy.php';
if (!isset($argonar_pdo)) { require_once __DIR__ . '/../../../includes/db.php'; $argonar_pdo = $pdo; }

$run_id = omni_start_run($argonar_pdo, 'sync-tournaments');
$objs = 0; $links = 0; $err = null;

try {
    $biz_argonar = omni_id_for_ref($argonar_pdo, 'global:business:argonar');

    // ── Teams ──
    try {
        $teams = $argonar_pdo->query("SELECT * FROM teams")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $teams = []; }

    foreach ($teams as $t) {
        $ref = omni_ref('argonar','team',$t['id']);
        $props = [
            'game'               => $t['game'] ?? '',
            'members'            => array_filter([$t['member_1']??null,$t['member_2']??null,$t['member_3']??null,$t['member_4']??null,$t['member_5']??null]),
            'substitute'         => $t['substitute'] ?? '',
            'ref_code'           => $t['ref_code'] ?? null,
            'status'             => $t['status'] ?? '',
            'team_logo'          => $t['team_logo'] ?? '',
            'captain_account_id' => !empty($t['captain_account_id']) ? (int)$t['captain_account_id'] : null,
            'recruiting'         => !empty($t['recruiting']),
            'created_at'         => $t['created_at'] ?? null,
        ];
        $tid = omni_upsert_object($argonar_pdo, [
            'ref'=>$ref,'type'=>'Team','business'=>'argonar',
            'label'=>($t['team_name'] ?? ('Team #'.$t['id'])) . ' · ' . ($t['game'] ?? ''),
            'props'=>$props,
            'source_table'=>'teams','source_id'=>(string)$t['id'],
        ]);
        $objs++;

        if ($biz_argonar) { if (omni_link($argonar_pdo, $tid, $biz_argonar, 'BELONGS_TO')) $links++; }

        // Explicit captain edge (authoritative)
        if (!empty($t['captain_account_id'])) {
            $cap_ref = omni_ref('argonar', 'person', (int)$t['captain_account_id']);
            $cap_id  = omni_id_for_ref($argonar_pdo, $cap_ref);
            if ($cap_id && omni_link($argonar_pdo, $cap_id, $tid, 'CAPTAIN_OF', ['occurred_at' => $t['created_at'] ?? null])) {
                $links++;
            }
        }

        // Explicit member edges (when register.php picker was used)
        foreach ([1,2,3,4,5] as $mi) {
            $col = "member_{$mi}_account_id";
            if (!empty($t[$col])) {
                $m_ref = omni_ref('argonar', 'person', (int)$t[$col]);
                $m_id  = omni_id_for_ref($argonar_pdo, $m_ref);
                if ($m_id && omni_link($argonar_pdo, $m_id, $tid, 'MEMBER_OF', ['occurred_at' => $t['created_at'] ?? null])) {
                    $links++;
                }
            }
        }
        if (!empty($t['substitute_account_id'])) {
            $s_ref = omni_ref('argonar', 'person', (int)$t['substitute_account_id']);
            $s_id  = omni_id_for_ref($argonar_pdo, $s_ref);
            if ($s_id && omni_link($argonar_pdo, $s_id, $tid, 'MEMBER_OF', ['props' => ['role' => 'substitute'], 'occurred_at' => $t['created_at'] ?? null])) {
                $links++;
            }
        }

        // Link team members (by name) to Person — best-effort display_name match
        foreach ($props['members'] as $mname) {
            $mname = trim($mname);
            if (!$mname) continue;
            $q = $argonar_pdo->prepare("SELECT id FROM accounts WHERE display_name = ? LIMIT 1");
            $q->execute([$mname]);
            $pid_src = $q->fetchColumn();
            if ($pid_src) {
                $p_ref = omni_ref('argonar','person',$pid_src);
                $pid = omni_id_for_ref($argonar_pdo, $p_ref);
                if ($pid && omni_link($argonar_pdo, $pid, $tid, 'PARTICIPATED', ['occurred_at'=>$t['created_at']??null])) {
                    $links++;
                }
            }
        }
    }

    // ── Solo players ──
    try {
        $solos = $argonar_pdo->query("SELECT * FROM solo_players")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $solos = []; }

    foreach ($solos as $s) {
        $ref = omni_ref('argonar','player',$s['id']);
        $pid = omni_upsert_object($argonar_pdo, [
            'ref'=>$ref,'type'=>'Player','business'=>'argonar',
            'label'=>($s['player_name'] ?? ('Player #'.$s['id'])) . ' · ' . ($s['game'] ?? ''),
            'props'=>[
                'game'=>$s['game']??'','rank_tier'=>$s['rank_tier']??'','preferred_role'=>$s['preferred_role']??'',
                'real_name'=>$s['real_name']??'','ref_code'=>$s['ref_code']??null,
                'status'=>$s['status']??'','account_id'=>!empty($s['account_id']) ? (int)$s['account_id'] : null,
                'created_at'=>$s['created_at']??null,
            ],
            'source_table'=>'solo_players','source_id'=>(string)$s['id'],
        ]);
        $objs++;
        if ($biz_argonar) { if (omni_link($argonar_pdo, $pid, $biz_argonar, 'BELONGS_TO')) $links++; }

        // Person → Player explicit link when registration is account-backed
        if (!empty($s['account_id'])) {
            $p_ref = omni_ref('argonar', 'person', (int)$s['account_id']);
            $p_id  = omni_id_for_ref($argonar_pdo, $p_ref);
            if ($p_id && omni_link($argonar_pdo, $p_id, $pid, 'REGISTERED_AS', ['occurred_at' => $s['created_at'] ?? null])) {
                $links++;
            }
        }
    }

    // ── Matches ──
    try {
        $matches = $argonar_pdo->query("SELECT * FROM matches")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $matches = []; }

    foreach ($matches as $m) {
        $ref = omni_ref('argonar','match',$m['id']);
        $label = ($m['team1_name'] ?? '?') . ' vs ' . ($m['team2_name'] ?? '?') .
                 ' · R' . ($m['round'] ?? '?') . ' ' . ($m['bracket_side'] ?? '');
        $mid = omni_upsert_object($argonar_pdo, [
            'ref'=>$ref,'type'=>'Match','business'=>'argonar','label'=>$label,
            'props'=>$m,
            'source_table'=>'matches','source_id'=>(string)$m['id'],
        ]);
        $objs++;

        if ($biz_argonar) { if (omni_link($argonar_pdo, $mid, $biz_argonar, 'BELONGS_TO')) $links++; }

        foreach (['team1_name','team2_name'] as $side) {
            $tname = $m[$side] ?? '';
            if (!$tname) continue;
            $q = $argonar_pdo->prepare("SELECT id FROM teams WHERE team_name = ? AND game = ? LIMIT 1");
            $q->execute([$tname, $m['game'] ?? '']);
            $tid_src = $q->fetchColumn();
            if ($tid_src) {
                $tref = omni_ref('argonar','team',$tid_src);
                $tid  = omni_id_for_ref($argonar_pdo, $tref);
                if ($tid && omni_link($argonar_pdo, $tid, $mid, 'PLAYED_IN', ['occurred_at'=>$m['scheduled_at']??($m['created_at']??null)])) {
                    $links++;
                }
            }
        }
    }

    // ── Tournament results (placement) ──
    try {
        $results = $argonar_pdo->query("SELECT * FROM tournament_results")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $results = []; }

    foreach ($results as $r) {
        $q = $argonar_pdo->prepare("SELECT id FROM teams WHERE team_name = ? AND game = ? LIMIT 1");
        $q->execute([$r['team_name'], $r['game']]);
        $tid_src = $q->fetchColumn();
        if (!$tid_src) continue;
        $tref = omni_ref('argonar','team',$tid_src);
        $tid = omni_id_for_ref($argonar_pdo, $tref);
        if ($tid && omni_link($argonar_pdo, $tid, $biz_argonar, 'PLACED', [
            'props'=>['season'=>$r['season']??'','placement'=>(int)($r['placement']??0),'prize'=>$r['prize']??''],
            'occurred_at'=>$r['created_at']??null,
        ])) {
            $links++;
        }
    }

    omni_finish_run($argonar_pdo, $run_id, $objs, $links);
} catch (Exception $e) {
    $err = $e->getMessage();
    omni_finish_run($argonar_pdo, $run_id, $objs, $links, $err);
}

if (php_sapi_name() === 'cli' || (isset($_GET['verbose']) && $_GET['verbose'])) {
    echo "[sync-tournaments] objs=$objs links=$links" . ($err ? " ERR=$err" : "") . "\n";
}
return ['pipeline'=>'sync-tournaments','objs'=>$objs,'links'=>$links,'err'=>$err];
