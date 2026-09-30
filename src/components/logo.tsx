import Link from "next/link";
import { site } from "@/lib/site";

export function Logo() {
  return (
    <Link href="/" className="flex items-center gap-2.5 font-semibold tracking-tight">
      <svg viewBox="0 0 24 24" className="h-7 w-7" aria-hidden>
        <rect x="2" y="2" width="9" height="9" rx="2" className="fill-ink" />
        <rect x="13" y="2" width="9" height="9" rx="4.5" className="fill-accent" />
        <rect x="2" y="13" width="9" height="9" rx="4.5" className="fill-accent/40" />
        <rect x="13" y="13" width="9" height="9" rx="2" className="fill-ink" />
      </svg>
      <span className="text-lg">{site.name}</span>
    </Link>
  );
}
