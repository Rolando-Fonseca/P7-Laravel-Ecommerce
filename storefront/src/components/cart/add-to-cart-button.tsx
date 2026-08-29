"use client";

import { useCart, type NewLine } from "./cart-context";

export function AddToCartButton({
  line,
  disabled,
  label,
}: {
  line: NewLine;
  disabled: boolean;
  label: string;
}) {
  const { add } = useCart();

  return (
    <button
      type="button"
      disabled={disabled}
      onClick={() => add(line)}
      aria-label={`Añadir ${line.productName} al carrito`}
      className="press inline-flex min-h-11 shrink-0 items-center rounded-full bg-nogal px-5 text-fluid-xs font-medium text-background hover:bg-ink disabled:cursor-not-allowed disabled:bg-line disabled:text-muted"
    >
      {label}
    </button>
  );
}
