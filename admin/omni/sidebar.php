<!-- ══ SIDEBAR ══ -->
<?php
// ── Alrisha last sync ──
$alrisha_last_push = null;
try {
    $alp = $argonar_pdo->query("SELECT pushed_at FROM alrisha_snapshots ORDER BY pushed_at DESC LIMIT 1");
    $alp_row = $alp ? $alp->fetch() : null;
    if ($alp_row) $alrisha_last_push = $alp_row['pushed_at'];
} catch (Exception $e) {}
// ── Wiki queue pending count ──
$wiki_pending = 0;
try {
    $wiki_pending = (int)$argonar_pdo->query("SELECT COUNT(*) FROM player_wikis WHERE (wiki_html IS NULL OR wiki_html='') AND answers_json IS NOT NULL")->fetchColumn();
} catch (Exception $e) {}
?>
<aside class="omni-sidebar">
    <div class="sidebar-logo">
        <div class="sidebar-logo-mark">◈</div>
        <div class="sidebar-logo-title">Omniscient</div>
        <div class="sidebar-logo-sub">Analytics</div>
    </div>
    <nav class="sidebar-nav">
        <div class="sidebar-section-label">Businesses</div>

        <?php
        $biz = [
            'argonar' => ['name'=>'Argonar',  'domain'=>'argonar.co',               'icon_bg'=>'rgba(124,58,237,0.18)',  'icon_color'=>'#a78bfa', 'letter'=>'A', 'active_bg'=>'rgba(124,58,237,0.1)',  'active_border'=>'rgba(124,58,237,0.25)', 'page'=>'activity-argonar.php'],
            'loan'    => ['name'=>'Loan',     'domain'=>'argonarsoftware.com',      'icon_bg'=>'rgba(167,139,250,0.15)', 'icon_color'=>'#c4b5fd', 'letter'=>'L', 'active_bg'=>'rgba(167,139,250,0.08)', 'active_border'=>'rgba(167,139,250,0.25)', 'page'=>'activity-loan.php'],
            'alrisha' => ['name'=>'Alrisha',  'domain'=>'alrisha ERP',              'icon_bg'=>'rgba(52,211,153,0.15)',  'icon_color'=>'#34d399', 'letter'=>'E', 'active_bg'=>'rgba(52,211,153,0.08)',  'active_border'=>'rgba(52,211,153,0.25)',  'page'=>'activity-alrisha.php'],
        ];
        foreach ($biz as $key => $b):
            $is_active = $active_site === $key;
            $stats = $sidebar_stats[$key];
        ?>
        <a href="<?= base_url('admin/' . $b['page']) ?>?dr=<?= $date_range ?>" class="sidebar-biz<?= $is_active ? ' active' : '' ?>"
           style="<?= $is_active ? "background:{$b['active_bg']};border-color:{$b['active_border']};" : '' ?>">
            <div class="sidebar-biz-head">
                <div class="sidebar-biz-icon" style="background:<?= $b['icon_bg'] ?>; color:<?= $b['icon_color'] ?>;"><?= $b['letter'] ?></div>
                <span class="sidebar-biz-name" style="<?= $is_active ? "color:{$b['icon_color']};" : '' ?>"><?= $b['name'] ?></span>
            </div>
            <div class="sidebar-biz-domain"><?= $b['domain'] ?></div>
            <div class="sidebar-biz-stats">
                <?php if ($key === 'alrisha'): ?>
                    <?php if ($alrisha_last_push): ?>
                    <?php $min_ago = max(0, (int)round((time() - strtotime($alrisha_last_push)) / 60)); ?>
                    <span class="sidebar-biz-count" title="<?= htmlspecialchars($alrisha_last_push) ?>">Last sync: <?= $min_ago < 60 ? $min_ago.'m ago' : round($min_ago/60,1).'h ago' ?></span>
                    <?php else: ?>
                    <span class="sidebar-biz-count" style="color:#6b7280;">No sync yet</span>
                    <?php endif; ?>
                <?php else: ?>
                <span class="sidebar-biz-count"><?= number_format($stats['sessions']) ?> today</span>
                <?php endif; ?>
                <?php if ($stats['live'] > 0): ?>
                <span class="sidebar-live-pip"><span class="dot"></span><span><?= $stats['live'] ?> live</span></span>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
        <?php $_omni_page = basename($page_file ?? ''); ?>
        <a href="<?= base_url('admin/omni/explore.php') ?>" class="sidebar-biz<?= $_omni_page === 'explore.php' ? ' active' : '' ?>"
           style="<?= $_omni_page === 'explore.php' ? 'background:rgba(192,132,252,0.1);border-color:rgba(192,132,252,0.35);' : '' ?>">
            <div class="sidebar-biz-head">
                <div class="sidebar-biz-icon" style="background:rgba(192,132,252,0.18); color:#c4b5fd;">◈</div>
                <span class="sidebar-biz-name" style="<?= $_omni_page === 'explore.php' ? 'color:#c4b5fd;' : '' ?>">Explorer</span>
            </div>
            <div class="sidebar-biz-domain">Ontology · cross-business</div>
        </a>
        <a href="<?= base_url('admin/omni/ask.php') ?>" class="sidebar-biz<?= $_omni_page === 'ask.php' ? ' active' : '' ?>"
           style="<?= $_omni_page === 'ask.php' ? 'background:rgba(52,211,153,0.1);border-color:rgba(52,211,153,0.35);' : '' ?>">
            <div class="sidebar-biz-head">
                <div class="sidebar-biz-icon" style="background:rgba(52,211,153,0.18); color:#6ee7b7;"><i class="bi bi-question-circle" style="font-size:0.9rem;"></i></div>
                <span class="sidebar-biz-name" style="<?= $_omni_page === 'ask.php' ? 'color:#6ee7b7;' : '' ?>">Ask</span>
            </div>
            <div class="sidebar-biz-domain">Templated insights</div>
        </a>
        <a href="<?= base_url('admin/omni/simulate.php') ?>" class="sidebar-biz<?= $_omni_page === 'simulate.php' ? ' active' : '' ?>"
           style="<?= $_omni_page === 'simulate.php' ? 'background:rgba(251,191,36,0.1);border-color:rgba(251,191,36,0.35);' : '' ?>">
            <div class="sidebar-biz-head">
                <div class="sidebar-biz-icon" style="background:rgba(251,191,36,0.18); color:#fbbf24;"><i class="bi bi-lightning-fill" style="font-size:0.9rem;"></i></div>
                <span class="sidebar-biz-name" style="<?= $_omni_page === 'simulate.php' ? 'color:#fbbf24;' : '' ?>">Simulate</span>
            </div>
            <div class="sidebar-biz-domain">Cohort actions · writes</div>
        </a>
        <a href="<?= base_url('admin/activity-bizops.php') ?>" class="sidebar-biz<?= ($active_site??'') === 'bizops' ? ' active' : '' ?>"
           style="<?= ($active_site??'') === 'bizops' ? 'background:rgba(251,191,36,0.08);border-color:rgba(251,191,36,0.25);' : '' ?>">
            <div class="sidebar-biz-head">
                <div class="sidebar-biz-icon" style="background:rgba(251,191,36,0.15); color:#fbbf24;">◈</div>
                <span class="sidebar-biz-name" style="<?= ($active_site??'') === 'bizops' ? 'color:#fbbf24;' : '' ?>">Biz Ops</span>
            </div>
            <div class="sidebar-biz-domain">Intelligence</div>
        </a>
        <a href="<?= base_url('admin/activity-brain.php') ?>" class="sidebar-biz<?= ($active_site??'') === 'brain' ? ' active' : '' ?>"
           style="<?= ($active_site??'') === 'brain' ? 'background:rgba(236,72,153,0.08);border-color:rgba(236,72,153,0.25);' : '' ?>">
            <div class="sidebar-biz-head">
                <div class="sidebar-biz-icon" style="background:rgba(236,72,153,0.15); color:#ec4899;"><i class="bi bi-journal-text" style="font-size:0.95rem;"></i></div>
                <span class="sidebar-biz-name" style="<?= ($active_site??'') === 'brain' ? 'color:#ec4899;' : '' ?>">Brain</span>
            </div>
            <div class="sidebar-biz-domain">Notebook</div>
        </a>
        <a href="<?= base_url('admin/activity-health.php') ?>" class="sidebar-biz<?= ($active_site??'') === 'health' ? ' active' : '' ?>"
           style="<?= ($active_site??'') === 'health' ? 'background:rgba(96,165,250,0.08);border-color:rgba(96,165,250,0.3);' : '' ?>">
            <div class="sidebar-biz-head">
                <div class="sidebar-biz-icon" style="background:rgba(96,165,250,0.15); color:#60a5fa;"><i class="bi bi-heart-pulse-fill" style="font-size:0.95rem;"></i></div>
                <span class="sidebar-biz-name" style="<?= ($active_site??'') === 'health' ? 'color:#60a5fa;' : '' ?>">System Health</span>
            </div>
            <div class="sidebar-biz-domain">VPS · CPU · storage</div>
        </a>
    </nav>
    <div class="sidebar-footer">
        <a href="#briefing" onclick="document.getElementById('briefing')?.classList.remove('collapsed')" style="margin-bottom:0.5rem;"><i class="bi bi-stars"></i> <span>Briefing</span></a>
        <a href="<?= base_url('admin/activity-manual.php') ?>" style="margin-bottom:0.5rem;"><i class="bi bi-book"></i> <span>Manual</span></a>
        <a href="<?= base_url('admin/') ?>"><i class="bi bi-arrow-left"></i> <span>Admin Panel</span></a>
    </div>
</aside>
<style>
@keyframes wikiPulse { 0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239,68,68,0.7); } 50% { transform: scale(1.08); box-shadow: 0 0 0 5px rgba(239,68,68,0); } }
</style>
