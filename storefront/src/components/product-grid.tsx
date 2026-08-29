import { products } from "@/lib/catalog";
import { priceRange, price } from "@/lib/format";
import { inkOn } from "@/lib/color";
import { Reveal } from "./reveal";

/**
 * Los ocho productos del seeder, con sus precios reales en centavos, sus tallas
 * y su disponibilidad calculada como on_hand menos reserved.
 *
 * Un producto puede estar agotado y se dice: `total_available` sale del
 * inventario, no de un campo booleano escrito a mano.
 */
export function ProductGrid() {
  return (
    <section id="coleccion" className="bg-background">
      <div className="mx-auto max-w-6xl px-5 py-section sm:px-8">
        <Reveal>
          <div className="flex flex-wrap items-baseline justify-between gap-4">
            <h2 className="font-heading text-fluid-lg font-semibold text-ink">
              La colección
            </h2>
            <p className="text-fluid-sm text-muted">
              Precios y existencias servidos desde la base de datos, no escritos
              aquí.
            </p>
          </div>
        </Reveal>

        <ul className="mt-10 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
          {products.map((p, i) => {
            const agotado = !p.in_stock;
            const tono = inkOn(p.sample_variant?.color.hex);

            return (
              <li key={p.slug}>
                <Reveal delay={(i % 3) * 0.05}>
                  <article className="lift group flex h-full flex-col overflow-hidden rounded-sm border border-line bg-background">
                    <div
                      className="relative aspect-4/5 w-full"
                      style={{
                        backgroundColor:
                          p.sample_variant?.color.hex ?? "#c9b79a",
                      }}
                    >
                      <div className="grain absolute inset-0" aria-hidden />
                      <span className="absolute left-4 top-4 rounded-full bg-background/85 px-3 py-1 text-fluid-xs uppercase tracking-[0.16em] text-ink">
                        {p.category.name}
                      </span>
                      {agotado && (
                        <span className="absolute right-4 top-4 rounded-full bg-ink px-3 py-1 text-fluid-xs uppercase tracking-[0.16em] text-background">
                          Agotado
                        </span>
                      )}
                      {p.sample_variant && (
                        <span
                          className={`tabular absolute bottom-4 left-4 text-[10px] uppercase tracking-[0.14em] ${tono.soft}`}
                        >
                          {p.sample_variant.sku}
                        </span>
                      )}
                    </div>

                    <div className="flex flex-1 flex-col p-5">
                      <h3 className="font-heading text-fluid-base font-semibold leading-snug text-ink">
                        {p.name}
                      </h3>
                      <p className="mt-2 line-clamp-2 text-fluid-sm leading-relaxed text-muted">
                        {p.summary}
                      </p>

                      <ul className="mt-4 flex flex-wrap gap-1.5">
                        {p.available_sizes.map((s) => (
                          <li
                            key={s}
                            className="tabular rounded-sm border border-line px-2 py-0.5 text-fluid-xs text-muted"
                          >
                            {s}
                          </li>
                        ))}
                      </ul>

                      <div className="mt-5 flex items-end justify-between gap-4 border-t border-line-soft pt-4">
                        <span>
                          <span className="tabular block font-heading text-fluid-base font-semibold text-ink">
                            {priceRange(
                              p.price_range_cents.min,
                              p.price_range_cents.max,
                            )}
                          </span>
                          <span className="tabular mt-0.5 block text-fluid-xs text-muted">
                            {p.variant_count} variantes ·{" "}
                            {p.total_available} disponibles
                          </span>
                        </span>

                        <button
                          type="button"
                          disabled={agotado}
                          aria-label={`Añadir ${p.name} al carrito`}
                          className="press inline-flex min-h-11 shrink-0 items-center rounded-full bg-nogal px-5 text-fluid-xs font-medium text-background hover:bg-ink disabled:cursor-not-allowed disabled:bg-line disabled:text-muted"
                        >
                          {agotado ? "Sin stock" : "Añadir"}
                        </button>
                      </div>
                    </div>
                  </article>
                </Reveal>
              </li>
            );
          })}
        </ul>

        <Reveal>
          <p className="mt-10 max-w-[62ch] text-fluid-sm leading-relaxed text-muted">
            El precio más alto de cada rango es el de la talla XXL, que cuesta{" "}
            {price(200000)} más. Está guardado en la variante, no en el producto:
            por eso el rango existe.
          </p>
        </Reveal>
      </div>
    </section>
  );
}
