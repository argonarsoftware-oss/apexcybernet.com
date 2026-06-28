<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$user       = current_user($pdo);
$logged_in  = !empty($user);
$uid        = $logged_in ? (int)$user['id'] : 0;

// Current pass status for logged-in users
$current_pass = null;
if ($logged_in) {
    $cp = $pdo->prepare("SELECT * FROM season_passes WHERE account_id = ? AND status IN ('pending','active') ORDER BY id DESC LIMIT 1");
    $cp->execute([$uid]);
    $current_pass = $cp->fetch() ?: null;
}

// Ensure season_passes table exists (safe to call on every request — IF NOT EXISTS)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS season_passes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ref_code VARCHAR(32) UNIQUE,
        account_id INT NOT NULL,
        season_label VARCHAR(50) NOT NULL DEFAULT 'Season 1',
        price_paid DECIMAL(10,2) NOT NULL DEFAULT 999.00,
        payment_method VARCHAR(20) DEFAULT 'gcash_manual',
        status ENUM('pending','active','cancelled') NOT NULL DEFAULT 'pending',
        tournaments_max INT NOT NULL DEFAULT 4,
        tournaments_used INT NOT NULL DEFAULT 0,
        hc_bonus INT NOT NULL DEFAULT 2000,
        hc_credited TINYINT(1) NOT NULL DEFAULT 0,
        purchased_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        activated_at DATETIME DEFAULT NULL,
        expires_at DATETIME DEFAULT NULL,
        admin_note TEXT DEFAULT NULL,
        INDEX (account_id), INDEX (status), INDEX (ref_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) { /* ignore */ }

$pageTitle       = 'Argonar Season 1 Pass — Reserve Yours';
$pageDescription = 'Argonar Season 1 Pass — ₱999 for 4 tournament entries, 2,000 H-Coins, Founder avatar, and early registration access. Limited pre-sale before the season begins.';
$canonicalUrl    = canonical_url('season-pass.php');

require_once __DIR__ . '/includes/header.php';
?>

<style>
.sp-page { max-width: 900px; margin: 0 auto; padding: 2rem 1rem 4rem; }

