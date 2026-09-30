import { NextResponse } from "next/server";
import { env } from "@/lib/server/env";
import { reconcileStuckOrders } from "@/lib/server/orders";
import { verifyBearer } from "@/lib/server/bearer";
import { syncCatalog } from "@/lib/server/sync";

export const runtime = "nodejs";
export const dynamic = "force-dynamic";
export const maxDuration = 300;

/**
 * Scheduled catalog sync + reconciliation of orders whose ERP submission
 * outcome was ambiguous. Vercel Cron calls this with
 * `Authorization: Bearer $CRON_SECRET`; any other scheduler can do the same.
 * `?mode=full` forces a full re-sync.
 */
export async function GET(req: Request) {
  if (!verifyBearer(req.headers.get("authorization"), env.cronSecret)) {
    return NextResponse.json({ error: "unauthorized" }, { status: 401 });
  }
  const modeParam = new URL(req.url).searchParams.get("mode");
  const mode = modeParam === "full" || modeParam === "incremental" ? modeParam : "auto";

  try {
    const catalog = await syncCatalog(mode);
    const orders = await reconcileStuckOrders();
    return NextResponse.json({ ok: true, catalog, orders });
  } catch (err) {
    console.error("[cron] sync failed", err);
    return NextResponse.json({ ok: false, error: err instanceof Error ? err.message : String(err) }, { status: 502 });
  }
}
