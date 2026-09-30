"use client";

import Link from "next/link";
import { useState } from "react";
import { Check, ShoppingBag } from "lucide-react";
import { useCart, type CartLine } from "./cart-provider";
import { QuantityStepper } from "./quantity-stepper";

export function AddToCart({ product }: { product: Omit<CartLine, "quantity"> }) {
  const { add, lines } = useCart();
  const [quantity, setQuantity] = useState(1);
  const [added, setAdded] = useState(false);
  const inCart = lines.find((l) => l.sku === product.sku)?.quantity ?? 0;
  const remaining = Math.max(0, product.maxQuantity - inCart);

  if (product.maxQuantity <= 0) {
    return (
      <button type="button" className="btn btn-outline w-full sm:w-auto" disabled>
        Out of stock
      </button>
    );
  }

  return (
    <div className="space-y-3">
      <div className="flex flex-wrap items-center gap-3">
        <QuantityStepper value={Math.min(quantity, Math.max(remaining, 1))} max={Math.max(remaining, 1)} onChange={setQuantity} />
        <button
          type="button"
          className="btn btn-primary h-12 flex-1 sm:flex-none sm:px-10"
          disabled={remaining <= 0}
          onClick={() => {
            add(product, Math.min(quantity, remaining));
            setQuantity(1);
            setAdded(true);
            setTimeout(() => setAdded(false), 2500);
          }}
        >
          {added ? <Check className="h-4 w-4" /> : <ShoppingBag className="h-4 w-4" />}
          {added ? "Added to cart" : remaining <= 0 ? "Max quantity in cart" : "Add to cart"}
        </button>
      </div>
      {inCart > 0 && (
        <p className="text-sm text-ink-soft">
          {inCart} in your cart ·{" "}
          <Link href="/cart" className="font-medium text-ink underline underline-offset-4">
            View cart
          </Link>
        </p>
      )}
    </div>
  );
}
