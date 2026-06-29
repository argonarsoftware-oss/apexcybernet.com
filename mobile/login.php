<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/layout.php';

if (!empty($_SESSION['account_id'])) {
    header('Location: ./');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare("SELECT * FROM accounts WHERE display_name = ?");
    $stmt->execute([$username]);
    $account = $stmt->fetch();
    if ($account && password_verify($password, $account['password_hash'])) {
        $_SESSION['account_id'] = $account['id'];
        header('Location: ./');
        exit;
    }
    $error = 'Invalid username or password.';
}

m_head('Sign In');
?>

<div style="min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:2rem 1.5rem;">

    <div style="text-align:center;margin-bottom:2rem;">
        <div style="font-size:1.5rem;font-weight:900;color:#fff;">Apex Cybernet</div>
        <div style="font-size:0.82rem;color:var(--muted);margin-top:4px;">Sign in to your Apex Cybernet account</div>
    </div>

    <?php if ($error): ?>
    <div style="background:#450a0a;border:1px solid var(--red);color:#fca5a5;border-radius:12px;padding:0.75rem 1rem;font-size:0.85rem;font-weight:600;width:100%;max-width:360px;margin-bottom:1rem;">
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" style="width:100%;max-width:360px;">
        <div class="m-field">
            <label class="m-lbl">Username</label>
            <input class="m-inp" type="text" name="username" placeholder="Your username" autocomplete="username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        </div>
        <div class="m-field">
            <label class="m-lbl">Password</label>
            <input class="m-inp" type="password" name="password" placeholder="••••••••" autocomplete="current-password" required>
        </div>
        <button type="submit" class="m-btn m-btn-primary">Sign In</button>
    </form>

    <div style="margin-top:1rem;padding:0 1rem;display:flex;align-items:center;gap:0.75rem;">
        <div style="flex:1;height:1px;background:var(--border);"></div>
        <div style="font-size:0.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:0.1em;font-weight:700;">or</div>
        <div style="flex:1;height:1px;background:var(--border);"></div>
    </div>

    <div style="padding:0.75rem 1rem 0;">
        <a href="./register.php" class="m-btn m-btn-ghost" style="display:flex;align-items:center;justify-content:center;gap:0.4rem;text-decoration:none;">
            <i class="bi bi-person-plus-fill" style="color:var(--accent-l);"></i> Create an Account
        </a>
    </div>
    <a href="https://apexcybernet.com/index.php?prefer_full=1" style="display:flex;align-items:center;justify-content:space-between;margin-top:1rem;padding:0.9rem 1.1rem;background:var(--card);border:1px solid var(--border);border-radius:14px;color:var(--text);text-decoration:none;">
        <div style="display:flex;align-items:center;gap:0.75rem;">
            <div style="width:36px;height:36px;border-radius:10px;background:rgba(251,191,36,0.12);color:#fbbf24;display:flex;align-items:center;justify-content:center;font-size:1rem;">
                <i class="bi bi-trophy-fill"></i>
            </div>
            <div>
                <div style="font-weight:800;font-size:0.88rem;">Tournament Site</div>
                <div style="font-size:0.68rem;color:var(--muted);margin-top:1px;">Brackets, leaderboard &amp; more</div>
            </div>
        </div>
        <i class="bi bi-arrow-up-right" style="color:var(--muted);"></i>
    </a>
</div>

<?php m_foot(); ?>
