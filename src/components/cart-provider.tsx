"use client";

import { createContext, useCallback, useContext, useEffect, useMemo, useState } from "react";

export type CartLine = {
  sku: string;
  name: string;
  price: number;
  currency: string;
  imageUrl: string | null;
  unit: string | null;
  quantity: number;
  /** Last known available quantity, used to cap the quantity picker. */
  maxQuantity: number;
};

type CartContextValue = {
  lines: CartLine[];
  ready: boolean;
  count: number;
  subtotal: number;
  add: (line: Omit<CartLine, "quantity">, quantity?: number) => void;
  setQuantity: (sku: string, quantity: number) => void;
  remove: (sku: string) => void;
  replace: (lines: CartLine[]) => void;
  clear: () => void;
  lastAdded: { sku: string; at: number } | null;
};

const STORAGE_KEY = "storefront.cart.v1";
const CartContext = createContext<CartContextValue | null>(null);

function load(): CartLine[] {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    const parsed = raw ? JSON.parse(raw) : [];
    return Array.isArray(parsed) ? parsed.filter((l) => l && typeof l.sku === "string" && l.quantity > 0) : [];
  } catch {
    return [];
  }
}

export function CartProvider({ children }: { children: React.ReactNode }) {
  const [lines, setLines] = useState<CartLine[]>([]);
  const [ready, setReady] = useState(false);
  const [lastAdded, setLastAdded] = useState<{ sku: string; at: number } | null>(null);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- hydrate from localStorage after mount
    setLines(load());
    setReady(true);
    const onStorage = (e: StorageEvent) => {
      if (e.key === STORAGE_KEY) setLines(load());
    };
    window.addEventListener("storage", onStorage);
    return () => window.removeEventListener("storage", onStorage);
  }, []);

  useEffect(() => {
    if (!ready) return;
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(lines));
    } catch {
      // Storage unavailable (private mode); the cart still works for this tab.
    }
  }, [lines, ready]);

  const add = useCallback((line: Omit<CartLine, "quantity">, quantity = 1) => {
    setLines((prev) => {
      const existing = prev.find((l) => l.sku === line.sku);
      if (existing) {
        return prev.map((l) =>
          l.sku === line.sku ? { ...l, ...line, quantity: Math.min(l.quantity + quantity, line.maxQuantity) } : l,
        );
      }
      return [...prev, { ...line, quantity: Math.min(quantity, line.maxQuantity) }];
    });
    setLastAdded({ sku: line.sku, at: Date.now() });
  }, []);

  const setQuantity = useCallback((sku: string, quantity: number) => {
    setLines((prev) =>
      quantity <= 0
        ? prev.filter((l) => l.sku !== sku)
        : prev.map((l) => (l.sku === sku ? { ...l, quantity: Math.min(quantity, Math.max(l.maxQuantity, 1)) } : l)),
    );
  }, []);

  const remove = useCallback((sku: string) => setLines((prev) => prev.filter((l) => l.sku !== sku)), []);
  const replace = useCallback((next: CartLine[]) => setLines(next), []);
  const clear = useCallback(() => setLines([]), []);

  const value = useMemo<CartContextValue>(
    () => ({
      lines,
      ready,
      count: lines.reduce((n, l) => n + l.quantity, 0),
      subtotal: Math.round(lines.reduce((s, l) => s + l.price * l.quantity, 0) * 100) / 100,
      add,
      setQuantity,
      remove,
      replace,
      clear,
      lastAdded,
    }),
    [lines, ready, add, setQuantity, remove, replace, clear, lastAdded],
  );

  return <CartContext.Provider value={value}>{children}</CartContext.Provider>;
}

export function useCart() {
  const ctx = useContext(CartContext);
  if (!ctx) throw new Error("useCart must be used inside <CartProvider>");
  return ctx;
}

// Recently placed orders, remembered on this device so customers can get back to them.
const ORDERS_KEY = "storefront.orders.v1";
export type RecentOrder = { websiteOrderId: string; key: string; placedAt: string };

export function rememberOrder(order: RecentOrder) {
  try {
    const list: RecentOrder[] = JSON.parse(localStorage.getItem(ORDERS_KEY) || "[]");
    const next = [order, ...list.filter((o) => o.websiteOrderId !== order.websiteOrderId)].slice(0, 20);
    localStorage.setItem(ORDERS_KEY, JSON.stringify(next));
  } catch {}
}

export function recentOrders(): RecentOrder[] {
  try {
    const list = JSON.parse(localStorage.getItem(ORDERS_KEY) || "[]");
    return Array.isArray(list) ? list : [];
  } catch {
    return [];
  }
}
