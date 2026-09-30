import type { Metadata } from "next";
import Link from "next/link";
import { AlertTriangle, CheckCircle2, Clock, FileText, Loader2, Truck } from "lucide-react";
import { ProductImage } from "@/components/product-image";
import { formatDate, formatPrice, productHref } from "@/lib/format";
import { getOrderForViewer } from "@/lib/server/orders";
import type { OrderView } from "@/lib/types";
import { OrderPlaced } from "./order-placed";
import { StatusTimeline } from "./status-timeline";

export const dynamic = "force-dynamic";
export const metadata: Metadata = { title: "Your order", robots: { index: false, follow: false } };

export default async function OrderPage({ params, searchParams }: PageProps<"/orders/[id]">) {
  const { id } = await params;
  const sp = await searchParams;
  const key = typeof sp.key === "string" ? sp.key : "";
  const placed = sp.placed === "1";
  const order = await getOrderForViewer(decodeURIComponent(id), key);

  if (!order) {
    return (
      <div className="container-page max-w-xl py-20 text-center">
        <h1 className="text-2xl font-semibold">We couldn&apos;t find that order</h1>
        <p className="mt-2 text-ink-soft">The link may be incomplete. You can look up your order with its number and your email.</p>
        <Link href="/track" className="btn btn-primary mt-6">Track an order</Link>
      </div>
    );
  }

  return (
    <div className="container-page max-w-5xl py-10">
      {placed && (order.submitState === "submitted" || order.submitState === "submitting") && (
        <OrderPlaced websiteOrderId={order.websiteOrderId} accessKey={key} />
      )}

      <Header order={order} placed={placed} />

      <div className="mt-8 grid gap-8 lg:grid-cols-[1fr_340px]">
        <div className="space-y-8">
          {order.submitState === "submitted" && (
            <section className="rounded-3xl border border-line bg-white p-6">
              <h2 className="mb-6 font-semibold">Order status</h2>
              <StatusTimeline status={order.status} delivered={!!order.deliveredAt || order.deliveryNote?.status === "delivered"} />
              {(order.deliveryNote || order.invoice) && (
                <div className="mt-8 grid gap-4 border-t border-line pt-6 sm:grid-cols-2">
                  {order.deliveryNote && (
                    <div className="flex gap-3">
                      <Truck className="mt-0.5 h-5 w-5 text-ink-soft" />
                      <div className="text-sm">
                        <p className="font-medium">Delivery {order.deliveryNote.dn_no}</p>
                        <p className="capitalize text-ink-soft">
                          {order.deliveryNote.status}
                          {order.deliveredAt && ` · ${formatDate(order.deliveredAt)}`}
                        </p>
                      </div>
                    </div>
                  )}
                  {order.invoice && (
                    <div className="flex gap-3">
                      <FileText className="mt-0.5 h-5 w-5 text-ink-soft" />
                      <div className="text-sm">
                        <p className="font-medium">Invoice {order.invoice.invoice_no}</p>
                        <p className="text-ink-soft">
                          <span className="capitalize">{order.invoice.status}</span> · {formatPrice(order.invoice.amount_paid, order.currency)} paid of{" "}
                          {formatPrice(order.invoice.total, order.currency)}
                        </p>
                      </div>
                    </div>
                  )}
                </div>
              )}
            </section>
          )}

          <section className="rounded-3xl border border-line bg-white p-6">
            <h2 className="mb-4 font-semibold">Items</h2>
            <ul className="divide-y divide-line">
              {order.items.map((item) => (
                <li key={item.sku} className="flex items-center gap-4 py-4 first:pt-0 last:pb-0">
                  <Link href={productHref(item.sku)} className="w-16 shrink-0 overflow-hidden rounded-xl border border-line">
                    <ProductImage src={item.imageUrl} alt={item.name} sizes="64px" />
                  </Link>
                  <div className="min-w-0 flex-1">
                    <p className="font-medium">{item.name}</p>
                    <p className="text-sm text-ink-soft">
                      Qty {item.quantity} · SKU <span className="font-mono">{item.sku}</span>
                    </p>
                  </div>
                  <p className="text-sm font-medium">{formatPrice(item.unitPrice * item.quantity, order.currency)}</p>
                </li>
              ))}
            </ul>
          </section>
        </div>

        <aside className="space-y-6">
          <section className="rounded-3xl border border-line bg-white p-6 text-sm">
            <h2 className="mb-4 text-base font-semibold">Summary</h2>
            <dl className="space-y-2">
              <div className="flex justify-between">
                <dt className="text-ink-soft">Order total (excl. GST)</dt>
                <dd className="font-semibold">{formatPrice(order.totalAmount ?? order.subtotalEstimate, order.currency)}</dd>
              </div>
              {order.totalAmount === null && (
                <p className="text-xs text-ink-soft">Estimated — final amount is confirmed by our team.</p>
              )}
              <p className="pt-2 text-xs text-ink-soft">Applicable GST will be shown on your invoice.</p>
            </dl>
          </section>
          <section className="rounded-3xl border border-line bg-white p-6 text-sm">
            <h2 className="mb-3 text-base font-semibold">Shipping to</h2>
            <address className="not-italic leading-relaxed text-ink-soft">
              <span className="font-medium text-ink">{order.customerName}</span>
              <br />
              {order.shippingAddress.address_line}
              <br />
              {order.shippingAddress.city}, {order.shippingAddress.state} {order.shippingAddress.pincode}
              <br />
              {order.shippingAddress.country}
              {order.customerPhone && (
                <>
                  <br />
                  {order.customerPhone}
                </>
              )}
            </address>
            {order.notes && (
              <>
                <h3 className="mb-1 mt-4 font-semibold">Notes</h3>
                <p className="whitespace-pre-line text-ink-soft">{order.notes}</p>
              </>
            )}
          </section>
        </aside>
      </div>
    </div>
  );
}

