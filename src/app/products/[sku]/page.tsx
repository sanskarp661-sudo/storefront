import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { AddToCart } from "@/components/add-to-cart";
import { ProductCard } from "@/components/product-card";
import { ProductImage } from "@/components/product-image";
import { StockBadge } from "@/components/stock-badge";
import { categoryHref, formatPrice } from "@/lib/format";
import { getProductBySku, getRelatedProducts } from "@/lib/server/catalog";
import { ensureFreshCatalog } from "@/lib/server/sync";

export const dynamic = "force-dynamic";

export async function generateMetadata({ params }: PageProps<"/products/[sku]">): Promise<Metadata> {
  const { sku } = await params;
  const product = await getProductBySku(decodeURIComponent(sku));
  if (!product) return { title: "Product not found" };
  return {
    title: product.name,
    description: product.description?.slice(0, 160) ?? undefined,
    openGraph: product.imageUrl ? { images: [product.imageUrl] } : undefined,
  };
}

export default async function ProductPage({ params }: PageProps<"/products/[sku]">) {
  const { sku: rawSku } = await params;
  const sku = decodeURIComponent(rawSku);
  await ensureFreshCatalog();
  const product = await getProductBySku(sku);
  if (!product) notFound();
  const related = await getRelatedProducts(product);

  return (
    <div className="container-page py-10">
      <nav className="mb-6 text-sm text-ink-soft">
        <Link href="/" className="hover:text-ink">Home</Link> <span className="mx-1">/</span>
        <Link href="/products" className="hover:text-ink">Shop</Link>
        {product.category && (
          <>
            {" "}<span className="mx-1">/</span>
            <Link href={categoryHref(product.category)} className="hover:text-ink">{product.category}</Link>
          </>
        )}
      </nav>

      <div className="grid gap-10 md:grid-cols-2 lg:gap-16">
        <div className="overflow-hidden rounded-3xl border border-line bg-white">
          <ProductImage src={product.imageUrl} alt={product.name} sizes="(min-width: 768px) 50vw, 100vw" priority />
        </div>

        <div className="flex flex-col">
          {product.brand && <p className="text-sm font-medium uppercase tracking-wide text-ink-soft">{product.brand}</p>}
          <h1 className="mt-1 text-3xl font-semibold tracking-tight lg:text-4xl">{product.name}</h1>
          <div className="mt-4 flex flex-wrap items-center gap-3">
            <p className="text-2xl font-semibold">
              {formatPrice(product.price, product.currency)}
              {product.unit && <span className="ml-1 text-base font-normal text-ink-soft">/ {product.unit}</span>}
            </p>
            <StockBadge available={product.availableQuantity} />
          </div>
          <p className="mt-1 text-xs text-ink-soft">Price excludes GST. Applicable taxes are shown on your invoice.</p>

          <div className="mt-8">
            <AddToCart
              product={{
                sku: product.sku,
                name: product.name,
                price: product.price,
                currency: product.currency,
                imageUrl: product.imageUrl,
                unit: product.unit,
                maxQuantity: Math.floor(product.availableQuantity),
              }}
            />
          </div>

          {product.description && (
            <div className="mt-10 border-t border-line pt-8">
              <h2 className="mb-3 font-semibold">Description</h2>
              <div className="whitespace-pre-line leading-relaxed text-ink-soft">{product.description}</div>
            </div>
          )}

          <dl className="mt-8 grid grid-cols-2 gap-x-6 gap-y-3 border-t border-line pt-8 text-sm">
            <dt className="text-ink-soft">SKU</dt>
            <dd className="font-mono">{product.sku}</dd>
            {product.category && (
              <>
                <dt className="text-ink-soft">Category</dt>
                <dd>
                  <Link href={categoryHref(product.category)} className="underline underline-offset-4">{product.category}</Link>
                </dd>
              </>
            )}
            {product.brand && (
              <>
                <dt className="text-ink-soft">Brand</dt>
                <dd>{product.brand}</dd>
              </>
            )}
            {product.unit && (
              <>
                <dt className="text-ink-soft">Sold per</dt>
                <dd>{product.unit}</dd>
              </>
            )}
          </dl>
        </div>
      </div>

      {related.length > 0 && (
        <section className="mt-20">
          <h2 className="mb-6 text-2xl font-semibold tracking-tight">You may also like</h2>
          <div className="grid grid-cols-2 gap-x-4 gap-y-8 md:grid-cols-4">
            {related.map((p) => (
              <ProductCard key={p.sku} product={p} />
            ))}
          </div>
        </section>
      )}
    </div>
  );
}
