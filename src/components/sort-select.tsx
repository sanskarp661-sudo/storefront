"use client";

import { usePathname, useRouter, useSearchParams } from "next/navigation";

const OPTIONS = [
  ["featured", "Featured"],
  ["newest", "Newest"],
  ["price-asc", "Price: low to high"],
  ["price-desc", "Price: high to low"],
  ["name", "Name: A–Z"],
] as const;

export function SortSelect({ value }: { value: string }) {
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();

  return (
    <label className="flex items-center gap-2 text-sm">
      <span className="text-ink-soft">Sort by</span>
      <select
        value={value}
        onChange={(e) => {
          const params = new URLSearchParams(searchParams.toString());
          if (e.target.value === "featured") params.delete("sort");
          else params.set("sort", e.target.value);
          params.delete("page");
          const qs = params.toString();
          router.push(qs ? `${pathname}?${qs}` : pathname);
        }}
        className="rounded-full border border-line bg-white px-3 py-2 font-medium outline-none focus:border-ink"
      >
        {OPTIONS.map(([v, label]) => (
          <option key={v} value={v}>
            {label}
          </option>
        ))}
      </select>
    </label>
  );
}
