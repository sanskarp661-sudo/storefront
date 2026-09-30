import "server-only";
import { after } from "next/server";
import { db } from "./db";
import { listCategories, listProducts, type ErpProduct } from "./erp";

const SYNC_KEY = "catalog";
/** How stale the local catalog may get before a page view triggers a background refresh. */
const INCREMENTAL_INTERVAL_MS = 5 * 60 * 1000;
/** How often a full (reconciling) sync runs instead of an incremental one. */
const FULL_INTERVAL_MS = 6 * 60 * 60 * 1000;
const LEASE_SECONDS = 300;
const PER_PAGE = 200;
const MAX_PAGES = 1000;

type SyncStateRow = {
  cursor: string | null;
  last_run_at: Date | null;
  last_full_at: Date | null;
  last_error: string | null;
};

export type SyncResult =
  | { skipped: true; reason: string }
  | { skipped: false; mode: "full" | "incremental"; products: number; deactivated: number; categories: number; cursor: string | null };

function toNumber(v: number | string | null | undefined, fallback = 0): number {
  const n = typeof v === "number" ? v : Number(v);
  return Number.isFinite(n) ? n : fallback;
}

function toRow(p: ErpProduct, syncedAt: Date) {
  return {
    erp_id: p.id,
    sku: p.sku,
    name: p.name,
    description: p.description ?? null,
    category: p.category ?? null,
    brand: p.brand ?? null,
    image_url: p.image_url ?? null,
    unit: p.unit ?? null,
    price: toNumber(p.price),
    currency: p.currency || "INR",
    quantity_on_hand: toNumber(p.quantity_on_hand),
    available_quantity: Math.max(0, toNumber(p.available_quantity)),
    status: p.status || "active",
    erp_updated_at: p.updated_at ? new Date(p.updated_at) : null,
    synced_at: syncedAt,
  };
}

/** Upserts ERP product rows into the local mirror. Also used by checkout's live stock check. */
export async function upsertProducts(products: ErpProduct[], syncedAt = new Date()) {
  if (products.length === 0) return;
  const sql = db();
  const rows = products.map((p) => toRow(p, syncedAt));
  await sql`
    INSERT INTO products ${sql(rows)}
    ON CONFLICT (erp_id) DO UPDATE SET
      sku = excluded.sku,
      name = excluded.name,
      description = excluded.description,
      category = excluded.category,
      brand = excluded.brand,
      image_url = excluded.image_url,
      unit = excluded.unit,
      price = excluded.price,
      currency = excluded.currency,
      quantity_on_hand = excluded.quantity_on_hand,
      available_quantity = excluded.available_quantity,
      status = excluded.status,
      erp_updated_at = excluded.erp_updated_at,
      synced_at = excluded.synced_at
  `;
}

async function acquireLease(): Promise<SyncStateRow | null> {
  const sql = db();
  const rows = await sql<SyncStateRow[]>`
    INSERT INTO sync_state (key, lease_until)
    VALUES (${SYNC_KEY}, now() + make_interval(secs => ${LEASE_SECONDS}))
    ON CONFLICT (key) DO UPDATE SET lease_until = excluded.lease_until
      WHERE sync_state.lease_until IS NULL OR sync_state.lease_until < now()
    RETURNING cursor, last_run_at, last_full_at, last_error
  `;
  return rows[0] ?? null;
}

async function syncCategories(): Promise<number> {
  const sql = db();
  const categories = await listCategories();
  await sql.begin(async (tx) => {
    if (categories.length > 0) {
      await tx`
        INSERT INTO categories ${tx(categories.map((c) => ({ erp_id: c.id, name: c.name, synced_at: new Date() })))}
        ON CONFLICT (erp_id) DO UPDATE SET name = excluded.name, synced_at = excluded.synced_at
      `;
    }
    const ids = categories.map((c) => c.id);
    await tx`DELETE FROM categories WHERE NOT (erp_id = ANY(${ids}::int[]))`;
  });
  return categories.length;
}

/**
 * Pulls the catalog from the ERP into Postgres.
 *
 * - "incremental" uses `?since=<max updated_at seen so far>` (the ERP's own
 *   clock, so there is no skew between the two systems).
 * - "full" walks every page and then marks anything the ERP no longer returns
 *   as inactive, which catches deletions/deactivations that an incremental
 *   fetch cannot see.
 * - "auto" picks full every FULL_INTERVAL_MS, incremental otherwise.
 *
 * A lease row in sync_state prevents overlapping runs across serverless instances.
 */
export async function syncCatalog(mode: "auto" | "full" | "incremental" = "auto"): Promise<SyncResult> {
  const state = await acquireLease();
  if (!state) return { skipped: true, reason: "another sync is in progress" };

  const sql = db();
  const runStartedAt = new Date();
  const full =
    mode === "full" ||
    (mode === "auto" &&
      (!state.last_full_at || runStartedAt.getTime() - state.last_full_at.getTime() > FULL_INTERVAL_MS)) ||
    !state.cursor;

  try {
    const since = full ? undefined : (state.cursor ?? undefined);
    let cursor = state.cursor;
    let count = 0;

    for (let page = 1; page <= MAX_PAGES; page++) {
      const res = await listProducts({ page, perPage: PER_PAGE, since });
      await upsertProducts(res.products, runStartedAt);
      count += res.products.length;
      for (const p of res.products) {
        if (p.updated_at && (!cursor || new Date(p.updated_at) > new Date(cursor))) cursor = p.updated_at;
      }
      if (!res.has_more || res.products.length === 0) break;
    }

    let deactivated = 0;
    if (full) {
      const result = await sql`
        UPDATE products SET status = 'inactive'
        WHERE synced_at < ${runStartedAt} AND status <> 'inactive'
      `;
      deactivated = result.count;
    }

    const categories = await syncCategories();

    await sql`
      UPDATE sync_state SET
        cursor = ${cursor},
        last_run_at = ${runStartedAt},
        last_full_at = ${full ? runStartedAt : state.last_full_at},
        last_error = NULL,
        lease_until = NULL
      WHERE key = ${SYNC_KEY}
    `;
    return { skipped: false, mode: full ? "full" : "incremental", products: count, deactivated, categories, cursor };
  } catch (err) {
    const message = err instanceof Error ? err.message : String(err);
    await sql`UPDATE sync_state SET last_error = ${message}, lease_until = NULL WHERE key = ${SYNC_KEY}`.catch(() => {});
    throw err;
  }
}

/**
 * Called by catalog pages. The very first request on an empty database waits
 * for a full sync; after that, stale data is served immediately and refreshed
 * in the background (stale-while-revalidate), so page loads never block on the ERP.
 */
export async function ensureFreshCatalog(): Promise<void> {
  const sql = db();
  const [state] = await sql<{ last_run_at: Date | null }[]>`
    SELECT last_run_at FROM sync_state WHERE key = ${SYNC_KEY}
  `;

  if (!state?.last_run_at) {
    try {
      await syncCatalog("full");
    } catch (err) {
      console.error("[sync] initial catalog sync failed", err);
    }
    return;
  }

  if (Date.now() - state.last_run_at.getTime() > INCREMENTAL_INTERVAL_MS) {
    after(() =>
      syncCatalog("auto").catch((err) => {
        console.error("[sync] background catalog sync failed", err);
      }),
    );
  }
}
