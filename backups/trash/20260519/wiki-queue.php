<?php
/**
 * admin/wiki-queue.php — Player Wiki queue
 *
 * Admin-only. Shows pending wiki submissions (players who answered the
 * interview but don't yet have published HTML). Admin copies the pre-built
 * prompt, pastes the AI response, publishes. Also shows published wikis
 * for re-editing.
 */
$active_site = 'wiki';
$page_file   = 'wiki-queue.php';
require_once __DIR__ . '/../includes/db.php';
$argonar_pdo = $pdo;
require_once __DIR__ . '/omni/auth.php';

// Ensure table exists (idempotent — mirrors wiki.php bootstrap)
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

function wq_sanitize(string $s): string {
    $s = strip_tags($s, '<h1><h2><h3><h4><p><ul><ol><li><b><strong><i><em><blockquote><br><hr>');
    $s = preg_replace('/<(\w+)(?:\s+[^>]*)?>/', '<$1>', $s);
    return trim($s);
}

// POST handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['_action'] ?? '';
    $aid = (int)($_POST['account_id'] ?? 0);
    if ($aid) {
        if ($act === 'publish') {
            $html = wq_sanitize($_POST['wiki_html'] ?? '');
            if ($html !== '') {
                $pdo->prepare("UPDATE player_wikis SET wiki_html = ?, is_published = 1, updated_at = NOW() WHERE account_id = ?")
                    ->execute([$html, $aid]);
            }
        } elseif ($act === 'unpublish') {
            $pdo->prepare("UPDATE player_wikis SET is_published = 0, updated_at = NOW() WHERE account_id = ?")->execute([$aid]);
        } elseif ($act === 'delete') {
            $pdo->prepare("DELETE FROM player_wikis WHERE account_id = ?")->execute([$aid]);
        }
        header('Location: ' . base_url('admin/wiki-queue.php?tab=' . urlencode($_POST['tab'] ?? 'pending') . '#acct-' . $aid));
        exit;
    }
}

$tab = $_GET['tab'] ?? 'pending';
if (!in_array($tab, ['pending','published','all'], true)) $tab = 'pending';

$where = match($tab) {
    'pending'   => "WHERE (pw.wiki_html IS NULL OR pw.wiki_html = '') AND pw.answers_json IS NOT NULL",
    'published' => "WHERE pw.is_published = 1 AND pw.wiki_html IS NOT NULL AND pw.wiki_html <> ''",
    default     => "WHERE pw.answers_json IS NOT NULL",
};

