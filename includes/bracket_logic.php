<?php
/**
 * 16-Team Double Elimination Bracket Logic
 * =========================================
 *
 * BRACKET STRUCTURE:
 *
 *   Upper Bracket (4 rounds, 15 matches):
 *     UB R1:  8 matches  (16 teams → 8 winners, 8 losers drop to LB R1)     BO1
 *     UB QF:  4 matches  (8 → 4 winners, 4 losers drop to LB R2)            BO1
 *     UB SF:  2 matches  (4 → 2 winners, 2 losers drop to LB R4)            BO3
 *     UB F:   1 match    (2 → 1 winner to GF, 1 loser drops to LB Final)    BO3
 *
 *   Lower Bracket (6 rounds, 14 matches):
 *     LB R1:  4 matches  (8 UB R1 losers paired → 4 winners, 4 eliminated)  BO1
 *     LB R2:  4 matches  (LB R1 winners vs UB QF losers → 4 win, 4 elim)   BO1
 *     LB R3:  2 matches  (LB R2 winners paired → 2 winners, 2 eliminated)   BO1
 *     LB R4:  2 matches  (LB R3 winners vs UB SF losers → 2 win, 2 elim)   BO1
 *     LB SF:  1 match    (LB R4 winners → 1 winner, 1 eliminated)           BO1
 *     LB F:   1 match    (LB SF winner vs UB Final loser → 1 win, 1 elim)  BO3
 *
 *   Grand Finals (1-2 matches):
 *     GF:     UB winner (team1) vs LB winner (team2)                         BO5
 *     Reset:  Created only if LB winner wins GF (bracket reset)              BO5
 *
 *   Total: 30 matches + optional 1 reset = 30-31 matches
 *
 * LOSER DROP MAPPING (with anti-rematch flip):
 *
 *   UB R1 losers → LB R1 (adjacent pairs, no flip needed):
 *     UB R1 M1 loser → LB R1 M1 team1    UB R1 M2 loser → LB R1 M1 team2
 *     UB R1 M3 loser → LB R1 M2 team1    UB R1 M4 loser → LB R1 M2 team2
 *     UB R1 M5 loser → LB R1 M3 team1    UB R1 M6 loser → LB R1 M3 team2
 *     UB R1 M7 loser → LB R1 M4 team1    UB R1 M8 loser → LB R1 M4 team2
 *
 *   UB QF losers → LB R2 team2 (FLIPPED to avoid rematches):
 *     UB QF M1 loser → LB R2 M4 team2
 *     UB QF M2 loser → LB R2 M3 team2
 *     UB QF M3 loser → LB R2 M2 team2
 *     UB QF M4 loser → LB R2 M1 team2
 *
 *   UB SF losers → LB R4 team2 (same order, no flip):
 *     UB SF M1 loser → LB R4 M1 team2
 *     UB SF M2 loser → LB R4 M2 team2
 *
 *   UB Final loser → LB Final (R6) M1 team2
 *
 * WINNER ADVANCEMENT:
 *
 *   UB R1-R3: winner → next UB round, ceil(order/2), odd=team1/even=team2
 *   UB R4:    winner → Grand Finals M1 team1
 *   LB R1:    winner → LB R2 same order, team1
 *   LB R2:    winner → LB R3 ceil(order/2), odd=team1/even=team2
 *   LB R3:    winner → LB R4 same order, team1
 *   LB R4:    winner → LB R5 (SF) M1, M1=team1/M2=team2
 *   LB R5:    winner → LB R6 (Final) M1 team1
 *   LB R6:    winner → Grand Finals M1 team2
 *   GF:       winner = champion (or creates reset)
 *
 * PLACEMENTS:
 *   1st:      Grand Finals winner
 *   2nd:      Grand Finals loser
 *   3rd:      LB Final (R6) loser
 *   4th:      LB Semifinal (R5) loser
 *   5th-6th:  LB R4 losers (2 teams)
 *   7th-8th:  LB R3 losers (2 teams)
 *   9th-12th: LB R2 losers (4 teams)
 *   13th-16th: LB R1 losers (4 teams)
 *
 * MATCH FORMAT RATIONALE:
 *   - UB R1 & UB QF: BO1 — quick single games to thin the field early
 *   - UB SF & UB Final: BO3 — meaningful series for higher stakes
 *   - LB R1-R5: BO1 — fast-paced lower bracket, one chance to survive
 *   - LB Final: BO3 — last chance before Grand Finals deserves a series
 *   - Grand Finals (& Reset): BO5 — ultimate showdown, maximum drama
 */

