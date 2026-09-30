import "server-only";
import { createHash, randomBytes, timingSafeEqual } from "node:crypto";
import { db } from "./db";
import { getProductsBySkus } from "./catalog";
import { createOrder, ErpHttpError, getOrderStatus, getProduct, type ErpOrderStatusResponse } from "./erp";
import { upsertProducts } from "./sync";
import type { OrderItem, OrderStatus, OrderView, ShippingAddress, SubmitState } from "../types";

// ---------------------------------------------------------------------------
// Types & helpers

type OrderRow = {
  id: string;
  website_order_id: string;
  checkout_key: string;
  access_token: string;
  submit_state: SubmitState;
  submit_error: string | null;
  erp_order_no: string | null;
  status: OrderStatus;
  status_updated_at: Date | null;
  customer_name: string;
  customer_email: string;
  customer_phone: string | null;
  customer_company: string | null;
  shipping_address: ShippingAddress;
  items: OrderItem[];
  notes: string | null;
  subtotal_estimate: number;
  erp_total_amount: number | null;
  currency: string;
  order_date: Date | string | null;
  delivery_note: { dn_no: string; status: string } | null;
  delivered_at: Date | null;
  invoice: { invoice_no: string; status: string; amount_paid: number | string; total: number | string } | null;
  last_webhook_at: Date | null;
  last_polled_at: Date | null;
  created_at: Date;
};

export type CheckoutInput = {
  checkoutKey: string;
  customer: { name: string; email: string; phone: string; company: string | null };
  shippingAddress: ShippingAddress;
  items: { sku: string; quantity: number }[];
  notes: string | null;
};

export type ItemIssue = { sku: string; message: string; available?: number };

export type PlaceOrderResult =
  | { ok: true; websiteOrderId: string; accessToken: string }
  | { ok: false; error: string; itemIssues?: ItemIssue[]; rotateKey: boolean };

const ORDER_STATUSES: OrderStatus[] = ["pending", "confirmed", "shipped", "completed", "cancelled"];
const TERMINAL: OrderStatus[] = ["completed", "cancelled"];

function asStatus(s: unknown): OrderStatus | null {
  return ORDER_STATUSES.includes(s as OrderStatus) ? (s as OrderStatus) : null;
}

const CROCKFORD = "0123456789ABCDEFGHJKMNPQRSTVWXYZ";
function generateWebsiteOrderId(): string {
  const bytes = randomBytes(10);
  let id = "";
  for (const b of bytes) id += CROCKFORD[b % 32];
  return `WEB-${id}`;
}

function safeEqual(a: string, b: string): boolean {
  const ab = Buffer.from(a);
  const bb = Buffer.from(b);
  return ab.length === bb.length && timingSafeEqual(ab, bb);
}

function toIso(d: Date | string | null): string | null {
  if (!d) return null;
  return d instanceof Date ? d.toISOString() : d;
}

function toView(r: OrderRow): OrderView {
  return {
    websiteOrderId: r.website_order_id,
    erpOrderNo: r.erp_order_no,
    submitState: r.submit_state,
    submitError: r.submit_error,
    status: r.status,
    customerName: r.customer_name,
    customerEmail: r.customer_email,
    customerPhone: r.customer_phone,
    shippingAddress: r.shipping_address,
    items: r.items,
    notes: r.notes,
    subtotalEstimate: Number(r.subtotal_estimate),
    totalAmount: r.erp_total_amount === null ? null : Number(r.erp_total_amount),
    currency: r.currency,
    orderDate: r.order_date instanceof Date ? r.order_date.toISOString().slice(0, 10) : r.order_date,
    deliveryNote: r.delivery_note,
    deliveredAt: toIso(r.delivered_at),
    invoice: r.invoice
      ? { ...r.invoice, amount_paid: Number(r.invoice.amount_paid), total: Number(r.invoice.total) }
      : null,
    createdAt: r.created_at.toISOString(),
  };
}

