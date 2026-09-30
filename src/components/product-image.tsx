import Image from "next/image";
import { Package } from "lucide-react";

const ERP_HOST = new URL(process.env.ERP_API_BASE_URL || "https://erp.mosaicengine.in/api/v1/").hostname;

type Props = { src: string | null; alt: string; sizes: string; priority?: boolean; className?: string };

/** Product photo in a square frame; optimised via next/image when hosted on the ERP, placeholder when missing. */
export function ProductImage({ src, alt, sizes, priority, className = "" }: Props) {
  let host: string | null = null;
  let optimizable = false;
  try {
    const url = src ? new URL(src) : null;
    host = url?.hostname ?? null;
    optimizable = url?.protocol === "https:" && host === ERP_HOST;
  } catch {
    host = null;
  }

  return (
    <div className={`relative aspect-square overflow-hidden bg-[#f3efe9] ${className}`}>
      {src && optimizable ? (
        <Image src={src} alt={alt} fill sizes={sizes} priority={priority} className="object-cover" />
      ) : src && host ? (
        // eslint-disable-next-line @next/next/no-img-element -- image hosted outside the configured remotePatterns
        <img src={src} alt={alt} loading={priority ? "eager" : "lazy"} className="absolute inset-0 h-full w-full object-cover" />
      ) : (
        <div className="absolute inset-0 flex items-center justify-center text-ink-soft/40">
          <Package className="h-1/4 w-1/4" strokeWidth={1.25} aria-hidden />
          <span className="sr-only">{alt}</span>
        </div>
      )}
    </div>
  );
}
