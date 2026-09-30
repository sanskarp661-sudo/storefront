<?php
/**
 * Storefront configuration.
 *
 * Copy this file to config.php (same folder) and fill in the real values.
 * config.php must never be committed to git or shared.
 */

// --- Storefront database (the MySQL database you created in hPanel; NOT the ERP's database) ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'u000000000_store');
define('DB_USER', 'u000000000_store');
define('DB_PASS', 'your-database-password');

// --- ERP API (server-side only; never sent to the browser) ---
define('ERP_API_KEY', 'your-erp-api-key');
define('ERP_API_BASE', 'https://erp.mosaicengine.in/api/v1');
// Same value as ERP_API_KEY: used to verify the X-Webhook-Signature on incoming webhooks.
define('ERP_WEBHOOK_SECRET', 'your-erp-api-key');

// --- Store settings ---
define('STORE_NAME', 'Mosaic Store');
define('SUPPORT_EMAIL', 'sanskar@mosaicengine.in');
// Optional: sender for password-reset emails (a mailbox on your domain). Defaults to SUPPORT_EMAIL.
// define('MAIL_FROM', 'no-reply@mosaicengine.in');
// Public address of the site, no trailing slash.
define('SITE_URL', 'https://store.mosaicengine.in');
// Payment methods offered at checkout, comma-separated; the first is the default. Currently available: cod
define('PAYMENT_METHODS', 'cod');

// Show PHP errors in the browser. Keep false on the live site.
define('DEBUG', false);
