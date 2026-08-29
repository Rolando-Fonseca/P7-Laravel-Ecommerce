"use client";

import { Minus, Plus, Trash2, X, AlertTriangle } from "lucide-react";
import { useEffect, useRef } from "react";
import { price } from "@/lib/format";
import { useCart, MAX_QUANTITY } from "./cart-context";

/**
 * Panel del carrito.
 *
 * Se queda siempre montado y se muestra con transiciones CSS, en vez de
 * montarlo y desmontarlo con AnimatePresence. La primera versión usaba
 * AnimatePresence y el árbol se quedaba montado tras cerrar: la animación de
 * salida terminaba (opacidad 0) pero el nodo no se retiraba nunca.
 *
 * Estando siempre montado hay que sacarlo del alcance cuando está cerrado, o
 * queda como trampa de tabulación y los lectores de pantalla leen un carrito
 * invisible. De eso se encarga `inert`.
 */
export function CartDrawer() {
  const {
    lines,
    itemCount,
    totals,
    open,
    setOpen,
    setQuantity,
    remove,
    clear,
    notice,
    dismissNotice,
  } = useCart();

  const panel = useRef<HTMLDivElement>(null);
  const disparador = useRef<Element | null>(null);

  useEffect(() => {
    if (!open) {
      // Devolver el foco a donde estaba: si no, al cerrar el foco cae al
      // principio del documento y el teclado tiene que recorrerlo todo.
      if (disparador.current instanceof HTMLElement) disparador.current.focus();
      return;
    }

    disparador.current = document.activeElement;

    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") setOpen(false);
    };

    document.addEventListener("keydown", onKey);
    panel.current?.focus();
    document.body.style.overflow = "hidden";

    return () => {
      document.removeEventListener("keydown", onKey);
      document.body.style.overflow = "";
    };
  }, [open, setOpen]);

  return (
    <div
      className={`fixed inset-0 z-[60] ${open ? "" : "pointer-events-none"}`}
      inert={!open}
    >
      <button
        type="button"
        aria-label="Cerrar el carrito"
        onClick={() => setOpen(false)}
        className={`drawer-fade absolute inset-0 h-full w-full cursor-default bg-ink/45 ${
          open ? "opacity-100 duration-240" : "opacity-0 duration-180"
        }`}
      />

      <div
        ref={panel}
        tabIndex={-1}
        role="dialog"
        aria-modal="true"
        aria-label="Carrito de compra"
        className={`drawer-panel absolute right-0 top-0 flex h-full w-full max-w-md flex-col bg-background shadow-2xl outline-none ${
          open
            ? "translate-x-0 opacity-100 duration-250"
            : "translate-x-full opacity-0 duration-190"
        }`}
      >
        <header className="flex items-center justify-between border-b border-line px-5 py-4">
          <h2 className="font-heading text-fluid-base font-semibold text-ink">
            Tu carrito
            {itemCount > 0 && (
              <span className="tabular ml-2 text-fluid-sm font-normal text-muted">
                {itemCount} {itemCount === 1 ? "unidad" : "unidades"}
              </span>
            )}
          </h2>
          <button
            type="button"
            onClick={() => setOpen(false)}
            aria-label="Cerrar el carrito"
            className="press grid h-11 w-11 place-items-center rounded-full text-muted hover:bg-surface hover:text-nogal"
          >
            <X size={20} strokeWidth={1.6} aria-hidden />
          </button>
        </header>

        {notice && (
          <div
            role="status"
            className="flex items-start gap-3 border-b border-line bg-surface px-5 py-3"
          >
            <AlertTriangle
              size={16}
              className="mt-0.5 shrink-0 text-nogal"
              aria-hidden
            />
            <p className="flex-1 text-fluid-sm text-foreground">{notice}</p>
            <button
              type="button"
              onClick={dismissNotice}
              className="press text-fluid-xs text-muted underline underline-offset-2"
            >
              Ocultar
            </button>
          </div>
        )}

        {lines.length === 0 ? (
          <div className="flex flex-1 flex-col items-center justify-center gap-3 px-8 text-center">
            <p className="font-heading text-fluid-base text-ink">
              Todavía no hay nada aquí.
            </p>
            <p className="max-w-[32ch] text-fluid-sm text-muted">
              Añade una prenda desde la colección y vuelve.
            </p>
          </div>
        ) : (
          <ul className="flex-1 divide-y divide-line-soft overflow-y-auto">
            {lines.map((l) => {
              const suficiente = l.available >= l.quantity;

              return (
                <li key={l.sku} className="flex gap-4 px-5 py-4">
                  <span
                    className="h-20 w-16 shrink-0 rounded-sm border border-line"
                    style={{ backgroundColor: l.colorHex ?? "#c9b79a" }}
                    aria-hidden
                  />

                  <div className="min-w-0 flex-1">
                    <p className="font-heading text-fluid-sm font-semibold leading-snug text-ink">
                      {l.productName}
                    </p>
                    <p className="mt-0.5 text-fluid-xs text-muted">
                      {l.variantLabel}
                    </p>
                    <p className="tabular mt-0.5 text-[10px] uppercase tracking-[0.14em] text-muted">
                      {l.sku}
                    </p>

                    {!suficiente && (
                      <p className="tabular mt-2 text-fluid-xs text-nogal">
                        Solo quedan {l.available}. El pedido fallaría con 409.
                      </p>
                    )}

                    <div className="mt-3 flex items-center justify-between gap-3">
                      <div className="flex items-center rounded-full border border-line">
                        <button
                          type="button"
                          onClick={() => setQuantity(l.sku, l.quantity - 1)}
                          disabled={l.quantity <= 1}
                          aria-label={`Quitar una unidad de ${l.productName}`}
                          className="press grid h-9 w-9 place-items-center rounded-full text-muted hover:text-nogal disabled:opacity-35"
                        >
                          <Minus size={14} strokeWidth={2} aria-hidden />
                        </button>
                        <span
                          className="tabular w-8 text-center text-fluid-sm text-ink"
                          aria-live="polite"
                        >
                          {l.quantity}
                        </span>
                        <button
                          type="button"
                          onClick={() => setQuantity(l.sku, l.quantity + 1)}
                          disabled={l.quantity >= MAX_QUANTITY}
                          aria-label={`Añadir una unidad de ${l.productName}`}
                          className="press grid h-9 w-9 place-items-center rounded-full text-muted hover:text-nogal disabled:opacity-35"
                        >
                          <Plus size={14} strokeWidth={2} aria-hidden />
                        </button>
                      </div>

                      <span className="tabular font-heading text-fluid-sm font-semibold text-ink">
                        {price(l.unitPriceCents * l.quantity)}
                      </span>

                      <button
                        type="button"
                        onClick={() => remove(l.sku)}
                        aria-label={`Quitar ${l.productName} del carrito`}
                        className="press grid h-9 w-9 place-items-center rounded-full text-muted hover:text-nogal"
                      >
                        <Trash2 size={15} strokeWidth={1.6} aria-hidden />
                      </button>
                    </div>
                  </div>
                </li>
              );
            })}
          </ul>
        )}

        {lines.length > 0 && (
          <footer className="border-t border-line px-5 py-5">
            <dl className="space-y-1.5">
              {(
                [
                  ["Subtotal", totals.subtotal_cents],
                  ["Descuento", totals.discount_cents],
                  ["Envío", totals.shipping_cents],
                  ["Impuestos", totals.tax_cents],
                ] as const
              ).map(([label, value]) => (
                <div
                  key={label}
                  className="flex justify-between text-fluid-sm text-muted"
                >
                  <dt>{label}</dt>
                  <dd className="tabular">{price(value)}</dd>
                </div>
              ))}
              <div className="flex justify-between border-t border-line pt-2.5">
                <dt className="font-heading text-fluid-base font-semibold text-ink">
                  Total
                </dt>
                <dd className="tabular font-heading text-fluid-base font-semibold text-ink">
                  {price(totals.total_cents)}
                </dd>
              </div>
            </dl>

            <p className="mt-4 text-fluid-xs leading-relaxed text-muted">
              Descuento, envío e impuestos valen siempre cero en el MVP y
              aparecen desde el día uno para que añadirlos después no rompa el
              contrato.
            </p>

            <details className="mt-4 rounded-sm border border-line bg-surface">
              <summary className="press cursor-pointer list-none px-4 py-3 text-fluid-xs font-medium text-nogal">
                Ver el cuerpo que enviaría a POST /api/v1/orders
              </summary>
              <pre className="overflow-x-auto border-t border-line px-4 py-3 text-[11px] leading-relaxed text-foreground">
                <code>
                  {JSON.stringify(
                    {
                      cart_token: "9f1c2b7e-4a83-4d21-9c55-7b0e1f3a6d84",
                      email: "cliente@ejemplo.com",
                      shipping_address: {
                        full_name: "Andrés Molina",
                        phone: "+57 300 123 4567",
                        line1: "Carrera 45 # 26-30",
                        city: "Medellín",
                        state: "Antioquia",
                        country: "CO",
                      },
                    },
                    null,
                    2,
                  )}
                </code>
              </pre>
            </details>

            <div className="mt-5 flex gap-2">
              <button
                type="button"
                disabled
                title="El MVP no cobra: los pagos están fuera de alcance por ADR-0007"
                className="press inline-flex min-h-11 flex-1 items-center justify-center rounded-full bg-line text-fluid-sm font-medium text-muted"
              >
                Pagar — fuera del MVP
              </button>
              <button
                type="button"
                onClick={clear}
                className="press inline-flex min-h-11 items-center rounded-full border border-line px-5 text-fluid-sm text-foreground hover:border-nogal hover:text-nogal"
              >
                Vaciar
              </button>
            </div>

            <p className="mt-3 text-fluid-xs leading-relaxed text-muted">
              El botón de pago está apagado a propósito: el backend llega hasta
              crear el pedido y el cobro queda fuera del alcance (ADR-0007).
            </p>
          </footer>
        )}
      </div>
    </div>
  );
}
