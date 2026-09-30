import "server-only";
import { env } from "./env";

/** Shapes returned by the ERP's /api/v1 endpoints. */
export type ErpProduct = {
  id: number;
  sku: string;
  name: string;
  description: string | null;
  category: string | null;
  brand: string | null;
  image_url: string | null;
  unit: string | null;
  price: number | string;
  currency: string | null;
  quantity_on_hand: number | string | null;
  available_quantity: number | string | null;
  status: string;
  updated_at: string;
};

export type ErpProductPage = {
  products: ErpProduct[];
  page: number;
  per_page: number;
  total: number;
  has_more: boolean;
};

export type ErpCategory = { id: number; name: string };

export type ErpOrderStatus = "pending" | "confirmed" | "shipped" | "completed" | "cancelled";

export type ErpCreateOrderRequest = {
  website_order_id: string;
  customer: { name: string; email: string | null; phone: string | null; company: string | null };
  shipping_address: {
    label: string | null;
    address_line: string;
    city: string;
    state: string;
    pincode: string;
    country: string;
    contact_person: string | null;
    contact_phone: string | null;
    contact_email: string | null;
  };
  items: { sku: string; quantity: number }[];
  notes: string | null;
};

export type ErpCreateOrderResponse = {
  order_no: string;
  status: ErpOrderStatus;
  total_amount: number | string;
  items: unknown[];
};

export type ErpOrderStatusResponse = {
  order_no: string;
  website_order_id: string | null;
  status: ErpOrderStatus;
  total_amount: number | string;
  order_date: string;
  delivery_note: { dn_no: string; status: string } | null;
  invoice: {
    invoice_no: string;
    status: string;
    amount_paid: number | string;
    total: number | string;
  } | null;
};

/** An HTTP-level error response from the ERP (4xx/5xx with a body). */
export class ErpHttpError extends Error {
  constructor(
    public status: number,
    message: string,
  ) {
    super(message);
    this.name = "ErpHttpError";
  }
}

type RequestOptions = {
  method?: "GET" | "POST";
  query?: Record<string, string | number | undefined>;
  body?: unknown;
  timeoutMs?: number;
};

async function erpRequest<T>(path: string, opts: RequestOptions = {}): Promise<T> {
  const url = new URL(path, env.erpBaseUrl);
  for (const [k, v] of Object.entries(opts.query ?? {})) {
    if (v !== undefined && v !== "") url.searchParams.set(k, String(v));
  }

  const res = await fetch(url, {
    method: opts.method ?? "GET",
    headers: {
      "X-API-Key": env.erpApiKey,
      Accept: "application/json",
      ...(opts.body !== undefined ? { "Content-Type": "application/json" } : {}),
    },
    body: opts.body !== undefined ? JSON.stringify(opts.body) : undefined,
    cache: "no-store",
    signal: AbortSignal.timeout(opts.timeoutMs ?? 10_000),
  });

  const text = await res.text();
  let data: unknown = null;
  if (text) {
    try {
      data = JSON.parse(text);
    } catch {
      data = null;
    }
  }

  if (!res.ok) {
    const message =
      data && typeof data === "object" && "error" in data && typeof data.error === "string"
        ? data.error
        : `ERP responded with HTTP ${res.status}`;
    throw new ErpHttpError(res.status, message);
  }
  if (data === null) throw new Error(`ERP returned a non-JSON response for ${url.pathname}`);
  return data as T;
}

export function listProducts(params: { page?: number; perPage?: number; since?: string }) {
  return erpRequest<ErpProductPage>("products.php", {
    query: { page: params.page, per_page: params.perPage, since: params.since },
    timeoutMs: 20_000,
  });
}

/** Live single-product lookup. Returns null when the ERP says 404 (missing or inactive). */
export async function getProduct(sku: string, timeoutMs = 5_000): Promise<ErpProduct | null> {
  try {
    const data = await erpRequest<{ product: ErpProduct }>("products.php", { query: { sku }, timeoutMs });
    return data.product;
  } catch (err) {
    if (err instanceof ErpHttpError && err.status === 404) return null;
    throw err;
  }
}

export async function listCategories(): Promise<ErpCategory[]> {
  const data = await erpRequest<{ categories: ErpCategory[] }>("categories.php");
  return data.categories;
}

export function createOrder(body: ErpCreateOrderRequest) {
  return erpRequest<ErpCreateOrderResponse>("orders.php", { method: "POST", body, timeoutMs: 20_000 });
}

/** Returns null when the ERP has no such order (404). */
export async function getOrderStatus(
  lookup: { orderNo: string } | { websiteOrderId: string },
  timeoutMs = 5_000,
): Promise<ErpOrderStatusResponse | null> {
  const query = "orderNo" in lookup ? { order_no: lookup.orderNo } : { website_order_id: lookup.websiteOrderId };
  try {
    return await erpRequest<ErpOrderStatusResponse>("order_status.php", { query, timeoutMs });
  } catch (err) {
    if (err instanceof ErpHttpError && err.status === 404) return null;
    throw err;
  }
}
