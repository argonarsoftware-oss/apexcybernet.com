<?php
/**
 * Helper to call the GCash Listener API from the Argonar site.
 * Keeps the API key server-side — never exposed to the browser.
 */

define('LISTENER_URL', 'https://listener.argonar.co');
define('LISTENER_API_KEY', 'kirfenia123');

function listenerAPI(string $method, string $endpoint, ?array $data = null): ?array {
    $url = LISTENER_URL . $endpoint;

    $headers = [
        "Content-Type: application/json",
        "X-API-Key: " . LISTENER_API_KEY,
    ];

    $opts = [
        'http' => [
            'method'  => $method,
            'header'  => implode("\r\n", $headers),
            'timeout' => 10,
            'ignore_errors' => true,
        ],
    ];

    if ($data !== null) {
        $opts['http']['content'] = json_encode($data);
    }

    $context = stream_context_create($opts);
    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        return null;
    }

    return json_decode($response, true);
}

/**
 * Create a payment order on the Listener API.
 * Returns the API response or null on failure.
 */
function listenerCreateOrder(string $orderId, float $amount, string $description): ?array {
    return listenerAPI('POST', '/api/orders', [
        'order_id'    => $orderId,
        'amount'      => $amount,
        'description' => $description,
    ]);
}

/**
 * Check if an order has been paid.
 * Returns the API response or null on failure.
 */
function listenerCheckPayment(string $orderId): ?array {
    return listenerAPI('POST', '/api/check-payment', [
        'order_id' => $orderId,
    ]);
}

/**
 * Get order details from the Listener API.
 */
function listenerGetOrder(string $orderId): ?array {
    return listenerAPI('GET', '/api/orders/' . urlencode($orderId));
}