// ──────────────────────────────────────────────
// Constants
// ──────────────────────────────────────────────

const BRACKET_ROUND_NAMES = [
    'winners' => [
        1 => 'UB Round 1',
        2 => 'UB Quarterfinals',
        3 => 'UB Semifinals',
        4 => 'UB Final',
    ],
    'losers' => [
        1 => 'LB Round 1',
        2 => 'LB Round 2',
        3 => 'LB Round 3',
        4 => 'LB Round 4',
        5 => 'LB Semifinal',
        6 => 'LB Final',
    ],
    'grand' => [
        1 => 'Grand Finals',
        2 => 'Grand Finals Reset',
    ],
];

const BRACKET_MATCH_FORMATS = [
    'winners' => [1 => 'BO1', 2 => 'BO1', 3 => 'BO3', 4 => 'BO3'],
    'losers'  => [1 => 'BO1', 2 => 'BO1', 3 => 'BO1', 4 => 'BO1', 5 => 'BO3', 6 => 'BO3'],
    'grand'   => [1 => 'BO5', 2 => 'BO5'],
];

// Match counts per round (for validation)
const BRACKET_MATCH_COUNTS = [
    'winners' => [1 => 8, 2 => 4, 3 => 2, 4 => 1],
    'losers'  => [1 => 4, 2 => 4, 3 => 2, 4 => 2, 5 => 1, 6 => 1],
    'grand'   => [1 => 1],
];

// ──────────────────────────────────────────────
// Lookup helpers
// ──────────────────────────────────────────────

function bracketRoundName(string $side, int $round): string {
    return BRACKET_ROUND_NAMES[$side][$round] ?? ucfirst($side) . " R$round";
}

function bracketMatchFormat(string $side, int $round): string {
    return BRACKET_MATCH_FORMATS[$side][$round] ?? 'BO1';
}

// ──────────────────────────────────────────────
// Winner destination
// ──────────────────────────────────────────────

/**
 * Where does the winner of this match advance to?
 * @return array{0:string,1:int,2:int,3:string}|null  [bracket_side, round, match_order, slot] or null = champion
 */
function bracketWinnerDest(string $side, int $round, int $order): ?array {
    if ($side === 'winners') {
        if ($round >= 1 && $round <= 3) {
            return ['winners', $round + 1, (int)ceil($order / 2), ($order % 2 === 1) ? 'team1_name' : 'team2_name'];
        }
        if ($round === 4) {
            return ['grand', 1, 1, 'team1_name'];
        }
    }

    if ($side === 'losers') {
        switch ($round) {
            case 1: return ['losers', 2, $order, 'team1_name'];
            case 2: return ['losers', 3, (int)ceil($order / 2), ($order % 2 === 1) ? 'team1_name' : 'team2_name'];
            case 3: return ['losers', 4, $order, 'team1_name'];
            case 4: return ['losers', 5, 1, ($order === 1) ? 'team1_name' : 'team2_name'];
            case 5: return ['losers', 6, 1, 'team1_name'];
            case 6: return ['grand', 1, 1, 'team2_name'];
        }
    }

    // Grand finals winner = champion (no further advancement)
    return null;
}

// ──────────────────────────────────────────────
// Loser destination (UB → LB drops)
// ──────────────────────────────────────────────

/**
 * Where does the loser of this match drop to?
 * Only UB matches send losers to LB. LB/GF losers are eliminated.
 * @return array{0:string,1:int,2:int,3:string}|null  [bracket_side, round, match_order, slot] or null = eliminated
 */
function bracketLoserDest(string $side, int $round, int $order): ?array {
    if ($side !== 'winners') return null;

    switch ($round) {
        case 1:
            // UB R1 losers → LB R1 (adjacent pairs)
            $lb_order = (int)ceil($order / 2);
            $lb_slot = ($order % 2 === 1) ? 'team1_name' : 'team2_name';
            return ['losers', 1, $lb_order, $lb_slot];

        case 2:
            // UB QF losers → LB R2 (FLIPPED: M1→M4, M2→M3, M3→M2, M4→M1)
            $lb_order = 5 - $order; // 1→4, 2→3, 3→2, 4→1
            return ['losers', 2, $lb_order, 'team2_name'];

        case 3:
            // UB SF losers → LB R4 (same order, no flip)
            return ['losers', 4, $order, 'team2_name'];

        case 4:
            // UB Final loser → LB Final (R6)
            return ['losers', 6, 1, 'team2_name'];
    }

    return null;
}