$rows = $pdo->query("
    SELECT pw.account_id, pw.answers_json, pw.prompt_text, pw.wiki_html, pw.is_published,
           pw.regen_count, pw.updated_at, pw.created_at,
           a.display_name, a.profile_picture, a.ref_type, a.ref_code, a.is_verified
    FROM player_wikis pw
    JOIN accounts a ON a.id = pw.account_id
    $where
    ORDER BY pw.is_published ASC, pw.updated_at DESC
    LIMIT 200
")->fetchAll();

$counts = [
    'pending'   => (int)$pdo->query("SELECT COUNT(*) FROM player_wikis WHERE (wiki_html IS NULL OR wiki_html='') AND answers_json IS NOT NULL")->fetchColumn(),
    'published' => (int)$pdo->query("SELECT COUNT(*) FROM player_wikis WHERE is_published = 1 AND wiki_html IS NOT NULL AND wiki_html <> ''")->fetchColumn(),
];

// Sidebar stats + date_range (omni needs these)
$sidebar_stats = ['argonar'=>['sessions'=>0,'live'=>0],'ocpd'=>['sessions'=>0,'live'=>0],'loan'=>['sessions'=>0,'live'=>0],'alrisha'=>['sessions'=>0,'live'=>0]];
try {
    $rs = $argonar_pdo->query("SELECT CASE WHEN site IS NULL OR site='' THEN 'argonar' ELSE site END s, COUNT(DISTINCT session_id) n FROM activity_logs WHERE created_at >= CURDATE() GROUP BY s")->fetchAll();
    foreach ($rs as $r) if (isset($sidebar_stats[$r['s']])) $sidebar_stats[$r['s']]['sessions'] = (int)$r['n'];
    $rs = $argonar_pdo->query("SELECT CASE WHEN site IS NULL OR site='' THEN 'argonar' ELSE site END s, COUNT(DISTINCT session_id) n FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) GROUP BY s")->fetchAll();
    foreach ($rs as $r) if (isset($sidebar_stats[$r['s']])) $sidebar_stats[$r['s']]['live'] = (int)$r['n'];
} catch (Exception $e) {}
$date_range = 'today';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Wiki Queue — Omniscient</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<?php require __DIR__ . '/omni/css.php'; ?>
<style>
.wq-wrap { max-width: 1100px; margin: 0 auto; padding: 1.5rem; }
.wq-hero { display: flex; align-items: baseline; gap: 0.75rem; margin-bottom: 1.1rem; padding-bottom: 0.75rem; border-bottom: 1px solid rgba(255,255,255,0.08); }
.wq-hero h1 { font-size: 1.4rem; font-weight: 900; color: #fbcfe8; margin: 0; display: flex; align-items: center; gap: 0.5rem; letter-spacing: -0.5px; }
.wq-hero h1 i { color: #a78bfa; }
.wq-sub { font-size: 0.78rem; color: #9ca3af; margin-left: auto; font-style: italic; }

.wq-tabs { display: flex; gap: 0.4rem; margin-bottom: 1rem; }
.wq-tab { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.08); color: #9ca3af; padding: 0.4rem 0.9rem; border-radius: 999px; text-decoration: none; font-size: 0.75rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.35rem; }
.wq-tab:hover { border-color: rgba(167,139,250,0.4); color: #c4b5fd; }
.wq-tab.on { background: rgba(167,139,250,0.15); border-color: rgba(167,139,250,0.45); color: #c4b5fd; }
.wq-tab .c { color: #6b7280; font-weight: 600; margin-left: 0.2rem; }
.wq-tab.on .c { color: #a78bfa; }

.wq-row { background: #131318; border: 1px solid rgba(255,255,255,0.06); border-radius: 14px; padding: 1.25rem 1.4rem; margin-bottom: 0.85rem; }
.wq-row.pending { border-left: 3px solid rgba(251,191,36,0.5); }
.wq-row.pub     { border-left: 3px solid rgba(52,211,153,0.5); opacity: 0.88; }

.wq-head { display: flex; align-items: center; gap: 0.8rem; margin-bottom: 0.8rem; }
.wq-av { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg,#7c3aed,#ec4899); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 900; font-size: 0.88rem; flex-shrink: 0; overflow: hidden; }
.wq-av img { width: 100%; height: 100%; object-fit: cover; }
.wq-name { font-size: 0.95rem; font-weight: 800; color: #f3f4f6; display: flex; align-items: center; gap: 0.35rem; }
.wq-meta { font-size: 0.7rem; color: #9ca3af; }
.wq-pill { background: rgba(251,191,36,0.12); color: #fbbf24; padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.62rem; font-weight: 800; letter-spacing: 0.5px; text-transform: uppercase; }
.wq-pill.pub { background: rgba(52,211,153,0.12); color: #34d399; }

.wq-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem; margin-top: 0.4rem; }
@media (max-width: 760px) { .wq-grid { grid-template-columns: 1fr; } }
.wq-block { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius: 10px; padding: 0.75rem 0.9rem; min-width: 0; }
.wq-block-t { font-size: 0.62rem; font-weight: 800; color: #9ca3af; letter-spacing: 1.2px; text-transform: uppercase; margin-bottom: 0.4rem; display: flex; align-items: center; gap: 0.4rem; }
.wq-block-t i { color: #a78bfa; }
.wq-answers { font-size: 0.76rem; color: #d1d5db; line-height: 1.6; }
.wq-answers dt { color: #9ca3af; font-weight: 700; font-size: 0.7rem; margin-top: 0.35rem; text-transform: uppercase; letter-spacing: 0.5px; }
.wq-answers dd { margin: 0.1rem 0 0.35rem; color: #e5e7eb; }
.wq-prompt { background: #0a0a0f; border: 1px solid rgba(167,139,250,0.2); border-radius: 6px; padding: 0.6rem 0.75rem; font-family: 'SF Mono', Consolas, monospace; font-size: 0.7rem; color: #d1d5db; white-space: pre-wrap; max-height: 260px; overflow-y: auto; line-height: 1.55; word-break: break-word; }
.wq-actions { display: flex; gap: 0.35rem; flex-wrap: wrap; margin-top: 0.55rem; }
.wq-btn { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.35rem 0.75rem; border-radius: 6px; border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.02); color: #d1d5db; text-decoration: none; font-size: 0.72rem; font-weight: 700; cursor: pointer; font-family: inherit; }
.wq-btn:hover { border-color: #a78bfa; color: #c4b5fd; }
.wq-btn.primary { background: linear-gradient(135deg,#7c3aed,#6d28d9); border-color: transparent; color: #fff; }
.wq-btn.primary:hover { opacity: 0.9; color: #fff; }
.wq-btn.danger { color: #f87171; }
.wq-btn.danger:hover { border-color: #ef4444; color: #f87171; }

.wq-paste { margin-top: 0.8rem; }
.wq-paste textarea { width: 100%; min-height: 160px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; padding: 0.65rem 0.8rem; color: #e5e7eb; font-family: 'SF Mono', Consolas, monospace; font-size: 0.76rem; line-height: 1.6; outline: none; box-sizing: border-box; resize: vertical; }
.wq-paste textarea:focus { border-color: #a78bfa; }

.wq-preview { background: rgba(52,211,153,0.05); border: 1px solid rgba(52,211,153,0.2); border-radius: 8px; padding: 0.85rem 1rem; font-size: 0.78rem; color: #d1d5db; line-height: 1.65; max-height: 260px; overflow-y: auto; }
.wq-preview h3 { color: #c4b5fd; font-size: 0.88rem; font-weight: 800; margin: 0.7rem 0 0.3rem; }
.wq-preview h3:first-child { margin-top: 0; }
.wq-preview p { margin: 0 0 0.5rem; color: #d1d5db; }
.wq-preview blockquote { border-left: 2px solid rgba(251,191,36,0.5); background: rgba(251,191,36,0.04); padding: 0.5rem 0.75rem; margin: 0.5rem 0; color: #fde68a; font-style: italic; border-radius: 0 6px 6px 0; }

.wq-empty { text-align: center; padding: 3rem 1rem; color: #6b7280; font-style: italic; font-size: 0.85rem; }
</style>
</head>
<body>
<div class="omni-layout">
<?php require __DIR__ . '/omni/sidebar.php'; ?>
<div class="omni-main">
<div class="wq-wrap">

    <div class="wq-hero">
        <h1><i class="bi bi-book-fill"></i> Wiki Queue</h1>
        <span class="wq-sub">turn player answers into published wikis</span>
    </div>

    <div class="wq-tabs">
        <a class="wq-tab <?= $tab === 'pending' ? 'on' : '' ?>" href="?tab=pending"><i class="bi bi-hourglass-split"></i> Pending <span class="c"><?= $counts['pending'] ?></span></a>
        <a class="wq-tab <?= $tab === 'published' ? 'on' : '' ?>" href="?tab=published"><i class="bi bi-check2-circle"></i> Published <span class="c"><?= $counts['published'] ?></span></a>
        <a class="wq-tab <?= $tab === 'all' ? 'on' : '' ?>" href="?tab=all"><i class="bi bi-collection"></i> All</a>
    </div>

    <?php if (empty($rows)): ?>
        <div class="wq-empty">Nothing here yet. <?= $tab === 'pending' ? 'Queue is empty — all caught up.' : '' ?></div>
    <?php else: foreach ($rows as $r):
        $answers = !empty($r['answers_json']) ? (json_decode($r['answers_json'], true) ?: []) : [];
        $is_pub  = (int)$r['is_published'] === 1 && !empty($r['wiki_html']);
        $initials = strtoupper(mb_substr($r['display_name'] ?? '?', 0, 2));
    ?>
    <div class="wq-row <?= $is_pub ? 'pub' : 'pending' ?>" id="acct-<?= (int)$r['account_id'] ?>">
        <div class="wq-head">
            <div class="wq-av">
                <?php if (!empty($r['profile_picture'])): ?>
                    <img src="<?= base_url($r['profile_picture']) ?>" alt="">
                <?php else: ?>
                    <?= htmlspecialchars($initials) ?>
                <?php endif; ?>
            </div>
            <div style="flex:1; min-width:0;">
                <div class="wq-name">
                    <?= htmlspecialchars($r['display_name']) ?>
                    <?php if ((int)$r['is_verified'] === 1): ?><i class="bi bi-patch-check-fill" style="color:#38bdf8;font-size:0.85rem;"></i><?php endif; ?>
                </div>
                <div class="wq-meta">
                    <?= htmlspecialchars(ucfirst($r['ref_type'] ?? 'player')) ?>
                    · BR-style submitted <?= date('M j, g:i a', strtotime($r['updated_at'] ?? $r['created_at'])) ?>
                    · regen <?= (int)$r['regen_count'] ?>
                </div>
            </div>
            <span class="wq-pill <?= $is_pub ? 'pub' : '' ?>">
                <?= $is_pub ? 'Published' : 'Pending' ?>
            </span>
            <a class="wq-btn" href="<?= base_url('wiki.php?id=' . (int)$r['account_id']) ?>" target="_blank"><i class="bi bi-box-arrow-up-right"></i> View</a>
        </div>

        <div class="wq-grid">
            <div class="wq-block">
                <div class="wq-block-t"><i class="bi bi-question-circle-fill"></i> Player answers</div>
                <dl class="wq-answers">
                    <?php
                    $labels = [
                        'role'=>'Role','playstyle'=>'Playstyle','signature_hero'=>'Signature hero',
                        'rank_tier'=>'Rank','descriptor'=>'Teammate descriptor',
                        'signature_moment'=>'Signature moment','rival'=>'Rival',
                        'hated_hero'=>'Hates facing','catchphrase'=>'Catchphrase',
                        'goal'=>'Goal','dangerous_line'=>'Why dangerous','extra'=>'Extra',
                    ];
                    foreach ($labels as $k => $l):
                        $v = trim($answers[$k] ?? '');
                        if ($v === '') continue;
                    ?>
                    <dt><?= htmlspecialchars($l) ?></dt>
                    <dd><?= htmlspecialchars($v) ?></dd>
                    <?php endforeach; ?>
                </dl>
            </div>
            <div class="wq-block">
                <div class="wq-block-t"><i class="bi bi-stars"></i> AI prompt <button class="wq-btn" style="margin-left:auto;" onclick="wqCopyPrompt('prm-<?= (int)$r['account_id'] ?>')"><i class="bi bi-clipboard"></i> Copy</button></div>
                <div class="wq-prompt" id="prm-<?= (int)$r['account_id'] ?>"><?= htmlspecialchars($r['prompt_text'] ?? '') ?></div>
                <div class="wq-actions">
                    <a class="wq-btn" href="https://claude.ai/new" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> Open Claude</a>
                    <a class="wq-btn" href="https://chat.openai.com/" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> Open ChatGPT</a>
                </div>
            </div>
        </div>

        <?php if ($is_pub): ?>
            <div class="wq-paste">
                <div class="wq-block-t" style="margin-top:0.85rem;"><i class="bi bi-file-text-fill"></i> Current published HTML</div>
                <div class="wq-preview"><?= $r['wiki_html'] ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" class="wq-paste" action="<?= base_url('admin/wiki-queue.php') ?>">
            <input type="hidden" name="_action" value="publish">
            <input type="hidden" name="account_id" value="<?= (int)$r['account_id'] ?>">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
            <div class="wq-block-t" style="margin-top:0.85rem;"><i class="bi bi-clipboard-plus"></i> <?= $is_pub ? 'Edit / replace wiki HTML' : 'Paste AI response here to publish' ?></div>
            <textarea name="wiki_html" placeholder="Paste HTML from Claude/ChatGPT…"><?= htmlspecialchars($r['wiki_html'] ?? '') ?></textarea>
            <div class="wq-actions">
                <button type="submit" class="wq-btn primary"><i class="bi bi-check2-circle"></i> <?= $is_pub ? 'Update wiki' : 'Publish wiki' ?></button>
                <?php if ($is_pub): ?>
                    <button type="submit" class="wq-btn" formaction="<?= base_url('admin/wiki-queue.php') ?>" onclick="this.form._action.value='unpublish'"><i class="bi bi-eye-slash-fill"></i> Unpublish</button>
                <?php endif; ?>
                <button type="submit" class="wq-btn danger" onclick="return confirm('Delete this entry? Player will have to redo answers.') && (this.form._action.value='delete')"><i class="bi bi-trash-fill"></i> Delete</button>
            </div>
        </form>

    </div>
    <?php endforeach; endif; ?>

</div><!-- /wq-wrap -->
</div><!-- /omni-main -->
</div><!-- /omni-layout -->

<script>
function wqCopyPrompt(id) {
    const el = document.getElementById(id);
    if (!el) return;
    navigator.clipboard.writeText(el.textContent).then(function(){
        const btn = event.target.closest('button');
        const old = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check2"></i> Copied';
        setTimeout(() => btn.innerHTML = old, 1500);
    });
}
</script>
</body>
</html>
