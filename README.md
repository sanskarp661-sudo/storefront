# Mosaic Store — storefront

A customer-facing shop for the Mosaic ERP, written in plain PHP + MySQL so it runs on Hostinger shared hosting.
It has no framework, no Composer and no build step on the server.

- **Catalogue** is copied from the ERP into the store's own MySQL database by a cron job, and pages are served from that copy.
- **Cart** is held in the PHP session. **Checkout** sends the order to the ERP from PHP (cURL). **Cash on Delivery** is the only payment method for now.
- **Order status** stays current through the ERP's signed webhook. If a webhook is missed, the store polls `order_status.php`.
- The ERP API key is used only by PHP on the server. It never appears in any page or JavaScript.

Requirements: PHP 8.1+ with `pdo_mysql`, `curl` and `mbstring` (all standard on Hostinger), and MySQL 5.7+ / MariaDB 10.3+.

---

## Setup on Hostinger (hPanel)

### 1. Upload the files

Put the contents of this folder into the site's web root, `domains/store.mosaicengine.in/public_html/`.
You can use the Git auto-deploy or upload `storefront-upload.zip` in **File Manager** and extract it there.
`index.php` must sit directly inside `public_html`.

If an earlier version is already in `public_html` (for example the old Next.js files), delete it first.

### 2. Create the database

1. hPanel → **Databases → MySQL Databases**: create a database and a user. Note the full names Hostinger gives them
   (like `u123456789_store`) and the password.
2. hPanel → **Databases → phpMyAdmin** → open that database → **Import** → choose `sql/schema.sql` → **Go**.
   You can also paste the file into the **SQL** tab. It creates 7 tables and is safe to run again.

### 3. Create `config.php`

