import { createHmac, timingSafeEqual } from "node:crypto";

/**
 * Verifies the ERP's `X-Webhook-Signature` header, which is PHP's
 * `hash_hmac('sha256', $rawBody, $apiKey)` — a lowercase hex digest.
 * Must be given the exact raw request body bytes, not re-serialised JSON.
 */
export function verifyWebhookSignature(rawBody: string | Buffer, signature: string | null, secret: string): boolean {
  if (!signature || !secret) return false;
  const provided = signature.trim().replace(/^sha256=/i, "").toLowerCase();
  if (!/^[0-9a-f]{64}$/.test(provided)) return false;

  const expected = createHmac("sha256", secret).update(rawBody).digest();
  return timingSafeEqual(expected, Buffer.from(provided, "hex"));
}
