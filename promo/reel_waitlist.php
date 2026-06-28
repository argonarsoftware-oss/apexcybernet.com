<?php
require_once __DIR__ . '/../includes/db.php';

$game = 'dota2';
$max_slots = 16;

$confirmed = $pdo->prepare("SELECT team_name FROM teams WHERE game = ? AND status = 'approved' ORDER BY created_at ASC");
$confirmed->execute([$game]);
$confirmed_teams = $confirmed->fetchAll(PDO::FETCH_COLUMN);

$all_stmt = $pdo->prepare("SELECT team_name, status, created_at FROM teams WHERE game = ? ORDER BY CASE WHEN status='approved' THEN 0 ELSE 1 END ASC, created_at ASC");
$all_stmt->execute([$game]);
$all_teams = $all_stmt->fetchAll();

$paid_solos_count = (int)$pdo->query("SELECT COUNT(*) FROM solo_players WHERE game = 'dota2' AND status = 'approved'")->fetchColumn();
$solo_slots = (int)floor($paid_solos_count / 5);
$available_team_slots = max(0, $max_slots - $solo_slots);

$main_teams     = array_slice($all_teams, 0, $available_team_slots);
$waitlist_teams = array_slice($all_teams, $available_team_slots);
$unpaid_teams   = array_values(array_filter($main_teams, fn($t) => $t['status'] !== 'approved'));
$paid_count     = count($confirmed_teams);
$unpaid_count   = count($unpaid_teams);
$waitlist_count = count($waitlist_teams);

$solo_stmt = $pdo->prepare("SELECT player_name, status FROM solo_players WHERE game = ? ORDER BY CASE WHEN status='approved' THEN 0 ELSE 1 END ASC, created_at ASC");
$solo_stmt->execute([$game]);
$solos      = $solo_stmt->fetchAll();
$paid_solos = array_values(array_filter($solos, fn($s) => $s['status'] === 'approved'));
$solos_needed = max(0, 5 - (count($paid_solos) % 5 ?: 5));

// Row animation CSS generators
function row_css(string $id, int $count, float $first, float $step = 1.0): string {
    $out = '';
    for ($i = 1; $i <= $count; $i++)
        $out .= "#{$id} .r{$i}{animation:popUp .5s ".round($first+($i-1)*$step,1)."s both}\n";
    return $out;
}
function wrow_css(string $id, int $count, float $first, float $step = 1.0): string {
    $out = '';
    for ($i = 1; $i <= $count; $i++)
        $out .= "#{$id} .rw{$i}{animation:popUp .5s ".round($first+($i-1)*$step,1)."s both}\n";
    return $out;
}
function srow_css(string $id, int $count, float $first, float $step = 1.2): string {
    $out = '';
    for ($i = 1; $i <= $count; $i++)
        $out .= "#{$id} .rs{$i}{animation:popUp .5s ".round($first+($i-1)*$step,1)."s both}\n";
    return $out;
}

// Scene timing
$s1s = 0.2;  $s1e = 7.0;
$s2s = 7.2;  $s2r = 8.0;  $s2n = max(1,$paid_count);
$s2e = $s2r + $s2n*1.0 + 5.0; $s2f = $s2e - 0.5;

$s3s = round($s2e,1); $s3r = $s3s+1.0; $s3n = max(1,$unpaid_count);
$s3notice = round($s3r+1.0+0.4,1);
$s3e = $s3r + $s3n*1.0 + 7.0; $s3f = $s3e - 0.5;
$s3w = round($s3r + $s3n*1.0 + 2.5, 1);

$s4s = round($s3e,1); $s4r = $s4s+1.0; $s4n = max(1,$waitlist_count);
$s4e = $s4r + $s4n*1.0 + 5.0; $s4f = $s4e - 0.5;
$s4d = round($s4r + $s4n*1.0 + 1.5, 1);
$s4b = round($s4d + 2.0, 1);

