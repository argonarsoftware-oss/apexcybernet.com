<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/bracket_logic.php';
require_once __DIR__ . '/../includes/pusher.php';

// Account that receives the 10% prediction rake
define('HOUSE_ACCOUNT_ID', 1);

// Token-based login
if (isset($_GET['token']) && $_GET['token'] === 'argonar-admin-2026-token') {
    $_SESSION['admin_logged_in'] = true;
}

if (empty($_SESSION['admin_logged_in'])) {
    header('Location: ' . base_url('admin/'));
    exit;
}

$valid_games = [
    'dota2' => 'Dota 2',
];

// Rank tier numeric values for seeding
$rank_values = [
    'valorant'  => ['Iron' => 1, 'Bronze' => 2, 'Silver' => 3, 'Gold' => 4, 'Platinum' => 5, 'Diamond' => 6, 'Ascendant' => 7, 'Immortal' => 8, 'Radiant' => 9],
    'crossfire' => ['Bronze' => 1, 'Silver' => 2, 'Gold' => 3, 'Platinum' => 4, 'Diamond' => 5, 'Master' => 6, 'Grand Master' => 7],
    'dota2'     => ['Herald' => 1, 'Guardian' => 2, 'Crusader' => 3, 'Archon' => 4, 'Legend' => 5, 'Ancient' => 6, 'Divine' => 7, 'Immortal' => 8],
];