// ---------------------------------------------------------------------------
// Placing orders

/**
 * Re-checks every cart line against the ERP (live `?sku=` lookup) so the
 * customer isn't told an item is in stock based on a catalog snapshot that
 * may be a few minutes old. Falls back to the local mirror if the ERP is
 * slow to answer; the ERP still validates SKUs itself when the order is posted.
 */
async function checkItems(items: { sku: string; quantity: number }[]) {
  const local = await getProductsBySkus(items.map((i) => i.sku));
  const issues: ItemIssue[] = [];
  const lines: OrderItem[] = [];

  const live = await Promise.all(
    items.map(async (i) => {
      try {
        return { sku: i.sku, product: await getProduct(i.sku, 4_000), ok: true as const };
      } catch {
        return { sku: i.sku, product: null, ok: false as const };
      }
    }),
  );
  const fresh = live.flatMap((l) => (l.product ? [l.product] : []));
  if (fresh.length > 0) await upsertProducts(fresh).catch(() => {});

  for (const item of items) {
    const l = live.find((x) => x.sku === item.sku)!;
    const localProduct = local.get(item.sku);
    let name: string, price: number, available: number, imageUrl: string | null, active: boolean;

    if (l.ok) {
      active = !!l.product && l.product.status === "active";
      name = l.product?.name ?? localProduct?.name ?? item.sku;
      price = Number(l.product?.price ?? 0);
      available = Number(l.product?.available_quantity ?? 0);
      imageUrl = l.product?.image_url ?? null;
    } else {
      active = !!localProduct;
      name = localProduct?.name ?? item.sku;
      price = localProduct?.price ?? 0;
      available = localProduct?.availableQuantity ?? 0;
      imageUrl = localProduct?.imageUrl ?? null;
    }

    if (!active) {
      issues.push({ sku: item.sku, message: `${name} is no longer available.`, available: 0 });
    } else if (available < item.quantity) {
      issues.push({
        sku: item.sku,
        available: Math.max(0, Math.floor(available)),
        message:
          available <= 0 ? `${name} is out of stock.` : `Only ${Math.floor(available)} of ${name} left in stock.`,
      });
    }
    lines.push({ sku: item.sku, name, quantity: item.quantity, unitPrice: price, imageUrl });
  }
  return { issues, lines };
}

async function findByCheckoutKey(key: string) {
  const [row] = await db()<OrderRow[]>`SELECT * FROM orders WHERE checkout_key = ${key}`;
  return row ?? null;
}

async function markSubmitted(id: string, erp: { order_no: string; status: unknown; total_amount: unknown }) {
  await db()`
    UPDATE orders SET
      submit_state = 'submitted',
      submit_error = NULL,
      erp_order_no = ${erp.order_no},
      status = ${asStatus(erp.status) ?? "pending"},
      status_updated_at = COALESCE(status_updated_at, now()),
      erp_total_amount = ${erp.total_amount === undefined || erp.total_amount === null ? null : Number(erp.total_amount)},
      updated_at = now()
    WHERE id = ${id}
  `;
}

/**
 * When the ERP call fails ambiguously (timeout, 5xx, dropped connection) the
 * order may or may not exist on the ERP side. Look it up by our own id before
 * deciding, so a retry never creates a duplicate order.
 */
async function reconcile(row: OrderRow): Promise<"submitted" | "absent" | "unreachable"> {
  let status: ErpOrderStatusResponse | null;
  try {
    status = await getOrderStatus({ websiteOrderId: row.website_order_id }, 5_000);
  } catch {
    return "unreachable";
  }
  if (!status) return "absent";
  await markSubmitted(row.id, status);
  await applyStatusSnapshot(row.id, status);
  return "submitted";
}

