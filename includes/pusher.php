<?php
/**
 * Notification dispatcher — no external SDK, pure PHP.
 * Include this file then call notify_user() to queue a bell notification.
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
