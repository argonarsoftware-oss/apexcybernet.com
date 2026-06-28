<?php
/**
 * Shared config for the voice signaling layer.
 * The HMAC secret lives on disk (same file the Node server reads) and is gitignored.
 */

function voice_secret(): string {
    static $cached = null;
    if ($cached !== null) return $cached;
    $paths = [
        __DIR__ . '/../voice-signal/.voice-secret',
        '/etc/apexcybernet/voice.secret',
    ];
    foreach ($paths as $p) {
        if (is_readable($p)) { $cached = trim(@file_get_contents($p)); return $cached; }
    }
    return $cached = '';
}

/** Return ICE servers config, including time-limited TURN credentials if our VPS runs coturn. */
function voice_ice_servers(int $ttl_seconds = 600): array {
    $turn_secret_file = '/etc/turnserver-secret';
    $turn_secret = is_readable($turn_secret_file) ? trim((string)@file_get_contents($turn_secret_file)) : '';

    // Determine public host of our TURN (same as web host by default)
    $turn_host = $_SERVER['HTTP_HOST'] ?? 'apexcybernet.com';
    $turn_host = preg_replace('/:\d+$/', '', $turn_host); // strip port

    $servers = [
        ['urls' => ['stun:stun.l.google.com:19302', 'stun:stun1.l.google.com:19302']],
    ];

    if ($turn_secret !== '') {
        // coturn use-auth-secret format: username = "<expiry_unix>:<user_label>", password = HMAC-SHA1(secret, username) base64
        $expiry = time() + $ttl_seconds;
        $user   = $expiry . ':apexcybernet';
        $pass   = base64_encode(hash_hmac('sha1', $user, $turn_secret, true));
        $servers[] = ['urls' => 'turn:' . $turn_host . ':3478?transport=udp', 'username' => $user, 'credential' => $pass];
        $servers[] = ['urls' => 'turn:' . $turn_host . ':3478?transport=tcp', 'username' => $user, 'credential' => $pass];
    } else {
        // Fallback public TURN (unreliable but better than nothing)
        $servers[] = ['urls' => 'turn:openrelay.metered.ca:80',  'username' => 'openrelayproject', 'credential' => 'openrelayproject'];
        $servers[] = ['urls' => 'turn:openrelay.metered.ca:443', 'username' => 'openrelayproject', 'credential' => 'openrelayproject'];
    }
    return $servers;
}

/** Build the canonical room id for a DM between two users (always sorted). */
function voice_dm_room(int $a, int $b): string {
    $lo = min($a, $b); $hi = max($a, $b);
    return "d:{$lo}-{$hi}";
}
function voice_group_room(int $gid): string { return "g:{$gid}"; }

/** Issue a short-lived signed JWT for the given user+room. */
function voice_issue_token(int $peer_id, string $room_id, string $display_name, int $ttl_seconds = 300): ?string {
    $secret = voice_secret();
    if (!$secret) return null;

    $header  = ['alg' => 'HS256', 'typ' => 'JWT'];
    $payload = [
        'peer_id'      => $peer_id,
        'room_id'      => $room_id,
        'group_id'     => str_starts_with($room_id, 'g:') ? (int)substr($room_id, 2) : 0, // backward-compat field
        'display_name' => mb_substr($display_name, 0, 80),
        'iat'          => time(),
        'exp'          => time() + $ttl_seconds,
    ];

    $b64 = fn($x) => rtrim(strtr(base64_encode($x), '+/', '-_'), '=');
    $h   = $b64(json_encode($header,  JSON_UNESCAPED_SLASHES));
    $p   = $b64(json_encode($payload, JSON_UNESCAPED_SLASHES));
    $sig = $b64(hash_hmac('sha256', $h . '.' . $p, $secret, true));
    return $h . '.' . $p . '.' . $sig;
}