In File Manager, copy `config.sample.php` to **`config.php`** (same folder) and fill in:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'u123456789_store');        // from step 2
define('DB_USER', 'u123456789_store');        // from step 2
define('DB_PASS', '...');                     // from step 2
define('ERP_API_KEY', '...');                 // the ERP API key
define('ERP_API_BASE', 'https://erp.mosaicengine.in/api/v1');
define('ERP_WEBHOOK_SECRET', '...');          // the same value as ERP_API_KEY
define('STORE_NAME', 'Mosaic Store');
define('SUPPORT_EMAIL', 'sanskar@mosaicengine.in');
define('SITE_URL', 'https://store.mosaicengine.in');
define('PAYMENT_METHODS', 'cod');
define('DEBUG', false);
```

`config.php` is in `.gitignore`, so it is never committed, and `.htaccess` blocks it from being downloaded.
Hostinger's Git deploy pulls into the folder and normally leaves untracked files like this in place.
Keep a copy of your values somewhere safe anyway.

### 4. Schedule the sync (Cron Jobs)

hPanel → **Advanced → Cron Jobs** → **Custom**:

| Field | Value |
| --- | --- |
| Command | `/usr/bin/php /home/u123456789/domains/store.mosaicengine.in/public_html/cron/sync_products.php` |
| Schedule | every 15 minutes (`*/15 * * * *`) |

Replace `u123456789` with your Hostinger username. It's the first part of the path File Manager shows, and the prefix on your database name.

Each run:
- fetches products changed since the last run (`products.php?since=…`)
- does a full re-sync every 6 hours (this also hides products removed in the ERP)
- refreshes categories
- settles any order whose submission to the ERP timed out

Runs never overlap. The first run loads the whole catalogue. Until it has run, the shop shows "Our catalogue is being updated".
To force a full re-sync, add ` full` to the end of the command.

### 5. Point the ERP webhook at the store

Set the ERP's `WEBSITE_WEBHOOK_URL` to:

```
https://store.mosaicengine.in/webhooks/erp.php
```

### 6. Check it

- Open **https://store.mosaicengine.in/status.php**. It checks the PHP version and extensions, `config.php`, the database connection and tables, the ERP API key, the cron sync, orders and webhooks, and tells you how to fix anything that fails. It never shows passwords or keys.
- Open https://store.mosaicengine.in. After the first cron run, products appear.
- Place a test order. It should show up in the ERP as a pending sales order, with `Payment: Cash on Delivery (COD)` at the top of its notes.
- Change the order's status in the ERP. The order page on the store should show the new status straight away.

SSL: `.htaccess` redirects HTTP to HTTPS, so the subdomain needs its (free) SSL certificate active in hPanel → **Security → SSL**.

---

## Files

| Path | What it is |
| --- | --- |
| `index.php`, `products.php`, `product.php` | Home, catalogue (search, category/brand filters, in-stock, sort, pages), product page |
| `cart.php`, `checkout.php` | Session cart; checkout form that places the order |
| `status.php` | Setup self-check (pass/fail for each part; shows no secrets) |
| `order.php`, `track.php` | Order confirmation / status page (private link); "Track my order" lookup |
| `webhooks/erp.php` | ERP webhook receiver |
| `cron/sync_products.php` | Cron job (command line only; refuses web requests) |
| `includes/` | PHP code and templates (not reachable from the web) |
| `sql/schema.sql` | Database schema |
| `assets/` | Compiled CSS and a small JS file |
| `tests/run.php` | `php tests/run.php`: unit tests for signature checking and formatting |
| `tools/tailwind/` | Only needed to rebuild the CSS after changing classes in templates: `cd tools/tailwind && npm install && npm run build` |

## How it works

**Catalogue.** The cron job copies the ERP catalogue into the `products` and `categories` tables.
The incremental cursor is the newest `updated_at` the ERP has returned, so it never depends on the server's clock.
Stock shown is the ERP's `available_quantity`. Prices exclude GST, and the pages say that GST is confirmed on the invoice.

**Checkout.**
- The checkout form is protected by a CSRF token and validates name, email, Indian mobile, 6-digit PIN and state.
  Full state names are sent to the ERP.
- Before ordering, PHP re-checks every cart line live against `products.php?sku=`. If stock has run out, the cart is
  corrected and the customer is told.
- The order is saved locally, with its items in `order_items`, and then POSTed to `orders.php` with a generated
  `website_order_id` (`WEB-XXXXXXXXXX`). Prices are not sent; the ERP sets them.

**No duplicate orders.** Each checkout attempt has an idempotency key, so a double click or a retry resolves to the same order.
If the ERP call times out or returns a 5xx, the store first looks the order up with
`order_status.php?website_order_id=…` before deciding anything. The cron job also settles any order still in doubt.
ERP 400/422 errors are shown to the customer.

**Payment.** Cash on Delivery. The ERP order API has no payment field, so the method goes in the first line of the
order notes. To add online payment later, see the comment at the top of `includes/payments.php`.

**Order status.**
- Webhooks are verified with `hash_hmac('sha256', raw body, ERP_WEBHOOK_SECRET)` and `hash_equals()`. They are logged
  in `order_events`, where repeated deliveries are ignored, and update the matching order in a few milliseconds.
- A status is applied only if its `sent_at` is not older than the one already stored, so late deliveries can't move an
  order backwards.
- When someone views an order whose data is more than 2 minutes old (1 hour once it's completed or cancelled), the store
  also asks `order_status.php`. This fills in the delivery note and invoice, and covers any missed webhook.

**Access to orders.**
- Each order has a private link: `order.php?id=WEB-…&key=<random>`.
- "Track my order" needs the order number and the checkout email, and is limited to 10 lookups per 10 minutes per browser session.
- Orders placed in the same browser session are listed on the track page.

**Security.**
- `config.php`, `includes/`, `cron/`, `sql/`, `tests/`, `tools/`, `.git` and dotfiles return 403 (`.htaccess`).
- PDO prepared statements are used everywhere, and all output is HTML-escaped.
- The session cookie is `HttpOnly`, `SameSite=Lax` and `Secure` on HTTPS.
- `Referrer-Policy: same-origin` keeps order-link keys out of Referer headers.
- There is a honeypot field on checkout.
- With `DEBUG` off, errors go to the PHP error log, and visitors see a generic message.
