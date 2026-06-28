<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';

if (current_user($pdo)) {
    header('Location: ' . base_url('dashboard.php'));
    exit;
}

// ── Self-bootstrap password_resets table ──
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        account_id  INT NOT NULL,
        token_hash  CHAR(64) NOT NULL,
        expires_at  DATETIME NOT NULL,
        used_at     DATETIME NULL,
        ip          VARCHAR(45),
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_token (token_hash),
        INDEX idx_account (account_id),
        INDEX idx_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

$pageTitle = 'Reset Password';
$pageDescription = 'Reset your Apex Cybernet account password.';
$sent = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    } else {
        // Look up user — but never reveal whether account exists (prevents enumeration).
        $q = $pdo->prepare("SELECT id, display_name, email FROM accounts WHERE LOWER(email) = ? AND claim_status = 'approved'");
        $q->execute([$email]);
        $account = $q->fetch();

        if ($account) {
            // Rate-limit: ignore if we've sent one in the last 2 minutes
            $rl = $pdo->prepare("SELECT 1 FROM password_resets WHERE account_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 2 MINUTE)");
            $rl->execute([$account['id']]);
            if (!$rl->fetch()) {
                // Generate token: 32 bytes random → 64 hex chars. Store only the SHA-256 hash.
                $token      = bin2hex(random_bytes(32));
                $token_hash = hash('sha256', $token);

                $pdo->prepare("INSERT INTO password_resets (account_id, token_hash, expires_at, ip) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR), ?)")
                    ->execute([$account['id'], $token_hash, $_SERVER['REMOTE_ADDR'] ?? '']);

                $reset_url = rtrim(base_url(''), '/') . '/reset-password.php?token=' . $token;
                $name      = htmlspecialchars($account['display_name'] ?: 'there');

                $html = '<div style="font-family:Arial,sans-serif;max-width:520px;margin:0 auto;padding:1.5rem;color:#222;">'
                      . '<h2 style="color:#7c3aed;margin:0 0 1rem;">Reset your Apex Cybernet password</h2>'
                      . '<p>Hi ' . $name . ',</p>'
                      . '<p>Someone (hopefully you) asked to reset the password on your Apex Cybernet account. Click the button below to set a new password. This link is good for <strong>1 hour</strong>.</p>'
                      . '<p style="margin:1.5rem 0;text-align:center;">'
                      . '<a href="' . htmlspecialchars($reset_url) . '" style="background:#7c3aed;color:#fff;text-decoration:none;padding:0.75rem 1.5rem;border-radius:8px;font-weight:700;display:inline-block;">Reset password</a>'
                      . '</p>'
                      . '<p style="font-size:0.8rem;color:#666;">Or paste this link into your browser:<br><code style="word-break:break-all;background:#f3f4f6;padding:0.25rem 0.45rem;border-radius:4px;">' . htmlspecialchars($reset_url) . '</code></p>'
                      . '<hr style="border:none;border-top:1px solid #eee;margin:1.5rem 0;">'
                      . '<p style="font-size:0.75rem;color:#999;">If you didn\'t request this, you can ignore this email — your password won\'t change. For your security, do not share this link with anyone.</p>'
                      . '<p style="font-size:0.75rem;color:#999;">— Apex Cybernet</p>'
                      . '</div>';

                $text = "Hi " . ($account['display_name'] ?: 'there') . ",\n\n"
                      . "Someone asked to reset the password on your Apex Cybernet account. Use this link to set a new password (valid for 1 hour):\n\n"
                      . $reset_url . "\n\n"
                      . "If you didn't request this, you can ignore this email.\n\n— Apex Cybernet";

                send_email($account['email'], 'Reset your Apex Cybernet password', $html, $text);
            }
        }
        // Always show success (don't leak whether email exists).
        $sent = true;
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="reg-container">
    <a href="<?= base_url('login.php') ?>" class="back-link">
        <i class="bi bi-arrow-left"></i> Back to login
    </a>

    <div class="reg-card">
        <h2><i class="bi bi-key"></i> Forgot password</h2>
        <p class="subtitle">Enter the email on your Apex Cybernet account — we'll send you a reset link.</p>

        <?php if ($sent): ?>
            <div style="background:rgba(34,197,94,0.1); border:1px solid rgba(34,197,94,0.3); border-radius:10px; padding:1.25rem; text-align:center; margin-top:1rem;">
                <i class="bi bi-envelope-check-fill" style="color:var(--success); font-size:1.8rem;"></i>
                <div style="font-weight:700; color:var(--success); margin-top:0.5rem;">Check your inbox</div>
                <div style="font-size:0.85rem; color:var(--text-muted); margin-top:0.4rem; line-height:1.5;">
                    If that email is on an Apex Cybernet account, a reset link is on the way. The link is good for 1 hour.<br>
                    Didn't get it? Check spam, or try again in 2 minutes.
                </div>
                <a href="<?= base_url('login.php') ?>" class="btn-submit" style="margin-top:1rem; display:inline-flex;">
                    <i class="bi bi-box-arrow-in-right"></i> Back to login
                </a>
            </div>
        <?php else: ?>
            <?php if (!empty($errors)): ?>
                <div class="alert-custom alert-danger">
                    <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" placeholder="your@email.com"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
                </div>
                <button type="submit" class="btn-submit">
                    <i class="bi bi-send-fill"></i> Send reset link
                </button>
                <div style="text-align:center; margin-top:0.85rem; font-size:0.78rem; color:var(--text-muted);">
                    Remember it? <a href="<?= base_url('login.php') ?>" style="color:var(--accent-light); text-decoration:none; font-weight:700;">Log in</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
