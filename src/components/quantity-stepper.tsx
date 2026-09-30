"use client";

import { Minus, Plus } from "lucide-react";

export function QuantityStepper({
  value,
  max,
  onChange,
  size = "md",
}: {
  value: number;
  max: number;
  onChange: (n: number) => void;
  size?: "sm" | "md";
}) {
  const h = size === "sm" ? "h-9" : "h-12";
  return (
    <div className={`inline-flex ${h} items-center rounded-full border border-line bg-white`}>
      <button
        type="button"
        className="flex h-full w-10 items-center justify-center rounded-l-full hover:bg-black/5 disabled:opacity-30"
        onClick={() => onChange(value - 1)}
        disabled={value <= 1}
        aria-label="Decrease quantity"
      >
        <Minus className="h-4 w-4" />
      </button>
      <input
        type="number"
        inputMode="numeric"
        min={1}
        max={max}
        value={value}
        onChange={(e) => {
          const n = Number.parseInt(e.target.value, 10);
          if (Number.isFinite(n)) onChange(Math.min(Math.max(n, 1), max));
        }}
        className="w-10 bg-transparent text-center text-sm font-medium outline-none [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none"
        aria-label="Quantity"
      />
      <button
        type="button"
        className="flex h-full w-10 items-center justify-center rounded-r-full hover:bg-black/5 disabled:opacity-30"
        onClick={() => onChange(value + 1)}
        disabled={value >= max}
        aria-label="Increase quantity"
      >
        <Plus className="h-4 w-4" />
      </button>
    </div>
  );
}
