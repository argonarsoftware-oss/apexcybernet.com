<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/listener-api.php';

$user = current_user($pdo);

const BUYBACK_RATE = 1.00;
const MIN_SELL     = 300;
const RAKE         = 0.10;

$PACKAGES = [
    ['coins' => 50,  'price' => 50.00],
    ['coins' => 100, 'price' => 100.00],
    ['coins' => 250, 'price' => 250.00],
    ['coins' => 500, 'price' => 500.00],
];

$h_coins      = 0;
$gcash_number = '';
$tab          = $_GET['tab'] ?? 'buy';
$sell_errors  = [];

// Redirect all POST actions to login if not authenticated
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$user) {
    header('Location: ' . base_url('login.php'));
    exit;
}
if (isset($_GET['action']) && !$user) {
    header('Content-Type: application/json');
    echo json_encode(['paid' => false]);
    exit;
}

if ($user) {
    // Fresh balance
    $stmt = $pdo->prepare("SELECT h_coins, gcash_number FROM accounts WHERE id = ?");
    $stmt->execute([$user['id']]);
    $fresh        = $stmt->fetch();
    $h_coins      = (int)($fresh['h_coins'] ?? 0);
    $gcash_number = $fresh['gcash_number'] ?? '';
}

// ── AJAX: check coin purchase payment ──
if (isset($_GET['action']) && $_GET['action'] === 'check') {
    header('Content-Type: application/json');
    $ref = $_SESSION['hc_order_ref'] ?? '';
    if (!$ref) { echo json_encode(['paid' => false]); exit; }

    $result = listenerCheckPayment($ref);
    if (!$result || empty($result['paid'])) { echo json_encode(['paid' => false]); exit; }

    $sender   = trim((string)($result['sender'] ?? ''));
    $phone    = trim((string)($result['phone'] ?? ''));
    $received = $result['pay_amount'] ?? $result['amount'] ?? null;
    $has_sender = ($sender !== '' || $phone !== '');
    $has_amount = ($received !== null && (float)$received > 0);

    $existing     = listenerGetOrder($ref);
    $order_paid   = ($existing['order']['status'] ?? '') === 'paid' || !empty($existing['order']['paid_at']);
    $expected     = $existing['order']['pay_amount'] ?? $existing['order']['amount'] ?? null;
    $amount_ok    = $expected !== null && $has_amount && abs((float)$received - (float)$expected) < 0.5;

    if (!$has_sender || !$has_amount || !$order_paid || !$amount_ok) {
        echo json_encode(['paid' => false]); exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM h_coin_orders WHERE ref_code = ? AND account_id = ? AND status = 'pending'");
    $stmt->execute([$ref, $user['id']]);
    $order = $stmt->fetch();

    if ($order) {
        $pdo->prepare("UPDATE h_coin_orders SET status = 'paid' WHERE ref_code = ?")->execute([$ref]);
        $pdo->prepare("UPDATE accounts SET h_coins = h_coins + ? WHERE id = ?")->execute([$order['coins'], $order['account_id']]);
        $pdo->prepare("INSERT INTO h_coin_transactions (account_id, type, amount, reason, ref) VALUES (?, 'credit', ?, 'purchase', ?)")->execute([$order['account_id'], $order['coins'], $ref]);
        unset($_SESSION['hc_order_ref']);
    }

    echo json_encode(['paid' => true, 'coins' => $order['coins'] ?? 0]);
    exit;
}

// ── Save GCash number ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_gcash'])) {
    $gcash = preg_replace('/[^0-9+]/', '', trim($_POST['gcash_number'] ?? ''));
    $pdo->prepare("UPDATE accounts SET gcash_number = ? WHERE id = ?")->execute([$gcash, $user['id']]);
    header('Location: ' . base_url('coins.php?tab=sell&saved=1'));
    exit;
}

