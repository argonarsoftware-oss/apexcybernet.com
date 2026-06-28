<?php
/**
 * includes/mailer.php — tiny Resend wrapper.
 *
 * Single function: send_email($to, $subject, $html_body, $text_body=null).
 * Returns true on success, false otherwise. Graceful no-op (returns false)
 * when RESEND_API_KEY is empty, so staging/dev doesn't blow up.
 */
require_once __DIR__ . '/seo_config.php';

function send_email(string $to, string $subject, string $html_body, ?string $text_body = null): bool {
    if (RESEND_API_KEY === '') {
        error_log('[mailer] RESEND_API_KEY not configured — skipping send to ' . $to);
        return false;
    }
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log('[mailer] invalid recipient: ' . $to);
        return false;
    }

    $payload = [
        'from'    => MAIL_FROM_NAME . ' <' . MAIL_FROM_ADDR . '>',
        'to'      => [$to],
        'subject' => $subject,
        'html'    => $html_body,
    ];
    if ($text_body !== null) $payload['text'] = $text_body;

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . RESEND_API_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_CONNECTTIMEOUT => 4,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($code >= 200 && $code < 300) return true;
    error_log('[mailer] Resend failed (' . $code . ') to ' . $to . ': ' . ($err ?: $resp));
    return false;
}
