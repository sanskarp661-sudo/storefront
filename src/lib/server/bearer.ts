import "server-only";
import { timingSafeEqual } from "node:crypto";

/** Constant-time check of an `Authorization: Bearer <secret>` header. Fails closed if no secret is configured. */
export function verifyBearer(header: string | null, secret: string): boolean {
  if (!secret || !header?.startsWith("Bearer ")) return false;
  const provided = Buffer.from(header.slice(7));
  const expected = Buffer.from(secret);
  return provided.length === expected.length && timingSafeEqual(provided, expected);
}
