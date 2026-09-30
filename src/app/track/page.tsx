import type { Metadata } from "next";
import { TrackForm } from "./track-form";
import { RecentOrders } from "./recent-orders";

export const metadata: Metadata = { title: "Track your order" };

export default function TrackPage() {
  return (
    <div className="container-page max-w-xl py-16">
      <h1 className="text-3xl font-semibold tracking-tight">Track your order</h1>
      <p className="mt-2 text-ink-soft">
        Enter your order number (it starts with <span className="font-mono">SO-</span> or <span className="font-mono">WEB-</span>) and
        the email address you used at checkout.
      </p>
      <TrackForm />
      <RecentOrders />
    </div>
  );
}
