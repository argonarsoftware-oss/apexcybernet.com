<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/layout.php';

if (!empty($_SESSION['account_id'])) {
    header('Location: ./');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $display_name     = trim($_POST['display_name'] ?? '');
    $email            = strtolower(trim($_POST['email'] ?? ''));
    $contact_number   = trim($_POST['contact_number'] ?? '');
    $password         = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    if ($display_name === '')
        $errors[] = 'Username is required.';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Valid email is required.';
    if (strlen($password) < 6)
        $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $password_confirm)
        $errors[] = 'Passwords do not match.';
    if (empty($_POST['agree_terms']))
        $errors[] = 'You must agree to the Terms of Service and Privacy Policy.';

    if (empty($errors)) {
        $ck = $pdo->prepare("SELECT 1 FROM accounts WHERE email = ?");
        $ck->execute([$email]);
        if ($ck->fetch()) $errors[] = 'An account with this email already exists.';

        $ck2 = $pdo->prepare("SELECT 1 FROM accounts WHERE display_name = ?");
        $ck2->execute([$display_name]);
        if ($ck2->fetch()) $errors[] = 'That username is already taken.';
    }

    if (empty($errors)) {
        $hash     = password_hash($password, PASSWORD_DEFAULT);
        $ref_code = 'ACC-' . strtoupper(bin2hex(random_bytes(4)));
        $pdo->prepare("INSERT INTO accounts (email, display_name, contact_number, password_hash, ref_code, ref_type, claim_status, h_coins)
            VALUES (?, ?, ?, ?, ?, 'team', 'approved', 20)")
            ->execute([$email, $display_name, $contact_number, $hash, $ref_code]);
        $_SESSION['account_id'] = (int)$pdo->lastInsertId();
        header('Location: ./');
        exit;
    }
}

m_head('Create Account');
?>

<div style="min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:2rem 1.5rem 3rem;">

    <!-- Header -->
    <div style="text-align:center;margin-bottom:2rem;">
        <img src="<?= m_base('../images/hcoin-icon.png') ?>" style="width:64px;height:64px;border-radius:18px;margin-bottom:1rem;" onerror="this.style.display='none'">
        <div style="font-size:1.5rem;font-weight:900;color:#fff;">HCoin Wallet</div>
        <div style="font-size:0.82rem;color:var(--muted);margin-top:4px;">Create your Apex Cybernet account</div>
    </div>

    <!-- Errors -->
    <?php if ($errors): ?>
    <div style="background:#450a0a;border:1px solid var(--red);color:#fca5a5;border-radius:12px;padding:0.85rem 1rem;font-size:0.83rem;font-weight:600;width:100%;max-width:360px;margin-bottom:1rem;line-height:1.6;">
        <?php foreach ($errors as $e): ?>
        <div><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Form -->
    <form method="POST" style="width:100%;max-width:360px;">
        <div class="m-field">
            <label class="m-lbl">Username</label>
            <input class="m-inp" type="text" name="display_name"
                   placeholder="Your in-game name or handle"
                   value="<?= htmlspecialchars($_POST['display_name'] ?? '') ?>"
                   autocomplete="username" required autofocus>
        </div>
        <div class="m-field">
            <label class="m-lbl">Email</label>
            <input class="m-inp" type="email" name="email"
                   placeholder="your@email.com"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                   autocomplete="email" required>
        </div>
        <div class="m-field">
            <label class="m-lbl">Contact Number <span style="font-weight:400;color:var(--muted);">(optional)</span></label>
            <input class="m-inp" type="tel" name="contact_number"
                   placeholder="09XX XXX XXXX"
                   value="<?= htmlspecialchars($_POST['contact_number'] ?? '') ?>">
        </div>
        <div class="m-field">
            <label class="m-lbl">Password</label>
            <div style="position:relative;">
                <input class="m-inp" type="password" id="pw1" name="password"
                       placeholder="At least 6 characters"
                       autocomplete="new-password" required minlength="6"
                       style="padding-right:3rem;">
                <button type="button" onclick="togglePw('pw1',this)"
                        style="position:absolute;right:0.85rem;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--muted);font-size:1rem;cursor:pointer;padding:0;">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
        </div>
        <div class="m-field">
            <label class="m-lbl">Confirm Password</label>
            <div style="position:relative;">
                <input class="m-inp" type="password" id="pw2" name="password_confirm"
                       placeholder="Repeat your password"
                       autocomplete="new-password" required
                       style="padding-right:3rem;">
                <button type="button" onclick="togglePw('pw2',this)"
                        style="position:absolute;right:0.85rem;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--muted);font-size:1rem;cursor:pointer;padding:0;">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
        </div>

        <!-- Terms -->
        <div style="margin-bottom:1.25rem;">
            <label style="display:flex;align-items:flex-start;gap:0.6rem;font-size:0.8rem;color:var(--muted);cursor:pointer;line-height:1.6;">
                <input type="checkbox" name="agree_terms" required
                       style="margin-top:3px;width:16px;height:16px;accent-color:var(--accent);flex-shrink:0;">
                <span>I agree to the
                    <a href="https://apexcybernet.com/terms.php" target="_blank" style="color:var(--accent-l);">Terms of Service</a>
                    and
                    <a href="https://apexcybernet.com/privacy.php" target="_blank" style="color:var(--accent-l);">Privacy Policy</a>.
                </span>
            </label>
        </div>

        <button type="submit" class="m-btn m-btn-primary m-gap">
            <i class="bi bi-person-check"></i> Create Account
        </button>
    </form>

    <!-- Footer links -->
    <div style="margin-top:1.5rem;text-align:center;font-size:0.8rem;color:var(--muted);">
        Already have an account?
        <a href="./login.php" style="color:var(--accent-l);font-weight:700;">Sign In</a>
    </div>
    <a href="https://apexcybernet.com/?prefer_full=1" style="display:flex;align-items:center;justify-content:space-between;margin-top:1rem;padding:0.9rem 1.1rem;background:var(--card);border:1px solid var(--border);border-radius:14px;color:var(--text);text-decoration:none;">
        <div style="display:flex;align-items:center;gap:0.75rem;">
            <div style="width:36px;height:36px;border-radius:10px;background:rgba(251,191,36,0.12);color:#fbbf24;display:flex;align-items:center;justify-content:center;font-size:1rem;">
                <i class="bi bi-trophy-fill"></i>
            </div>
            <div>
                <div style="font-weight:800;font-size:0.88rem;">Tournament Site</div>
                <div style="font-size:0.68rem;color:var(--muted);margin-top:1px;">Brackets, predictions, leaderboard &amp; more</div>
            </div>
        </div>
        <i class="bi bi-arrow-up-right" style="color:var(--muted);"></i>
    </a>
</div>

<?php m_foot(); ?>
<script>
function togglePw(id, btn) {
    var inp = document.getElementById(id);
    var ico = btn.querySelector('i');
    inp.type = inp.type === 'password' ? 'text' : 'password';
    ico.className = inp.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}
</script>
