import "server-only";
import { db } from "./db";
import type { Product } from "../types";

type ProductRow = {
  sku: string;
  name: string;
  description: string | null;
  category: string | null;
  brand: string | null;
  image_url: string | null;
  unit: string | null;
  price: number;
  currency: string;
  available_quantity: number;
};

const COLUMNS = ["sku", "name", "description", "category", "brand", "image_url", "unit", "price", "currency", "available_quantity"];

function toProduct(r: ProductRow): Product {
  return {
    sku: r.sku,
    name: r.name,
    description: r.description,
    category: r.category,
    brand: r.brand,
    imageUrl: r.image_url,
    unit: r.unit,
    price: Number(r.price),
    currency: r.currency,
    availableQuantity: Number(r.available_quantity),
  };
}

export type ProductSort = "featured" | "newest" | "price-asc" | "price-desc" | "name";

export type ProductQuery = {
  q?: string;
  category?: string;
  brand?: string;
  inStock?: boolean;
  sort?: ProductSort;
  page?: number;
  perPage?: number;
};

export async function searchProducts(query: ProductQuery) {
  const sql = db();
  const perPage = Math.min(Math.max(query.perPage ?? 24, 1), 96);
  const page = Math.max(query.page ?? 1, 1);
  const terms = (query.q ?? "").toLowerCase().split(/\s+/).filter(Boolean).slice(0, 8);

  const conditions = [sql`status = 'active'`];
  for (const t of terms) conditions.push(sql`search_text LIKE ${"%" + t.replace(/[\\%_]/g, "\\$&") + "%"}`);
  if (query.category) conditions.push(sql`category = ${query.category}`);
  if (query.brand) conditions.push(sql`brand = ${query.brand}`);
  if (query.inStock) conditions.push(sql`available_quantity > 0`);
  const where = conditions.reduce((acc, c) => sql`${acc} AND ${c}`);

  const order = {
    featured: sql`(available_quantity > 0) DESC, erp_updated_at DESC NULLS LAST, name`,
    newest: sql`erp_updated_at DESC NULLS LAST, name`,
    "price-asc": sql`price ASC, name`,
    "price-desc": sql`price DESC, name`,
    name: sql`name ASC`,
  }[query.sort ?? "featured"];

  const [rows, [{ total }]] = await Promise.all([
    sql<ProductRow[]>`
      SELECT ${sql(COLUMNS)} FROM products WHERE ${where}
      ORDER BY ${order} LIMIT ${perPage} OFFSET ${(page - 1) * perPage}
    `,
    sql<{ total: number }[]>`SELECT count(*)::int AS total FROM products WHERE ${where}`,
  ]);

  return {
    products: rows.map(toProduct),
    total,
    page,
    perPage,
    pageCount: Math.max(1, Math.ceil(total / perPage)),
  };
}

export async function getProductBySku(sku: string): Promise<Product | null> {
  const sql = db();
  const [row] = await sql<ProductRow[]>`
    SELECT ${sql(COLUMNS)} FROM products WHERE sku = ${sku} AND status = 'active'
    ORDER BY erp_updated_at DESC NULLS LAST LIMIT 1
  `;
  return row ? toProduct(row) : null;
}

export async function getProductsBySkus(skus: string[]): Promise<Map<string, Product>> {
  if (skus.length === 0) return new Map();
  const sql = db();
  const rows = await sql<ProductRow[]>`
    SELECT DISTINCT ON (sku) ${sql(COLUMNS)} FROM products
    WHERE sku = ANY(${skus}::text[]) AND status = 'active'
    ORDER BY sku, erp_updated_at DESC NULLS LAST
  `;
  return new Map(rows.map((r) => [r.sku, toProduct(r)]));
}

export async function getRelatedProducts(product: Product, limit = 4): Promise<Product[]> {
  if (!product.category) return [];
  const sql = db();
  const rows = await sql<ProductRow[]>`
    SELECT ${sql(COLUMNS)} FROM products
    WHERE status = 'active' AND category = ${product.category} AND sku <> ${product.sku}
    ORDER BY (available_quantity > 0) DESC, erp_updated_at DESC NULLS LAST
    LIMIT ${limit}
  `;
  return rows.map(toProduct);
}

/** Categories from the ERP, with a count of active products in each (empty ones hidden). */
export async function getCategories(): Promise<{ name: string; count: number; imageUrl: string | null }[]> {
  const sql = db();
  return sql<{ name: string; count: number; imageUrl: string | null }[]>`
    SELECT c.name,
           count(p.erp_id)::int AS count,
           (SELECT p2.image_url FROM products p2
             WHERE p2.category = c.name AND p2.status = 'active' AND p2.image_url IS NOT NULL
             ORDER BY p2.available_quantity > 0 DESC, p2.erp_updated_at DESC NULLS LAST LIMIT 1) AS "imageUrl"
    FROM categories c
    JOIN products p ON p.category = c.name AND p.status = 'active'
    GROUP BY c.name
    ORDER BY count(p.erp_id) DESC, c.name
  `;
}

export async function getBrands(category?: string): Promise<string[]> {
  const sql = db();
  const rows = await sql<{ brand: string }[]>`
    SELECT DISTINCT brand FROM products
    WHERE status = 'active' AND brand IS NOT NULL AND brand <> ''
      ${category ? sql`AND category = ${category}` : sql``}
    ORDER BY brand
  `;
  return rows.map((r) => r.brand);
}