async function submitToErp(row: OrderRow): Promise<PlaceOrderResult> {
  const sql = db();
  const ok = { ok: true as const, websiteOrderId: row.website_order_id, accessToken: row.access_token };

  try {
    const res = await createOrder({
      website_order_id: row.website_order_id,
      customer: {
        name: row.customer_name,
        email: row.customer_email,
        phone: row.customer_phone,
        company: row.customer_company,
      },
      shipping_address: row.shipping_address,
      items: row.items.map((i) => ({ sku: i.sku, quantity: i.quantity })),
      notes: row.notes,
    });
    await markSubmitted(row.id, res);
    return ok;
  } catch (err) {
    if (err instanceof ErpHttpError && (err.status === 400 || err.status === 422)) {
      await sql`
        UPDATE orders SET submit_state = 'rejected', submit_error = ${err.message}, updated_at = now()
        WHERE id = ${row.id}
      `;
      return {
        ok: false,
        rotateKey: true,
        error:
          err.status === 422
            ? `One of the items in your cart can't be ordered right now: ${err.message}`
            : `We couldn't place your order: ${err.message}`,
      };
    }

    console.error("[orders] ERP order submission failed", row.website_order_id, err);
    const outcome = await reconcile(row);
    if (outcome === "submitted") return ok;

    await sql`
      UPDATE orders SET submit_state = 'unknown',
        submit_error = ${err instanceof Error ? err.message : String(err)}, updated_at = now()
      WHERE id = ${row.id}
    `;
    return {
      ok: false,
      rotateKey: false,
      error:
        "We couldn't reach our order system to confirm your order. Please try again in a moment — retrying won't create a duplicate order.",
    };
  }
}

export async function placeOrder(input: CheckoutInput): Promise<PlaceOrderResult> {
  const sql = db();

  // Idempotency: the checkout form sends a stable key per attempt, so double
  // submits and retries after a network error resolve to the same order.
  const existing = await findByCheckoutKey(input.checkoutKey);
  if (existing) return resumeExisting(existing);

  // Merge duplicate lines.
  const merged = new Map<string, number>();
  for (const i of input.items) merged.set(i.sku, (merged.get(i.sku) ?? 0) + i.quantity);
  const items = [...merged].map(([sku, quantity]) => ({ sku, quantity }));

  const { issues, lines } = await checkItems(items);
  if (issues.length > 0) {
    return {
      ok: false,
      rotateKey: true,
      itemIssues: issues,
      error: "Some items in your cart have changed. Please review your cart and try again.",
    };
  }

  const subtotal = Math.round(lines.reduce((s, l) => s + l.unitPrice * l.quantity, 0) * 100) / 100;
  let row: OrderRow | undefined;
  for (let attempt = 0; attempt < 3 && !row; attempt++) {
    const rows = await sql<OrderRow[]>`
      INSERT INTO orders ${sql({
        website_order_id: generateWebsiteOrderId(),
        checkout_key: input.checkoutKey,
        access_token: randomBytes(24).toString("base64url"),
        customer_name: input.customer.name,
        customer_email: input.customer.email,
        customer_phone: input.customer.phone,
        customer_company: input.customer.company,
        shipping_address: sql.json(input.shippingAddress),
        items: sql.json(lines),
        notes: input.notes,
        subtotal_estimate: subtotal,
      } as Record<string, unknown>)}
      ON CONFLICT DO NOTHING
      RETURNING *
    `;
    row = rows[0];
    if (!row) {
      // Either a concurrent submit with the same checkout key won the race, or
      // (astronomically unlikely) the random website_order_id collided.
      const concurrent = await findByCheckoutKey(input.checkoutKey);
      if (concurrent) return resumeExisting(concurrent);
    }
  }
  if (!row) throw new Error("Could not allocate a website order id");

  return submitToErp(row);
}