$game_filter = $_GET['game'] ?? '';
$message = '';
$msg_type = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Toggle waitlist lock
    if ($action === 'toggle_waitlist_lock') {
        $lk_game = $_POST['game'] ?? '';
        if (isset($valid_games[$lk_game])) {
            $new_state = !is_waitlist_locked($pdo, $lk_game);
            set_waitlist_locked($pdo, $lk_game, $new_state);
            $message = $new_state
                ? 'Waitlist auto-replace LOCKED for ' . $valid_games[$lk_game] . '. Paid waitlist teams will no longer bump unpaid main-list teams.'
                : 'Waitlist auto-replace UNLOCKED for ' . $valid_games[$lk_game] . '. Paid teams will again jump ahead of unpaid teams.';
            $msg_type = 'success';
            $game_filter = $lk_game;
        }
    }

    // Delete bracket
    if ($action === 'delete_bracket') {
        $del_game = $_POST['game'] ?? '';
        if (isset($valid_games[$del_game])) {
            $del = $pdo->prepare("DELETE FROM matches WHERE game = ?");
            $del->execute([$del_game]);
            $message = 'Bracket deleted for ' . $valid_games[$del_game] . '. (' . $del->rowCount() . ' matches removed)';
            $msg_type = 'success';
            $game_filter = $del_game;
        }
    }

    // Generate bracket
    if ($action === 'generate') {
        $gen_game = $_POST['game'] ?? '';
        if (isset($valid_games[$gen_game])) {
            // --- Step 1: Identify locked matches (WB R1 with active predictions) ---
            $locked_q = $pdo->prepare("
                SELECT DISTINCT m.id, m.match_order, m.team1_name, m.team2_name
                FROM matches m
                JOIN match_predictions p ON p.match_id = m.id AND p.status = 'active'
                WHERE m.game = ? AND m.bracket_side = 'winners' AND m.round = 1
            ");
            $locked_q->execute([$gen_game]);
            $locked_matches = $locked_q->fetchAll();
            $locked_ids = array_column($locked_matches, 'id');
            $locked_orders = []; // match_order => true
            $locked_teams = [];  // team names that are locked
            foreach ($locked_matches as $lm) {
                $locked_orders[(int)$lm['match_order']] = true;
                $locked_teams[$lm['team1_name']] = true;
                $locked_teams[$lm['team2_name']] = true;
            }
            $locked_count = count($locked_matches);

            // --- Step 2: Refund only NON-locked predictions ---
            if (!empty($locked_ids)) {
                $placeholders = implode(',', array_fill(0, count($locked_ids), '?'));
                $refund_preds = $pdo->prepare("
                    SELECT p.id, p.account_id, p.wager
                    FROM match_predictions p
                    JOIN matches m ON m.id = p.match_id
                    WHERE m.game = ? AND p.status = 'active' AND m.id NOT IN ($placeholders)
                ");
                $refund_preds->execute(array_merge([$gen_game], $locked_ids));
            } else {
                $refund_preds = $pdo->prepare("
                    SELECT p.id, p.account_id, p.wager
                    FROM match_predictions p
                    JOIN matches m ON m.id = p.match_id
                    WHERE m.game = ? AND p.status = 'active'
                ");
                $refund_preds->execute([$gen_game]);
            }
            $refunded_count = 0;
            foreach ($refund_preds->fetchAll() as $rp) {
                $pdo->prepare("UPDATE accounts SET h_coins = h_coins + ? WHERE id = ?")->execute([$rp['wager'], $rp['account_id']]);
                $pdo->prepare("INSERT INTO h_coin_transactions (account_id, type, amount, reason, ref) VALUES (?, 'credit', ?, 'admin_credit', ?)")
                    ->execute([$rp['account_id'], $rp['wager'], 'predict_refund:' . $rp['id']]);
                $rpBal = (int)$pdo->query("SELECT h_coins FROM accounts WHERE id = {$rp['account_id']}")->fetchColumn();
                hc_push($pdo, (int)$rp['account_id'], (int)$rp['wager'], 'Argonar (predict refund)', $rpBal, 'predict_refund');
                $pdo->prepare("DELETE FROM match_predictions WHERE id = ?")->execute([$rp['id']]);
                try {
                    $pdo->prepare("INSERT INTO user_notifications (account_id, title, message, icon, link) VALUES (?, ?, ?, ?, ?)")
                        ->execute([$rp['account_id'], 'Prediction Refunded', 'Your prediction of ' . $rp['wager'] . ' HC was refunded due to bracket regeneration.', 'bi-coin', base_url('predict.php')]);
                } catch (Exception $e) {}
                $refunded_count++;
            }

            // --- Step 3: Delete only NON-locked matches ---
            if (!empty($locked_ids)) {
                $placeholders = implode(',', array_fill(0, count($locked_ids), '?'));
                $del = $pdo->prepare("DELETE FROM matches WHERE game = ? AND id NOT IN ($placeholders)");
                $del->execute(array_merge([$gen_game], $locked_ids));
            } else {
                $del = $pdo->prepare("DELETE FROM matches WHERE game = ?");
                $del->execute([$gen_game]);
            }

            ensure_reserved_columns($pdo);
            // Get ALL non-reserved teams (paid + unpaid) with member ranks for seeding
            $stmt = $pdo->prepare("SELECT id, team_name, members_ranks, status, created_at FROM teams WHERE game = ? AND status IN ('approved', 'pending') AND reserved = 0");
            $stmt->execute([$gen_game]);
            $raw_teams = $stmt->fetchAll();

            if (count($raw_teams) < 2) {
                $message = 'Need at least 2 registered teams to generate a bracket.';
                $msg_type = 'danger';
            } else {
                // Calculate average rank value per team for seeding
                $game_ranks = $rank_values[$gen_game] ?? [];
                $team_seeds = [];
                foreach ($raw_teams as $rt) {
                    $avg_rank = 0;
                    if (!empty($rt['members_ranks'])) {
                        $entries = explode('|', $rt['members_ranks']);
                        $total = 0;
                        $counted = 0;
                        foreach ($entries as $entry) {
                            $parts = explode(':', $entry, 2);
                            $rank = $parts[1] ?? '';
                            if (!empty($rank) && isset($game_ranks[$rank])) {
                                $total += $game_ranks[$rank];
                                $counted++;
                            }
                        }
                        $avg_rank = $counted > 0 ? $total / $counted : 0;
                    }
                    // Organizer override: team jakolerns seeded at max rank
                    if (strtolower(trim($rt['team_name'])) === 'team jakolerns' && !empty($game_ranks)) {
                        $avg_rank = max(array_values($game_ranks));
                    }
                    $team_seeds[] = ['name' => $rt['team_name'], 'avg_rank' => $avg_rank, 'paid' => $rt['status'] === 'approved', 'registered_at' => $rt['created_at']];
                }

                // Step 1: Pick who gets in — paid first, then earliest registered
                usort($team_seeds, function($a, $b) {
                    if ($a['paid'] !== $b['paid']) return $b['paid'] - $a['paid'];
                    return $a['registered_at'] <=> $b['registered_at'];
                });

                // Cap at 12 teams
                $max_teams = 12;
                if (count($team_seeds) > $max_teams) {
                    $team_seeds = array_slice($team_seeds, 0, $max_teams);
                }

                // --- Step 4: Remove locked teams from seeding pool ---
                $unlocked_seeds = [];
                foreach ($team_seeds as $ts) {
                    if (!isset($locked_teams[$ts['name']])) {
                        $unlocked_seeds[] = $ts;
                    }
                }

                // Re-sort unlocked teams by rank for bracket seeding (strongest = seed 1)
                usort($unlocked_seeds, function($a, $b) {
                    return $b['avg_rank'] <=> $a['avg_rank'];
                });

                $count = count($team_seeds);

                // --- BYE FORMAT ---
                // Round up to next power of 2 for bracket size
                $bracket_size = 1;
                while ($bracket_size < $count) $bracket_size *= 2;
                $bye_count = $bracket_size - $count;

                // Standard seeding order for the main bracket
                function seedOrder($n) {
                    if ($n === 1) return [0];
                    $prev = seedOrder($n / 2);
                    $result = [];
                    foreach ($prev as $seed) {
                        $result[] = $seed;
                        $result[] = $n - 1 - $seed;
                    }
                    return $result;
                }

                $insert = $pdo->prepare("INSERT INTO matches (game, bracket_side, round, match_order, team1_name, team2_name, status, winner, team1_score, team2_score) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0)");

                // --- Step 5: Build R1 with locked matches preserved ---
                $total_r1_matches = $bracket_size / 2;

                // Figure out how many unlocked slots we need to fill
                $unlocked_slot_count = $total_r1_matches - $locked_count;

                // Build unlocked team list: unlocked real teams + BYEs
                $unlocked_with_byes = [];
                foreach ($unlocked_seeds as $us) {
                    $unlocked_with_byes[] = $us['name'];
                }
                for ($i = 0; $i < $bye_count; $i++) {
                    $unlocked_with_byes[] = 'BYE';
                }

                // Power-match pairing (default): strongest faces strongest.
                // $unlocked_with_byes is already sorted by avg_rank DESC, so pairing
                // index 0/1, 2/3, 4/5... yields seed 1 vs 2, 3 vs 4, etc.
                $unlocked_bracket_size = count($unlocked_with_byes);
                if ($unlocked_bracket_size > 0) {
                    $unlocked_pow2 = 1;
                    while ($unlocked_pow2 < $unlocked_bracket_size) $unlocked_pow2 *= 2;
                    while (count($unlocked_with_byes) < $unlocked_pow2) {
                        $unlocked_with_byes[] = 'BYE';
                    }
                    $unlocked_ordered = $unlocked_with_byes;
                } else {
                    $unlocked_ordered = [];
                }

                // Pair unlocked teams into matches
                $unlocked_pairs = [];
                for ($i = 0; $i < count($unlocked_ordered); $i += 2) {
                    $unlocked_pairs[] = [$unlocked_ordered[$i], $unlocked_ordered[$i + 1] ?? 'BYE'];
                }

                // Create R1 matches: skip locked slots, fill unlocked slots
                $unlocked_idx = 0;
                for ($mo = 1; $mo <= $total_r1_matches; $mo++) {
                    if (isset($locked_orders[$mo])) {
                        // Locked match already exists in DB — skip
                        continue;
                    }
                    // Fill this slot with next unlocked pair
                    if ($unlocked_idx < count($unlocked_pairs)) {
                        $t1 = $unlocked_pairs[$unlocked_idx][0];
                        $t2 = $unlocked_pairs[$unlocked_idx][1];
                        $unlocked_idx++;
                    } else {
                        $t1 = 'BYE';
                        $t2 = 'BYE';
                    }
                    if ($t1 === 'BYE' || $t2 === 'BYE') {
                        $real_team = ($t1 === 'BYE') ? $t2 : $t1;
                        if ($real_team === 'BYE') continue; // both BYE, skip
                        $insert->execute([$gen_game, 'winners', 1, $mo, $t1, $t2, 'completed', $real_team]);
                    } else {
                        $insert->execute([$gen_game, 'winners', 1, $mo, $t1, $t2, 'upcoming', '']);
                    }
                }

                // Winners bracket subsequent rounds (R2+ are always TBD)
                $matches_in_round = $bracket_size / 2;
                $round = 2;
                while ($matches_in_round > 1) {
                    $matches_in_round = $matches_in_round / 2;
                    for ($j = 1; $j <= $matches_in_round; $j++) {
                        $insert->execute([$gen_game, 'winners', $round, $j, 'TBD', 'TBD', 'pending', '']);
                    }
                    $round++;
                }
                $winners_rounds = $round - 1;

                // Auto-advance BYE winners into Round 2
                $r1_matches = $pdo->prepare("SELECT id, match_order, winner FROM matches WHERE game = ? AND bracket_side = 'winners' AND round = 1 AND status = 'completed' AND winner != ''");
                $r1_matches->execute([$gen_game]);
                foreach ($r1_matches->fetchAll() as $bye_m) {
                    $next_order = (int)ceil($bye_m['match_order'] / 2);
                    $slot = ($bye_m['match_order'] % 2 === 1) ? 'team1_name' : 'team2_name';
                    $r2 = $pdo->prepare("SELECT id FROM matches WHERE game = ? AND bracket_side = 'winners' AND round = 2 AND match_order = ?");
                    $r2->execute([$gen_game, $next_order]);
                    $r2_match = $r2->fetch();
                    if ($r2_match) {
                        $pdo->prepare("UPDATE matches SET $slot = ? WHERE id = ?")->execute([$bye_m['winner'], $r2_match['id']]);
                    }
                }
                // If both teams in a R2 match are filled (no TBD), mark as upcoming
                $r2_check = $pdo->prepare("SELECT id, team1_name, team2_name FROM matches WHERE game = ? AND bracket_side = 'winners' AND round = 2");
                $r2_check->execute([$gen_game]);
                foreach ($r2_check->fetchAll() as $r2m) {
                    if ($r2m['team1_name'] !== 'TBD' && $r2m['team2_name'] !== 'TBD') {
                        $pdo->prepare("UPDATE matches SET status = 'upcoming' WHERE id = ?")->execute([$r2m['id']]);
                    }
                }

                // --- LOSERS BRACKET ---
                $losers_rounds = max(1, ($winners_rounds - 1) * 2);
                $lr_matches = $bracket_size / 4;
                for ($lr = 1; $lr <= $losers_rounds; $lr++) {
                    $num = max(1, (int)ceil($lr_matches));
                    for ($j = 1; $j <= $num; $j++) {
                        $insert->execute([$gen_game, 'losers', $lr, $j, 'TBD', 'TBD', 'pending', '']);
                    }
                    if ($lr % 2 === 0) $lr_matches = $lr_matches / 2;
                    if ($lr_matches < 1) break;
                }

                // --- GRAND FINALS ---
                $insert->execute([$gen_game, 'grand', 1, 1, 'TBD', 'TBD', 'pending', '']);

                $bye_note = $bye_count > 0 ? " ($bye_count BYE" . ($bye_count > 1 ? 's' : '') . ")" : '';
                $refund_note = $refunded_count > 0 ? " $refunded_count prediction(s) refunded." : '';
                $locked_note = $locked_count > 0 ? " $locked_count match(es) preserved (have active predictions)." : '';
                $message = 'Bracket generated for ' . $valid_games[$gen_game] . ' with ' . $count . ' teams' . $bye_note . '!' . $refund_note . $locked_note;
                $msg_type = 'success';
                $game_filter = $gen_game;
            }
        }
    }

    // ── Generate schedule ──
    if ($action === 'generate_schedule') {
        $sched_game    = $_POST['sched_game'] ?? 'dota2';
        $start_str     = $_POST['start_datetime'] ?? '2026-05-30 11:00:00';
        $match_mins    = max(30, (int)($_POST['match_duration'] ?? 90));
        $concurrent    = max(1, (int)($_POST['concurrent'] ?? 1));

        $start_ts = strtotime($start_str);
        if (!$start_ts) $start_ts = strtotime('2026-05-30 11:00:00');

        // Load all real (non-BYE-auto) matches ordered by bracket wave
        // Wave order: WB rounds interleaved with LB rounds, then grand finals
        // Within each wave, assign slots concurrently then advance time
        $all = $pdo->prepare("
            SELECT id, bracket_side, round, match_order
            FROM matches
            WHERE game = ? AND team1_name != 'BYE' AND team2_name != 'BYE'
            ORDER BY
                CASE bracket_side
                    WHEN 'winners' THEN 1
                    WHEN 'losers'  THEN 2
                    WHEN 'grand'   THEN 3
                    ELSE 4
                END,
                round ASC, match_order ASC
        ");
        $all->execute([$sched_game]);
        $matches_to_schedule = $all->fetchAll();

        // Group into waves by (bracket_side, round)
        $waves = [];
        foreach ($matches_to_schedule as $m) {
            $key = $m['bracket_side'] . '_' . $m['round'];
            $waves[$key][] = $m;
        }

        // Interleave WB and LB waves in proper double-elim order:
        // WB R1, LB R1, WB R2, LB R2, WB R3, LB R3 ... then grand
        $wb_waves  = [];
        $lb_waves  = [];
        $gf_waves  = [];
        foreach ($waves as $key => $ms) {
            if (strpos($key, 'winners') === 0) $wb_waves[$key] = $ms;
            elseif (strpos($key, 'losers') === 0) $lb_waves[$key] = $ms;
            else $gf_waves[$key] = $ms;
        }
        ksort($wb_waves); ksort($lb_waves); ksort($gf_waves);

        $ordered_waves = [];
        $wb_list = array_values($wb_waves);
        $lb_list = array_values($lb_waves);
        $max_wb  = count($wb_list);
        $max_lb  = count($lb_list);
        for ($i = 0; $i < max($max_wb, $max_lb); $i++) {
            if (isset($wb_list[$i])) $ordered_waves[] = $wb_list[$i];
            if (isset($lb_list[$i])) $ordered_waves[] = $lb_list[$i];
        }
        foreach ($gf_waves as $gw) $ordered_waves[] = $gw;

        $upd_sched = $pdo->prepare("UPDATE matches SET scheduled_at = ?, status = 'upcoming' WHERE id = ?");
        $slot_ts   = $start_ts;

        foreach ($ordered_waves as $wave) {
            // Assign slots within this wave using concurrent lanes
            $chunk = array_chunk($wave, $concurrent);
            foreach ($chunk as $group) {
                foreach ($group as $m) {
                    $upd_sched->execute([date('Y-m-d H:i:s', $slot_ts), $m['id']]);
                }
                $slot_ts += $match_mins * 60;
            }
        }

        // Mark completed (BYE) matches as completed, upcoming otherwise already handled
        $message  = 'Schedule generated for ' . ($valid_games[$sched_game] ?? $sched_game)
                  . ' — ' . count($matches_to_schedule) . ' matches scheduled starting '
                  . date('M j, Y g:ia', $start_ts) . '.';
        $msg_type = 'success';
        $game_filter = $sched_game;
    }

    // Power-match round 1 — pair adjacent seeds so strongest faces strongest
    if ($action === 'power_match') {
        $pm_game = $_POST['game'] ?? '';
        if (!isset($valid_games[$pm_game])) {
            $message = 'Invalid game.';
            $msg_type = 'danger';
        } else {
            $r1 = $pdo->prepare("SELECT id, match_order, team1_name, team2_name, team1_score, team2_score, status, winner FROM matches WHERE game = ? AND bracket_side = 'winners' AND round = 1 ORDER BY match_order ASC");
            $r1->execute([$pm_game]);
            $r1_matches = $r1->fetchAll(PDO::FETCH_ASSOC);

            $locked = false;
            $teams_in_r1 = [];
            foreach ($r1_matches as $rm) {
                if ($rm['status'] === 'completed' || !empty($rm['winner']) || (int)$rm['team1_score'] + (int)$rm['team2_score'] > 0) {
                    $locked = true; break;
                }
                if ($rm['team1_name'] && $rm['team1_name'] !== 'BYE') $teams_in_r1[] = $rm['team1_name'];
                if ($rm['team2_name'] && $rm['team2_name'] !== 'BYE') $teams_in_r1[] = $rm['team2_name'];
            }

            if ($locked) {
                $message = 'Cannot re-pair — at least one round-1 match already has a score or winner.';
                $msg_type = 'danger';
            } elseif (count($teams_in_r1) < 2) {
                $message = 'Not enough teams in round 1 to power-match.';
                $msg_type = 'danger';
            } else {
                // Compute avg rank per team from members_ranks
                $game_ranks = $rank_values[$pm_game] ?? [];
                $teams_in_r1 = array_values(array_unique($teams_in_r1));
                $ph = implode(',', array_fill(0, count($teams_in_r1), '?'));
                $tq = $pdo->prepare("SELECT team_name, members_ranks FROM teams WHERE game = ? AND team_name IN ($ph)");
                $tq->execute(array_merge([$pm_game], $teams_in_r1));
                $by_name = [];
                foreach ($tq->fetchAll() as $t) $by_name[$t['team_name']] = $t;

                $team_seeds = [];
                foreach ($teams_in_r1 as $tn) {
                    $mr = $by_name[$tn]['members_ranks'] ?? '';
                    $entries = $mr ? explode('|', $mr) : [];
                    $sum = 0; $n = 0;
                    foreach ($entries as $e) {
                        $parts = explode(':', $e, 2);
                        $rk = $parts[1] ?? '';
                        if ($rk && isset($game_ranks[$rk])) { $sum += $game_ranks[$rk]; $n++; }
                    }
                    $team_seeds[$tn] = $n > 0 ? $sum / $n : 0;
                }
                // Organizer override (mirrors generate): jakolerns at max rank
                if (isset($team_seeds['Team Jakolerns']) && $game_ranks) $team_seeds['Team Jakolerns'] = max($game_ranks);

                // Strongest first
                arsort($team_seeds);
                $sorted = array_keys($team_seeds);

                // Pad to even count with BYE
                if (count($sorted) % 2 !== 0) $sorted[] = 'BYE';

                // Pair adjacent: 1v2, 3v4, 5v6, ...
                $pairs = [];
                for ($i = 0; $i < count($sorted); $i += 2) {
                    $pairs[] = [$sorted[$i], $sorted[$i + 1]];
                }

                // Apply to round 1 matches in match_order
                $upd = $pdo->prepare("UPDATE matches SET team1_name = ?, team2_name = ? WHERE id = ?");
                $applied = 0;
                foreach ($r1_matches as $i => $rm) {
                    if (!isset($pairs[$i])) break;
                    [$t1, $t2] = $pairs[$i];
                    $upd->execute([$t1, $t2, $rm['id']]);
                    $applied++;
                }
                $message = 'Power-matched ' . $applied . ' round-1 matches by rank. Strongest teams now face each other.';
                $msg_type = 'success';
                $game_filter = $pm_game;
            }
        }
    }

    // Reset a match (undo completion)
    if ($action === 'reset_match') {
        $match_id = (int)$_POST['match_id'];
        if ($match_id > 0) {
            $pdo->prepare("UPDATE matches SET team1_score = 0, team2_score = 0, winner = '', status = 'upcoming' WHERE id = ?")
                ->execute([$match_id]);
            $message = 'Match reset. Downstream matches may still reference the old winner — re-declare and double-check next round.';
            $msg_type = 'success';
            $game_filter = $_POST['game'] ?? $game_filter;
        }
    }

    // Update match result
    if ($action === 'update_match') {
        $match_id = (int)$_POST['match_id'];
        $team1_score = (int)$_POST['team1_score'];
        $team2_score = (int)$_POST['team2_score'];
        $winner = $_POST['winner'] ?? '';

        $scheduled_at = !empty($_POST['scheduled_at']) ? $_POST['scheduled_at'] : null;

        $status_raw = $_POST['status'] ?? '';
        if ($status_raw === '' || $status_raw === 'auto') {
            if (!empty($winner)) {
                $status = 'completed';
            } elseif ($team1_score > 0 || $team2_score > 0) {
                $status = 'live';
            } elseif ($scheduled_at && strtotime($scheduled_at) < time()) {
                $status = 'live';
            } elseif ($scheduled_at) {
                $status = 'upcoming';
            } else {
                $status = 'pending';
            }
        } else {
            $status = $status_raw;
        }

        $upd = $pdo->prepare("UPDATE matches SET team1_score = ?, team2_score = ?, winner = ?, status = ?, scheduled_at = ? WHERE id = ?");
        $upd->execute([$team1_score, $team2_score, $winner, $status, $scheduled_at, $match_id]);

        // If completed, advance winner/loser + settle predictions
        if ($status === 'completed' && $winner) {
            $match = $pdo->prepare("SELECT * FROM matches WHERE id = ?");
            $match->execute([$match_id]);
            $m = $match->fetch();

            if ($m) {
                // ── Use bracket_logic for proper cross-bracket advancement ──
                // This handles: UB winner advance, UB loser drop to LB, LB advance,
                // GF bracket reset, and marks destination matches as 'upcoming'
                $affected_ids = bracketAdvanceMatch($pdo, $m);

                // ── Futures: mark losing futures predictions (team didn't advance) as lost ──
                foreach ($affected_ids as $affected_id) {
                    $nq = $pdo->prepare("SELECT * FROM matches WHERE id = ?");
                    $nq->execute([$affected_id]);
                    $nm = $nq->fetch();
                    if ($nm && $nm['team1_name'] !== 'TBD' && $nm['team1_name'] !== ''
                        && $nm['team2_name'] !== 'TBD' && $nm['team2_name'] !== '') {
                        $futures_preds = $pdo->prepare("SELECT * FROM match_predictions WHERE match_id = ? AND status = 'active' AND is_futures = 1 AND picked_team NOT IN (?, ?)");
                        $futures_preds->execute([$affected_id, $nm['team1_name'], $nm['team2_name']]);
                        foreach ($futures_preds->fetchAll() as $fp) {
                            // Stake stays in pool — just mark lost, no refund
                            $pdo->prepare("UPDATE match_predictions SET status = 'lost' WHERE id = ?")->execute([$fp['id']]);
                            try {
                                $pdo->prepare("INSERT INTO user_notifications (account_id, title, message, icon, link) VALUES (?, ?, ?, ?, ?)")
                                    ->execute([$fp['account_id'], 'Futures Prediction Lost', 'Your futures prediction of ' . $fp['wager'] . ' HC on ' . htmlspecialchars($fp['picked_team']) . ' was lost — team did not advance to this match.', 'bi-graph-up-arrow', base_url('predict.php')]);
                            } catch (Exception $e) {}
                        }
                    }
                }

                // ── Settle match predictions ──
                $preds = $pdo->prepare("SELECT * FROM match_predictions WHERE match_id = ? AND status = 'active'");
                $preds->execute([$match_id]);
                $all_preds = $preds->fetchAll();

                $regular_preds = array_filter($all_preds, fn($p) => !$p['is_futures']);
                $futures_preds = array_filter($all_preds, fn($p) => $p['is_futures']);

                // Settle regular predictions
                if (!empty($regular_preds)) {
                    $pool_sum     = array_sum(array_column($regular_preds, 'wager'));
                    $winner_preds = array_filter($regular_preds, fn($p) => $p['picked_team'] === $winner);
                    $winner_pool  = array_sum(array_column($winner_preds, 'wager'));
                    $house_cut    = (int)round($pool_sum * 0.10);
                    $reward_pool  = $pool_sum - $house_cut;

                    foreach ($regular_preds as $pred) {
                        if ($pred['picked_team'] === $winner) {
                            $payout = $winner_pool > 0
                                ? (int)round(($pred['wager'] / $winner_pool) * $reward_pool)
                                : 0;
                            $pdo->prepare("UPDATE accounts SET h_coins = h_coins + ? WHERE id = ?")->execute([$payout, $pred['account_id']]);
                            $pdo->prepare("INSERT INTO h_coin_transactions (account_id, type, amount, reason, ref) VALUES (?, 'credit', ?, 'match_win', ?)")->execute([$pred['account_id'], $payout, (string)$match_id]);
                            $winBal = (int)$pdo->query("SELECT h_coins FROM accounts WHERE id = {$pred['account_id']}")->fetchColumn();
                            hc_push($pdo, (int)$pred['account_id'], $payout, 'Argonar (match win)', $winBal, 'match_win');
                            $pdo->prepare("UPDATE match_predictions SET status = 'won' WHERE id = ?")->execute([$pred['id']]);
                        } else {
                            $pdo->prepare("UPDATE match_predictions SET status = 'lost' WHERE id = ?")->execute([$pred['id']]);
                        }
                    }

                    if ($house_cut > 0) {
                        $pdo->prepare("UPDATE accounts SET h_coins = h_coins + ? WHERE id = ?")->execute([$house_cut, HOUSE_ACCOUNT_ID]);
                        $pdo->prepare("INSERT INTO h_coin_transactions (account_id, type, amount, reason, ref) VALUES (?, 'credit', ?, 'prediction_rake', ?)")
                            ->execute([HOUSE_ACCOUNT_ID, $house_cut, 'match:' . $match_id]);
                    }
                }

                // Settle futures predictions — pool includes all stakes (wrong-team already marked lost above)
                // Only active futures predictions remaining are those who backed one of the two actual teams
                if (!empty($futures_preds)) {
                    // Total futures pool = ALL futures stakes on this match (active + already-lost from wrong teams)
                    $all_futures_q = $pdo->prepare("SELECT wager, picked_team, account_id, id, status FROM match_predictions WHERE match_id = ? AND is_futures = 1");
                    $all_futures_q->execute([$match_id]);
                    $all_futures = $all_futures_q->fetchAll();

                    $futures_pool_sum  = array_sum(array_column($all_futures, 'wager'));
                    $futures_house_cut = (int)round($futures_pool_sum * 0.10);
                    $futures_reward    = $futures_pool_sum - $futures_house_cut;

                    $futures_winner_preds = array_filter($futures_preds, fn($p) => $p['picked_team'] === $winner);
                    $futures_winner_pool  = array_sum(array_column($futures_winner_preds, 'wager'));

                    foreach ($futures_preds as $pred) {
                        if ($pred['picked_team'] === $winner) {
                            $payout = $futures_winner_pool > 0
                                ? (int)round(($pred['wager'] / $futures_winner_pool) * $futures_reward)
                                : 0;
                            $pdo->prepare("UPDATE accounts SET h_coins = h_coins + ? WHERE id = ?")->execute([$payout, $pred['account_id']]);
                            $pdo->prepare("INSERT INTO h_coin_transactions (account_id, type, amount, reason, ref) VALUES (?, 'credit', ?, 'match_win', ?)")->execute([$pred['account_id'], $payout, 'futures:' . $match_id]);
                            $winBal = (int)$pdo->query("SELECT h_coins FROM accounts WHERE id = {$pred['account_id']}")->fetchColumn();
                            hc_push($pdo, (int)$pred['account_id'], $payout, 'Argonar (futures win)', $winBal, 'futures_win');
                            $pdo->prepare("UPDATE match_predictions SET status = 'won' WHERE id = ?")->execute([$pred['id']]);
                        } else {
                            $pdo->prepare("UPDATE match_predictions SET status = 'lost' WHERE id = ?")->execute([$pred['id']]);
                        }
                    }

                    if ($futures_house_cut > 0) {
                        $pdo->prepare("UPDATE accounts SET h_coins = h_coins + ? WHERE id = ?")->execute([$futures_house_cut, HOUSE_ACCOUNT_ID]);
                        $pdo->prepare("INSERT INTO h_coin_transactions (account_id, type, amount, reason, ref) VALUES (?, 'credit', ?, 'prediction_rake', ?)")
                            ->execute([HOUSE_ACCOUNT_ID, $futures_house_cut, 'futures:' . $match_id]);
                    }
                }
            }
        }

        $message = 'Match updated!';
        $msg_type = 'success';
        $game_filter = $_POST['game'] ?? $game_filter;
    }

    // Finalize results — uses bracket_logic standings for correct 1st-16th placements
    if ($action === 'finalize') {
        $fin_game = $_POST['game'] ?? '';
        $season = $_POST['season'] ?? 'Season 1';

        if (isset($valid_games[$fin_game])) {
            $standings = bracketStandings($pdo, $fin_game);

            if (empty($standings) || !isset($standings['1'])) {
                $message = 'Grand Finals has no winner yet. Complete the bracket first.';
                $msg_type = 'danger';
            } else {
                $del = $pdo->prepare("DELETE FROM tournament_results WHERE game = ? AND season = ?");
                $del->execute([$fin_game, $season]);

                $ins = $pdo->prepare("INSERT INTO tournament_results (game, season, placement, team_name, prize) VALUES (?, ?, ?, ?, '')");

                // Placement map: '1' => [team], '2' => [team], '5-6' => [team, team], etc.
                $placement_map = [
                    '1' => 1, '2' => 2, '3' => 3, '4' => 4,
                    '5-6' => 5, '7-8' => 7, '9-12' => 9, '13-16' => 13,
                ];

                foreach ($placement_map as $key => $start_place) {
                    if (!isset($standings[$key])) continue;
                    $place = $start_place;
                    foreach ($standings[$key] as $team) {
                        if ($team && $team !== 'TBD' && $team !== 'BYE') {
                            $ins->execute([$fin_game, $season, $place, $team]);
                            $place++;
                        }
                    }
                }

                $total = array_sum(array_map('count', $standings));
                $message = "Tournament results finalized for {$valid_games[$fin_game]} — $total placements recorded!";
                $msg_type = 'success';
            }
        }
        $game_filter = $fin_game;
    }
}

// Fetch matches for selected game
$matches = [];
$bracket_data = [];
if ($game_filter && isset($valid_games[$game_filter])) {
    $stmt = $pdo->prepare("SELECT * FROM matches WHERE game = ? ORDER BY bracket_side ASC, round ASC, match_order ASC");
    $stmt->execute([$game_filter]);
    $matches = $stmt->fetchAll();
    foreach ($matches as $m) {
        $side = $m['bracket_side'] ?? 'winners';
        $bracket_data[$side][$m['round']][] = $m;
    }
}
$rounds = $bracket_data; // for empty check

// Count approved teams per game
$team_counts = [];
foreach ($valid_games as $slug => $name) {
    $c = $pdo->prepare("SELECT COUNT(*) FROM teams WHERE game = ? AND status = 'approved'");
    $c->execute([$slug]);
    $team_counts[$slug] = $c->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bracket Management — Argonar Tournament</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= base_url('css/app.css') ?>">
</head>
<body>

<div class="admin-container">
    <div class="admin-header">
        <div>
            <h1><i class="bi bi-diagram-3"></i> Bracket Management</h1>
        </div>
        <div class="admin-header-actions">
            <a href="<?= base_url('admin/') ?>" class="btn-back-site"><i class="bi bi-arrow-left"></i> Dashboard</a>
            <a href="<?= base_url('admin/tools.php') ?>" class="btn-back-site" style="border-color:rgba(167,139,250,0.35); color:#a78bfa;"><i class="bi bi-tools"></i> Admin Tools</a>
            <a href="<?= base_url() ?>" class="btn-back-site"><i class="bi bi-house"></i> Site</a>
            <a href="<?= base_url('admin/logout.php') ?>" class="btn-logout"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert-custom alert-<?= $msg_type ?>" style="margin-bottom:1.5rem;">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Game Filter Tabs -->
    <div class="filter-tabs">
        <?php foreach ($valid_games as $slug => $name): ?>
            <a href="<?= base_url('admin/brackets.php?game=' . $slug) ?>" class="filter-tab <?= $game_filter === $slug ? 'active' : '' ?>">
                <?= $name ?> (<?= $team_counts[$slug] ?> teams)
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($game_filter && isset($valid_games[$game_filter])): ?>
        <!-- Waitlist Auto-Replace Lock -->
        <?php $wl_locked = is_waitlist_locked($pdo, $game_filter); ?>
        <div class="admin-section" style="<?= $wl_locked ? 'border-left:3px solid #fbbf24;' : '' ?>">
            <h2><i class="bi <?= $wl_locked ? 'bi-lock-fill' : 'bi-unlock' ?>"></i> Waitlist Auto-Replace — <?= $valid_games[$game_filter] ?></h2>
            <p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:1rem;">
                <?php if ($wl_locked): ?>
                    <strong style="color:#fbbf24;">LOCKED.</strong>
                    Registered participants are frozen in their current slots. A paid waitlist team will NOT bump an unpaid main-list team — slots are held in registration order until you unlock.
                <?php else: ?>
                    <strong style="color:var(--success);">UNLOCKED.</strong>
                    If a waitlist team pays, they automatically jump ahead of any unpaid main-list team. Lock this once you've finalized who's in and don't want new payments reshuffling the field.
                <?php endif; ?>
            </p>
            <form method="POST" style="display:inline;" onsubmit="return confirm('<?= $wl_locked ? 'Unlock auto-replace? Paid waitlist teams will immediately be allowed to bump unpaid main-list teams.' : 'Lock auto-replace? Main-list slots will freeze in their current order (registration date). Paid waitlist teams stay in waitlist until unlocked.' ?>');">
                <input type="hidden" name="action" value="toggle_waitlist_lock">
                <input type="hidden" name="game" value="<?= $game_filter ?>">
                <button type="submit" style="background:<?= $wl_locked ? 'linear-gradient(135deg,#22c55e,#16a34a)' : 'linear-gradient(135deg,#fbbf24,#d97706)' ?>; color:#0b0b0b; border:none; padding:0.6rem 1.5rem; font-size:0.85rem; font-weight:800; border-radius:8px; cursor:pointer; font-family:inherit; display:inline-flex; align-items:center; gap:0.4rem;">
                    <i class="bi <?= $wl_locked ? 'bi-unlock' : 'bi-lock-fill' ?>"></i>
                    <?= $wl_locked ? 'Unlock Auto-Replace' : 'Lock Auto-Replace' ?>
                </button>
            </form>
        </div>

        <!-- Generate Bracket -->
        <div class="admin-section">
            <h2><i class="bi bi-shuffle"></i> Generate Bracket — <?= $valid_games[$game_filter] ?></h2>
            <p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:1rem;">
                This will seed all registered teams (paid first, then unpaid) by average rank into a double-elimination bracket (strongest vs weakest in round 1).
                <?php if (!empty($rounds)): ?>
                    <strong style="color:var(--warning);">Warning: existing bracket will be replaced!</strong>
                <?php endif; ?>
            </p>

            <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                <form method="POST" style="display:inline;" onsubmit="return confirm('Generate new bracket? This will delete any existing matches for this game.');">
                    <input type="hidden" name="action" value="generate">
                    <input type="hidden" name="game" value="<?= $game_filter ?>">
                    <button type="submit" class="btn-submit" style="width:auto; padding:0.6rem 1.5rem; margin-top:0;">
                        <i class="bi bi-shuffle"></i> Generate Bracket
                    </button>
                </form>
                <?php if (!empty($bracket_data)):
                    // Can we power-match? only if round 1 has no scores / winners yet
                    $can_power = true;
                    if (!empty($bracket_data['winners'][1])) {
                        foreach ($bracket_data['winners'][1] as $_rm) {
                            if ($_rm['status'] === 'completed' || !empty($_rm['winner']) || (int)$_rm['team1_score'] + (int)$_rm['team2_score'] > 0) { $can_power = false; break; }
                        }
                    } else { $can_power = false; }
                ?>
                    <?php if ($can_power): ?>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Re-pair round 1 using current ranks? Useful if team ranks changed after generation. Scores untouched (none have started).');">
                        <input type="hidden" name="action" value="power_match">
                        <input type="hidden" name="game" value="<?= $game_filter ?>">
                        <button type="submit" style="background:linear-gradient(135deg,#fbbf24,#d97706);color:#1f1300;border:none;padding:0.6rem 1.5rem;font-size:0.85rem;font-weight:800;border-radius:8px;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:0.4rem;">
                            <i class="bi bi-arrow-repeat"></i> Re-pair by rank
                        </button>
                    </form>
                    <?php endif; ?>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete ALL brackets for <?= $valid_games[$game_filter] ?>? This cannot be undone.');">
                        <input type="hidden" name="action" value="delete_bracket">
                        <input type="hidden" name="game" value="<?= $game_filter ?>">
                        <button type="submit" class="btn-delete" style="padding:0.6rem 1.5rem; font-size:0.85rem;">
                            <i class="bi bi-trash"></i> Delete Bracket
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Generate Schedule -->
        <?php if (!empty($bracket_data)): ?>
        <div class="admin-section">
            <h2><i class="bi bi-calendar-event"></i> Generate Match Schedule</h2>
            <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:1rem;">
                Assigns start times to every match in wave order (WB R1 → LB R1 → WB R2 → … → Grand Finals).
                Overwrites any existing scheduled times. BYE auto-advances are skipped.
            </p>
            <form method="POST">
                <input type="hidden" name="action" value="generate_schedule">
                <input type="hidden" name="sched_game" value="<?= $game_filter ?>">
                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:0.75rem; margin-bottom:1rem; max-width:560px;">
                    <div>
                        <label style="font-size:0.75rem; color:var(--text-muted); display:block; margin-bottom:0.25rem;">Start date &amp; time</label>
                        <input type="datetime-local" name="start_datetime" class="form-control" style="font-size:0.85rem; padding:0.4rem 0.6rem;"
                               value="2026-05-30T11:00" required>
                    </div>
                    <div>
                        <label style="font-size:0.75rem; color:var(--text-muted); display:block; margin-bottom:0.25rem;">Duration per match (min)</label>
                        <input type="number" name="match_duration" class="form-control" style="font-size:0.85rem; padding:0.4rem 0.6rem;"
                               value="90" min="30" max="300">
                    </div>
                    <div>
                        <label style="font-size:0.75rem; color:var(--text-muted); display:block; margin-bottom:0.25rem;">Concurrent matches</label>
                        <input type="number" name="concurrent" class="form-control" style="font-size:0.85rem; padding:0.4rem 0.6rem;"
                               value="1" min="1" max="4">
                    </div>
                </div>
                <button type="submit" class="btn-submit" style="width:auto; padding:0.55rem 1.4rem; margin-top:0;">
                    <i class="bi bi-calendar-check"></i> Generate Schedule
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Edit Matches -->
        <?php if (!empty($bracket_data)): ?>
            <?php
            $side_labels = ['winners' => 'Winners Bracket', 'losers' => 'Losers Bracket', 'grand' => 'Grand Finals'];
            $side_icons = ['winners' => 'bi-trophy', 'losers' => 'bi-arrow-repeat', 'grand' => 'bi-star-fill'];
            ?>
            <?php foreach (['winners', 'losers', 'grand'] as $side):
                if (empty($bracket_data[$side])) continue;
            ?>
            <div class="admin-section">
                <h2><i class="bi <?= $side_icons[$side] ?>"></i> <?= $side_labels[$side] ?></h2>
                <?php foreach ($bracket_data[$side] as $round_num => $round_matches):
                    $round_label = bracketRoundName($side, $round_num);
                    $round_format = bracketMatchFormat($side, $round_num);
                ?>
                    <h3 style="font-size:1rem; font-weight:700; color:var(--accent-light); margin:1.5rem 0 0.75rem; display:flex; align-items:center; gap:0.5rem;">
                        <?= $round_label ?>
                        <span style="font-size:0.7rem; font-weight:700; background:rgba(124,58,237,0.15); color:var(--accent-light); padding:0.15rem 0.5rem; border-radius:4px;"><?= $round_format ?></span>
                        <?php
                        // Show loser destination hint for UB rounds
                        $loser_hint = '';
                        if ($side === 'winners') {
                            $dest = bracketLoserDest('winners', $round_num, 1);
                            if ($dest) {
                                $loser_hint = 'Losers → ' . bracketRoundName($dest[0], $dest[1]);
                            }
                        }
                        if ($loser_hint): ?>
                            <span style="font-size:0.65rem; color:var(--warning); font-weight:600; margin-left:auto;"><?= $loser_hint ?></span>
                        <?php endif; ?>
                    </h3>
                    <div class="mc-grid">
                        <?php foreach ($round_matches as $m):
                            $t1_real = $m['team1_name'] && $m['team1_name'] !== 'TBD' && $m['team1_name'] !== 'BYE';
                            $t2_real = $m['team2_name'] && $m['team2_name'] !== 'TBD' && $m['team2_name'] !== 'BYE';
                            $both_ready = $t1_real && $t2_real;
                            $is_done = $m['status'] === 'completed';
                            $is_live = $m['status'] === 'live';
                            $win_score = $round_format === 'BO5' ? 3 : ($round_format === 'BO3' ? 2 : 1);
                            $t1_is_winner = $is_done && $m['winner'] === $m['team1_name'];
                            $t2_is_winner = $is_done && $m['winner'] === $m['team2_name'];
                        ?>
                            <div class="mc mc-<?= $m['status'] ?>">
                                <div class="mc-head">
                                    <span class="mc-num">#<?= $m['match_order'] ?></span>
                                    <span class="mc-format"><?= $round_format ?></span>
                                    <?php if ($is_live): ?>
                                        <span class="mc-pill mc-pill-live"><span class="live-dot"></span> LIVE</span>
                                    <?php elseif ($is_done): ?>
                                        <span class="mc-pill mc-pill-done"><i class="bi bi-check-circle-fill"></i> Done</span>
                                    <?php elseif ($m['status'] === 'upcoming'): ?>
                                        <span class="mc-pill mc-pill-upcoming">Upcoming</span>
                                    <?php else: ?>
                                        <span class="mc-pill mc-pill-pending">Pending</span>
                                    <?php endif; ?>
                                    <?php if ($m['scheduled_at']): ?>
                                        <span class="mc-time"><i class="bi bi-clock"></i> <?= date('M j, g:ia', strtotime($m['scheduled_at'])) ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="mc-teams">
                                    <?php // Team 1 slot ?>
                                    <?php if (!$t1_real || !$both_ready || $is_done): ?>
                                        <div class="mc-team <?= $t1_is_winner ? 'mc-win' : ($is_done && $t2_is_winner ? 'mc-lose' : ($t1_real ? '' : 'mc-tbd')) ?>">
                                            <span class="mc-team-name"><?= htmlspecialchars($m['team1_name'] ?: 'TBD') ?></span>
                                            <?php if ($is_done): ?><span class="mc-score"><?= (int)$m['team1_score'] ?></span><?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <form method="POST" class="mc-team-form" onsubmit="return confirm('Declare <?= htmlspecialchars($m['team1_name'], ENT_QUOTES) ?> as winner (<?= $win_score ?>-0)? This auto-advances them to the next round.');">
                                            <input type="hidden" name="action" value="update_match">
                                            <input type="hidden" name="match_id" value="<?= $m['id'] ?>">
                                            <input type="hidden" name="game" value="<?= $game_filter ?>">
                                            <input type="hidden" name="winner" value="<?= htmlspecialchars($m['team1_name']) ?>">
                                            <input type="hidden" name="status" value="completed">
                                            <input type="hidden" name="team1_score" value="<?= $win_score ?>">
                                            <input type="hidden" name="team2_score" value="0">
                                            <button type="submit" class="mc-team mc-pick">
                                                <span class="mc-team-name"><?= htmlspecialchars($m['team1_name']) ?></span>
                                                <span class="mc-pick-hint">Click to win</span>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <span class="mc-vs">vs</span>

                                    <?php // Team 2 slot ?>
                                    <?php if (!$t2_real || !$both_ready || $is_done): ?>
                                        <div class="mc-team <?= $t2_is_winner ? 'mc-win' : ($is_done && $t1_is_winner ? 'mc-lose' : ($t2_real ? '' : 'mc-tbd')) ?>">
                                            <span class="mc-team-name"><?= htmlspecialchars($m['team2_name'] ?: 'TBD') ?></span>
                                            <?php if ($is_done): ?><span class="mc-score"><?= (int)$m['team2_score'] ?></span><?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <form method="POST" class="mc-team-form" onsubmit="return confirm('Declare <?= htmlspecialchars($m['team2_name'], ENT_QUOTES) ?> as winner (<?= $win_score ?>-0)? This auto-advances them to the next round.');">
                                            <input type="hidden" name="action" value="update_match">
                                            <input type="hidden" name="match_id" value="<?= $m['id'] ?>">
                                            <input type="hidden" name="game" value="<?= $game_filter ?>">
                                            <input type="hidden" name="winner" value="<?= htmlspecialchars($m['team2_name']) ?>">
                                            <input type="hidden" name="status" value="completed">
                                            <input type="hidden" name="team1_score" value="0">
                                            <input type="hidden" name="team2_score" value="<?= $win_score ?>">
                                            <button type="submit" class="mc-team mc-pick">
                                                <span class="mc-team-name"><?= htmlspecialchars($m['team2_name']) ?></span>
                                                <span class="mc-pick-hint">Click to win</span>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>

                                <details class="mc-details">
                                    <summary><i class="bi bi-sliders"></i> Edit details</summary>
                                    <form method="POST" class="mc-edit-form">
                                        <input type="hidden" name="action" value="update_match">
                                        <input type="hidden" name="match_id" value="<?= $m['id'] ?>">
                                        <input type="hidden" name="game" value="<?= $game_filter ?>">
                                        <input type="hidden" name="status" value="auto">
                                        <div class="mc-edit-row">
                                            <label><?= htmlspecialchars($m['team1_name']) ?: 'Team 1' ?> score</label>
                                            <input type="number" name="team1_score" value="<?= $m['team1_score'] ?>" min="0">
                                        </div>
                                        <div class="mc-edit-row">
                                            <label><?= htmlspecialchars($m['team2_name']) ?: 'Team 2' ?> score</label>
                                            <input type="number" name="team2_score" value="<?= $m['team2_score'] ?>" min="0">
                                        </div>
                                        <div class="mc-edit-row">
                                            <label>Winner</label>
                                            <select name="winner">
                                                <option value="">— None —</option>
                                                <?php if ($t1_real): ?>
                                                    <option value="<?= htmlspecialchars($m['team1_name']) ?>" <?= $m['winner'] === $m['team1_name'] ? 'selected' : '' ?>><?= htmlspecialchars($m['team1_name']) ?></option>
                                                <?php endif; ?>
                                                <?php if ($t2_real): ?>
                                                    <option value="<?= htmlspecialchars($m['team2_name']) ?>" <?= $m['winner'] === $m['team2_name'] ? 'selected' : '' ?>><?= htmlspecialchars($m['team2_name']) ?></option>
                                                <?php endif; ?>
                                            </select>
                                        </div>
                                        <div class="mc-edit-row">
                                            <label>Schedule</label>
                                            <input type="datetime-local" name="scheduled_at" value="<?= $m['scheduled_at'] ? date('Y-m-d\TH:i', strtotime($m['scheduled_at'])) : '' ?>">
                                        </div>
                                        <div class="mc-edit-actions">
                                            <button type="submit" class="mc-save">
                                                <i class="bi bi-check-lg"></i> Save
                                            </button>
                                            <?php if ($is_done): ?>
                                                <button type="submit" formaction="" formmethod="POST" class="mc-reset" onclick="this.form.querySelector('[name=action]').value='reset_match'; return confirm('Reset this match? Scores and winner will clear. Downstream matches may still reference the old winner — re-check the next round.');">
                                                    <i class="bi bi-arrow-counterclockwise"></i> Reset match
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </form>
                                </details>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>

            <!-- Standings -->
            <?php
            $standings = bracketStandings($pdo, $game_filter);
            if (!empty($standings)): ?>
            <div class="admin-section">
                <h2><i class="bi bi-bar-chart-fill"></i> Current Standings</h2>
                <div style="display:grid; gap:0.35rem; max-width:500px;">
                    <?php
                    $placement_labels = [
                        '1' => ['1st', '#fbbf24', 'bi-trophy-fill'],
                        '2' => ['2nd', '#c0c0c0', 'bi-trophy'],
                        '3' => ['3rd', '#cd7f32', 'bi-trophy'],
                        '4' => ['4th', 'var(--text-muted)', 'bi-dash'],
                        '5-6' => ['5th-6th', 'var(--text-muted)', 'bi-dash'],
                        '7-8' => ['7th-8th', 'var(--text-muted)', 'bi-dash'],
                        '9-12' => ['9th-12th', 'var(--text-muted)', 'bi-dash'],
                        '13-16' => ['13th-16th', 'var(--text-muted)', 'bi-dash'],
                    ];
                    foreach ($placement_labels as $key => [$label, $color, $icon]):
                        if (!isset($standings[$key])) continue;
                        $teams_str = implode(', ', array_map('htmlspecialchars', $standings[$key]));
                    ?>
                        <div style="display:flex; align-items:center; gap:0.6rem; padding:0.4rem 0.75rem; background:var(--bg-dark); border:1px solid var(--border); border-radius:8px;">
                            <span style="width:5rem; font-size:0.75rem; font-weight:800; color:<?= $color ?>;"><i class="bi <?= $icon ?>"></i> <?= $label ?></span>
                            <span style="font-size:0.82rem; font-weight:600;"><?= $teams_str ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Finalize Results -->
            <div class="admin-section">
                <h2><i class="bi bi-trophy"></i> Finalize Tournament Results</h2>
                <p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:1rem;">
                    Creates leaderboard entries from the bracket. The finals winner must be decided first.
                </p>
                <form method="POST" onsubmit="return confirm('Finalize results? This will create/replace leaderboard entries.');">
                    <input type="hidden" name="action" value="finalize">
                    <input type="hidden" name="game" value="<?= $game_filter ?>">
                    <div style="display:flex; gap:1rem; align-items:end; flex-wrap:wrap;">
                        <div>
                            <label class="form-label">Season</label>
                            <input type="text" name="season" value="Season 1" class="form-control" style="width:200px;">
                        </div>
                        <button type="submit" class="btn-submit" style="width:auto; padding:0.6rem 1.5rem; margin-top:0;">
                            <i class="bi bi-trophy"></i> Finalize Results
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="reg-card" style="text-align:center; padding:3rem 2rem;">
            <i class="bi bi-hand-index" style="font-size:2.5rem; color:var(--accent-light); display:block; margin-bottom:1rem;"></i>
            <h3>Select a Game</h3>
            <p style="color:var(--text-muted);">Choose a game tab above to manage its bracket.</p>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