.sp-hero {
    background: linear-gradient(135deg, #1a0a3d 0%, #2d1065 40%, #7c3aed 100%);
    border-radius: 24px;
    padding: 2.5rem 1.75rem;
    text-align: center;
    margin-bottom: 1.5rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 18px 50px rgba(124,58,237,0.35);
}
.sp-hero::before, .sp-hero::after {
    content: ''; position: absolute; border-radius: 50%;
    background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
    pointer-events: none;
}
.sp-hero::before { width: 360px; height: 360px; top: -120px; right: -100px; }
.sp-hero::after  { width: 240px; height: 240px; bottom: -80px; left: -60px; }
.sp-hero > * { position: relative; z-index: 1; }

.sp-tag {
    display: inline-block;
    background: rgba(251,191,36,0.2);
    border: 1px solid rgba(251,191,36,0.4);
    color: #fde68a;
    font-size: 0.7rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    padding: 0.3rem 0.85rem;
    border-radius: 99px;
    margin-bottom: 0.85rem;
}
.sp-hero h1 {
    font-size: clamp(1.7rem, 4vw, 2.4rem);
    font-weight: 900;
    color: #fff;
    letter-spacing: -0.8px;
    margin: 0.25rem 0 0.5rem;
}
.sp-hero .sp-sub {
    color: rgba(255,255,255,0.8);
    font-size: 0.95rem;
    line-height: 1.55;
    max-width: 520px;
    margin: 0 auto;
}
.sp-price {
    display: inline-flex;
    align-items: baseline;
    gap: 0.5rem;
    margin-top: 1.1rem;
    padding: 0.6rem 1.25rem;
    background: rgba(0,0,0,0.25);
    border-radius: 14px;
    border: 1px solid rgba(255,255,255,0.1);
}
.sp-price .peso { font-size: 2.8rem; font-weight: 900; color: #fbbf24; letter-spacing: -1.5px; line-height: 1; }
.sp-price .per  { font-size: 0.78rem; color: rgba(255,255,255,0.6); font-weight: 600; }

/* Benefits grid */
.sp-benefits {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 0.9rem;
    margin-bottom: 1.75rem;
}
.sp-benefit {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 1.2rem 1.1rem;
}
.sp-benefit-ico {
    width: 42px; height: 42px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem; margin-bottom: 0.7rem;
}
.sp-benefit-title { font-size: 0.95rem; font-weight: 800; color: var(--text); margin-bottom: 0.2rem; }
.sp-benefit-desc  { font-size: 0.78rem; color: var(--text-muted); line-height: 1.5; }

/* CTA card */
.sp-cta-card {
    background: var(--bg-card);
    border: 1.5px solid rgba(124,58,237,0.4);
    border-radius: 18px;
    padding: 1.5rem 1.25rem;
    text-align: center;
}
.sp-cta-btn {
    display: inline-flex; align-items: center; gap: 0.45rem;
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
    color: #0f0f13;
    border: none;
    border-radius: 12px;
    padding: 0.95rem 2rem;
    font-size: 1.02rem;
    font-weight: 900;
    cursor: pointer;
    font-family: inherit;
    transition: transform 0.15s, box-shadow 0.15s;
    box-shadow: 0 8px 22px rgba(251,191,36,0.35);
    text-decoration: none;
}
.sp-cta-btn:hover { transform: translateY(-2px); box-shadow: 0 12px 30px rgba(251,191,36,0.5); color: #0f0f13; }
.sp-cta-btn:disabled { opacity: 0.55; cursor: default; transform: none; }

/* Instructions block (after reserve) */
.sp-instructions {
    background: linear-gradient(135deg, rgba(52,211,153,0.08), rgba(34,197,94,0.04));
    border: 1.5px solid rgba(34,197,94,0.3);
    border-radius: 16px;
    padding: 1.5rem 1.25rem;
    margin-bottom: 1.5rem;
}
.sp-ref-code {
    display: inline-block;
    background: #0d1f0d;
    border: 1px solid #22c55e;
    color: #86efac;
    font-family: 'Courier New', monospace;
    font-weight: 800;
    font-size: 1rem;
    padding: 0.5rem 0.9rem;
    border-radius: 10px;
    letter-spacing: 0.05em;
    margin: 0.35rem 0 0.9rem;
}
.sp-steps {
    display: flex; flex-direction: column; gap: 0.65rem;
    margin-top: 0.75rem; text-align: left;
}
.sp-step {
    display: flex; align-items: flex-start; gap: 0.7rem;
    font-size: 0.85rem; color: var(--text);
}
.sp-step-num {
    width: 24px; height: 24px; border-radius: 50%;
    background: rgba(34,197,94,0.2); color: #22c55e;
    font-size: 0.78rem; font-weight: 800;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; border: 1px solid rgba(34,197,94,0.35);
}
.sp-gcash {
    background: #0d1f0d;
    border: 1px solid #22c55e;
    color: #86efac;
    font-family: 'Courier New', monospace;
    font-size: 1rem;
    font-weight: 800;
    padding: 0.45rem 0.8rem;
    border-radius: 8px;
    display: inline-block;
    letter-spacing: 0.05em;
}

/* Active pass card */
.sp-active-card {
    background: linear-gradient(135deg, rgba(251,191,36,0.1), rgba(217,119,6,0.05));
    border: 1.5px solid rgba(251,191,36,0.4);
    border-radius: 16px;
    padding: 1.5rem 1.25rem;
    text-align: center;
    margin-bottom: 1.5rem;
}
.sp-active-card .sp-active-title { color: #fbbf24; font-size: 1.2rem; font-weight: 900; margin-bottom: 0.35rem; }
.sp-active-card .sp-active-sub   { color: var(--text-muted); font-size: 0.85rem; }

/* FAQ */
.sp-faq { background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px; overflow: hidden; }
.sp-faq-item { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); }
.sp-faq-item:last-child { border-bottom: none; }
.sp-faq-q { font-weight: 800; color: var(--text); font-size: 0.9rem; margin-bottom: 0.35rem; }
.sp-faq-a { font-size: 0.8rem; color: var(--text-muted); line-height: 1.6; }

h2.sp-h2 { font-size: 1.1rem; font-weight: 800; color: var(--text); margin: 1.75rem 0 0.85rem; }
</style>

<div class="sp-page">

    <!-- Hero -->
    <div class="sp-hero">
        <div class="sp-tag">⚡ Pre-Sale · Limited Spots</div>
        <h1>Argonar Season 1 Pass</h1>
        <p class="sp-sub">Lock in the season before the first match. 4 tournament entries, 2,000 H-Coins, Founder avatar, and early registration access.</p>
        <div class="sp-price">
            <span class="peso">₱999</span>
            <span class="per">one-time · full season</span>
        </div>
    </div>

    <!-- Active pass banner -->
    <?php if ($current_pass && $current_pass['status'] === 'active'): ?>
    <div class="sp-active-card">
        <div class="sp-active-title"><i class="bi bi-patch-check-fill"></i> Your Season Pass is Active</div>
        <div class="sp-active-sub">
            Tournaments used: <?= (int)$current_pass['tournaments_used'] ?> / <?= (int)$current_pass['tournaments_max'] ?>
            <?php if ($current_pass['expires_at']): ?> · Expires <?= date('M j, Y', strtotime($current_pass['expires_at'])) ?><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Benefits grid -->
    <h2 class="sp-h2">What's included</h2>
    <div class="sp-benefits">
        <div class="sp-benefit">
            <div class="sp-benefit-ico" style="background:rgba(251,191,36,0.12);color:#fbbf24;"><i class="bi bi-trophy-fill"></i></div>
            <div class="sp-benefit-title">4 Tournament Entries</div>
            <div class="sp-benefit-desc">Free entry (worth ₱400 solo, ₱2,000 team) for any 4 Dota 2 tournaments in Season 1.</div>
        </div>
        <div class="sp-benefit">
            <div class="sp-benefit-ico" style="background:rgba(124,58,237,0.15);color:#a78bfa;"><i class="bi bi-coin"></i></div>
            <div class="sp-benefit-title">2,000 H-Coins Bonus</div>
            <div class="sp-benefit-desc">Credited on activation. Use in the marketplace or stake on match predictions.</div>
        </div>
        <div class="sp-benefit">
            <div class="sp-benefit-ico" style="background:rgba(52,211,153,0.12);color:#22c55e;"><i class="bi bi-person-badge-fill"></i></div>
            <div class="sp-benefit-title">Founder Avatar &amp; Badge</div>
            <div class="sp-benefit-desc">Permanent profile badge marking you as a Season 1 founder — visible forever on brackets, Hall of Fame, and marketplace.</div>
        </div>
        <div class="sp-benefit">
            <div class="sp-benefit-ico" style="background:rgba(96,165,250,0.15);color:#60a5fa;"><i class="bi bi-clock-history"></i></div>
            <div class="sp-benefit-title">Early Registration Access</div>
            <div class="sp-benefit-desc">24h early window to register for every tournament in Season 1 — first pick of slots before public opens.</div>
        </div>
    </div>

    <!-- CTA block -->
    <?php if (!$logged_in): ?>
    <div class="sp-cta-card">
        <div style="font-size:0.9rem;color:var(--text);margin-bottom:0.75rem;font-weight:700;">
            <i class="bi bi-person-lock" style="color:var(--accent-light);"></i> Sign in to reserve your spot
        </div>
        <a href="<?= base_url('login.php') ?>?next=<?= urlencode('/season-pass.php') ?>" class="sp-cta-btn">
            <i class="bi bi-box-arrow-in-right"></i> Login / Register
        </a>
    </div>

    <?php elseif ($current_pass && $current_pass['status'] === 'pending'): ?>
    <!-- Pending payment instructions -->
    <div class="sp-instructions">
        <div style="font-size:1rem;font-weight:800;color:#86efac;margin-bottom:0.25rem;">
            <i class="bi bi-receipt"></i> Your Pass is Reserved — Complete Payment
        </div>
        <div style="font-size:0.8rem;color:var(--text-muted);">Reference code for this reservation:</div>
        <div class="sp-ref-code"><?= htmlspecialchars($current_pass['ref_code']) ?></div>

        <div class="sp-steps">
            <div class="sp-step">
                <span class="sp-step-num">1</span>
                <span>Open <strong>GCash</strong> → Send Money.</span>
            </div>
            <div class="sp-step">
                <span class="sp-step-num">2</span>
                <span>Send <strong>₱999.00</strong> to <span class="sp-gcash">0927 872 8916</span></span>
            </div>
            <div class="sp-step">
                <span class="sp-step-num">3</span>
                <span>Enter the reference code <code style="color:#86efac;"><?= htmlspecialchars($current_pass['ref_code']) ?></code> in the GCash message field.</span>
            </div>
            <div class="sp-step">
                <span class="sp-step-num">4</span>
                <span>We activate your pass within <strong>24 hours</strong>. You'll get a bell notification with +2,000 HC credited.</span>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- Active (pass already shown above) OR new reservation -->
    <?php if (!$current_pass): ?>
    <div class="sp-cta-card">
        <div style="font-size:0.9rem;color:var(--text);margin-bottom:0.75rem;">
            Click below to reserve your Season Pass. You'll get a reference code and GCash payment instructions.
        </div>
        <button class="sp-cta-btn" id="spReserveBtn" onclick="spReserve()">
            <i class="bi bi-ticket-perforated-fill"></i> Reserve My Season Pass
        </button>
        <div id="spReserveErr" style="display:none;margin-top:0.75rem;color:#fca5a5;font-size:0.8rem;"></div>
    </div>
    <?php endif; ?>

    <?php endif; ?>

    <!-- FAQ -->
    <h2 class="sp-h2">Questions</h2>
    <div class="sp-faq">
        <div class="sp-faq-item">
            <div class="sp-faq-q">Why pre-sale?</div>
            <div class="sp-faq-a">Argonar needs prize pool + production funding before Season 1 can go big. The pre-sale lets founding players fund the season and lock in a discounted bundle — everyone wins.</div>
        </div>
        <div class="sp-faq-item">
            <div class="sp-faq-q">What counts as one of the 4 tournaments?</div>
            <div class="sp-faq-a">Any Argonar-organized Dota 2 tournament during Season 1, solo or team entry. One entry = one tournament. Entries don't expire within the season.</div>
        </div>
        <div class="sp-faq-item">
            <div class="sp-faq-q">Is this refundable?</div>
            <div class="sp-faq-a">Full refund if Season 1 doesn't launch by September 2026. Otherwise, partial refund equal to the number of unused entries × their solo-entry-fee value.</div>
        </div>
        <div class="sp-faq-item">
            <div class="sp-faq-q">How does activation work?</div>
            <div class="sp-faq-a">Once we confirm your GCash payment (usually within 24h), your pass is marked active, 2,000 HC is credited to your wallet, and the Founder badge appears on your profile. You'll get a notification in the bell.</div>
        </div>
        <div class="sp-faq-item">
            <div class="sp-faq-q">Will there be more passes after this?</div>
            <div class="sp-faq-a">Yes — but Season 1 Founder passes are the only ones that get the permanent "S1 Founder" badge. Later passes are Season-specific and don't carry the founder marker.</div>
        </div>
    </div>

</div>

<script>
async function spReserve() {
    const btn = document.getElementById('spReserveBtn');
    const err = document.getElementById('spReserveErr');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Reserving…';
    err.style.display = 'none';
    try {
        const r = await fetch('<?= base_url("api/season-pass-reserve.php") ?>', {
            method: 'POST',
            credentials: 'include',
        });
        const d = await r.json();
        if (!d.ok) {
            err.textContent = d.error || 'Something went wrong. Try again.';
            err.style.display = 'block';
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-ticket-perforated-fill"></i> Reserve My Season Pass';
            return;
        }
        // Reload to render the pending-payment instructions block
        location.reload();
    } catch (e) {
        err.textContent = 'Network error — try again.';
        err.style.display = 'block';
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-ticket-perforated-fill"></i> Reserve My Season Pass';
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
