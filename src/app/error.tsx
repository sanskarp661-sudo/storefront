"use client";

export default function ErrorPage({ reset }: { error: Error & { digest?: string }; reset: () => void }) {
  return (
    <div className="container-page max-w-xl py-24 text-center">
      <h1 className="text-3xl font-semibold tracking-tight">Something went wrong</h1>
      <p className="mt-2 text-ink-soft">We&apos;re having trouble loading this page. Please try again in a moment.</p>
      <button type="button" onClick={reset} className="btn btn-primary mt-8">Try again</button>
    </div>
  );
}
