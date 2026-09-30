"use client";

import Link from "next/link";
import { useEffect, useRef, useState } from "react";
import { AlertTriangle, ArrowRight, ShoppingBag, Trash2 } from "lucide-react";
import { refreshCart } from "@/app/actions";
import { useCart } from "@/components/cart-provider";
import { ProductImage } from "@/components/product-image";
import { QuantityStepper } from "@/components/quantity-stepper";
import { formatPrice, formatQuantity, productHref } from "@/lib/format";

export function CartView() {
  const { lines, ready, subtotal, setQuantity, remove, replace } = useCart();
  const [notices, setNotices] = useState<string[]>([]);
  const refreshed = useRef(false);

  // Once per visit, reconcile the stored cart with the current catalog (prices, stock, discontinued items).
  useEffect(() => {
    if (!ready || refreshed.current || lines.length === 0) return;
    refreshed.current = true;
    refreshCart(lines.map((l) => l.sku))
      .then((fresh) => {
        const messages: string[] = [];
        const next = lines.flatMap((l) => {
          const p = fresh[l.sku];
          if (p === undefined) return [l];
          if (p === null) {
            messages.push(`${l.name} is no longer available and was removed.`);
            return [];
          }
          const max = Math.floor(p.availableQuantity);
          if (max <= 0) {
            messages.push(`${p.name} is now out of stock and was removed.`);
            return [];
          }
          const quantity = Math.min(l.quantity, max);
          if (quantity < l.quantity) messages.push(`Only ${formatQuantity(max)} of ${p.name} available — quantity updated.`);
          if (p.price !== l.price) messages.push(`The price of ${p.name} changed to ${formatPrice(p.price, p.currency)}.`);
          return [{ ...l, name: p.name, price: p.price, currency: p.currency, imageUrl: p.imageUrl, unit: p.unit, maxQuantity: max, quantity }];
        });
        setNotices(messages);
        replace(next);
      })
      .catch(() => {});
  }, [ready, lines, replace]);

  if (!ready) {
    return <div className="mt-8 h-40 animate-pulse rounded-2xl bg-black/5" />;
  }

  if (lines.length === 0) {
    return (
      <div className="mt-8 rounded-3xl border border-dashed border-line bg-white p-14 text-center">
        <ShoppingBag className="mx-auto h-10 w-10 text-ink-soft/50" strokeWidth={1.5} />
        <p className="mt-4 text-lg font-medium">Your cart is empty</p>
        <p className="mt-1 text-sm text-ink-soft">Find something you love in our catalogue.</p>
        <Link href="/products" className="btn btn-primary mt-6">Start shopping</Link>
      </div>
    );
  }

  const currency = lines[0]?.currency ?? "INR";

  return (
    <div className="mt-8 grid gap-10 lg:grid-cols-[1fr_360px]">
      <div>
        {notices.length > 0 && (
          <div className="mb-6 space-y-1 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            {notices.map((n) => (
              <p key={n} className="flex gap-2">
                <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" /> {n}
              </p>
            ))}
          </div>
        )}
        <ul className="divide-y divide-line border-y border-line">
          {lines.map((l) => (
            <li key={l.sku} className="flex gap-4 py-5">
              <Link href={productHref(l.sku)} className="w-24 shrink-0 overflow-hidden rounded-xl border border-line sm:w-28">
                <ProductImage src={l.imageUrl} alt={l.name} sizes="112px" />
              </Link>
              <div className="flex flex-1 flex-col gap-2">
                <div className="flex justify-between gap-4">
                  <div>
                    <Link href={productHref(l.sku)} className="font-medium hover:underline">{l.name}</Link>
                    <p className="text-sm text-ink-soft">
                      {formatPrice(l.price, l.currency)}
                      {l.unit && ` / ${l.unit}`}
                    </p>
                  </div>
                  <p className="font-semibold">{formatPrice(l.price * l.quantity, l.currency)}</p>
                </div>
                <div className="mt-auto flex items-center justify-between">
                  <QuantityStepper size="sm" value={l.quantity} max={Math.max(l.maxQuantity, 1)} onChange={(n) => setQuantity(l.sku, n)} />
                  <button
                    type="button"
                    onClick={() => remove(l.sku)}
                    className="inline-flex items-center gap-1.5 text-sm text-ink-soft hover:text-red-600"
                  >
                    <Trash2 className="h-4 w-4" /> Remove
                  </button>
                </div>
              </div>
            </li>
          ))}
        </ul>
        <Link href="/products" className="mt-6 inline-block text-sm font-medium text-ink-soft hover:text-ink">
          ← Continue shopping
        </Link>
      </div>

      <aside className="h-fit rounded-3xl border border-line bg-white p-6 lg:sticky lg:top-24">
        <h2 className="text-lg font-semibold">Order summary</h2>
        <dl className="mt-5 space-y-3 text-sm">
          <div className="flex justify-between">
            <dt className="text-ink-soft">Subtotal</dt>
            <dd className="font-medium">{formatPrice(subtotal, currency)}</dd>
          </div>
          <div className="flex justify-between">
            <dt className="text-ink-soft">GST</dt>
            <dd className="text-ink-soft">Confirmed on invoice</dd>
          </div>
        </dl>
        <div className="mt-5 flex justify-between border-t border-line pt-5 font-semibold">
          <span>Total (excl. GST)</span>
          <span>{formatPrice(subtotal, currency)}</span>
        </div>
        <Link href="/checkout" className="btn btn-accent mt-6 w-full">
          Checkout <ArrowRight className="h-4 w-4" />
        </Link>
        <p className="mt-3 text-center text-xs text-ink-soft">Final prices are confirmed when your order is placed.</p>
      </aside>
    </div>
  );
}
