<?php
require_once __DIR__ . '/includes/db.php';
$pageTitle = 'Facebook Post Guide — Apex Cybernet Tournament';
$pageDescription = 'Ready-to-copy Facebook post captions for promoting the Apex Cybernet Tournament.';
require_once __DIR__ . '/includes/header.php';
?>

<div class="reg-container" style="max-width:750px;">
    <a href="<?= base_url() ?>" class="back-link">
        <i class="bi bi-arrow-left"></i> Back to dashboard
    </a>

    <div class="reg-card">
        <h2><i class="bi bi-megaphone-fill" style="color:#1877f2;"></i> Facebook Post Captions</h2>
        <p class="subtitle">Ready-to-copy captions for your tournament promotion posts. Just copy, paste, and post!</p>

        <!-- GENERAL POST -->
        <div class="section-label">General Tournament Announcement</div>
        <div class="guide-template" style="position:relative;"><button onclick="copyCaption(this)" class="btn-copy-link" style="position:absolute; top:0.5rem; right:0.5rem; font-size:0.7rem;"><i class="bi bi-clipboard"></i> Copy</button>
🎮🏆 APEX CYBERNET GAMING TOURNAMENT IS HERE! 🏆🎮

Are you ready to prove you're the best? 🔥

📋 3 GAMES. 1 CHAMPION PER GAME. WINNER TAKES ALL.
🔫 Valorant
💥 CrossFire (GameClub)
⚔️ Dota 2

💰 PRIZE: TBD cash for the winning team! 🏆

📝 HOW TO JOIN:
✅ Team Entry (5 players): ₱250 (50% OFF — was ₱500)
✅ Solo Entry: ₱50 (50% OFF — was ₱100) — we'll match you with a team based on your skill level!
✅ Register, pay via QR Ph (InstaPay), and your slot is locked in

📍 Venue: PGL Ibabao
📌 S.B. Cabahug, Ibabao-Estancia, Mandaue, 6014 Cebu

🔗 REGISTER NOW: https://apexcybernet.com

⚡ Double Elimination Format — you have to lose TWICE to be out!
🏆 12 teams max per game — slots are filling up FAST!

Don't have a team? No problem! Join solo and we'll build one for you 💪

Presented by Apex Cybernet
Venue hosted by PGL Ibabao

#ApexCybernetTournament #GamingCebu #Esports #Valorant #Dota2 #CrossFire #CebuGaming #WinnerTakesAll #PGLIbabao #GameOn</div>

        <!-- VALORANT POST -->
        <div class="section-label"><i class="bi bi-crosshair"></i> Valorant Post</div>
        <div class="guide-template" style="position:relative;"><button onclick="copyCaption(this)" class="btn-copy-link" style="position:absolute; top:0.5rem; right:0.5rem; font-size:0.7rem;"><i class="bi bi-clipboard"></i> Copy</button>
🔫 VALORANT TOURNAMENT — APEX CYBERNET GAMING 🔫

Calling all Agents! 🎯

Your aim, your strats, your team — it's time to prove it on the big stage. 5v5. Double elimination. No second chances in the losers bracket.

🏆 WINNER TAKES ALL
💰 TBD cash for the champion squad!

📋 DETAILS:
• Format: Double Elimination (Bo1 / Bo3 Grand Finals)
• Entry: ₱250/team · ₱50/solo (50% OFF — was ₱500/₱100) (pay via QR Ph)
• Max: 12 teams only — first come, first served!
• All agents allowed. Standard competitive settings.

🎮 No team? Register solo — we'll match you with players at your rank!
Iron to Radiant, everyone's welcome.

📍 PGL Ibabao — S.B. Cabahug, Mandaue, Cebu
🎟️ ₱250/team · ₱50/solo (50% OFF — was ₱500/₱100) entry. Pay via QR Ph (InstaPay) right after you register.

🔗 REGISTER: https://apexcybernet.com/register.php?game=valorant
🔗 SOLO: https://apexcybernet.com/matchmaking.php?game=valorant

Lock in. Clutch up. Win everything. 🏆

#Valorant #ValorantPH #ValorantCebu #ApexCybernetTournament #Esports #TacticalShooter #ClutchOrKick #GamingCebu #WinnerTakesAll</div>

        <!-- DOTA 2 POST -->
        <div class="section-label"><i class="bi bi-shield-shaded"></i> Dota 2 Post</div>
        <div class="guide-template" style="position:relative;"><button onclick="copyCaption(this)" class="btn-copy-link" style="position:absolute; top:0.5rem; right:0.5rem; font-size:0.7rem;"><i class="bi bi-clipboard"></i> Copy</button>
⚔️ DOTA 2 TOURNAMENT — APEX CYBERNET GAMING ⚔️

Mga lodi ng Dota, tara na! 🔥