$s5s = round($s4e,1); $s5r = $s5s+1.0; $s5n = max(1,count($paid_solos));
$s5e = $s5r + $s5n*1.2 + 5.0; $s5f = $s5e - 0.5;
$s5i = round($s5r + $s5n*1.2 + 1.5, 1);
$s5nd = round($s5i + 2.0, 1);

$s6s = round($s5e,1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=1080,height=1920">
<style>
@import url('https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800;900&family=Barlow:wght@400;600;700&display=swap');

*{margin:0;padding:0;box-sizing:border-box}

body{
    width:1080px;height:1920px;overflow:hidden;
    background:#080810;
    font-family:'Barlow Condensed',sans-serif;
    color:#f0f0f5;
}

/* ── BACKGROUND ── */
body::before{
    content:'';position:fixed;inset:0;
    background:
        radial-gradient(ellipse 120% 60% at 50% -10%, rgba(99,57,245,0.35) 0%, transparent 60%),
        radial-gradient(ellipse 80% 50% at 100% 110%, rgba(20,180,120,0.12) 0%, transparent 55%);
    z-index:0;pointer-events:none;
}
body::after{
    content:'';position:fixed;inset:0;
    background-image:
        linear-gradient(rgba(255,255,255,0.025) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.025) 1px, transparent 1px);
    background-size:60px 60px;
    z-index:0;pointer-events:none;
}

/* ── SCENE BASE ── */
.scene{
    position:absolute;inset:0;opacity:0;z-index:1;
    display:flex;flex-direction:column;
    align-items:center;justify-content:center;
    padding:80px 64px;
}

/* ── KEYFRAMES ── */
@keyframes fadeIn    {from{opacity:0}to{opacity:1}}
@keyframes fadeOut   {from{opacity:1}to{opacity:0}}
@keyframes slideUp   {from{opacity:0;transform:translateY(60px)}to{opacity:1;transform:translateY(0)}}
@keyframes popUp     {from{opacity:0;transform:translateY(40px) scale(.96)}to{opacity:1;transform:translateY(0) scale(1)}}
@keyframes scaleIn   {from{opacity:0;transform:scale(.7)}to{opacity:1;transform:scale(1)}}
@keyframes glow      {0%,100%{opacity:1}50%{opacity:.6}}
@keyframes shimmer   {0%{background-position:200% center}100%{background-position:-200% center}}
@keyframes pulse     {0%,100%{box-shadow:0 0 0 0 rgba(99,57,245,0)}50%{box-shadow:0 0 40px 10px rgba(99,57,245,0.4)}}
@keyframes breathe   {0%,100%{transform:scale(1)}50%{transform:scale(1.03)}}

/* ── SCENE LABELS ── */
.scene-label{
    font-size:22px;font-weight:700;letter-spacing:6px;text-transform:uppercase;
    color:rgba(255,255,255,0.3);margin-bottom:18px;align-self:flex-start;
}

/* ── DIVIDER ── */
.divider{
    width:100%;height:2px;margin-bottom:30px;
    background:linear-gradient(90deg,transparent,rgba(99,57,245,0.8),transparent);
    align-self:flex-start;
}

/* ══ SCENE 1 ══ */
#s1    {animation:fadeIn .4s <?= $s1s ?>s forwards}
#s1-out{animation:fadeOut .5s <?= $s1e-.3 ?>s forwards}