// ── List coins for sale (platform buyback) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sell_coins'])) {
    $tab    = 'sell';
    $amount = (int)($_POST['sell_amount'] ?? 0);

    if ($amount < MIN_SELL)   $sell_errors[] = 'Minimum listing is ' . MIN_SELL . ' H-Coins.';
    if ($amount > $h_coins)   $sell_errors[] = "You only have $h_coins H-Coins.";
    if (empty($gcash_number)) $sell_errors[] = 'Set your GCash number below before listing.';

    // Block withdrawal of welcome bonus HC
    $bonus_q = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM h_coin_transactions WHERE account_id = ? AND type='credit' AND reason='welcome_bonus'");
    $bonus_q->execute([$user['id']]);
    $total_bonus = (int)$bonus_q->fetchColumn();
    $spent_q = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM h_coin_transactions WHERE account_id = ? AND type='debit'");
    $spent_q->execute([$user['id']]);
    $total_spent    = (int)$spent_q->fetchColumn();
    $unspent_bonus  = max(0, $total_bonus - $total_spent);
    $withdrawable   = max(0, $h_coins - $unspent_bonus);
    if ($amount > $withdrawable) {
        $sell_errors[] = 'Welcome bonus HC cannot be cashed out. Only earned HC (from purchases or prediction wins) can be withdrawn. You can withdraw up to ' . $withdrawable . ' HC.';
    }

    $pending_stmt = $pdo->prepare("SELECT 1 FROM h_coin_sell_orders WHERE account_id = ? AND status = 'pending'");
    $pending_stmt->execute([$user['id']]);
    if ($pending_stmt->fetch()) $sell_errors[] = 'You already have a pending listing. Wait for it to complete.';

    if (empty($sell_errors)) {
        $peso = round($amount * BUYBACK_RATE, 2);
        $upd  = $pdo->prepare("UPDATE accounts SET h_coins = h_coins - ? WHERE id = ? AND h_coins >= ?");
        $upd->execute([$amount, $user['id'], $amount]);
        if ($upd->rowCount() > 0) {
            $pdo->prepare("INSERT INTO h_coin_sell_orders (account_id, coins, peso_amount, gcash_number) VALUES (?, ?, ?, ?)")->execute([$user['id'], $amount, $peso, $gcash_number]);
            $pdo->prepare("INSERT INTO h_coin_transactions (account_id, type, amount, reason) VALUES (?, 'debit', ?, 'sell_listing')")->execute([$user['id'], $amount]);
            header('Location: ' . base_url('coins.php?tab=sell&listed=1'));
            exit;
        }
        $sell_errors[] = 'Transaction failed. Try again.';
    }
}

// ── Cancel pending buy order ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order']) && $user) {
    $ref = $_SESSION['hc_order_ref'] ?? '';
    if ($ref) {
        $pdo->prepare("UPDATE h_coin_orders SET status = 'cancelled' WHERE ref_code = ? AND account_id = ? AND status = 'pending'")->execute([$ref, $user['id']]);
        unset($_SESSION['hc_order_ref']);
    }
    header('Location: ' . base_url('coins.php?tab=buy'));
    exit;
}

// ── Buy coins: create order ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy_package'])) {
    $pkg = $PACKAGES[(int)$_POST['buy_package']] ?? null;
    if ($pkg) {
        $rand = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
        $ref  = 'HC-' . $user['id'] . '-' . $rand;

        // Cancel any prior pending order
        $pdo->prepare("UPDATE h_coin_orders SET status = 'cancelled' WHERE account_id = ? AND status = 'pending'")->execute([$user['id']]);
        unset($_SESSION['hc_order_ref']);

        $pdo->prepare("INSERT INTO h_coin_orders (account_id, ref_code, coins, peso_amount) VALUES (?, ?, ?, ?)")->execute([$user['id'], $ref, $pkg['coins'], $pkg['price']]);
        $_SESSION['hc_order_ref'] = $ref;
        header('Location: ' . base_url('coins.php?tab=buy'));
        exit;
    }
}

// ── Resolve active buy order ──
$activeOrder  = false;
$payAmount    = 0;
$orderCoins   = 0;
$slotBusy     = false;
$retryAfter   = 0;
$activeSell   = null;
$transactions = [];

