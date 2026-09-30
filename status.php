<?php
/**
 * Setup self-check: https://store.mosaicengine.in/status.php
 * Shows pass/fail for each part of the installation. Never prints secrets,
 * credentials or database names.
 */
define('NO_SESSION', true);
require __DIR__ . '/includes/bootstrap.php';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$checks = [];
$add = function (string $group, string $label, string $state, string $detail = '', string $fix = '') use (&$checks) {
    $checks[] = compact('group', 'label', 'state', 'detail', 'fix');
};

// --- Server -----------------------------------------------------------------
$add('Server', 'PHP version', version_compare(PHP_VERSION, '8.1.0', '>=') ? 'ok' : 'fail', PHP_VERSION,
    'Select PHP 8.1 or newer in hPanel → Advanced → PHP Configuration.');
foreach (['pdo_mysql', 'curl', 'mbstring'] as $ext) {
    $add('Server', "PHP extension: $ext", extension_loaded($ext) ? 'ok' : 'fail', '',
        "Enable $ext in hPanel → Advanced → PHP Configuration → PHP Extensions.");
}
$add('Server', 'HTTPS', is_https() ? 'ok' : 'warn', is_https() ? '' : 'Page was loaded over HTTP',
    'Activate the free SSL certificate for the subdomain in hPanel → Security → SSL.');

// --- config.php -------------------------------------------------------------
$placeholder = fn(string $v) => $v === '' || str_contains($v, 'your-') || str_contains($v, 'u000000000');
$add('config.php', 'Database settings filled in', ($placeholder(DB_NAME) || $placeholder(DB_USER) || $placeholder(DB_PASS)) ? 'fail' : 'ok', '',
    'Set DB_NAME, DB_USER and DB_PASS to the database you created in hPanel.');
$add('config.php', 'ERP_API_KEY filled in', $placeholder(ERP_API_KEY) ? 'fail' : 'ok', '', 'Paste the ERP API key into ERP_API_KEY.');
$add('config.php', 'ERP_WEBHOOK_SECRET equals ERP_API_KEY', hash_equals(ERP_API_KEY, ERP_WEBHOOK_SECRET) ? 'ok' : 'fail', '',
    'ERP_WEBHOOK_SECRET must be exactly the same value as ERP_API_KEY.');
$add('config.php', 'ERP_API_BASE', rtrim(ERP_API_BASE, '/') === 'https://erp.mosaicengine.in/api/v1' ? 'ok' : 'warn', ERP_API_BASE,
    "Expected https://erp.mosaicengine.in/api/v1");
$add('config.php', 'DEBUG is off', DEBUG ? 'warn' : 'ok', '', "Set DEBUG to false on the live site.");

// --- Database ---------------------------------------------------------------
$dbOk = false;
try {
    db();
    $dbOk = true;
    $add('Database', 'Connection', 'ok');
} catch (Throwable $e) {
    $code = $e instanceof PDOException ? (string) ($e->errorInfo[1] ?? $e->getCode()) : '';
    $hint = match ($code) {
        '1045' => 'Access denied: DB_USER or DB_PASS is wrong.',
        '1049' => 'Unknown database: DB_NAME is wrong.',
        '2002' => 'Cannot reach the MySQL server: DB_HOST should be localhost.',
        default => 'Could not connect (error ' . ($code ?: 'unknown') . ').',
    };
    $add('Database', 'Connection', 'fail', $hint, 'Check DB_HOST / DB_NAME / DB_USER / DB_PASS against hPanel → Databases → MySQL Databases.');
}

