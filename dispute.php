<?php
require_once __DIR__ . '/includes/db.php';

$pageTitle = 'File a Complaint or Dispute — Argonar Dota 2 Tournament';
$pageDescription = 'Report unfair play, rank manipulation, smurfing, or other violations during the Argonar Dota 2 Tournament. All complaints reviewed by the organizers.';
$canonicalUrl = canonical_url('dispute.php');
$extraHead = breadcrumb_jsonld([
    ['name' => 'Home',     'url' => 'https://argonar.co/'],
    ['name' => 'Disputes', 'url' => 'https://argonar.co/dispute.php'],
]);
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $player_name = trim($_POST['player_name'] ?? '');
    $ref_code = strtoupper(trim($_POST['ref_code'] ?? ''));
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($player_name)) $errors[] = 'Your name is required.';
    if (empty($subject)) $errors[] = 'Subject is required.';
    if (empty($message)) $errors[] = 'Please describe your complaint.';

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO disputes (ref_code, player_name, subject, message) VALUES (?, ?, ?, ?)");
        $stmt->execute([$ref_code, $player_name, $subject, $message]);
        $success = true;
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="reg-container" style="max-width:650px;">
    <a href="<?= base_url() ?>" class="back-link"><i class="bi bi-arrow-left"></i> Back to Home</a>

    <div class="reg-card">
        <h2><i class="bi bi-flag-fill" style="color:var(--danger);"></i> File a Complaint</h2>
        <p class="subtitle">Report unfair play, rule violations, or any issues. All complaints are reviewed by the organizers.</p>

        <?php if ($success): ?>
            <div class="alert-custom alert-success">
                <i class="bi bi-check-circle-fill"></i>
                <strong>Complaint submitted!</strong> The organizers will review your report. Thank you for helping maintain a fair tournament.
            </div>
        <?php else: ?>
            <?php if (!empty($errors)): ?>
                <div class="alert-custom alert-danger">
                    <i class="bi bi-exclamation-circle"></i>
                    <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
                </div>
            <?php endif; ?>

            <form method="POST">

                <div class="mb-3">
                    <label class="form-label">Your Name</label>
                    <input type="text" name="player_name" class="form-control" placeholder="In-game name or real name"
                           value="<?= htmlspecialchars($_POST['player_name'] ?? '') ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Reference Code <span style="color:var(--text-muted); font-weight:400;">(optional)</span></label>
                    <input type="text" name="ref_code" class="form-control" placeholder="e.g. DOTA-T-A1B2"
                           value="<?= htmlspecialchars($_POST['ref_code'] ?? '') ?>"
                           style="text-transform:uppercase;">
                    <div class="form-text" style="font-size:0.75rem; color:var(--text-muted);">Your registration code, if applicable.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Subject</label>
                    <select name="subject" class="form-control form-select" required>
                        <option value="">Select a reason</option>
                        <option value="Rank Manipulation" <?= ($_POST['subject'] ?? '') === 'Rank Manipulation' ? 'selected' : '' ?>>Rank Manipulation</option>
                        <option value="Cheating / Hacking" <?= ($_POST['subject'] ?? '') === 'Cheating / Hacking' ? 'selected' : '' ?>>Cheating / Hacking</option>
                        <option value="Smurfing" <?= ($_POST['subject'] ?? '') === 'Smurfing' ? 'selected' : '' ?>>Smurfing</option>
                        <option value="Unsportsmanlike Conduct" <?= ($_POST['subject'] ?? '') === 'Unsportsmanlike Conduct' ? 'selected' : '' ?>>Unsportsmanlike Conduct</option>
                        <option value="Match Fixing" <?= ($_POST['subject'] ?? '') === 'Match Fixing' ? 'selected' : '' ?>>Match Fixing</option>
                        <option value="Lying About Skill Level" <?= ($_POST['subject'] ?? '') === 'Lying About Skill Level' ? 'selected' : '' ?>>Lying About Skill Level</option>
                        <option value="False Information" <?= ($_POST['subject'] ?? '') === 'False Information' ? 'selected' : '' ?>>False Information</option>
                        <option value="Payment Issue" <?= ($_POST['subject'] ?? '') === 'Payment Issue' ? 'selected' : '' ?>>Payment Issue</option>
                        <option value="Other" <?= ($_POST['subject'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Describe the Issue</label>
                    <textarea name="message" class="form-control" rows="5" placeholder="Provide details about what happened, who was involved, and any evidence you have..." required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                </div>

                <div style="background:rgba(245,158,11,0.08); border:1px solid rgba(245,158,11,0.25); border-radius:10px; padding:0.75rem 1rem; margin-bottom:1.25rem; font-size:0.8rem; color:var(--text-muted);">
                    <i class="bi bi-info-circle" style="color:var(--warning);"></i>
                    All complaints are reviewed by <strong>Argonar</strong>. False or malicious reports may result in penalties.
                </div>

                <button type="submit" class="btn-submit">
                    <i class="bi bi-send"></i> Submit Complaint
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
