// Payment method catalogue, shared by server and client (no secrets here).
//
// To add online payments later:
//   1. Add an entry below with `kind: "online"`.
//   2. Implement its flow (create a gateway order, redirect, verify the callback)
//      and hook it in where `placeOrder` handles `kind`.
//   3. Enable it with PAYMENT_METHODS, e.g. PAYMENT_METHODS=cod,razorpay
// Order of PAYMENT_METHODS is the order shown at checkout; the first is the default.

export type PaymentMethodId = "cod";

export type PaymentMethod = {
  id: PaymentMethodId;
  kind: "offline" | "online";
  label: string;
  description: string;
  /** Written into the ERP order's notes, since the ERP API has no payment field. */
  erpNote: string;
  /** Shown on the order page once placed. */
  orderPageNote: string;
};

export const PAYMENT_METHODS: Record<PaymentMethodId, PaymentMethod> = {
  cod: {
    id: "cod",
    kind: "offline",
    label: "Cash on Delivery",
    description: "Pay in cash when your order is delivered.",
    erpNote: "Payment: Cash on Delivery (COD)",
    orderPageNote: "Pay in cash when your order is delivered. The final amount due is shown on your invoice.",
  },
};

export function isPaymentMethodId(id: string): id is PaymentMethodId {
  return Object.hasOwn(PAYMENT_METHODS, id);
}

export function paymentMethod(id: string | null | undefined): PaymentMethod | null {
  return id && isPaymentMethodId(id) ? PAYMENT_METHODS[id] : null;
}
