<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/listener-api.php';

$valid_games = [
    'valorant'  => 'Valorant',
    'crossfire' => 'CrossFire',
    'dota2'     => 'Dota 2',
];

$ref = trim($_GET['ref'] ?? '');
$type = $_GET['type'] ?? 'team';

if (empty($ref)) {
    header('Location: ' . base_url());
    exit;
}

// Look up registration
$registration = null;
$game_name = '';
$amount = 0;
$description = '';

if ($type === 'solo') {
    $stmt = $pdo->prepare("SELECT * FROM solo_players WHERE ref_code = ?");
    $stmt->execute([$ref]);
    $registration = $stmt->fetch();
    if ($registration) {
        $game_name = $valid_games[$registration['game']] ?? $registration['game'];
        $amount = 100.00;
        $description = "Solo: {$registration['player_name']} - $game_name";
    }
} else {
    $stmt = $pdo->prepare("SELECT * FROM teams WHERE ref_code = ?");
    $stmt->execute([$ref]);
    $registration = $stmt->fetch();
    if ($registration) {
        $game_name = $valid_games[$registration['game']] ?? $registration['game'];
        $amount = 500.00;
        $description = "Team: {$registration['team_name']} - $game_name";
    }
}

if (!$registration) {
    flash('error', 'Registration not found.');
    header('Location: ' . base_url());
    exit;
}

// Already approved? Go to success page
if ($registration['status'] === 'approved') {
    $_SESSION['ref_code'] = $ref;
    flash('success', 'Your payment has already been confirmed!');
    header('Location: ' . base_url("success.php?type=$type&game={$registration['game']}"));
    exit;
}

// ── AJAX: Check payment status ──
// MULTI-LAYER SAFEGUARD: never auto-approve without all of the following:
//   1. listenerCheckPayment() returns paid=true
//   2. Response has a non-empty sender OR phone (real GCash transaction)
//   3. Response includes a received amount
//   4. Cross-verify: listenerGetOrder() also reports paid status
//   5. Received amount matches expected pay_amount within ±0.5 PHP (catches the unique-centavo)
//   6. Registration is still 'pending' (not racing with admin/manual update)
if (isset($_GET['action']) && $_GET['action'] === 'check') {
    header('Content-Type: application/json');

    $result = listenerCheckPayment($ref);

    // Layer 1: API must explicitly say paid=true
    if (!$result || empty($result['paid'])) {
        echo json_encode(['paid' => false]);
        exit;
    }

    // Layer 2: must have a real sender (GCash always provides this)
    $sender = trim((string)($result['sender'] ?? ''));
    $phone  = trim((string)($result['phone'] ?? ''));
    $has_sender = ($sender !== '' || $phone !== '');

    // Layer 3: must have received amount
    // Listener API field is `pay_amount` (the unique-centavo amount that was actually received)
    $received_amount = $result['pay_amount'] ?? $result['amount'] ?? $result['received_amount'] ?? null;
    $has_amount = ($received_amount !== null && (float)$received_amount > 0);

    // Layer 4: cross-verify with listenerGetOrder
    $existing = listenerGetOrder($ref);
    $order_status = $existing['order']['status'] ?? '';
    $order_paid = ($order_status === 'paid') || !empty($existing['order']['paid']) || !empty($existing['order']['paid_at']);
    $expected_amount = $existing['order']['pay_amount'] ?? $existing['order']['amount'] ?? null;

    // Layer 5: amount must match expected (within 0.5 PHP — accounts for unique centavo)
    $amount_ok = ($expected_amount !== null && $has_amount)
        && abs((float)$received_amount - (float)$expected_amount) < 0.5;

    // Build a verification report for logging
    $verification = [
        'ref' => $ref,
        'paid' => true,
        'has_sender' => $has_sender,
        'has_amount' => $has_amount,
        'order_paid' => $order_paid,
        'amount_ok' => $amount_ok,
        'expected' => $expected_amount,
        'received' => $received_amount,
        'sender' => $sender,
        'phone' => $phone,
    ];

    // ALL layers must pass
    if (!$has_sender || !$has_amount || !$order_paid || !$amount_ok) {
        error_log("[ticket.php] Payment check REJECTED: " . json_encode($verification));
        echo json_encode(['paid' => false, 'pending_verification' => true]);
        exit;
    }

    // Layer 6: race-condition guard via WHERE status='pending'
    $proof = sprintf('GCASH-AUTO | %s | %s | ₱%s | order=%s',
        $sender ?: '(no name)',
        $phone ?: '(no phone)',
        $received_amount,
        $ref
    );

    if ($type === 'solo') {
        $upd = $pdo->prepare("UPDATE solo_players SET status = 'approved', payment_proof = ? WHERE ref_code = ? AND status = 'pending'");
        $upd->execute([$proof, $ref]);
    } else {
        $upd = $pdo->prepare("UPDATE teams SET status = 'approved', payment_proof = ? WHERE ref_code = ? AND status = 'pending'");
        $upd->execute([$proof, $ref]);
    }

    if ($upd->rowCount() > 0) {
        error_log("[ticket.php] Payment APPROVED: " . json_encode($verification));
    }

    echo json_encode(['paid' => true, 'sender' => $sender, 'phone' => $phone]);
    exit;
}

