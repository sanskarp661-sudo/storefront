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
