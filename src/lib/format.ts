export function formatPrice(amount: number, currency = "INR"): string {
  try {
    return new Intl.NumberFormat("en-IN", { style: "currency", currency, maximumFractionDigits: 2 }).format(amount);
  } catch {
    return `${currency} ${amount.toFixed(2)}`;
  }
}

export function formatQuantity(q: number): string {
  return Number.isInteger(q) ? String(q) : q.toFixed(2).replace(/\.?0+$/, "");
}

export function productHref(sku: string): string {
  return `/products/${encodeURIComponent(sku)}`;
}

export function categoryHref(category: string): string {
  return `/products?category=${encodeURIComponent(category)}`;
}

export function formatDate(iso: string | null | undefined): string {
  if (!iso) return "";
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return iso;
  return d.toLocaleDateString("en-IN", { day: "numeric", month: "short", year: "numeric" });
}
