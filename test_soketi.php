<?php
if (($_GET['token'] ?? '') !== 'apexcybernet-ws-test-2026') die('Forbidden');
header('Content-Type: application/json');

// Test 1: Can we reach Soketi on port 6001?
$fp = @fsockopen('127.0.0.1', 6001, $errno, $errstr, 2);
$soketi_reachable = $fp !== false;
if ($fp) fclose($fp);

// Test 2: Does Soketi respond to HTTP?
$ctx = stream_context_create(['http' => ['timeout' => 2, 'ignore_errors' => true]]);
$resp = @file_get_contents('http://127.0.0.1:6001/', false, $ctx);

echo json_encode([
    'soketi_port_open' => $soketi_reachable,
    'soketi_http_resp' => $resp === false ? 'no response' : substr($resp, 0, 200),
    'errno'            => $errno,
    'errstr'           => $errstr,
], JSON_PRETTY_PRINT);
