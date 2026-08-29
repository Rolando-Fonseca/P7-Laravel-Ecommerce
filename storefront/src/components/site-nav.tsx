"use client";

import { useState } from "react";
import { Menu, X, Search, ShoppingBag } from "lucide-react";
import { useCart } from "./cart/cart-context";

const links = [
  { href: "#coleccion", label: "Colección" },
  { href: "#categorias", label: "Categorías" },
  { href: "#otono", label: "Otoño" },
  { href: "#bajo-el-capo", label: "Bajo el capó" },
];

export function SiteNav() {
  const [open, setOpen] = useState(false);
  const { itemCount, setOpen: setCartOpen } = useCart();

  return (
    <header className="sticky top-0 z-50 border-b border-line-soft bg-background/85 backdrop-blur-md">
      <nav
        aria-label="Principal"
        className="mx-auto flex max-w-6xl items-center justify-between gap-6 px-5 py-4 sm:px-8"
      >
        <a
          href="#top"
          className="press inline-flex min-h-11 items-center font-heading text-lg font-semibold tracking-[0.22em] text-nogal"
        >
          NOGAL
        </a>

        <ul className="hidden items-center gap-8 md:flex">
          {links.map((link) => (
            <li key={link.href}>
              <a
                href={link.href}
                className="press text-fluid-sm text-muted hover:text-nogal"
              >
                {link.label}
              </a>
            </li>
          ))}
        </ul>

        <div className="flex items-center gap-1">
          <button
            type="button"
            aria-label="Buscar en el catálogo"
            className="press grid h-11 w-11 place-items-center rounded-full text-muted hover:bg-surface hover:text-nogal"
          >
            <Search size={18} strokeWidth={1.6} aria-hidden />
          </button>

          <button
            type="button"
            onClick={() => setCartOpen(true)}
            aria-label={`Ver el carrito, ${itemCount} ${itemCount === 1 ? "unidad" : "unidades"}`}
            className="press relative grid h-11 w-11 place-items-center rounded-full text-muted hover:bg-surface hover:text-nogal"
          >
            <ShoppingBag size={18} strokeWidth={1.6} aria-hidden />
            {itemCount > 0 && (
              <span className="tabular absolute right-0.5 top-0.5 grid h-4 min-w-4 place-items-center rounded-full bg-caramel px-1 text-[10px] font-semibold text-ink">
                {itemCount}
              </span>
            )}
          </button>

          <button
            type="button"
            onClick={() => setOpen((v) => !v)}
            aria-expanded={open}
            aria-controls="menu-movil"
            aria-label={open ? "Cerrar el menú" : "Abrir el menú"}
            className="press grid h-11 w-11 place-items-center rounded-full text-muted hover:bg-surface hover:text-nogal md:hidden"
          >
            {open ? (
              <X size={20} strokeWidth={1.6} aria-hidden />
            ) : (
              <Menu size={20} strokeWidth={1.6} aria-hidden />
            )}
          </button>
        </div>
      </nav>

      {/* Cae desde el borde superior, que es de donde sale. 220 ms cae dentro
          del presupuesto de un desplegable (150–250 ms). */}
      <div
        id="menu-movil"
        hidden={!open}
        className="border-t border-line-soft bg-background md:hidden"
      >
        <ul className="mx-auto max-w-6xl px-5 py-2 sm:px-8">
          {links.map((link) => (
            <li key={link.href}>
              <a
                href={link.href}
                onClick={() => setOpen(false)}
                className="press flex min-h-11 items-center text-fluid-base text-foreground"
              >
                {link.label}
              </a>
            </li>
          ))}
        </ul>
      </div>
    </header>
  );
}
