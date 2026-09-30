<?php
/** Unit tests for the pure helpers. Run: php tests/run.php */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/includes/signature.php';
require APP_ROOT . '/includes/india.php';
require APP_ROOT . '/includes/icons.php';
require APP_ROOT . '/includes/helpers.php';
require APP_ROOT . '/includes/auth.php';

$failures = 0;
function check(string $name, bool $ok): void
{
    global $failures;
    echo ($ok ? "  ok   " : "  FAIL ") . $name . PHP_EOL;
    if (!$ok) $failures++;
}

$secret = 'test-secret';
$body = '{"event":"order.status_changed","data":{"order_no":"SO-000123","website_order_id":"WEB-1","status":"confirmed","previous_status":"pending"},"sent_at":"2026-09-30T12:00:00+00:00"}';
$sig = hash_hmac('sha256', $body, $secret);

echo "Webhook signature\n";
check('accepts a correct signature', verify_webhook_signature($body, $sig, $secret));
check('accepts uppercase hex', verify_webhook_signature($body, strtoupper($sig), $secret));
check('accepts a sha256= prefix', verify_webhook_signature($body, "sha256=$sig", $secret));
check('rejects a tampered body', !verify_webhook_signature(str_replace('confirmed', 'completed', $body), $sig, $secret));
check('rejects another key', !verify_webhook_signature($body, hash_hmac('sha256', $body, 'other'), $secret));
check('rejects a missing signature', !verify_webhook_signature($body, null, $secret));
check('rejects a malformed signature', !verify_webhook_signature($body, 'not-hex', $secret));
check('rejects a truncated signature', !verify_webhook_signature($body, substr($sig, 0, 40), $secret));
check('rejects when no secret is configured', !verify_webhook_signature($body, hash_hmac('sha256', $body, ''), ''));

echo "Phone numbers\n";
check('10 digits', normalize_indian_phone('9876543210') === '9876543210');
check('+91 with spaces', normalize_indian_phone('+91 98765 43210') === '9876543210');
check('leading 0', normalize_indian_phone('09876543210') === '9876543210');
check('rejects landline-style start', normalize_indian_phone('1234567890') === null);
check('rejects short', normalize_indian_phone('98765') === null);

echo "Prices\n";
check('₹0.00', format_price(0) === '₹0.00');
check('₹999.50', format_price(999.5) === '₹999.50');
check('₹1,234.00', format_price(1234) === '₹1,234.00');
check('₹1,23,456.78', format_price(123456.78) === '₹1,23,456.78');
check('₹1,23,45,678.00', format_price(12345678) === '₹1,23,45,678.00');

echo "Post-login redirects\n";
check('keeps a same-site path', safe_return_url('/checkout.php?x=1', '/fallback') === '/checkout.php?x=1');
check('rejects another site', safe_return_url('https://evil.example', '/fallback') === '/fallback');
check('rejects protocol-relative //', safe_return_url('//evil.example/x', '/fallback') === '/fallback');
check('rejects backslash tricks', safe_return_url('/\\evil.example', '/fallback') === '/fallback');
check('rejects header injection', safe_return_url("/ok\r\nSet-Cookie: x=1", '/fallback') === '/fallback');
check('rejects empty', safe_return_url('', '/fallback') === '/fallback');

echo $failures ? "\n$failures test(s) failed\n" : "\nAll tests passed\n";
exit($failures ? 1 : 0);