function Header({ order, placed }: { order: OrderView; placed: boolean }) {
  const ref = order.erpOrderNo ?? order.websiteOrderId;
  return (
    <div>
      {order.submitState === "submitted" && placed && (
        <div className="mb-8 flex items-start gap-4 rounded-3xl bg-ink p-6 text-white sm:p-8">
          <CheckCircle2 className="h-8 w-8 shrink-0 text-emerald-400" />
          <div>
            <h1 className="text-2xl font-semibold sm:text-3xl">Thank you, {order.customerName.split(" ")[0]}!</h1>
            <p className="mt-1 text-white/75">
              Your order <span className="font-mono text-white">{ref}</span> has been received. Bookmark this page to track your order any time — you can also look it up with your order number and
              {order.customerEmail}.
            </p>
          </div>
        </div>
      )}
      {order.submitState === "submitting" && (
        <Banner icon={<Loader2 className="h-6 w-6 animate-spin" />} tone="neutral" title="Confirming your order…">
          We&apos;re confirming your order with our system. Refresh this page in a few seconds.
        </Banner>
      )}
      {order.submitState === "unknown" && (
        <Banner icon={<Clock className="h-6 w-6" />} tone="warn" title="We haven't been able to confirm this order yet">
          Our order system didn&apos;t respond in time. We&apos;ll keep checking — refresh this page shortly. If it still isn&apos;t
          confirmed, <Link href="/checkout" className="underline">return to checkout</Link> to try again (this won&apos;t create a
          duplicate order).
        </Banner>
      )}
      {order.submitState === "rejected" && (
        <Banner icon={<AlertTriangle className="h-6 w-6" />} tone="error" title="This order could not be placed">
          {order.submitError ?? "Our system rejected this order."} Nothing has been charged.{" "}
          <Link href="/cart" className="underline">Review your cart</Link>.
        </Banner>
      )}

      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="text-sm text-ink-soft">Order</p>
          <h2 className="font-mono text-2xl font-semibold">{ref}</h2>
          <p className="mt-1 text-sm text-ink-soft">
            Placed {formatDate(order.orderDate ?? order.createdAt)}
            {order.erpOrderNo && (
              <>
                {" "}· Reference <span className="font-mono">{order.websiteOrderId}</span>
              </>
            )}
          </p>
        </div>
        {order.submitState === "submitted" && <StatusPill status={order.status} />}
      </div>
    </div>
  );
}

function Banner({ icon, tone, title, children }: { icon: React.ReactNode; tone: "neutral" | "warn" | "error"; title: string; children: React.ReactNode }) {
  const tones = {
    neutral: "border-line bg-white",
    warn: "border-amber-200 bg-amber-50 text-amber-900",
    error: "border-red-200 bg-red-50 text-red-900",
  };
  return (
    <div className={`mb-8 flex gap-4 rounded-3xl border p-6 ${tones[tone]}`}>
      <div className="shrink-0">{icon}</div>
      <div>
        <h1 className="text-lg font-semibold">{title}</h1>
        <p className="mt-1 text-sm opacity-90">{children}</p>
      </div>
    </div>
  );
}

const PILL: Record<string, string> = {
  pending: "bg-amber-50 text-amber-800 border-amber-200",
  confirmed: "bg-sky-50 text-sky-800 border-sky-200",
  shipped: "bg-indigo-50 text-indigo-800 border-indigo-200",
  completed: "bg-emerald-50 text-emerald-800 border-emerald-200",
  cancelled: "bg-stone-100 text-stone-700 border-stone-300",
};

function StatusPill({ status }: { status: string }) {
  return <span className={`rounded-full border px-4 py-1.5 text-sm font-medium capitalize ${PILL[status] ?? PILL.pending}`}>{status}</span>;
}
