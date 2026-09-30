"use client";

import { useActionState } from "react";
import { Loader2, Search } from "lucide-react";
import { trackOrderAction, type TrackState } from "./actions";

export function TrackForm() {
  const [state, action, pending] = useActionState<TrackState, FormData>(trackOrderAction, {});
  return (
    <form action={action} className="mt-8 space-y-4 rounded-3xl border border-line bg-white p-6">
      {state.error && (
        <p role="alert" className="rounded-xl bg-red-50 p-3 text-sm text-red-800">{state.error}</p>
      )}
      <div>
        <label htmlFor="reference" className="field-label">Order number</label>
        <input id="reference" name="reference" required defaultValue={state.reference} placeholder="SO-000123" className="field-input font-mono uppercase" autoCapitalize="characters" />
      </div>
      <div>
        <label htmlFor="email" className="field-label">Email</label>
        <input id="email" name="email" type="email" required defaultValue={state.email} autoComplete="email" className="field-input" />
      </div>
      <button type="submit" disabled={pending} className="btn btn-primary w-full">
        {pending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Search className="h-4 w-4" />}
        Find my order
      </button>
    </form>
  );
}
