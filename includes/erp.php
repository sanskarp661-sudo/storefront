<?php
declare(strict_types=1);

/**
 * Client for the ERP's REST API. Server-side only: the API key is sent from
 * PHP via cURL and never appears in any page or JavaScript.
 */

/** The ERP answered with an HTTP error (4xx/5xx). */
final class ErpHttpException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message, $status);
    }
}

/** The ERP could not be reached, timed out, or returned something that isn't JSON. */
final class ErpConnectionException extends RuntimeException
{
}

function erp_request(string $method, string $path, array $query = [], ?array $body = null, int $timeout = 10): array
{
    $query = array_filter($query, fn($v) => $v !== null && $v !== '');
    $url = rtrim(ERP_API_BASE, '/') . '/' . ltrim($path, '/') . ($query ? '?' . http_build_query($query) : '');

    $headers = ['X-API-Key: ' . ERP_API_KEY, 'Accept: application/json'];
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_USERAGENT => 'MosaicStorefront/1.0',
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $headers[] = 'Content-Type: application/json';
    }
    $opts[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($ch, $opts);

    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);

    if ($raw === false) {
        throw new ErpConnectionException("ERP request to $path failed: $error");
    }
    $data = json_decode((string) $raw, true);

    if ($status >= 400) {
        $message = is_array($data) && isset($data['error']) && is_string($data['error'])
            ? $data['error']
            : "ERP responded with HTTP $status";
        throw new ErpHttpException($status, $message);
    }
    if (!is_array($data)) {
        throw new ErpConnectionException("ERP returned a non-JSON response for $path (HTTP $status)");
    }
    return $data;
}

/** One page of the product list. Pass $since (ISO 8601) for an incremental fetch. */
function erp_list_products(int $page, int $perPage = 200, ?string $since = null): array
{
    return erp_request('GET', 'products.php', ['page' => $page, 'per_page' => $perPage, 'since' => $since], null, 30);
}

/** Live single-product lookup; null when the ERP says 404 (missing or inactive). */
function erp_get_product(string $sku, int $timeout = 5): ?array
{
    try {
        return erp_request('GET', 'products.php', ['sku' => $sku], null, $timeout)['product'] ?? null;
    } catch (ErpHttpException $e) {
        if ($e->status === 404) return null;
        throw $e;
    }
}

function erp_list_categories(): array
{
    return erp_request('GET', 'categories.php')['categories'] ?? [];
}

function erp_create_order(array $payload): array
{
    return erp_request('POST', 'orders.php', [], $payload, 20);
}

/**
 * Status lookup by ['order_no' => ...] or ['website_order_id' => ...].
 * Returns null when the ERP has no such order (404).
 */
function erp_order_status(array $lookup, int $timeout = 5): ?array
{
    try {
        return erp_request('GET', 'order_status.php', $lookup, null, $timeout);
    } catch (ErpHttpException $e) {
        if ($e->status === 404) return null;
        throw $e;
    }
}
