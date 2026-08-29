import { ArrowRight } from "lucide-react";
import { products, totals } from "@/lib/catalog";
import { Garment, garmentBySlug, type GarmentKind } from "./garment";
import { Reveal } from "./reveal";

/**
 * Un perchero con tres prendas del catálogo.
 *
 * Las ilustraciones se rellenan con el `color_hex` real de cada variante y van
 * etiquetadas con su SKU: es una imagen de verdad, pero sigue siendo el dato.
 */
const percha = ["chaqueta-trucker-mezclilla", "camisa-oxford-manga-larga", "pantalon-chino-slim"]
  .map((slug) => products.find((p) => p.slug === slug))
  .filter((p): p is NonNullable<typeof p> => Boolean(p))
  .map((p) => ({
    kind: (garmentBySlug[p.slug] ?? "tee") as GarmentKind,
    hex: p.sample_variant?.color.hex ?? null,
    sku: p.sample_variant?.sku ?? "",
    color: p.sample_variant?.color.name ?? "",
  }));

export function Hero() {
  return (
    <section id="top" className="relative overflow-hidden bg-surface">
      <div className="grain absolute inset-0" aria-hidden />

      <div className="relative mx-auto grid max-w-6xl gap-12 px-5 pb-section pt-16 sm:px-8 lg:grid-cols-12 lg:gap-16 lg:pt-24">
        {/* Columna de texto: 7 de 12. Nada está centrado, y el desequilibrio
            es el punto. */}
        <div className="lg:col-span-7 lg:pt-6">
          <Reveal>
            <p className="text-fluid-xs font-medium uppercase tracking-[0.32em] text-muted">
              Medellín · desde 2026
            </p>
          </Reveal>

          <Reveal delay={0.06}>
            <h1 className="mt-6 font-heading text-fluid-2xl font-semibold leading-[0.95] tracking-tight text-ink">
              Ropa que
              <br />
              <span className="text-nogal">envejece bien.</span>
            </h1>
          </Reveal>

          <Reveal delay={0.12}>
            <p className="mt-7 max-w-[52ch] text-fluid-base leading-relaxed text-muted">
              Oxford de algodón peinado. Mezclilla de catorce onzas que los
              primeros días es dura y a las tres semanas es otro pantalón. Cuero
              curtido al vegetal que se oscurece donde lo tocas.
            </p>
          </Reveal>

          <Reveal delay={0.18}>
            <div className="mt-9 flex flex-wrap items-center gap-3">
              <a
                href="#coleccion"
                className="press inline-flex min-h-11 items-center gap-2 rounded-full bg-nogal px-7 text-fluid-sm font-medium text-background hover:bg-ink"
              >
                Ver la colección
                <ArrowRight size={16} strokeWidth={2} aria-hidden />
              </a>
              <a
                href="#bajo-el-capo"
                className="press inline-flex min-h-11 items-center rounded-full border border-line px-7 text-fluid-sm font-medium text-foreground hover:border-nogal hover:text-nogal"
              >
                Cómo está construido
              </a>
            </div>
          </Reveal>

          <Reveal delay={0.24}>
            <dl className="mt-14 grid max-w-md grid-cols-3 gap-6 border-t border-line pt-7">
              {[
                { n: totals.products, l: "productos" },
                { n: totals.variants, l: "variantes" },
                { n: totals.units, l: "unidades" },
              ].map((s) => (
                <div key={s.l}>
                  <dt className="sr-only">{s.l}</dt>
                  <dd>
                    <span className="tabular block font-heading text-fluid-lg font-semibold text-ink">
                      {s.n}
                    </span>
                    <span className="text-fluid-xs uppercase tracking-[0.18em] text-muted">
                      {s.l}
                    </span>
                  </dd>
                </div>
              ))}
            </dl>
          </Reveal>
        </div>

        {/* Columna visual: 5 de 12. */}
        <Reveal delay={0.1} className="lg:col-span-5">
          <div className="relative h-full min-h-[26rem] overflow-hidden rounded-sm border border-line bg-background lg:min-h-[34rem]">
            <div className="grain absolute inset-0" aria-hidden />

            {/* La barra del perchero */}
            <div
              className="absolute left-6 right-6 top-14 h-px bg-line"
              aria-hidden
            />

            <div className="relative flex h-full items-stretch gap-1 px-4 pt-14">
              {percha.map((g) => (
                <div key={g.sku} className="flex min-w-0 flex-1 flex-col">
                  {/* El gancho */}
                  <svg
                    viewBox="0 0 24 34"
                    className="mx-auto h-8 w-6 shrink-0"
                    aria-hidden
                  >
                    <path
                      d="M12 4c4 0 4 6 0 6v10"
                      fill="none"
                      stroke="var(--linea)"
                      strokeWidth="2"
                      strokeLinecap="round"
                    />
                    <path
                      d="M4 30 12 20l8 10Z"
                      fill="none"
                      stroke="var(--linea)"
                      strokeWidth="2"
                      strokeLinejoin="round"
                    />
                  </svg>

                  <Garment
                    kind={g.kind}
                    color={g.hex}
                    className="min-h-0 w-full flex-1"
                  />

                  <span className="tabular mt-2 truncate pb-5 text-center text-[10px] uppercase tracking-[0.1em] text-muted">
                    {g.sku}
                  </span>
                </div>
              ))}
            </div>
          </div>
        </Reveal>
      </div>
    </section>
  );
}