if ($dbOk) {
    $expected = ['products', 'categories', 'sync_state', 'orders', 'order_items', 'order_events', 'customers', 'customer_addresses', 'wishlist_items', 'password_resets'];
    $present = db_query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $missing = array_values(array_diff($expected, $present));
    $add('Database', 'Tables (' . count($expected) . ')', $missing ? 'fail' : 'ok', $missing ? 'Missing: ' . implode(', ', $missing) : 'All present, including customer accounts',
        'Import sql/schema.sql in phpMyAdmin (it is safe to run again). Account tables are otherwise created automatically on the next page view.');
    $add('Server', 'Email (password reset)', function_exists('mail') ? 'ok' : 'warn', function_exists('mail') ? 'mail() available, sending from ' . ((defined('MAIL_FROM') && MAIL_FROM) ? MAIL_FROM : SUPPORT_EMAIL) : 'mail() is disabled',
        'Password-reset emails need PHP mail(); create the sender mailbox in hPanel → Emails if messages don\'t arrive.');
}

// --- ERP API (checked at most once a minute) ---------------------------------
$cacheFile = sys_get_temp_dir() . '/mosaic_store_erp_check_' . md5(ERP_API_KEY . ERP_API_BASE);
$cached = is_file($cacheFile) && filemtime($cacheFile) > time() - 60 ? json_decode((string) file_get_contents($cacheFile), true) : null;
if (!is_array($cached)) {
    try {
        $cats = erp_list_categories();
        $cached = ['state' => 'ok', 'detail' => count($cats) . ' categories returned', 'fix' => ''];
    } catch (ErpHttpException $e) {
        $cached = $e->status === 401 || $e->status === 403
            ? ['state' => 'fail', 'detail' => "ERP rejected the API key (HTTP {$e->status})", 'fix' => 'Check ERP_API_KEY in config.php.']
            : ['state' => 'fail', 'detail' => "ERP returned HTTP {$e->status}", 'fix' => 'Check ERP_API_BASE and that the ERP is up.'];
    } catch (Throwable $e) {
        $cached = ['state' => 'fail', 'detail' => 'Could not reach the ERP', 'fix' => 'Check ERP_API_BASE; the ERP must be reachable from this server.'];
    }
    @file_put_contents($cacheFile, json_encode($cached));
}
$add('ERP API', 'API key accepted (categories.php)', $cached['state'], $cached['detail'], $cached['fix']);

// --- Catalogue sync (cron) ----------------------------------------------------
if ($dbOk && empty($missing)) {
    $sync = db_one("SELECT last_run_at, last_full_at, last_error, lease_until FROM sync_state WHERE name = 'catalog'");
    $active = (int) db_one("SELECT COUNT(*) AS n FROM products WHERE status = 'active'")['n'];
    $cronHint = 'Add the Cron Job from the README and wait for it to run.';
    // The exact command for this server, from where the files really are (shown only until cron works).
    $cronCommand = 'Use this exact Cron Job command: /usr/bin/php ' . APP_ROOT . '/cron/sync_products.php';
    if (!$sync) {
        $add('Catalogue sync', 'Cron has run', 'warn', 'The cron job has not reached the store yet',
            $cronCommand . ' (hPanel → Cron Jobs → View output shows any error.)');
    } elseif (!$sync['last_run_at'] && $sync['lease_until'] && strtotime($sync['lease_until'] . ' UTC') > time()) {
        $add('Catalogue sync', 'Cron has run', 'info', 'The first sync is running right now', 'Reload this page in a minute.');
    } elseif (!$sync['last_run_at']) {
        $add('Catalogue sync', 'Cron has run', $sync['last_error'] ? 'fail' : 'warn',
            $sync['last_error'] ? 'Last attempt failed: ' . mb_substr($sync['last_error'], 0, 200) : 'The cron job started but no sync has completed yet',
            'hPanel → Cron Jobs → View output shows the full error. ' . $cronCommand);
    } else {
        $age = time() - strtotime($sync['last_run_at'] . ' UTC');
        $add('Catalogue sync', 'Cron has run', $age < 45 * 60 ? 'ok' : 'warn',
            'Last successful sync ' . ($age < 120 ? "$age seconds" : round($age / 60) . ' minutes') . ' ago',
            'The last sync is over 45 minutes old: check the Cron Job is still scheduled.');
        if ($sync['last_error']) $add('Catalogue sync', 'Last sync error', 'warn', mb_substr($sync['last_error'], 0, 200));
    }
    $add('Catalogue sync', 'Products in the store', $active > 0 ? 'ok' : 'warn', "$active active products", $cronHint);

    // --- Orders & webhooks --------------------------------------------------------
    $orders = db_one("SELECT COUNT(*) AS n, SUM(submit_state = 'submitted') AS ok, SUM(submit_state IN ('unknown','submitting')) AS pending FROM orders");
    $events = db_one('SELECT COUNT(*) AS n, MAX(received_at) AS last FROM order_events');
    $add('Orders', 'Orders placed', (int) $orders['n'] > 0 ? 'ok' : 'info',
        (int) $orders['n'] . ' total, ' . (int) $orders['ok'] . ' accepted by the ERP' . ((int) $orders['pending'] ? ', ' . (int) $orders['pending'] . ' awaiting confirmation' : ''),
        'Place a test order to check checkout end to end.');
    $add('Orders', 'Webhooks received from the ERP', (int) $events['n'] > 0 ? 'ok' : 'info',
        (int) $events['n'] > 0 ? (int) $events['n'] . ' received, last at ' . $events['last'] . ' UTC' : 'None yet',
        'Set the ERP\'s WEBSITE_WEBHOOK_URL to ' . (SITE_URL ?: 'https://store.mosaicengine.in') . '/webhooks/erp.php, then change a test order\'s status in the ERP.');
}

