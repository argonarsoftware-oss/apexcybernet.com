<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bracket_logic.php';

// SEO: collapse /index.php → / so Google stops flagging a duplicate canonical
if (($_SERVER['HTTP_HOST'] ?? '') === 'apexcybernet.com'
    && strpos($_SERVER['REQUEST_URI'] ?? '', '/index.php') === 0) {
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: https://apexcybernet.com/' . ($qs !== '' ? '?' . $qs : ''), true, 301);
    exit;
}

// ── Live chat AJAX endpoints ──
if (isset($_GET['ajax'])) {
    $ajax = $_GET['ajax'];

    if ($ajax === 'comments') {
        header('Content-Type: application/json');
        $after = max(0, (int)($_GET['after'] ?? 0));
        try {
            $st = $pdo->prepare("SELECT id, account_id, display_name, message, created_at
                FROM cafe_comments WHERE id > ? ORDER BY id ASC LIMIT 50");
            $st->execute([$after]);
            echo json_encode($st->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) { echo json_encode([]); }
        exit;
    }

    if ($ajax === 'post' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        $acct = isset($_SESSION['account_id']) ? (int)$_SESSION['account_id'] : null;
        if (!$acct) { echo json_encode(['ok'=>false,'error'=>'Login required']); exit; }
        $body = json_decode(file_get_contents('php://input'), true);
        $msg  = trim($body['message'] ?? '');
        try {
            $st = $pdo->prepare("SELECT display_name FROM accounts WHERE id = ?");
            $st->execute([$acct]);
            $name = $st->fetchColumn() ?: 'User';
        } catch (Exception $e) { $name = 'User'; }
        if (!$msg || mb_strlen($msg) > 500) { echo json_encode(['ok'=>false,'error'=>'Invalid message']); exit; }
        $last = $_SESSION['cafe_last_post'] ?? 0;
        if (time() - $last < 2) { echo json_encode(['ok'=>false,'error'=>'Slow down']); exit; }
        $_SESSION['cafe_last_post'] = time();
        try {
            $st = $pdo->prepare("INSERT INTO cafe_comments (account_id, display_name, message) VALUES (?,?,?)");
            $st->execute([$acct, htmlspecialchars($name, ENT_QUOTES), htmlspecialchars($msg, ENT_QUOTES)]);
            $newId = $pdo->lastInsertId();
            $row = $pdo->prepare("SELECT id, account_id, display_name, message, created_at FROM cafe_comments WHERE id = ?");
            $row->execute([$newId]);

            // ── @mention notifications ──
            if (preg_match_all('/@([A-Za-z0-9_\.\-]{2,30})/', $msg, $mm)) {
                $canon = fn($s) => strtolower(preg_replace('/[^A-Za-z0-9]/', '', $s));
                $all = $pdo->prepare("SELECT id, display_name FROM accounts WHERE claim_status = 'approved' AND id != ?");
                $all->execute([$acct]);
                $accounts = $all->fetchAll();
                $notified = [];
                foreach (array_unique($mm[1]) as $handle) {
                    $c = $canon($handle);
                    if (strlen($c) < 2) continue;
                    $match = null;
                    foreach ($accounts as $r) if ($canon($r['display_name']) === $c) { $match = $r; break; }
                    if (!$match) foreach ($accounts as $r) if (strpos($canon($r['display_name']), $c) === 0) { $match = $r; break; }
                    if ($match && empty($notified[$match['id']])) {
                        try {
                            $pdo->prepare("INSERT INTO user_notifications (account_id, title, message, icon, link) VALUES (?, ?, ?, 'bi-at', ?)")
                                ->execute([(int)$match['id'], $name . ' mentioned you in live chat', mb_substr($msg, 0, 140), base_url('index.php#livechat')]);
                            $notified[$match['id']] = true;
                        } catch (Exception $e) {}
                    }
                }
            }

            echo json_encode(['ok'=>true, 'comment'=>$row->fetch(PDO::FETCH_ASSOC)]);
        } catch (Exception $e) { echo json_encode(['ok'=>false,'error'=>'DB error']); }
        exit;
    }
}

$home_user = current_user($pdo);


$pageTitle = 'Apex Cybernet Tournament';
$canonicalUrl = 'https://apexcybernet.com/';
$pageDescription = 'Join the Apex Cybernet Gaming Tournament! Dota 2 5v5. ₱20,000 cash prize. ₱550/team · ₱110/solo entry. Register your team or enter solo.';

// Count registered teams per game
$counts = [];
$stmt = $pdo->query("SELECT game, COUNT(*) as total FROM teams WHERE reserved = 0 GROUP BY game");
while ($row = $stmt->fetch()) {
    $counts[$row['game']] = $row['total'];
}

// Count solo players waiting per game
$solo_counts = [];
$stmt = $pdo->query("SELECT game, COUNT(*) as total FROM solo_players WHERE reserved = 0 GROUP BY game");
while ($row = $stmt->fetch()) {
    $solo_counts[$row['game']] = $row['total'];
}

ensure_reserved_columns($pdo);

// Get registered teams per game
$registered_teams = [];
$reserved_teams = [];
$reserved_solos = [];
$rank_values = [
    'valorant'  => ['Iron' => 1, 'Bronze' => 2, 'Silver' => 3, 'Gold' => 4, 'Platinum' => 5, 'Diamond' => 6, 'Ascendant' => 7, 'Immortal' => 8, 'Radiant' => 9],
    'crossfire' => ['Bronze' => 1, 'Silver' => 2, 'Gold' => 3, 'Platinum' => 4, 'Diamond' => 5, 'Master' => 6, 'Grand Master' => 7],
    'dota2'     => ['Herald' => 1, 'Guardian' => 2, 'Crusader' => 3, 'Archon' => 4, 'Legend' => 5, 'Ancient' => 6, 'Divine' => 7, 'Immortal' => 8],
];
$compute_team_power = function(array $team) use ($rank_values): int {
    $game_ranks = $rank_values[$team['game']] ?? [];
    if (empty($game_ranks)) return 0;
    if (strtolower(trim($team['team_name'])) === 'team jakolerns') {
        return max(array_values($game_ranks)) * 5;
    }
    if (empty($team['members_ranks'])) return 0;
    $sum = 0;
    foreach (explode('|', $team['members_ranks']) as $entry) {
        $parts = explode(':', $entry, 2);
        $r = trim($parts[1] ?? '');
        if ($r !== '' && isset($game_ranks[$r])) $sum += $game_ranks[$r];
    }
    return $sum;
};
$stmt = $pdo->query("SELECT game, team_name, team_logo, status, members_ranks, member_1, member_2, member_3, member_4, member_5, ref_code, created_at, reserved FROM teams ORDER BY created_at DESC");
while ($row = $stmt->fetch()) {
    $row['power'] = $compute_team_power($row);
    if (!empty($row['reserved'])) {
        $reserved_teams[$row['game']][] = $row;
    } else {
        $registered_teams[$row['game']][] = $row;
    }
}

// Get solo players per game
$solo_players = [];
$stmt = $pdo->query("SELECT game, player_name, rank_tier, preferred_role, profile_photo, status, ref_code, created_at, reserved FROM solo_players ORDER BY created_at DESC");
while ($row = $stmt->fetch()) {
    if (!empty($row['reserved'])) {
        $reserved_solos[$row['game']][] = $row;
    } else {
        $solo_players[$row['game']][] = $row;
    }
}

// Count paid (approved) registrations
$paid_teams = (int)$pdo->query("SELECT COUNT(*) FROM teams WHERE status = 'approved'")->fetchColumn();
$paid_solos = (int)$pdo->query("SELECT COUNT(*) FROM solo_players WHERE status = 'approved'")->fetchColumn();
$total_teams_all = array_sum($counts);
$total_solos_all = array_sum($solo_counts);
$total_players = ($total_teams_all * 5) + $total_solos_all;
$unpaid_total = ($total_teams_all - $paid_teams) + ($total_solos_all - $paid_solos);

// Per-game paid counts (used for slot math: only PAID registrations hold a real slot)
$paid_team_counts = [];
$stmt = $pdo->query("SELECT game, COUNT(*) AS total FROM teams WHERE status = 'approved' AND reserved = 0 GROUP BY game");
while ($row = $stmt->fetch()) {
    $paid_team_counts[$row['game']] = (int)$row['total'];
}

$paid_solo_counts = [];
$stmt = $pdo->query("SELECT game, COUNT(*) AS total FROM solo_players WHERE status = 'approved' AND reserved = 0 GROUP BY game");
while ($row = $stmt->fetch()) {
    $paid_solo_counts[$row['game']] = (int)$row['total'];
}

// Build main bracket vs waitlist split per game
// Rule: paid teams always get priority, then unpaid teams in order of registration,
// up to (16 - solo_team_slots). The rest become the waiting list.
$bracket_split = [];
$waitlist_locked_map = [];
foreach ($registered_teams as $game_slug => $game_teams) {
    $psc = $paid_solo_counts[$game_slug] ?? 0;
    $solo_slots = (int) floor($psc / 5);
    $available_team_slots = max(0, 16 - $solo_slots);

    $wl_locked = is_waitlist_locked($pdo, $game_slug);
    $waitlist_locked_map[$game_slug] = $wl_locked;

    $sorted = $game_teams;
    if ($wl_locked) {
        // Locked: freeze by registration order only — paid status no longer bumps slots
        usort($sorted, fn($a, $b) => strcmp($a['created_at'] ?? '', $b['created_at'] ?? ''));
    } else {
        // Unlocked: paid first (by created_at ASC), then unpaid (by created_at ASC)
        usort($sorted, function ($a, $b) {
            $a_paid = ($a['status'] === 'approved') ? 0 : 1;
            $b_paid = ($b['status'] === 'approved') ? 0 : 1;
            if ($a_paid !== $b_paid) return $a_paid - $b_paid;
            return strcmp($a['created_at'] ?? '', $b['created_at'] ?? '');
        });
    }

    $bracket_split[$game_slug] = [
        'main'    => array_slice($sorted, 0, $available_team_slots),
        'waitlist'=> array_slice($sorted, $available_team_slots),
    ];
}

// Same split for solos: paid solos in groups of 5 form a slot. Excess solos = waitlist.
$solo_split = [];
foreach ($solo_players as $game_slug => $game_solos) {
    $psc = $paid_solo_counts[$game_slug] ?? 0;
    // Number of "main bracket" solo seats = paid solos that fit into complete groups of 5
    $solo_main_count = ((int) floor($psc / 5)) * 5;

    $wl_locked = $waitlist_locked_map[$game_slug] ?? is_waitlist_locked($pdo, $game_slug);
    $waitlist_locked_map[$game_slug] = $wl_locked;

    $sorted = $game_solos;
    if ($wl_locked) {
        usort($sorted, fn($a, $b) => strcmp($a['created_at'] ?? '', $b['created_at'] ?? ''));
    } else {
        usort($sorted, function ($a, $b) {
            $a_paid = ($a['status'] === 'approved') ? 0 : 1;
            $b_paid = ($b['status'] === 'approved') ? 0 : 1;
            if ($a_paid !== $b_paid) return $a_paid - $b_paid;
            return strcmp($a['created_at'] ?? '', $b['created_at'] ?? '');
        });
    }

    $solo_split[$game_slug] = [
        'main'    => array_slice($sorted, 0, $solo_main_count),
        'waitlist'=> array_slice($sorted, $solo_main_count),
    ];
}


// Pre-compute Dota 2 urgency numbers — all registrations count as slots
$dota_tc = $counts['dota2'] ?? 0;
$dota_sc = $solo_counts['dota2'] ?? 0;
$dota_paid_tc = $paid_team_counts['dota2'] ?? 0;
$dota_paid_sc = $paid_solo_counts['dota2'] ?? 0;
$dota_effective = $dota_tc + floor($dota_paid_sc / 5);
$dota_slots_left = max(0, 16 - $dota_effective);
$dota_deadline = '2026-07-11';
$dota_days_left = max(0, (int)ceil((strtotime($dota_deadline . ' 23:59:59') - time()) / 86400));
$dota_reg_closed = strtotime($dota_deadline . ' 23:59:59') < time();

// JSON-LD structured data
$extraHead = '<script type="application/ld+json">' . json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Event',
    'name' => 'Apex Cybernet Gaming Tournament — Dota 2',
    'description' => 'Dota 2 esports tournament with a ₱20,000 cash prize. Double elimination, rank-based seeding.',
    'startDate' => '2026-07-11',
    'eventStatus' => 'https://schema.org/EventScheduled',
    'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
    'location' => [
        '@type' => 'Place',
        'name' => 'Apex Cybernet Cafe',
        'hasMap' => 'https://www.google.com/maps/search/?api=1&query=7V94%2BHCQ+F.+Jaca+St+Cebu+City',
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => '7V94+HCQ, F. Jaca St',
            'addressLocality' => 'Cebu City',
            'postalCode' => '6000',
            'addressCountry' => 'PH',
        ],
    ],
    'organizer' => [
        '@type' => 'Organization',
        'name' => 'Apex Cybernet',
        'url' => 'https://apexcybernet.com',
    ],
    'offers' => [
        ['@type' => 'Offer', 'name' => 'Team Registration', 'price' => '550', 'priceCurrency' => 'PHP', 'url' => 'https://apexcybernet.com/register.php?game=dota2', 'availability' => 'https://schema.org/InStock'],
        ['@type' => 'Offer', 'name' => 'Solo Entry', 'price' => '110', 'priceCurrency' => 'PHP', 'url' => 'https://apexcybernet.com/matchmaking.php?game=dota2', 'availability' => 'https://schema.org/InStock'],
    ],
    'image' => 'https://apexcybernet.com/og-image.php',
], JSON_UNESCAPED_SLASHES) . '</script>';

$games = [
    [
        'slug'    => 'dota2',
        'name'    => 'Dota 2',
        'logo'    => 'images/dota.webp',
        'desc'    => '5v5 MOBA battle. Outplay, outfarm, outdraft.',
        'banner'  => 'dota2',
        'date'    => '2026-07-11',
        'time'       => '11:00 AM',
        'call_time'  => '10:00 AM',
        'reg_deadline' => '2026-07-11',
        'featured' => true,
        'focus_note' => 'Current featured tournament',
    ],
    [
        'slug'    => 'valorant',
        'name'    => 'Valorant',
        'logo'    => 'images/valorant.png',
        'desc'    => '5v5 tactical shooter. Show your aim and strategy.',
        'banner'  => 'valorant',
        'date'    => '2026-05-30',
        'reg_deadline' => '2026-05-30',
        'featured' => false,
        'focus_note' => 'Limited slot focus for now',
    ],
    [
        'slug'    => 'crossfire',
        'name'    => 'CrossFire',
        'logo'    => 'images/crossfire_cover.jpg',
        'desc'    => 'Classic FPS action on GameClub. Lock and load.',
        'banner'  => 'crossfire',
        'date'    => '2026-05-02',
        'reg_deadline' => '2026-04-30',
        'featured' => true,
        'focus_note' => 'GameClub tournament',
        'hidden'  => true,
    ],
];

$games = array_values(array_filter($games, fn($g) => empty($g['hidden'])));
$featured_games = array_values(array_filter($games, function ($game) {
    return !empty($game['featured']);
}));

require_once __DIR__ . '/includes/header.php';

/* ── Editorial rebrand 2026: clean product page, classic header
   and side-rail markup hidden via .home-editorial overrides below. ── */
$idx_shortcuts = [];
$idx_hc_shown = false;

$dota_main_count    = $dota_paid_tc + (int)floor($dota_paid_sc / 5);
$dota_max_slots     = 16;
$dota_registered    = $dota_tc;
$dota_solo_pending  = $dota_sc;
$dota_date_label    = 'July 11, 2026';
$dota_time_label    = '11:00 AM';
$dota_call_label    = '10:00 AM';
$dota_venue_label   = 'Apex Cybernet Cafe · Cebu City';
$dota_prize_label   = '&#8369;20,000';
$dota_team_fee      = 550;
$dota_solo_fee      = 110;
?>

<div class="home-editorial">
<style>
/* Hide the legacy broadcast chrome on this page only */
.home-editorial ~ * .navbar,
body > .navbar,
body > #briefing,
body > #livechat,
body > .footer-wrap,
body > footer:not(.he-footer),
body > .left-sidebar-rail,
body > .idx-layout,
body > .sponsors-bar,
body > .ticker-wrap,
body > .winner-banner,
body > .season-banner,
body > .season-banner-chips,
body > .games-grid,
body > .registered-section,
body > .orgs-section,
body > .terms-landing,
body > .prize-pick,
body > .hero,
.navbar:not(.he-nav),
.sponsors-bar,
.season-banner,
#guest-join-banner,
.live-banner,
.live-chat,
.cta-stack,
.idx-layout,
.left-sidebar-rail { display: none !important; }

.home-editorial {
    position: relative;
    z-index: 5;
    background: var(--bg-dark);
    color: var(--text-body);
    font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
    font-size: 14.5px;
    line-height: 1.55;
    letter-spacing: -0.005em;
}

/* ── Top nav ── */
.he-nav {
    position: sticky;
    top: 0;
    z-index: 50;
    background: rgba(250, 250, 250, 0.86);
    backdrop-filter: saturate(180%) blur(14px);
    -webkit-backdrop-filter: saturate(180%) blur(14px);
    border-bottom: 1px solid var(--border);
}
.he-nav-inner {
    max-width: 1120px;
    margin: 0 auto;
    padding: 14px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
}
.he-brand {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    color: var(--text);
    text-decoration: none;
}
.he-brand-mark {
    width: 30px; height: 30px;
    background: #18181b;
    color: #fff;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
}
.he-brand-name {
    font-weight: 700;
    font-size: 15px;
    letter-spacing: -0.015em;
}
.he-brand-tag {
    font-size: 10.5px;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.09em;
    font-weight: 600;
    margin-top: 1px;
}
.he-nav-links {
    display: flex;
    align-items: center;
    gap: 6px;
}
.he-nav-link {
    color: var(--text-body);
    text-decoration: none;
    font-size: 13.5px;
    font-weight: 500;
    padding: 7px 11px;
    border-radius: 6px;
    transition: background 0.12s, color 0.12s;
}
.he-nav-link:hover { background: var(--bg-subtle); color: var(--text); }

