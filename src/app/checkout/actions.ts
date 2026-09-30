"use server";

import { redirect } from "next/navigation";
import { z } from "zod";
import type { PaymentMethodId } from "@/lib/payments";
import { INDIAN_STATES, normalizeIndianPhone } from "@/lib/india";
import { placeOrder, type ItemIssue } from "@/lib/server/orders";
import { enabledPaymentMethods } from "@/lib/server/payments";

export type CheckoutState = {
  error?: string;
  fieldErrors?: Record<string, string>;
  itemIssues?: ItemIssue[];
  rotateKey?: boolean;
  values?: Record<string, string>;
};

const optional = (max: number) =>
  z
    .string()
    .trim()
    .max(max)
    .transform((v) => (v === "" ? null : v));

const schema = z.object({
  checkoutKey: z.uuid(),
  name: z.string().trim().min(2, "Please enter your full name").max(120),
  email: z.string().trim().max(200).pipe(z.email("Please enter a valid email address")),
  phone: z
    .string()
    .transform((v) => normalizeIndianPhone(v))
    .refine((v): v is string => v !== null, "Please enter a valid 10-digit mobile number"),
  company: optional(120),
  addressLabel: z.enum(["Home", "Office", "Other"]).catch("Home"),
  addressLine: z.string().trim().min(5, "Please enter your street address").max(300),
  city: z.string().trim().min(2, "Please enter your city").max(80),
  state: z.enum(INDIAN_STATES, "Please choose your state"),
  pincode: z.string().trim().regex(/^[1-9][0-9]{5}$/, "Please enter a valid 6-digit PIN code"),
  notes: optional(500),
  paymentMethod: z
    .string()
    .refine((id) => enabledPaymentMethods().some((m) => m.id === id), "Please choose a payment method")
    .transform((id) => id as PaymentMethodId),
  items: z
    .string()
    .transform((s, ctx) => {
      try {
        return JSON.parse(s);
      } catch {
        ctx.addIssue({ code: "custom", message: "Invalid cart" });
        return z.NEVER;
      }
    })
    .pipe(
      z
        .array(z.object({ sku: z.string().min(1).max(100), quantity: z.number().int().min(1).max(10_000) }))
        .min(1, "Your cart is empty")
        .max(50, "Too many different items in one order"),
    ),
});

export async function placeOrderAction(_prev: CheckoutState, formData: FormData): Promise<CheckoutState> {
  const raw = Object.fromEntries([...formData.entries()].map(([k, v]) => [k, typeof v === "string" ? v : ""]));
  const values = Object.fromEntries(Object.entries(raw).filter(([k]) => k !== "items" && k !== "checkoutKey"));

  // Honeypot: real customers never see or fill this field.
  if (raw.website) return { error: "Something went wrong. Please try again.", values };

  const parsed = schema.safeParse(raw);
  if (!parsed.success) {
    const fieldErrors: Record<string, string> = {};
    for (const issue of parsed.error.issues) {
      const key = String(issue.path[0] ?? "form");
      fieldErrors[key] ??= issue.message;
    }
    const cartError = fieldErrors.items || fieldErrors.checkoutKey;
    return {
      error: cartError ? `${cartError}. Please refresh the page and try again.` : "Please fix the highlighted fields.",
      fieldErrors,
      values,
    };
  }

  const d = parsed.data;
  let result;
  try {
    result = await placeOrder({
      checkoutKey: d.checkoutKey,
      customer: { name: d.name, email: d.email, phone: d.phone, company: d.company },
      shippingAddress: {
        label: d.addressLabel,
        address_line: d.addressLine,
        city: d.city,
        state: d.state,
        pincode: d.pincode,
        country: "India",
        contact_person: d.name,
        contact_phone: d.phone,
        contact_email: d.email,
      },
      items: d.items,
      notes: d.notes,
      paymentMethod: d.paymentMethod,
    });
  } catch (err) {
    console.error("[checkout] placeOrder failed", err);
    return { error: "Something went wrong while placing your order. Please try again.", values };
  }

  if (!result.ok) {
    return { error: result.error, itemIssues: result.itemIssues, rotateKey: result.rotateKey, values };
  }

  redirect(`/orders/${encodeURIComponent(result.websiteOrderId)}?key=${encodeURIComponent(result.accessToken)}&placed=1`);
}