5v5. Captains Mode. Double Elimination. The classic MOBA battle returns — and this time, WINNER TAKES ALL.

🏆 PRIZE:
💰 TBD cash for the winning squad!

📋 DETAILS:
• Format: Double Elimination (Bo1 / Bo3 Grand Finals)
• Mode: Captains Mode (CM)
• Entry: ₱250/team · ₱50/solo (50% OFF — was ₱500/₱100) (pay via QR Ph)
• Max: 12 teams — limited slots!
• All heroes allowed (current patch)

🎮 Solo player? Register and we'll match you by rank!
Herald to Immortal — Carry, Mid, Offlane, Support — pick your role.

📍 PGL Ibabao — S.B. Cabahug, Mandaue, Cebu
🎟️ ₱250/team · ₱50/solo (50% OFF — was ₱500/₱100) entry. Pay via QR Ph (InstaPay) right after you register.

🔗 REGISTER: https://apexcybernet.com/register.php?game=dota2
🔗 SOLO: https://apexcybernet.com/matchmaking.php?game=dota2

Outplay. Outfarm. Outdraft. Take the throne. 👑

#Dota2 #Dota2PH #Dota2Cebu #ApexCybernetTournament #MOBA #GGWellPlayed #CebuGaming #WinnerTakesAll #Esports</div>

        <!-- CROSSFIRE POST -->
        <div class="section-label"><i class="bi bi-bullseye"></i> CrossFire Post</div>
        <div class="guide-template" style="position:relative;"><button onclick="copyCaption(this)" class="btn-copy-link" style="position:absolute; top:0.5rem; right:0.5rem; font-size:0.7rem;"><i class="bi bi-clipboard"></i> Copy</button>
💥 CROSSFIRE TOURNAMENT — APEX CYBERNET GAMING 💥

OG gamers, this one's for you! 🎯

CrossFire on GameClub — the classic FPS is back and better than ever. 5v5 Search & Destroy. Double elimination. Pure skill. No gimmicks.

🏆 WINNER TAKES ALL
💰 TBD cash for the champion squad!

📋 DETAILS:
• Format: Double Elimination (Bo1 / Bo3 Grand Finals)
• Mode: Search & Destroy (SnD)
• Entry: ₱250/team · ₱50/solo (50% OFF — was ₱500/₱100) (pay via QR Ph)
• Max: 12 teams — don't miss out!
• GameClub client required

🎮 Flying solo? We got you — register solo and we'll build your dream team based on your skill level!

📍 PGL Ibabao — S.B. Cabahug, Mandaue, Cebu
🎟️ ₱250/team · ₱50/solo (50% OFF — was ₱500/₱100) entry. Pay via QR Ph (InstaPay) right after you register.

🔗 REGISTER: https://apexcybernet.com/register.php?game=crossfire
🔗 SOLO: https://apexcybernet.com/matchmaking.php?game=crossfire

Lock and load. The battlefield awaits. 💀

#CrossFire #CrossFirePH #CFFPS #ApexCybernetTournament #GameClub #FPS #SearchAndDestroy #CebuGaming #WinnerTakesAll #OGGamers</div>

        <!-- SOLO MATCHMAKING POST -->
        <div class="section-label"><i class="bi bi-person-plus-fill"></i> Solo Entry Promo Post</div>
        <div class="guide-template" style="position:relative;"><button onclick="copyCaption(this)" class="btn-copy-link" style="position:absolute; top:0.5rem; right:0.5rem; font-size:0.7rem;"><i class="bi bi-clipboard"></i> Copy</button>
🎮 WALANG TEAM? NO PROBLEM! 🙌

Join the Apex Cybernet Tournament as a SOLO PLAYER — only ₱50 entry (50% OFF — was ₱100)!

Here's how it works:
1️⃣ Register at https://apexcybernet.com
2️⃣ Pick your game (Valorant, Dota 2, or CrossFire)
3️⃣ Select your rank and preferred role
4️⃣ Pay ₱50 via QR Ph (50% OFF promo) and we'll match you with players of similar skill level
5️⃣ Show up, play, and WIN! 🏆

💰 Your team can win TBD cash for the whole squad!

⚡ Your rank matters — we use it to build balanced teams so every match is competitive and fair.

Don't sit this one out. Your next squad is waiting! 💪

📍 PGL Ibabao — Mandaue, Cebu
🔗 https://apexcybernet.com

#ApexCybernetTournament #SoloQueue #FindYourTeam #GamingCebu #Valorant #Dota2 #CrossFire #Esports #NoTeamNoProblem</div>

        <div style="margin-top:2rem; text-align:center;">
            <a href="<?= base_url('meta-guide.php') ?>" class="btn-register" style="display:inline-flex; width:auto; padding:0.75rem 2rem;">
                <i class="bi bi-gear"></i> Meta Business Suite Guide
            </a>
        </div>
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
