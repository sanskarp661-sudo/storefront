"use client";

import { useEffect } from "react";
import { rememberOrder, useCart } from "@/components/cart-provider";

/** After a successful checkout: empty the cart, forget the checkout key, remember the order on this device. */
export function OrderPlaced({ websiteOrderId, accessKey }: { websiteOrderId: string; accessKey: string }) {
  const { clear, ready } = useCart();
  useEffect(() => {
    if (!ready) return;
    clear();
    try {
      sessionStorage.removeItem("storefront.checkoutKey");
    } catch {}
    rememberOrder({ websiteOrderId, key: accessKey, placedAt: new Date().toISOString() });
  }, [ready, clear, websiteOrderId, accessKey]);
  return null;
}
