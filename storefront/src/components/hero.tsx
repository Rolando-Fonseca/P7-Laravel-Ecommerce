import Image from "next/image";
import { ArrowRight } from "lucide-react";
import { totals } from "@/lib/catalog";
import { Reveal } from "./reveal";

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
            <Image
              src="/images/hero-nogal.webp"
              alt="Chaqueta trucker de mezclilla, camisa Oxford azul cielo, pantalón chino beige y cinturón de cuero, doblados y dispuestos sobre una superficie de lino."
              fill
              sizes="(min-width: 1024px) 40vw, 100vw"
              className="object-cover"
              // Es el elemento más grande de la primera pantalla: sin priority,
              // el LCP lo paga.
              priority
            />
          </div>
        </Reveal>
      </div>
    </section>
  );
}
