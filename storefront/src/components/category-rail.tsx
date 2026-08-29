import { categories, sizeSystemLabel } from "@/lib/catalog";
import { Reveal } from "./reveal";

/**
 * Las seis categorías vienen de la tabla `categories`, con su `product_count`
 * calculado contando solo productos activos, igual que hace
 * GET /api/v1/categories.
 *
 * Cada categoría muestra su sistema de tallas: una camisa usa S–XXL y un
 * pantalón la cintura en pulgadas. Esa distinción está en el modelo de dominio,
 * no es adorno.
 */
export function CategoryRail() {
  return (
    <section id="categorias" className="border-y border-line-soft bg-background">
      <div className="mx-auto max-w-6xl px-5 py-section sm:px-8">
        <Reveal>
          <div className="flex flex-wrap items-baseline justify-between gap-4">
            <h2 className="font-heading text-fluid-lg font-semibold text-ink">
              Lo esencial
            </h2>
            <p className="text-fluid-sm text-muted">
              Seis categorías. Tres sistemas de tallas distintos.
            </p>
          </div>
        </Reveal>

        <ul className="mt-10 grid grid-cols-2 gap-px overflow-hidden rounded-sm border border-line bg-line sm:grid-cols-3 lg:grid-cols-6">
          {categories.map((c, i) => (
            <li key={c.slug}>
              {/* Escalonado de 45 ms: dentro del rango 30–80 ms. Más rápido no
                  se percibe como secuencia y más lento se siente lento. */}
              <Reveal delay={i * 0.045}>
                <a
                  href="#coleccion"
                  className="press lift group flex h-full min-h-40 flex-col justify-between bg-background p-5 hover:bg-surface"
                >
                  <span className="tabular text-fluid-xs uppercase tracking-[0.2em] text-caramel">
                    {String(i + 1).padStart(2, "0")}
                  </span>
                  <span>
                    <span className="block font-heading text-fluid-base font-semibold text-ink">
                      {c.name}
                    </span>
                    <span className="mt-1 block text-fluid-xs leading-snug text-muted">
                      {sizeSystemLabel[c.size_system] ?? c.size_system}
                    </span>
                    <span className="tabular mt-3 block text-fluid-xs text-muted">
                      {c.product_count}{" "}
                      {c.product_count === 1 ? "producto" : "productos"}
                    </span>
                  </span>
                </a>
              </Reveal>
            </li>
          ))}
        </ul>
      </div>
    </section>
  );
}
