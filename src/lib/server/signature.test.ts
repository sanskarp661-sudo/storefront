import { createHmac } from "node:crypto";
import { describe, expect, it } from "vitest";
import { verifyWebhookSignature } from "./signature";

const secret = "test-secret";
const body = '{"event":"order.status_changed","data":{"order_no":"SO-000123","website_order_id":"WEB-1","status":"confirmed","previous_status":"pending"},"sent_at":"2026-09-30T12:00:00+00:00"}';
const sign = (b: string, s = secret) => createHmac("sha256", s).update(b).digest("hex");

describe("verifyWebhookSignature", () => {
  it("accepts a correct signature", () => {
    expect(verifyWebhookSignature(body, sign(body), secret)).toBe(true);
  });
  it("accepts uppercase hex and a sha256= prefix", () => {
    expect(verifyWebhookSignature(body, sign(body).toUpperCase(), secret)).toBe(true);
    expect(verifyWebhookSignature(body, `sha256=${sign(body)}`, secret)).toBe(true);
  });
  it("rejects a tampered body", () => {
    expect(verifyWebhookSignature(body.replace("confirmed", "completed"), sign(body), secret)).toBe(false);
  });
  it("rejects a signature made with another key", () => {
    expect(verifyWebhookSignature(body, sign(body, "other"), secret)).toBe(false);
  });
  it("rejects missing, malformed or truncated signatures", () => {
    expect(verifyWebhookSignature(body, null, secret)).toBe(false);
    expect(verifyWebhookSignature(body, "", secret)).toBe(false);
    expect(verifyWebhookSignature(body, "not-hex", secret)).toBe(false);
    expect(verifyWebhookSignature(body, sign(body).slice(0, 40), secret)).toBe(false);
  });
  it("rejects when no secret is configured", () => {
    expect(verifyWebhookSignature(body, sign(body, ""), "")).toBe(false);
  });
});