if ($user) {
    $ref = $_SESSION['hc_order_ref'] ?? '';
    if ($ref) {
        $stmt = $pdo->prepare("SELECT * FROM h_coin_orders WHERE ref_code = ? AND account_id = ? AND status = 'pending'");
        $stmt->execute([$ref, $user['id']]);
        $dbOrder = $stmt->fetch();

        if ($dbOrder) {
            $orderCoins = $dbOrder['coins'];
            $res = listenerCreateOrder($ref, (float)$dbOrder['peso_amount'], "Apex Cybernet H-Coins: {$dbOrder['coins']} coins");
            if ($res) {
                if (!empty($res['success'])) {
                    $activeOrder = true;
                    $payAmount   = $res['pay_amount'] ?? $dbOrder['peso_amount'];
                } elseif (($res['error'] ?? '') === 'slot_busy') {
                    $slotBusy   = true;
                    $retryAfter = $res['retry_after'] ?? 60;
                } elseif (strpos($res['error'] ?? '', 'already exists') !== false) {
                    $activeOrder = true;
                    $ex = listenerGetOrder($ref);
                    $payAmount = $ex['order']['pay_amount'] ?? $dbOrder['peso_amount'];
                }
            }
        } else {
            unset($_SESSION['hc_order_ref']);
        }
    }

    // Active sell order
    $stmt = $pdo->prepare("SELECT * FROM h_coin_sell_orders WHERE account_id = ? AND status = 'pending' ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$user['id']]);
    $activeSell = $stmt->fetch() ?: null;

    // Recent transactions
    $stmt = $pdo->prepare("SELECT * FROM h_coin_transactions WHERE account_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$user['id']]);
    $transactions = $stmt->fetchAll();
}

$checkUrl   = base_url("coins.php?action=check");
$successUrl = base_url("coins.php?tab=buy&paid=1");
$pageTitle  = 'H-Coins — Apex Cybernet';
$pageDescription = 'Buy or sell H-Coins on the Apex Cybernet platform.';
require_once __DIR__ . '/includes/header.php';
?>

<div class="reg-container" style="max-width:560px;">
    <a href="<?= base_url() ?>" class="back-link"><i class="bi bi-arrow-left"></i> Back to home</a>

    <div class="reg-card">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1.25rem; flex-wrap:wrap; gap:0.5rem;">
            <h2 style="margin:0; display:flex; align-items:center; gap:0.4rem;"><img src="<?= base_url('images/hcoin-icon.png') ?>" alt="H-Coin" style="width:28px; height:28px; object-fit:contain;"> H-Coins</h2>
            <?php if ($user): ?>
            <div style="background:rgba(251,191,36,0.12); border:1px solid rgba(251,191,36,0.3); border-radius:10px; padding:0.4rem 1rem; font-weight:800; font-size:1.1rem; color:#fbbf24;">
                <?= number_format($h_coins) ?> <span style="font-size:0.75rem; font-weight:400; color:var(--text-muted);">coins</span>
            </div>
            <?php else: ?>
            <a href="<?= base_url('login.php') ?>" style="background:var(--accent); color:#fff; border-radius:8px; padding:0.4rem 1rem; font-size:0.85rem; font-weight:700; text-decoration:none;">
                <i class="bi bi-box-arrow-in-right"></i> Log in
            </a>
            <?php endif; ?>
        </div>

        <?php if (isset($_GET['paid'])): ?>
            <div class="alert-custom alert-success" style="margin-bottom:1rem;"><i class="bi bi-check-circle-fill"></i> Coins added to your balance!</div>
        <?php endif; ?>
        <?php if (isset($_GET['listed'])): ?>
            <div class="alert-custom alert-success" style="margin-bottom:1rem;"><i class="bi bi-check-circle-fill"></i> Your coins are listed. We'll send ₱<?= number_format($activeSell['peso_amount'] ?? 0, 2) ?> to your GCash once processed.</div>
        <?php endif; ?>

        <!-- What can I do with H-Coins? -->
        <div style="margin-bottom:1.25rem;">
            <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:var(--text-muted);margin-bottom:0.6rem;">What can I do with H-Coins?</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;">
                <div style="display:flex;align-items:center;gap:0.55rem;background:rgba(124,58,237,0.07);border:1px solid rgba(124,58,237,0.18);border-radius:9px;padding:0.55rem 0.7rem;font-size:0.75rem;color:var(--text-muted);">
                    <i class="bi bi-trophy-fill" style="color:#fbbf24;font-size:1rem;flex-shrink:0;"></i>
                    <span><strong style="color:var(--text-main);">Enter Tournaments</strong> — pay the ₱500 entry fee using H-Coins</span>
                </div>
                <div style="display:flex;align-items:center;gap:0.55rem;background:rgba(124,58,237,0.07);border:1px solid rgba(124,58,237,0.18);border-radius:9px;padding:0.55rem 0.7rem;font-size:0.75rem;color:var(--text-muted);">
                    <i class="bi bi-shop" style="color:#34d399;font-size:1rem;flex-shrink:0;"></i>
                    <span><strong style="color:var(--text-main);">Marketplace</strong> — buy and sell items with other players</span>
                </div>
                <div style="display:flex;align-items:center;gap:0.55rem;background:rgba(124,58,237,0.07);border:1px solid rgba(124,58,237,0.18);border-radius:9px;padding:0.55rem 0.7rem;font-size:0.75rem;color:var(--text-muted);">
                    <i class="bi bi-patch-check-fill" style="color:#60a5fa;font-size:1rem;flex-shrink:0;"></i>
                    <span><strong style="color:var(--text-main);">Profile Badges</strong> — unlock exclusive titles and cosmetics</span>
                </div>
                <div style="display:flex;align-items:center;gap:0.55rem;background:rgba(124,58,237,0.07);border:1px solid rgba(124,58,237,0.18);border-radius:9px;padding:0.55rem 0.7rem;font-size:0.75rem;color:var(--text-muted);">
                    <i class="bi bi-arrow-left-right" style="color:#a78bfa;font-size:1rem;flex-shrink:0;"></i>
                    <span><strong style="color:var(--text-main);">Send & Receive</strong> — transfer H-Coins to other Apex Cybernet users</span>
                </div>
            </div>
            <div style="margin-top:0.5rem;display:flex;align-items:center;gap:0.55rem;background:rgba(251,191,36,0.06);border:1px solid rgba(251,191,36,0.2);border-radius:9px;padding:0.55rem 0.7rem;font-size:0.75rem;color:var(--text-muted);">
                <i class="bi bi-bank" style="color:#fbbf24;font-size:1rem;flex-shrink:0;"></i>
                <span><strong style="color:var(--text-main);">Cash Out</strong> — sell H-Coins back at ₱1.00/coin via GCash (min <?= MIN_SELL ?> coins)</span>
            </div>
        </div>

        <!-- Tabs -->
        <div style="display:flex; border-bottom:2px solid var(--border); margin-bottom:1.5rem;">
            <a href="<?= base_url('coins.php?tab=buy') ?>" style="flex:1; text-align:center; padding:0.65rem; font-weight:700; font-size:0.9rem; text-decoration:none; border-bottom:2px solid <?= $tab === 'buy' ? 'var(--accent)' : 'transparent' ?>; margin-bottom:-2px; color:<?= $tab === 'buy' ? 'var(--accent-light)' : 'var(--text-muted)' ?>;">
                <i class="bi bi-plus-circle"></i> Buy Coins
            </a>
            <a href="<?= base_url('coins.php?tab=sell') ?>" style="flex:1; text-align:center; padding:0.65rem; font-weight:700; font-size:0.9rem; text-decoration:none; border-bottom:2px solid <?= $tab === 'sell' ? 'var(--accent)' : 'transparent' ?>; margin-bottom:-2px; color:<?= $tab === 'sell' ? 'var(--accent-light)' : 'var(--text-muted)' ?>;">
                <i class="bi bi-arrow-left-right"></i> Sell Coins
            </a>
        </div>

        <?php if (!$user): ?>
            <div style="background:rgba(139,92,246,0.08); border:1.5px solid rgba(139,92,246,0.25); border-radius:12px; padding:1.5rem; text-align:center; margin-bottom:1rem;">
                <img src="<?= base_url('images/hcoin-chest.jpg') ?>" alt="H-Coins" style="width:180px; border-radius:12px; margin-bottom:0.75rem; box-shadow:0 4px 20px rgba(0,0,0,0.3);">
                <div style="font-weight:700; font-size:1rem; color:var(--text-main); margin-bottom:0.4rem;">Log in to buy or sell H-Coins</div>
                <div style="font-size:0.85rem; color:var(--text-muted); margin-bottom:1rem;">H-Coins let you enter match predictions. ₱1 = 1 coin. Platform buys back at ₱1.00/coin.</div>
                <a href="<?= base_url('login.php') ?>" class="btn-submit" style="display:inline-flex; align-items:center; gap:0.4rem; padding:0.6rem 1.5rem; text-decoration:none; font-size:0.9rem;">
                    <i class="bi bi-box-arrow-in-right"></i> Log In
                </a>
            </div>
        <?php elseif ($tab === 'buy'): ?>
            <?php if ($activeOrder): ?>
                <!-- Payment checkout -->
                <div style="background:linear-gradient(135deg,var(--accent),#6366f1); color:#fff; padding:1rem; text-align:center; border-radius:12px; margin-bottom:1rem;">
                    <div style="font-size:0.75rem; opacity:0.85;">Amount to pay</div>
                    <div style="font-size:2rem; font-weight:800; font-family:monospace;">&#8369; <?= number_format($payAmount, 2) ?></div>
                    <div style="font-size:0.8rem; opacity:0.85; margin-top:0.25rem;">You receive <strong><?= number_format($orderCoins) ?> H-Coins</strong></div>
                </div>
                <div style="text-align:center; margin-bottom:1rem;">
                    <div style="font-size:0.7rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px; margin-bottom:0.75rem;">Scan QR with any banking or e-wallet app</div>
                    <img src="<?= base_url('payment/generate-qr.php?amount=' . number_format($payAmount, 2, '.', '')) ?>" alt="QR Code" style="width:200px; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,0.2); margin:0 auto;">
                    <div style="font-size:0.7rem; color:var(--text-muted); margin-top:0.5rem;">QR Ph (InstaPay) — Apex Cybernet</div>
                </div>
                <div id="statusBox" style="border:2px dashed var(--border); border-radius:12px; padding:1rem; text-align:center; margin-bottom:1rem;">
                    <div id="statusWaiting">
                        <div class="ticket-spinner" style="margin:0 auto 0.5rem;"></div>
                        <div style="font-size:0.9rem; color:var(--text-muted);">Waiting for payment...</div>
                    </div>
                    <div id="paymentSuccess" style="display:none;">
                        <i class="bi bi-check-circle-fill" style="font-size:2.5rem; color:var(--success);"></i>
                        <div style="font-weight:700; color:var(--success); margin-top:0.5rem;">Payment Received!</div>
                        <div style="font-size:0.85rem; color:var(--text-muted);">Adding coins to your balance...</div>
                    </div>
                </div>
                <div style="text-align:center;">
                    <form method="POST" style="display:inline;">
                        <button type="submit" name="cancel_order" value="1" onclick="return confirm('Cancel this order?')"
                                style="background:rgba(239,68,68,0.1); border:1.5px solid rgba(239,68,68,0.35); color:#f87171; border-radius:8px; padding:0.5rem 1.25rem; font-size:0.8rem; font-weight:700; cursor:pointer; font-family:inherit; transition:all 0.15s;">
                            <i class="bi bi-x-circle"></i> Cancel Order
                        </button>
                    </form>
                </div>

            <?php elseif ($slotBusy): ?>
                <div style="text-align:center; padding:1.5rem;">
                    <i class="bi bi-hourglass-split" style="font-size:2rem; color:#fbbf24;"></i>
                    <div style="margin-top:0.5rem; font-weight:700;">Another payment is in progress</div>
                    <div style="font-size:0.8rem; color:var(--text-muted); margin-top:0.25rem;">Retrying in <strong id="slotSeconds"><?= $retryAfter ?></strong>s...</div>
                </div>

            <?php else: ?>
                <!-- Package selection -->
                <div style="text-align:center; margin-bottom:1rem;">
                    <img src="<?= base_url('images/hcoin-chest.jpg') ?>" alt="H-Coins" style="width:160px; border-radius:12px; margin-bottom:0.5rem; box-shadow:0 4px 16px rgba(0,0,0,0.3);">
                    <div style="font-size:0.8rem; color:var(--text-muted);">
                        1 H-Coin = ₱1.00 &nbsp;·&nbsp; Paid via GCash / InstaPay QR
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.65rem; margin-bottom:1.5rem;">
                    <?php foreach ($PACKAGES as $i => $pkg): ?>
                        <form method="POST">
                            <button type="submit" name="buy_package" value="<?= $i ?>" style="width:100%; background:rgba(124,58,237,0.08); border:1px solid rgba(124,58,237,0.25); border-radius:12px; padding:1rem 0.75rem; cursor:pointer; text-align:center; transition:all 0.2s; color:var(--text);">
                                <div style="font-size:1.4rem; font-weight:800; color:var(--accent-light);"><?= number_format($pkg['coins']) ?></div>
                                <div style="font-size:0.7rem; color:var(--text-muted); margin-bottom:0.5rem;">H-Coins</div>
                                <div style="background:var(--accent); color:#fff; border-radius:6px; padding:0.3rem 0.5rem; font-size:0.8rem; font-weight:700;">&#8369;<?= number_format($pkg['price'], 2) ?></div>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- Sell tab -->
            <?php if ($activeSell): ?>
                <div style="background:rgba(251,191,36,0.08); border:1px solid rgba(251,191,36,0.3); border-radius:12px; padding:1rem 1.25rem; margin-bottom:1.25rem;">
                    <div style="font-weight:700; color:#fbbf24; margin-bottom:0.5rem;"><i class="bi bi-clock-history"></i> Listing in progress</div>
                    <div style="display:flex; justify-content:space-between; font-size:0.85rem; margin-bottom:0.3rem;">
                        <span style="color:var(--text-muted);">Coins listed</span>
                        <span style="font-weight:700; color:var(--accent-light);"><?= number_format($activeSell['coins']) ?> H-Coins</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:0.85rem; margin-bottom:0.3rem;">
                        <span style="color:var(--text-muted);">You receive</span>
                        <span style="font-weight:700; color:var(--success);">&#8369;<?= number_format($activeSell['peso_amount'], 2) ?></span>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:0.85rem;">
                        <span style="color:var(--text-muted);">GCash</span>
                        <span style="color:var(--text);"><?= htmlspecialchars($activeSell['gcash_number']) ?></span>
                    </div>
                    <div style="font-size:0.72rem; color:var(--text-muted); margin-top:0.75rem; text-align:center;">
                        <i class="bi bi-info-circle"></i> Apex Cybernet will purchase your coins and send the GCash transfer within 24 hours.
                    </div>
                </div>
            <?php else: ?>
                <!-- Sell form -->
                <div style="background:rgba(34,197,94,0.06); border:1px solid rgba(34,197,94,0.2); border-radius:10px; padding:0.75rem 1rem; margin-bottom:1rem; font-size:0.8rem;">
                    <div style="font-weight:700; color:var(--success); margin-bottom:0.3rem;"><i class="bi bi-info-circle-fill"></i> How selling works</div>
                    <div style="color:var(--text-muted); line-height:1.6;">
                        Apex Cybernet instantly buys your H-Coins at <strong style="color:#fbbf24;">₱<?= BUYBACK_RATE ?>/coin</strong>. List your coins, and we'll send the peso amount to your GCash within 24 hours. Minimum: <?= MIN_SELL ?> H-Coins.
                    </div>
                </div>

                <?php if (!empty($sell_errors)): ?>
                    <div class="alert-custom alert-danger" style="margin-bottom:1rem;">
                        <?php foreach ($sell_errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" style="margin-bottom:1.25rem;">
                    <div class="mb-3">
                        <label class="form-label">Coins to list <span style="color:var(--text-muted); font-weight:400;">(min <?= MIN_SELL ?>)</span></label>
                        <input type="number" name="sell_amount" class="form-control" min="<?= MIN_SELL ?>" max="<?= $h_coins ?>"
                               placeholder="e.g. 200" value="<?= htmlspecialchars($_POST['sell_amount'] ?? '') ?>" oninput="updatePeso(this.value)">
                        <div style="font-size:0.8rem; color:var(--success); margin-top:0.4rem;">
                            You receive: <strong id="pesoOut">₱<?= number_format(0, 2) ?></strong>
                        </div>
                    </div>
                    <button type="submit" name="sell_coins" value="1" class="btn-submit" <?= $h_coins < MIN_SELL ? 'disabled' : '' ?>>
                        <i class="bi bi-arrow-left-right"></i> Exchange
                    </button>
                    <?php if ($h_coins < MIN_SELL): ?>
                        <div style="font-size:0.75rem; color:var(--text-muted); text-align:center; margin-top:0.5rem;">
                            You need at least <?= MIN_SELL ?> H-Coins to list.
                        </div>
                    <?php endif; ?>
                </form>
            <?php endif; ?>

            <!-- GCash number setting -->
            <div style="border-top:1px solid var(--border); padding-top:1.25rem;">
                <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:0.5rem; text-transform:uppercase; letter-spacing:1px;">Your GCash Number</div>
                <?php if (isset($_GET['saved'])): ?>
                    <div style="font-size:0.8rem; color:var(--success); margin-bottom:0.5rem;"><i class="bi bi-check-circle-fill"></i> Saved.</div>
                <?php endif; ?>
                <form method="POST" style="display:flex; gap:0.5rem;">
                    <input type="tel" name="gcash_number" class="form-control" placeholder="09XX XXX XXXX"
                           value="<?= htmlspecialchars($gcash_number) ?>" style="flex:1; font-size:0.85rem;">
                    <button type="submit" name="save_gcash" value="1" class="btn-submit" style="width:auto; padding:0.5rem 1rem; font-size:0.8rem; margin:0;">
                        <i class="bi bi-check-lg"></i> Save
                    </button>
                </form>
                <div style="font-size:0.72rem; color:var(--text-muted); margin-top:0.4rem;">Required to receive payment when your coins are purchased.</div>
            </div>
        <?php endif; ?>

        <!-- Transaction history -->
        <?php if (!empty($transactions)): ?>
            <div style="border-top:1px solid var(--border); padding-top:1.25rem; margin-top:1.25rem;">
                <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:0.75rem; text-transform:uppercase; letter-spacing:1px;">Recent Transactions</div>
                <?php foreach ($transactions as $tx): ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; padding:0.4rem 0; border-bottom:1px solid rgba(255,255,255,0.04); font-size:0.82rem;">
                        <div>
                            <span style="color:var(--text);"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $tx['reason']))) ?></span>
                            <div style="font-size:0.68rem; color:var(--text-muted);"><?= date('M j, g:ia', strtotime($tx['created_at'])) ?></div>
                        </div>
                        <span style="font-weight:700; color:<?= $tx['type'] === 'credit' ? 'var(--success)' : '#f87171' ?>;">
                            <?= $tx['type'] === 'credit' ? '+' : '-' ?><?= number_format($tx['amount']) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; // end buy/sell tabs / guest check ?>
    </div>
