import Link from "next/link";
import { formatPrice, productHref } from "@/lib/format";
import type { Product } from "@/lib/types";
import { ProductImage } from "./product-image";
import { StockBadge } from "./stock-badge";

export function ProductCard({ product, priority }: { product: Product; priority?: boolean }) {
  return (
    <Link href={productHref(product.sku)} className="group flex flex-col">
      <div className="overflow-hidden rounded-2xl border border-line bg-card">
        <ProductImage
          src={product.imageUrl}
          alt={product.name}
          priority={priority}
          sizes="(min-width: 1280px) 300px, (min-width: 768px) 33vw, 50vw"
          className="transition-transform duration-500 group-hover:scale-[1.03]"
        />
      </div>
      <div className="mt-3 flex flex-1 flex-col gap-1 px-0.5">
        {product.brand && <p className="text-xs font-medium uppercase tracking-wide text-ink-soft">{product.brand}</p>}
        <h3 className="line-clamp-2 text-[15px] font-medium leading-snug group-hover:underline group-hover:underline-offset-4">
          {product.name}
        </h3>
        <div className="mt-auto flex items-center justify-between gap-2 pt-1">
          <p className="font-semibold">
            {formatPrice(product.price, product.currency)}
            {product.unit && <span className="ml-1 text-xs font-normal text-ink-soft">/ {product.unit}</span>}
          </p>
          <StockBadge available={product.availableQuantity} compact />
        </div>
      </div>
    </Link>
  );
}