async function resumeExisting(row: OrderRow): Promise<PlaceOrderResult> {
  const ok = { ok: true as const, websiteOrderId: row.website_order_id, accessToken: row.access_token };
  switch (row.submit_state) {
    case "submitted":
      return ok;
    case "rejected":
      return { ok: false, rotateKey: true, error: row.submit_error ?? "This order was rejected. Please review your cart." };
    case "submitting":
      // Another request is mid-flight for this key; let the order page show progress.
      if (Date.now() - row.created_at.getTime() < 60_000) return ok;
    // falls through: a stale "submitting" row means that request died.
    case "unknown": {
      const outcome = await reconcile(row);
      if (outcome === "submitted") return ok;
      if (outcome === "absent") return submitToErp(row);
      return {
        ok: false,
        rotateKey: false,
        error: "We still can't reach our order system. Please try again in a few minutes.",
      };
    }
  }
}

// ---------------------------------------------------------------------------
// Reading & refreshing orders

async function applyStatusSnapshot(id: string, s: ErpOrderStatusResponse) {
  const status = asStatus(s.status);
  await db()`
    UPDATE orders SET
      status = COALESCE(${status}, status),
      status_updated_at = now(),
      erp_order_no = COALESCE(erp_order_no, ${s.order_no}),
      erp_total_amount = COALESCE(${s.total_amount === null || s.total_amount === undefined ? null : Number(s.total_amount)}, erp_total_amount),
      order_date = COALESCE(${s.order_date || null}::date, order_date),
      delivery_note = ${s.delivery_note ? db().json(s.delivery_note) : null},
      invoice = ${s.invoice ? db().json(s.invoice) : null},
      last_polled_at = now(),
      updated_at = now()
    WHERE id = ${id}
  `;
}

const POLL_ACTIVE_MS = 2 * 60 * 1000;
const POLL_TERMINAL_MS = 60 * 60 * 1000;

function needsRefresh(r: OrderRow): boolean {
  if (r.submit_state === "rejected") return false;
  if (r.submit_state === "unknown") return true;
  if (r.submit_state === "submitting") return Date.now() - r.created_at.getTime() > 30_000;
  const last = Math.max(r.last_webhook_at?.getTime() ?? 0, r.last_polled_at?.getTime() ?? 0);
  const interval = TERMINAL.includes(r.status) ? POLL_TERMINAL_MS : POLL_ACTIVE_MS;
  return Date.now() - last > interval;
}

/**
 * Fallback poll against order_status.php, used when webhook data is missing
 * or stale (webhooks are not retried by the ERP, so one can be lost).
 * Never throws: on ERP trouble the cached record is shown.
 */
async function refreshIfStale(row: OrderRow): Promise<OrderRow> {
  if (!needsRefresh(row)) return row;
  try {
    if (row.submit_state !== "submitted" || !row.erp_order_no) {
      await reconcile(row);
    } else {
      const s = await getOrderStatus({ orderNo: row.erp_order_no }, 3_000);
      if (s) await applyStatusSnapshot(row.id, s);
    }
  } catch (err) {
    console.error("[orders] status refresh failed", row.website_order_id, err);
  }
  const [updated] = await db()<OrderRow[]>`SELECT * FROM orders WHERE id = ${row.id}`;
  return updated ?? row;
}

/** Loads an order for the holder of its private link (website_order_id + access token). */
export async function getOrderForViewer(websiteOrderId: string, token: string): Promise<OrderView | null> {
  if (!websiteOrderId || !token) return null;
  const [row] = await db()<OrderRow[]>`SELECT * FROM orders WHERE website_order_id = ${websiteOrderId}`;
  if (!row || !safeEqual(row.access_token, token)) return null;
  return toView(await refreshIfStale(row));
}

/**
 * "Track my order" lookup: requires the order reference (our WEB- id or the
 * ERP's SO- number) AND the email used at checkout, so knowing one alone
 * reveals nothing.
 */
export async function findOrderLink(reference: string, email: string) {
  const ref = reference.trim().toUpperCase();
  const [row] = await db()<{ website_order_id: string; access_token: string }[]>`
    SELECT website_order_id, access_token FROM orders
    WHERE (upper(website_order_id) = ${ref} OR upper(erp_order_no) = ${ref})
      AND lower(customer_email) = ${email.trim().toLowerCase()}
    LIMIT 1
  `;
  return row ? { websiteOrderId: row.website_order_id, accessToken: row.access_token } : null;
}

