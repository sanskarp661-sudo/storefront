import Link from "next/link";
import { site } from "@/lib/site";
import { Logo } from "./logo";

export function Footer({ categories }: { categories: string[] }) {
  return (
    <footer className="mt-24 border-t border-line bg-white">
      <div className="container-page grid gap-10 py-14 sm:grid-cols-2 lg:grid-cols-4">
        <div className="space-y-3">
          <Logo />
          <p className="max-w-xs text-sm text-ink-soft">{site.tagline}</p>
        </div>
        <div>
          <h3 className="mb-3 text-sm font-semibold">Shop</h3>
          <ul className="space-y-2 text-sm text-ink-soft">
            <li><Link href="/products" className="hover:text-ink">All products</Link></li>
            {categories.slice(0, 6).map((c) => (
              <li key={c}>
                <Link href={`/products?category=${encodeURIComponent(c)}`} className="hover:text-ink">{c}</Link>
              </li>
            ))}
          </ul>
        </div>
        <div>
          <h3 className="mb-3 text-sm font-semibold">Orders</h3>
          <ul className="space-y-2 text-sm text-ink-soft">
            <li><Link href="/track" className="hover:text-ink">Track your order</Link></li>
            <li><Link href="/cart" className="hover:text-ink">Your cart</Link></li>
          </ul>
        </div>
        <div>
          <h3 className="mb-3 text-sm font-semibold">Good to know</h3>
          <ul className="space-y-2 text-sm text-ink-soft">
            <li>Prices shown exclude GST.</li>
            <li>Applicable taxes are confirmed on your invoice.</li>
            {site.supportEmail && (
              <li>
                <a href={`mailto:${site.supportEmail}`} className="hover:text-ink">{site.supportEmail}</a>
              </li>
            )}
          </ul>
        </div>
      </div>
      <div className="border-t border-line">
        <div className="container-page py-5 text-xs text-ink-soft">
          © {new Date().getFullYear()} {site.name}. All rights reserved.
        </div>
      </div>
    </footer>
  );
}
