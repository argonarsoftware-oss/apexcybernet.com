<?php
require_once __DIR__ . '/includes/db.php';

$valid_games = [
    'valorant'  => 'Valorant',
    'crossfire' => 'CrossFire',
    'dota2'     => 'Dota 2',
];

$game_slug = $_GET['game'] ?? '';
$game_name = $valid_games[$game_slug] ?? 'Tournament';
$type = $_GET['type'] ?? 'team';
$pageTitle = 'Registration Submitted';
$pageDescription = 'Registration confirmed for Apex Cybernet Tournament.';

$flash = get_flash();
$ref_code = $_SESSION['ref_code'] ?? null;
unset($_SESSION['ref_code']);

require_once __DIR__ . '/includes/header.php';
?>

<div class="success-container">
    <div class="success-icon"><?= $type === 'solo' ? '&#127919;' : '&#127942;' ?></div>
    <h2><?= $type === 'solo' ? "You're on the List!" : "You're In!" ?></h2>

    <?php if ($flash && strpos($flash['message'], 'confirmed') !== false): ?>
        <p style="color: var(--success); font-weight: 600;"><?= htmlspecialchars($flash['message']) ?></p>
        <p>Your registration is approved. See you at the tournament!</p>
    <?php elseif ($flash): ?>
        <p style="color: var(--success); font-weight: 600;"><?= htmlspecialchars($flash['message']) ?></p>
    <?php elseif ($type === 'solo'): ?>
        <p>You've been registered for <?= htmlspecialchars($game_name) ?> solo matchmaking. Once your ₱100 entry is confirmed, your slot is locked in. See you at PGL Ibabao on May 30.</p>
    <?php else: ?>
        <p>Your team has been registered for <?= htmlspecialchars($game_name) ?>. Once your ₱500 entry is confirmed, your slot is locked in. See you at PGL Ibabao on May 30.</p>
    <?php endif; ?>

    <?php if ($ref_code): ?>
        <div class="ref-code-display">
            <div class="ref-code-label">Your Reference Code</div>
            <div class="ref-code-value" id="refCode"><?= htmlspecialchars($ref_code) ?></div>
            <div style="display:flex; gap:0.5rem; justify-content:center; margin:0.75rem 0; flex-wrap:wrap;">
                <button onclick="copyRef(this)" style="background:rgba(124,58,237,0.1); border:1px solid rgba(124,58,237,0.3); color:var(--accent-light); padding:0.4rem 1rem; border-radius:8px; font-size:0.8rem; cursor:pointer; font-weight:600;">
                    <i class="bi bi-clipboard"></i> Copy
                </button>
                <a href="sms:?body=My%20Apex Cybernet%20Tournament%20code:%20<?= urlencode($ref_code) ?>%20-%20https://apexcybernet.com/" style="background:rgba(59,130,246,0.1); border:1px solid rgba(59,130,246,0.3); color:#60a5fa; padding:0.4rem 1rem; border-radius:8px; font-size:0.8rem; text-decoration:none; font-weight:600;">
                    <i class="bi bi-chat-dots"></i> Text Myself
                </a>
                <a href="https://www.facebook.com/argonarsoftware/?text=Save%20my%20tournament%20code:%20<?= urlencode($ref_code) ?>" target="_blank" style="background:rgba(24,119,242,0.1); border:1px solid rgba(24,119,242,0.3); color:#1877f2; padding:0.4rem 1rem; border-radius:8px; font-size:0.8rem; text-decoration:none; font-weight:600;">
                    <i class="bi bi-messenger"></i> Send to Messenger
                </a>
            </div>
            <div class="ref-code-hint">Save this code — it's your tournament reference.</div>
        </div>
        <script>
        function copyRef(btn) {
            var code = document.getElementById('refCode').textContent.trim();
            navigator.clipboard.writeText(code).then(function() {
                btn.innerHTML = '<i class="bi bi-check-lg"></i> Copied!';
                setTimeout(function() { btn.innerHTML = '<i class="bi bi-clipboard"></i> Copy'; }, 2000);
            });
        }
        </script>
    <?php endif; ?>

    <div style="display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap; margin-top: 1.5rem;">
        <a href="<?= base_url('bracket.php?game=dota2') ?>" class="btn-register" style="width: auto; display: inline-flex; padding: 0.75rem 2rem;">
            <i class="bi bi-diagram-3-fill"></i> View Bracket
        </a>
        <a href="<?= base_url() ?>" class="btn-solo" style="width: auto; display: inline-flex; padding: 0.75rem 2rem;">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