// ──────────────────────────────────────────────
// Full match advancement
// ──────────────────────────────────────────────

/**
 * After a match is completed, advance the winner forward and drop the loser (if UB).
 * Also handles Grand Finals bracket reset creation.
 * @return int[] IDs of destination matches that were updated
 */
function bracketAdvanceMatch(PDO $pdo, array $match): array {
    $affected = [];
    $winner = $match['winner'];
    $loser = ($winner === $match['team1_name']) ? $match['team2_name'] : $match['team1_name'];
    $side = $match['bracket_side'];
    $round = (int)$match['round'];
    $order = (int)$match['match_order'];
    $game = $match['game'];

    // ── Advance winner ──
    $dest = bracketWinnerDest($side, $round, $order);
    if ($dest) {
        $affected = array_merge($affected, bracketPlaceTeam($pdo, $game, $dest, $winner));
    }

    // ── Drop loser to LB (only from UB, skip BYE/TBD) ──
    if ($loser && $loser !== 'TBD' && $loser !== 'BYE' && $loser !== '') {
        $loser_dest = bracketLoserDest($side, $round, $order);
        if ($loser_dest) {
            $affected = array_merge($affected, bracketPlaceTeam($pdo, $game, $loser_dest, $loser));
        }
    }

    // ── Mark destination matches as 'upcoming' when both teams are known ──
    foreach (array_unique($affected) as $mid) {
        $check = $pdo->prepare("SELECT team1_name, team2_name, status FROM matches WHERE id = ?");
        $check->execute([$mid]);
        $cm = $check->fetch();
        if ($cm && $cm['status'] === 'pending'
            && $cm['team1_name'] !== 'TBD' && $cm['team1_name'] !== ''
            && $cm['team2_name'] !== 'TBD' && $cm['team2_name'] !== '') {
            $pdo->prepare("UPDATE matches SET status = 'upcoming' WHERE id = ?")->execute([$mid]);
        }
    }

    // ── Grand Finals bracket reset ──
    if ($side === 'grand' && $round === 1 && $winner) {
        // team1 = UB winner (undefeated), team2 = LB winner (1 loss)
        // If LB winner (team2) wins → bracket reset needed
        if ($winner === $match['team2_name']) {
            $exists = $pdo->prepare("SELECT id FROM matches WHERE game = ? AND bracket_side = 'grand' AND round = 2");
            $exists->execute([$game]);
            if (!$exists->fetch()) {
                $pdo->prepare("INSERT INTO matches (game, bracket_side, round, match_order, team1_name, team2_name, status, winner, team1_score, team2_score) VALUES (?, 'grand', 2, 1, ?, ?, 'upcoming', '', 0, 0)")
                    ->execute([$game, $match['team2_name'], $match['team1_name']]);
            }
        }
    }

    return $affected;
}

/**
 * Place a team into a destination match slot.
 * @return int[] IDs of updated matches
 */
function bracketPlaceTeam(PDO $pdo, string $game, array $dest, string $team): array {
    [$dest_side, $dest_round, $dest_order, $dest_slot] = $dest;
    $stmt = $pdo->prepare("SELECT id FROM matches WHERE game = ? AND bracket_side = ? AND round = ? AND match_order = ?");
    $stmt->execute([$game, $dest_side, $dest_round, $dest_order]);
    $dest_match = $stmt->fetch();
    if ($dest_match) {
        $pdo->prepare("UPDATE matches SET $dest_slot = ? WHERE id = ?")->execute([$team, $dest_match['id']]);
        return [$dest_match['id']];
    }
    return [];
}

// ──────────────────────────────────────────────
// Standings
// ──────────────────────────────────────────────

/**
 * Compute final standings from 1st to 16th based on elimination round.
 * @return array  Keyed by placement string ('1','2','3','4','5-6','7-8','9-12','13-16')
 *                Values are arrays of team name strings.
 */
