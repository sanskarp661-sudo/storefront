import { formatQuantity } from "@/lib/format";

const LOW_STOCK = 5;

export function StockBadge({ available, compact }: { available: number; compact?: boolean }) {
  if (available <= 0) {
    return <span className="rounded-full bg-stone-200 px-2 py-0.5 text-xs font-medium text-stone-600">Out of stock</span>;
  }
  if (available <= LOW_STOCK) {
    return (
      <span className="rounded-full bg-accent-soft px-2 py-0.5 text-xs font-medium text-accent-dark">
        {compact ? `${formatQuantity(available)} left` : `Only ${formatQuantity(available)} left`}
      </span>
    );
  }
  return compact ? null : (
    <span className="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">In stock</span>
  );
}
