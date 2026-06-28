<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

// ── Self-bootstrap the wiki storage table ──
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS player_wikis (
        account_id   INT PRIMARY KEY,
        answers_json MEDIUMTEXT,
        prompt_text  MEDIUMTEXT,
        wiki_html    MEDIUMTEXT,
        regen_count  INT DEFAULT 0,
        is_published TINYINT DEFAULT 0,
        created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

const WIKI_REGEN_LIMIT = 3; // lifetime regenerations per player

$me      = current_user($pdo);
$me_id   = $me ? (int)$me['id'] : 0;

// ── Routing ──
$action  = $_GET['action'] ?? '';
$view_id = (int)($_GET['id'] ?? 0);

// ── POST handlers ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $me_id) {
    $post_action = $_POST['_action'] ?? '';

    if ($post_action === 'save_answers') {
        $answers = [
            'role'            => trim($_POST['role']            ?? ''),
            'playstyle'       => trim($_POST['playstyle']       ?? ''),
            'signature_hero'  => trim($_POST['signature_hero']  ?? ''),
            'rank_tier'       => trim($_POST['rank_tier']       ?? ''),
            'descriptor'      => trim($_POST['descriptor']      ?? ''),
            'signature_moment'=> trim($_POST['signature_moment']?? ''),
            'rival'           => trim($_POST['rival']           ?? ''),
            'hated_hero'      => trim($_POST['hated_hero']      ?? ''),
            'catchphrase'     => trim($_POST['catchphrase']     ?? ''),
            'goal'            => trim($_POST['goal']            ?? ''),
            'dangerous_line'  => trim($_POST['dangerous_line']  ?? ''),
            'extra'           => trim(mb_substr($_POST['extra'] ?? '', 0, 300)),
        ];
        $answers_json = json_encode($answers, JSON_UNESCAPED_UNICODE);

        // Fetch existing to check regen limit
        $ex = $pdo->prepare("SELECT regen_count, is_published FROM player_wikis WHERE account_id = ?");
        $ex->execute([$me_id]);
        $row = $ex->fetch();
        $regen = $row ? ((int)$row['regen_count'] + ($row['is_published'] ? 1 : 0)) : 0;
        if ($row && $regen >= WIKI_REGEN_LIMIT) {
            header('Location: ' . base_url('wiki.php?id=' . $me_id . '&err=regen_limit'));
            exit;
        }

        // Build the AI prompt from answers + account context
        $prompt = wiki_build_prompt($pdo, $me_id, $answers);

        if ($row) {
            // Redo / new submission: clear any existing wiki_html so it re-enters the admin queue
            $pdo->prepare("UPDATE player_wikis SET answers_json = ?, prompt_text = ?, wiki_html = NULL, is_published = 0, regen_count = regen_count + 1, updated_at = NOW() WHERE account_id = ?")
                ->execute([$answers_json, $prompt, $me_id]);
        } else {
            $pdo->prepare("INSERT INTO player_wikis (account_id, answers_json, prompt_text, regen_count) VALUES (?, ?, ?, 0)")
                ->execute([$me_id, $answers_json, $prompt]);
        }
        header('Location: ' . base_url('wiki.php?action=submitted'));
        exit;
    }
}

// ── Helpers ──
function wiki_sanitize_html(string $s): string {
    $s = strip_tags($s, '<h1><h2><h3><h4><p><ul><ol><li><b><strong><i><em><blockquote><br><hr>');
    // remove any attributes to prevent xss
    $s = preg_replace('/<(\w+)(?:\s+[^>]*)?>/', '<$1>', $s);
    return trim($s);
}