function bracketStandings(PDO $pdo, string $game): array {
    $standings = [];

    $stmt = $pdo->prepare("SELECT * FROM matches WHERE game = ? AND status = 'completed' ORDER BY bracket_side, round, match_order");
    $stmt->execute([$game]);
    $matches = $stmt->fetchAll();

    $by = [];
    foreach ($matches as $m) {
        $by[$m['bracket_side']][$m['round']][] = $m;
    }

    $getLoser = function($m) {
        if (!$m['winner']) return null;
        return ($m['winner'] === $m['team1_name']) ? $m['team2_name'] : $m['team1_name'];
    };

    // 1st & 2nd from Grand Finals (check reset first)
    $gf = $by['grand'][2][0] ?? $by['grand'][1][0] ?? null;
    if ($gf && $gf['winner']) {
        $standings['1'] = [$gf['winner']];
        $standings['2'] = [$getLoser($gf)];
    }

    // 3rd: LB Final (R6) loser
    foreach ($by['losers'][6] ?? [] as $m) {
        $l = $getLoser($m);
        if ($l) $standings['3'] = [$l];
    }

    // 4th: LB SF (R5) loser
    foreach ($by['losers'][5] ?? [] as $m) {
        $l = $getLoser($m);
        if ($l) $standings['4'] = [$l];
    }

    // 5th-6th: LB R4 losers
    $teams = [];
    foreach ($by['losers'][4] ?? [] as $m) { $l = $getLoser($m); if ($l) $teams[] = $l; }
    if ($teams) $standings['5-6'] = $teams;

    // 7th-8th: LB R3 losers
    $teams = [];
    foreach ($by['losers'][3] ?? [] as $m) { $l = $getLoser($m); if ($l) $teams[] = $l; }
    if ($teams) $standings['7-8'] = $teams;

    // 9th-12th: LB R2 losers
    $teams = [];
    foreach ($by['losers'][2] ?? [] as $m) { $l = $getLoser($m); if ($l) $teams[] = $l; }
    if ($teams) $standings['9-12'] = $teams;

    // 13th-16th: LB R1 losers
    $teams = [];
    foreach ($by['losers'][1] ?? [] as $m) { $l = $getLoser($m); if ($l) $teams[] = $l; }
    if ($teams) $standings['13-16'] = $teams;

    return $standings;
}

// ──────────────────────────────────────────────
// Bracket generation (16 teams)
// ──────────────────────────────────────────────

/**
 * Generate the full bracket structure for a game.
 * Creates all UB, LB, and GF matches in the database.
 * Teams array must have exactly the team names in seed order (index 0 = seed 1).
 *
 * @param PDO    $pdo
 * @param string $game       Game slug (e.g., 'dota2')
 * @param array  $teams      Array of 16 team name strings in seed order
 * @param array  $locked     Optional: [match_order => [team1, team2]] for R1 matches to preserve
 */
function bracketGenerate(PDO $pdo, string $game, array $teams, array $locked = []): void {
    $count = count($teams);
    if ($count < 2 || $count > 16) {
        throw new InvalidArgumentException("Need 2-16 teams, got $count");
    }

    // Pad to 16 with BYEs
    $bracket_size = 16;
    while (count($teams) < $bracket_size) {
        $teams[] = 'BYE';
    }

    $insert = $pdo->prepare("INSERT INTO matches (game, bracket_side, round, match_order, team1_name, team2_name, status, winner, team1_score, team2_score) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0)");

    // ── Standard seeding order ──
    // Produces the classic bracket ordering: 1v16, 8v9, 5v12, 4v13, 3v14, 6v11, 7v10, 2v15
    $seed_positions = bracketSeedOrder($bracket_size);

    $seeded = [];
    foreach ($seed_positions as $idx) {
        $seeded[] = $teams[$idx];
    }

    // ── UB Round 1 (8 matches) ──
    for ($mo = 1; $mo <= 8; $mo++) {
        if (isset($locked[$mo])) continue; // Locked match preserved in DB

        $t1 = $seeded[($mo - 1) * 2];
        $t2 = $seeded[($mo - 1) * 2 + 1];

        if ($t1 === 'BYE' || $t2 === 'BYE') {
            $real = ($t1 === 'BYE') ? $t2 : $t1;
            if ($real === 'BYE') continue; // Both BYE — skip
            $insert->execute([$game, 'winners', 1, $mo, $t1, $t2, 'completed', $real]);
        } else {
            $insert->execute([$game, 'winners', 1, $mo, $t1, $t2, 'upcoming', '']);
        }
    }

    // ── UB Rounds 2-4 ──
    foreach (BRACKET_MATCH_COUNTS['winners'] as $r => $num) {
        if ($r === 1) continue;
        for ($j = 1; $j <= $num; $j++) {
            $insert->execute([$game, 'winners', $r, $j, 'TBD', 'TBD', 'pending', '']);
        }
    }

    // ── LB Rounds 1-6 ──
    foreach (BRACKET_MATCH_COUNTS['losers'] as $r => $num) {
        for ($j = 1; $j <= $num; $j++) {
            $insert->execute([$game, 'losers', $r, $j, 'TBD', 'TBD', 'pending', '']);
        }
    }

    // ── Grand Finals ──
    $insert->execute([$game, 'grand', 1, 1, 'TBD', 'TBD', 'pending', '']);

    // ── Auto-advance BYE winners ──
    $bye_matches = $pdo->prepare("SELECT * FROM matches WHERE game = ? AND bracket_side = 'winners' AND round = 1 AND status = 'completed' AND winner != ''");
    $bye_matches->execute([$game]);
    foreach ($bye_matches->fetchAll() as $bm) {
        bracketAdvanceMatch($pdo, $bm);
    }
}

