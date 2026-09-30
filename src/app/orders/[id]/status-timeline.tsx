import { Check, X } from "lucide-react";
import type { OrderStatus } from "@/lib/types";

const STEPS: { key: OrderStatus | "delivered"; label: string; description: string }[] = [
  { key: "pending", label: "Order placed", description: "We've received your order." },
  { key: "confirmed", label: "Confirmed", description: "Your order is confirmed and being prepared." },
  { key: "shipped", label: "Shipped", description: "Your order is on its way." },
  { key: "delivered", label: "Delivered", description: "Your order has been delivered." },
];

export function StatusTimeline({ status, delivered }: { status: OrderStatus; delivered: boolean }) {
  if (status === "cancelled") {
    return (
      <div className="flex items-center gap-3 rounded-2xl bg-stone-100 p-4 text-sm">
        <span className="flex h-8 w-8 items-center justify-center rounded-full bg-stone-700 text-white">
          <X className="h-4 w-4" />
        </span>
        <div>
          <p className="font-medium">Order cancelled</p>
          <p className="text-ink-soft">This order has been cancelled. Contact us if you have questions.</p>
        </div>
      </div>
    );
  }

  const rank: Record<string, number> = { pending: 0, confirmed: 1, shipped: 2, completed: 3 };
  const reached = delivered ? 3 : (rank[status] ?? 0);

  return (
    <ol className="grid gap-6 sm:grid-cols-4 sm:gap-2">
      {STEPS.map((step, i) => {
        const done = i <= reached;
        const current = i === reached;
        return (
          <li key={step.key} className="relative flex gap-3 sm:flex-col">
            {i < STEPS.length - 1 && (
              <span
                aria-hidden
                className={`absolute left-4 top-8 h-[calc(100%-8px)] w-0.5 sm:left-8 sm:top-4 sm:h-0.5 sm:w-[calc(100%-16px)] ${
                  i < reached ? "bg-ink" : "bg-line"
                }`}
              />
            )}
            <span
              className={`relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full border-2 ${
                done ? "border-ink bg-ink text-white" : "border-line bg-white text-ink-soft"
              } ${current ? "ring-4 ring-ink/10" : ""}`}
            >
              {done ? <Check className="h-4 w-4" /> : <span className="text-xs">{i + 1}</span>}
            </span>
            <div className="text-sm">
              <p className={`font-medium ${done ? "" : "text-ink-soft"}`}>{step.label}</p>
              {current && <p className="mt-0.5 text-ink-soft">{step.description}</p>}
            </div>
          </li>
        );
      })}
    </ol>
  );
}