function wiki_build_prompt(PDO $pdo, int $account_id, array $a): string {
    $acc = $pdo->prepare("SELECT a.display_name, a.ref_type, a.ref_code, a.bio,
                                 t.team_name, t.game, sp.player_name
                          FROM accounts a
                          LEFT JOIN teams t         ON a.ref_type='team' AND t.ref_code = a.ref_code
                          LEFT JOIN solo_players sp ON a.ref_type='solo' AND sp.ref_code = a.ref_code
                          WHERE a.id = ?");
    $acc->execute([$account_id]);
    $r = $acc->fetch() ?: [];
    $name = $r['display_name'] ?? 'Player';
    $team = $r['team_name'] ?? '';
    $game = $r['game'] ?? 'dota2';

    $lines = [];
    $lines[] = "You are writing a Liquipedia-style player profile for the Argonar Dota 2 tournament platform.";
    $lines[] = "Tone: like a beat reporter, not a fanboy. Present tense. Do not use disclaimers or \"as an AI.\" Do not exaggerate — write with quiet confidence, not hype.";
    $lines[] = "Output: HTML only. Use <h3>, <p>, <blockquote>. NO <html>, <head>, <body>, <div>, or <style> tags. Aim for 220–360 words.";
    $lines[] = "";
    $lines[] = "=== PLAYER ===";
    $lines[] = "Display name: " . $name;
    if ($team) $lines[] = "Team: " . $team;
    if ($game) $lines[] = "Game: " . strtoupper($game);
    $lines[] = "Role: "              . ($a['role']             ?: '(unspecified)');
    $lines[] = "Rank tier: "         . ($a['rank_tier']        ?: '(unspecified)');
    $lines[] = "Playstyle tag: "     . ($a['playstyle']        ?: '(unspecified)');
    $lines[] = "Signature hero: "    . ($a['signature_hero']   ?: '(unspecified)');
    $lines[] = "Teammate descriptor: ". ($a['descriptor']      ?: '(unspecified)');
    if (!empty($a['signature_moment'])) $lines[] = "Signature moment: " . $a['signature_moment'];
    if (!empty($a['rival']))            $lines[] = "Rival: "             . $a['rival'];
    if (!empty($a['hated_hero']))       $lines[] = "Hero they hate facing: " . $a['hated_hero'];
    if (!empty($a['catchphrase']))      $lines[] = "Catchphrase: \""    . $a['catchphrase'] . "\"";
    if (!empty($a['goal']))             $lines[] = "Tournament goal: "  . $a['goal'];
    if (!empty($a['dangerous_line']))   $lines[] = "Why dangerous: "    . $a['dangerous_line'];
    if (!empty($a['extra']))            $lines[] = "Extra color: "      . $a['extra'];
    $lines[] = "";
    $lines[] = "=== SECTIONS TO WRITE ===";
    $lines[] = "<h3>Overview</h3>";
    $lines[] = "  One paragraph. Who they are, their role, playstyle in one line. Lead with the punchiest detail.";
    $lines[] = "<h3>Playstyle</h3>";
    $lines[] = "  One paragraph. How they play, what heroes suit them, what matchups they thrive in. If 'signature hero' is set, build the paragraph around it.";
    if (!empty($a['signature_moment'])) {
        $lines[] = "<h3>Signature Moment</h3>";
        $lines[] = "  One paragraph describing what the player said happened, in a reported, third-person voice.";
    }
    if (!empty($a['rival']) || !empty($a['goal'])) {
        $lines[] = "<h3>Rivalries &amp; Ambitions</h3>";
        $lines[] = "  One paragraph covering the rival, their goal in Argonar, and what stands in the way.";
    }
    if (!empty($a['catchphrase'])) {
        $lines[] = "<blockquote>";
        $lines[] = "  Use the catchphrase verbatim here as the sign-off line.";
        $lines[] = "</blockquote>";
    }
    $lines[] = "";
    $lines[] = "Return only the HTML. No preamble. No explanation.";
    return implode("\n", $lines);
}

// ── State: VIEW SINGLE WIKI ──
if ($view_id > 0 && $action === '') {
    $q = $pdo->prepare("SELECT a.id, a.display_name, a.bio, a.profile_picture, a.ref_type, a.ref_code, a.titles, a.is_verified,
                               pw.wiki_html, pw.is_published, pw.updated_at
                        FROM accounts a
                        LEFT JOIN player_wikis pw ON pw.account_id = a.id
                        WHERE a.id = ? AND a.claim_status = 'approved'");
    $q->execute([$view_id]);
    $view = $q->fetch();
    if (!$view) {
        header('Location: ' . base_url('wiki.php'));
        exit;
    }
    // Enrich with team/solo row
    if ($view['ref_type'] === 'team' && $view['ref_code']) {
        $rs = $pdo->prepare("SELECT team_name, game, team_logo FROM teams WHERE ref_code = ?");
        $rs->execute([$view['ref_code']]);
        $view += ($rs->fetch() ?: []);
    } elseif ($view['ref_type'] === 'solo' && $view['ref_code']) {
        $rs = $pdo->prepare("SELECT player_name AS team_name, game, profile_photo AS team_logo FROM solo_players WHERE ref_code = ?");
        $rs->execute([$view['ref_code']]);
        $view += ($rs->fetch() ?: []);
    }
    $pageTitle       = ($view['display_name'] ?: 'Player') . ' — Wiki — Argonar';
    $pageDescription = 'Player wiki for ' . ($view['display_name'] ?: 'an Argonar player') . ' — role, playstyle, signature hero, and more from Argonar Dota 2 Tournament.';
    $canonicalUrl    = canonical_url('wiki.php?id=' . $view_id);
    $titles          = !empty($view['titles']) ? json_decode($view['titles'], true) : [];

    // Structured data — Person schema for indexable player pages
    if (!empty($view['wiki_html']) && (int)$view['is_published'] === 1) {
        $person_img = !empty($view['profile_picture']) ? base_url($view['profile_picture']) : (!empty($view['team_logo']) ? base_url($view['team_logo']) : '');
        $person_name = $view['display_name'] ?: 'Argonar Player';
        $person_ld = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Person',
            'name'        => $person_name,
            'url'         => $canonicalUrl,
            'description' => 'Dota 2 player on the Argonar Tournament platform.',
        ];
        if ($person_img) $person_ld['image'] = $person_img;
        if (!empty($view['team_name']) && $view['ref_type'] === 'team') {
            $person_ld['memberOf'] = ['@type' => 'SportsTeam', 'name' => $view['team_name']];
        }
        $breadcrumb_ld = [
            '@context' => 'https://schema.org',
            '@type'    => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home',        'item' => 'https://argonar.co/'],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Player Wiki', 'item' => 'https://argonar.co/wiki.php'],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $person_name,  'item' => $canonicalUrl],
            ],
        ];
        $extraHead = '<script type="application/ld+json">' . json_encode($person_ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>'
                   . '<script type="application/ld+json">' . json_encode($breadcrumb_ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
    }
    require_once __DIR__ . '/includes/header.php';
    ?>
    <style>
    .wk-detail { max-width: 820px; margin: 0 auto; padding: 2rem 1.25rem 5rem; }
    .wk-back { display: inline-flex; align-items: center; gap: 0.3rem; color: var(--text-muted); text-decoration: none; font-size: 0.8rem; margin-bottom: 1.25rem; }
    .wk-back:hover { color: var(--accent-light); }
    .wk-hero { display: flex; align-items: center; gap: 1rem; padding: 1.5rem; border: 1px solid var(--border); border-radius: 14px; background: linear-gradient(135deg, rgba(124,58,237,0.08), rgba(236,72,153,0.05)); margin-bottom: 1.5rem; }
    .wk-hero img { width: 72px; height: 72px; border-radius: 12px; object-fit: cover; }
    .wk-hero-init { width: 72px; height: 72px; border-radius: 12px; background: linear-gradient(135deg,#7c3aed,#4c1d95); display: flex; align-items: center; justify-content: center; font-size: 1.8rem; font-weight: 900; color: #fff; }
    .wk-hero-name { font-size: 1.5rem; font-weight: 900; color: var(--text); letter-spacing: -0.5px; display: flex; align-items: center; gap: 0.45rem; }
    .wk-hero-sub { color: var(--text-muted); font-size: 0.82rem; margin-top: 0.2rem; }
    .wk-article { background: var(--bg-card); border: 1px solid var(--border); border-radius: 14px; padding: 1.75rem 1.85rem; line-height: 1.75; color: var(--text); }
    .wk-article h3 { font-size: 1.05rem; font-weight: 800; color: var(--accent-light); letter-spacing: -0.2px; margin: 1.5rem 0 0.6rem; }
    .wk-article h3:first-child { margin-top: 0; }
    .wk-article p { color: #d1d5db; font-size: 0.94rem; margin: 0 0 0.9rem; }
    .wk-article blockquote { border-left: 3px solid rgba(251,191,36,0.5); background: rgba(251,191,36,0.04); padding: 0.85rem 1.1rem; border-radius: 0 8px 8px 0; font-style: italic; color: #fde68a; margin: 1rem 0; }
    .wk-article ul, .wk-article ol { padding-left: 1.2rem; color: #d1d5db; }
    .wk-empty-wiki { text-align: center; padding: 3.5rem 2rem; color: var(--text-muted); border: 1px dashed var(--border); border-radius: 14px; background: rgba(255,255,255,0.02); }
    .wk-empty-wiki i { font-size: 2.5rem; display: block; margin-bottom: 0.65rem; color: var(--accent-light); opacity: 0.5; }
    .wk-empty-wiki p { font-size: 0.88rem; margin-bottom: 1rem; }
    .wk-cta-link { display: inline-flex; align-items: center; gap: 0.4rem; background: linear-gradient(135deg,#7c3aed,#6d28d9); color:#fff; text-decoration:none; padding: 0.65rem 1.2rem; border-radius: 10px; font-weight: 800; font-size: 0.85rem; }
    .wk-cta-link:hover { opacity: 0.88; color:#fff; }
    .wk-meta { display: flex; gap: 0.5rem; align-items: center; font-size: 0.7rem; color: var(--text-muted); margin-top: 1rem; }
    .wk-edit-link { color: var(--accent-light); text-decoration: none; font-size: 0.78rem; font-weight: 700; }
    .wk-edit-link:hover { text-decoration: underline; }
    </style>
    <div class="wk-detail">
        <a href="<?= base_url('wiki.php') ?>" class="wk-back"><i class="bi bi-arrow-left"></i> All players</a>

        <div class="wk-hero">
            <?php if (!empty($view['profile_picture'])): ?>
                <img src="<?= base_url($view['profile_picture']) ?>" alt="">
            <?php elseif (!empty($view['team_logo'])): ?>
                <img src="<?= base_url($view['team_logo']) ?>" alt="">
            <?php else: ?>
                <div class="wk-hero-init"><?= strtoupper(mb_substr($view['display_name'] ?? '?', 0, 2)) ?></div>
            <?php endif; ?>
            <div style="flex:1; min-width:0;">
                <div class="wk-hero-name">
                    <?= htmlspecialchars($view['display_name']) ?>
                    <?php if ((int)($view['is_verified'] ?? 0) === 1): ?><i class="bi bi-patch-check-fill" style="color:#38bdf8;font-size:1rem;" title="Verified"></i><?php endif; ?>
                </div>
                <div class="wk-hero-sub">
                    <?= htmlspecialchars(ucfirst($view['ref_type'] ?? 'player')) ?>
                    <?php if (!empty($view['team_name']) && $view['ref_type'] === 'team'): ?> · <?= htmlspecialchars($view['team_name']) ?><?php endif; ?>
                    <?php if (!empty($view['game'])): ?> · Dota 2<?php endif; ?>
                </div>
            </div>
            <?php if ($me_id === (int)$view['id']): ?>
                <a href="<?= base_url('wiki.php?action=interview') ?>" class="wk-edit-link"><i class="bi bi-pencil-square"></i> <?= !empty($view['wiki_html']) ? 'Edit' : 'Create' ?> my wiki</a>
            <?php endif; ?>
        </div>

        <?php
        $view_has_answers = false;
        if ($me_id === (int)$view['id']) {
            $qa = $pdo->prepare("SELECT 1 FROM player_wikis WHERE account_id = ? AND answers_json IS NOT NULL AND answers_json <> ''");
            $qa->execute([$me_id]);
            $view_has_answers = (bool)$qa->fetchColumn();
        }
        ?>
        <?php if (!empty($view['wiki_html']) && (int)$view['is_published'] === 1): ?>
            <article class="wk-article"><?= $view['wiki_html'] ?></article>
            <div class="wk-meta"><i class="bi bi-clock"></i> Updated <?= date('M j, Y', strtotime($view['updated_at'])) ?></div>
        <?php elseif ($me_id === (int)$view['id'] && $view_has_answers): ?>
            <div class="wk-empty-wiki" style="border-color:rgba(251,191,36,0.35);background:rgba(251,191,36,0.04);">
                <i class="bi bi-hourglass-split" style="color:#fbbf24;"></i>
                <p style="color:#fde68a;"><strong>Your wiki is queued for review.</strong><br>The Argonar team will publish it shortly — usually within a day.</p>
                <a href="<?= base_url('wiki.php?action=interview') ?>" class="wk-edit-link" style="color:#fbbf24;"><i class="bi bi-arrow-repeat"></i> Redo my answers</a>
            </div>
        <?php else: ?>
            <div class="wk-empty-wiki">
                <i class="bi bi-book"></i>
                <p><?= htmlspecialchars($view['display_name']) ?> hasn't written their wiki yet.</p>
                <?php if ($me_id === (int)$view['id']): ?>
                    <a href="<?= base_url('wiki.php?action=interview') ?>" class="wk-cta-link"><i class="bi bi-stars"></i> Create my wiki</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// ── State: INTERVIEW (card stack) ──
if ($action === 'interview') {
    if (!$me_id) { header('Location: ' . base_url('login.php?redirect=wiki.php%3Faction%3Dinterview')); exit; }

    // Load previous answers if any, to prefill
    $pre = $pdo->prepare("SELECT answers_json, regen_count, is_published FROM player_wikis WHERE account_id = ?");
    $pre->execute([$me_id]);
    $pre_row = $pre->fetch();
    $prev = $pre_row && !empty($pre_row['answers_json']) ? (json_decode($pre_row['answers_json'], true) ?: []) : [];
    $regen_used = $pre_row ? (int)$pre_row['regen_count'] : 0;

    $pageTitle = 'Create my wiki — Argonar';
    require_once __DIR__ . '/includes/header.php';
    ?>
    <style>
    .iv-wrap { max-width: 620px; margin: 0 auto; padding: 2rem 1.25rem 5rem; }
    .iv-head { text-align: center; margin-bottom: 1.5rem; }
    .iv-head h1 { font-size: 1.4rem; font-weight: 900; color: var(--text); margin-bottom: 0.4rem; }
    .iv-head p { color: var(--text-muted); font-size: 0.82rem; }
    .iv-progress { height: 4px; background: rgba(255,255,255,0.08); border-radius: 2px; margin-bottom: 1.75rem; overflow: hidden; }
    .iv-progress-bar { height: 100%; background: linear-gradient(90deg,#7c3aed,#ec4899); width: 0%; transition: width 0.3s; }
    .iv-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px; padding: 2rem 1.75rem; display: none; animation: ivFade 0.3s ease; }
    .iv-card.on { display: block; }
    @keyframes ivFade { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .iv-step { font-size: 0.68rem; font-weight: 800; color: var(--accent-light); letter-spacing: 1.5px; text-transform: uppercase; margin-bottom: 0.75rem; }
    .iv-q { font-size: 1.15rem; font-weight: 800; color: var(--text); margin-bottom: 0.3rem; line-height: 1.3; }
    .iv-hint { font-size: 0.78rem; color: var(--text-muted); margin-bottom: 1rem; }
    .iv-choices { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 0.5rem; }
    .iv-choice { background: rgba(255,255,255,0.03); border: 1px solid var(--border); border-radius: 10px; padding: 0.65rem 0.85rem; cursor: pointer; font-size: 0.85rem; color: var(--text); text-align: center; font-family: inherit; font-weight: 600; transition: all 0.15s; }
    .iv-choice:hover { border-color: var(--accent-light); color: var(--accent-light); }
    .iv-choice.selected { background: rgba(124,58,237,0.15); border-color: var(--accent); color: #fff; }
    .iv-input { width: 100%; background: rgba(255,255,255,0.03); border: 1px solid var(--border); border-radius: 10px; padding: 0.75rem 0.9rem; color: var(--text); font-size: 0.9rem; font-family: inherit; outline: none; box-sizing: border-box; }
    .iv-input:focus { border-color: var(--accent); }
    .iv-textarea { min-height: 80px; resize: vertical; }
    .iv-nav { display: flex; justify-content: space-between; gap: 0.75rem; margin-top: 1.5rem; }
    .iv-btn { padding: 0.65rem 1.3rem; background: rgba(255,255,255,0.04); border: 1px solid var(--border); border-radius: 10px; color: var(--text); font-weight: 700; font-size: 0.82rem; cursor: pointer; font-family: inherit; }
    .iv-btn:hover { border-color: var(--accent-light); color: var(--accent-light); }
    .iv-btn.primary { background: linear-gradient(135deg,#7c3aed,#6d28d9); border-color: transparent; color: #fff; }
    .iv-btn.primary:hover { opacity: 0.9; color: #fff; }
    .iv-btn:disabled { opacity: 0.35; cursor: not-allowed; }
    .iv-skip { background: none; border: none; color: var(--text-muted); font-size: 0.72rem; cursor: pointer; font-family: inherit; text-decoration: underline; }
    .iv-skip:hover { color: var(--accent-light); }
    .iv-regen-note { background: rgba(251,191,36,0.08); border: 1px solid rgba(251,191,36,0.25); border-radius: 8px; padding: 0.6rem 0.85rem; font-size: 0.76rem; color: #fde68a; margin-bottom: 1rem; text-align: center; }
    </style>
    <div class="iv-wrap">
        <div class="iv-head">
            <h1><i class="bi bi-stars" style="color:var(--accent-light);"></i> Create my wiki</h1>
            <p>Answer 12 quick questions. Our editors turn your answers into a published wiki page.</p>
        </div>
        <?php if ($regen_used > 0): ?>
            <div class="iv-regen-note"><i class="bi bi-arrow-repeat"></i> You've regenerated <?= $regen_used ?> / <?= WIKI_REGEN_LIMIT ?> times. Each new submission counts.</div>
        <?php endif; ?>
        <div class="iv-progress"><div class="iv-progress-bar" id="ivProgress"></div></div>

        <form method="POST" id="ivForm" action="<?= base_url('wiki.php') ?>">
            <input type="hidden" name="_action" value="save_answers">

            <?php
            $roles       = ['Carry','Mid','Offlane','Soft Support','Hard Support'];
            $playstyles  = ['Aggressive','Patient','Tempo','Disruptor','Cheese','IGL / Shotcaller'];
            $ranks       = ['Herald','Guardian','Crusader','Archon','Legend','Ancient','Divine','Immortal'];
            $descriptors = ['Calm','Cold-blooded','Loud','Quiet','Reliable','Memer','Toxic but clutch','Sleeper'];
            $goals       = ['First place','Top 4','Just experience','Get scouted','Become a pro','Grief the mid-tier'];

            $cards = [
                ['id'=>'role',            'q'=>'What\'s your role?',                    'hint'=>'Pick one. Swap it later if needed.',    'type'=>'choice','opts'=>$roles,       'prev'=>$prev['role']            ?? ''],
                ['id'=>'playstyle',       'q'=>'Describe your playstyle.',              'hint'=>'One tag.',                               'type'=>'choice','opts'=>$playstyles,  'prev'=>$prev['playstyle']       ?? ''],
                ['id'=>'signature_hero',  'q'=>'Your signature hero.',                  'hint'=>'The one you\'re known for.',             'type'=>'text',  'placeholder'=>'e.g. Invoker, Pudge', 'prev'=>$prev['signature_hero']  ?? ''],
                ['id'=>'rank_tier',       'q'=>'What rank tier are you?',                'hint'=>'Current or peak — your call.',           'type'=>'choice','opts'=>$ranks,       'prev'=>$prev['rank_tier']       ?? ''],
                ['id'=>'descriptor',      'q'=>'One word teammates use for you.',       'hint'=>'Be honest. The wiki reads better.',      'type'=>'choice','opts'=>$descriptors, 'prev'=>$prev['descriptor']      ?? ''],
                ['id'=>'signature_moment','q'=>'A signature moment.',                    'hint'=>'The play you still talk about. Optional.','type'=>'textarea','placeholder'=>'e.g. Rampaged in the grand final with Sven, 1 HP...', 'prev'=>$prev['signature_moment'] ?? ''],
                ['id'=>'rival',           'q'=>'Your biggest rival.',                    'hint'=>'Team or player. Optional.',              'type'=>'text',  'placeholder'=>'e.g. Team Hide Out, player XYZ', 'prev'=>$prev['rival']           ?? ''],
                ['id'=>'hated_hero',      'q'=>'The hero you HATE facing.',             'hint'=>'Optional.',                              'type'=>'text',  'placeholder'=>'e.g. Broodmother', 'prev'=>$prev['hated_hero']      ?? ''],
                ['id'=>'catchphrase',    'q'=>'A line your team would quote you saying.','hint'=>'Optional. Short.',                        'type'=>'text',  'placeholder'=>'e.g. farm btw', 'prev'=>$prev['catchphrase']     ?? ''],
                ['id'=>'goal',            'q'=>'Your goal in Argonar.',                  'hint'=>'Pick one.',                              'type'=>'choice','opts'=>$goals,       'prev'=>$prev['goal']            ?? ''],
                ['id'=>'dangerous_line',  'q'=>'One line: why you\'re dangerous.',      'hint'=>'Brag. Optional.',                        'type'=>'text',  'placeholder'=>'e.g. I always win lane.', 'prev'=>$prev['dangerous_line']  ?? ''],
                ['id'=>'extra',           'q'=>'Anything else?',                         'hint'=>'Optional. Under 300 chars.',             'type'=>'textarea','placeholder'=>'Any other color for the wiki.', 'prev'=>$prev['extra']            ?? ''],
            ];
            foreach ($cards as $i => $c):
            ?>
            <div class="iv-card<?= $i === 0 ? ' on' : '' ?>" data-card="<?= $i ?>">
                <div class="iv-step">Question <?= $i + 1 ?> of <?= count($cards) ?></div>
                <div class="iv-q"><?= htmlspecialchars($c['q']) ?></div>
                <div class="iv-hint"><?= htmlspecialchars($c['hint']) ?></div>
                <?php if ($c['type'] === 'choice'): ?>
                    <div class="iv-choices">
                        <?php foreach ($c['opts'] as $opt): ?>
                            <button type="button" class="iv-choice<?= $c['prev'] === $opt ? ' selected' : '' ?>" data-input="<?= htmlspecialchars($c['id']) ?>" data-value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></button>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="<?= htmlspecialchars($c['id']) ?>" id="iv-<?= htmlspecialchars($c['id']) ?>" value="<?= htmlspecialchars($c['prev']) ?>">
                <?php elseif ($c['type'] === 'textarea'): ?>
                    <textarea class="iv-input iv-textarea" name="<?= htmlspecialchars($c['id']) ?>" id="iv-<?= htmlspecialchars($c['id']) ?>" placeholder="<?= htmlspecialchars($c['placeholder'] ?? '') ?>" maxlength="300"><?= htmlspecialchars($c['prev']) ?></textarea>
                <?php else: ?>
                    <input type="text" class="iv-input" name="<?= htmlspecialchars($c['id']) ?>" id="iv-<?= htmlspecialchars($c['id']) ?>" placeholder="<?= htmlspecialchars($c['placeholder'] ?? '') ?>" value="<?= htmlspecialchars($c['prev']) ?>" maxlength="120">
                <?php endif; ?>

                <div class="iv-nav">
                    <?php if ($i > 0): ?>
                        <button type="button" class="iv-btn" onclick="ivGo(<?= $i - 1 ?>)"><i class="bi bi-arrow-left"></i> Back</button>
                    <?php else: ?>
                        <span></span>
                    <?php endif; ?>
                    <?php if ($i < count($cards) - 1): ?>
                        <button type="button" class="iv-btn primary" onclick="ivGo(<?= $i + 1 ?>)">Next <i class="bi bi-arrow-right"></i></button>
                    <?php else: ?>
                        <button type="submit" class="iv-btn primary"><i class="bi bi-stars"></i> Build my prompt</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </form>
    </div>
    <script>
    const IV_TOTAL = <?= count($cards) ?>;
    let ivIdx = 0;
    function ivGo(i) {
        if (i < 0 || i >= IV_TOTAL) return;
        document.querySelectorAll('.iv-card').forEach(c => c.classList.remove('on'));
        const target = document.querySelector('.iv-card[data-card="' + i + '"]');
        if (target) target.classList.add('on');
        ivIdx = i;
        document.getElementById('ivProgress').style.width = ((i + 1) / IV_TOTAL * 100) + '%';
        window.scrollTo({top: 0, behavior: 'smooth'});
    }
    // Initial progress
    document.getElementById('ivProgress').style.width = (1 / IV_TOTAL * 100) + '%';
    // Choice click handler — mutually exclusive within a card
    document.querySelectorAll('.iv-choice').forEach(btn => {
        btn.addEventListener('click', function() {
            const card = btn.closest('.iv-card');
            card.querySelectorAll('.iv-choice').forEach(b => b.classList.remove('selected'));
            btn.classList.add('selected');
            document.getElementById('iv-' + btn.dataset.input).value = btn.dataset.value;
        });
    });
    </script>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// ── State: SUBMITTED (confirmation — no prompt shown to user) ──
if ($action === 'submitted') {
    if (!$me_id) { header('Location: ' . base_url('login.php')); exit; }
    $pageTitle = 'Wiki submitted — Argonar';
    require_once __DIR__ . '/includes/header.php';
    ?>
    <style>
    .sb-wrap { max-width: 560px; margin: 0 auto; padding: 4rem 1.25rem 5rem; text-align: center; }
    .sb-icon { font-size: 3rem; color: var(--accent-light); margin-bottom: 1rem; animation: sbPulse 1.8s ease-in-out infinite; }
    @keyframes sbPulse { 0%, 100% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.08); opacity: 0.8; } }
    .sb-t { font-size: 1.45rem; font-weight: 900; color: var(--text); margin-bottom: 0.6rem; letter-spacing: -0.5px; }
    .sb-s { font-size: 0.9rem; color: var(--text-muted); line-height: 1.6; margin-bottom: 1.75rem; max-width: 440px; margin-left: auto; margin-right: auto; }
    .sb-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 14px; padding: 1.25rem 1.5rem; margin-bottom: 1.25rem; text-align: left; }
    .sb-card-t { font-size: 0.72rem; font-weight: 800; color: var(--accent-light); letter-spacing: 1.5px; text-transform: uppercase; margin-bottom: 0.5rem; }
    .sb-card-p { font-size: 0.85rem; color: #d1d5db; line-height: 1.6; }
    .sb-actions { display: flex; gap: 0.5rem; justify-content: center; flex-wrap: wrap; }
    .sb-btn { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.6rem 1.1rem; border-radius: 10px; border: 1px solid var(--border); background: rgba(255,255,255,0.03); color: var(--text); text-decoration: none; font-size: 0.82rem; font-weight: 700; }
    .sb-btn:hover { border-color: var(--accent-light); color: var(--accent-light); }
    .sb-btn.primary { background: linear-gradient(135deg,#7c3aed,#6d28d9); border-color: transparent; color: #fff; }
    .sb-btn.primary:hover { opacity: 0.9; color: #fff; }
    </style>
    <div class="sb-wrap">
        <div class="sb-icon"><i class="bi bi-hourglass-split"></i></div>
        <div class="sb-t">Your wiki is on the way</div>
        <div class="sb-s">Your answers are in. The Argonar editors will write and publish your wiki shortly — usually within a day. You'll see it live on your wiki page once it's up.</div>

        <div class="sb-card">
            <div class="sb-card-t">What happens next</div>
            <div class="sb-card-p">Our editors craft your wiki from the answers you gave, polish the tone, and publish it under your profile. You don't need to do anything.</div>
        </div>

        <div class="sb-actions">
            <a class="sb-btn primary" href="<?= base_url('wiki.php?id=' . $me_id) ?>"><i class="bi bi-eye-fill"></i> See my wiki page</a>
            <a class="sb-btn" href="<?= base_url('wiki.php') ?>"><i class="bi bi-arrow-left"></i> Back to directory</a>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// ── State: DIRECTORY (default — existing grid) ──
$pageTitle       = 'Player Wiki — Argonar';
$pageDescription = 'Browse all registered players and teams competing in the Argonar tournament.';
$canonicalUrl    = canonical_url('wiki.php');

$valid_games = ['dota2' => 'Dota 2'];
$filter_game = 'dota2';
$filter_type = trim($_GET['type'] ?? '');
$search      = trim($_GET['q']    ?? '');

$sql = "
    SELECT a.id, a.display_name, a.bio, a.profile_picture, a.ref_code, a.ref_type,
           a.h_coins, a.titles, a.created_at, a.is_verified,
           pw.is_published AS wiki_published
    FROM accounts a
    LEFT JOIN player_wikis pw ON pw.account_id = a.id
    WHERE a.claim_status = 'approved'
      AND a.ref_code IS NOT NULL AND a.ref_code != ''
    ORDER BY a.display_name ASC
";
$accounts = $pdo->query($sql)->fetchAll();

$entries = [];
foreach ($accounts as $acc) {
    $reg = null;
    if ($acc['ref_type'] === 'team') {
        $st = $pdo->prepare("SELECT team_name, game, team_logo, status, members_ranks FROM teams WHERE ref_code = ?");
    } else {
        $st = $pdo->prepare("SELECT player_name, game, profile_photo, status FROM solo_players WHERE ref_code = ?");
    }
    $st->execute([$acc['ref_code']]);
    $reg = $st->fetch();
    if (!$reg) continue;

    $name = $acc['ref_type'] === 'team' ? ($reg['team_name'] ?? '') : ($reg['player_name'] ?? '');
    $game = $reg['game'] ?? '';
    $display = !empty($acc['display_name']) ? $acc['display_name'] : $name;

    if ($filter_game && $game !== $filter_game) continue;
    if ($filter_type && $acc['ref_type'] !== $filter_type) continue;
    if ($search && stripos($display, $search) === false && stripos($name, $search) === false) continue;

    $wins = $losses = 0;
    if ($name && $game) {
        $ms = $pdo->prepare("SELECT winner FROM matches WHERE game = ? AND status = 'completed' AND (team1_name = ? OR team2_name = ?)");
        $ms->execute([$game, $name, $name]);
        foreach ($ms->fetchAll() as $m) {
            if ($m['winner'] === $name) $wins++; else $losses++;
        }
    }

    $entries[] = [
        'id'              => $acc['id'],
        'display'         => $display,
        'name'            => $name,
        'game'            => $game,
        'type'            => $acc['ref_type'],
        'status'          => $reg['status'] ?? '',
        'bio'             => $acc['bio'] ?? '',
        'titles'          => !empty($acc['titles']) ? json_decode($acc['titles'], true) : [],
        'pic'             => $acc['profile_picture'] ?? '',
        'logo'            => ($acc['ref_type'] === 'team' ? ($reg['team_logo'] ?? '') : ($reg['profile_photo'] ?? '')),
        'h_coins'         => (int)$acc['h_coins'],
        'wins'            => $wins,
        'losses'          => $losses,
        'joined'          => $acc['created_at'],
        'members'         => !empty($reg['members_ranks']) ? explode('|', $reg['members_ranks']) : [],
        'wiki_published'  => (int)($acc['wiki_published'] ?? 0),
        'is_verified'     => (int)($acc['is_verified'] ?? 0),
    ];
}

// Check whether the logged-in user has a wiki yet
$my_wiki_row = null;
if ($me_id) {
    $mw = $pdo->prepare("SELECT is_published, answers_json FROM player_wikis WHERE account_id = ?");
    $mw->execute([$me_id]);
    $my_wiki_row = $mw->fetch();
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
.wk { max-width: 1100px; margin: 0 auto; padding: 2rem 1.5rem 5rem; }
.wk-head { margin-bottom: 2rem; }
.wk-title { font-size: 1.75rem; font-weight: 900; color: var(--text); margin-bottom: 0.3rem; }
.wk-sub { font-size: 0.88rem; color: var(--text-muted); margin-bottom: 1.25rem; }

.wk-self-cta {
    display: flex; align-items: center; gap: 0.85rem;
    background: linear-gradient(135deg, rgba(124,58,237,0.12), rgba(236,72,153,0.08));
    border: 1px solid rgba(124,58,237,0.35);
    border-radius: 14px;
    padding: 1rem 1.2rem;
    margin-bottom: 1.5rem;
}
.wk-self-icon { font-size: 1.5rem; }
.wk-self-body { flex: 1; min-width: 0; }
.wk-self-t { font-size: 0.95rem; font-weight: 800; color: var(--text); }
.wk-self-s { font-size: 0.78rem; color: var(--text-muted); margin-top: 0.15rem; }
.wk-self-btn { background: linear-gradient(135deg,#7c3aed,#6d28d9); color: #fff; text-decoration: none; padding: 0.55rem 1.05rem; border-radius: 10px; font-size: 0.82rem; font-weight: 800; flex-shrink: 0; display: inline-flex; align-items: center; gap: 0.35rem; }
.wk-self-btn:hover { opacity: 0.88; color: #fff; }

.wk-filters { display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center; }
.wk-search-wrap { flex: 1; min-width: 200px; position: relative; }
.wk-search-wrap i { position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.9rem; pointer-events: none; }
.wk-search { width: 100%; background: var(--bg-card); border: 1px solid var(--border); border-radius: 10px; color: var(--text); padding: 0.6rem 0.85rem 0.6rem 2.3rem; font-size: 0.88rem; font-family: inherit; outline: none; transition: border-color 0.15s; }
.wk-search:focus { border-color: var(--accent); }
.wk-filter-btn { padding: 0.55rem 1rem; background: var(--bg-card); border: 1px solid var(--border); border-radius: 10px; font-size: 0.8rem; font-weight: 700; color: var(--text-muted); cursor: pointer; font-family: inherit; transition: all 0.15s; white-space: nowrap; text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem; }
.wk-filter-btn:hover, .wk-filter-btn.on { border-color: var(--accent); color: var(--accent-light); background: rgba(124,58,237,0.08); }
.wk-count { font-size: 0.75rem; color: var(--text-muted); margin-left: auto; white-space: nowrap; align-self: center; }

.wk-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.1rem; margin-top: 1.5rem; }
.wk-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px; overflow: hidden; text-decoration: none; color: var(--text); display: flex; flex-direction: column; transition: border-color 0.18s, transform 0.18s; position: relative; }
.wk-card:hover { border-color: rgba(139,92,246,0.45); transform: translateY(-2px); }
.wk-wiki-badge { position: absolute; top: 0.6rem; right: 0.6rem; background: rgba(52,211,153,0.15); color: #34d399; font-size: 0.6rem; font-weight: 800; padding: 0.15rem 0.45rem; border-radius: 4px; letter-spacing: 0.5px; text-transform: uppercase; border: 1px solid rgba(52,211,153,0.3); z-index: 2; }

.wk-card-cover { height: 70px; position: relative; overflow: hidden; flex-shrink: 0; }
.wk-card-cover-bg { position: absolute; inset: 0; }
.wk-card-av-wrap { position: absolute; bottom: -24px; left: 1.1rem; }
.wk-card-av { width: 52px; height: 52px; border-radius: 50%; border: 3px solid var(--bg-card); box-shadow: 0 0 0 2px rgba(139,92,246,0.35); object-fit: cover; display: block; }
.wk-card-av-team { width: 52px; height: 52px; border-radius: 12px; border: 3px solid var(--bg-card); box-shadow: 0 0 0 2px rgba(139,92,246,0.35); object-fit: cover; display: block; }
.wk-card-av-init { width: 52px; height: 52px; border-radius: 50%; border: 3px solid var(--bg-card); box-shadow: 0 0 0 2px rgba(139,92,246,0.35); background: linear-gradient(135deg,#7c3aed,#4c1d95); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; font-weight: 900; color: #fff; letter-spacing: -1px; }
.wk-card-av-init.team { border-radius: 12px; }

.wk-card-body { padding: 1.75rem 1.1rem 1rem; flex: 1; }
.wk-card-name { font-size: 1rem; font-weight: 900; color: var(--text); margin-bottom: 0.15rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: flex; align-items: center; gap: 0.3rem; }
.wk-card-meta { font-size: 0.72rem; color: var(--text-muted); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap; }
.wk-card-bio { font-size: 0.78rem; color: rgba(167,139,250,0.7); font-style: italic; margin-bottom: 0.6rem; line-height: 1.45; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.wk-card-titles { display: flex; flex-wrap: wrap; gap: 0.3rem; margin-bottom: 0.6rem; }
.wk-title-badge { display: inline-flex; align-items: center; gap: 0.2rem; font-size: 0.6rem; font-weight: 700; color: #fbbf24; background: rgba(251,191,36,0.1); border: 1px solid rgba(251,191,36,0.22); padding: 0.15rem 0.4rem; border-radius: 4px; }

.wk-card-stats { display: flex; gap: 0.5rem; padding-top: 0.65rem; border-top: 1px solid var(--border); }
.wk-stat { flex: 1; text-align: center; }
.wk-stat-val { font-size: 1rem; font-weight: 900; color: var(--text); line-height: 1; }
.wk-stat-lbl { font-size: 0.58rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 0.15rem; }

.wk-status { display: inline-block; padding: 0.14rem 0.5rem; border-radius: 4px; font-size: 0.62rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }
.wk-confirmed { background: rgba(34,197,94,0.12); color: #34d399; }
.wk-pending   { background: rgba(239,68,68,0.12);  color: #f87171; }
.wk-waitlist  { background: rgba(251,191,36,0.12); color: #fbbf24; }

.gc-dota2     { background: linear-gradient(135deg, #c23b22 0%, #1a0a08 100%); }
.gc-default   { background: linear-gradient(135deg, #3b0f78 0%, #1a0a30 100%); }

.wk-empty { text-align: center; padding: 4rem 2rem; color: var(--text-muted); }
.wk-empty i { font-size: 2.5rem; display: block; margin-bottom: 0.85rem; }
.wk-empty p { font-size: 0.9rem; }

@media (max-width: 600px) {
    .wk-grid { grid-template-columns: 1fr 1fr; gap: 0.75rem; }
    .wk-filters { gap: 0.5rem; }
}
@media (max-width: 380px) {
    .wk-grid { grid-template-columns: 1fr; }
}
</style>

<div class="wk">

    <div class="wk-head">
        <div class="wk-title"><i class="bi bi-book-fill" style="color:var(--accent-light);font-size:1.4rem;vertical-align:-2px;margin-right:0.4rem;"></i>Player Wiki</div>
        <div class="wk-sub">All registered Dota 2 players and teams competing in Argonar Season 1.</div>

        <?php
        $my_state = 'none';
        if ($my_wiki_row) {
            if ((int)$my_wiki_row['is_published'] === 1)       $my_state = 'published';
            elseif (!empty($my_wiki_row['answers_json']))      $my_state = 'queued';
        }
        ?>
        <?php if ($me_id): ?>
        <div class="wk-self-cta" <?php if ($my_state === 'queued'): ?>style="background:linear-gradient(135deg,rgba(251,191,36,0.1),rgba(245,158,11,0.06));border-color:rgba(251,191,36,0.35);"<?php endif; ?>>
            <div class="wk-self-icon"><?= $my_state === 'queued' ? '⏳' : ($my_state === 'published' ? '📖' : '🧙') ?></div>
            <div class="wk-self-body">
                <?php if ($my_state === 'published'): ?>
                    <div class="wk-self-t">Your wiki is live</div>
                    <div class="wk-self-s">Our editors turned your 12 answers into your wiki page. Redo anytime.</div>
                <?php elseif ($my_state === 'queued'): ?>
                    <div class="wk-self-t" style="color:#fde68a;">Your wiki is queued for review</div>
                    <div class="wk-self-s">The Argonar editors are writing it from your answers. Usually up within a day.</div>
                <?php else: ?>
                    <div class="wk-self-t">Write your own wiki — 2 minutes</div>
                    <div class="wk-self-s">Answer 12 cards. Our editors handle the writing and publishing for you.</div>
                <?php endif; ?>
            </div>
            <?php if ($my_state === 'published'): ?>
                <a href="<?= base_url('wiki.php?id=' . $me_id) ?>" class="wk-self-btn"><i class="bi bi-eye-fill"></i> View mine</a>
            <?php elseif ($my_state === 'queued'): ?>
                <a href="<?= base_url('wiki.php?action=interview') ?>" class="wk-self-btn" style="background:linear-gradient(135deg,#fbbf24,#d97706);color:#1f1300;"><i class="bi bi-arrow-repeat"></i> Redo answers</a>
            <?php else: ?>
                <a href="<?= base_url('wiki.php?action=interview') ?>" class="wk-self-btn"><i class="bi bi-stars"></i> Create my wiki</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <form method="GET" action="" class="wk-filters">
            <div class="wk-search-wrap">
                <i class="bi bi-search"></i>
                <input type="text" name="q" class="wk-search" placeholder="Search player or team…" value="<?= htmlspecialchars($search) ?>">
            </div>
            <span class="wk-filter-btn on" style="cursor:default;"><i class="bi bi-joystick"></i> Dota 2</span>
            <a href="?<?= http_build_query(['q'=>$search,'type'=>$filter_type==='team'?'':'team']) ?>" class="wk-filter-btn<?= $filter_type==='team'?' on':'' ?>"><i class="bi bi-people-fill"></i> Teams</a>
            <a href="?<?= http_build_query(['q'=>$search,'type'=>$filter_type==='solo'?'':'solo']) ?>" class="wk-filter-btn<?= $filter_type==='solo'?' on':'' ?>"><i class="bi bi-person-fill"></i> Solo</a>
            <span class="wk-count"><?= count($entries) ?> result<?= count($entries) !== 1 ? 's' : '' ?></span>
        </form>
    </div>

    <?php if (empty($entries)): ?>
    <div class="wk-empty"><i class="bi bi-search"></i><p>No players found<?= $search ? ' for "' . htmlspecialchars($search) . '"' : '' ?>.</p></div>
    <?php else: ?>
    <div class="wk-grid">
        <?php foreach ($entries as $e):
            $initials = strtoupper(substr($e['display'], 0, 2));
            $gc_class = 'gc-' . ($e['game'] ?: 'default');
            $wr = ($e['wins'] + $e['losses']) > 0 ? round($e['wins'] / ($e['wins'] + $e['losses']) * 100) : null;
        ?>
        <a href="<?= base_url('wiki.php?id=' . $e['id']) ?>" class="wk-card">
            <?php if ($e['wiki_published']): ?>
                <span class="wk-wiki-badge"><i class="bi bi-book-fill" style="font-size:0.55rem;"></i> Wiki</span>
            <?php endif; ?>

            <div class="wk-card-cover">
                <div class="wk-card-cover-bg <?= $gc_class ?>"></div>
                <div class="wk-card-av-wrap">
                    <?php if (!empty($e['pic'])): ?>
                        <img src="<?= base_url($e['pic']) ?>" class="wk-card-av" alt="">
                    <?php elseif ($e['type'] === 'team' && !empty($e['logo'])): ?>
                        <img src="<?= base_url($e['logo']) ?>" class="wk-card-av-team" alt="">
                    <?php elseif ($e['type'] === 'solo' && !empty($e['logo'])): ?>
                        <img src="<?= base_url($e['logo']) ?>" class="wk-card-av" alt="">
                    <?php else: ?>
                        <div class="wk-card-av-init <?= $e['type'] === 'team' ? 'team' : '' ?>"><?= htmlspecialchars($initials) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="wk-card-body">
                <div class="wk-card-name">
                    <?= htmlspecialchars($e['display']) ?>
                    <?php if ($e['is_verified']): ?><i class="bi bi-patch-check-fill" style="color:#38bdf8;font-size:0.8rem;" title="Verified"></i><?php endif; ?>
                </div>
                <div class="wk-card-meta">
                    <i class="bi bi-<?= $e['type'] === 'team' ? 'people-fill' : 'person-fill' ?>"></i>
                    <?= ucfirst($e['type']) ?>
                    <?php if ($e['game']): ?> · <?= htmlspecialchars($valid_games[$e['game']] ?? $e['game']) ?><?php endif; ?>
                    <?php if ($e['status']): ?>
                    <span class="wk-status wk-<?= $e['status'] ?>"><?= $e['status'] ?></span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($e['bio'])): ?>
                <div class="wk-card-bio">"<?= htmlspecialchars($e['bio']) ?>"</div>
                <?php endif; ?>
                <?php if (!empty($e['titles'])): ?>
                <div class="wk-card-titles">
                    <?php foreach (array_slice($e['titles'], 0, 2) as $t): ?>
                    <span class="wk-title-badge"><i class="bi bi-award-fill"></i> <?= htmlspecialchars($t) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="wk-card-stats">
                    <div class="wk-stat">
                        <div class="wk-stat-val" style="color:#34d399;"><?= $e['wins'] ?></div>
                        <div class="wk-stat-lbl">Wins</div>
                    </div>
                    <div class="wk-stat">
                        <div class="wk-stat-val" style="color:#f87171;"><?= $e['losses'] ?></div>
                        <div class="wk-stat-lbl">Losses</div>
                    </div>
                    <div class="wk-stat">
                        <div class="wk-stat-val" style="color:<?= $wr !== null ? '#a78bfa' : 'var(--text-muted)' ?>;"><?= $wr !== null ? $wr . '%' : '—' ?></div>
                        <div class="wk-stat-lbl">Win Rate</div>
                    </div>
                    <div class="wk-stat">
                        <div class="wk-stat-val" style="color:#fbbf24;"><?= number_format($e['h_coins']) ?></div>
                        <div class="wk-stat-lbl">HC</div>
                    </div>
                </div>
            </div>

        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
