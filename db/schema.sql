-- Storefront database schema. Idempotent: safe to run repeatedly (npm run db:migrate).

-- Local mirror of the ERP catalog, refreshed by the product sync.
CREATE TABLE IF NOT EXISTS products (
  erp_id              integer PRIMARY KEY,
  sku                 text NOT NULL,
  name                text NOT NULL,
  description         text,
  category            text,
  brand               text,
  image_url           text,
  unit                text,
  price               numeric(12, 2) NOT NULL,
  currency            text NOT NULL DEFAULT 'INR',
  quantity_on_hand    numeric(14, 3) NOT NULL DEFAULT 0,
  available_quantity  numeric(14, 3) NOT NULL DEFAULT 0,
  status              text NOT NULL,
  erp_updated_at      timestamptz,
  synced_at           timestamptz NOT NULL DEFAULT now(),
  search_text         text GENERATED ALWAYS AS (
                        lower(coalesce(name, '') || ' ' || sku || ' ' || coalesce(brand, '') || ' ' || coalesce(category, ''))
                      ) STORED
);
CREATE INDEX IF NOT EXISTS products_sku_idx ON products (sku);
CREATE INDEX IF NOT EXISTS products_category_idx ON products (category) WHERE status = 'active';
CREATE INDEX IF NOT EXISTS products_updated_idx ON products (erp_updated_at DESC);

CREATE TABLE IF NOT EXISTS categories (
  erp_id    integer PRIMARY KEY,
  name      text NOT NULL,
  synced_at timestamptz NOT NULL DEFAULT now()
);

-- Sync bookkeeping: cursors plus a lease so concurrent requests don't run the same sync twice.
CREATE TABLE IF NOT EXISTS sync_state (
  key             text PRIMARY KEY,
  cursor          text,
  last_run_at     timestamptz,
  last_full_at    timestamptz,
  lease_until     timestamptz,
  last_error      text
);

CREATE TABLE IF NOT EXISTS orders (
  id                  bigserial PRIMARY KEY,
  website_order_id    text NOT NULL UNIQUE,
  checkout_key        uuid NOT NULL UNIQUE,
  access_token        text NOT NULL,
  -- submitting: row created, ERP call in flight
  -- submitted:  ERP accepted it (erp_order_no set)
  -- rejected:   ERP returned 400/422; nothing was created there
  -- unknown:    ERP call failed ambiguously (timeout / 5xx); reconciled via order_status.php
  submit_state        text NOT NULL DEFAULT 'submitting',
  submit_error        text,
  erp_order_no        text UNIQUE,
  status              text NOT NULL DEFAULT 'pending',
  status_updated_at   timestamptz,
  customer_name       text NOT NULL,
  customer_email      text NOT NULL,
  customer_phone      text,
  customer_company    text,
  shipping_address    jsonb NOT NULL,
  items               jsonb NOT NULL,
  notes               text,
  subtotal_estimate   numeric(12, 2) NOT NULL,
  erp_total_amount    numeric(12, 2),
  currency            text NOT NULL DEFAULT 'INR',
  order_date          date,
  delivery_note       jsonb,
  delivered_at        timestamptz,
  invoice             jsonb,
  last_webhook_at     timestamptz,
  last_polled_at      timestamptz,
  created_at          timestamptz NOT NULL DEFAULT now(),
  updated_at          timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS orders_email_idx ON orders (lower(customer_email));

-- Raw log of verified webhook deliveries (dedupes redeliveries by body hash).
CREATE TABLE IF NOT EXISTS order_events (
  id                bigserial PRIMARY KEY,
  body_sha256       text NOT NULL UNIQUE,
  event             text NOT NULL,
  website_order_id  text,
  erp_order_no      text,
  payload           jsonb NOT NULL,
  sent_at           timestamptz,
  received_at       timestamptz NOT NULL DEFAULT now(),
  matched           boolean NOT NULL DEFAULT false
);

-- v2: payment method chosen at checkout (see src/lib/payments.ts).
ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_method text NOT NULL DEFAULT 'cod';
