"use client";

import Link from "next/link";
import { startTransition, useActionState, useEffect, useState } from "react";
import { AlertCircle, Banknote, Loader2, Lock } from "lucide-react";
import { useCart } from "@/components/cart-provider";
import { ProductImage } from "@/components/product-image";
import { formatPrice } from "@/lib/format";
import { INDIAN_STATES } from "@/lib/india";
import type { PaymentMethod } from "@/lib/payments";
import { placeOrderAction, type CheckoutState } from "./actions";

const KEY_STORAGE = "storefront.checkoutKey";

function newKey() {
  const key = crypto.randomUUID();
  try {
    sessionStorage.setItem(KEY_STORAGE, key);
  } catch {}
  return key;
}

/** Reuse the key across reloads so a retry after a dropped connection can't create a second order. */
function currentKey() {
  try {
    return sessionStorage.getItem(KEY_STORAGE) || newKey();
  } catch {
    return newKey();
  }
}

export function CheckoutForm({ paymentMethods }: { paymentMethods: PaymentMethod[] }) {
  const { lines, ready, subtotal, setQuantity, remove } = useCart();
  const [checkoutKey, setCheckoutKey] = useState("");
  const [state, formAction, pending] = useActionState<CheckoutState, FormData>(async (prev, formData) => {
    const next = await placeOrderAction(prev, formData);
    // Nothing was created in the ERP: the next attempt gets a fresh idempotency key.
    if (next.rotateKey) setCheckoutKey(newKey());
    // Apply stock corrections reported by the server to the cart.
    for (const issue of next.itemIssues ?? []) {
      if (!issue.available) remove(issue.sku);
      else setQuantity(issue.sku, issue.available);
    }
    return next;
  }, {});

  // eslint-disable-next-line react-hooks/set-state-in-effect -- sessionStorage is only available after mount
  useEffect(() => setCheckoutKey(currentKey()), []);

  if (!ready) return <div className="mt-8 h-96 animate-pulse rounded-3xl bg-black/5" />;

  if (lines.length === 0) {
    return (
      <div className="mt-8 rounded-3xl border border-dashed border-line bg-white p-14 text-center">
        <p className="text-lg font-medium">Your cart is empty</p>
        <Link href="/products" className="btn btn-primary mt-6">Browse products</Link>
      </div>
    );
  }

  const v = state.values ?? {};
  const fe = state.fieldErrors ?? {};
  const currency = lines[0]?.currency ?? "INR";
  const items = JSON.stringify(lines.map((l) => ({ sku: l.sku, quantity: l.quantity })));

  return (
    <form
      action={formAction}
      onSubmit={(e) => {
        // Submit manually so React doesn't reset the form afterwards: on an error the
        // customer keeps everything they typed (the `action` stays as the no-JS fallback).
        e.preventDefault();
        const formData = new FormData(e.currentTarget);
        startTransition(() => formAction(formData));
      }}
      className="mt-8 grid gap-10 lg:grid-cols-[1fr_400px]"
      noValidate
    >
      <input type="hidden" name="checkoutKey" value={checkoutKey} />
      <input type="hidden" name="items" value={items} />
      <div className="absolute -left-[9999px]" aria-hidden>
        <label>
          Website <input type="text" name="website" tabIndex={-1} autoComplete="off" />
        </label>
      </div>

      <div className="space-y-8">
        {state.error && (
          <div role="alert" className="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <p className="flex gap-2 font-medium">
              <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" /> {state.error}
            </p>
            {state.itemIssues && state.itemIssues.length > 0 && (
              <ul className="ml-6 mt-2 list-disc space-y-0.5">
                {state.itemIssues.map((i) => (
                  <li key={i.sku}>
                    {i.message} {i.available ? "Your cart has been updated." : "It was removed from your cart."}
                  </li>
                ))}
              </ul>
            )}
          </div>
        )}

        <fieldset className="rounded-3xl border border-line bg-white p-6">
          <legend className="px-2 text-lg font-semibold">Contact details</legend>
          <div className="mt-2 grid gap-4 sm:grid-cols-2">
            <Field label="Full name" name="name" error={fe.name} defaultValue={v.name} autoComplete="name" required className="sm:col-span-2" />
            <Field label="Email" name="email" type="email" error={fe.email} defaultValue={v.email} autoComplete="email" required hint="We'll use this to look up your order." />
            <Field label="Mobile number" name="phone" type="tel" error={fe.phone} defaultValue={v.phone} autoComplete="tel-national" inputMode="tel" required placeholder="10-digit mobile" />
            <Field label="Company (optional)" name="company" error={fe.company} defaultValue={v.company} autoComplete="organization" className="sm:col-span-2" />
          </div>
        </fieldset>

        <fieldset className="rounded-3xl border border-line bg-white p-6">
          <legend className="px-2 text-lg font-semibold">Shipping address</legend>
          <div className="mt-2 grid gap-4 sm:grid-cols-2">
            <div className="sm:col-span-2">
              <span className="field-label">Address type</span>
              <div className="flex gap-2">
                {["Home", "Office", "Other"].map((label) => (
                  <label key={label} className="cursor-pointer">
                    <input type="radio" name="addressLabel" value={label} defaultChecked={(v.addressLabel || "Home") === label} className="peer sr-only" />
                    <span className="inline-block rounded-full border border-line px-4 py-1.5 text-sm peer-checked:border-ink peer-checked:bg-ink peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-ink/30">
                      {label}
                    </span>
                  </label>
                ))}
              </div>
            </div>
            <Field label="Street address" name="addressLine" error={fe.addressLine} defaultValue={v.addressLine} autoComplete="street-address" required className="sm:col-span-2" placeholder="House / flat no., building, street, area" />
            <Field label="City" name="city" error={fe.city} defaultValue={v.city} autoComplete="address-level2" required />
            <Field label="PIN code" name="pincode" error={fe.pincode} defaultValue={v.pincode} autoComplete="postal-code" inputMode="numeric" maxLength={6} required />
            <div>
              <label className="field-label" htmlFor="state">State</label>
              <select id="state" name="state" defaultValue={v.state || ""} required aria-invalid={!!fe.state} className="field-input">
                <option value="" disabled>Select state</option>
                {INDIAN_STATES.map((s) => (
                  <option key={s} value={s}>{s}</option>
                ))}
              </select>
              {fe.state && <p className="mt-1 text-xs text-red-600">{fe.state}</p>}
            </div>
            <div>
              <span className="field-label">Country</span>
              <div className="field-input bg-stone-50 text-ink-soft">India</div>
            </div>
          </div>
        </fieldset>

        <fieldset className="rounded-3xl border border-line bg-white p-6">
          <legend className="px-2 text-lg font-semibold">Payment</legend>
          <div className="mt-2 grid gap-3">
            {paymentMethods.map((m, i) => (
              <label key={m.id} className="cursor-pointer">
                <input
                  type="radio"
                  name="paymentMethod"
                  value={m.id}
                  defaultChecked={v.paymentMethod ? v.paymentMethod === m.id : i === 0}
                  className="peer sr-only"
                />
                <span className="flex items-start gap-3 rounded-2xl border border-line p-4 peer-checked:border-ink peer-checked:ring-1 peer-checked:ring-ink peer-focus-visible:ring-2 peer-focus-visible:ring-ink/30">
                  <Banknote className="mt-0.5 h-5 w-5 shrink-0 text-accent" />
                  <span>
                    <span className="block font-medium">{m.label}</span>
                    <span className="block text-sm text-ink-soft">{m.description}</span>
                  </span>
                </span>
              </label>
            ))}
            {fe.paymentMethod && <p className="text-xs text-red-600">{fe.paymentMethod}</p>}
          </div>
        </fieldset>

        <fieldset className="rounded-3xl border border-line bg-white p-6">
          <legend className="px-2 text-lg font-semibold">Order notes</legend>
          <textarea
            name="notes"
            rows={3}
            maxLength={500}
            defaultValue={v.notes}
            placeholder="Delivery instructions, preferred time, etc. (optional)"
            className="field-input mt-2 resize-y"
          />
        </fieldset>
      </div>

      <aside className="h-fit rounded-3xl border border-line bg-white p-6 lg:sticky lg:top-24">
        <h2 className="text-lg font-semibold">Your order</h2>
        <ul className="mt-5 space-y-4">
          {lines.map((l) => (
            <li key={l.sku} className="flex items-center gap-3">
              <div className="relative w-14 shrink-0 overflow-hidden rounded-lg border border-line">
                <ProductImage src={l.imageUrl} alt={l.name} sizes="56px" />
              </div>
              <div className="min-w-0 flex-1">
                <p className="line-clamp-1 text-sm font-medium">{l.name}</p>
                <p className="text-xs text-ink-soft">Qty {l.quantity} × {formatPrice(l.price, l.currency)}</p>
              </div>
              <p className="text-sm font-medium">{formatPrice(l.price * l.quantity, l.currency)}</p>
            </li>
          ))}
        </ul>
        <dl className="mt-6 space-y-2 border-t border-line pt-5 text-sm">
          <div className="flex justify-between">
            <dt className="text-ink-soft">Subtotal</dt>
            <dd>{formatPrice(subtotal, currency)}</dd>
          </div>
          <div className="flex justify-between">
            <dt className="text-ink-soft">GST</dt>
            <dd className="text-ink-soft">Confirmed on invoice</dd>
          </div>
        </dl>
        <div className="mt-4 flex justify-between border-t border-line pt-4 font-semibold">
          <span>Total (excl. GST)</span>
          <span>{formatPrice(subtotal, currency)}</span>
        </div>
        <button type="submit" className="btn btn-accent mt-6 w-full" disabled={pending || !checkoutKey}>
          {pending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Lock className="h-4 w-4" />}
          {pending ? "Placing your order…" : "Place order"}
        </button>
        <p className="mt-3 text-xs leading-relaxed text-ink-soft">
          Prices are confirmed by our system when your order is placed. Applicable GST is added on your invoice.
          {paymentMethods.length === 1 && paymentMethods[0].id === "cod" && " You pay in cash when your order is delivered."}
        </p>
      </aside>
    </form>
  );
}

type FieldProps = React.InputHTMLAttributes<HTMLInputElement> & {
  label: string;
  name: string;
  error?: string;
  hint?: string;
};

function Field({ label, name, error, hint, className, ...rest }: FieldProps) {
  return (
    <div className={className}>
      <label className="field-label" htmlFor={name}>{label}</label>
      <input id={name} name={name} aria-invalid={!!error} aria-describedby={error ? `${name}-error` : undefined} className="field-input" {...rest} />
      {error ? (
        <p id={`${name}-error`} className="mt-1 text-xs text-red-600">{error}</p>
      ) : hint ? (
        <p className="mt-1 text-xs text-ink-soft">{hint}</p>
      ) : null}
    </div>
  );
}
