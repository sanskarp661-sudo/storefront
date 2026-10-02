-- Mosaic Store — storefront database schema (MySQL 5.7+ / MariaDB 10.3+).
-- Run once in phpMyAdmin (hPanel → Databases → phpMyAdmin → select your DB → Import / SQL tab).
-- Safe to re-run: every statement is CREATE TABLE IF NOT EXISTS.
-- (Existing installs are upgraded automatically by includes/migrate.php — no need to re-import.)

SET NAMES utf8mb4;

-- Local copy of the ERP catalogue, refreshed by cron/sync_products.php.
CREATE TABLE IF NOT EXISTS products (
  erp_id              INT UNSIGNED  NOT NULL PRIMARY KEY,
  sku                 VARCHAR(100)  NOT NULL,
  name                VARCHAR(255)  NOT NULL,
  description         TEXT          NULL,
  category            VARCHAR(191)  NULL,
  sub_category        VARCHAR(191)  NULL,  -- the ERP's "Item Category" (Shirt, T-Shirt, Trouser…)
  brand               VARCHAR(191)  NULL,
  image_url           VARCHAR(1024) NULL,
  images              TEXT          NULL,  -- JSON list of all image URLs (main image first)
  unit                VARCHAR(50)   NULL,
  price               DECIMAL(12,2) NOT NULL DEFAULT 0,
  currency            CHAR(3)       NOT NULL DEFAULT 'INR',
  quantity_on_hand    DECIMAL(14,3) NOT NULL DEFAULT 0,
  available_quantity  DECIMAL(14,3) NOT NULL DEFAULT 0,
  status              VARCHAR(20)   NOT NULL DEFAULT 'active',
  erp_updated_at      DATETIME(3)   NULL,
  synced_at           DATETIME(3)   NOT NULL,
  search_text         TEXT          NOT NULL,
  KEY idx_products_sku (sku),
  KEY idx_products_category (status, category, sub_category),
  KEY idx_products_brand (status, brand),
  KEY idx_products_updated (erp_updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
  erp_id     INT UNSIGNED NOT NULL PRIMARY KEY,
  name       VARCHAR(191) NOT NULL,
  synced_at  DATETIME(3)  NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sync bookkeeping: incremental cursor, and a lease so two cron runs never overlap.
CREATE TABLE IF NOT EXISTS sync_state (
  name          VARCHAR(50) NOT NULL PRIMARY KEY,
  cursor_value  VARCHAR(64) NULL,
  last_run_at   DATETIME(3) NULL,
  last_full_at  DATETIME(3) NULL,
  lease_until   DATETIME(3) NULL,
  last_error    TEXT        NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  website_order_id    VARCHAR(20)   NOT NULL,
  checkout_key        CHAR(32)      NOT NULL,
  access_token        CHAR(32)      NOT NULL,
  customer_id         BIGINT UNSIGNED NULL,
  -- submitting: saved locally, ERP call in progress
  -- submitted:  accepted by the ERP (erp_order_no set)
  -- rejected:   ERP returned 400/422; nothing was created there
  -- unknown:    ERP call failed ambiguously (timeout / 5xx); settled via order_status.php
  submit_state        VARCHAR(20)   NOT NULL DEFAULT 'submitting',
  submit_error        TEXT          NULL,
  erp_order_no        VARCHAR(40)   NULL,
  status              VARCHAR(20)   NOT NULL DEFAULT 'pending',
  status_updated_at   DATETIME(3)   NULL,
  customer_name       VARCHAR(120)  NOT NULL,
  customer_email      VARCHAR(200)  NOT NULL,
  customer_phone      VARCHAR(20)   NULL,
  customer_company    VARCHAR(120)  NULL,
  ship_label          VARCHAR(20)   NULL,
  ship_address_line   VARCHAR(300)  NOT NULL,
  ship_city           VARCHAR(80)   NOT NULL,
  ship_state          VARCHAR(80)   NOT NULL,
  ship_pincode        VARCHAR(10)   NOT NULL,
  ship_country        VARCHAR(60)   NOT NULL DEFAULT 'India',
  notes               TEXT          NULL,
  payment_method      VARCHAR(30)   NOT NULL DEFAULT 'cod',
  subtotal_estimate   DECIMAL(12,2) NOT NULL DEFAULT 0,
  erp_total_amount    DECIMAL(12,2) NULL,
  currency            CHAR(3)       NOT NULL DEFAULT 'INR',
  order_date          DATE          NULL,
  dn_no               VARCHAR(40)   NULL,
  dn_status           VARCHAR(30)   NULL,
  delivered_at        DATETIME(3)   NULL,
  invoice_no          VARCHAR(40)   NULL,
  invoice_status      VARCHAR(30)   NULL,
  invoice_amount_paid DECIMAL(12,2) NULL,
  invoice_total       DECIMAL(12,2) NULL,
  last_webhook_at     DATETIME(3)   NULL,
  last_polled_at      DATETIME(3)   NULL,
  created_at          DATETIME(3)   NOT NULL,
  updated_at          DATETIME(3)   NOT NULL,
  UNIQUE KEY uq_orders_website_order_id (website_order_id),
  UNIQUE KEY uq_orders_checkout_key (checkout_key),
  UNIQUE KEY uq_orders_erp_order_no (erp_order_no),
  KEY idx_orders_email (customer_email),
  KEY idx_orders_customer (customer_id, created_at),
  KEY idx_orders_submit_state (submit_state, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Snapshot of what was ordered (name/price as shown at checkout; the ERP sets final prices).
CREATE TABLE IF NOT EXISTS order_items (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_id    BIGINT UNSIGNED NOT NULL,
  sku         VARCHAR(100)  NOT NULL,
  name        VARCHAR(255)  NOT NULL,
  quantity    INT UNSIGNED  NOT NULL,
  unit_price  DECIMAL(12,2) NOT NULL,
  image_url   VARCHAR(1024) NULL,
  KEY idx_order_items_order (order_id),
  CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Log of verified webhook deliveries (also de-duplicates repeated deliveries).
CREATE TABLE IF NOT EXISTS order_events (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  body_sha256       CHAR(64)     NOT NULL,
  event             VARCHAR(50)  NOT NULL,
  website_order_id  VARCHAR(20)  NULL,
  erp_order_no      VARCHAR(40)  NULL,
  payload           MEDIUMTEXT   NOT NULL,
  sent_at           DATETIME(3)  NULL,
  received_at       DATETIME(3)  NOT NULL,
  matched           TINYINT(1)   NOT NULL DEFAULT 0,
  UNIQUE KEY uq_order_events_body (body_sha256)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customer accounts (profile, saved addresses, wishlist, password reset).
CREATE TABLE IF NOT EXISTS customers (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_addresses (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wishlist_items (
  customer_id     BIGINT UNSIGNED NOT NULL,
  sku             VARCHAR(100) NOT NULL,
  created_at      DATETIME(3)  NOT NULL,
  PRIMARY KEY (customer_id, sku),
  CONSTRAINT fk_wishlist_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
  token_hash      CHAR(64)     NOT NULL PRIMARY KEY,
  customer_id     BIGINT UNSIGNED NOT NULL,
  expires_at      DATETIME(3)  NOT NULL,
  used_at         DATETIME(3)  NULL,
  created_at      DATETIME(3)  NOT NULL,
  KEY idx_resets_customer (customer_id),
  CONSTRAINT fk_resets_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
