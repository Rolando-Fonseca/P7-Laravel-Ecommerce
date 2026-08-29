import { ArrowRight } from "lucide-react";
import { products } from "@/lib/catalog";
import { Reveal } from "./reveal";

const otono = products
  .filter((p) => ["chaquetas", "pantalones"].includes(p.category.slug))
  .slice(0, 3);

export function CollectionBanner() {
  return (
    <section id="otono" className="relative overflow-hidden bg-ink text-background">
      <div className="grain absolute inset-0" aria-hidden />

      <div className="relative mx-auto grid max-w-6xl items-center gap-10 px-5 py-section sm:px-8 lg:grid-cols-12 lg:gap-16">
        <div className="lg:col-span-6">
          <Reveal>
            <p className="text-fluid-xs uppercase tracking-[0.32em] text-caramel">
              Colección otoño
            </p>
            <h2 className="mt-5 font-heading text-fluid-xl font-semibold leading-tight">
              Capas para cuando
              <br />
              baja la temperatura.
            </h2>
            <p className="mt-6 max-w-[48ch] text-fluid-base leading-relaxed text-background/70">
              Trucker de doce onzas, chino con dos por ciento de elastano y jean
              rígido. Tres prendas que se ponen mejor cuanto más las usas, que es
              exactamente lo contrario de lo que hace la ropa barata.
            </p>
            <a
              href="#coleccion"
              className="press mt-8 inline-flex min-h-11 items-center gap-2 rounded-full bg-caramel px-7 text-fluid-sm font-medium text-ink hover:bg-background"
            >
              Ver las prendas
              <ArrowRight size={16} strokeWidth={2} aria-hidden />
            </a>
          </Reveal>
        </div>

        <div className="lg:col-span-6">
          <ul className="grid gap-px overflow-hidden rounded-sm bg-background/15">
            {otono.map((p, i) => (
              <li key={p.slug}>
                <Reveal delay={i * 0.06}>
                  <div className="flex items-center gap-5 bg-ink p-5">
                    <span
                      className="h-14 w-14 shrink-0 rounded-sm"
                      style={{
                        backgroundColor:
                          p.sample_variant?.color.hex ?? "#8a6a4a",
                      }}
                      aria-hidden
                    />
                    <span className="min-w-0 flex-1">
                      <span className="block truncate font-heading text-fluid-base font-medium">
                        {p.name}
                      </span>
                      <span className="mt-1 block text-fluid-xs text-background/55">
                        {p.material}
                      </span>
                    </span>
                    <span className="tabular shrink-0 text-fluid-xs uppercase tracking-[0.14em] text-caramel">
                      {p.available_sizes.length} tallas
                    </span>
                  </div>
                </Reveal>
              </li>
            ))}
          </ul>
        </div>
      </div>
    </section>
  );
}
