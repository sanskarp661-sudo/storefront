import Link from "next/link";

export default function NotFound() {
  return (
    <div className="container-page max-w-xl py-24 text-center">
      <p className="font-mono text-sm text-accent">404</p>
      <h1 className="mt-2 text-3xl font-semibold tracking-tight">We couldn&apos;t find that page</h1>
      <p className="mt-2 text-ink-soft">The product may have been discontinued, or the link might be wrong.</p>
      <Link href="/products" className="btn btn-primary mt-8">Browse products</Link>
    </div>
  );
}
