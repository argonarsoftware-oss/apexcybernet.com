<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

if (current_user($pdo)) {
    header('Location: ' . base_url('dashboard.php'));
    exit;
}

$pageTitle = 'Account';
$pageDescription = 'Log in or create your Argonar Tournament account.';
$tab = $_GET['tab'] ?? 'login';
$errors = [];

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_login'])) {
    $tab = 'login';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM accounts WHERE display_name = ?");
    $stmt->execute([$username]);
    $account = $stmt->fetch();

    if (!$account || !password_verify($password, $account['password_hash'])) {
        $errors[] = 'Invalid username or password.';
    } else {
        $_SESSION['account_id'] = $account['id'];
        $redirect = !empty($_GET['mobile']) ? base_url('mobile/index.php') : base_url('dashboard.php');
        header('Location: ' . $redirect);
        exit;
    }
}

// Handle register
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_register'])) {
    $tab = 'register';
    $email          = strtolower(trim($_POST['email'] ?? ''));
    $display_name   = trim($_POST['display_name'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $password       = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    if ($display_name === '') {
        $errors[] = 'Username is required.';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($password !== $password_confirm) {
        $errors[] = 'Passwords do not match.';
    }
    if (empty($_POST['agree_terms'])) {
        $errors[] = 'You must agree to the Terms of Service and Privacy Policy.';
    }
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT 1 FROM accounts WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) $errors[] = 'An account with this email already exists.';

        $stmt = $pdo->prepare("SELECT 1 FROM accounts WHERE display_name = ?");
        $stmt->execute([$display_name]);
        if ($stmt->fetch()) $errors[] = 'That username is already taken.';
    }
    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $ref_code = 'ACC-' . strtoupper(bin2hex(random_bytes(4)));
        $stmt = $pdo->prepare("INSERT INTO accounts (email, display_name, contact_number, password_hash, ref_code, ref_type, claim_status, h_coins) VALUES (?, ?, ?, ?, ?, 'team', 'approved', 20)");
        $stmt->execute([$email, $display_name, $contact_number, $hash, $ref_code]);
        $new_id = $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO h_coin_transactions (account_id, type, amount, reason, ref) VALUES (?, 'credit', 20, 'welcome_bonus', 'Welcome gift')")->execute([$new_id]);
        $_SESSION['account_id'] = $new_id;
        header('Location: ' . base_url('dashboard.php'));
        exit;
    }
}

$hideCompany = true; // scrub company name from meta/SEO on this page
require_once __DIR__ . '/includes/header.php';
?>

<style>
.auth-page {
    min-height: calc(100vh - 70px);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem 1rem;
    position: relative;
}

.auth-page::before {
    content: '';
    position: fixed;
    top: -150px;
    left: 50%;
    transform: translateX(-50%);
    width: 800px;
    height: 800px;
    background: radial-gradient(circle, rgba(124, 58, 237, 0.1) 0%, transparent 65%);
    pointer-events: none;
    z-index: 0;
}

.auth-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 20px;
    width: 100%;
    max-width: 420px;
    overflow: hidden;
    position: relative;
    z-index: 1;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.4);
}

.auth-header {
    padding: 1.5rem 2rem 0;
    text-align: center;
    position: relative;
}

.auth-logo {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 1.75rem;
    margin-bottom: 1.25rem;
    text-decoration: none;
}

.auth-logo-icon {
    width: 36px;
    height: 36px;
    background: var(--accent);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    color: #fff;
}

.auth-logo-text {
    font-size: 1.2rem;
    font-weight: 800;
    color: var(--accent-light);
    letter-spacing: -0.5px;
}

.auth-logo-text span {
    color: var(--text);
    font-weight: 400;
}

/* Tab switcher */
.auth-tabs {
    display: flex;
    background: var(--bg-dark);
    border-radius: 12px;
    padding: 4px;
    margin: 0 2rem 0;
    gap: 4px;
}

.auth-tab {
    flex: 1;
    padding: 0.6rem 0.75rem;
    text-align: center;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.85rem;
    border-radius: 9px;
    color: var(--text-muted);
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
}

.auth-tab.active {
    background: var(--accent);
    color: #fff;
    box-shadow: 0 2px 8px rgba(124, 58, 237, 0.4);
}

.auth-tab:not(.active):hover {
    color: var(--text);
    background: rgba(255,255,255,0.05);
}

/* Form area */
.auth-body {
    padding: 1.5rem 2rem 2rem;
}

.auth-errors {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    border-radius: 10px;
    padding: 0.85rem 1rem;
    margin-bottom: 1.25rem;
    font-size: 0.875rem;
    color: #fca5a5;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.auth-errors i {
    margin-right: 0.4rem;
}

.auth-field {
    margin-bottom: 1rem;
}

