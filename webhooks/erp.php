<?php
/**
 * ERP → storefront webhook.
 * Set the ERP's WEBSITE_WEBHOOK_URL to: https://store.mosaicengine.in/webhooks/erp.php
 *
 * The ERP doesn't retry, so this does the minimum: verify the HMAC signature
 * over the raw body, record the event, update one order row, respond.
 */
define('NO_SESSION', true);
require dirname(__DIR__) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    json_response(['error' => 'method not allowed'], 405);
}

$rawBody = file_get_contents('php://input', false, null, 0, 65537);
if ($rawBody === false || strlen($rawBody) > 65536) {
    json_response(['error' => 'payload too large'], 413);
}

if (!verify_webhook_signature($rawBody, $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? null, ERP_WEBHOOK_SECRET)) {
    json_response(['error' => 'invalid signature'], 401);
}

$payload = json_decode($rawBody, true);
if (!is_array($payload) || !is_string($payload['event'] ?? null) || !is_array($payload['data'] ?? null)) {
    json_response(['error' => 'malformed payload'], 400);
}

try {
    json_response(['ok' => true] + apply_webhook($rawBody, $payload));
} catch (Throwable $e) {
    error_log('[storefront] webhook failed: ' . $e);
    json_response(['error' => 'internal error'], 500);
}
