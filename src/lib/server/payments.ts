import "server-only";
import { isPaymentMethodId, PAYMENT_METHODS, type PaymentMethod } from "../payments";

/**
 * Payment methods offered at checkout, from PAYMENT_METHODS (comma-separated ids,
 * default "cod"). Unknown ids are ignored; if nothing valid remains, COD is used.
 */
export function enabledPaymentMethods(): PaymentMethod[] {
  const ids = (process.env.PAYMENT_METHODS || "cod")
    .split(",")
    .map((s) => s.trim().toLowerCase())
    .filter(Boolean);
  const methods = [...new Set(ids)].filter(isPaymentMethodId).map((id) => PAYMENT_METHODS[id]);
  return methods.length > 0 ? methods : [PAYMENT_METHODS.cod];
}