.auth-field label {
    display: block;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.45rem;
}

.auth-input-wrap {
    position: relative;
}

.auth-input-wrap .field-icon {
    position: absolute;
    left: 0.9rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    font-size: 0.9rem;
    pointer-events: none;
    transition: color 0.2s;
}

.auth-input-wrap input {
    width: 100%;
    background: var(--bg-dark);
    border: 1px solid var(--border);
    border-radius: 10px;
    color: var(--text);
    padding: 0.7rem 1rem 0.7rem 2.5rem;
    font-size: 0.9rem;
    font-family: inherit;
    transition: border-color 0.2s, box-shadow 0.2s;
    outline: none;
}

.auth-input-wrap input:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.18);
}

.auth-input-wrap input:focus ~ .field-icon,
.auth-input-wrap:focus-within .field-icon {
    color: var(--accent-light);
}

.auth-input-wrap input::placeholder {
    color: #4b5563;
}

/* Password toggle */
.pw-wrap {
    position: relative;
}

.pw-wrap input {
    padding-right: 2.8rem !important;
}

.pw-toggle {
    position: absolute;
    right: 0.9rem;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    font-size: 0.9rem;
    padding: 0;
    line-height: 1;
    transition: color 0.2s;
}

.pw-toggle:hover {
    color: var(--accent-light);
}

/* Optional badge */
.label-optional {
    font-size: 0.7rem;
    font-weight: 400;
    color: #4b5563;
    text-transform: none;
    letter-spacing: 0;
    margin-left: 0.3rem;
}

/* Submit button */
.auth-submit {
    width: 100%;
    padding: 0.8rem;
    background: var(--accent);
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: 0.9rem;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 1.25rem;
    transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
    letter-spacing: 0.3px;
    font-family: inherit;
    box-shadow: 0 4px 14px rgba(124, 58, 237, 0.35);
}

.auth-submit:hover {
    background: #6d28d9;
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(124, 58, 237, 0.45);
}

.auth-submit:active {
    transform: translateY(0);
}

/* Footer links */
.auth-foot {
    text-align: center;
    padding-top: 1rem;
    font-size: 0.8rem;
    color: var(--text-muted);
}

.auth-foot a {
    color: var(--accent-light);
    text-decoration: none;
}

.auth-foot a:hover {
    text-decoration: underline;
}

.auth-divider {
    border: none;
    border-top: 1px solid var(--border);
    margin: 0 2rem;
}

.back-home {
    position: absolute;
    top: 1.1rem;
    left: 1.25rem;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--text-muted);
    text-decoration: none;
    letter-spacing: 0.02em;
    transition: color 0.2s;
}

.back-home i { font-size: 0.7rem; }

.back-home:hover { color: var(--accent-light); }

@media (max-width: 480px) {
    .auth-card { border-radius: 16px; }
    .auth-body { padding: 1.25rem 1.5rem 1.75rem; }
    .auth-tabs { margin: 0 1.5rem; }
    .auth-header { padding: 1.75rem 1.5rem 0; }
    .auth-divider { margin: 0 1.5rem; }
}
</style>