</div>

<script>
function updatePeso(val) {
    var n = parseInt(val) || 0;
    document.getElementById('pesoOut').textContent = '₱' + (n * <?= BUYBACK_RATE ?>).toFixed(2);
}

<?php if ($activeOrder): ?>
(function() {
    var checkUrl  = <?= json_encode($checkUrl) ?>;
    var successUrl = <?= json_encode($successUrl) ?>;
    var poll = setInterval(function() {
        fetch(checkUrl).then(function(r){return r.json();}).then(function(d){
            if (d.paid) {
                clearInterval(poll);
                document.getElementById('statusWaiting').style.display = 'none';
                document.getElementById('paymentSuccess').style.display = 'block';
                var box = document.getElementById('statusBox');
                box.style.borderColor = 'var(--success)';
                box.style.background = 'rgba(34,197,94,0.05)';
                setTimeout(function(){ window.location.href = successUrl; }, 2000);
            }
        }).catch(function(){});
    }, 3000);
    // Reload after 5 min
    setTimeout(function(){ location.reload(); }, 300000);
})();
<?php endif; ?>

<?php if ($slotBusy): ?>
(function() {
    var n = <?= $retryAfter ?>;
    var el = document.getElementById('slotSeconds');
    var t = setInterval(function(){
        n--; if (el) el.textContent = n;
        if (n <= 0) { clearInterval(t); location.reload(); }
    }, 1000);
})();
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
