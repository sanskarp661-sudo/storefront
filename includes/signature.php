<?php
declare(strict_types=1);

/**
 * Verifies the ERP's X-Webhook-Signature header, which is
 * hash_hmac('sha256', <raw request body>, ERP_WEBHOOK_SECRET) as lowercase hex.
 * $rawBody must be the exact bytes received (file_get_contents('php://input')).
 */
function verify_webhook_signature(string $rawBody, ?string $signature, string $secret): bool
{
    if ($secret === '' || $signature === null) return false;
    $provided = strtolower(trim($signature));
    if (str_starts_with($provided, 'sha256=')) $provided = substr($provided, 7);
    if (!preg_match('/^[0-9a-f]{64}$/', $provided)) return false;

    return hash_equals(hash_hmac('sha256', $rawBody, $secret), $provided);
}
