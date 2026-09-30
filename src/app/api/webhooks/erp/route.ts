import { NextResponse } from "next/server";
import { env } from "@/lib/server/env";
import { applyWebhook, type WebhookPayload } from "@/lib/server/orders";
import { verifyWebhookSignature } from "@/lib/server/signature";

export const runtime = "nodejs";
export const dynamic = "force-dynamic";

const MAX_BODY_BYTES = 64 * 1024;

/**
 * ERP → storefront webhook. Configure the ERP's WEBSITE_WEBHOOK_URL to
 * https://<your-domain>/api/webhooks/erp
 *
 * The ERP does not retry, so this does the minimum: verify the HMAC over the
 * raw body, record the event, update one order row, and return.
 */
export async function POST(req: Request) {
  const rawBody = await req.text();
  if (Buffer.byteLength(rawBody) > MAX_BODY_BYTES) {
    return NextResponse.json({ error: "payload too large" }, { status: 413 });
  }

  if (!verifyWebhookSignature(rawBody, req.headers.get("x-webhook-signature"), env.erpApiKey)) {
    return NextResponse.json({ error: "invalid signature" }, { status: 401 });
  }

  let payload: WebhookPayload;
  try {
    payload = JSON.parse(rawBody);
  } catch {
    return NextResponse.json({ error: "invalid JSON" }, { status: 400 });
  }
  if (!payload || typeof payload.event !== "string" || typeof payload.data !== "object" || payload.data === null) {
    return NextResponse.json({ error: "malformed payload" }, { status: 400 });
  }

  try {
    const result = await applyWebhook(rawBody, payload);
    return NextResponse.json({ ok: true, ...result });
  } catch (err) {
    console.error("[webhook] failed to apply ERP webhook", err);
    return NextResponse.json({ error: "internal error" }, { status: 500 });
  }
}
