import Link from "next/link";
import { Search, Truck } from "lucide-react";
import { CartButton } from "./cart-button";
import { Logo } from "./logo";
import { MobileNav } from "./mobile-nav";

export function Header({ categories }: { categories: string[] }) {
  const top = categories.slice(0, 5);
  return (
    <header className="sticky top-0 z-40 border-b border-line bg-paper/90 backdrop-blur">
      <div className="container-page flex h-16 items-center gap-4">
        <MobileNav categories={categories} />
        <Logo />
        <nav className="ml-6 hidden items-center gap-6 text-sm font-medium text-ink-soft lg:flex">
          <Link href="/products" className="hover:text-ink">
            Shop all
          </Link>
          {top.map((c) => (
            <Link key={c} href={`/products?category=${encodeURIComponent(c)}`} className="hover:text-ink">
              {c}
            </Link>
          ))}
        </nav>
        <div className="ml-auto flex items-center gap-1 sm:gap-2">
          <form action="/products" className="relative hidden md:block" role="search">
            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-soft" />
            <input
              name="q"
              type="search"
              placeholder="Search products"
              aria-label="Search products"
              className="h-10 w-56 rounded-full border border-line bg-white pl-9 pr-4 text-sm outline-none focus:border-ink xl:w-72"
            />
          </form>
          <Link
            href="/track"
            className="hidden h-10 items-center gap-2 rounded-full px-3 text-sm font-medium hover:bg-black/5 sm:inline-flex"
          >
            <Truck className="h-4 w-4" /> Track order
          </Link>
          <CartButton />
        </div>
      </div>
    </header>
  );
}
