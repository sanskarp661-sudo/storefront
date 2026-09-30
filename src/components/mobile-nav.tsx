"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useState } from "react";
import { Menu, Search, X } from "lucide-react";

export function MobileNav({ categories }: { categories: string[] }) {
  const [open, setOpen] = useState(false);
  const pathname = usePathname();
  // eslint-disable-next-line react-hooks/set-state-in-effect -- close the drawer on navigation
  useEffect(() => setOpen(false), [pathname]);

  return (
    <>
      <button
        type="button"
        className="-ml-2 inline-flex h-10 w-10 items-center justify-center rounded-full hover:bg-black/5 lg:hidden"
        onClick={() => setOpen(true)}
        aria-label="Open menu"
      >
        <Menu className="h-5 w-5" />
      </button>
      {open && (
        <div className="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true">
          <div className="absolute inset-0 bg-black/30" onClick={() => setOpen(false)} />
          <div className="absolute inset-y-0 left-0 flex w-80 max-w-[85vw] flex-col gap-6 overflow-y-auto bg-paper p-6 shadow-xl">
            <div className="flex items-center justify-between">
              <span className="font-semibold">Menu</span>
              <button type="button" onClick={() => setOpen(false)} aria-label="Close menu" className="rounded-full p-2 hover:bg-black/5">
                <X className="h-5 w-5" />
              </button>
            </div>
            <form action="/products" className="relative" role="search">
              <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-soft" />
              <input name="q" type="search" placeholder="Search products" aria-label="Search products" className="field-input pl-9" />
            </form>
            <nav className="flex flex-col gap-1 text-[15px]">
              <Link href="/products" className="rounded-lg px-2 py-2 font-medium hover:bg-black/5">
                Shop all
              </Link>
              {categories.map((c) => (
                <Link key={c} href={`/products?category=${encodeURIComponent(c)}`} className="rounded-lg px-2 py-2 hover:bg-black/5">
                  {c}
                </Link>
              ))}
              <hr className="my-2 border-line" />
              <Link href="/track" className="rounded-lg px-2 py-2 hover:bg-black/5">
                Track your order
              </Link>
              <Link href="/cart" className="rounded-lg px-2 py-2 hover:bg-black/5">
                Cart
              </Link>
            </nav>
          </div>
        </div>
      )}
    </>
  );
}