/** Cron helper: settle orders whose submission outcome is still unknown. */
export async function reconcileStuckOrders(limit = 25) {
  const rows = await db()<OrderRow[]>`
    SELECT * FROM orders
    WHERE (submit_state = 'unknown' OR (submit_state = 'submitting' AND created_at < now() - interval '2 minutes'))
      AND created_at > now() - interval '7 days'
    ORDER BY created_at LIMIT ${limit}
  `;
  let settled = 0;
  for (const row of rows) if ((await reconcile(row)) === "submitted") settled++;
  return { checked: rows.length, settled };
}

// ---------------------------------------------------------------------------
// Webhooks

export type WebhookPayload = {
  event: string;
  data: {
    order_no?: string;
    website_order_id?: string | null;
    status?: string;
    previous_status?: string;
    dn_no?: string;
    delivered_at?: string;
  };
  sent_at?: string;
};

/**
 * Applies a verified webhook to the matching order with at most two quick
 * queries. Out-of-order deliveries are handled by only applying a status
 * whose `sent_at` is not older than the last status we recorded.
 */
export async function applyWebhook(rawBody: string, payload: WebhookPayload) {
  const sql = db();
  const d = payload.data ?? {};
  const sentAt = payload.sent_at && !Number.isNaN(Date.parse(payload.sent_at)) ? new Date(payload.sent_at) : new Date();
  const websiteOrderId = d.website_order_id || null;
  const orderNo = d.order_no || null;
  const bodyHash = createHash("sha256").update(rawBody).digest("hex");

  const inserted = await sql`
    INSERT INTO order_events ${sql({
      body_sha256: bodyHash,
      event: payload.event,
      website_order_id: websiteOrderId,
      erp_order_no: orderNo,
      payload: sql.json(payload as never),
      sent_at: sentAt,
    } as Record<string, unknown>)}
    ON CONFLICT (body_sha256) DO NOTHING
    RETURNING id
  `;
  if (inserted.length === 0) return { duplicate: true, matched: false };

  const match = websiteOrderId
    ? sql`website_order_id = ${websiteOrderId}`
    : orderNo
      ? sql`erp_order_no = ${orderNo}`
      : sql`false`;

  let result;
  if (payload.event === "order.status_changed") {
    const status = asStatus(d.status);
    if (!status) return { duplicate: false, matched: false, ignored: "unknown status" };
    result = await sql`
      UPDATE orders SET
        status = CASE WHEN status_updated_at IS NULL OR status_updated_at <= ${sentAt} THEN ${status} ELSE status END,
        status_updated_at = GREATEST(status_updated_at, ${sentAt}),
        erp_order_no = COALESCE(erp_order_no, ${orderNo}),
        submit_state = 'submitted',
        last_webhook_at = now(),
        updated_at = now()
      WHERE ${match}
    `;
  } else if (payload.event === "order.delivered") {
    const deliveredAt = d.delivered_at && !Number.isNaN(Date.parse(d.delivered_at)) ? new Date(d.delivered_at) : sentAt;
    result = await sql`
      UPDATE orders SET
        delivered_at = ${deliveredAt},
        delivery_note = ${sql.json({ dn_no: d.dn_no ?? "", status: "delivered" })},
        erp_order_no = COALESCE(erp_order_no, ${orderNo}),
        submit_state = 'submitted',
        last_webhook_at = now(),
        updated_at = now()
      WHERE ${match}
    `;
  } else {
    return { duplicate: false, matched: false, ignored: "unknown event" };
  }

  const matched = result.count > 0;
  if (matched) await sql`UPDATE order_events SET matched = true WHERE id = ${inserted[0].id}`;
  return { duplicate: false, matched };
}
