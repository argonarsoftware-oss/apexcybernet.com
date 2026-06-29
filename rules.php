<?php
require_once __DIR__ . '/includes/db.php';

$pageTitle = 'Tournament Rules — Apex Cybernet Dota 2 Tournament';
$pageDescription = 'Official Apex Cybernet Dota 2 Tournament rules — double elimination format, Dota 2 captains mode, fair play, violations, prize claiming. Read before you register.';
$canonicalUrl = canonical_url('rules.php');
$extraHead = breadcrumb_jsonld([
    ['name' => 'Home',             'url' => 'https://apexcybernet.com/'],
    ['name' => 'Tournament Rules', 'url' => 'https://apexcybernet.com/rules.php'],
]);
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/he-chrome.php';
?>

<section class="he-page-hero">
    <div class="he-page-eyebrow">Rules</div>
    <h1 class="he-page-title">Read this before you register.</h1>
    <p class="he-page-sub">Format, conduct, violations, and prize claim. Same rules apply to everyone — captains, players, subs, and substitutes.</p>
</section>

<div class="he-card" style="max-width:780px;">
    <div class="he-card-inner he-prose">
        <div class="he-card-section">
            <div class="he-card-section-label">Format</div>
            <ul>
                <li><strong>Double Elimination.</strong> Two brackets — Winners and Losers. You have to lose twice to go home.</li>
                <li><strong>Winners bracket:</strong> Best of 1.</li>
                <li><strong>Losers bracket:</strong> Best of 1. Lose here and you're out.</li>
                <li><strong>Grand Finals:</strong> Best of 3. The losers bracket winner must take 2 sets.</li>
                <li>Seeding and brackets are announced before tournament day.</li>
                <li>Maximum <strong>16 teams</strong>.</li>
            </ul>
        </div>

        <div class="he-card-section">
            <div class="he-card-section-label">Dota 2 specifics</div>
            <ul>
                <li>5v5 Captains Mode (CM) for all matches.</li>
                <li>All current-patch heroes allowed.</li>
                <li>Pauses limited to 5 minutes total per team per game.</li>
                <li>Intentional disconnects or crash-exploits → immediate DQ.</li>
                <li>Lobby settings are configured by the admin.</li>
            </ul>
        </div>

        <div class="he-card-section">
            <div class="he-card-section-label">General conduct</div>
            <ul>
                <li><strong>Call time 10:00 AM.</strong> Check in by 10:00, first matches at 11:00. 15-minute grace; after that it's a forfeit.</li>
                <li><strong>No cheating.</strong> Hacks, scripts, macros, exploits = immediate DQ + permanent ban.</li>
                <li><strong>Admin decisions are final.</strong> No appeals.</li>
                <li><strong>No-show = forfeit.</strong> The opponent advances.</li>
                <li><strong>No account sharing.</strong> Each player on their own account.</li>
                <li><strong>One substitute.</strong> Declared at registration. Undeclared subs can't play. Subs replace, not add.</li>
            </ul>
        </div>

        <div class="he-card-section">
            <div class="he-card-section-label">Schedule</div>
            <ul>
                <li>Match schedules posted at least 24 hours before each round.</li>
                <li>Updates go to the Apex Cybernet Facebook page + Messenger.</li>
                <li>Reschedules need both teams + admin to agree, 12+ hours in advance.</li>
            </ul>
        </div>

        <div class="he-card-section">
            <div class="he-card-section-label">Sportsmanship</div>
            <ul>
                <li><strong>Respect opponents.</strong> Win or lose, give a GG.</li>
                <li><strong>No toxicity.</strong> Trash talk that crosses into harassment, slurs, or personal attacks is not tolerated.</li>
                <li>Violators are banned from all future Apex Cybernet events.</li>
                <li>Issues and disputes go straight to the admin — don't argue with the other team.</li>
            </ul>
        </div>

        <div class="he-card-section">
            <div class="he-card-section-label" style="color:var(--danger);">Violations &amp; penalties</div>
            <ul>
                <li><strong>Rank manipulation</strong> — fake or under-declared rank to gain an edge = immediate DQ.</li>
                <li><strong>False information</strong> — wrong names, identity fraud, fake payment proof = DQ + prize forfeiture.</li>
                <li><strong>Smurfing</strong> — using alts or lower-rank accounts is strictly prohibited.</li>
                <li><strong>Match fixing</strong> — collusion, intentional losses, or score manipulation = permanent ban for everyone involved.</li>
                <li><strong>Lying about skill</strong> — treated the same as rank manipulation.</li>
                <li><strong>Complaints &amp; reports</strong> — taken into consideration when evaluating penalties. <a href="<?= base_url('dispute.php') ?>" style="color:var(--accent-light); text-decoration:underline;">File a complaint</a>.</li>
                <li><strong>Penalties</strong> — warnings, DQ, prize forfeiture, or permanent ban — at Apex Cybernet's discretion.</li>
                <li><strong>All decisions final.</strong> Apex Cybernet reserves the right to act to maintain integrity.</li>
            </ul>
        </div>

        <div class="he-card-section">
            <div class="he-card-section-label">Prize claim</div>
            <ul>
                <li><strong>Within 7 days.</strong> Claim the cash prize within 7 days of the finals or it may be forfeited.</li>
                <li><strong>Via GCash</strong> to the team captain. Distribution among team members is the captain's responsibility.</li>
                <li><strong>Verification.</strong> Organizers may request ID or proof of identity.</li>
                <li><strong>Non-transferable.</strong> Can't be moved to another team or individual.</li>
            </ul>
        </div>

        <div style="margin-top:32px; text-align:center;">
            <a href="<?= base_url('register.php?game=dota2') ?>" class="he-btn-primary">Register your team <i class="bi bi-arrow-right"></i></a>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/he-foot.php'; return; ?>