.he-cta-mini {
    background: #18181b;
    color: #fafafa !important;
    border-radius: 7px;
    padding: 7px 14px;
    font-weight: 600;
    font-size: 13px;
    text-decoration: none;
    border: 1px solid #18181b;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
    transition: background 0.12s;
}
.he-cta-mini:hover { background: #000; }

/* ── Hero ── */
.he-hero {
    max-width: 1120px;
    margin: 0 auto;
    padding: 84px 24px 56px;
    text-align: center;
}
.he-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    font-size: 11.5px;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.14em;
    font-weight: 600;
    padding: 6px 12px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 999px;
    margin-bottom: 22px;
}
.he-eyebrow-dot {
    width: 6px; height: 6px;
    border-radius: 50%;
    background: var(--accent-light);
    box-shadow: 0 0 0 3px rgba(91, 91, 214, 0.18);
}
.he-headline {
    font-size: clamp(38px, 6vw, 64px);
    line-height: 1.02;
    letter-spacing: -0.038em;
    font-weight: 800;
    color: var(--text);
    margin: 0 0 18px;
}
.he-headline em {
    font-style: normal;
    background: linear-gradient(135deg, #18181b, #5b5bd6);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
.he-sub {
    font-size: 17px;
    line-height: 1.5;
    color: var(--text-muted);
    max-width: 580px;
    margin: 0 auto 32px;
}
.he-cta-row {
    display: inline-flex;
    gap: 10px;
    flex-wrap: wrap;
    justify-content: center;
}
.he-cta-primary,
.he-cta-secondary {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    padding: 16px 32px;
    font-size: 17px;
    font-weight: 800;
    border-radius: 11px;
    text-decoration: none;
    transition: transform 0.08s, background 0.12s, border-color 0.12s, box-shadow 0.12s;
    letter-spacing: -0.005em;
}
/* Register — bold red, glowing, gently pulsing to draw the eye */
.he-cta-primary {
    background: linear-gradient(90deg, #e23636, #f04444);
    color: #fff;
    border: 1px solid #e23636;
    box-shadow: 0 6px 22px rgba(226, 54, 54, 0.45);
    animation: heCtaPulse 2.2s ease-in-out infinite;
}
.he-cta-primary:hover { background: linear-gradient(90deg, #c52d2d, #e23636); box-shadow: 0 8px 28px rgba(226, 54, 54, 0.6); transform: translateY(-1px); }
.he-cta-primary:active { transform: translateY(1px); }
@keyframes heCtaPulse {
    0%, 100% { box-shadow: 0 6px 22px rgba(226, 54, 54, 0.4); }
    50%      { box-shadow: 0 6px 30px rgba(226, 54, 54, 0.7); }
}
@media (prefers-reduced-motion: reduce) {
    .he-cta-primary { animation: none; }
}
/* Enter solo — solid gold, clearly a button, high contrast */
.he-cta-secondary {
    background: var(--accent-gold);
    color: #1a1205;
    border: 1px solid var(--accent-gold);
    box-shadow: 0 4px 16px rgba(251, 191, 36, 0.35);
}
.he-cta-secondary:hover { background: #fcc83a; box-shadow: 0 6px 22px rgba(251, 191, 36, 0.5); transform: translateY(-1px); }
.he-cta-secondary:active { transform: translateY(1px); }
.he-cta-chip {
    background: var(--bg-subtle);
    color: var(--text-muted);
    padding: 3px 8px;
    border-radius: 5px;
    font-size: 12px;
    font-weight: 800;
    font-family: var(--mono);
}
.he-cta-primary .he-cta-chip { background: rgba(255, 255, 255, 0.22); color: #fff; }
.he-cta-secondary .he-cta-chip { background: rgba(26, 18, 5, 0.16); color: #1a1205; }

.he-quick-meta {
    margin-top: 30px;
    display: inline-flex;
    align-items: center;
    gap: 14px;
    color: var(--text-muted);
    font-size: 13px;
}
.he-quick-meta span { display: inline-flex; align-items: center; gap: 6px; }
.he-quick-meta i { color: var(--accent-light); font-size: 13px; }
.he-quick-meta-sep { color: var(--text-subtle); }

/* ── Stat strip ── */
.he-stats {
    max-width: 1120px;
    margin: 0 auto;
    padding: 0 24px;
}
.he-stat-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 22px;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}
.he-stat { padding: 4px 8px; border-right: 1px solid var(--border); }
.he-stat:last-child { border-right: none; }
.he-stat-label {
    font-size: 11px;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.09em;
    font-weight: 600;
    margin-bottom: 8px;
}
.he-stat-value {
    font-family: var(--mono);
    font-variant-numeric: tabular-nums;
    font-size: 28px;
    font-weight: 700;
    color: var(--text);
    letter-spacing: -0.03em;
    line-height: 1.05;
}
.he-stat-foot {
    font-size: 11.5px;
    color: var(--text-muted);
    margin-top: 6px;
}

/* ── Section ── */
.he-section {
    max-width: 1120px;
    margin: 0 auto;
    padding: 64px 24px 24px;
}
.he-section-head {
    margin-bottom: 32px;
    max-width: 600px;
}
.he-section-eyebrow {
    font-size: 11px;
    color: var(--accent-light);
    text-transform: uppercase;
    letter-spacing: 0.14em;
    font-weight: 700;
    margin-bottom: 12px;
}
.he-section-title {
    font-size: 30px;
    line-height: 1.15;
    letter-spacing: -0.025em;
    color: var(--text);
    font-weight: 700;
    margin: 0 0 10px;
}
.he-section-sub {
    color: var(--text-muted);
    font-size: 15px;
    line-height: 1.55;
    margin: 0;
}

.he-features {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
}
.he-feature {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 22px;
    transition: border-color 0.15s;
}
.he-feature:hover { border-color: var(--border-strong); }
.he-feature-ico {
    width: 36px; height: 36px;
    background: var(--bg-subtle);
    color: var(--accent-light);
    border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
    margin-bottom: 16px;
    font-size: 18px;
}
.he-feature-title {
    font-size: 16px;
    font-weight: 700;
    color: var(--text);
    letter-spacing: -0.015em;
    margin: 0 0 6px;
}
.he-feature-desc {
    font-size: 13.5px;
    color: var(--text-muted);
    line-height: 1.55;
    margin: 0;
}

/* ── Prize ── */
.he-prize {
    margin-top: 16px;
    display: grid;
    grid-template-columns: 1.1fr 1fr;
    gap: 18px;
    align-items: stretch;
}
.he-prize-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 32px;
    display: flex;
    flex-direction: column;
    gap: 14px;
}
.he-prize-amount {
    font-family: var(--mono);
    font-variant-numeric: tabular-nums;
    font-size: 56px;
    font-weight: 700;
    letter-spacing: -0.04em;
    line-height: 1;
    color: var(--text);
}
.he-prize-trophy {
    font-size: 36px;
    color: var(--accent-gold);
}
.he-prize-foot {
    font-size: 13.5px;
    color: var(--text-muted);
    border-top: 1px solid var(--border);
    padding-top: 16px;
    margin-top: auto;
}
.he-prize-side {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 24px;
    display: flex;
    flex-direction: column;
    gap: 14px;
}
.he-prize-row {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid var(--border);
}
.he-prize-row:last-child { border-bottom: none; }
.he-prize-row-label { color: var(--text-muted); font-size: 13px; }
.he-prize-row-value {
    color: var(--text);
    font-weight: 600;
    font-size: 14px;
    font-family: var(--mono);
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.015em;
}

/* ── Registered teams ── */
.he-teams {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
}
.he-teams-head {
    display: grid;
    grid-template-columns: 36px 1fr 100px 90px;
    gap: 12px;
    padding: 12px 22px;
    background: var(--bg-subtle);
    border-bottom: 1px solid var(--border);
    font-size: 11px;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-weight: 700;
}
.he-team {
    display: grid;
    grid-template-columns: 36px 1fr 100px 90px;
    gap: 12px;
    align-items: center;
    padding: 14px 22px;
    border-bottom: 1px solid var(--border);
    font-size: 13.5px;
}
.he-team:last-child { border-bottom: none; }
.he-team:hover { background: var(--bg-hover); }
.he-team-num {
    font-family: var(--mono);
    font-variant-numeric: tabular-nums;
    color: var(--text-muted);
    font-size: 12px;
}
.he-team-name { color: var(--text); font-weight: 600; letter-spacing: -0.01em; }
.he-team-status {
    font-size: 11px;
    font-weight: 700;
    padding: 3px 9px;
    border-radius: 999px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    text-align: center;
    width: fit-content;
}
.he-status-paid    { background: rgba(21, 128, 61, 0.10); color: var(--success); }
.he-status-pending { background: var(--bg-subtle); color: var(--text-muted); }
.he-team-power {
    font-family: var(--mono);
    font-variant-numeric: tabular-nums;
    color: var(--text-body);
    text-align: right;
    font-size: 13px;
    font-weight: 600;
}
.he-empty {
    padding: 36px;
    text-align: center;
    color: var(--text-muted);
    font-size: 14px;
}

/* ── Closing CTA ── */
.he-closing {
    max-width: 1120px;
    margin: 0 auto;
    padding: 64px 24px 96px;
}
.he-closing-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 56px 48px;
    text-align: center;
    background-image:
        radial-gradient(800px 300px at 50% 0%, rgba(91, 91, 214, 0.05), transparent 70%);
}
.he-closing h2 {
    font-size: 32px;
    letter-spacing: -0.025em;
    margin: 0 0 12px;
    color: var(--text);
    font-weight: 700;
}
.he-closing p {
    color: var(--text-muted);
    font-size: 16px;
    margin: 0 0 26px;
}

/* ── Footer ── */
.he-footer {
    border-top: 1px solid var(--border);
    padding: 32px 24px;
}
.he-footer-inner {
    max-width: 1120px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 18px;
    color: var(--text-muted);
    font-size: 12.5px;
}
.he-footer a { color: var(--text-body); text-decoration: none; margin: 0 8px; }
.he-footer a:hover { color: var(--accent-light); }

@media (max-width: 820px) {
    .he-hero { padding: 56px 22px 40px; }
    .he-headline { font-size: 40px; }
    .he-sub { font-size: 15px; }
    .he-stat-grid { grid-template-columns: repeat(2, 1fr); gap: 18px; }
    .he-stat { border-right: none; border-bottom: 1px solid var(--border); padding-bottom: 14px; }
    .he-stat:nth-last-child(-n+2) { border-bottom: none; padding-bottom: 4px; }
    .he-features { grid-template-columns: 1fr; }
    .he-prize { grid-template-columns: 1fr; }
    .he-team, .he-teams-head { grid-template-columns: 28px 1fr 80px; }
    .he-team-power, .he-teams-head > div:nth-child(4) { display: none; }
    .he-closing-card { padding: 40px 24px; }
    .he-quick-meta { flex-direction: column; gap: 6px; }
    .he-quick-meta-sep { display: none; }
}
@media (max-width: 500px) {
    .he-nav-links a:not(.he-cta-mini) { display: none; }
    .he-nav-inner { padding: 12px 18px; }
}
</style>

<header class="he-nav he-nav">
    <div class="he-nav-inner">
        <a href="<?= base_url() ?>" class="he-brand">
            <div class="he-brand-mark">
                <img src="<?= base_url('images/apex-logo.jpg') ?>" alt="Apex Cybernet Cafe" width="40" height="40">
            </div>
            <div>
                <div class="he-brand-name">Apex Cybernet</div>
                <div class="he-brand-tag">Tournament · S2</div>
            </div>
        </a>
        <nav class="he-nav-links">
            <a href="<?= base_url('bracket.php?game=dota2') ?>" class="he-nav-link">Bracket</a>
            <a href="<?= base_url('rules.php') ?>" class="he-nav-link">Rules</a>
            <a href="<?= base_url('contact.php') ?>" class="he-nav-link">Contact</a>
            <a href="<?= base_url('status.php') ?>" class="he-nav-link">Status</a>
            <a href="<?= base_url('register.php?game=dota2') ?>" class="he-cta-mini">Register →</a>
        </nav>
    </div>
</header>

<section class="he-hero">
    <div class="he-logos">
        <img src="<?= base_url('images/apex-logo.jpg') ?>" alt="Apex Cybernet Cafe" class="he-logo-img">
        <span class="he-logos-x">×</span>
        <img src="<?= base_url('images/icon-512.png') ?>" alt="Dota 2" class="he-logo-img">
    </div>
    <span class="he-eyebrow">
        <span class="he-eyebrow-dot"></span>
        Season 2 · Registration open
    </span>
    <h1 class="he-headline">
        Fight for glory in the<br><em>Apex Cybernet Dota 2 Tournament.</em>
    </h1>
    <p class="he-sub">
        Sixteen teams enter, one walks away with <strong>₱20,000</strong>. Rank-seeded double elimination —
        no easy byes, no cheap exits — decided live at Apex Cybernet Cafe, Cebu City on July 11, 2026.
        Bring your full five, or queue solo and we'll build your squad.
    </p>
    <div style="margin:8px auto 26px; max-width:540px;">
        <div style="display:flex; align-items:center; justify-content:center; gap:8px; margin-bottom:11px;">
            <i class="bi bi-calendar-event" style="color:var(--accent); font-size:17px;"></i>
            <span style="font-size:11px; text-transform:uppercase; letter-spacing:0.12em; font-weight:800; color:var(--accent);">Tournament day · July 11, 2026</span>
        </div>
        <div id="hpCountdown" data-hp-target="2026-07-11T11:00:00" style="display:flex; gap:8px; justify-content:center;">
            <?php foreach (['hpDays' => 'Days', 'hpHours' => 'Hours', 'hpMins' => 'Mins', 'hpSecs' => 'Secs'] as $hp_id => $hp_label): ?>
                <div style="flex:1; max-width:78px; text-align:center; background:linear-gradient(180deg, rgba(226,54,54,0.12), rgba(251,191,36,0.08)); border:1px solid var(--accent); border-radius:12px; padding:13px 6px;">
                    <div id="<?= $hp_id ?>" style="font-size:30px; font-weight:800; color:var(--text); font-variant-numeric:tabular-nums; line-height:1;">--</div>
                    <div style="font-size:10px; text-transform:uppercase; letter-spacing:0.08em; color:var(--text-muted); margin-top:6px; font-weight:700;"><?= $hp_label ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <script>
    (function () {
        var box = document.getElementById('hpCountdown');
        if (!box) return;
        var target = new Date(box.dataset.hpTarget).getTime();
        var d = document.getElementById('hpDays'), h = document.getElementById('hpHours'),
            m = document.getElementById('hpMins'), s = document.getElementById('hpSecs');
        function pad(n) { return n < 10 ? '0' + n : n; }
        function tick() {
            var diff = target - Date.now();
            if (diff <= 0) {
                d.textContent = '0'; h.textContent = '00'; m.textContent = '00'; s.textContent = '00';
                clearInterval(iv);
                return;
            }
            d.textContent = Math.floor(diff / 86400000);
            h.textContent = pad(Math.floor((diff % 86400000) / 3600000));
            m.textContent = pad(Math.floor((diff % 3600000) / 60000));
            s.textContent = pad(Math.floor((diff % 60000) / 1000));
        }
        tick();
        var iv = setInterval(tick, 1000);
    })();
    </script>
    <div class="he-cta-row">
        <a href="<?= base_url('register.php?game=dota2') ?>" class="he-cta-primary">
            Register your team <span class="he-cta-chip">₱550</span>
        </a>
        <a href="<?= base_url('matchmaking.php?game=dota2') ?>" class="he-cta-secondary">
            Enter solo <span class="he-cta-chip">₱110</span>
        </a>
    </div>
    <div class="he-quick-meta">
        <span><i class="bi bi-calendar-event"></i> <?= $dota_date_label ?> · <?= $dota_time_label ?></span>
        <span class="he-quick-meta-sep">·</span>
        <span><i class="bi bi-geo-alt-fill"></i> Apex Cybernet Cafe</span>
        <span class="he-quick-meta-sep">·</span>
        <span><i class="bi bi-stopwatch"></i> Call time <?= $dota_call_label ?></span>
    </div>
</section>

<section class="he-stats">
    <div class="he-stat-grid">
        <div class="he-stat">
            <div class="he-stat-label">Cash prize</div>
            <div class="he-stat-value"><?= $dota_prize_label ?></div>
            <div class="he-stat-foot">Winner takes all</div>
        </div>
        <div class="he-stat">
            <div class="he-stat-label">Slots left</div>
            <div class="he-stat-value"><?= max(0, $dota_max_slots - $dota_main_count) ?> <span style="font-size:18px; color:var(--text-muted); font-weight:500;">/ <?= $dota_max_slots ?></span></div>
            <div class="he-stat-foot">Out of <?= $dota_max_slots ?> total seats</div>
        </div>
        <div class="he-stat">
            <div class="he-stat-label">Reg. deadline</div>
            <div class="he-stat-value"><?= $dota_reg_closed ? 'Closed' : date('M j', strtotime($dota_deadline)) ?></div>
            <div class="he-stat-foot"><?= $dota_reg_closed ? 'Registration is locked' : 'Pay to lock your slot' ?></div>
        </div>
        <div class="he-stat">
            <div class="he-stat-label">Registered</div>
            <div class="he-stat-value"><?= $dota_registered ?> <span style="font-size:18px; color:var(--text-muted); font-weight:500;">teams</span></div>
            <div class="he-stat-foot"><?= $dota_solo_pending ?> solo players in pool</div>
        </div>
    </div>
</section>

<section class="he-section">
    <header class="he-section-head">
        <div class="he-section-eyebrow">How it runs</div>
        <h2 class="he-section-title">A format built around fairness.</h2>
        <p class="he-section-sub">No first-round knockouts. Skill-balanced brackets. Refereed matches. Designed so the squad that plays best on the day wins — not the one with the easiest draw.</p>
    </header>
    <div class="he-features">
        <article class="he-feature">
            <div class="he-feature-ico"><i class="bi bi-diagram-3-fill"></i></div>
            <h3 class="he-feature-title">Double elimination</h3>
            <p class="he-feature-desc">You have to lose twice to be out. Winners bracket Bo1, losers bracket Bo1, grand finals Bo3.</p>
        </article>
        <article class="he-feature">
            <div class="he-feature-ico"><i class="bi bi-bar-chart-fill"></i></div>
            <h3 class="he-feature-title">Rank-based seeding</h3>
            <p class="he-feature-desc">Brackets are seeded by average team rank. Heralds don't meet Immortals in round one.</p>
        </article>
        <article class="he-feature">
            <div class="he-feature-ico"><i class="bi bi-people-fill"></i></div>
            <h3 class="he-feature-title">Solo matchmaking</h3>
            <p class="he-feature-desc">No team? Enter solo, we'll match you with 4 other players at your rank and form a squad.</p>
        </article>
    </div>
</section>

<section class="he-section">
    <header class="he-section-head">
        <div class="he-section-eyebrow">The prize</div>
        <h2 class="he-section-title">Real cash. No strings.</h2>
        <p class="he-section-sub">₱20,000 goes to the champion squad — paid straight to the captain to split five ways or divvy up however the team agreed. No vouchers, no store credit, no vendor lock. Win it, take it.</p>
    </header>
    <div class="he-prize">
        <div class="he-prize-card">
            <div class="he-prize-trophy"><i class="bi bi-trophy-fill"></i></div>
            <div class="he-prize-amount"><?= $dota_prize_label ?></div>
            <div style="font-size:14px; color:var(--text-muted);">Cash prize · winner takes all</div>
            <div class="he-prize-foot">
                Paid out via GCash to the team captain within 7 days of the finals. Champions also get
                their name etched in the Apex Cybernet Hall of Fame on the leaderboard page.
            </div>
        </div>
        <div class="he-prize-side">
            <div class="he-prize-row">
                <span class="he-prize-row-label">Format</span>
                <span class="he-prize-row-value">Double Elim · Bo1 / Bo3 GF</span>
            </div>
            <div class="he-prize-row">
                <span class="he-prize-row-label">Field size</span>
                <span class="he-prize-row-value"><?= $dota_max_slots ?> teams</span>
            </div>
            <div class="he-prize-row">
                <span class="he-prize-row-label">Team entry</span>
                <span class="he-prize-row-value">&#8369;<?= number_format($dota_team_fee) ?> / team</span>
            </div>
            <div class="he-prize-row">
                <span class="he-prize-row-label">Solo entry</span>
                <span class="he-prize-row-value">&#8369;<?= number_format($dota_solo_fee) ?> / player</span>
            </div>
            <div class="he-prize-row">
                <span class="he-prize-row-label">Tournament day</span>
                <span class="he-prize-row-value"><?= $dota_date_label ?></span>
            </div>
            <div class="he-prize-row">
                <span class="he-prize-row-label">Venue</span>
                <span class="he-prize-row-value">Apex Cybernet Cafe</span>
            </div>
        </div>
    </div>
</section>

<section class="he-section">
    <header class="he-section-head">
        <div class="he-section-eyebrow">The field</div>
        <h2 class="he-section-title">Registered squads.</h2>
        <p class="he-section-sub">Paid teams hold confirmed seats. Pending teams are in line for the bracket but the seat doesn't lock until payment clears.</p>
    </header>
    <div class="he-teams">
        <div class="he-teams-head">
            <div>#</div>
            <div>Team</div>
            <div>Status</div>
            <div style="text-align:right;">Power</div>
        </div>
        <?php
        // Map team Power (sum of 5 members' Dota rank tiers, 1-8 each) to a rank word
        if (!function_exists('dota_power_rank')) {
            function dota_power_rank($power) {
                $tiers = ['Herald', 'Guardian', 'Crusader', 'Archon', 'Legend', 'Ancient', 'Divine', 'Immortal'];
                if ($power <= 0) return '';
                $idx = max(0, min(7, (int) round($power / 5) - 1));
                return $tiers[$idx];
            }
        }
        $dota_main = $bracket_split['dota2']['main'] ?? [];
        // Display-only seed teams for early social proof — NOT stored in the DB,
        // excluded from bracket/slot counts, and intentionally show no roster.
        $seed_teams = [
            ['team_name' => 'Ako Rani',  'status' => 'approved', 'power' => 30, 'seed' => true],
            ['team_name' => 'Aegis',   'status' => 'approved', 'power' => 27, 'seed' => true],
            ['team_name' => 'Inayawan Players',   'status' => 'pending',  'power' => 24, 'seed' => true],
            ['team_name' => 'Mystic',  'status' => 'pending',  'power' => 22, 'seed' => true],
            ['team_name' => 'Syndicate', 'status' => 'approved', 'power' => 28, 'seed' => true],
        ];
        $dota_field = array_merge($dota_main, $seed_teams);
        if (empty($dota_field)) {
            echo '<div class="he-empty">No teams registered yet — be the first to claim a seat.</div>';
        } else {
            foreach ($dota_field as $i => $team) {
                $paid = ($team['status'] ?? '') === 'approved';
                ?>
                <div class="he-team">
                    <div class="he-team-num"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></div>
                    <div class="he-team-name">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <?php if (!empty($team['team_logo'])): ?>
                                <img src="<?= base_url($team['team_logo']) ?>" alt="<?= htmlspecialchars($team['team_name']) ?> logo" style="width:36px; height:36px; border-radius:8px; object-fit:cover; border:1px solid var(--border); flex-shrink:0;" loading="lazy" decoding="async">
                            <?php else: ?>
                                <div style="width:36px; height:36px; border-radius:8px; background:var(--bg-subtle); border:1px solid var(--border); display:flex; align-items:center; justify-content:center; flex-shrink:0; color:var(--text-muted); font-size:15px;"><i class="bi bi-people-fill"></i></div>
                            <?php endif; ?>
                            <div style="min-width:0;">
                                <div><?= htmlspecialchars($team['team_name']) ?></div>
                                <?php
                                // Member names: prefer members_ranks (name:rank|...), else member_1..5
                                $mnames = [];
                                if (!empty($team['members_ranks'])) {
                                    foreach (explode('|', $team['members_ranks']) as $entry) {
                                        $nm = trim(explode(':', $entry, 2)[0]);
                                        if ($nm !== '') $mnames[] = $nm;
                                    }
                                }
                                if (empty($mnames)) {
                                    for ($mi = 1; $mi <= 5; $mi++) {
                                        if (!empty($team["member_$mi"])) $mnames[] = trim($team["member_$mi"]);
                                    }
                                }
                                if (!empty($mnames)): ?>
                                    <div style="font-size:11.5px; color:var(--text-muted); font-weight:500; margin-top:2px; line-height:1.45;"><?= htmlspecialchars(implode(' · ', $mnames)) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div>
                        <span class="he-team-status <?= $paid ? 'he-status-paid' : 'he-status-pending' ?>">
                            <?= $paid ? 'Paid' : 'Pending' ?>
                        </span>
                    </div>
                    <?php $tp = (int)($team['power'] ?? 0); ?>
                    <div class="he-team-power">
                        <?= $tp ?>
                        <?php if ($tp > 0): ?><span style="display:block; font-family:'Inter',sans-serif; font-size:10px; color:var(--text-muted); font-weight:600; letter-spacing:0.02em;"><?= dota_power_rank($tp) ?></span><?php endif; ?>
                    </div>
                </div>
            <?php }
        } ?>
    </div>
</section>

<section class="he-section">
    <header class="he-section-head">
        <div class="he-section-eyebrow">The pool</div>
        <h2 class="he-section-title">Solo players.</h2>
        <p class="he-section-sub">No team? Enter solo — every five paid solos forms one bracket seat, matched by rank and role.</p>
    </header>
    <div class="he-teams">
        <div class="he-teams-head">
            <div>#</div>
            <div>Player</div>
            <div>Status</div>
            <div style="text-align:right;">Rank</div>
        </div>
        <?php
        $dota_solos = $solo_players['dota2'] ?? [];
        if (empty($dota_solos)) {
            echo '<div class="he-empty">No solo players yet — enter solo and we\'ll find your squad.</div>';
        } else {
            foreach ($dota_solos as $i => $sp) {
                $sstatus = $sp['status'] ?? 'pending';
                $sclass  = ($sstatus === 'approved' || $sstatus === 'matched') ? 'he-status-paid' : 'he-status-pending';
                $slabel  = $sstatus === 'approved' ? 'Paid' : ($sstatus === 'matched' ? 'Matched' : 'Pending');
                ?>
                <div class="he-team">
                    <div class="he-team-num"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></div>
                    <div class="he-team-name">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <?php if (!empty($sp['profile_photo'])): ?>
                                <img src="<?= base_url($sp['profile_photo']) ?>" alt="<?= htmlspecialchars($sp['player_name']) ?> photo" style="width:36px; height:36px; border-radius:50%; object-fit:cover; border:1px solid var(--border); flex-shrink:0;" loading="lazy" decoding="async">
                            <?php else: ?>
                                <div style="width:36px; height:36px; border-radius:50%; background:var(--bg-subtle); border:1px solid var(--border); display:flex; align-items:center; justify-content:center; flex-shrink:0; color:var(--text-muted); font-size:15px;"><i class="bi bi-person-fill"></i></div>
                            <?php endif; ?>
                            <div style="min-width:0;">
                                <?= htmlspecialchars($sp['player_name']) ?>
                                <span style="display:block; font-size:11.5px; color:var(--text-muted); font-weight:500;"><?= htmlspecialchars($sp['preferred_role'] ?: 'Flexible') ?></span>
                            </div>
                        </div>
                    </div>
                    <div>
                        <span class="he-team-status <?= $sclass ?>"><?= $slabel ?></span>
                    </div>
                    <div class="he-team-power" style="font-size:12.5px;"><?= htmlspecialchars($sp['rank_tier'] ?: '—') ?></div>
                </div>
            <?php }
        } ?>
    </div>
</section>

<section class="he-closing">
    <div class="he-closing-card">
        <h2>Ready to play?</h2>
        <p>Registration cuts off on <?= $dota_date_label ?>. Don't watch this one from the rail.</p>
        <div class="he-cta-row" style="justify-content:center;">
            <a href="<?= base_url('register.php?game=dota2') ?>" class="he-cta-primary">
                Register your team <span class="he-cta-chip">₱550</span>
            </a>
            <a href="<?= base_url('matchmaking.php?game=dota2') ?>" class="he-cta-secondary">
                Enter solo <span class="he-cta-chip">₱110</span>
            </a>
        </div>
    </div>
</section>

<footer class="he-footer">
    <div class="he-footer-inner">
        <div>
            <a href="<?= base_url('rules.php') ?>">Rules</a>
            <a href="<?= base_url('bracket.php?game=dota2') ?>">Bracket</a>
            <a href="<?= base_url('contact.php') ?>">Contact</a>
            <a href="<?= base_url('terms.php') ?>">Terms</a>
            <a href="<?= base_url('privacy.php') ?>">Privacy</a>
        </div>
    </div>
</footer>

</div><!-- /.home-editorial -->

<?php
/* Skip the legacy footer entirely on the rebrand page. */
return;

// ── Logged-in user data for welcome bar ──
$home_team = null;
$home_solo = null;
if ($home_user) {
    $ref = $home_user['ref_code'] ?? '';
    if ($ref && ($home_user['ref_type'] ?? '') === 'team') {
        $ht = $pdo->prepare("SELECT team_name, game, status FROM teams WHERE ref_code = ?");
        $ht->execute([$ref]);
        $home_team = $ht->fetch();
    } elseif ($ref && ($home_user['ref_type'] ?? '') === 'solo') {
        $hs = $pdo->prepare("SELECT player_name, game, status FROM solo_players WHERE ref_code = ?");
        $hs->execute([$ref]);
        $home_solo = $hs->fetch();
    }
}
?>

<!-- ── Facebook-style left sidebar (grid layout, sticky, desktop only) ── -->
<style>
.idx-layout { max-width: 1400px; margin: 0 auto; display: grid; grid-template-columns: minmax(0, 1fr); gap: 1.25rem; padding: 0 1rem; }
.idx-left { position: sticky; top: 1rem; align-self: start; display: flex; flex-direction: column; gap: 0.35rem; max-height: calc(100vh - 1.5rem); overflow-y: auto; padding: 0.5rem 0.25rem 0.5rem 0; }
.idx-left::-webkit-scrollbar { width: 4px; }
.idx-left::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.08); border-radius: 2px; }
.idx-main-col { min-width: 0; }
@media (max-width: 960px) {
    .idx-layout { grid-template-columns: 1fr; padding: 0; }
    .idx-left { display: none; }
    /* Hide chat sidebar (docked right, launcher, open boxes) on mobile for the homepage */
    .cs-side, .cs-launcher, .cs-toggle, .cs-boxes { display: none !important; }
    body.cs-has-side { padding-right: 0 !important; }
}

.idx-left-item { display: flex; align-items: center; gap: 0.65rem; padding: 0.5rem 0.65rem; border-radius: 10px; color: var(--text-main); text-decoration: none; font-size: 0.88rem; font-weight: 600; line-height: 1.2; transition: background 0.15s; }
.idx-left-item:hover { background: rgba(255,255,255,0.05); color: var(--text-main); }
.idx-left-item.active { background: rgba(124,58,237,0.12); color: #c4b5fd; }
.idx-left-ico { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 0.95rem; background: rgba(124,58,237,0.15); color: #a78bfa; overflow: hidden; }
.idx-left-ico img { width: 100%; height: 100%; object-fit: cover; }
.idx-left-item.profile .idx-left-ico { background: linear-gradient(135deg,#7c3aed,#ec4899); color: #fff; font-weight: 900; }
.idx-left-sub { font-size: 0.68rem; color: var(--text-muted); font-weight: 600; margin-top: 1px; }
.idx-left-item.profile { padding: 0.6rem 0.65rem; margin-bottom: 0.4rem; border-bottom: 1px solid rgba(255,255,255,0.06); border-radius: 0; }
.idx-left-verified { color: #38bdf8; font-size: 0.7rem; margin-left: 0.2rem; }
.idx-left-sec { font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; padding: 0.85rem 0.65rem 0.35rem; margin-top: 0.3rem; border-top: 1px solid rgba(255,255,255,0.06); }
.idx-left-footer { padding: 0.85rem 0.65rem 0; font-size: 0.7rem; color: var(--text-muted); line-height: 1.5; margin-top: 0.4rem; border-top: 1px solid rgba(255,255,255,0.06); }
.idx-left-footer a { color: var(--text-muted); text-decoration: none; }
.idx-left-footer a:hover { color: var(--accent-light); }
.idx-left-footer .dot { margin: 0 0.25rem; opacity: 0.5; }
</style>

<div class="idx-layout">
<div class="idx-main-col">

<?php if ($home_user): ?>
<div style="background:linear-gradient(135deg, rgba(124,58,237,0.12), rgba(49,46,129,0.18)); border-bottom:1px solid rgba(139,92,246,0.2); padding:0.6rem 1rem;">
    <div style="max-width:1000px; margin:0 auto; display:flex; align-items:center; justify-content:space-between; gap:0.75rem; flex-wrap:wrap;">
        <div style="display:flex; align-items:center; gap:0.6rem; flex-wrap:wrap;">
            <div style="width:30px; height:30px; border-radius:50%; background:var(--accent); display:flex; align-items:center; justify-content:center; color:#fff; font-size:0.8rem; font-weight:800; flex-shrink:0;">
                <?= strtoupper(substr($home_user['display_name'] ?? $home_user['email'], 0, 1)) ?>
            </div>
            <div>
                <span style="font-weight:700; font-size:0.85rem; color:var(--text-main);"><?= htmlspecialchars($home_user['display_name'] ?? $home_user['email']) ?></span>
                <?php if ($home_team): ?>
                    <span style="font-size:0.72rem; color:var(--text-muted); margin-left:0.4rem;">
                        <i class="bi bi-people-fill"></i> <?= htmlspecialchars($home_team['team_name']) ?>
                        <span style="background:<?= $home_team['status'] === 'approved' ? 'rgba(52,211,153,0.15)' : 'rgba(251,191,36,0.15)' ?>; color:<?= $home_team['status'] === 'approved' ? '#34d399' : '#fbbf24' ?>; padding:0.05rem 0.4rem; border-radius:4px; font-size:0.65rem; font-weight:700; margin-left:0.2rem;"><?= $home_team['status'] === 'approved' ? 'Locked In' : ucfirst($home_team['status']) ?></span>
                    </span>
                <?php elseif ($home_solo): ?>
                    <span style="font-size:0.72rem; color:var(--text-muted); margin-left:0.4rem;">
                        <i class="bi bi-person-fill"></i> Solo — <?= htmlspecialchars($home_solo['player_name']) ?>
                        <span style="background:<?= $home_solo['status'] === 'approved' ? 'rgba(52,211,153,0.15)' : 'rgba(251,191,36,0.15)' ?>; color:<?= $home_solo['status'] === 'approved' ? '#34d399' : '#fbbf24' ?>; padding:0.05rem 0.4rem; border-radius:4px; font-size:0.65rem; font-weight:700; margin-left:0.2rem;"><?= $home_solo['status'] === 'approved' ? 'Locked In' : ucfirst($home_solo['status']) ?></span>
                    </span>
                <?php else: ?>
                    <span style="font-size:0.72rem; color:var(--text-muted); margin-left:0.4rem;">Not registered to any tournament or games</span>
                <?php endif; ?>
            </div>
        </div>
        <div style="display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap;">
            <a href="<?= base_url('dashboard.php') ?>" style="display:inline-flex; align-items:center; gap:0.3rem; background:rgba(56,189,248,0.1); border:1px solid rgba(56,189,248,0.3); border-radius:99px; padding:0.2rem 0.7rem; font-size:0.78rem; font-weight:700; color:var(--accent-cyan); text-decoration:none;">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <?php if (!$home_team && !$home_solo): ?>
                <a href="#games" style="display:inline-flex; align-items:center; gap:0.3rem; background:var(--accent); border-radius:99px; padding:0.2rem 0.7rem; font-size:0.78rem; font-weight:700; color:#fff; text-decoration:none;">
                    <i class="bi bi-plus-circle"></i> Register
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!$dota_reg_closed): ?>
<?php
$dota_waitlist_count = count($bracket_split['dota2']['waitlist'] ?? []);
$dota_reserved_count = count($reserved_teams['dota2'] ?? []);
$dota_paid_in_main = min($dota_paid_tc, 16);
$dota_unpaid_in_main = max(0, min(16, $dota_effective) - $dota_paid_in_main);
$dota_open_slots = max(0, 16 - $dota_effective);
$dota_all_paid = $dota_paid_in_main >= 16;
?>
<div class="season-banner">
    <div class="season-banner-inner">
        <div class="season-banner-headline">
            <div class="season-banner-title">
                <span class="season-banner-badge">Season 1</span>
                <span class="season-banner-game"><i class="bi bi-controller"></i> Dota 2</span>
                <span class="season-banner-status <?= $dota_all_paid ? 'locked' : ($dota_open_slots > 0 ? 'open' : 'partial') ?>">
                    <?php if ($dota_all_paid): ?>
                        <i class="bi bi-check-circle-fill"></i> Locked in
                    <?php elseif ($dota_open_slots > 0): ?>
                        <i class="bi bi-fire"></i> <?= $dota_open_slots ?> slot<?= $dota_open_slots !== 1 ? 's' : '' ?> open
                    <?php else: ?>
                        <i class="bi bi-hourglass-split"></i> Slots taken · <?= $dota_unpaid_in_main ?> unpaid
                    <?php endif; ?>
                </span>
            </div>
            <?php if ($dota_slots_left > 0): ?>
                <a href="#games" class="season-banner-cta season-banner-cta-primary">Register Now <i class="bi bi-arrow-down"></i></a>
            <?php elseif ($dota_waitlist_count > 0 || !$dota_all_paid): ?>
                <a href="#games" class="season-banner-cta season-banner-cta-secondary">
                    <?= $dota_all_paid ? 'Reserve for Next Tournament' : 'Claim an Unpaid Slot' ?> <i class="bi bi-arrow-down"></i>
                </a>
            <?php endif; ?>
        </div>

        <?php if ($dota_tc > 0): ?>
            <div class="slot-progress" aria-label="16 slot progress — <?= $dota_paid_in_main ?> paid, <?= $dota_unpaid_in_main ?> unpaid, <?= $dota_open_slots ?> open">
                <?php
                for ($i = 0; $i < 16; $i++):
                    if ($i < $dota_paid_in_main) $cls = 'paid';
                    elseif ($i < $dota_paid_in_main + $dota_unpaid_in_main) $cls = 'unpaid';
                    else $cls = 'open';
                ?>
                    <span class="slot-cell slot-<?= $cls ?>"></span>
                <?php endfor; ?>
                <span class="slot-progress-label">
                    <strong><?= $dota_paid_in_main ?>/16</strong> paid &amp; locked
                </span>
            </div>
        <?php endif; ?>

        <div class="season-banner-chips">
            <?php if (isset($dota_at_risk_count) && $dota_at_risk_count > 0): ?>
                <span class="sb-chip sb-chip-red">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <strong><?= $dota_at_risk_count ?></strong> unpaid at risk
                </span>
            <?php endif; ?>
            <?php if ($dota_waitlist_count > 0): ?>
                <span class="sb-chip sb-chip-gold">
                    <i class="bi bi-calendar-plus"></i>
                    <strong><?= $dota_waitlist_count ?></strong> on waitlist
                </span>
            <?php endif; ?>
            <?php if ($dota_reserved_count > 0): ?>
                <span class="sb-chip sb-chip-purple">
                    <i class="bi bi-bookmark-star-fill"></i>
                    <strong><?= $dota_reserved_count ?></strong> reserved for next
                </span>
            <?php endif; ?>
            <?php if ($dota_slots_left > 0): ?>
                <span class="sb-chip sb-chip-pale">
                    <i class="bi bi-hourglass-split"></i>
                    <strong><?= $dota_days_left ?></strong> day<?= $dota_days_left !== 1 ? 's' : '' ?> left
                </span>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div style="max-width:1000px; margin:0 auto 1rem; padding:0 1rem; text-align:center;">
    <div style="background:rgba(124,58,237,0.08); border:1px solid rgba(124,58,237,0.25); border-radius:10px; padding:0.6rem 1rem; font-size:0.8rem; color:var(--text-muted);">
        <i class="bi bi-building" style="color:var(--accent-light);"></i>
        This event is officially organized by <strong style="color:var(--accent-light);">Apex Cybernet</strong>.
        All rules, penalties, and final decisions are under the authority of Apex Cybernet.
    </div>
    <div style="background:var(--bg-card); border:1px solid var(--border); border-radius:10px; padding:0.6rem 1rem; font-size:0.85rem; color:var(--text-body); font-weight:600; margin-top:0.5rem;">
        <i class="bi bi-cash-coin"></i> Entry · <strong>₱550/team · ₱110/solo</strong> — pay via QR Ph (InstaPay) on the payment page after you register.
    </div>
    <div style="background:rgba(251,191,36,0.12); border:1px solid rgba(251,191,36,0.35); border-radius:10px; padding:0.6rem 1rem; font-size:0.85rem; color:#fbbf24; font-weight:700; margin-top:0.5rem;">
        <i class="bi bi-calendar-event"></i> CAN'T ATTEND? — Teams, registered participants, solo entries, and waiting list who cannot attend on tournament day will be rescheduled to the next season/tournament.
    </div>
</div>

<div class="sponsors-bar">
    <div class="sponsor-block">
        <span class="sponsor-label">Presented by</span>
        <div class="sponsor-logo">
            <img src="<?= base_url('images/apexcybernet-logo.svg') ?>" alt="Apex Cybernet" width="60" height="60" decoding="async">
            <div class="sponsor-text">
                <strong>APEX CYBERNET</strong>
                <span>TOURNAMENT</span>
            </div>
        </div>
    </div>
    <div class="sponsor-divider"></div>
    <div class="sponsor-block">
        <span class="sponsor-label" style="color:#fbbf24; letter-spacing:0.18em;"><i class="bi bi-geo-alt-fill" style="font-size:0.8em; margin-right:0.25rem;"></i> Official Venue</span>
        <div class="sponsor-logo">
            <img src="<?= base_url('images/pgl-ibabao.webp') ?>" alt="Apex Cybernet Cafe — tournament venue" class="venue-logo" width="60" height="60" decoding="async" style="object-fit:cover;">
            <div class="sponsor-text">
                <strong>APEX CYBERNET CAFE</strong>
                <span>CEBU CITY</span>
            </div>
        </div>

        <div style="display:flex; flex-direction:column; gap:0.4rem; margin-top:0.6rem; font-size:0.82rem;">
            <div style="color:var(--text-muted); display:flex; align-items:flex-start; gap:0.4rem; line-height:1.45;">
                <i class="bi bi-geo-alt-fill" style="color:#fbbf24; margin-top:0.15rem; flex-shrink:0;"></i>
                <span>7V94+HCQ, F. Jaca St, Cebu City, 6000 Cebu</span>
            </div>
            <div style="display:flex; flex-wrap:wrap; gap:0.4rem; margin-top:0.15rem;">
                <a href="https://www.google.com/maps/search/?api=1&query=7V94%2BHCQ+F.+Jaca+St+Cebu+City"
                   target="_blank" rel="noopener"
                   style="display:inline-flex; align-items:center; gap:0.35rem; padding:0.35rem 0.7rem; background:rgba(251,191,36,0.12); border:1px solid rgba(251,191,36,0.35); border-radius:999px; font-size:0.74rem; font-weight:700; color:#fbbf24; text-decoration:none; letter-spacing:0.05em;">
                    <i class="bi bi-map-fill"></i> Get directions
                </a>
            </div>
            <div style="display:flex; gap:0.4rem; align-items:flex-start; padding:0.45rem 0.6rem; background:var(--bg-subtle); border-left:2px solid var(--accent-light); border-radius:4px; margin-top:0.25rem; font-size:0.72rem; color:var(--text-body); line-height:1.45; font-weight:600;">
                <i class="bi bi-cash-coin" style="margin-top:0.15rem; flex-shrink:0; color:var(--accent-light);"></i>
                <span><strong>₱550/team · ₱110/solo</strong> entry. PC time paid at venue.</span>
            </div>
        </div>
    </div>
</div>

<div class="hero">
    <h1>Fight for Glory</h1>
    <p>The prize gets spent. The reputation stays. Every match is on record. Every win builds your legacy.</p>
    <?php if (!$dota_reg_closed && $dota_slots_left > 0): ?>
    <div style="display:flex; gap:0.75rem; justify-content:center; margin:1.25rem auto 1rem; max-width:420px;">
        <a href="<?= base_url('register.php') ?>?game=dota2" class="btn-register" style="padding:0.85rem 1.25rem; font-size:0.95rem;">
            <i class="bi bi-people-fill"></i> Register Team <span class="btn-price">₱550</span>
        </a>
        <a href="<?= base_url('matchmaking.php') ?>?game=dota2" class="btn-solo" style="padding:0.85rem 1.25rem; font-size:0.95rem;">
            <i class="bi bi-person-fill"></i> Solo Entry <span class="btn-price">₱110</span>
        </a>
    </div>
    <?php endif; ?>
    <div style="margin-top:1rem; padding:0.6rem 1.5rem; background:rgba(251,191,36,0.06); border:1px solid rgba(251,191,36,0.15); border-radius:10px; display:inline-block; max-width:500px;" id="heroQuoteBox">
        <div style="font-size:0.85rem; font-style:italic; color:#fbbf24; line-height:1.5; min-height:1.3em;" id="heroQuote"></div>
    </div>
    <script>
    (function(){
        var quotes = [
            'No one is going to hand me success. I must go out and get it myself.',
            'Every legend started as an unknown. This is your beginning.',
            'Your team trained for this. Now show everyone what you\'re made of.',
            'The stage is set. The bracket is waiting. Are you ready?',
            'Great players win games. Great teams win tournaments.',
            'This isn\'t just a game. It\'s your story being written.',
            'The crowd will cheer for the bold. Play fearless.',
            'One tournament can change everything. Make it count.',
            'You didn\'t come this far just to come this far.',
            'Trust your team. Trust your game. The rest will follow.',
            'Heroes are remembered. Legends never die.',
            'The best teams don\'t just play together. They fight together.',
            'Your next game could be the one everyone talks about.',
            'Pressure makes diamonds. Tournaments make champions.',
            'Behind every great play is a team that believed first.',
            'Today\'s underdog is tomorrow\'s champion.',
            'The bracket is just a path. You decide where it ends.',
            'Play like your whole city is watching. Because they will be.',
            'The throne is empty. Someone has to take it.',
            'Every click, every call, every teamfight — it all matters here.',
            'You don\'t need to be the best. You just need to play your best.',
            'When the game gets hard, the hard get going.',
            'Respect your opponent. Then outplay them.',
            'A team that communicates is a team that dominates.',
            'The draft doesn\'t win the game. Your heart does.',
            'No one remembers the teams that almost made it.',
            'Fortune favors the bold. So does the bracket.',
            'Your rank is a number. Your legacy is a story.',
            'This tournament is not the end. It\'s where it all starts.',
            'Show up. Step up. Stand out.',
            'Every game is a chance to prove something.',
            'The only thing standing between you and the trophy is the next match.',
            'Confidence is silent. Results are loud.',
            'Your opponents are preparing. Are you?',
            'The best moments in gaming come from tournaments like this.',
            'Leave everything on the battlefield. No regrets.',
            'It\'s not about the prize. It\'s about the glory.',
            'Five players. One goal. Zero excuses.',
            'Talent wins games, but teamwork wins championships.',
            'The whole community is watching. Make them remember.',
            'Dream big. Play bigger.',
            'This is more than a game. This is your moment.',
            'The road to the finals starts with one match. One decision. One play.',
            'A true champion lifts the whole team up.',
            'When you step into that bracket, leave your doubts behind.',
            'Some play for fun. Champions play for something more.',
            'The greatest glory is not in never falling, but in rising every time.',
            'Your team chose you for a reason. Don\'t let them down.',
            'Write your name in the Hall of Fame. It starts here.',
            'The tournament doesn\'t wait. Neither should you.',
            'Be the team that others wish they were on.'
        ];
        var el = document.getElementById('heroQuote');
        if (!el) return;
        var i = 0;
        el.textContent = quotes[i];
        setInterval(function(){ i = (i + 1) % quotes.length; el.style.opacity = 0; setTimeout(function(){ el.textContent = quotes[i]; el.style.opacity = 1; }, 400); }, 6000);
        el.style.transition = 'opacity 0.4s ease';
    })();
    </script>
    <div style="margin-top:1.25rem; font-size:1rem; font-style:italic; color:var(--text-muted); letter-spacing:0.2px;">
        The stage is set. The bracket is waiting. Are you ready?
    </div>
    <div class="winner-banner">
        <i class="bi bi-trophy-fill"></i> Winner Takes All — One champion per game. No runner-up, no second place.
    </div>
    <div class="winner-banner" style="margin-top:0.5rem; background:rgba(59,130,246,0.1); border-color:rgba(59,130,246,0.3); color:#60a5fa;">
        <i class="bi bi-bar-chart-fill"></i> Rank-Based Balancing — Brackets are seeded by average team rank for fair matchups. Your rank matters!
    </div>
    <div class="winner-banner" style="margin-top:0.5rem; background:rgba(124,58,237,0.1); border-color:rgba(124,58,237,0.3); color:var(--accent-light);">
        <i class="bi bi-diagram-3"></i> Double Elimination — Winners &amp; Losers bracket. You have to lose twice to be out.
    </div>
    <div class="winner-banner" style="margin-top:0.5rem; background:rgba(251,191,36,0.1); border-color:rgba(251,191,36,0.3); color:#fbbf24;">
        <i class="bi bi-star-fill"></i> Build Your Legacy — Champions are immortalized in the Hall of Fame. Your name. Your record. Every season.
    </div>
    <div class="prize-pick">
        <div class="prize-pick-title">Champion takes the cash prize:</div>
        <div class="prize-options" style="justify-content:center;">
            <div class="prize-option" style="max-width:320px;">
                <div class="prize-icon"><i class="bi bi-cash-stack"></i></div>
                <div class="prize-amount">&#8369;20,000</div>
                <div class="prize-desc">Split among the winning team</div>
            </div>
        </div>
        <div class="prize-note">Cash prize is subject to change and final organizer confirmation.</div>
        <div class="prize-note" style="color:var(--text-body); font-weight:600; font-style:normal; font-size:0.9rem; margin-top:0.5rem; background:var(--bg-subtle); border:1px solid var(--border); padding:0.6rem 1rem; border-radius:8px;">
            <i class="bi bi-cash-coin" style="color:var(--accent-light);"></i> Entry: <strong>₱550/team · ₱110/solo</strong>. Pay during registration to lock your slot.
        </div>
        <div style="
            margin-top:0.75rem;
            display:flex; align-items:center; gap:0.6rem;
            background:linear-gradient(135deg,rgba(251,146,60,0.18),rgba(239,68,68,0.12));
            border:1.5px solid rgba(251,146,60,0.5);
            border-radius:14px; padding:0.75rem 1.1rem;
            animation: pulseGlow 2.4s ease-in-out infinite;
        ">
            <span style="font-size:1.3rem; flex-shrink:0;">🏆</span>
            <div style="flex:1; min-width:0;">
                <div style="font-size:0.98rem; font-weight:900; color:#fff; letter-spacing:0.3px; line-height:1.2;">
                    TOURNAMENT STARTS JULY 11 · 11:00 AM
                </div>
                <div style="font-size:0.76rem; color:#fb923c; font-weight:600; margin-top:3px;">
                    <i class="bi bi-geo-alt-fill"></i> Apex Cybernet Cafe, F. Jaca St, Cebu City
                </div>
                <div style="display:flex; align-items:center; gap:0.55rem; margin-top:8px; padding:0.55rem 0.75rem; background:linear-gradient(135deg,rgba(251,191,36,0.2),rgba(245,158,11,0.12)); border:2px solid #fbbf24; border-radius:10px; box-shadow:0 0 0 2px rgba(251,191,36,0.15), 0 0 12px rgba(251,191,36,0.25);">
                    <i class="bi bi-bell-fill" style="font-size:1.2rem; color:#fbbf24; animation:pulseGlow 1.4s ease-in-out infinite;"></i>
                    <div style="flex:1; min-width:0;">
                        <div style="font-size:0.62rem; font-weight:900; color:#fde68a; letter-spacing:2px; text-transform:uppercase;">CALL TIME — BE THERE BY</div>
                        <div style="font-size:1.3rem; font-weight:900; color:#fbbf24; line-height:1.05; letter-spacing:-0.5px;">10:00 AM</div>
                        <div style="font-size:0.66rem; color:#fde68a; font-weight:700; margin-top:1px;">First match starts 1 hour later at 11:00 AM</div>
                    </div>
                </div>
                <div style="font-size:0.72rem; color:#fca5a5; font-weight:700; margin-top:6px;">
                    <i class="bi bi-stopwatch-fill"></i> 15-minute grace period — late = forfeit = lose
                </div>
            </div>
        </div>
        <div class="prize-note" style="color:#fbbf24; font-weight:700; font-style:normal; font-size:0.9rem; margin-top:0.5rem; background:rgba(251,191,36,0.08); border:1px solid rgba(251,191,36,0.25); padding:0.6rem 1rem; border-radius:8px;">
            <i class="bi bi-calendar-event"></i> Can't attend on tournament day? Paid teams, registered participants, solo entries, and paid waiting list will be rescheduled to the next season/tournament.
        </div>

        <!-- ── Season 1 Champion spotlight ── -->
        <div class="s1-champ-card">
            <div class="s1-champ-ribbon">
                <i class="bi bi-trophy-fill"></i>
                SEASON 1 CHAMPION
            </div>
            <div class="s1-champ-photo">
                <img src="<?= base_url('images/season1-champion-teknova.jpg') ?>" alt="Teknova — Season 1 Champions of Apex Cybernet Dota 2 Tournament" loading="lazy">
                <div class="s1-champ-photo-overlay">
                    <div class="s1-champ-team-name">TEKNOVA</div>
                    <div class="s1-champ-sub"><i class="bi bi-crown-fill"></i> Grand Finals winner · Apex Cybernet Dota 2 · Season 1</div>
                </div>
            </div>
            <div class="s1-champ-footer">
                <div class="s1-champ-placements">
                    <span class="s1-champ-place gold"><i class="bi bi-1-circle-fill"></i> Teknova</span>
                    <span class="s1-champ-place silver"><i class="bi bi-2-circle-fill"></i> Toledotes</span>
                    <span class="s1-champ-place bronze"><i class="bi bi-3-circle-fill"></i> CREAMSILK NI LAPAD</span>
                </div>
                <a href="<?= base_url('leaderboard.php') ?>" class="s1-champ-link">Full Hall of Fame <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
        <style>
        .s1-champ-card { position: relative; background: linear-gradient(135deg, rgba(251,191,36,0.08), rgba(239,68,68,0.06)); border: 2px solid rgba(251,191,36,0.5); border-radius: 16px; padding: 0; margin: 1.25rem 0; overflow: hidden; box-shadow: 0 0 0 2px rgba(251,191,36,0.1), 0 12px 40px rgba(0,0,0,0.4); }
        .s1-champ-ribbon { position: absolute; top: 0.85rem; left: 0.85rem; z-index: 3; display: inline-flex; align-items: center; gap: 0.35rem; background: linear-gradient(135deg,#fbbf24,#d97706); color:#1f1300; font-weight: 900; font-size: 0.68rem; letter-spacing: 2px; padding: 0.35rem 0.8rem; border-radius: 6px; text-transform: uppercase; box-shadow: 0 4px 12px rgba(217,119,6,0.4); }
        .s1-champ-photo { position: relative; aspect-ratio: 16/9; background: #0a0a0f; overflow: hidden; }
        .s1-champ-photo img { width: 100%; height: 100%; object-fit: cover; display: block; filter: saturate(1.1) contrast(1.05); }
        .s1-champ-photo-overlay { position: absolute; left: 0; right: 0; bottom: 0; padding: 1.1rem 1.25rem 0.95rem; background: linear-gradient(to top, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0.55) 55%, transparent 100%); }
        .s1-champ-team-name { font-size: 1.85rem; font-weight: 900; color: #fff; letter-spacing: 2px; text-shadow: 0 2px 12px rgba(0,0,0,0.5); line-height: 1; }
        .s1-champ-sub { font-size: 0.78rem; color: #fbbf24; font-weight: 700; margin-top: 0.35rem; display: flex; align-items: center; gap: 0.35rem; }
        .s1-champ-sub i { color: #fde047; }
        .s1-champ-footer { padding: 0.85rem 1.1rem 0.95rem; display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; flex-wrap: wrap; background: rgba(0,0,0,0.25); border-top: 1px solid rgba(251,191,36,0.15); }
        .s1-champ-placements { display: flex; flex-wrap: wrap; gap: 0.45rem; font-size: 0.72rem; font-weight: 700; }
        .s1-champ-place { display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.2rem 0.55rem; border-radius: 999px; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); }
        .s1-champ-place.gold   { color: #fbbf24; border-color: rgba(251,191,36,0.4); background: rgba(251,191,36,0.08); }
        .s1-champ-place.silver { color: #d1d5db; border-color: rgba(209,213,219,0.3); background: rgba(209,213,219,0.06); }
        .s1-champ-place.bronze { color: #f59e0b; border-color: rgba(180,83,9,0.4); background: rgba(180,83,9,0.08); }
        .s1-champ-link { font-size: 0.78rem; font-weight: 800; color: #fbbf24; text-decoration: none; display: inline-flex; align-items: center; gap: 0.3rem; white-space: nowrap; }
        .s1-champ-link:hover { color: #fde047; }
        @media (max-width: 520px) {
            .s1-champ-team-name { font-size: 1.35rem; }
            .s1-champ-photo-overlay { padding: 0.85rem 0.95rem 0.75rem; }
            .s1-champ-ribbon { top: 0.6rem; left: 0.6rem; font-size: 0.6rem; padding: 0.25rem 0.6rem; }
        }
        </style>

    </div>
</div>

<?php
$max_teams = 16;
// Solo players form teams of 5
function estimate_date($team_count, $solo_count, $fixed_date = null) {
    if ($fixed_date) {
        $date = date('F j, Y', strtotime($fixed_date));
        return "Tournament date: $date";
    }
    $total = $team_count + floor($solo_count / 5);
    if ($total >= 16) {
        $date = date('F j, Y', strtotime('+1 week'));
        return "Slots full! Target date: $date";
    }
    if ($total >= 12) {
        $date = date('F j', strtotime('+2 weeks'));
        return "Almost full — target date: $date";
    }
    if ($total >= 8) {
        $date = date('F j', strtotime('+3 weeks'));
        return "Filling up — estimated: $date";
    }
    if ($total >= 4) {
        $date = date('F j', strtotime('+4 weeks'));
        return "Building up — estimated: $date";
    }
    return 'Recruiting — date TBA once 8+ teams register';
}

// Determine countdown: pick the game with the most registrations
$best_game = null;
$best_total = 0;
$countdown_target = null;
$countdown_label = '';
foreach ($games as $g) {
    $tc = $counts[$g['slug']] ?? 0;
    $sc = $solo_counts[$g['slug']] ?? 0;
    $total = $tc + $sc;
    if ($total > $best_total) {
        $best_total = $total;
        $best_game = $g;
    }
}
// Countdown to registration deadline (actionable) or tournament date (if reg closed)
if ($best_game && !empty($best_game['reg_deadline']) && strtotime($best_game['reg_deadline'] . ' 23:59:59') > time()) {
    $countdown_target = $best_game['reg_deadline'];
    $countdown_label = 'Registration closes in';
} elseif ($best_game && !empty($best_game['date']) && strtotime($best_game['date']) > time()) {
    $countdown_target = $best_game['date'];
    $countdown_label = $best_game['name'] . ' tournament starts in';
} elseif ($best_total >= 16) {
    $countdown_target = date('Y-m-d', strtotime('+1 week'));
    $countdown_label = 'Tournament starts in';
} elseif ($best_total >= 8) {
    $countdown_target = date('Y-m-d', strtotime('+3 weeks'));
    $countdown_label = 'Estimated tournament date';
}
?>

<div class="countdown-section" id="countdownSection">
    <?php if ($countdown_target): ?>
        <?php if (!empty($best_game['date']) && !empty($best_game['time'])): ?>
        <div style="
            display:inline-flex; align-items:center; gap:0.6rem;
            background:linear-gradient(135deg,rgba(251,146,60,0.18),rgba(239,68,68,0.12));
            border:1.5px solid rgba(251,146,60,0.5);
            border-radius:14px; padding:0.65rem 1.4rem;
            margin-bottom:1rem;
            animation: pulseGlow 2.4s ease-in-out infinite;
        ">
            <span style="font-size:1.3rem;">🏆</span>
            <div>
                <div style="font-size:1.05rem; font-weight:900; color:#fff; letter-spacing:0.3px; line-height:1.2;">
                    TOURNAMENT STARTS JULY 11 · 11:00 AM
                </div>
                <div style="font-size:0.78rem; color:#fb923c; font-weight:600; margin-top:2px;">
                    <i class="bi bi-geo-alt-fill"></i> Apex Cybernet Cafe, F. Jaca St, Cebu City
                </div>
            </div>
        </div>
        <style>
        @keyframes pulseGlow {
            0%,100% { box-shadow: 0 0 0 0 rgba(251,146,60,0); }
            50%      { box-shadow: 0 0 18px 4px rgba(251,146,60,0.22); }
        }
        </style>
        <?php endif; ?>
        <div class="countdown-heading" style="margin-top:0.25rem;"><?= $countdown_label ?></div>
        <div class="countdown-timer" data-target="<?= $countdown_target ?>">
            <div class="countdown-unit">
                <div class="countdown-number" id="cdDays">--</div>
                <div class="countdown-label">Days</div>
            </div>
            <div class="countdown-sep">:</div>
            <div class="countdown-unit">
                <div class="countdown-number" id="cdHours">--</div>
                <div class="countdown-label">Hours</div>
            </div>
            <div class="countdown-sep">:</div>
            <div class="countdown-unit">
                <div class="countdown-number" id="cdMins">--</div>
                <div class="countdown-label">Minutes</div>
            </div>
            <div class="countdown-sep">:</div>
            <div class="countdown-unit">
                <div class="countdown-number" id="cdSecs">--</div>
                <div class="countdown-label">Seconds</div>
            </div>
        </div>
    <?php else: ?>
        <div class="countdown-heading">Tournament Season</div>
        <div class="countdown-tba">
            <i class="bi bi-calendar-event"></i> Date TBA — <a href="#games">Register now!</a>
        </div>
        <div class="countdown-sub">Registration closing soon. Secure your slot before it fills up.</div>
    <?php endif; ?>
</div>

<!-- ── Live Chat Widget ── -->
<?php
$chat_initial = [];
$chat_last_id = 0;
try {
    $chat_initial = $pdo->query("SELECT id, display_name, message, created_at
        FROM cafe_comments ORDER BY id DESC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);
    $chat_initial = array_reverse($chat_initial);
    $chat_last_id = !empty($chat_initial) ? (int)end($chat_initial)['id'] : 0;
} catch (Exception $e) {}
$chat_logged_in = !empty($_SESSION['account_id']);
?>
<style>
.idx-chat-wrap {
    max-width: 520px;
    margin: 0 auto 2rem;
    padding: 0 1rem;
}
.idx-chat-box {
    background: var(--surface, rgba(255,255,255,0.04));
    border: 1px solid var(--border, rgba(255,255,255,0.08));
    border-radius: 14px;
    display: flex; flex-direction: column;
    height: 340px; overflow: hidden;
}
.idx-chat-header {
    padding: 0.6rem 1rem;
    border-bottom: 1px solid var(--border, rgba(255,255,255,0.08));
    font-size: 0.8rem; font-weight: 700; color: var(--text, #e5e7eb);
    display: flex; align-items: center; gap: 0.5rem; flex-shrink: 0;
}
.idx-live-pill {
    background: #e53e3e; color: #fff;
    font-size: 0.6rem; font-weight: 800; letter-spacing: 0.06em;
    padding: 0.1rem 0.45rem; border-radius: 4px;
}
.idx-live-dot {
    width: 6px; height: 6px; border-radius: 50%;
    background: #e53e3e; animation: pulse 1.2s infinite; flex-shrink: 0;
}
.idx-chat-messages {
    flex: 1; overflow-y: auto; padding: 0.6rem 0.9rem;
    display: flex; flex-direction: column; gap: 0.4rem;
    scroll-behavior: smooth;
}
.idx-chat-messages::-webkit-scrollbar { width: 3px; }
.idx-chat-messages::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 2px; }
.idx-chat-msg { display: flex; align-items: baseline; gap: 0.4rem; font-size: 0.78rem; animation: fadeInUp 0.2s ease; }
.idx-chat-msg-name { font-weight: 700; flex-shrink: 0; font-size: 0.72rem; }
.idx-chat-msg-text { color: var(--text, #e5e7eb); line-height: 1.4; word-break: break-word; }
.idx-chat-msg-time { font-size: 0.6rem; color: #4b5563; flex-shrink: 0; margin-left: auto; white-space: nowrap; }
.idx-chat-input-wrap {
    padding: 0.6rem 0.9rem;
    border-top: 1px solid var(--border, rgba(255,255,255,0.08));
    flex-shrink: 0;
    display: flex; gap: 0.5rem;
}
.idx-chat-input {
    flex: 1;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px; color: #e5e7eb; font-size: 0.78rem;
    padding: 0.4rem 0.7rem; outline: none;
    font-family: inherit;
}
.idx-chat-input:focus { border-color: rgba(124,58,237,0.5); }
.idx-chat-send {
    background: var(--accent, #7c3aed); border: none; color: #fff;
    border-radius: 8px; padding: 0 0.85rem; cursor: pointer;
    font-size: 0.78rem; font-weight: 700; flex-shrink: 0;
    transition: opacity 0.15s;
}
.idx-chat-send:hover { opacity: 0.85; }
.idx-chat-send:disabled { opacity: 0.4; cursor: not-allowed; }
.idx-chat-emoji-btn {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    color: #e5e7eb;
    border-radius: 8px; padding: 0 0.55rem;
    cursor: pointer; font-size: 0.95rem; flex-shrink: 0;
    line-height: 1; font-family: inherit;
}
.idx-chat-emoji-btn:hover { border-color: rgba(124,58,237,0.5); }
.idx-chat-emoji-pop {
    position: absolute;
    bottom: calc(100% + 6px); right: 0;
    width: 240px; max-height: 220px; overflow-y: auto;
    background: #16161d; border: 1px solid rgba(255,255,255,0.12);
    border-radius: 10px; padding: 0.4rem;
    display: grid; grid-template-columns: repeat(8, 1fr); gap: 2px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.4);
    z-index: 50;
}
.idx-chat-emoji-pop button {
    background: transparent; border: none; padding: 0.25rem 0;
    font-size: 1.05rem; cursor: pointer; border-radius: 4px; line-height: 1;
}
.idx-chat-emoji-pop button:hover { background: rgba(124,58,237,0.2); }
.idx-chat-input-wrap { position: relative; }
.chat-mention {
    color: #c4b5fd;
    background: rgba(124,58,237,0.18);
    border: 1px solid rgba(124,58,237,0.35);
    border-radius: 4px;
    padding: 0 0.25rem;
    font-weight: 700;
}
.idx-mention-pop {
    position: absolute;
    bottom: 100%;
    left: 0.5rem;
    right: 0.5rem;
    max-height: 180px;
    overflow-y: auto;
    background: #0f0f13;
    border: 1px solid rgba(124,58,237,0.4);
    border-radius: 8px;
    margin-bottom: 0.35rem;
    z-index: 60;
    box-shadow: 0 6px 20px rgba(0,0,0,0.45);
}
.idx-mention-pop-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.4rem 0.6rem;
    font-size: 0.78rem;
    cursor: pointer;
    color: var(--text);
}
.idx-mention-pop-item:hover, .idx-mention-pop-item.active {
    background: rgba(124,58,237,0.2);
}
.idx-mention-pop-item img {
    width: 22px; height: 22px; border-radius: 50%; object-fit: cover;
    background: rgba(255,255,255,0.05);
}
</style>
<div id="livechat"></div>
<div class="idx-chat-wrap">
    <div class="idx-chat-box">
        <div class="idx-chat-header">
            <span class="idx-live-dot"></span>
            <i class="bi bi-chat-dots-fill" style="color:#a78bfa;"></i>
            Live Chat
            <span class="idx-live-pill">LIVE</span>
        </div>
        <div class="idx-chat-messages" id="idxChatMessages">
            <?php foreach ($chat_initial as $c):
                $hue = (crc32($c['display_name']) & 0x7FFFFFFF) % 360;
                $col = "hsl({$hue},65%,65%)";
            ?>
            <div class="idx-chat-msg">
                <span class="idx-chat-msg-name" style="color:<?= $col ?>;"><?= htmlspecialchars($c['display_name']) ?></span>
                <span class="idx-chat-msg-text"><?= preg_replace('/@([A-Za-z0-9_\.\-]{2,30})/', '<span class="chat-mention">@$1</span>', htmlspecialchars($c['message'])) ?></span>
                <span class="idx-chat-msg-time"><?= date('H:i', strtotime($c['created_at'])) ?></span>
            </div>
            <?php endforeach; ?>
            <?php if (empty($chat_initial)): ?>
            <div id="idxChatEmpty" style="color:#4b5563; font-size:0.75rem; text-align:center; margin:auto;">Be the first to say something!</div>
            <?php endif; ?>
        </div>
        <div class="idx-chat-input-wrap">
            <?php if ($chat_logged_in): ?>
            <input type="text" class="idx-chat-input" id="idxChatInput"
                placeholder="Say something…" maxlength="300" autocomplete="off">
            <button type="button" class="idx-chat-emoji-btn" id="idxChatEmojiBtn" title="Emoji">😊</button>
            <button class="idx-chat-send" id="idxChatSend" onclick="idxSendChat()">
                <i class="bi bi-send-fill"></i>
            </button>
            <div class="idx-chat-emoji-pop" id="idxChatEmojiPop" style="display:none;"></div>
            <?php else: ?>
            <div style="flex:1; text-align:center; font-size:0.72rem; color:#6b7280; padding:0.2rem 0;">
                <a href="<?= base_url('login.php') ?>" style="color:#a78bfa; font-weight:700; text-decoration:none;"><i class="bi bi-box-arrow-in-right"></i> Log in</a> to join the chat
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
(function(){
    let idxLastId = <?= $chat_last_id ?>;
    let idxAtBottom = true;
    const idxBox = document.getElementById('idxChatMessages');

    idxBox.addEventListener('scroll', () => {
        idxAtBottom = idxBox.scrollHeight - idxBox.scrollTop - idxBox.clientHeight < 50;
    });
    idxBox.scrollTop = idxBox.scrollHeight;

    function idxNameColor(name) {
        let h = 0;
        for (let i = 0; i < name.length; i++) h = name.charCodeAt(i) + ((h << 5) - h);
        return `hsl(${Math.abs(h) % 360},65%,65%)`;
    }
    function idxEsc(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function idxMentions(s) {
        return s.replace(/@([A-Za-z0-9_.\-]{2,30})/g, '<span class="chat-mention">@$1</span>');
    }
    function idxRenderMsg(c) {
        const div = document.createElement('div');
        div.className = 'idx-chat-msg';
        const t = new Date(c.created_at.replace(' ','T'));
        const hh = String(t.getHours()).padStart(2,'0');
        const mm = String(t.getMinutes()).padStart(2,'0');
        div.innerHTML =
            `<span class="idx-chat-msg-name" style="color:${idxNameColor(c.display_name)};">${idxEsc(c.display_name)}</span>` +
            `<span class="idx-chat-msg-text">${idxMentions(idxEsc(c.message))}</span>` +
            `<span class="idx-chat-msg-time">${hh}:${mm}</span>`;
        return div;
    }

    function idxPoll() {
        fetch(`index.php?ajax=comments&after=${idxLastId}`)
            .then(r => r.json())
            .then(comments => {
                if (!comments.length) return;
                const empty = document.getElementById('idxChatEmpty');
                if (empty) empty.remove();
                comments.forEach(c => {
                    idxBox.appendChild(idxRenderMsg(c));
                    idxLastId = Math.max(idxLastId, parseInt(c.id));
                });
                if (idxAtBottom) idxBox.scrollTop = idxBox.scrollHeight;
            }).catch(() => {});
    }
    setInterval(idxPoll, 2000);

    window.idxSendChat = function() {
        const input = document.getElementById('idxChatInput');
        const btn   = document.getElementById('idxChatSend');
        const msg   = input?.value.trim();
        if (!msg) return;
        btn.disabled = true;
        fetch('index.php?ajax=post', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ message: msg })
        })
        .then(r => r.json())
        .then(d => {
            if (d.ok) {
                input.value = '';
                const empty = document.getElementById('idxChatEmpty');
                if (empty) empty.remove();
                idxBox.appendChild(idxRenderMsg(d.comment));
                idxLastId = Math.max(idxLastId, parseInt(d.comment.id));
                idxBox.scrollTop = idxBox.scrollHeight;
            }
            btn.disabled = false;
            input.focus();
        }).catch(() => { btn.disabled = false; });
    };

    // ── @mention autocomplete ──
    const idxInput = document.getElementById('idxChatInput');
    const idxInputWrap = idxInput?.closest('.idx-chat-input-wrap');
    let idxMentionPop = null;
    let idxMentionItems = [];
    let idxMentionActive = 0;
    let idxMentionFetchSeq = 0;

    function idxClosePop() {
        if (idxMentionPop) { idxMentionPop.remove(); idxMentionPop = null; }
        idxMentionItems = []; idxMentionActive = 0;
    }
    function idxActivePart() {
        if (!idxInput) return null;
        const pos = idxInput.selectionStart ?? idxInput.value.length;
        const before = idxInput.value.slice(0, pos);
        const m = before.match(/@([A-Za-z0-9_\.\-]{1,30})$/);
        if (!m) return null;
        return { query: m[1], start: pos - m[0].length, end: pos };
    }
    function idxInsertMention(name, part) {
        if (!idxInput || !part) return;
        const token = '@' + name.replace(/\s+/g, '') + ' ';
        idxInput.value = idxInput.value.slice(0, part.start) + token + idxInput.value.slice(part.end);
        const p = part.start + token.length;
        idxInput.setSelectionRange(p, p);
        idxInput.focus();
        idxClosePop();
    }
    function idxShowMentions(users, part) {
        idxClosePop();
        if (!users.length || !idxInputWrap) return;
        idxMentionItems = users.slice(0, 8);
        idxMentionActive = 0;
        idxMentionPop = document.createElement('div');
        idxMentionPop.className = 'idx-mention-pop';
        idxMentionItems.forEach((u, i) => {
            const el = document.createElement('div');
            el.className = 'idx-mention-pop-item' + (i === 0 ? ' active' : '');
            const img = u.profile_picture ? `<img src="${u.profile_picture}" alt="">` : '<div style="width:22px;height:22px;border-radius:50%;background:rgba(124,58,237,0.25);"></div>';
            el.innerHTML = img + `<span style="font-weight:700;">${idxEsc(u.display_name)}</span>`;
            el.addEventListener('mousedown', ev => { ev.preventDefault(); idxInsertMention(u.display_name, part); });
            idxMentionPop.appendChild(el);
        });
        idxInputWrap.appendChild(idxMentionPop);
    }
    idxInput?.addEventListener('input', () => {
        const part = idxActivePart();
        if (!part || part.query.length < 1) { idxClosePop(); return; }
        const seq = ++idxMentionFetchSeq;
        fetch('api/chat-users.php?q=' + encodeURIComponent(part.query))
            .then(r => r.json())
            .then(data => {
                if (seq !== idxMentionFetchSeq) return;
                const users = Array.isArray(data) ? data : (data.users || []);
                const refreshed = idxActivePart();
                if (!refreshed) { idxClosePop(); return; }
                idxShowMentions(users, refreshed);
            }).catch(() => {});
    });

    document.getElementById('idxChatInput')?.addEventListener('keydown', e => {
        if (idxMentionPop && idxMentionItems.length) {
            if (e.key === 'ArrowDown') { e.preventDefault(); idxMentionActive = (idxMentionActive + 1) % idxMentionItems.length; [...idxMentionPop.children].forEach((c, i) => c.classList.toggle('active', i === idxMentionActive)); return; }
            if (e.key === 'ArrowUp')   { e.preventDefault(); idxMentionActive = (idxMentionActive - 1 + idxMentionItems.length) % idxMentionItems.length; [...idxMentionPop.children].forEach((c, i) => c.classList.toggle('active', i === idxMentionActive)); return; }
            if (e.key === 'Enter' || e.key === 'Tab') {
                e.preventDefault();
                const part = idxActivePart();
                if (part && idxMentionItems[idxMentionActive]) idxInsertMention(idxMentionItems[idxMentionActive].display_name, part);
                return;
            }
            if (e.key === 'Escape') { idxClosePop(); return; }
        }
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); idxSendChat(); }
    });
    idxInput?.addEventListener('blur', () => setTimeout(idxClosePop, 150));

    // ── Emoji picker ──
    const idxEmojis = [
        '😀','😁','😂','🤣','😊','😍','😎','🤩',
        '😘','🥰','😇','🙂','😉','😋','😜','🤪',
        '🤔','🤨','😐','😶','🙄','😏','😒','😳',
        '😔','😞','😢','😭','😤','😡','🤬','🥵',
        '😱','😨','😰','🥺','😴','🤤','🤧','🤒',
        '🤯','😵','🤠','🥳','🤓','😷','🤐','🫡',
        '👍','👎','👏','🙏','💪','🤝','👌','✌️',
        '🤞','👊','🫶','🫰','🤟','🤘','👋','🖐️',
        '❤️','🧡','💛','💚','💙','💜','🖤','🤍',
        '💔','💯','🔥','✨','⭐','🌟','💫','🎉',
        '🎊','🏆','🥇','🥈','🥉','🎮','🕹️','⚔️',
        '💰','💸','💎','📈','📉','🚀','⚡','💥',
        'GG','GLHF','WP','OP','EZ','LUL','POG','F'
    ];
    const idxPop = document.getElementById('idxChatEmojiPop');
    const idxBtn = document.getElementById('idxChatEmojiBtn');
    if (idxPop && idxBtn) {
        idxEmojis.forEach(em => {
            const b = document.createElement('button');
            b.type = 'button';
            b.textContent = em;
            if (em.length > 2) { b.style.fontSize = '0.65rem'; b.style.fontWeight = '800'; b.style.color = '#a78bfa'; }
            b.addEventListener('click', ev => {
                ev.stopPropagation();
                const inp = document.getElementById('idxChatInput');
                if (!inp) return;
                const s = inp.selectionStart ?? inp.value.length;
                const e = inp.selectionEnd   ?? inp.value.length;
                const tok = (em.length > 2) ? em + ' ' : em;
                inp.value = inp.value.slice(0, s) + tok + inp.value.slice(e);
                const pos = s + tok.length;
                inp.focus();
                inp.setSelectionRange(pos, pos);
            });
            idxPop.appendChild(b);
        });
        idxBtn.addEventListener('click', ev => {
            ev.stopPropagation();
            idxPop.style.display = (idxPop.style.display === 'none') ? 'grid' : 'none';
        });
        document.addEventListener('click', ev => {
            if (idxPop.style.display === 'grid' && !idxPop.contains(ev.target) && ev.target !== idxBtn) {
                idxPop.style.display = 'none';
            }
        });
    }
})();
</script>

<?php if ($total_players > 0): ?>
<div style="max-width:1000px; margin:0 auto 1.5rem; padding:0 1rem;">
    <div style="display:flex; justify-content:center; gap:1.5rem; flex-wrap:wrap;">
        <div style="background:rgba(59,130,246,0.1); border:1px solid rgba(59,130,246,0.25); border-radius:12px; padding:0.75rem 1.5rem; text-align:center; min-width:120px;">
            <div style="font-size:1.75rem; font-weight:800; color:#60a5fa;"><?= $total_players ?></div>
            <div style="font-size:0.75rem; color:var(--text-muted); font-weight:600;">Players Registered</div>
        </div>
        <div style="background:rgba(34,197,94,0.1); border:1px solid rgba(34,197,94,0.25); border-radius:12px; padding:0.75rem 1.5rem; text-align:center; min-width:120px;">
            <div style="font-size:1.75rem; font-weight:800; color:var(--success);"><?= $paid_teams + $paid_solos ?></div>
            <div style="font-size:0.75rem; color:var(--text-muted); font-weight:600;">Confirmed & Paid</div>
        </div>
        <?php if ($unpaid_total > 0): ?>
        <div style="background:rgba(249,115,22,0.1); border:1px solid rgba(249,115,22,0.25); border-radius:12px; padding:0.75rem 1.5rem; text-align:center; min-width:120px;">
            <div style="font-size:1.75rem; font-weight:800; color:#fb923c;"><?= $unpaid_total ?></div>
            <div style="font-size:0.75rem; color:var(--text-muted); font-weight:600;">Unpaid</div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div style="max-width:1000px; margin:0 auto 1.5rem; padding:0 1rem; display:flex; flex-direction:column; gap:0.75rem;">
    <div style="background:rgba(124,58,237,0.08); border:1px solid rgba(124,58,237,0.25); border-radius:12px; padding:1rem 1.5rem; display:flex; align-items:center; gap:0.75rem; flex-wrap:wrap;">
        <i class="bi bi-person-plus-fill" style="font-size:1.5rem; color:var(--accent-light);"></i>
        <div>
            <div style="font-weight:700; font-size:0.95rem; color:var(--text);">Don't have a team?</div>
            <div style="font-size:0.85rem; color:var(--text-muted);">Join as a solo entry! The system will pick a team for you based on your actual skill level. Just choose "Solo Entry" on any game below.</div>
        </div>
    </div>
    <?php $any_locked_banner = !empty(array_filter($waitlist_locked_map)); ?>
    <div style="background:rgba(52,211,153,0.08); border:1px solid rgba(52,211,153,0.3); border-radius:12px; padding:1rem 1.5rem; display:flex; align-items:center; gap:0.75rem; flex-wrap:wrap;">
        <i class="bi <?= $any_locked_banner ? 'bi-lock-fill' : 'bi-check-circle-fill' ?>" style="font-size:1.5rem; color:#34d399;"></i>
        <div>
            <?php if ($any_locked_banner): ?>
                <div style="font-weight:700; font-size:0.95rem; color:var(--text);">Slots are locked in</div>
                <div style="font-size:0.85rem; color:var(--text-muted);">Organizers have locked the registered field. New registrations join the waitlist; getting in now requires a withdrawal or organizer approval.</div>
            <?php else: ?>
                <div style="font-weight:700; font-size:0.95rem; color:var(--text);">Lock your slot — pay to confirm</div>
                <div style="font-size:0.85rem; color:var(--text-muted);">₱550/team · ₱110/solo entry. Slots are first-come, first-served. Once 16 teams register the field is locked and the rest go to the waitlist.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="games-grid" id="games">
    <?php foreach ($featured_games as $game):
        $tc = $counts[$game['slug']] ?? 0;
        $sc = $solo_counts[$game['slug']] ?? 0;
        $ptc = $paid_team_counts[$game['slug']] ?? 0;
        $psc = $paid_solo_counts[$game['slug']] ?? 0;
        // All registrations count as slots, but unpaid can be replaced anytime by paid waitlist
        $solo_slots = (int) floor($psc / 5);
        $effective = $tc + $solo_slots;
        $pct = min(100, round(($effective / $max_teams) * 100));
        $slots_left = max(0, $max_teams - $effective);
        $date_est = estimate_date($tc, $psc, $game['date'] ?? null);
        $solo_remaining_for_next_slot = $psc > 0 ? (5 - ($psc % 5)) % 5 : 5;
        $reg_deadline = $game['reg_deadline'] ?? null;
        $reg_closed = $reg_deadline && strtotime($reg_deadline . ' 23:59:59') < time();
        $days_left = $reg_deadline ? max(0, (int)ceil((strtotime($reg_deadline . ' 23:59:59') - time()) / 86400)) : null;
        $is_featured = !empty($game['featured']);
    ?>
        <div class="game-card <?= $is_featured ? 'game-card-featured' : 'game-card-secondary' ?>">
            <div class="game-banner">
                <img src="<?= base_url($game['logo']) ?>" alt="<?= $game['name'] ?>" class="game-logo">
                <div class="game-focus-pill <?= $is_featured ? 'game-focus-pill-featured' : 'game-focus-pill-secondary' ?>">
                    <?= htmlspecialchars($game['focus_note']) ?>
                </div>
                <div class="game-title"><?= $game['name'] ?></div>
            </div>
            <div class="game-body">
                <p class="desc"><?= $game['desc'] ?></p>

                <?php if ($is_featured): ?>
                    <div class="game-focus-copy">
                        <i class="bi bi-stars"></i> Featured tournament registration is open.
                    </div>
                <?php else: ?>
                    <div class="game-focus-copy game-focus-copy-secondary">
                        <i class="bi bi-dash-circle"></i> Kept available, but currently de-emphasized while we focus on Dota 2.
                    </div>
                <?php endif; ?>

                <div class="slot-tracker">
                    <div class="slot-info">
                        <span><strong><?= $effective ?></strong> / <?= $max_teams ?> teams</span>
                        <?php if ($slots_left === 0 && ($tc - $ptc) > 0): ?>
                            <span class="slots-left" style="color:#fbbf24;"><i class="bi bi-clock-history"></i> Waitlist open</span>
                        <?php elseif ($slots_left === 0): ?>
                            <span class="slots-left" style="color:#f87171;">Full</span>
                        <?php else: ?>
                            <span class="slots-left"><?= $slots_left ?> slot(s) left</span>
                        <?php endif; ?>
                    </div>
                    <div class="slot-bar">
                        <div class="slot-fill" style="width: <?= $pct ?>%"></div>
                    </div>
                    <?php if ($psc > 0 || $sc > 0): ?>
                        <div style="font-size:0.7rem; color:var(--text-muted); margin-top:0.4rem; line-height:1.4;">
                            <i class="bi bi-info-circle"></i>
                            <strong style="color:var(--accent-light);"><?= $psc ?></strong>/5 paid solo<?= $psc !== 1 ? 's' : '' ?>
                            <?php if ($psc < 5): ?>
                                — need <strong style="color:#fbbf24;"><?= 5 - $psc ?></strong> more paid solo<?= (5 - $psc) !== 1 ? 's' : '' ?> to form 1 team slot
                            <?php else: ?>
                                — forms <strong style="color:var(--success);"><?= $solo_slots ?></strong> team slot<?= $solo_slots !== 1 ? 's' : '' ?>
                                <?php if ($solo_remaining_for_next_slot > 0 && $solo_remaining_for_next_slot < 5): ?>
                                    (<?= $solo_remaining_for_next_slot ?> more for next slot)
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if ($sc > $psc): ?>
                                <br><span style="color:#fb923c;"><i class="bi bi-exclamation-circle"></i> <?= $sc - $psc ?> unpaid solo<?= ($sc - $psc) !== 1 ? 's' : '' ?> not yet counted toward slots</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div class="slot-date"><i class="bi bi-calendar-event"></i> <?= $date_est ?><?= !empty($game['time']) ? ' · <strong>' . htmlspecialchars($game['time']) . '</strong>' : '' ?></div>
                    <?php if ($reg_deadline): ?>
                        <div class="slot-date" style="color:<?= $reg_closed ? 'var(--danger)' : '#f59e0b' ?>;">
                            <i class="bi bi-clock-fill"></i>
                            <?php if ($reg_closed): ?>
                                Registration closed
                            <?php else: ?>
                                Register by <?= date('F j, Y', strtotime($reg_deadline)) ?>
                            <?php endif; ?>
                        </div>
                        <?php if (!$reg_closed && $days_left !== null): ?>
                            <div class="reg-days-left">
                                <i class="bi bi-hourglass-split"></i>
                                <strong><?= $days_left ?></strong> day<?= $days_left !== 1 ? 's' : '' ?> left to register
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="game-stats">
                    <span class="teams-count">
                        <i class="bi bi-people-fill"></i> <?= $tc ?> team(s)
                    </span>
                    <span class="teams-count">
                        <i class="bi bi-person-fill"></i> <?= $sc ?> solo player(s)
                    </span>
                </div>
                <?php if (!$reg_closed && $slots_left > 0 && $slots_left <= 8): ?>
                    <div style="background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.25); border-radius:8px; padding:0.5rem 0.75rem; font-size:0.8rem; color:#f87171; text-align:center; margin-bottom:0.5rem; font-weight:600;">
                        <i class="bi bi-fire"></i> Only <strong><?= $slots_left ?></strong> slot<?= $slots_left !== 1 ? 's' : '' ?> remaining — <?= $effective ?>/<?= $max_teams ?> filled
                    </div>
                <?php endif; ?>
                <?php if (!$reg_closed && $slots_left === 0): ?>
                    <?php $unpaid_in_game = $tc - $ptc; ?>
                    <div style="background:rgba(251,191,36,0.08); border:1px solid rgba(251,191,36,0.3); border-radius:10px; padding:0.75rem 1rem; margin-bottom:0.6rem; font-size:0.8rem; color:#fbbf24; line-height:1.6;">
                        <?php if ($unpaid_in_game > 0): ?>
                            <div style="font-weight:700; margin-bottom:0.25rem;"><i class="bi bi-clock-history"></i> Bracket is full — waitlist is open</div>
                            <div style="color:var(--text-muted); font-size:0.75rem;">
                                <strong style="color:#fb923c;"><?= $unpaid_in_game ?> unpaid team<?= $unpaid_in_game !== 1 ? 's' : '' ?></strong> hold unconfirmed spots.
                                Register and pay now — if any of them don't pay, your spot moves up <strong style="color:#fbbf24;">automatically</strong>.
                            </div>
                        <?php else: ?>
                            <div style="font-weight:700; margin-bottom:0.25rem;"><i class="bi bi-shield-fill-check" style="color:var(--success);"></i> All <?= $max_teams ?> slots confirmed</div>
                            <div style="color:var(--text-muted); font-size:0.75rem;">Join the waitlist — you'll move in automatically if any slot opens up before the deadline.</div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="game-actions">
                    <?php if ($reg_closed): ?>
                        <div class="btn-register" style="opacity:0.5; cursor:default;">
                            <i class="bi bi-lock-fill"></i> Registration Closed
                        </div>
                    <?php elseif ($slots_left > 0): ?>
                        <a href="<?= base_url('register.php') ?>?game=<?= $game['slug'] ?>" class="btn-register">
                            <i class="bi bi-people-fill"></i> Register Team <span class="btn-price">₱550</span>
                        </a>
                        <a href="<?= base_url('matchmaking.php') ?>?game=<?= $game['slug'] ?>" class="btn-solo">
                            <i class="bi bi-person-fill"></i> Solo Entry <span class="btn-price">₱110</span>
                        </a>
                    <?php else: ?>
                        <a href="<?= base_url('register.php') ?>?game=<?= $game['slug'] ?>" class="btn-register" style="background:#fbbf24; color:#0f0f13;">
                            <i class="bi bi-clock-history"></i> Join Waitlist (Team) <span class="btn-price" style="background:rgba(52,211,153,0.18);color:#34d399;">FREE</span>
                        </a>
                        <a href="<?= base_url('matchmaking.php') ?>?game=<?= $game['slug'] ?>" class="btn-solo" style="border-color:#fbbf24; color:#fbbf24;">
                            <i class="bi bi-clock-history"></i> Waitlist Solo <span class="btn-price" style="background:rgba(52,211,153,0.18);color:#34d399;">FREE</span>
                        </a>
                    <?php endif; ?>
                </div>
                <div class="share-buttons">
                    <a href="https://www.facebook.com/sharer/sharer.php?u=https://apexcybernet.com" target="_blank" rel="noopener" class="btn-share-fb" title="Share on Facebook">
                        <i class="bi bi-facebook"></i> Share
                    </a>
                    <a href="fb-messenger://share/?link=https://apexcybernet.com" class="btn-share-msg" title="Send via Messenger">
                        <i class="bi bi-messenger"></i> Send
                    </a>
                    <button type="button" class="btn-copy-link" onclick="copyLink(this)" title="Copy link">
                        <i class="bi bi-link-45deg"></i> Copy Link
                    </button>
                </div>

            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php
// Total waitlisted entries across all games
$total_waitlist_teams = 0;
$total_waitlist_solos = 0;
foreach ($bracket_split as $bs) { $total_waitlist_teams += count($bs['waitlist']); }
foreach ($solo_split as $ss)    { $total_waitlist_solos += count($ss['waitlist']); }
?>

<?php if ($total_waitlist_teams > 0 || $total_waitlist_solos > 0): ?>
<div class="registered-section" style="border-top:1px dashed rgba(251,191,36,0.3); padding-top:1.5rem;">
    <h2 style="color:#fbbf24;"><i class="bi bi-clock-history"></i> Waiting List</h2>
    <?php $any_locked = !empty(array_filter($waitlist_locked_map)); ?>
    <div style="text-align:center; max-width:700px; margin:0 auto 1.25rem; font-size:0.85rem; color:var(--text-muted); line-height:1.6; padding:0.85rem 1rem; background:rgba(251,191,36,0.06); border:1px solid rgba(251,191,36,0.25); border-radius:10px;">
        <strong style="color:#fbbf24;">How the waitlist works:</strong>
        <?php if ($any_locked): ?>
            Registered participants are <strong style="color:#fbbf24;">locked in</strong> — new payments no longer bump existing slots. Waitlist entries get in only if a registered team withdraws or on organizer discretion.
        <?php else: ?>
            Slots are full, but you can still register and join the waiting list.
            <br><span style="color:#fbbf24; font-weight:600;"><i class="bi bi-star-fill"></i> If a registered team withdraws, the next waitlist entry takes the slot.</span>
        <?php endif; ?>
    </div>

    <?php foreach ($featured_games as $game):
        $wl_teams = $bracket_split[$game['slug']]['waitlist'] ?? [];
        $wl_solos = $solo_split[$game['slug']]['waitlist'] ?? [];
        if (empty($wl_teams) && empty($wl_solos)) continue;
    ?>
        <div class="registered-game">
            <h3 style="color:#fbbf24;"><i class="bi bi-controller"></i> <?= $game['name'] ?> Waitlist (<?= count($wl_teams) + count($wl_solos) ?>)</h3>
            <div class="registered-list">
                <?php foreach ($wl_teams as $i => $team):
                    $is_paid = $team['status'] === 'approved';
                ?>
                    <div class="registered-team <?= !empty($team['team_logo']) ? 'has-logo' : '' ?>"
                         style="<?= $is_paid ? 'border:1px solid rgba(34,197,94,0.4); box-shadow:0 0 12px rgba(34,197,94,0.1);' : 'border:1px dashed rgba(251,191,36,0.3);' ?>">
                        <?php if (!empty($team['team_logo'])): ?>
                            <img src="<?= base_url($team['team_logo']) ?>" alt="<?= htmlspecialchars($team['team_name']) ?> team logo" class="team-logo-img" loading="lazy" decoding="async">
                        <?php endif; ?>
                        <div style="flex:1; min-width:0;">
                            <div class="team-name">#<?= $i + 1 ?> · <?= htmlspecialchars($team['team_name']) ?>
                                <?php if (!empty($team['power'])): ?>
                                    <span class="team-power-badge" title="Team Power — sum of member rank tiers">Power <?= (int)$team['power'] ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="team-type"><i class="bi bi-people-fill"></i> Team</div>
                            <?php if ($is_paid): ?>
                                <span style="display:inline-flex; align-items:center; gap:0.2rem; font-size:0.65rem; color:var(--success); background:rgba(34,197,94,0.1); border:1px solid rgba(34,197,94,0.3); padding:0.15rem 0.5rem; border-radius:6px;">
                                    <i class="bi bi-check-circle-fill"></i> Paid · Will replace any unpaid team after Apr 17
                                </span>
                            <?php else: ?>
                                <a href="<?= base_url('ticket.php') ?>?ref=<?= urlencode($team['ref_code']) ?>&type=team&game=<?= $game['slug'] ?>"
                                   style="display:inline-flex; align-items:center; gap:0.2rem; font-size:0.65rem; color:#fb923c; background:rgba(249,115,22,0.1); border:1px solid rgba(249,115,22,0.25); padding:0.15rem 0.5rem; border-radius:6px; text-decoration:none;">
                                    <i class="bi bi-exclamation-circle-fill"></i> Pay now to claim a slot
                                </a>
                            <?php endif; ?>
                            <div class="team-members-list">
                                <div class="team-member-row team-member-header">
                                    <span>Player</span>
                                    <span>Rank</span>
                                </div>
                                <?php
                                $members_data = !empty($team['members_ranks']) ? explode('|', $team['members_ranks']) : [];
                                if (!empty($members_data) && $members_data[0] !== ':'):
                                    foreach ($members_data as $mi => $entry):
                                        $parts = explode(':', $entry, 2);
                                        $mname = $parts[0] ?? '';
                                        $mrank = $parts[1] ?? '';
                                        if (empty($mname)) continue;
                                ?>
                                    <div class="team-member-row">
                                        <span class="team-member-name"><?= $mi === 0 ? '<i class="bi bi-star-fill" style="color:#fbbf24; font-size:0.6rem;" title="Captain"></i> ' : '' ?><?= htmlspecialchars($mname) ?></span>
                                        <span class="team-member-rank">
                                            <?= !empty($mrank) ? htmlspecialchars($mrank) : '—' ?>
                                            <?php if (strtolower(trim($team['team_name'])) === 'team jakolerns'): ?>
                                            <br><span style="color:#fbbf24; font-size:0.7em; font-weight:700;">★ Immortal</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                <?php
                                    endforeach;
                                else:
                                    for ($mi = 1; $mi <= 5; $mi++):
                                        if (empty($team["member_$mi"])) continue;
                                ?>
                                    <div class="team-member-row">
                                        <span class="team-member-name"><?= $mi === 1 ? '<i class="bi bi-star-fill" style="color:#fbbf24; font-size:0.6rem;" title="Captain"></i> ' : '' ?><?= htmlspecialchars($team["member_$mi"]) ?></span>
                                        <span class="team-member-rank">—</span>
                                    </div>
                                <?php
                                    endfor;
                                endif;
                                ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php foreach ($wl_solos as $i => $solo):
                    $is_paid = $solo['status'] === 'approved';
                ?>
                    <div class="registered-team <?= !empty($solo['profile_photo']) ? 'has-logo' : '' ?>"
                         style="<?= $is_paid ? 'border:1px solid rgba(34,197,94,0.4); box-shadow:0 0 12px rgba(34,197,94,0.1);' : 'border:1px dashed rgba(251,191,36,0.3);' ?>">
                        <?php if (!empty($solo['profile_photo'])): ?>
                            <img src="<?= base_url($solo['profile_photo']) ?>" alt="<?= htmlspecialchars($solo['player_name']) ?> profile photo" class="team-logo-img" style="border-radius:50%;" loading="lazy" decoding="async">
                        <?php endif; ?>
                        <div style="flex:1; min-width:0;">
                            <div class="team-name"><?= htmlspecialchars($solo['player_name']) ?></div>
                            <div class="team-type"><i class="bi bi-person-fill"></i> Solo &middot; <?= htmlspecialchars($solo['rank_tier']) ?></div>
                            <?php if (!empty($solo['preferred_role'])): ?>
                                <span style="display:inline-flex; align-items:center; gap:0.2rem; font-size:0.65rem; color:#60a5fa; background:rgba(59,130,246,0.1); border:1px solid rgba(59,130,246,0.25); padding:0.15rem 0.5rem; border-radius:6px; margin-bottom:0.2rem;">
                                    <i class="bi bi-joystick"></i> <?= htmlspecialchars($solo['preferred_role']) ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($is_paid): ?>
                                <span style="display:inline-flex; align-items:center; gap:0.2rem; font-size:0.65rem; color:var(--success); background:rgba(34,197,94,0.1); border:1px solid rgba(34,197,94,0.3); padding:0.15rem 0.5rem; border-radius:6px;">
                                    <i class="bi bi-check-circle-fill"></i> Paid · Counted toward next solo team
                                </span>
                            <?php else: ?>
                                <a href="<?= base_url('ticket.php') ?>?ref=<?= urlencode($solo['ref_code']) ?>&type=solo&game=<?= $game['slug'] ?>"
                                   style="display:inline-flex; align-items:center; gap:0.2rem; font-size:0.65rem; color:#fb923c; background:rgba(249,115,22,0.1); border:1px solid rgba(249,115,22,0.25); padding:0.15rem 0.5rem; border-radius:6px; text-decoration:none;">
                                    <i class="bi bi-exclamation-circle-fill"></i> Pay now to claim a slot
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php
$total_reserved_teams = array_sum(array_map('count', $reserved_teams));
$total_reserved_solos = array_sum(array_map('count', $reserved_solos));
?>
<?php if ($total_reserved_teams > 0 || $total_reserved_solos > 0): ?>
<div class="registered-section" style="border-top:1px dashed rgba(124,58,237,0.3); padding-top:1.5rem;">
    <h2 style="color:#a78bfa;"><i class="bi bi-bookmark-star-fill"></i> Reserved</h2>
    <div style="text-align:center; max-width:700px; margin:0 auto 1.25rem; font-size:0.85rem; color:var(--text-muted); line-height:1.6; padding:0.85rem 1rem; background:rgba(124,58,237,0.06); border:1px solid rgba(124,58,237,0.25); border-radius:10px;">
        <strong style="color:#a78bfa;">Reserved entries:</strong>
        These teams and players paid but couldn't join the tournament start. Their slots are held — they're excluded from the active bracket but remain on record, and will be <strong style="color:#c4b5fd;">rescheduled to the next tournament</strong>.
    </div>

    <?php foreach ($featured_games as $game):
        $rs_teams = $reserved_teams[$game['slug']] ?? [];
        $rs_solos = $reserved_solos[$game['slug']] ?? [];
        if (empty($rs_teams) && empty($rs_solos)) continue;
    ?>
        <div class="registered-game">
            <h3 style="color:#a78bfa;"><i class="bi bi-controller"></i> <?= $game['name'] ?> Reserved (<?= count($rs_teams) + count($rs_solos) ?>)</h3>
            <div class="registered-list">
                <?php foreach ($rs_teams as $team): ?>
                    <div class="registered-team <?= !empty($team['team_logo']) ? 'has-logo' : '' ?>"
                         style="border:1px solid rgba(124,58,237,0.35); background:rgba(124,58,237,0.04);">
                        <?php if (!empty($team['team_logo'])): ?>
                            <img src="<?= base_url($team['team_logo']) ?>" alt="<?= htmlspecialchars($team['team_name']) ?> team logo" class="team-logo-img" loading="lazy" decoding="async">
                        <?php endif; ?>
                        <div style="flex:1; min-width:0;">
                            <div class="team-name"><?= htmlspecialchars($team['team_name']) ?>
                                <?php if (!empty($team['power'])): ?>
                                    <span class="team-power-badge" title="Team Power — sum of member rank tiers">Power <?= (int)$team['power'] ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="team-type"><i class="bi bi-people-fill"></i> Team</div>
                            <span style="display:inline-flex; align-items:center; gap:0.2rem; font-size:0.65rem; color:#c4b5fd; background:rgba(124,58,237,0.12); border:1px solid rgba(124,58,237,0.35); padding:0.15rem 0.5rem; border-radius:6px;">
                                <i class="bi bi-bookmark-star-fill"></i> Slot reserved — not in active bracket
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php foreach ($rs_solos as $solo): ?>
                    <div class="registered-team <?= !empty($solo['profile_photo']) ? 'has-logo' : '' ?>"
                         style="border:1px solid rgba(124,58,237,0.35); background:rgba(124,58,237,0.04);">
                        <?php if (!empty($solo['profile_photo'])): ?>
                            <img src="<?= base_url($solo['profile_photo']) ?>" alt="<?= htmlspecialchars($solo['player_name']) ?> profile photo" class="team-logo-img" style="border-radius:50%;" loading="lazy" decoding="async">
                        <?php endif; ?>
                        <div style="flex:1; min-width:0;">
                            <div class="team-name"><?= htmlspecialchars($solo['player_name']) ?></div>
                            <div class="team-type"><i class="bi bi-person-fill"></i> Solo &middot; <?= htmlspecialchars($solo['rank_tier']) ?></div>
                            <span style="display:inline-flex; align-items:center; gap:0.2rem; font-size:0.65rem; color:#c4b5fd; background:rgba(124,58,237,0.12); border:1px solid rgba(124,58,237,0.35); padding:0.15rem 0.5rem; border-radius:6px;">
                                <i class="bi bi-bookmark-star-fill"></i> Slot reserved — not in active matchmaking
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="registered-section">
    <h2>Registered Participants</h2>
    <?php if ($unpaid_total > 0): ?>
        <div style="text-align:center; font-size:0.8rem; color:var(--text-muted); margin-bottom:1rem; line-height:1.6;">
            <span style="color:var(--success); font-weight:700;"><i class="bi bi-shield-fill-check"></i> <?= $paid_teams + $paid_solos ?> locked in</span> &middot;
            <span style="color:#fb923c;"><?= $unpaid_total ?> still unpaid</span> — can be replaced anytime by a paid team.
            <br><span style="color:#fbbf24; font-weight:600;"><i class="bi bi-star-fill"></i> Pay early = priority seeding + guaranteed slot</span>
        </div>
    <?php endif; ?>

    <?php foreach ($featured_games as $game): ?>
        <?php
        // Use main bracket only — waitlisted entries are shown in the Waiting List section above
        $teams = $bracket_split[$game['slug']]['main'] ?? ($registered_teams[$game['slug']] ?? []);
        $solos = $solo_split[$game['slug']]['main'] ?? ($solo_players[$game['slug']] ?? []);
        if (empty($teams) && empty($solos)) continue;
        // Already sorted (paid first, then by created_at)
        ?>
        <div class="registered-game">
            <h3><i class="bi bi-controller"></i> <?= $game['name'] ?></h3>
            <div class="registered-list">
                <?php foreach ($teams as $team):
                    $is_paid = $team['status'] === 'approved';
                ?>
                    <div class="registered-team <?= !empty($team['team_logo']) ? 'has-logo' : '' ?>"
                         style="<?= $is_paid ? 'border:1px solid rgba(251,191,36,0.35); box-shadow:0 0 16px rgba(251,191,36,0.08), 0 0 4px rgba(124,58,237,0.1); background:rgba(251,191,36,0.03);' : '' ?>">
                        <?php if (!empty($team['team_logo'])): ?>
                            <img src="<?= base_url($team['team_logo']) ?>" alt="<?= htmlspecialchars($team['team_name']) ?> team logo" class="team-logo-img" loading="lazy" decoding="async">
                        <?php endif; ?>
                        <div style="flex:1; min-width:0;">
                            <div class="team-name"><?= htmlspecialchars($team['team_name']) ?>
                                <?php if (!empty($team['power'])): ?>
                                    <span class="team-power-badge" title="Team Power — sum of member rank tiers">Power <?= (int)$team['power'] ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="team-type"><i class="bi bi-people-fill"></i> Team</div>
                            <?php if ($is_paid): ?>
                                <span style="display:inline-flex; align-items:center; gap:0.2rem; font-size:0.65rem; color:#fbbf24; background:rgba(251,191,36,0.1); border:1px solid rgba(251,191,36,0.25); padding:0.15rem 0.5rem; border-radius:6px;">
                                    <i class="bi bi-shield-fill-check"></i> Slot Locked In
                                </span>
                            <?php elseif ($team['status'] === 'pending'): ?>
                                <a href="<?= base_url('ticket.php') ?>?ref=<?= urlencode($team['ref_code']) ?>&type=team&game=<?= $game['slug'] ?>"
                                   style="display:inline-flex; align-items:center; gap:0.2rem; font-size:0.65rem; color:#fb923c; background:rgba(249,115,22,0.1); border:1px solid rgba(249,115,22,0.25); padding:0.15rem 0.5rem; border-radius:6px; text-decoration:none;">
                                    <i class="bi bi-exclamation-circle-fill"></i> Unpaid — Click here to Pay
                                </a>
                                <?php if (strtolower(trim($team['team_name'])) === 'team jakolerns'): ?>
                                <div style="margin-top:0.4rem; padding:0.4rem 0.6rem; background:rgba(251,191,36,0.08); border-left:3px solid rgba(251,191,36,0.5); border-radius:6px; font-size:0.65rem; color:#fcd34d; line-height:1.4;">
                                    ⚠️ Following multiple reports regarding potential rank manipulation, and in order to preserve competitive integrity, the organizers have adjusted all team members' ranks to the highest applicable level based on internal evaluation.
                                </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="team-status <?= $team['status'] ?>"><?= $team['status'] ?></span>
                            <?php endif; ?>
                            <div class="team-members-list">
                                <div class="team-member-row team-member-header">
                                    <span>Player</span>
                                    <span>Rank</span>
                                </div>
                                <?php
                                // Parse members_ranks (format: name:rank|name:rank|...)
                                $members_data = !empty($team['members_ranks']) ? explode('|', $team['members_ranks']) : [];
                                if (!empty($members_data) && $members_data[0] !== ':'):
                                    foreach ($members_data as $mi => $entry):
                                        $parts = explode(':', $entry, 2);
                                        $mname = $parts[0] ?? '';
                                        $mrank = $parts[1] ?? '';
                                        if (empty($mname)) continue;
                                ?>
                                    <div class="team-member-row">
                                        <span class="team-member-name"><?= $mi === 0 ? '<i class="bi bi-star-fill" style="color:#fbbf24; font-size:0.6rem;" title="Captain"></i> ' : '' ?><?= htmlspecialchars($mname) ?></span>
                                        <span class="team-member-rank">
                                            <?= !empty($mrank) ? htmlspecialchars($mrank) : '—' ?>
                                            <?php if (strtolower(trim($team['team_name'])) === 'team jakolerns'): ?>
                                            <br><span style="color:#fbbf24; font-size:0.7em; font-weight:700;">★ Immortal</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                <?php
                                    endforeach;
                                else:
                                    // Fallback to individual member columns
                                    for ($mi = 1; $mi <= 5; $mi++):
                                        if (empty($team["member_$mi"])) continue;
                                ?>
                                    <div class="team-member-row">
                                        <span class="team-member-name"><?= $mi === 1 ? '<i class="bi bi-star-fill" style="color:#fbbf24; font-size:0.6rem;" title="Captain"></i> ' : '' ?><?= htmlspecialchars($team["member_$mi"]) ?></span>
                                        <span class="team-member-rank">—</span>
                                    </div>
                                <?php
                                    endfor;
                                endif;
                                ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php foreach ($solos as $solo):
                    $is_paid = $solo['status'] === 'approved';
                ?>
                    <div class="registered-team <?= !empty($solo['profile_photo']) ? 'has-logo' : '' ?>"
                         style="<?= $is_paid ? 'border:1px solid rgba(251,191,36,0.35); box-shadow:0 0 16px rgba(251,191,36,0.08), 0 0 4px rgba(124,58,237,0.1); background:rgba(251,191,36,0.03);' : '' ?>">
                        <?php if (!empty($solo['profile_photo'])): ?>
                            <img src="<?= base_url($solo['profile_photo']) ?>" alt="<?= htmlspecialchars($solo['player_name']) ?> profile photo" class="team-logo-img" style="border-radius:50%;" loading="lazy" decoding="async">
                        <?php endif; ?>
                        <div style="flex:1; min-width:0;">
                            <div class="team-name"><?= htmlspecialchars($solo['player_name']) ?></div>
                            <div class="team-type"><i class="bi bi-person-fill"></i> Solo &middot; <?= htmlspecialchars($solo['rank_tier']) ?></div>
                            <?php if (!empty($solo['preferred_role'])): ?>
                                <span style="display:inline-flex; align-items:center; gap:0.2rem; font-size:0.65rem; color:#60a5fa; background:rgba(59,130,246,0.1); border:1px solid rgba(59,130,246,0.25); padding:0.15rem 0.5rem; border-radius:6px; margin-bottom:0.2rem;">
                                    <i class="bi bi-joystick"></i> <?= htmlspecialchars($solo['preferred_role']) ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($is_paid): ?>
                                <span style="display:inline-flex; align-items:center; gap:0.2rem; font-size:0.65rem; color:#fbbf24; background:rgba(251,191,36,0.1); border:1px solid rgba(251,191,36,0.25); padding:0.15rem 0.5rem; border-radius:6px;">
                                    <i class="bi bi-shield-fill-check"></i> Slot Locked In
                                </span>
                            <?php elseif ($solo['status'] === 'pending'): ?>
                                <a href="<?= base_url('ticket.php') ?>?ref=<?= urlencode($solo['ref_code']) ?>&type=solo&game=<?= $game['slug'] ?>"
                                   style="display:inline-flex; align-items:center; gap:0.2rem; font-size:0.65rem; color:#fb923c; background:rgba(249,115,22,0.1); border:1px solid rgba(249,115,22,0.25); padding:0.15rem 0.5rem; border-radius:6px; text-decoration:none;">
                                    <i class="bi bi-exclamation-circle-fill"></i> Unpaid — Click here to Pay
                                </a>
                            <?php else: ?>
                                <span class="team-status <?= $solo['status'] ?>"><?= $solo['status'] ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>


    <?php if (empty($registered_teams) && empty($solo_players)): ?>
        <p class="no-teams" style="text-align:center;">No participants registered yet. Be the first!</p>
    <?php endif; ?>
</div>

<?php
// --- Live Bracket Preview ---
$bracket_games = [];
$bracket_team_info = [];
foreach ($featured_games as $fg) {
    $bstmt = $pdo->prepare("SELECT * FROM matches WHERE game = ? AND bracket_side IN ('winners','losers','grand') ORDER BY bracket_side ASC, round ASC, match_order ASC");
    $bstmt->execute([$fg['slug']]);
    $bmatches = $bstmt->fetchAll();
    if (!empty($bmatches)) {
        $grouped = [];
        foreach ($bmatches as $bm) {
            $grouped[$bm['bracket_side']][$bm['round']][] = $bm;
        }
        $bracket_games[$fg['slug']] = ['name' => $fg['name'], 'data' => $grouped];
        // Look up team logos and payment status
        $ti_stmt = $pdo->prepare("SELECT team_name, team_logo, status FROM teams WHERE game = ?");
        $ti_stmt->execute([$fg['slug']]);
        foreach ($ti_stmt->fetchAll() as $ti) {
            $bracket_team_info[$ti['team_name']] = ['logo' => $ti['team_logo'], 'paid' => $ti['status'] === 'approved'];
        }
    }
}
?>
<?php if (!empty($bracket_games)): ?>
<div class="registered-section" id="bracket-preview">
    <h2><i class="bi bi-diagram-3"></i> Tournament Bracket</h2>
    <div style="text-align:center; max-width:700px; margin:0 auto 1.25rem; font-size:0.85rem; color:var(--text-muted); line-height:1.6; padding:0.85rem 1rem; background:rgba(124,58,237,0.06); border:1px solid rgba(124,58,237,0.25); border-radius:10px;">
        <strong style="color:var(--accent-light);">Double Elimination</strong> — Winners bracket, losers bracket, and grand finals. You have to lose twice to be eliminated.
        <br><a href="<?= base_url('bracket.php') ?>" style="color:var(--accent-light); text-decoration:underline;">View full bracket page</a>
    </div>

    <?php foreach ($bracket_games as $bg_slug => $bg):
        $bg_data = $bg['data'];
        $side_meta = [
            'winners' => ['label' => 'Winners Bracket', 'icon' => 'bi-trophy-fill',  'class' => 'winners'],
            'losers'  => ['label' => 'Losers Bracket',  'icon' => 'bi-arrow-repeat',  'class' => 'losers'],
            'grand'   => ['label' => 'Grand Finals',    'icon' => 'bi-star-fill',     'class' => 'grand'],
        ];
    ?>
        <div class="registered-game">
            <h3><i class="bi bi-controller"></i> <?= htmlspecialchars($bg['name']) ?></h3>

            <?php foreach (['winners', 'losers', 'grand'] as $side):
                if (empty($bg_data[$side])) continue;
                $meta = $side_meta[$side];
                $side_rounds = $bg_data[$side];
                $round_keys = array_keys($side_rounds);
            ?>
                <div class="bracket-section">
                    <div class="bracket-section-header bracket-section-<?= $meta['class'] ?>">
                        <i class="bi <?= $meta['icon'] ?>"></i>
                        <span><?= $meta['label'] ?></span>
                    </div>
                    <div class="bracket-container">
                        <?php
                        $rendered = 0;
                        foreach ($round_keys as $idx => $round_num):
                            $round_matches = $side_rounds[$round_num];
                            $visible = array_filter($round_matches, fn($bm) => $bm['team1_name'] !== 'BYE' && $bm['team2_name'] !== 'BYE');
                            if (empty($visible)) continue;
                            $label = bracketRoundName($side, $round_num);
                            $format = bracketMatchFormat($side, $round_num);
                        ?>
                            <?php if ($rendered > 0): ?>
                                <div class="bracket-connector-col"><div class="bracket-connector-line"></div></div>
                            <?php endif; ?>
                            <div class="bracket-round">
                                <div class="bracket-round-title"><?= $label ?> <span class="bracket-format-badge"><?= $format ?></span></div>
                                <?php foreach ($visible as $bm):
                                    $t1_info = $bracket_team_info[$bm['team1_name']] ?? null;
                                    $t2_info = $bracket_team_info[$bm['team2_name']] ?? null;
                                    $t1_tbd = empty($bm['team1_name']) || $bm['team1_name'] === 'TBD';
                                    $t2_tbd = empty($bm['team2_name']) || $bm['team2_name'] === 'TBD';
                                    $is_done = $bm['status'] === 'completed';
                                    $is_live = $bm['status'] === 'live';
                                ?>
                                    <?php if ($t1_tbd && $t2_tbd): ?>
                                        <div class="bracket-match tbd-match">
                                            <div class="tbd-placeholder"><i class="bi bi-clock-history"></i> <span>Awaiting results</span></div>
                                        </div>
                                    <?php else: ?>
                                        <div class="bracket-match <?= $bm['status'] ?>">
                                            <?php if ($is_live): ?><div class="bracket-match-live-bar"></div><?php endif; ?>
                                            <div class="team-row <?= ($bm['winner'] && $bm['winner'] === $bm['team1_name']) ? 'winner' : (($is_done && $bm['winner'] && $bm['winner'] !== $bm['team1_name']) ? 'loser' : '') ?>">
                                                <?php if (!$t1_tbd && $t1_info && !empty($t1_info['logo'])): ?>
                                                    <img src="<?= base_url($t1_info['logo']) ?>" alt="" class="bracket-team-logo" loading="lazy" decoding="async">
                                                <?php else: ?>
                                                    <div class="bracket-team-logo-placeholder"></div>
                                                <?php endif; ?>
                                                <span class="team-name <?= $t1_tbd ? 'tbd' : '' ?>"><?= htmlspecialchars($bm['team1_name'] ?: 'TBD') ?></span>
                                                <?php if (!$t1_tbd && strtolower(trim($bm['team1_name'])) === 'team jakolerns'): ?>
                                                    <span style="font-size:0.6rem; font-weight:700; color:#fbbf24; background:rgba(251,191,36,0.15); border:1px solid rgba(251,191,36,0.4); padding:0.1rem 0.4rem; border-radius:4px; white-space:nowrap;" title="Rank raised to Immortal by organizers">★ Immortal</span>
                                                <?php endif; ?>
                                                <?php if (!$t1_tbd && $t1_info && !$t1_info['paid']): ?>
                                                    <span class="bracket-unpaid" title="Unpaid"><i class="bi bi-exclamation-circle-fill"></i></span>
                                                <?php endif; ?>
                                                <span class="team-score"><?= $bm['team1_score'] ?></span>
                                            </div>
                                            <div class="team-row-divider"></div>
                                            <div class="team-row <?= ($bm['winner'] && $bm['winner'] === $bm['team2_name']) ? 'winner' : (($is_done && $bm['winner'] && $bm['winner'] !== $bm['team2_name']) ? 'loser' : '') ?>">
                                                <?php if (!$t2_tbd && $t2_info && !empty($t2_info['logo'])): ?>
                                                    <img src="<?= base_url($t2_info['logo']) ?>" alt="" class="bracket-team-logo" loading="lazy" decoding="async">
                                                <?php else: ?>
                                                    <div class="bracket-team-logo-placeholder"></div>
                                                <?php endif; ?>
                                                <span class="team-name <?= $t2_tbd ? 'tbd' : '' ?>"><?= htmlspecialchars($bm['team2_name'] ?: 'TBD') ?></span>
                                                <?php if (!$t2_tbd && strtolower(trim($bm['team2_name'])) === 'team jakolerns'): ?>
                                                    <span style="font-size:0.6rem; font-weight:700; color:#fbbf24; background:rgba(251,191,36,0.15); border:1px solid rgba(251,191,36,0.4); padding:0.1rem 0.4rem; border-radius:4px; white-space:nowrap;" title="Rank raised to Immortal by organizers">★ Immortal</span>
                                                <?php endif; ?>
                                                <?php if (!$t2_tbd && $t2_info && !$t2_info['paid']): ?>
                                                    <span class="bracket-unpaid" title="Unpaid"><i class="bi bi-exclamation-circle-fill"></i></span>
                                                <?php endif; ?>
                                                <span class="team-score"><?= $bm['team2_score'] ?></span>
                                            </div>
                                            <div class="match-footer">
                                                <?php if ($is_live): ?>
                                                    <span class="match-status match-status-live"><span class="live-dot"></span> LIVE</span>
                                                <?php elseif ($is_done): ?>
                                                    <span class="match-status match-status-completed"><i class="bi bi-check-circle-fill"></i> Done</span>
                                                <?php else: ?>
                                                    <span class="match-status match-status-pending">Upcoming</span>
                                                <?php endif; ?>
                                                <?php if (!empty($bm['scheduled_at'])): ?>
                                                    <span class="match-time"><i class="bi bi-clock"></i> <?= date('M j, g:i A', strtotime($bm['scheduled_at'])) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                            <?php $rendered++; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <div style="text-align:center; margin-top:1.25rem;">
        <a href="<?= base_url('bracket.php') ?>" style="display:inline-flex; align-items:center; gap:0.4rem; background:rgba(124,58,237,0.15); border:1px solid rgba(124,58,237,0.35); color:var(--accent-light); padding:0.7rem 1.5rem; border-radius:10px; font-weight:700; font-size:0.9rem; text-decoration:none;">
            <i class="bi bi-diagram-3"></i> View Full Bracket Page
        </a>
    </div>
</div>
<?php endif; ?>

<div class="orgs-section">
    <h2>Presented By</h2>
    <div class="orgs-grid">
        <a href="https://www.facebook.com/people/APEX-cybernet-cafe/61590841850979/" target="_blank" rel="noopener" class="org-card">
            <img src="<?= base_url('images/apexcybernet-logo.svg') ?>" alt="Apex Cybernet" class="org-logo" loading="lazy" decoding="async">
            <div class="org-info">
                <div class="org-name">Apex Cybernet</div>
                <span class="org-link"><i class="bi bi-facebook"></i> Facebook Page</span>
            </div>
        </a>
    </div>
</div>

<div class="terms-landing">
    <div class="terms-section">
        <div class="terms-title"><i class="bi bi-shield-check"></i> Terms &amp; Consent</div>
        <div class="terms-body">
            <p>By registering for this tournament, all participants agree to the following, as well as the <a href="<?= base_url('terms.php') ?>" target="_blank" style="color:var(--accent-light);">Terms of Service</a> and <a href="<?= base_url('privacy.php') ?>" target="_blank" style="color:var(--accent-light);">Privacy Policy</a>:</p>
            <ul>
                <li><strong>Media Release:</strong> You consent to being photographed, filmed, and/or recorded during the tournament. All media may be used for promotional, social media, and public purposes by the organizers.</li>
                <li><strong>Fair Play &amp; Integrity:</strong> You commit to playing with honesty and sportsmanship. Any form of cheating, rank manipulation, or unsportsmanlike behavior may result in disqualification.</li>
                <li><strong>Violations &amp; Penalties:</strong> Rank manipulation, submitting false information, smurfing, or any form of dishonesty will be subject to penalties — including disqualification and prize forfeiture — at the discretion of Apex Cybernet.</li>
                <li><strong>Entry Fee:</strong> ₱550 per team or ₱110 per solo player. Pay via QR Ph (InstaPay) on the payment page after registration. PC time at the venue is paid directly to Apex Cybernet Cafe.</li>
                <li><strong>Build Your Reputation:</strong> This tournament is your stage. Your performance, conduct, and teamwork build your credibility as a player in the community. Play with honor.</li>
            </ul>
            <!-- Violations & Penalties Warning -->
            <div style="margin-top:1.25rem; padding:1rem 1.25rem; background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.3); border-radius:10px;">
                <div style="font-size:1rem; font-weight:800; color:var(--danger); margin-bottom:0.5rem;">
                    <i class="bi bi-shield-exclamation"></i> VIOLATIONS &amp; PENALTIES
                </div>
                <ul style="margin:0; padding-left:1.25rem; font-size:0.85rem; color:var(--text); line-height:1.7;">
                    <li><strong>Rank manipulation</strong> — Submitting a fake or lower rank will result in immediate disqualification.</li>
                    <li><strong>Dishonesty</strong> — False information, smurfing, or fraudulent submissions will lead to disqualification and prize forfeiture.</li>
                    <li><strong>Lying about skill level</strong> — Intentionally misrepresenting your rank or skill level is treated the same as rank manipulation.</li>
                    <li><strong>Match fixing</strong> — Intentional losing, score manipulation, or collusion = permanent ban.</li>
                    <li><strong>Complaints &amp; reports</strong> — Any complaints from players, audiences, or other participants regarding unfair play, lying about skill level, or rule violations <strong>will be taken into consideration</strong> by the organizers. <a href="<?= base_url('dispute.php') ?>" style="color:var(--danger); text-decoration:underline;">File a complaint</a>.</li>
                </ul>
                <div style="margin-top:0.75rem; padding:0.6rem 0.75rem; background:rgba(239,68,68,0.1); border-radius:8px; font-size:0.8rem; font-weight:700; color:var(--danger); text-align:center;">
                    <i class="bi bi-exclamation-triangle-fill"></i> All penalties — including warnings, disqualification, and prize forfeiture — will be judged by the organizers. All decisions are final.
                </div>
            </div>

            <!-- Organizer -->
            <div style="margin-top:1rem; padding:0.85rem 1rem; background:rgba(124,58,237,0.1); border:1px solid rgba(124,58,237,0.3); border-radius:10px; text-align:center;">
                <div style="font-size:0.95rem; font-weight:800; color:var(--accent-light);">
                    <i class="bi bi-building"></i> This event is officially organized by
                </div>
                <div style="font-size:1.25rem; font-weight:800; margin-top:0.3rem; color:var(--text);">
                    APEX CYBERNET
                </div>
                <div style="font-size:0.75rem; color:var(--text-muted); margin-top:0.25rem;">
                    All rules, penalties, and final decisions are under the authority of Apex Cybernet.
                </div>
            </div>
        </div>
    </div>
</div>

</div><!-- /.idx-main-col -->
</div><!-- /.idx-layout -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>
