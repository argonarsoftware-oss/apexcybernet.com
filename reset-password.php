<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

if (current_user($pdo)) {
    header('Location: ' . base_url('dashboard.php'));
    exit;
}

$pageTitle       = 'Set new password';
$pageDescription = 'Choose a new password for your Argonar account.';

$token   = trim($_GET['token'] ?? ($_POST['token'] ?? ''));
$errors  = [];
$success = false;
$valid   = false;
$account = null;

if ($token === '' || strlen($token) !== 64 || !ctype_xdigit($token)) {
    $errors[] = 'Invalid reset link. Request a new one.';
} else {
    $token_hash = hash('sha256', $token);
    $q = $pdo->prepare("SELECT pr.id AS reset_id, pr.expires_at, pr.used_at, a.id, a.display_name, a.email
                        FROM password_resets pr
                        JOIN accounts a ON a.id = pr.account_id
                        WHERE pr.token_hash = ?
                        LIMIT 1");
    $q->execute([$token_hash]);
    $row = $q->fetch();

    if (!$row)                                $errors[] = 'This reset link is invalid or has already been used.';
    elseif (!empty($row['used_at']))          $errors[] = 'This reset link was already used.';
    elseif (strtotime($row['expires_at']) < time()) $errors[] = 'This reset link has expired. Request a new one.';
    else {
        $valid   = true;
        $account = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid && empty($errors)) {
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    if (strlen($password) < 6)           $errors[] = 'Password must be at least 6 characters.';
    elseif ($password !== $confirm)       $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE accounts SET password_hash = ? WHERE id = ?")->execute([$hash, $account['id']]);
            $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?")->execute([$account['reset_id']]);
            // Invalidate any other outstanding reset links for this account
            $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE account_id = ? AND used_at IS NULL AND id <> ?")
                ->execute([$account['id'], $account['reset_id']]);
            $pdo->commit();
            $success = true;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Could not update password. Try again.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="reg-container">
    <a href="<?= base_url('login.php') ?>" class="back-link">
        <i class="bi bi-arrow-left"></i> Back to login
    </a>

    <div class="reg-card">
        <h2><i class="bi bi-key"></i> Set a new password</h2>

        <?php if ($success): ?>
            <div style="background:rgba(34,197,94,0.1); border:1px solid rgba(34,197,94,0.3); border-radius:10px; padding:1.25rem; text-align:center; margin-top:1rem;">
                <i class="bi bi-check-circle-fill" style="color:var(--success); font-size:1.8rem;"></i>
                <div style="font-weight:700; color:var(--success); margin-top:0.5rem;">Password updated</div>
                <div style="font-size:0.85rem; color:var(--text-muted); margin-top:0.4rem;">You can now log in with your new password.</div>
                <a href="<?= base_url('login.php') ?>" class="btn-submit" style="margin-top:1rem; display:inline-flex;">
                    <i class="bi bi-box-arrow-in-right"></i> Log in
                </a>
            </div>
        <?php elseif (!$valid): ?>
            <p class="subtitle" style="color:#f87171;"><?= htmlspecialchars($errors[0] ?? 'This reset link is no longer valid.') ?></p>
            <a href="<?= base_url('forgot-password.php') ?>" class="btn-submit" style="margin-top:0.75rem; display:inline-flex;">
                <i class="bi bi-arrow-clockwise"></i> Request new link
            </a>
        <?php else: ?>
            <p class="subtitle">for <strong><?= htmlspecialchars($account['email']) ?></strong></p>

            <?php if (!empty($errors)): ?>
                <div class="alert-custom alert-danger">
                    <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <div class="mb-3">
                    <label class="form-label">New password</label>
                    <input type="password" name="password" class="form-control" placeholder="At least 6 characters" required minlength="6" autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm new password</label>
                    <input type="password" name="password_confirm" class="form-control" placeholder="Repeat password" required minlength="6">
                </div>
                <button type="submit" class="btn-submit">
                    <i class="bi bi-check-lg"></i> Update password
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
