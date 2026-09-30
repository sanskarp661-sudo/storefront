import Link from "next/link";
import { ArrowRight, BadgeCheck, PackageCheck, ReceiptText } from "lucide-react";
import { ProductCard } from "@/components/product-card";
import { ProductImage } from "@/components/product-image";
import { categoryHref } from "@/lib/format";
import { getCategories, searchProducts } from "@/lib/server/catalog";
import { ensureFreshCatalog } from "@/lib/server/sync";
import { site } from "@/lib/site";

export const dynamic = "force-dynamic";

export default async function HomePage() {
  await ensureFreshCatalog();
  const [categories, newest, featured] = await Promise.all([
    getCategories(),
    searchProducts({ sort: "newest", inStock: true, perPage: 8 }),
    searchProducts({ sort: "featured", perPage: 4 }),
  ]);
  const heroProducts = featured.products.filter((p) => p.imageUrl).slice(0, 3);

  return (
    <>
      <section className="border-b border-line">
        <div className="container-page grid items-center gap-10 py-14 md:grid-cols-2 md:py-20">
          <div className="space-y-6">
            <span className="inline-flex items-center gap-2 rounded-full border border-line bg-white px-3 py-1 text-xs font-medium text-ink-soft">
              <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" /> Live stock, straight from our warehouse
            </span>
            <h1 className="text-4xl font-semibold leading-[1.05] tracking-tight sm:text-5xl lg:text-6xl">
              Everything you need, <span className="text-accent">in stock</span> and ready to ship.
            </h1>
            <p className="max-w-md text-lg text-ink-soft">
              Browse the full {site.name} catalogue with real-time availability, and follow your order from confirmation to your doorstep.
            </p>
            <div className="flex flex-wrap gap-3">
              <Link href="/products" className="btn btn-primary">
                Shop the catalogue <ArrowRight className="h-4 w-4" />
              </Link>
              <Link href="/track" className="btn btn-outline">
                Track an order
              </Link>
            </div>
          </div>
          <div className="relative hidden h-[420px] md:block">
            {heroProducts.length > 0 ? (
              heroProducts.map((p, i) => (
                <div
                  key={p.sku}
                  className={[
                    "absolute w-56 overflow-hidden rounded-3xl border border-line bg-white shadow-xl shadow-black/5",
                    i === 0 && "left-0 top-6 rotate-[-4deg]",
                    i === 1 && "left-1/2 top-0 z-10 -translate-x-1/2",
                    i === 2 && "right-0 top-16 rotate-[5deg]",
                  ]
                    .filter(Boolean)
                    .join(" ")}
                >
                  <ProductImage src={p.imageUrl} alt={p.name} sizes="224px" priority />
                  <div className="p-3 text-sm font-medium line-clamp-1">{p.name}</div>
                </div>
              ))
            ) : (
              <div className="grid h-full grid-cols-2 gap-4">
                <div className="rounded-3xl bg-ink" />
                <div className="rounded-full bg-accent" />
                <div className="rounded-full bg-accent/30" />
                <div className="rounded-3xl bg-ink/80" />
              </div>
            )}
          </div>
        </div>
      </section>

      <section className="container-page grid gap-4 py-10 sm:grid-cols-3">
        {[
          { icon: BadgeCheck, title: "Real-time availability", body: "Stock levels come straight from our inventory system." },
          { icon: ReceiptText, title: "Proper GST invoices", body: "Every order is invoiced; applicable taxes are confirmed there." },
          { icon: PackageCheck, title: "Track every step", body: "See when your order is confirmed, shipped and delivered." },
        ].map(({ icon: Icon, title, body }) => (
          <div key={title} className="flex gap-4 rounded-2xl border border-line bg-white p-5">
            <Icon className="h-6 w-6 shrink-0 text-accent" strokeWidth={1.75} />
            <div>
              <p className="font-medium">{title}</p>
              <p className="mt-1 text-sm text-ink-soft">{body}</p>
            </div>
          </div>
        ))}
      </section>

      {categories.length > 0 && (
        <section className="container-page py-10">
          <div className="mb-6 flex items-end justify-between">
            <h2 className="text-2xl font-semibold tracking-tight">Shop by category</h2>
            <Link href="/products" className="text-sm font-medium text-ink-soft hover:text-ink">
              View all →
            </Link>
          </div>
          <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            {categories.slice(0, 8).map((c) => (
              <Link
                key={c.name}
                href={categoryHref(c.name)}
                className="group relative overflow-hidden rounded-2xl border border-line bg-white"
              >
                <ProductImage
                  src={c.imageUrl}
                  alt=""
                  sizes="(min-width: 1024px) 25vw, 50vw"
                  className="aspect-[4/3]! transition-transform duration-500 group-hover:scale-105"
                />
                <div className="absolute inset-0 bg-gradient-to-t from-black/60 via-black/10 to-transparent" />
                <div className="absolute bottom-0 p-4 text-white">
                  <p className="font-semibold">{c.name}</p>
                  <p className="text-xs text-white/80">
                    {c.count} product{c.count === 1 ? "" : "s"}
                  </p>
                </div>
              </Link>
            ))}
          </div>
        </section>
      )}

      <section className="container-page py-10">
        <div className="mb-6 flex items-end justify-between">
          <h2 className="text-2xl font-semibold tracking-tight">New arrivals</h2>
          <Link href="/products?sort=newest" className="text-sm font-medium text-ink-soft hover:text-ink">
            See more →
          </Link>
        </div>
        {newest.products.length > 0 ? (
          <div className="grid grid-cols-2 gap-x-4 gap-y-8 md:grid-cols-3 lg:grid-cols-4">
            {newest.products.map((p) => (
              <ProductCard key={p.sku} product={p} />
            ))}
          </div>
        ) : (
          <p className="rounded-2xl border border-dashed border-line p-10 text-center text-ink-soft">
            Our catalogue is being updated — please check back in a moment.
          </p>
        )}
      </section>
    </>
  );
}
