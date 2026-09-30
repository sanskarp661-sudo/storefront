<?php
/**
 * Loaded first by every page and script. Pages may define NO_SESSION before
 * including this (webhook, cron) to skip starting a PHP session.
 */

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

if (!is_file(APP_ROOT . '/config.php')) {
    http_response_code(503);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "config.php is missing. Copy config.sample.php to config.php and fill it in.\n");
        exit(1);
    }
    echo '<!doctype html><meta charset="utf-8"><title>Store setup</title>'
        . '<p style="font-family:sans-serif;padding:2rem">The store is being set up. Please check back soon.</p>';
    exit;
}
require APP_ROOT . '/config.php';

foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'ERP_API_KEY', 'ERP_API_BASE', 'ERP_WEBHOOK_SECRET'] as $const) {
    if (!defined($const)) {
        http_response_code(503);
        error_log("[storefront] config.php is missing $const");
        exit(PHP_SAPI === 'cli' ? "config.php is missing $const\n" : 'The store is being set up. Please check back soon.');
    }
}
defined('STORE_NAME') || define('STORE_NAME', 'Mosaic Store');
defined('SUPPORT_EMAIL') || define('SUPPORT_EMAIL', '');
defined('SITE_URL') || define('SITE_URL', '');
defined('PAYMENT_METHODS') || define('PAYMENT_METHODS', 'cod');
defined('DEBUG') || define('DEBUG', false);

date_default_timezone_set('UTC');
error_reporting(E_ALL);
ini_set('display_errors', DEBUG ? '1' : '0');
ini_set('log_errors', '1');

require __DIR__ . '/helpers.php';
require __DIR__ . '/icons.php';
require __DIR__ . '/db.php';
require __DIR__ . '/erp.php';
require __DIR__ . '/india.php';
require __DIR__ . '/payments.php';
require __DIR__ . '/catalog.php';
require __DIR__ . '/sync.php';
require __DIR__ . '/cart.php';
require __DIR__ . '/orders.php';
require __DIR__ . '/signature.php';
require __DIR__ . '/templates/partials.php';

if (PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    // Order links carry a private key in the URL; don't leak it to other sites via Referer.
    header('Referrer-Policy: same-origin');

    set_exception_handler(function (Throwable $e): void {
        error_log('[storefront] ' . $e);
        if (!headers_sent()) http_response_code(500);
        if (DEBUG) {
            echo '<pre>' . e((string) $e) . '</pre>';
        } else {
            render_error_page(500);
        }
    });

    if (!defined('NO_SESSION')) {
        session_name('store_sid');
        session_set_cookie_params([
            'lifetime' => 60 * 60 * 24 * 30,
            'path' => '/',
            'secure' => is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.gc_maxlifetime', (string) (60 * 60 * 24 * 30));
        session_start();
    }
}
