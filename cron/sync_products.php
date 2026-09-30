<?php
/**
 * Catalog sync + order reconciliation. Run from Hostinger Cron Jobs (command line only):
 *
 *   /usr/bin/php /home/<user>/domains/store.mosaicengine.in/public_html/cron/sync_products.php
 *
 * Optional argument: "full" forces a full re-sync (e.g. ... sync_products.php full).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}
if (PHP_VERSION_ID < 80100) {
    fwrite(STDERR, 'This script needs PHP 8.1 or newer, but cron ran PHP ' . PHP_VERSION . ". Use /usr/bin/php8.3 (or /opt/alt/php83/usr/bin/php) in the Cron Job command.\n");
    exit(1);
}
define('NO_SESSION', true);
require dirname(__DIR__) . '/includes/bootstrap.php';

set_time_limit(0);
$mode = in_array($argv[1] ?? '', ['full', 'incremental'], true) ? $argv[1] : 'auto';
$started = microtime(true);

try {
    $catalog = sync_catalog($mode);
    $orders = reconcile_stuck_orders();
    echo gmdate('c'), ' ', json_encode(['catalog' => $catalog, 'orders' => $orders], JSON_UNESCAPED_SLASHES),
        sprintf(' (%.1fs)', microtime(true) - $started), PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, gmdate('c') . ' sync failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
