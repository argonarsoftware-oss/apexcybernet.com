<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bracket_logic.php';

$current = current_user($pdo);
if (!$current) {
    header('Location: ' . base_url('login.php'));
    exit;
}

ensure_team_recruiting_column($pdo);

$captained = teams_captained_by($pdo, (int)$current['id']);
$claimable = teams_claimable_by($pdo, (int)$current['id']);

$pageTitle = 'My Teams — Apex Cybernet';
$pageDescription = 'Manage the teams you captain: recruit toggles, roster, and tournament registration autofill.';
$canonicalUrl = canonical_url('my-teams.php');

$game_names = [
    'valorant'  => 'Valorant',
    'dota2'     => 'Dota 2',
    'crossfire' => 'CrossFire',
];

require_once __DIR__ . '/includes/header.php';
?>

<style>
.mt-wrap { max-width: 960px; margin: 1.5rem auto; padding: 0 1rem; }
.mt-head { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:0.8rem; margin-bottom:1.2rem; }
.mt-head h1 { margin:0; color: var(--text); font-size:1.6rem; }
.mt-head .subtitle { color: var(--text-muted); font-size:0.9rem; margin-top:0.25rem; }
.mt-cta { display:inline-flex; gap:0.5rem; flex-wrap:wrap; }
.mt-btn { background: rgba(124,58,237,0.15); border:1px solid rgba(124,58,237,0.4); color:#c4b5fd; padding:0.55rem 1rem; border-radius:8px; text-decoration:none; font-size:0.85rem; font-weight:700; display:inline-flex; align-items:center; gap:0.35rem; }
.mt-btn.primary { background: linear-gradient(135deg, #7c3aed, #6d28d9); border:none; color:#fff; }
.mt-btn:hover { filter:brightness(1.15); color:#fff; }
.mt-section-label { font-size:0.72rem; text-transform:uppercase; letter-spacing:0.08em; color:var(--text-muted); font-weight:800; margin:1.3rem 0 0.6rem 0; }
.mt-card { background: rgba(255,255,255,0.02); border:1px solid var(--border); border-radius:12px; padding:1rem 1.1rem; margin-bottom:0.9rem; }
.mt-card-head { display:flex; align-items:center; gap:0.8rem; flex-wrap:wrap; margin-bottom:0.6rem; }
.mt-logo { width:46px; height:46px; border-radius:10px; background:rgba(124,58,237,0.12); display:flex; align-items:center; justify-content:center; overflow:hidden; flex-shrink:0; }
.mt-logo img { width:100%; height:100%; object-fit:cover; }
.mt-logo .ph { color:#a78bfa; font-size:1.2rem; font-weight:900; }
.mt-title { color: var(--text); font-weight:800; font-size:1rem; flex:1; min-width:0; }
.mt-meta { font-size:0.74rem; color: var(--text-muted); margin-top:0.1rem; }
.mt-tag { display:inline-block; font-size:0.62rem; letter-spacing:0.06em; text-transform:uppercase; padding:0.12rem 0.5rem; border-radius:999px; font-weight:800; margin-right:0.3rem; }
.mt-tag.game { background:rgba(124,58,237,0.15); color:#c4b5fd; }
.mt-tag.status-pending { background:rgba(251,191,36,0.15); color:#fbbf24; }
.mt-tag.status-approved { background:rgba(34,197,94,0.15); color:#86efac; }
.mt-tag.status-rejected { background:rgba(239,68,68,0.15); color:#fca5a5; }
.mt-roster { display:grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap:0.35rem 0.8rem; font-size:0.82rem; color:var(--text); margin-top:0.6rem; padding:0.5rem 0.7rem; background:rgba(0,0,0,0.15); border-radius:8px; }
.mt-roster .mem { display:flex; align-items:center; gap:0.4rem; }
.mt-roster .mem i { color: var(--text-muted); font-size:0.78rem; }
.mt-roster .cap i { color:#fbbf24; }
.mt-actions { display:flex; gap:0.4rem; flex-wrap:wrap; margin-top:0.8rem; }
.mt-mini { font-size:0.75rem; padding:0.32rem 0.7rem; border-radius:6px; text-decoration:none; font-weight:700; border:1px solid transparent; cursor:pointer; display:inline-flex; gap:0.3rem; align-items:center; }
.mt-mini.ghost { background:rgba(255,255,255,0.04); border-color: rgba(255,255,255,0.1); color: var(--text); }
.mt-mini.ghost:hover { background:rgba(255,255,255,0.08); color:#fff; }
.mt-mini.recruit-off { background:rgba(255,255,255,0.04); border-color:rgba(255,255,255,0.1); color:var(--text); }
.mt-mini.recruit-on  { background:rgba(34,197,94,0.15); border-color:rgba(34,197,94,0.4); color:#86efac; }
.mt-mini.claim { background:rgba(251,191,36,0.15); border-color:rgba(251,191,36,0.4); color:#fbbf24; }
.mt-mini.claim:hover { background:rgba(251,191,36,0.25); color:#fde68a; }
.mt-empty { background: rgba(255,255,255,0.02); border: 1px dashed var(--border); border-radius: 12px; padding: 2rem 1.5rem; text-align:center; color:var(--text-muted); }
.mt-empty .ic { font-size:2.2rem; color:#a78bfa; margin-bottom:0.5rem; }
</style>

<div class="mt-wrap">
    <div class="mt-head">
        <div>
            <h1><i class="bi bi-people-fill" style="color:#a78bfa;"></i> My Teams</h1>
            <div class="subtitle">Teams you captain — registration autofill, recruit toggle, one-click claim.</div>
        </div>
        <div class="mt-cta">
            <a href="<?= base_url('register.php?game=valorant') ?>" class="mt-btn primary"><i class="bi bi-plus-circle"></i> Register a new team</a>
            <a href="<?= base_url('profile.php?id=' . (int)$current['id']) ?>" class="mt-btn"><i class="bi bi-arrow-left"></i> My profile</a>
        </div>
    </div>

    <?php if (empty($captained) && empty($claimable)): ?>
        <div class="mt-empty">
            <div class="ic"><i class="bi bi-people"></i></div>
            <div style="color:var(--text); font-size:1rem; font-weight:700; margin-bottom:0.3rem;">No teams yet</div>
            <div style="margin-bottom:1rem;">You haven't captained or been listed on any team. Start by registering one:</div>
            <div style="display:flex; gap:0.5rem; justify-content:center; flex-wrap:wrap;">
                <a href="<?= base_url('register.php?game=valorant') ?>" class="mt-btn"><i class="bi bi-crosshair" style="color:#fb7185;"></i> Valorant team</a>
                <a href="<?= base_url('register.php?game=dota2') ?>" class="mt-btn"><i class="bi bi-shield-fill" style="color:#c4b5fd;"></i> Dota 2 team</a>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($captained)): ?>
        <div class="mt-section-label">Teams you captain (<?= count($captained) ?>)</div>
        <?php foreach ($captained as $t):
            $members = [];
            for ($i = 1; $i <= 5; $i++) if (!empty($t["member_$i"])) $members[$i] = $t["member_$i"];
            $status = $t['status'] ?? '';
            $is_recruiting = !empty($t['recruiting']);
            $game_label = $game_names[$t['game']] ?? $t['game'];
        ?>
            <div class="mt-card" data-team-id="<?= (int)$t['id'] ?>">
                <div class="mt-card-head">
                    <div class="mt-logo">
                        <?php if (!empty($t['team_logo']) && file_exists(__DIR__ . '/' . $t['team_logo'])): ?>
                            <img src="<?= base_url(htmlspecialchars($t['team_logo'])) ?>" alt="">
                        <?php else: ?>
                            <span class="ph"><?= htmlspecialchars(strtoupper(substr($t['team_name'] ?? '?', 0, 1))) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="mt-title">
                        <?= htmlspecialchars($t['team_name'] ?? 'Unnamed team') ?>
                        <div class="mt-meta">
                            <span class="mt-tag game"><?= htmlspecialchars($game_label) ?></span>
                            <?php if ($status): ?>
                                <span class="mt-tag status-<?= htmlspecialchars($status) ?>"><?= htmlspecialchars($status) ?></span>
                            <?php endif; ?>
                            <span style="color: var(--text-muted); font-family: monospace; font-size:0.72rem;"><?= htmlspecialchars($t['ref_code'] ?? '') ?></span>
                        </div>
                    </div>
                </div>
                <div class="mt-roster">
                    <?php foreach ($members as $i => $name): ?>
                        <div class="mem <?= $i === 1 ? 'cap' : '' ?>">
                            <i class="bi <?= $i === 1 ? 'bi-star-fill' : 'bi-person' ?>"></i>
                            <?= htmlspecialchars($name) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-actions">
                    <a href="<?= base_url('register.php?game=' . urlencode($t['game']) . '&autofill=' . (int)$t['id']) ?>" class="mt-mini ghost" title="Load this roster into the tournament registration form">
                        <i class="bi bi-magic"></i> Autofill new registration
                    </a>
                    <button type="button"
                            class="mt-mini <?= $is_recruiting ? 'recruit-on' : 'recruit-off' ?>"
                            data-recruit-btn="<?= (int)$t['id'] ?>"
                            onclick="mtToggleRecruit(<?= (int)$t['id'] ?>, this)">
                        <i class="bi <?= $is_recruiting ? 'bi-megaphone-fill' : 'bi-megaphone' ?>"></i>
                        <span class="lbl"><?= $is_recruiting ? 'Recruiting: ON' : 'Open to recruits' ?></span>
                    </button>
                    <a href="<?= base_url('profile.php?id=' . (int)$current['id']) ?>" class="mt-mini ghost">
                        <i class="bi bi-eye"></i> View on profile
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($claimable)): ?>
        <div class="mt-section-label" style="color:#fbbf24;">Claim as captain (<?= count($claimable) ?>)</div>
        <div style="font-size:0.78rem; color:var(--text-muted); margin-bottom:0.6rem;">You're listed as member 1 (captain slot) on these teams but haven't claimed them yet. One click makes you the official captain.</div>
        <?php foreach ($claimable as $t):
            $game_label = $game_names[$t['game']] ?? $t['game'];
        ?>
            <div class="mt-card" style="border-color:rgba(251,191,36,0.3); background:rgba(251,191,36,0.04);" data-claim-team-id="<?= (int)$t['id'] ?>">
                <div class="mt-card-head">
                    <div class="mt-logo" style="background:rgba(251,191,36,0.12);">
                        <?php if (!empty($t['team_logo']) && file_exists(__DIR__ . '/' . $t['team_logo'])): ?>
                            <img src="<?= base_url(htmlspecialchars($t['team_logo'])) ?>" alt="">
                        <?php else: ?>
                            <span class="ph" style="color:#fbbf24;"><?= htmlspecialchars(strtoupper(substr($t['team_name'] ?? '?', 0, 1))) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="mt-title">
                        <?= htmlspecialchars($t['team_name'] ?? 'Unnamed team') ?>
                        <div class="mt-meta">
                            <span class="mt-tag game"><?= htmlspecialchars($game_label) ?></span>
                            <span style="color: var(--text-muted); font-family: monospace; font-size:0.72rem;"><?= htmlspecialchars($t['ref_code'] ?? '') ?></span>
                        </div>
                    </div>
                    <button type="button" class="mt-mini claim" onclick="mtClaim(<?= (int)$t['id'] ?>)">
                        <i class="bi bi-check2-circle"></i> Claim captain
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
function mtToggleRecruit(team_id, btn) {
    btn.disabled = true;
    const fd = new FormData();
    fd.append('team_id', team_id);
    fetch('<?= base_url('api/team-recruiting-toggle.php') ?>', { method: 'POST', credentials: 'same-origin', body: fd })
        .then(r => r.json())
        .then(d => {
            btn.disabled = false;
            if (!d.ok) { alert(d.error || 'Could not update recruiting'); return; }
            const lbl = btn.querySelector('.lbl');
            const ic = btn.querySelector('i');
            if (d.recruiting) {
                btn.classList.remove('recruit-off'); btn.classList.add('recruit-on');
                ic.className = 'bi bi-megaphone-fill';
                lbl.textContent = 'Recruiting: ON';
            } else {
                btn.classList.remove('recruit-on'); btn.classList.add('recruit-off');
                ic.className = 'bi bi-megaphone';
                lbl.textContent = 'Open to recruits';
            }
        })
        .catch(() => { btn.disabled = false; alert('Network error. Try again.'); });
}

function mtClaim(team_id) {
    const fd = new FormData();
    fd.append('team_id', team_id);
    fetch('<?= base_url('api/add-team-claim.php') ?>', { method: 'POST', credentials: 'same-origin', body: fd })
        .then(r => r.json())
        .then(d => {
            if (!d.ok) { alert(d.error || 'Could not claim team'); return; }
            location.reload();
        })
        .catch(() => alert('Network error. Try again.'));
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
