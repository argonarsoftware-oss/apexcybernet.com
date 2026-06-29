<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/layout.php';

$user = current_user($pdo);

m_head('Tournament');
?>

<div class="m-top">
    <a href="./" class="m-back"><i class="bi bi-arrow-left"></i></a>
    <div class="m-top-title">Tournament</div>
</div>

<div style="padding:0 1rem 0.75rem;font-size:0.78rem;color:var(--muted);">
    Apex Cybernet Dota 2 Tournament — opens in desktop view for this session only.
</div>

<div class="m-tour-menu">
    <a class="m-tour-item" href="https://apexcybernet.com/bracket.php?full_once=1">
        <div class="m-tour-ico" style="background:rgba(251,191,36,0.12);color:#fbbf24;">
            <i class="bi bi-trophy-fill"></i>
        </div>
        <div class="m-tour-body">
            <div class="m-tour-title">Brackets</div>
            <div class="m-tour-sub">View tournament brackets &amp; match results</div>
        </div>
        <i class="bi bi-chevron-right m-tour-chev"></i>
    </a>

    <a class="m-tour-item" href="https://apexcybernet.com/register.php?game=dota2&full_once=1">
        <div class="m-tour-ico" style="background:rgba(34,197,94,0.12);color:#22c55e;">
            <i class="bi bi-people-fill"></i>
        </div>
        <div class="m-tour-body">
            <div class="m-tour-title">Register Team <span style="font-size:0.6rem;color:var(--text-muted);font-weight:700;">&#8369;500</span></div>
            <div class="m-tour-sub">Sign up your full 5-player team for Dota 2</div>
        </div>
        <i class="bi bi-chevron-right m-tour-chev"></i>
    </a>

    <a class="m-tour-item" href="https://apexcybernet.com/matchmaking.php?game=dota2&full_once=1">
        <div class="m-tour-ico" style="background:rgba(124,58,237,0.15);color:#a78bfa;">
            <i class="bi bi-person-plus-fill"></i>
        </div>
        <div class="m-tour-body">
            <div class="m-tour-title">Solo Player Entry <span style="font-size:0.6rem;color:var(--text-muted);font-weight:700;">&#8369;100</span></div>
            <div class="m-tour-sub">No team? We'll match you with other solos</div>
        </div>
        <i class="bi bi-chevron-right m-tour-chev"></i>
    </a>

    <a class="m-tour-item" href="https://apexcybernet.com/leaderboard.php?full_once=1">
        <div class="m-tour-ico" style="background:rgba(239,68,68,0.12);color:#f87171;">
            <i class="bi bi-bar-chart-fill"></i>
        </div>
        <div class="m-tour-body">
            <div class="m-tour-title">Hall of Fame</div>
            <div class="m-tour-sub">Past champions &amp; standings</div>
        </div>
        <i class="bi bi-chevron-right m-tour-chev"></i>
    </a>
</div>

<div style="padding:1rem;font-size:0.72rem;color:var(--muted);text-align:center;">
    <i class="bi bi-info-circle"></i>
    Desktop pages open without saving a preference — closing the tab returns you to mobile view.
</div>

<style>
.m-tour-menu{display:flex;flex-direction:column;gap:0.6rem;padding:0 1rem;}
.m-tour-item{display:flex;align-items:center;gap:0.9rem;background:var(--card);border:1px solid var(--border);border-radius:14px;padding:0.9rem 1rem;color:var(--text);transition:border-color 0.15s,transform 0.1s;}
.m-tour-item:active{transform:scale(0.98);border-color:var(--accent);}
.m-tour-ico{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.15rem;flex-shrink:0;}
.m-tour-body{flex:1;min-width:0;}
.m-tour-title{font-size:0.92rem;font-weight:800;}
.m-tour-sub{font-size:0.72rem;color:var(--muted);margin-top:0.15rem;}
.m-tour-chev{color:var(--muted);font-size:0.85rem;flex-shrink:0;}
</style>

<?php m_nav('tournament'); m_toast(); m_foot(); ?>