<div class="auth-page">
    <div class="auth-card">
        <div class="auth-header">
            <a href="<?= base_url() ?>" class="back-home">
                <i class="bi bi-arrow-left"></i> Home
            </a>
            <a href="<?= base_url() ?>" class="auth-logo">
                <div class="auth-logo-icon"><i class="bi bi-controller"></i></div>
                <span class="auth-logo-text">Argonar<span> Tournament</span></span>
            </a>
        </div>

        <div class="auth-tabs">
            <a href="<?= base_url('login.php?tab=login') ?>" class="auth-tab <?= $tab === 'login' ? 'active' : '' ?>">
                <i class="bi bi-box-arrow-in-right"></i> Log In
            </a>
            <a href="<?= base_url('login.php?tab=register') ?>" class="auth-tab <?= $tab === 'register' ? 'active' : '' ?>">
                <i class="bi bi-person-plus"></i> Register
            </a>
        </div>

        <hr class="auth-divider" style="margin-top:1.25rem;">

        <div class="auth-body">
            <?php if (!empty($errors)): ?>
                <div class="auth-errors">
                    <?php foreach ($errors as $e): ?>
                        <div><i class="bi bi-exclamation-circle"></i><?= htmlspecialchars($e) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'login'): ?>
                <form method="POST">
                    <input type="hidden" name="action_login" value="1">

                    <div class="auth-field">
                        <label for="l-username">Username</label>
                        <div class="auth-input-wrap">
                            <input type="text" id="l-username" name="username"
                                   placeholder="Your username"
                                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                                   required autofocus>
                            <i class="bi bi-person field-icon"></i>
                        </div>
                    </div>

                    <div class="auth-field">
                        <label for="l-password">Password</label>
                        <div class="auth-input-wrap pw-wrap">
                            <input type="password" id="l-password" name="password"
                                   placeholder="Your password" required>
                            <i class="bi bi-lock field-icon"></i>
                            <button type="button" class="pw-toggle" onclick="togglePw('l-password', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="auth-submit">
                        <i class="bi bi-box-arrow-in-right"></i> Log In
                    </button>
                    <div style="text-align:center; margin-top:0.85rem; font-size:0.78rem; color:var(--text-muted);">
                        <a href="<?= base_url('forgot-password.php') ?>" style="color:var(--accent-light); text-decoration:none; font-weight:600;">Forgot your password?</a>
                    </div>
                </form>

            <?php else: ?>
                <div style="background:linear-gradient(135deg,rgba(251,191,36,0.12),rgba(124,58,237,0.1));border:1px solid rgba(251,191,36,0.3);border-radius:12px;padding:0.9rem 1rem;margin-bottom:1.25rem;display:flex;align-items:center;gap:0.75rem;">
                    <img src="<?= base_url('images/hcoin-icon.png') ?>" alt="H-Coin" style="width:32px;height:32px;object-fit:contain;flex-shrink:0;">
                    <div>
                        <div style="font-size:0.92rem;font-weight:800;color:#fbbf24;">Get 20 H-Coins free</div>
                        <div style="font-size:0.72rem;color:var(--text-muted);margin-top:1px;">Credited instantly when you create your account. Use them in tournaments, marketplace, and more.</div>
                    </div>
                </div>
                <form method="POST">
                    <input type="hidden" name="action_register" value="1">

                    <div class="auth-field">
                        <label for="r-name">Username</label>
                        <div class="auth-input-wrap">
                            <input type="text" id="r-name" name="display_name"
                                   placeholder="Your in-game name or handle"
                                   value="<?= htmlspecialchars($_POST['display_name'] ?? '') ?>"
                                   required autofocus>
                            <i class="bi bi-person field-icon"></i>
                        </div>
                    </div>

                    <div class="auth-field">
                        <label for="r-email">Email</label>
                        <div class="auth-input-wrap">
                            <input type="email" id="r-email" name="email"
                                   placeholder="your@email.com"
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                   required>
                            <i class="bi bi-envelope field-icon"></i>
                        </div>
                    </div>

                    <div class="auth-field">
                        <label for="r-phone">Contact Number <span class="label-optional">(optional)</span></label>
                        <div class="auth-input-wrap">
                            <input type="tel" id="r-phone" name="contact_number"
                                   placeholder="09XX XXX XXXX"
                                   value="<?= htmlspecialchars($_POST['contact_number'] ?? '') ?>">
                            <i class="bi bi-phone field-icon"></i>
                        </div>
                    </div>

                    <div class="auth-field">
                        <label for="r-pw">Password</label>
                        <div class="auth-input-wrap pw-wrap">
                            <input type="password" id="r-pw" name="password"
                                   placeholder="At least 6 characters"
                                   required minlength="6">
                            <i class="bi bi-lock field-icon"></i>
                            <button type="button" class="pw-toggle" onclick="togglePw('r-pw', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="auth-field">
                        <label for="r-pw2">Confirm Password</label>
                        <div class="auth-input-wrap pw-wrap">
                            <input type="password" id="r-pw2" name="password_confirm"
                                   placeholder="Repeat your password" required>
                            <i class="bi bi-lock field-icon"></i>
                            <button type="button" class="pw-toggle" onclick="togglePw('r-pw2', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div style="margin-bottom:1rem;">
                        <label style="display:flex; align-items:flex-start; gap:0.5rem; font-size:0.8rem; color:var(--text-muted); cursor:pointer; line-height:1.5;">
                            <input type="checkbox" name="agree_terms" required style="margin-top:0.25rem; accent-color:var(--accent);">
                            <span>I agree to the <a href="<?= base_url('terms.php') ?>" target="_blank" style="color:var(--accent-light);">Terms of Service</a> and <a href="<?= base_url('privacy.php') ?>" target="_blank" style="color:var(--accent-light);">Privacy Policy</a>.</span>
                        </label>
                    </div>

                    <button type="submit" class="auth-submit">
                        <i class="bi bi-person-check"></i> Create Account
                    </button>
                </form>

                <div class="auth-foot" style="margin-top:1rem;">
                    By registering you agree to our
                    <a href="<?= base_url('terms.php') ?>">Terms</a> &amp;
                    <a href="<?= base_url('privacy.php') ?>">Privacy Policy</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function togglePw(inputId, btn) {
    var input = document.getElementById(inputId);
    var icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
