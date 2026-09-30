"use server";

import { z } from "zod";
import { getProductsBySkus } from "@/lib/server/catalog";
import type { Product } from "@/lib/types";

const skuList = z.array(z.string().min(1).max(100)).max(100);

/** Returns current catalog data for the SKUs in a cart (null = no longer sold). Public data only. */
export async function refreshCart(skus: string[]): Promise<Record<string, Product | null>> {
  const parsed = skuList.safeParse(skus);
  if (!parsed.success) return {};
  const found = await getProductsBySkus(parsed.data);
  return Object.fromEntries(parsed.data.map((s) => [s, found.get(s) ?? null]));
}