/**
 * Standard tournament seeding order for n participants.
 * e.g., for 16: [0,15, 7,8, 3,12, 4,11, 1,14, 6,9, 2,13, 5,10]
 * This ensures seed 1 plays seed 16, seed 2 plays seed 15, etc.
 */
function bracketSeedOrder(int $n): array {
    if ($n === 1) return [0];
    $prev = bracketSeedOrder($n / 2);
    $result = [];
    foreach ($prev as $seed) {
        $result[] = $seed;
        $result[] = $n - 1 - $seed;
    }
    return $result;
}

function ensure_tournament_settings(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS tournament_settings (
            game VARCHAR(32) PRIMARY KEY,
            waitlist_locked TINYINT(1) NOT NULL DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {}
    $done = true;
}

function is_waitlist_locked(PDO $pdo, string $game): bool {
    static $cache = [];
    if (array_key_exists($game, $cache)) return $cache[$game];
    ensure_tournament_settings($pdo);
    try {
        $s = $pdo->prepare("SELECT waitlist_locked FROM tournament_settings WHERE game = ?");
        $s->execute([$game]);
        $cache[$game] = (bool)$s->fetchColumn();
    } catch (Throwable $e) {
        $cache[$game] = false;
    }
    return $cache[$game];
}

function set_waitlist_locked(PDO $pdo, string $game, bool $locked): void {
    ensure_tournament_settings($pdo);
    $s = $pdo->prepare("INSERT INTO tournament_settings (game, waitlist_locked) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE waitlist_locked = VALUES(waitlist_locked)");
    $s->execute([$game, $locked ? 1 : 0]);
}

function ensure_team_recruiting_column(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM `teams` LIKE 'recruiting'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE `teams` ADD COLUMN recruiting TINYINT(1) NOT NULL DEFAULT 0, ADD INDEX idx_recruiting (recruiting)");
        }
    } catch (Throwable $e) {}
    $done = true;
}

/**
 * Teams an account captains (via explicit teams.captain_account_id).
 * One user may captain multiple teams across games. Returns [] if none.
 */
function teams_captained_by(PDO $pdo, int $uid): array {
    if ($uid < 1) return [];
    try {
        $q = $pdo->prepare(
            "SELECT * FROM teams
             WHERE captain_account_id = ?
             ORDER BY id DESC"
        );
        $q->execute([$uid]);
        return $q->fetchAll() ?: [];
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Teams this account could claim as captain — i.e., teams where
 * member_1 = their display_name and no captain_account_id is set yet.
 * This handles legacy teams registered before the captain column existed.
 */
function teams_claimable_by(PDO $pdo, int $uid): array {
    if ($uid < 1) return [];
    try {
        $acc = $pdo->prepare("SELECT display_name FROM accounts WHERE id = ?");
        $acc->execute([$uid]);
        $name = trim((string)$acc->fetchColumn());
        if ($name === '') return [];

        $q = $pdo->prepare(
            "SELECT * FROM teams
             WHERE member_1 = ?
               AND (captain_account_id IS NULL OR captain_account_id = 0)
             ORDER BY id DESC"
        );
        $q->execute([$name]);
        return $q->fetchAll() ?: [];
    } catch (Exception $e) {
        return [];
    }
}

function ensure_reserved_columns(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    foreach (['teams', 'solo_players'] as $tbl) {
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM `$tbl` LIKE 'reserved'")->fetchAll();
            if (empty($cols)) {
                $pdo->exec("ALTER TABLE `$tbl` ADD COLUMN reserved TINYINT(1) NOT NULL DEFAULT 0, ADD INDEX idx_reserved (reserved)");
            }
        } catch (Throwable $e) {}
    }
    $done = true;
}
