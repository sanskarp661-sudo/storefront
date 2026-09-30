"use server";

import { redirect } from "next/navigation";
import { z } from "zod";
import { findOrderLink } from "@/lib/server/orders";

export type TrackState = { error?: string; reference?: string; email?: string };

const schema = z.object({
  reference: z.string().trim().min(3).max(40),
  email: z.string().trim().max(200).pipe(z.email()),
});

export async function trackOrderAction(_prev: TrackState, formData: FormData): Promise<TrackState> {
  const reference = String(formData.get("reference") ?? "");
  const email = String(formData.get("email") ?? "");
  const parsed = schema.safeParse({ reference, email });
  if (!parsed.success) return { error: "Please enter your order number and the email you used at checkout.", reference, email };

  const link = await findOrderLink(parsed.data.reference, parsed.data.email);
  if (!link) {
    return { error: "We couldn't find an order matching that number and email.", reference, email };
  }
  redirect(`/orders/${encodeURIComponent(link.websiteOrderId)}?key=${encodeURIComponent(link.accessToken)}`);
}