$fails = count(array_filter($checks, fn($c) => $c['state'] === 'fail'));
$warns = count(array_filter($checks, fn($c) => $c['state'] === 'warn'));
$badge = [
    'ok' => ['✓', 'bg-emerald-50 text-emerald-700'],
    'fail' => ['✗', 'bg-red-50 text-red-700'],
    'warn' => ['!', 'bg-amber-50 text-amber-800'],
    'info' => ['•', 'bg-stone-100 text-stone-600'],
];
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Setup check · <?= e(STORE_NAME) ?></title>
<link rel="stylesheet" href="<?= e(asset('assets/css/store.css')) ?>">
</head>
<body class="font-sans">
<div class="container-page max-w-3xl py-12">
  <h1 class="text-3xl font-semibold tracking-tight">Setup check</h1>
  <p class="mt-2 <?= $fails ? 'text-red-700' : ($warns ? 'text-amber-800' : 'text-emerald-700') ?> font-medium">
    <?= $fails ? "$fails problem" . ($fails === 1 ? '' : 's') . ' to fix' : ($warns ? "Working, with $warns thing" . ($warns === 1 ? '' : 's') . ' to look at' : 'Everything is set up.') ?>
  </p>
  <?php $group = null; foreach ($checks as $c): ?>
    <?php if ($c['group'] !== $group): $group = $c['group']; ?>
      <?= $group === $checks[0]['group'] ? '' : '</ul>' ?>
      <h2 class="mb-2 mt-8 text-sm font-semibold uppercase tracking-wide text-ink-soft"><?= e($group) ?></h2>
      <ul class="divide-y divide-line rounded-2xl border border-line bg-white">
    <?php endif; ?>
    <li class="flex gap-3 px-4 py-3 text-sm">
      <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full font-semibold <?= $badge[$c['state']][1] ?>"><?= $badge[$c['state']][0] ?></span>
      <div>
        <p class="font-medium"><?= e($c['label']) ?><?php if ($c['detail']): ?> <span class="font-normal text-ink-soft">— <?= e($c['detail']) ?></span><?php endif; ?></p>
        <?php if ($c['state'] !== 'ok' && $c['fix']): ?><p class="mt-0.5 text-ink-soft"><?= e($c['fix']) ?></p><?php endif; ?>
      </div>
    </li>
  <?php endforeach; ?>
  </ul>
  <p class="mt-8 text-xs text-ink-soft">Checked <?= gmdate('j M Y, H:i') ?> UTC. This page shows no passwords or keys.</p>
</div>
</body></html>
