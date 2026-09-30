// Types shared by server and client code. Nothing secret lives here.

export type Product = {
  sku: string;
  name: string;
  description: string | null;
  category: string | null;
  brand: string | null;
  imageUrl: string | null;
  unit: string | null;
  price: number;
  currency: string;
  availableQuantity: number;
};

export type OrderStatus = "pending" | "confirmed" | "shipped" | "completed" | "cancelled";

export type SubmitState = "submitting" | "submitted" | "rejected" | "unknown";

export type OrderItem = { sku: string; name: string; quantity: number; unitPrice: number; imageUrl: string | null };

export type ShippingAddress = {
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

export type OrderView = {
  websiteOrderId: string;
  erpOrderNo: string | null;
  submitState: SubmitState;
  submitError: string | null;
  status: OrderStatus;
  customerName: string;
  customerEmail: string;
  customerPhone: string | null;
  shippingAddress: ShippingAddress;
  items: OrderItem[];
  notes: string | null;
  subtotalEstimate: number;
  totalAmount: number | null;
  currency: string;
  orderDate: string | null;
  deliveryNote: { dn_no: string; status: string } | null;
  deliveredAt: string | null;
  invoice: { invoice_no: string; status: string; amount_paid: number; total: number } | null;
  createdAt: string;
};
