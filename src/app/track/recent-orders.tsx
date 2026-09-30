"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { ChevronRight } from "lucide-react";
import { recentOrders, type RecentOrder } from "@/components/cart-provider";
import { formatDate } from "@/lib/format";

export function RecentOrders() {
  const [orders, setOrders] = useState<RecentOrder[]>([]);
  // eslint-disable-next-line react-hooks/set-state-in-effect -- localStorage is only available after mount
  useEffect(() => setOrders(recentOrders()), []);
  if (orders.length === 0) return null;

  return (
    <section className="mt-10">
      <h2 className="mb-3 font-semibold">Orders placed on this device</h2>
      <ul className="divide-y divide-line rounded-3xl border border-line bg-white">
        {orders.map((o) => (
          <li key={o.websiteOrderId}>
            <Link
              href={`/orders/${encodeURIComponent(o.websiteOrderId)}?key=${encodeURIComponent(o.key)}`}
              className="flex items-center justify-between px-5 py-4 text-sm hover:bg-black/[0.02]"
            >
              <span className="font-mono">{o.websiteOrderId}</span>
              <span className="flex items-center gap-2 text-ink-soft">
                {formatDate(o.placedAt)} <ChevronRight className="h-4 w-4" />
              </span>
            </Link>
          </li>
        ))}
      </ul>
    </section>
  );
}
