# Storefront

A customer-facing e-commerce site (Next.js 16 App Router + TypeScript + Tailwind) for the Mosaic ERP at
`https://erp.mosaicengine.in`. Customers can browse the catalog synced from the ERP, check out, and track their orders.
Orders are pushed to the ERP, and status comes back through signed webhooks, with polling as a fallback.

## Webhook URL for the ERP

```
WEBSITE_WEBHOOK_URL = https://<your-storefront-domain>/api/webhooks/erp
```

The endpoint recomputes `hash_hmac('sha256', rawBody, ERP_API_KEY)` and compares it with `X-Webhook-Signature` in
constant time. It returns `401` when the signature is wrong, `400` for malformed JSON, and `200` otherwise, including
for orders it doesn't know. Each request does two small DB writes and returns in a few milliseconds.

## Environment variables

| Variable | Required | Notes |
| --- | --- | --- |
| `ERP_API_KEY` | yes | Server-only. Sent as `X-API-Key` and used as the webhook HMAC secret. |
| `ERP_API_BASE_URL` | no | Defaults to `https://erp.mosaicengine.in/api/v1/`. |
| `DATABASE_URL` | yes | Postgres. On Vercel, use the **pooled** connection string (Neon / Supabase / Vercel Postgres). |
| `CRON_SECRET` | yes | Protects `/api/cron/sync`. Vercel Cron sends it automatically as `Authorization: Bearer …`. |
| `NEXT_PUBLIC_STORE_NAME` | no | Display name, default "Mosaic Store". |
| `NEXT_PUBLIC_SUPPORT_EMAIL` | no | Shown in the footer. Default `sanskar@mosaicengine.in`. |
| `PAYMENT_METHODS` | no | Comma-separated payment methods offered at checkout; the first is the default. Default `cod`. |

See `.env.example`.

## Setup

```bash
npm install
cp .env.example .env.local   # fill in values
npm run db:migrate           # creates tables (idempotent, safe to re-run)
npm run dev
```

The catalog fills itself: when the database is empty, the first page view runs a full sync. To trigger a sync by hand:

```bash
curl -H "Authorization: Bearer $CRON_SECRET" "https://<domain>/api/cron/sync?mode=full"
```

Checks: `npm run lint`, `npm run typecheck`, `npm test` (webhook signature tests), `npm run build`.

## Deploying (Vercel)

1. Import the repo in Vercel. Add a Postgres database (for example Neon from the Vercel Marketplace), which sets `DATABASE_URL`.
2. Set `ERP_API_KEY` and `CRON_SECRET` (plus the optional variables) for Production.
3. Run `npm run db:migrate` once against the production `DATABASE_URL`.
4. Deploy, then set the ERP's `WEBSITE_WEBHOOK_URL` to `https://<domain>/api/webhooks/erp`.

The app runs on any Node host (`npm run build && npm start`). It has no Vercel-specific code apart from `vercel.json`'s cron entry.

## How it works

**Catalog: synced into Postgres and served from there** (`src/lib/server/sync.ts`)
- *Incremental* sync calls `products.php?since=<max updated_at seen>`. The cursor is the ERP's own timestamp, so the two
  servers' clocks never need to agree. *Full* sync walks every page and marks products the ERP no longer returns as
  `inactive`, which catches deletions and deactivations. It runs every 6 hours, or on `?mode=full`.
- **Freshness does not depend on the cron schedule.** A page view on data older than 5 minutes serves the cached data
  immediately and starts a background incremental sync (`after()`). A lease row stops overlapping syncs across serverless
  instances. The Vercel cron (`vercel.json`, daily, which the Hobby plan allows) is only a safety net. On Pro you can
  schedule it more often, or have any external scheduler call `/api/cron/sync`.
- Search (name / SKU / brand / category), category and brand filters, in-stock toggle, sorting and pagination all run in SQL.
- Stock displayed is `available_quantity`, as specified.

**Cart & checkout** (`src/components/cart-provider.tsx`, `src/app/checkout/`, `src/lib/server/orders.ts`)
- The cart is stored client-side in `localStorage`. The cart page refreshes prices and stock from the DB, and prices in
  the cart are display-only: the order payload sends SKU and quantity, and the ERP resolves prices.
- On submit, a Server Action validates the form with zod (Indian mobile, 6-digit PIN, state list) and re-checks each SKU
  live against `products.php?sku=`. If stock ran out, the customer sees it and the cart is corrected. It then creates a
  local order row with a generated `website_order_id` (`WEB-XXXXXXXXXX`) and POSTs to `orders.php`.
- **No duplicate orders.** Every checkout attempt carries an idempotency key, so double-clicks and retries resolve to the
  same order. If the ERP call fails ambiguously (timeout, 5xx), the storefront looks the order up with
  `order_status.php?website_order_id=` before telling the customer anything, so a retry can't create a second ERP order.
  The cron route also reconciles any order still in doubt.
- 400/422 responses from the ERP are shown to the customer, and the order is marked `rejected`.

**Payment** (`src/lib/payments.ts`)
- Cash on Delivery is currently the only method. The customer's choice is saved on the order and shown on the order page.
- The ERP order API has no payment field, so the method is written as the first line of the ERP order's `notes`
  (`Payment: Cash on Delivery (COD)`), above the customer's own notes.
- To add online payment later: add the method to `PAYMENT_METHODS` in `src/lib/payments.ts` with `kind: "online"`,
  implement its gateway flow where `placeOrder` checks `kind`, and enable it with `PAYMENT_METHODS=cod,<id>`. Until a
  flow exists, `placeOrder` refuses any method that isn't offline.

**Tax.** Prices and totals are shown **excluding GST**, with a note that GST is confirmed on the invoice. No tax rates are
built in. Adding a GST estimate needs rates per product or category, which the ERP API doesn't currently provide.

**Order confirmation & tracking** (`src/app/orders/[id]`, `src/app/track`)
- Each order has a private link, `/orders/<website_order_id>?key=<random token>`. The order ID alone doesn't open the page.
- *Track my order* needs the order number (`SO-…` or `WEB-…`) **and** the checkout email. Orders placed on a device are
  also listed there.
- Status comes from the local DB, which webhooks keep up to date. If the record hasn't been updated recently (2 minutes
  for active orders, 1 hour for completed or cancelled ones), viewing it triggers a quick poll of `order_status.php`,
  which also pulls in the delivery note and invoice. If the ERP is slow, the page shows the cached record.

**Webhooks** (`src/app/api/webhooks/erp/route.ts`)
- Each delivery is logged in `order_events`, and a hash of the body dedupes redeliveries.
- Out-of-order deliveries are handled: a status is applied only if its `sent_at` is not older than the last status recorded.
- `order.delivered` records `delivered_at` and the delivery note number, and the timeline shows the order as delivered.

**Security**
- The ERP API key is read only in `src/lib/server/env.ts`. Everything under `src/lib/server/` imports `server-only`, so
  importing it from a Client Component fails the build. ERP calls are made only from Server Components, Server Actions
  and Route Handlers.
- `Referrer-Policy: same-origin` keeps order-link tokens from leaking through the `Referer` header.
- The checkout form has a honeypot field. There is no rate limiting yet; add it at the edge (for example the Vercel
  Firewall) if bots become a problem.

## Not included / to decide

- **Online payment.** Not built yet; Cash on Delivery only (see *Payment* above for how to add a method).
- **Customer emails.** The storefront doesn't send email, so customers track orders through their link or the lookup page.
- **Shipping charges.** None are added. The ERP's `total_amount` is shown as the order total, excluding GST.