.s1-eyebrow{
    font-size:28px;font-weight:700;letter-spacing:8px;text-transform:uppercase;
    color:rgba(255,255,255,0.4);
    animation:slideUp .5s .4s both;
}
.s1-number{
    font-size:220px;font-weight:900;line-height:.85;
    background:linear-gradient(135deg,#a78bfa,#6366f1,#818cf8);
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;
    animation:scaleIn .6s .8s both;
    filter:drop-shadow(0 0 60px rgba(99,57,245,0.5));
}
.s1-sublabel{
    font-size:52px;font-weight:800;letter-spacing:3px;text-transform:uppercase;
    color:#e2e8f0;margin-top:10px;
    animation:slideUp .5s 1.2s both;
}
.s1-game{
    font-size:34px;font-weight:600;color:rgba(255,255,255,0.4);letter-spacing:4px;
    margin-top:8px;text-transform:uppercase;
    animation:slideUp .4s 1.6s both;
}
.s1-stats{
    display:flex;gap:40px;margin-top:60px;
    animation:slideUp .5s 2.2s both;
}
.s1-stat{
    display:flex;flex-direction:column;align-items:center;gap:6px;
    padding:28px 44px;
    background:rgba(255,255,255,0.04);
    border:1px solid rgba(255,255,255,0.1);
    border-radius:20px;
    backdrop-filter:blur(10px);
}
.s1-stat-num{font-size:64px;font-weight:900;line-height:1}
.s1-stat-lbl{font-size:22px;font-weight:600;letter-spacing:3px;text-transform:uppercase;color:rgba(255,255,255,0.4)}
.s1-stat.green .s1-stat-num{color:#34d399}
.s1-stat.red   .s1-stat-num{color:#f87171}
.s1-stat.amber .s1-stat-num{color:#fbbf24}

.s1-notice{
    margin-top:50px;padding:22px 52px;
    background:rgba(99,57,245,0.15);
    border:1px solid rgba(99,57,245,0.5);
    border-radius:16px;
    font-size:32px;font-weight:700;color:#c4b5fd;
    letter-spacing:1px;
    animation:scaleIn .5s 2.8s both, pulse 2s 3.5s infinite;
}

/* ══ SCENE HEADERS ══ */
.sh{
    width:100%;display:flex;align-items:center;gap:20px;
    margin-bottom:30px;
}
.sh-icon{
    width:56px;height:56px;border-radius:14px;
    display:flex;align-items:center;justify-content:center;
    font-size:28px;flex-shrink:0;
}
.sh-title{font-size:44px;font-weight:900;letter-spacing:2px;text-transform:uppercase;text-align:left}
.sh-count{
    margin-left:auto;
    font-size:28px;font-weight:800;
    padding:8px 22px;border-radius:10px;flex-shrink:0;
}

/* ══ TEAM CARD ══ */
.tc{
    width:100%;display:flex;align-items:center;gap:24px;
    padding:20px 28px;border-radius:18px;margin-bottom:12px;
    position:relative;overflow:hidden;
}
.tc-num{
    width:52px;height:52px;border-radius:12px;
    display:flex;align-items:center;justify-content:center;
    font-size:24px;font-weight:900;flex-shrink:0;
    font-family:'Barlow Condensed',sans-serif;
}
.tc-name{
    flex:1;font-size:36px;font-weight:800;
    letter-spacing:.5px;text-align:left;
    font-family:'Barlow Condensed',sans-serif;
}
.tc-badge{
    font-size:20px;font-weight:800;letter-spacing:2px;text-transform:uppercase;
    padding:8px 20px;border-radius:10px;flex-shrink:0;
}

/* confirmed */
.tc-ok{
    background:rgba(52,211,153,0.07);
    border:1px solid rgba(52,211,153,0.2);
    border-left:4px solid #34d399;
}
.tc-ok .tc-num{background:rgba(52,211,153,0.15);color:#34d399}
.tc-ok .tc-name{color:#d1fae5}
.tc-ok .tc-badge{background:rgba(52,211,153,0.12);border:1px solid rgba(52,211,153,0.3);color:#34d399}

/* unpaid */
.tc-no{
    background:rgba(248,113,113,0.07);
    border:1px solid rgba(248,113,113,0.2);
    border-left:4px solid #f87171;
}
.tc-no .tc-num{background:rgba(248,113,113,0.15);color:#f87171}
.tc-no .tc-name{color:#fecaca}
.tc-no .tc-badge{background:rgba(248,113,113,0.12);border:1px solid rgba(248,113,113,0.3);color:#f87171}

/* waitlist */
.tc-wl{
    background:rgba(251,191,36,0.06);
    border:1px solid rgba(251,191,36,0.2);
    border-left:4px solid #fbbf24;
}
.tc-wl .tc-num{background:rgba(251,191,36,0.12);color:#fbbf24}
.tc-wl .tc-name{color:#fef3c7}
.tc-wl .tc-badge{background:rgba(251,191,36,0.1);border:1px solid rgba(251,191,36,0.3);color:#fbbf24}

/* solo */
.tc-solo{
    background:rgba(96,165,250,0.07);
    border:1px solid rgba(96,165,250,0.2);
    border-left:4px solid #60a5fa;
}
.tc-solo .tc-num{background:rgba(96,165,250,0.15);color:#60a5fa}
.tc-solo .tc-name{color:#bfdbfe}
.tc-solo .tc-badge{background:rgba(96,165,250,0.12);border:1px solid rgba(96,165,250,0.3);color:#60a5fa}

/* ══ NOTICE CARD ══ */
.notice-card{
    width:100%;padding:20px 28px;margin-bottom:12px;
    background:rgba(251,191,36,0.06);
    border:1px solid rgba(251,191,36,0.3);
    border-radius:14px;
    font-size:24px;font-weight:600;color:#fcd34d;line-height:1.5;
    font-family:'Barlow',sans-serif;
    text-align:left;
}

/* ══ BOTTOM BANNERS ══ */
.warn-bar{
    width:100%;margin-top:24px;padding:22px 36px;
    background:rgba(248,113,113,0.1);
    border:1px solid rgba(248,113,113,0.35);
    border-radius:16px;
    font-size:32px;font-weight:800;color:#fca5a5;
    text-align:center;letter-spacing:1px;
}
.info-box{
    margin-top:28px;padding:28px 36px;
    background:rgba(255,255,255,0.03);
    border:1px solid rgba(255,255,255,0.1);
    border-radius:18px;
    font-size:32px;color:rgba(255,255,255,0.55);line-height:1.7;
    font-family:'Barlow',sans-serif;
    text-align:center;
}
.info-box strong{color:#fbbf24}
.replace-bar{
    margin-top:24px;padding:22px 40px;
    background:rgba(251,191,36,0.1);
    border:1px solid rgba(251,191,36,0.4);
    border-radius:16px;
    font-size:32px;font-weight:800;color:#fbbf24;
    text-align:center;
    animation:breathe 2s infinite;
}
.solo-need{
    margin-top:24px;padding:22px 40px;
    background:rgba(96,165,250,0.08);
    border:1px solid rgba(96,165,250,0.35);
    border-radius:16px;
    font-size:30px;font-weight:800;color:#93c5fd;
    text-align:center;
}

/* ══ SCENE TIMINGS ══ */
#s2    {animation:fadeIn .4s <?= $s2s ?>s forwards}
#s2-out{animation:fadeOut .5s <?= $s2f ?>s forwards}
#s2 .sh{animation:slideUp .5s <?= $s2s+.3 ?>s both}
#s2 .divider{animation:fadeIn .6s <?= $s2s+.6 ?>s both}
#s2 .locked{animation:slideUp .4s <?= round($s2r+$s2n*1.0+1.0,1) ?>s both}
<?= row_css('s2',$s2n,$s2r) ?>

#s3    {animation:fadeIn .4s <?= $s3s ?>s forwards}
#s3-out{animation:fadeOut .5s <?= $s3f ?>s forwards}
#s3 .sh{animation:slideUp .5s <?= $s3s+.3 ?>s both}
#s3 .divider{animation:fadeIn .6s <?= $s3s+.6 ?>s both}
#s3 .notice-card{animation:popUp .5s <?= $s3notice ?>s both}
#s3 .warn-bar{animation:scaleIn .5s <?= $s3w ?>s both}
<?= row_css('s3',$s3n,$s3r) ?>

#s4    {animation:fadeIn .4s <?= $s4s ?>s forwards}
#s4-out{animation:fadeOut .5s <?= $s4f ?>s forwards}
#s4 .sh{animation:slideUp .5s <?= $s4s+.3 ?>s both}
#s4 .divider{animation:fadeIn .6s <?= $s4s+.6 ?>s both}
#s4 .info-box{animation:slideUp .5s <?= $s4d ?>s both}
#s4 .replace-bar{animation:scaleIn .5s <?= $s4b ?>s both}
<?= wrow_css('s4',$s4n,$s4r) ?>

#s5    {animation:fadeIn .4s <?= $s5s ?>s forwards}
#s5-out{animation:fadeOut .5s <?= $s5f ?>s forwards}
#s5 .sh{animation:slideUp .5s <?= $s5s+.3 ?>s both}
#s5 .divider{animation:fadeIn .6s <?= $s5s+.6 ?>s both}
#s5 .info-box{animation:slideUp .5s <?= $s5i ?>s both}
#s5 .solo-need{animation:scaleIn .5s <?= $s5nd ?>s both}
<?= srow_css('s5',$s5n,$s5r) ?>

#s6{animation:fadeIn .4s <?= $s6s ?>s forwards}
#s6 .s6-eyebrow{animation:slideUp .5s <?= $s6s+.3 ?>s both}
#s6 .s6-main   {animation:scaleIn .6s <?= $s6s+.9 ?>s both}
#s6 .s6-sub    {animation:slideUp .4s <?= $s6s+1.6 ?>s both}
#s6 .s6-url    {animation:scaleIn .6s <?= $s6s+2.4 ?>s both}
#s6 .s6-fee    {animation:slideUp .4s <?= $s6s+3.2 ?>s both}
#s6 .s6-tags   {animation:fadeIn  .5s <?= $s6s+4.2 ?>s both}

/* ── CTA STYLES ── */
.s6-eyebrow{
    font-size:26px;font-weight:700;letter-spacing:8px;text-transform:uppercase;
    color:rgba(255,255,255,0.35);
}
.s6-main{
    font-size:148px;font-weight:900;line-height:.88;margin-top:16px;
    background:linear-gradient(135deg,#fff 0%,#c4b5fd 50%,#818cf8 100%);
    background-size:200% auto;
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;
    animation:scaleIn .6s <?= $s6s+.9 ?>s both, shimmer 4s <?= $s6s+1.5 ?>s linear infinite !important;
    text-transform:uppercase;letter-spacing:2px;
}
.s6-sub{
    font-size:34px;color:rgba(255,255,255,0.45);margin-top:12px;
    font-family:'Barlow',sans-serif;font-weight:400;
}
.s6-url{
    margin-top:52px;padding:32px 100px;
    background:linear-gradient(135deg,#6336f5,#8b5cf6,#a78bfa);
    border-radius:28px;
    font-size:60px;font-weight:900;color:#fff;letter-spacing:2px;
    box-shadow:0 0 60px rgba(99,57,245,0.5);
    animation:scaleIn .6s <?= $s6s+2.4 ?>s both, pulse 2.2s <?= $s6s+3 ?>s infinite !important;
}
.s6-fee{
    margin-top:32px;padding:20px 52px;
    background:rgba(255,255,255,0.04);
    border:1px solid rgba(255,255,255,0.1);
    border-radius:16px;
    font-size:34px;font-weight:700;color:rgba(255,255,255,0.6);
}
.s6-fee strong{color:#fbbf24}
.s6-tags{
    margin-top:30px;font-size:26px;color:rgba(255,255,255,0.25);
    line-height:1.8;text-align:center;
    font-family:'Barlow',sans-serif;
}
.s6-tags span{color:#7c3aed}
</style>
</head>
<body>

<!-- SCENE 1: HOOK -->
<div class="scene" id="s1">
    <div id="s1-out" style="display:flex;flex-direction:column;align-items:center;text-align:center;width:100%">
        <div class="s1-eyebrow">Kopitana — Dota 2 Tournament</div>
        <div class="s1-number">16</div>
        <div class="s1-sublabel">Slots Registered</div>
        <div class="s1-game">Cebu City &nbsp;·&nbsp; April 19</div>
        <div class="s1-stats">
            <div class="s1-stat green">
                <div class="s1-stat-num"><?= $paid_count ?></div>
                <div class="s1-stat-lbl">Confirmed</div>
            </div>
            <div class="s1-stat red">
                <div class="s1-stat-num"><?= $unpaid_count ?></div>
                <div class="s1-stat-lbl">Unpaid</div>
            </div>
            <div class="s1-stat amber">
                <div class="s1-stat-num"><?= $waitlist_count ?></div>
                <div class="s1-stat-lbl">Waitlist</div>
            </div>
        </div>
        <div class="s1-notice">Payment deadline — April 17</div>
    </div>
</div>

<!-- SCENE 2: CONFIRMED -->
<div class="scene" id="s2">
    <div id="s2-out" style="width:100%">
        <div class="sh">
            <div class="sh-icon" style="background:rgba(52,211,153,0.15);color:#34d399">✓</div>
            <div class="sh-title" style="color:#34d399">Locked In</div>
            <div class="sh-count" style="background:rgba(52,211,153,0.12);border:1px solid rgba(52,211,153,0.3);color:#34d399"><?= $paid_count ?> Teams</div>
        </div>
        <div class="divider"></div>
        <?php foreach ($confirmed_teams as $i => $name): ?>
        <div class="tc tc-ok r<?= $i+1 ?>">
            <div class="tc-num"><?= $i+1 ?></div>
            <div class="tc-name"><?= htmlspecialchars($name) ?></div>
            <div class="tc-badge">Paid</div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($confirmed_teams)): ?>
        <div class="tc tc-ok r1"><div class="tc-num">—</div><div class="tc-name">No confirmed teams yet</div></div>
        <?php endif; ?>
        <div class="locked" style="margin-top:20px;font-size:30px;font-weight:700;color:rgba(255,255,255,0.3);text-align:center;font-family:'Barlow',sans-serif;">
            See you on <strong style="color:#34d399">April 19</strong> at Hide Out Cafe
        </div>
    </div>
</div>

<!-- SCENE 3: UNPAID -->
<div class="scene" id="s3">
    <div id="s3-out" style="width:100%">
        <div class="sh">
            <div class="sh-icon" style="background:rgba(248,113,113,0.15);color:#f87171">!</div>
            <div class="sh-title" style="color:#f87171">Payment Pending</div>
            <div class="sh-count" style="background:rgba(248,113,113,0.12);border:1px solid rgba(248,113,113,0.3);color:#f87171"><?= $unpaid_count ?> Teams</div>
        </div>
        <div class="divider" style="background:linear-gradient(90deg,transparent,rgba(248,113,113,0.8),transparent)"></div>
        <?php foreach ($unpaid_teams as $i => $team): ?>
        <div class="tc tc-no r<?= $i+1 ?>">
            <div class="tc-num"><?= $i+1 ?></div>
            <div class="tc-name"><?= htmlspecialchars($team['team_name']) ?></div>
            <div class="tc-badge">Unpaid</div>
        </div>
        <?php if (strtolower(trim($team['team_name'])) === 'team jakolerns'): ?>
        <div class="notice-card">
            ⚠️ Following multiple reports regarding potential rank manipulation, and in order to preserve competitive integrity, the organizers have adjusted all team members' ranks to the highest applicable level based on internal evaluation.
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
        <?php if (empty($unpaid_teams)): ?>
        <div class="tc tc-no r1"><div class="tc-num">—</div><div class="tc-name">All teams are paid!</div></div>
        <?php endif; ?>
        <div class="warn-bar">Complete your payment before April 17</div>
    </div>
</div>

<!-- SCENE 4: WAITLIST -->
<div class="scene" id="s4">
    <div id="s4-out" style="width:100%">
        <div class="sh">
            <div class="sh-icon" style="background:rgba(251,191,36,0.12);color:#fbbf24">⏳</div>
            <div class="sh-title" style="color:#fbbf24">Waiting List</div>
            <div class="sh-count" style="background:rgba(251,191,36,0.1);border:1px solid rgba(251,191,36,0.3);color:#fbbf24"><?= $waitlist_count ?> Teams</div>
        </div>
        <div class="divider" style="background:linear-gradient(90deg,transparent,rgba(251,191,36,0.8),transparent)"></div>
        <?php foreach ($waitlist_teams as $i => $team): ?>
        <div class="tc tc-wl rw<?= $i+1 ?>">
            <div class="tc-num"><?= $i+1 ?></div>
            <div class="tc-name"><?= htmlspecialchars($team['team_name']) ?></div>
            <div class="tc-badge">Waitlist</div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($waitlist_teams)): ?>
        <div class="tc tc-wl rw1"><div class="tc-num">—</div><div class="tc-name">No waitlist teams</div></div>
        <?php endif; ?>
        <div class="info-box">
            Registered after all slots were filled.<br>
            If a pending team doesn't pay — the <strong>next team here</strong> takes the slot.
        </div>
        <div class="replace-bar">Your slot is still within reach — stay ready</div>
    </div>
</div>

<!-- SCENE 5: SOLOS -->
<div class="scene" id="s5">
    <div id="s5-out" style="width:100%">
        <div class="sh">
            <div class="sh-icon" style="background:rgba(96,165,250,0.15);color:#60a5fa">◈</div>
            <div class="sh-title" style="color:#60a5fa">Solo Players</div>
            <div class="sh-count" style="background:rgba(96,165,250,0.1);border:1px solid rgba(96,165,250,0.3);color:#60a5fa"><?= count($paid_solos) ?> Paid</div>
        </div>
        <div class="divider" style="background:linear-gradient(90deg,transparent,rgba(96,165,250,0.8),transparent)"></div>
        <?php foreach ($paid_solos as $i => $solo): ?>
        <div class="tc tc-solo rs<?= $i+1 ?>">
            <div class="tc-num"><?= $i+1 ?></div>
            <div class="tc-name"><?= htmlspecialchars($solo['player_name']) ?></div>
            <div class="tc-badge">Paid</div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($paid_solos)): ?>
        <div class="tc tc-solo rs1"><div class="tc-num">—</div><div class="tc-name">No paid solos yet</div></div>
        <?php endif; ?>
        <div class="info-box">
            Solo players are grouped into teams of <strong>5</strong>.<br>
            <?php if ($solos_needed < 5): ?>
            <strong><?= count($paid_solos) ?> paid</strong> so far — <strong><?= $solos_needed ?> more</strong> needed to activate a full slot.
            <?php else: ?>
            Register now to start a team slot.
            <?php endif; ?>
        </div>
        <?php if ($solos_needed < 5): ?>
        <div class="solo-need"><?= $solos_needed ?> more solo player<?= $solos_needed!==1?'s':'' ?> needed to form a team slot</div>
        <?php endif; ?>
    </div>
</div>

<!-- SCENE 6: CTA -->
<div class="scene" id="s6" style="text-align:center">
    <div class="s6-eyebrow">Payment Deadline — April 17</div>
    <div class="s6-main">SECURE<br>YOUR<br>SLOT</div>
    <div class="s6-sub">Register and pay at</div>
    <div class="s6-url">argonar.co</div>
    <div class="s6-fee">₱250 / team &nbsp;·&nbsp; ₱50 / solo &nbsp;·&nbsp; <strong>50% OFF · GCash / InstaPay</strong></div>
    <div class="s6-tags">
        <span>#ArgonarTournament</span> &nbsp; <span>#Dota2Cebu</span><br>
        <span>#CebuEsports</span> &nbsp; <span>#HideOutCafe</span>
    </div>
</div>

</body>
</html>