// ── Cancel registration ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_registration'])) {
    if ($registration['status'] === 'pending') {
        if ($type === 'solo') {
            $pdo->prepare("DELETE FROM solo_players WHERE ref_code = ? AND status = 'pending'")->execute([$ref]);
        } else {
            $pdo->prepare("DELETE FROM teams WHERE ref_code = ? AND status = 'pending'")->execute([$ref]);
        }
        flash('success', 'Registration cancelled.');
        header('Location: ' . base_url());
        exit;
    }
}

// ── AJAX: Upload proof fallback ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_proof'])) {
    $upload_path = '';
    $payment_note = trim($_POST['payment_note'] ?? '');
    $has_file = isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK;

    if (!$has_file && $payment_note === '') {
        flash('error', 'Please upload a file or provide a note.');
        header("Location: " . base_url("ticket.php?ref=$ref&type=$type"));
        exit;
    }

    if ($has_file) {
        $file = $_FILES['payment_proof'];
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        if (!in_array($file['type'], $allowed) || $file['size'] > 5 * 1024 * 1024) {
            flash('error', 'Invalid file. Use JPG/PNG/WebP/PDF under 5MB.');
            header("Location: " . base_url("ticket.php?ref=$ref&type=$type"));
            exit;
        }
        $upload_dir = __DIR__ . '/uploads/payment_proofs';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = strtolower($ref) . '_' . time() . '.' . $ext;
        move_uploaded_file($file['tmp_name'], "$upload_dir/$filename");
        $upload_path = "uploads/payment_proofs/$filename";
    } else {
        $upload_path = "NOTE: $payment_note";
    }

    if ($type === 'solo') {
        $pdo->prepare("UPDATE solo_players SET payment_proof = ? WHERE ref_code = ?")->execute([$upload_path, $ref]);
    } else {
        $pdo->prepare("UPDATE teams SET payment_proof = ? WHERE ref_code = ?")->execute([$upload_path, $ref]);
    }

    $_SESSION['ref_code'] = $ref;
    flash('success', "Proof submitted! We'll review and confirm shortly.");
    header("Location: " . base_url("success.php?type=$type&game={$registration['game']}"));
    exit;
}

// ── Create order on Listener API (if not already created) ──
$orderResult = listenerCreateOrder($ref, $amount, $description);
$orderActive = false;
$slotBusy = false;
$retryAfter = 0;
$payAmount = $amount; // default, overridden by API's pay_amount (with unique centavos)

if ($orderResult) {
    if (!empty($orderResult['success'])) {
        $orderActive = true;
        $payAmount = $orderResult['pay_amount'] ?? $amount;
    } elseif (($orderResult['error'] ?? '') === 'slot_busy') {
        $slotBusy = true;
        $retryAfter = $orderResult['retry_after'] ?? 60;
    } elseif (strpos($orderResult['error'] ?? '', 'already exists') !== false) {
        // Order was created on a previous page load — show the payment page
        $orderActive = true;
        $payAmount = $orderResult['pay_amount'] ?? $amount;
        // Try to get pay_amount from the existing order
        $existing = listenerGetOrder($ref);
        if ($existing && !empty($existing['order']['pay_amount'])) {
            $payAmount = $existing['order']['pay_amount'];
        }
    }
}

