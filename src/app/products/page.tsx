import type { Metadata } from "next";
import Link from "next/link";
import { ProductCard } from "@/components/product-card";
import { SortSelect } from "@/components/sort-select";
import { getBrands, getCategories, searchProducts, type ProductSort } from "@/lib/server/catalog";
import { ensureFreshCatalog } from "@/lib/server/sync";

export const dynamic = "force-dynamic";

const SORTS: ProductSort[] = ["featured", "newest", "price-asc", "price-desc", "name"];

function one(v: string | string[] | undefined): string | undefined {
  return Array.isArray(v) ? v[0] : v;
}

export async function generateMetadata({ searchParams }: PageProps<"/products">): Promise<Metadata> {
  const sp = await searchParams;
  const category = one(sp.category);
  const q = one(sp.q);
  return { title: q ? `Search: ${q}` : (category ?? "Shop all") };
}

export default async function ProductsPage({ searchParams }: PageProps<"/products">) {
  const sp = await searchParams;
  const q = one(sp.q)?.slice(0, 100);
  const category = one(sp.category);
  const brand = one(sp.brand);
  const inStock = one(sp.stock) === "1";
  const sortParam = one(sp.sort) as ProductSort | undefined;
  const sort = sortParam && SORTS.includes(sortParam) ? sortParam : "featured";
  const page = Math.max(1, Number.parseInt(one(sp.page) ?? "1", 10) || 1);

  await ensureFreshCatalog();
  const [result, categories, brands] = await Promise.all([
    searchProducts({ q, category, brand, inStock, sort, page, perPage: 24 }),
    getCategories(),
    getBrands(category),
  ]);

  const href = (overrides: Record<string, string | undefined>) => {
    const params = new URLSearchParams();
    const merged = { q, category, brand, stock: inStock ? "1" : undefined, sort: sort === "featured" ? undefined : sort, ...overrides };
    for (const [k, v] of Object.entries(merged)) if (v) params.set(k, v);
    const s = params.toString();
    return s ? `/products?${s}` : "/products";
  };

  const title = q ? `Results for “${q}”` : (category ?? "All products");

  return (
    <div className="container-page py-10">
      <nav className="mb-4 text-sm text-ink-soft">
        <Link href="/" className="hover:text-ink">Home</Link> <span className="mx-1">/</span>
        {category ? (
          <>
            <Link href="/products" className="hover:text-ink">Shop</Link> <span className="mx-1">/</span>
            <span className="text-ink">{category}</span>
          </>
        ) : (
          <span className="text-ink">Shop</span>
        )}
      </nav>

      <div className="flex flex-wrap items-end justify-between gap-4 border-b border-line pb-6">
        <div>
          <h1 className="text-3xl font-semibold tracking-tight">{title}</h1>
          <p className="mt-1 text-sm text-ink-soft">
            {result.total} product{result.total === 1 ? "" : "s"}
          </p>
        </div>
        <SortSelect value={sort} />
      </div>

      <div className="mt-8 grid gap-10 lg:grid-cols-[220px_1fr]">
        <aside className="space-y-8 text-sm">
          <div>
            <h2 className="mb-3 font-semibold">Category</h2>
            <ul className="flex gap-2 overflow-x-auto pb-1 lg:flex-col lg:gap-1 lg:overflow-visible">
              <li>
                <FilterLink href={href({ category: undefined, brand: undefined, page: undefined })} active={!category}>
                  All
                </FilterLink>
              </li>
              {categories.map((c) => (
                <li key={c.name}>
                  <FilterLink href={href({ category: c.name, brand: undefined, page: undefined })} active={category === c.name}>
                    {c.name} <span className="text-ink-soft">({c.count})</span>
                  </FilterLink>
                </li>
              ))}
            </ul>
          </div>
          {brands.length > 1 && (
            <div>
              <h2 className="mb-3 font-semibold">Brand</h2>
              <ul className="flex flex-wrap gap-2 lg:flex-col lg:gap-1">
                {brands.map((b) => (
                  <li key={b}>
                    <FilterLink href={href({ brand: brand === b ? undefined : b, page: undefined })} active={brand === b}>
                      {b}
                    </FilterLink>
                  </li>
                ))}
              </ul>
            </div>
          )}
          <div>
            <h2 className="mb-3 font-semibold">Availability</h2>
            <Link
              href={href({ stock: inStock ? undefined : "1", page: undefined })}
              className="inline-flex items-center gap-2"
            >
              <span
                className={`flex h-5 w-9 items-center rounded-full p-0.5 transition-colors ${inStock ? "bg-ink" : "bg-stone-300"}`}
              >
                <span className={`h-4 w-4 rounded-full bg-white transition-transform ${inStock ? "translate-x-4" : ""}`} />
              </span>
              In stock only
            </Link>
          </div>
        </aside>

        <div>
          {result.products.length > 0 ? (
            <div className="grid grid-cols-2 gap-x-4 gap-y-10 md:grid-cols-3 xl:grid-cols-4">
              {result.products.map((p, i) => (
                <ProductCard key={p.sku} product={p} priority={i < 4} />
              ))}
            </div>
          ) : (
            <div className="rounded-2xl border border-dashed border-line p-12 text-center">
              <p className="font-medium">No products found</p>
              <p className="mt-1 text-sm text-ink-soft">Try a different search or clear your filters.</p>
              <Link href="/products" className="btn btn-outline mt-5">Clear filters</Link>
            </div>
          )}

          {result.pageCount > 1 && (
            <nav className="mt-12 flex items-center justify-center gap-2" aria-label="Pagination">
              {page > 1 && (
                <Link href={href({ page: String(page - 1) })} className="btn btn-outline px-4 py-2">← Previous</Link>
              )}
              <span className="px-3 text-sm text-ink-soft">
                Page {page} of {result.pageCount}
              </span>
              {page < result.pageCount && (
                <Link href={href({ page: String(page + 1) })} className="btn btn-outline px-4 py-2">Next →</Link>
              )}
            </nav>
          )}
        </div>
      </div>
    </div>
  );
}

function FilterLink({ href, active, children }: { href: string; active: boolean; children: React.ReactNode }) {
  return (
    <Link
      href={href}
      className={`block whitespace-nowrap rounded-full px-3 py-1.5 lg:rounded-lg ${
        active ? "bg-ink text-white [&_span]:text-white/70" : "border border-line bg-white hover:border-ink lg:border-transparent lg:bg-transparent"
      }`}
    >
      {children}
    </Link>
  );
}
