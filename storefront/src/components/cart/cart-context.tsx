"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";

/**
 * Carrito del escaparate.
 *
 * IMPORTANTE: esto no llama a la API. Vercel no ejecuta PHP, así que el carrito
 * vive en el navegador. Lo que sí replica son las reglas del contrato, para que
 * la demo no mienta sobre cómo se comporta el backend:
 *
 *  - Añadir una variante que ya está en el carrito SUMA cantidad, no crea una
 *    segunda línea (UNIQUE cart_id + product_variant_id).
 *  - El tope por línea es 20 (CartItem::MAX_QUANTITY).
 *  - Añadir NO valida stock (ADR-0005): se pueden meter 10 de las que quedan 2.
 *    Se avisa, pero no se bloquea.
 *  - El precio se congela al añadir y no se recalcula.
 *  - item_count es la suma de cantidades, no el número de líneas.
 */

export const MAX_QUANTITY = 20;
const STORAGE_KEY = "nogal.cart.v1";

export type CartLine = {
  sku: string;
  productSlug: string;
  productName: string;
  variantLabel: string;
  colorHex: string | null;
  unitPriceCents: number;
  quantity: number;
  available: number;
};

export type NewLine = Omit<CartLine, "quantity">;

type Totals = {
  subtotal_cents: number;
  discount_cents: number;
  shipping_cents: number;
  tax_cents: number;
  total_cents: number;
};

type CartValue = {
  lines: CartLine[];
  itemCount: number;
  totals: Totals;
  open: boolean;
  notice: string | null;
  add: (line: NewLine, quantity?: number) => void;
  setQuantity: (sku: string, quantity: number) => void;
  remove: (sku: string) => void;
  clear: () => void;
  setOpen: (open: boolean) => void;
  dismissNotice: () => void;
};

const CartContext = createContext<CartValue | null>(null);

function read(): CartLine[] {
  // Puede fallar en ventana privada, con datos de sitio bloqueados o durante la
  // captura de miniaturas. Un carrito vacío es una respuesta válida.
  try {
    const raw = window.localStorage.getItem(STORAGE_KEY);
    if (!raw) return [];
    const parsed = JSON.parse(raw);
    return Array.isArray(parsed) ? (parsed as CartLine[]) : [];
  } catch {
    return [];
  }
}

export function CartProvider({ children }: { children: ReactNode }) {
  const [lines, setLines] = useState<CartLine[]>([]);
  const [open, setOpen] = useState(false);
  const [notice, setNotice] = useState<string | null>(null);

  // Se hidrata después del montaje: leer localStorage durante el render
  // rompería el HTML generado en el servidor.
  useEffect(() => setLines(read()), []);

  useEffect(() => {
    try {
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify(lines));
    } catch {
      /* sin persistencia; el carrito sigue funcionando en memoria */
    }
  }, [lines]);

  const add = useCallback((line: NewLine, quantity = 1) => {
    setNotice(null);

    setLines((prev) => {
      const existente = prev.find((l) => l.sku === line.sku);
      const objetivo = (existente?.quantity ?? 0) + quantity;

      if (objetivo > MAX_QUANTITY) {
        setNotice(
          `El tope por línea es ${MAX_QUANTITY} unidades. Ya tienes ${existente?.quantity ?? 0} de ${line.sku}.`,
        );
        return prev;
      }

      if (existente) {
        return prev.map((l) =>
          l.sku === line.sku ? { ...l, quantity: objetivo } : l,
        );
      }

      return [...prev, { ...line, quantity }];
    });

    setOpen(true);
  }, []);

  const setQuantity = useCallback((sku: string, quantity: number) => {
    // Reemplaza, no suma. Para quitar existe remove(), igual que en la API el
    // borrado es DELETE y no PATCH con quantity 0.
    const q = Math.min(Math.max(quantity, 1), MAX_QUANTITY);
    setLines((prev) =>
      prev.map((l) => (l.sku === sku ? { ...l, quantity: q } : l)),
    );
  }, []);

  const remove = useCallback((sku: string) => {
    setLines((prev) => prev.filter((l) => l.sku !== sku));
  }, []);

  const clear = useCallback(() => setLines([]), []);

  const value = useMemo<CartValue>(() => {
    const subtotal = lines.reduce(
      (sum, l) => sum + l.unitPriceCents * l.quantity,
      0,
    );

    return {
      lines,
      itemCount: lines.reduce((sum, l) => sum + l.quantity, 0),
      // ADR-0008: los cinco campos existen desde el día uno aunque tres valgan
      // cero, y el total se calcula con las cuatro operaciones.
      totals: {
        subtotal_cents: subtotal,
        discount_cents: 0,
        shipping_cents: 0,
        tax_cents: 0,
        total_cents: subtotal - 0 + 0 + 0,
      },
      open,
      notice,
      add,
      setQuantity,
      remove,
      clear,
      setOpen,
      dismissNotice: () => setNotice(null),
    };
  }, [lines, open, notice, add, setQuantity, remove, clear]);

  return <CartContext.Provider value={value}>{children}</CartContext.Provider>;
}

export function useCart(): CartValue {
  const ctx = useContext(CartContext);
  if (!ctx) throw new Error("useCart debe usarse dentro de CartProvider");
  return ctx;
}
