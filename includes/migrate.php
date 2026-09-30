<?php
declare(strict_types=1);

/**
 * Automatic schema upgrades, so an existing install never needs a manual SQL
 * import for new features. Runs at most once per schema version per server
 * (a marker file in the temp dir skips the check on every later request).
 * sql/schema.sql always contains the full, current schema for fresh installs.
 */

const SCHEMA_VERSION = 2;

function ensure_schema(): void
{
    $marker = sys_get_temp_dir() . '/mosaic_store_schema_' . md5(DB_HOST . '|' . DB_NAME) . '_v' . SCHEMA_VERSION;
    if (is_file($marker)) return;

    $pdo = db();
    // v2: customer accounts, saved addresses, wishlist, password resets.
    $pdo->exec("CREATE TABLE IF NOT EXISTS customers (
        id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name            VARCHAR(120) NOT NULL,
        email           VARCHAR(200) NOT NULL,
        phone           VARCHAR(20)  NULL,
        password_hash   VARCHAR(255) NOT NULL,
        failed_logins   INT UNSIGNED NOT NULL DEFAULT 0,
        locked_until    DATETIME(3)  NULL,
        last_login_at   DATETIME(3)  NULL,
        created_at      DATETIME(3)  NOT NULL,
        updated_at      DATETIME(3)  NOT NULL,
        UNIQUE KEY uq_customers_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS customer_addresses (
        id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        customer_id     BIGINT UNSIGNED NOT NULL,
        label           VARCHAR(20)  NOT NULL DEFAULT 'Home',
        name            VARCHAR(120) NOT NULL,
        phone           VARCHAR(20)  NOT NULL,
        address_line    VARCHAR(300) NOT NULL,
        city            VARCHAR(80)  NOT NULL,
        state           VARCHAR(80)  NOT NULL,
        pincode         VARCHAR(10)  NOT NULL,
        is_default      TINYINT(1)   NOT NULL DEFAULT 0,
        created_at      DATETIME(3)  NOT NULL,
        KEY idx_addresses_customer (customer_id),
        CONSTRAINT fk_addresses_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS wishlist_items (
        customer_id     BIGINT UNSIGNED NOT NULL,
        sku             VARCHAR(100) NOT NULL,
        created_at      DATETIME(3)  NOT NULL,
        PRIMARY KEY (customer_id, sku),
        CONSTRAINT fk_wishlist_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
        token_hash      CHAR(64)     NOT NULL PRIMARY KEY,
        customer_id     BIGINT UNSIGNED NOT NULL,
        expires_at      DATETIME(3)  NOT NULL,
        used_at         DATETIME(3)  NULL,
        created_at      DATETIME(3)  NOT NULL,
        KEY idx_resets_customer (customer_id),
        CONSTRAINT fk_resets_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $hasColumn = db_one(
        "SELECT 1 AS x FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'customer_id'"
    );
    if (!$hasColumn) {
        $pdo->exec('ALTER TABLE orders ADD COLUMN customer_id BIGINT UNSIGNED NULL AFTER access_token, ADD KEY idx_orders_customer (customer_id, created_at)');
    }

    @file_put_contents($marker, (string) time());
}
