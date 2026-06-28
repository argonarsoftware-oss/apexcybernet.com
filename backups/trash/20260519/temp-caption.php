<?php
require_once __DIR__ . '/includes/db.php';
$pageTitle = 'Dota 2 Caption — Kopitana';
$pageDescription = 'Ready-to-copy Dota 2 tournament caption.';
require_once __DIR__ . '/includes/header.php';
?>

<div class="reg-container" style="max-width:750px;">
    <a href="<?= base_url() ?>" class="back-link">
        <i class="bi bi-arrow-left"></i> Back to dashboard
    </a>

    <div class="reg-card">
        <h2><i class="bi bi-megaphone-fill" style="color:#1877f2;"></i> Dota 2 — Kopitana Caption</h2>
        <p class="subtitle">Ready-to-copy caption for Facebook. Just copy, paste, and post!</p>

        <div class="section-label"><i class="bi bi-shield-shaded"></i> Kopitana — Dota 2 Tournament</div>
        <div class="guide-template" style="position:relative;"><button onclick="copyCaption(this)" class="btn-copy-link" style="position:absolute; top:0.5rem; right:0.5rem; font-size:0.7rem;"><i class="bi bi-clipboard"></i> Copy</button>
⚔️ KOPITANA — DOTA 2 TOURNAMENT ⚔️

Mga lodi ng Dota, ready na ba kayo? 🔥
Gather your squad and battle it out in the ultimate 5v5 Captains Mode tournament. Strategy, skill, and teamwork will decide who takes the throne! 👑

🏆 WINNER TAKES ALL PRIZE
💰 ₱9,000 CASH
OR
🪂 FREE PARAGLIDING TICKETS for your whole team
(Choose only ONE prize — courtesy of OCPD Oslob Cebu Paragliding)

📌 Important Notes:
• Winners must choose only one prize — cash or paragliding tickets.
• Both prizes cannot be claimed together.
• Paragliding prize covers tickets only.
• Transportation, travel expenses, and other logistics will be shouldered by the winners.

📅 Tournament Date: April 19
⏳ Registration Deadline: April 17

📋 TOURNAMENT DETAILS
• Format: Double Elimination
• Matches: Bo1 | Grand Finals Bo3
• Mode: Captains Mode (CM)
• Entry Fee: ₱500 per team | ₱100 solo entry
• Slots: Maximum 16 teams only
• Heroes: All heroes allowed (current patch)

🎮 Solo Player? No problem!
Register as a solo player and we'll match you with a team based on your rank and preferred role. Herald to Immortal — Carry, Mid, Offlane, Support — pick your position!

💳 PAYMENT OPTIONS
• GCash: 0927 872 8916 (auto-detected)
• InstaPay QR: Scan on the payment page
• On-site: Pay at Hide Out Cybernet Cafe

📍 Venue: Hide Out Cybernet Cafe
📌 Brgy. Inayawan, Inayawan Central, Cebu City, 6000

🔗 REGISTER YOUR TEAM: https://argonar.co/register.php?game=dota2
🔗 SOLO ENTRY: https://argonar.co/matchmaking.php?game=dota2

Outplay. Outfarm. Outdraft. Take the throne. 👑

Presented by Argonar Software OPC
Venue hosted by Hide Out Cybernet Cafe
Paragliding by OCPD — https://oslobcebuparagliding.com

#Kopitana #Dota2 #Dota2PH #Dota2Cebu #ArgonarTournament #MOBA #CaptainsMode #GGWellPlayed #CebuGaming #WinnerTakesAll #Esports #Paragliding</div>

    </div>
</div>

<script>
function copyCaption(btn) {
    var template = btn.parentElement;
    var text = template.innerText.replace('Copy', '').trim();
    navigator.clipboard.writeText(text).then(function() {
        var orig = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Copied!';
        setTimeout(function() { btn.innerHTML = orig; }, 2000);
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
