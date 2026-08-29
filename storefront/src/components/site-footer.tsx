import { categories } from "@/lib/catalog";

export function SiteFooter() {
  return (
    <footer className="relative overflow-hidden border-t border-line bg-ink text-background/70">
      <div className="grain absolute inset-0" aria-hidden />

      <div className="relative mx-auto max-w-6xl px-5 py-16 sm:px-8">
        <div className="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
          <div className="lg:col-span-2">
            <p className="font-heading text-lg font-semibold tracking-[0.22em] text-background">
              NOGAL
            </p>
            <p className="mt-4 max-w-[42ch] text-fluid-sm leading-relaxed">
              Ropa masculina en Medellín. Proyecto académico del curso de
              Ingeniería de Contexto con sistemas de IA: un backend de ecommerce
              en Laravel 12 con su escaparate.
            </p>
          </div>

          <nav aria-label="Categorías">
            <h2 className="text-fluid-xs uppercase tracking-[0.2em] text-caramel">
              Categorías
            </h2>
            <ul className="mt-2">
              {categories.map((c) => (
                <li key={c.slug}>
                  <a
                    href="#coleccion"
                    className="press flex min-h-11 items-center text-fluid-sm hover:text-background"
                  >
                    {c.name}
                  </a>
                </li>
              ))}
            </ul>
          </nav>

          <nav aria-label="Proyecto">
            <h2 className="text-fluid-xs uppercase tracking-[0.2em] text-caramel">
              El proyecto
            </h2>
            <ul className="mt-2">
              <li>
                <a
                  href="https://github.com/Rolando-Fonseca/P7-Laravel-Ecommerce"
                  className="press flex min-h-11 items-center text-fluid-sm hover:text-background"
                >
                  Repositorio
                </a>
              </li>
              <li>
                <a
                  href="https://github.com/Rolando-Fonseca/P7-Laravel-Ecommerce/blob/main/docs/api/contracts/README.md"
                  className="press flex min-h-11 items-center text-fluid-sm hover:text-background"
                >
                  Contratos de la API
                </a>
              </li>
              <li>
                <a
                  href="https://github.com/Rolando-Fonseca/P7-Laravel-Ecommerce/blob/main/docs/adr/README.md"
                  className="press flex min-h-11 items-center text-fluid-sm hover:text-background"
                >
                  Decisiones (ADR)
                </a>
              </li>
              <li>
                <a
                  href="#bajo-el-capo"
                  className="press flex min-h-11 items-center text-fluid-sm hover:text-background"
                >
                  Bajo el capó
                </a>
              </li>
            </ul>
          </nav>
        </div>

        <div className="mt-14 border-t border-background/15 pt-8 text-center">
          <p className="text-fluid-xs text-background/65">
            Built with Claude Web Builder by{" "}
            <a
              href="https://tododeia.com"
              className="press underline underline-offset-4 hover:text-background"
            >
              Tododeia
            </a>
          </p>
        </div>
      </div>
    </footer>
  );
}