$pageTitle = "Payment — $game_name";
$pageDescription = "Complete your payment for $game_name tournament registration.";
$flash = get_flash();

require_once __DIR__ . '/includes/header.php';
?>

<div class="ticket-container">
    <a href="<?= base_url() ?>" class="back-link">
        <i class="bi bi-arrow-left"></i> Back to games
    </a>

    <div class="ticket-card">
        <div class="ticket-header">
            <div class="ticket-ref" id="refCode"><?= htmlspecialchars($ref) ?></div>
            <div style="display:flex; gap:0.5rem; justify-content:center; margin-top:0.5rem; flex-wrap:wrap;">
                <button onclick="copyRef()" id="copyBtn" style="background:rgba(255,255,255,0.15); border:1px solid rgba(255,255,255,0.25); color:#fff; padding:0.3rem 0.75rem; border-radius:8px; font-size:0.75rem; cursor:pointer; font-weight:600;">
                    <i class="bi bi-clipboard"></i> Copy Code
                </button>
                <a href="sms:?body=My%20Apex Cybernet%20Tournament%20code:%20<?= urlencode($ref) ?>%20-%20https://apexcybernet.com/" style="background:rgba(255,255,255,0.15); border:1px solid rgba(255,255,255,0.25); color:#fff; padding:0.3rem 0.75rem; border-radius:8px; font-size:0.75rem; text-decoration:none; font-weight:600;">
                    <i class="bi bi-chat-dots"></i> SMS
                </a>
                <a href="https://www.facebook.com/argonarsoftware/?text=My%20tournament%20code:%20<?= urlencode($ref) ?>" target="_blank" style="background:rgba(255,255,255,0.15); border:1px solid rgba(255,255,255,0.25); color:#fff; padding:0.3rem 0.75rem; border-radius:8px; font-size:0.75rem; text-decoration:none; font-weight:600;">
                    <i class="bi bi-messenger"></i> Messenger
                </a>
            </div>
            <h2 style="margin-top:0.75rem;"><?= htmlspecialchars($game_name) ?></h2>
            <p class="subtitle">
                <?php if ($type === 'solo'): ?>
                    Solo Entry — <?= htmlspecialchars($registration['player_name']) ?>
                <?php else: ?>
                    Team — <?= htmlspecialchars($registration['team_name']) ?>
                <?php endif; ?>
            </p>
        </div>

        <?php if ($flash): ?>
            <div class="alert-custom alert-<?= $flash['type'] === 'error' ? 'danger' : 'success' ?>">
                <i class="bi bi-<?= $flash['type'] === 'error' ? 'exclamation-circle' : 'check-circle' ?>"></i>
                <?= htmlspecialchars($flash['message']) ?>
            </div>
        <?php endif; ?>

        <?php if ($slotBusy): ?>
            <div class="ticket-status ticket-waiting" id="slotBusy">
                <div class="ticket-status-icon"><i class="bi bi-hourglass-split"></i></div>
                <h3>Another player is paying this amount</h3>
                <p>Please wait a moment. The slot will open shortly.</p>
                <div class="ticket-countdown" id="slotTimer">
                    Retrying in <strong id="slotSeconds"><?= $retryAfter ?></strong>s...
                </div>
            </div>
        <?php elseif ($orderActive): ?>
            <!-- Amount banner -->
            <div style="background:linear-gradient(135deg, var(--accent), #6366f1); color:#fff; padding:1rem; text-align:center; border-radius:12px; margin-bottom:1rem;">
                <div style="font-size:0.75rem; opacity:0.85;">Amount to pay</div>
                <div style="font-size:2rem; font-weight:800; font-family:monospace; letter-spacing:1px;">&#8369; <?= number_format($payAmount, 2) ?></div>
            </div>

            <!-- Dynamic QR Code -->
            <div id="paymentWaiting" style="text-align:center; margin-bottom:1rem;">
                <div style="font-size:0.7rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px; margin-bottom:0.75rem;">Scan QR with any banking or e-wallet app</div>
                <img src="<?= base_url('payment/generate-qr.php?amount=' . number_format($payAmount, 2, '.', '')) ?>"
                     alt="Payment QR Code" style="width:220px; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,0.15); margin:0 auto;">
                <div style="font-size:0.7rem; color:var(--text-muted); margin-top:0.5rem;">QR Ph (InstaPay) - Apex Cybernet</div>
                <div style="font-size:0.75rem; color:var(--accent-light); margin-top:0.4rem;"><i class="bi bi-info-circle"></i> The exact amount will be filled automatically when you scan.</div>
                <div style="font-size:0.7rem; color:var(--text-muted); margin-top:0.3rem;">The small centavo difference is for payment verification. Send the exact amount shown.</div>
            </div>

            <!-- Steps -->
            <div style="background:rgba(245,158,11,0.08); border:1px solid rgba(245,158,11,0.25); border-radius:10px; padding:0.75rem 1rem; margin-bottom:1rem; font-size:0.85rem;">
                <div style="font-weight:700; color:#f59e0b; margin-bottom:0.4rem;"><i class="bi bi-list-ol"></i> Steps:</div>
                <ol style="margin:0; padding-left:1.2rem; color:var(--text-muted);">
                    <li>Open your <strong>banking app or e-wallet</strong> (GCash, Maya, BPI, etc.)</li>
                    <li>Scan the QR code above</li>
                    <li>Confirm the amount: <strong>&#8369; <?= number_format($payAmount, 2) ?></strong></li>
                    <li>Confirm and send the payment</li>
                    <li>This page will <strong style="color:var(--success);">auto-detect</strong> your payment</li>
                </ol>
            </div>

            <!-- Status -->
            <div id="statusBox" style="border:2px dashed var(--border); border-radius:12px; padding:1rem; text-align:center; margin-bottom:1rem;">
                <div id="statusWaiting">
                    <div class="ticket-spinner" style="margin:0 auto 0.5rem;"></div>
                    <div style="font-size:0.9rem; color:var(--text-muted);">Waiting for payment...</div>
                    <div style="font-size:0.75rem; color:var(--text-muted); margin-top:0.3rem;">Auto-detects once you send &#8369; <?= number_format($payAmount, 2) ?></div>
                </div>
                <div id="paymentSuccess" style="display:none;">
                    <i class="bi bi-check-circle-fill" style="font-size:2.5rem; color:var(--success);"></i>
                    <div style="font-weight:700; color:var(--success); margin-top:0.5rem;">Payment Received!</div>
                    <div id="paymentSender" style="font-size:0.85rem; color:var(--text-muted);"></div>
                    <div style="font-size:0.8rem; color:var(--text-muted); margin-top:0.3rem;">Redirecting...</div>
                </div>
            </div>

            <span id="orderTimer" style="display:none;">5:00</span>
        <?php else: ?>
            <div class="ticket-status ticket-waiting">
                <div class="ticket-status-icon"><i class="bi bi-exclamation-triangle"></i></div>
                <h3>Auto-detection unavailable</h3>
                <p>We couldn't reach the payment listener right now. Send your &#8369;<?= number_format($amount, 0) ?> to QR Ph (InstaPay) — Apex Cybernet, then upload the proof below. We'll review and confirm within minutes.</p>
            </div>
        <?php endif; ?>

        <!-- Bracket disclaimer -->
        <div style="background:rgba(251,191,36,0.07); border:1px solid rgba(251,191,36,0.2); border-radius:10px; padding:0.7rem 1rem; margin-bottom:1rem; font-size:0.78rem; color:var(--text-muted); display:flex; gap:0.6rem; align-items:flex-start;">
            <i class="bi bi-diagram-3-fill" style="color:#fbbf24; flex-shrink:0; margin-top:0.1rem;"></i>
            <span>
                <strong style="color:#fbbf24;">The bracket is live.</strong>
                Pay now to lock your name in before the seedings are finalized on April 17.
                <a href="<?= base_url('bracket.php?game=dota2') ?>" style="color:var(--accent-light); text-decoration:underline;">View bracket →</a>
            </span>
        </div>

        <!-- Skip payment for now -->
        <div style="border-top:1px solid var(--border); padding-top:1.25rem; margin-top:0.5rem; text-align:center;">
            <a href="<?= base_url("success.php?type=$type&game={$registration['game']}") ?>"
               style="display:inline-flex; align-items:center; gap:0.4rem; padding:0.6rem 1.5rem; border-radius:10px; background:rgba(100,116,139,0.07); border:1px solid rgba(100,116,139,0.2); text-decoration:none; color:#94a3b8; font-weight:600; font-size:0.82rem;">
                <i class="bi bi-clock"></i> I'll pay later
            </a>
            <div style="margin-top:0.6rem; font-size:0.72rem; color:var(--text-muted);">
                Your code: <strong style="color:var(--accent-light);"><?= htmlspecialchars($ref) ?></strong> — save it to pay anytime or show at the venue.
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var orderActive = <?= $orderActive ? 'true' : 'false' ?>;
    var slotBusy = <?= $slotBusy ? 'true' : 'false' ?>;
    var ref = <?= json_encode($ref) ?>;
    var type = <?= json_encode($type) ?>;
    var game = <?= json_encode($registration['game']) ?>;
    var baseUrl = <?= json_encode(base_url()) ?>;
    var checkUrl = <?= json_encode(base_url("ticket.php?ref=$ref&type=$type&action=check")) ?>;
    var successUrl = <?= json_encode(base_url("success.php?type=$type&game={$registration['game']}")) ?>;
    var pollInterval;

    // ── Copy ref code ──
    window.copyRef = function() {
        var code = document.getElementById('refCode').textContent.trim();
        navigator.clipboard.writeText(code).then(function() {
            var btn = document.getElementById('copyBtn');
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Copied!';
            setTimeout(function() { btn.innerHTML = '<i class="bi bi-clipboard"></i> Copy Code'; }, 2000);
        });
    };

    // ── Payment polling ──
    if (orderActive) {
        // Start 5-minute countdown
        var timeLeft = 300;
        var timerEl = document.getElementById('orderTimer');
        var countdownInterval = setInterval(function() {
            timeLeft--;
            if (timeLeft <= 0) {
                clearInterval(countdownInterval);
                clearInterval(pollInterval);
                if (timerEl) timerEl.textContent = '0:00';
                location.reload();
                return;
            }
            var m = Math.floor(timeLeft / 60);
            var s = timeLeft % 60;
            if (timerEl) timerEl.textContent = m + ':' + (s < 10 ? '0' : '') + s;
        }, 1000);

        // Poll every 3 seconds
        pollInterval = setInterval(function() {
            fetch(checkUrl)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.paid) {
                        clearInterval(pollInterval);
                        clearInterval(countdownInterval);
                        showSuccess(data.sender, data.phone);
                    }
                })
                .catch(function() {});
        }, 3000);
    }

    // ── Slot busy auto-retry ──
    if (slotBusy) {
        var slotLeft = <?= $retryAfter ?>;
        var slotEl = document.getElementById('slotSeconds');
        var slotInterval = setInterval(function() {
            slotLeft--;
            if (slotLeft <= 0) {
                clearInterval(slotInterval);
                location.reload();
                return;
            }
            if (slotEl) slotEl.textContent = slotLeft;
        }, 1000);
    }

    function showSuccess(sender, phone) {
        var statusWaiting = document.getElementById('statusWaiting');
        var success = document.getElementById('paymentSuccess');
        var senderEl = document.getElementById('paymentSender');
        var statusBox = document.getElementById('statusBox');

        if (statusWaiting) statusWaiting.style.display = 'none';
        if (success) success.style.display = 'block';
        if (statusBox) {
            statusBox.style.borderColor = 'var(--success)';
            statusBox.style.background = 'rgba(34,197,94,0.05)';
        }
        if (senderEl && sender) {
            senderEl.textContent = 'From ' + sender + (phone ? ' ' + phone : '');
        }

        setTimeout(function() {
            window.location.href = successUrl;
        }, 2500);
    }

})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
