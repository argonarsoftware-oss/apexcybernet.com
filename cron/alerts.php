<?php
/**
 * Apex Cybernet Alert Engine
 * Run every 15 minutes via server cron:
 *   *\/15 * * * * curl -s "https://apexcybernet.com/cron/alerts.php?token=apexcybernet-admin-2026-token" >/dev/null 2>&1
 * Or trigger manually: https://apexcybernet.com/cron/alerts.php?token=apexcybernet-admin-2026-token
 */
if (PHP_SAPI !== 'cli' && ($_GET['token'] ?? '') !== 'apexcybernet-admin-2026-token') {
    http_response_code(403); exit('Forbidden');
}

require_once dirname(__DIR__) . '/includes/db.php';

// ── Load Brevo API key from loan-management .env ──
$brevo_key = null;
$loan_env = dirname(__DIR__, 2) . '/loan-management/.env';
if (file_exists($loan_env)) {
    foreach (file($loan_env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
        if (strpos(trim($ln), 'BREVO_API_KEY=') === 0) {
            $brevo_key = trim(substr($ln, 14));
            break;
        }
    }
}

function send_alert_email(string $to, string $subject, string $body, ?string $brevo_key): bool {
    if ($brevo_key) {
        $payload = json_encode([
            'sender'      => ['email' => 'argonarsoftware@gmail.com', 'name' => 'Apex Cybernet Alerts'],
            'to'          => [['email' => $to]],
            'subject'     => $subject,
            'textContent' => $body,
        ]);
        $ctx = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\napi-key: $brevo_key\r\n",
            'content'       => $payload,
            'timeout'       => 10,
            'ignore_errors' => true,
        ]]);
        $r = @file_get_contents('https://api.brevo.com/v3/smtp/email', false, $ctx);
        return $r !== false && strpos($r, '"messageId"') !== false;
    }
    return mail($to, $subject, $body, "From: alerts@apexcybernet.com\r\nContent-Type: text/plain; charset=UTF-8");
}

$now   = date('Y-m-d H:i:s');
$fired = 0;
$checked = 0;

try {
    $rules = $pdo->query("SELECT * FROM alert_rules WHERE active = 1 ORDER BY id")->fetchAll();
} catch (Exception $e) {
    error_log('[alerts] Cannot load rules: ' . $e->getMessage());
    echo "ERROR: Cannot load rules.\n";
    exit(1);
}

foreach ($rules as $rule) {
    $checked++;
    $site     = $rule['site'];
    $type     = $rule['alert_type'];
    $threshold = (float)$rule['threshold_pct'];
    $window   = (int)$rule['window_minutes'];
    $cooldown = (int)$rule['cooldown_minutes'];
    $email    = $rule['notify_email'];

    // Check cooldown
    if ($rule['last_fired']) {
        $mins_since = (time() - strtotime($rule['last_fired'])) / 60;
        if ($mins_since < $cooldown) {
            echo "SKIP [{$type}][{$site}]: cooldown {$cooldown}min, last fired " . round($mins_since) . "min ago\n";
            continue;
        }
    }

    // Build site WHERE condition
    if ($site === 'all') {
        $site_cond = '';
    } elseif ($site === 'apexcybernet') {
        $site_cond = "AND (site='apexcybernet' OR site IS NULL OR site='')";
    } else {
        $site_cond = "AND site='" . addslashes($site) . "'";
    }

    $fired_alert = false;
    $current_val = 0.0;
    $baseline_val = 0.0;
    $message = '';

    try {
        if ($type === 'traffic_drop') {
            // Current pageviews in the last $window minutes
            $current_val = (float)$pdo->query("SELECT COUNT(*) FROM activity_logs
                WHERE event_type='pageview'
                  AND created_at >= DATE_SUB(NOW(), INTERVAL {$window} MINUTE)
                  $site_cond")->fetchColumn();

            // Baseline: average $window-minute bucket over past 7 days
            $total_7d = (float)$pdo->query("SELECT COUNT(*) FROM activity_logs
                WHERE event_type='pageview'
                  AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                  AND created_at < DATE_SUB(NOW(), INTERVAL {$window} MINUTE)
                  $site_cond")->fetchColumn();

            $buckets = 7 * 24 * (60 / max(1, $window));
            $baseline_val = round($total_7d / $buckets, 1);

            if ($baseline_val > 3 && $current_val < $baseline_val * (1 - $threshold / 100)) {
                $drop_pct = round((1 - ($current_val / max(0.1, $baseline_val))) * 100);
                $message  = "Traffic drop [{$site}]: {$current_val} pageviews in last {$window}min"
                           . " (baseline avg: {$baseline_val}). Drop: {$drop_pct}% > threshold {$threshold}%";
                $fired_alert = true;
            }

        } elseif ($type === 'error_spike') {
            $current_val = (float)$pdo->query("SELECT COUNT(*) FROM activity_logs
                WHERE event_type='error'
                  AND created_at >= DATE_SUB(NOW(), INTERVAL {$window} MINUTE)
                  $site_cond")->fetchColumn();

            if ($current_val >= $threshold) {
                $message = "JS Error spike [{$site}]: {$current_val} errors in last {$window}min (threshold: {$threshold})";
                $fired_alert = true;
            }

        } elseif ($type === 'no_traffic') {
            $last_pv = $pdo->query("SELECT MAX(created_at) FROM activity_logs
                WHERE event_type='pageview' $site_cond")->fetchColumn();

            if ($last_pv) {
                $mins_since = (time() - strtotime($last_pv)) / 60;
                $current_val  = round($mins_since, 1);
                $baseline_val = (float)$window;
                if ($mins_since > $window) {
                    $message = "No traffic [{$site}]: last pageview was " . round($mins_since) . "min ago (threshold: {$window}min)";
                    $fired_alert = true;
                }
            }
        }
    } catch (Exception $e) {
        error_log('[alerts] Rule ' . $rule['id'] . ' eval error: ' . $e->getMessage());
        echo "ERROR rule #{$rule['id']}: " . $e->getMessage() . "\n";
        continue;
    }

    if ($fired_alert) {
        try {
            $pdo->prepare("INSERT INTO alert_log (rule_id, site, alert_type, message, current_value, baseline_value)
                VALUES (?,?,?,?,?,?)")
                ->execute([$rule['id'], $site, $type, $message, $current_val, $baseline_val]);

            $pdo->prepare("UPDATE alert_rules SET last_fired = NOW() WHERE id = ?")
                ->execute([$rule['id']]);

            $labels = ['apexcybernet'=>'Apex Cybernet.co','ocpd'=>'Oslob Paragliding','loan'=>'Apex Cybernet','all'=>'All Sites'];
            $label  = $labels[$site] ?? $site;
            $sent   = send_alert_email(
                $email,
                "[Apex Cybernet Alert] {$label} — " . strtoupper(str_replace('_', ' ', $type)),
                "ALERT FIRED\n\n{$message}\n\nTime: {$now}\n\nDashboard: https://apexcybernet.com/admin/activity.php?site={$site}\n\n---\nApex Cybernet Analytics · argonarsoftware.com",
                $brevo_key
            );

            $fired++;
            echo "FIRED: [$type][$site] email=" . ($sent ? 'sent' : 'failed') . " → $message\n";
        } catch (Exception $e) {
            error_log('[alerts] Log/send failed: ' . $e->getMessage());
        }
    } else {
        echo "OK   [{$type}][{$site}]: no threshold breach (current={$current_val}, baseline={$baseline_val})\n";
    }
}

echo "\nDone. Checked {$checked} rules, fired {$fired} alert(s). [{$now}]\n";
