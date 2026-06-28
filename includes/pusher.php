<?php
/**
 * Soketi WebSocket event dispatcher — no external SDK, pure PHP HTTP.
 * Include this file then call hc_push() after every HC credit commit.
 */

define('PUSHER_APP_ID', '1');
define('PUSHER_KEY',    'apexcybernet_ws_key');
define('PUSHER_SECRET', 'apexcybernet_ws_secret_2026');
define('PUSHER_HOST',   '127.0.0.1');
define('PUSHER_PORT',   6001);

/**
 * Insert a notification row. Clients discover new rows via the central JS
 * poller in includes/footer.php and mobile/layout.php (10s interval on
 * api/notifications.php). No WebSocket needed — Soketi was never deployed.
 */
function notify_user(PDO $pdo, int $uid, string $title, string $message, string $icon = 'bi-bell', ?string $link = null): void {
    try {
        $stmt = $pdo->prepare("INSERT INTO user_notifications (account_id, title, message, icon, link)
                                VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$uid, $title, $message, $icon, $link]);
    } catch (Exception $e) { /* table may not exist in all envs — skip */ }
}

/**
 * Credit-side helper — writes a bell-ready "+N HC received" notification row.
 * Clients pick it up via the poller and surface an HC-received toast (desktop)
 * or slide-up receipt overlay (mobile).
 */
function hc_push(PDO $pdo, int $uid, int $amount, string $from, int $new_balance, string $reason = 'credit'): void {
    notify_user(
        $pdo, $uid,
        '+' . number_format($amount) . ' HC received',
        'From ' . $from,
        'bi-coin',
        '/mobile/'
    );
}
