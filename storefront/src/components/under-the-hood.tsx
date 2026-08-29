import { Reveal } from "./reveal";

const stats = [
  { n: "18", l: "endpoints" },
  { n: "12", l: "tablas" },
  { n: "80", l: "tests" },
  { n: "94.4%", l: "cobertura" },
  { n: "10", l: "ADRs" },
];

const niveles = [
  {
    nivel: "Producto",
    pregunta: "¿Qué es?",
    ejemplo: "Camisa Oxford, 100% algodón peinado",
    tabla: "products",
  },
  {
    nivel: "Variante",
    pregunta: "¿Cuál exactamente?",
    ejemplo: "Azul cielo / M → NGL-CAM-OXF-AZC-M",
    tabla: "product_variants",
  },
  {
    nivel: "Existencia",
    pregunta: "¿Cuántas y dónde?",
    ejemplo: "9 en mano, 2 reservadas, en NGL-CEN",
    tabla: "inventory_items",
  },
];

const estados = [
  "created",
  "paid",
  "packed",
  "shipped",
];

const errorJson = `{
  "error": {
    "code": "INSUFFICIENT_STOCK",
    "message": "No hay unidades suficientes para completar el pedido.",
    "details": [
      {
        "field": "items.1.quantity",
        "issue": "solicitado 3, disponible 1",
        "meta": { "sku": "NGL-PAN-CHI-BEI-32", "available": 1 }
      }
    ],
    "traceId": "01JBQ9M2P4R6T8V0X2Z4B6D8F0"
  }
}`;

export function UnderTheHood() {
  return (
    <section
      id="bajo-el-capo"
      className="relative overflow-hidden bg-surface-2"
    >
      <div className="grain absolute inset-0" aria-hidden />

      <div className="relative mx-auto max-w-6xl px-5 py-section sm:px-8">
        <Reveal>
          <p className="text-fluid-xs uppercase tracking-[0.32em] text-nogal">
            Bajo el capó
          </p>
          <h2 className="mt-5 max-w-[22ch] font-heading text-fluid-xl font-semibold leading-tight text-ink">
            Esta tienda es la cara de una API en Laravel 12.
          </h2>
          <p className="mt-6 max-w-[62ch] text-fluid-base leading-relaxed text-muted">
            El catálogo que acabas de ver no está escrito en el frontend. Sale de
            la misma base de datos que sirve la API, exportado con{" "}
            <code className="rounded-sm bg-background px-1.5 py-0.5 text-fluid-sm text-nogal">
              php artisan catalog:export
            </code>
            . Vercel no ejecuta PHP, así que el escaparate es estático — pero los
            datos son reales.
          </p>
        </Reveal>

        <Reveal delay={0.08}>
          <dl className="mt-12 grid grid-cols-2 gap-px overflow-hidden rounded-sm border border-line bg-line sm:grid-cols-5">
            {stats.map((s) => (
              <div key={s.l} className="bg-background px-5 py-6">
                <dt className="text-fluid-xs uppercase tracking-[0.18em] text-muted">
                  {s.l}
                </dt>
                <dd className="tabular mt-2 font-heading text-fluid-lg font-semibold text-ink">
                  {s.n}
                </dd>
              </div>
            ))}
          </dl>
        </Reveal>

        <div className="mt-16 grid gap-12 lg:grid-cols-12 lg:gap-14">
          {/* Producto / Variante / Existencia */}
          <div className="lg:col-span-7">
            <Reveal>
              <h3 className="font-heading text-fluid-lg font-semibold text-ink">
                Tres niveles, tres preguntas
              </h3>
              <p className="mt-3 max-w-[58ch] text-fluid-sm leading-relaxed text-muted">
                El stock no vive en <code>products</code>. Una camisa Oxford no
                tiene existencias; &laquo;Oxford / Azul cielo / M&raquo; sí. Y
                tampoco vive en la variante: vive en el cruce entre variante y
                almacén, para que abrir una segunda bodega no obligue a migrar en
                producción.
              </p>
            </Reveal>

            <ol className="mt-8 space-y-px overflow-hidden rounded-sm border border-line bg-line">
              {niveles.map((n, i) => (
                <li key={n.nivel}>
                  <Reveal delay={i * 0.06}>
                    <div className="bg-background p-5">
                      <div className="flex flex-wrap items-baseline justify-between gap-3">
                        <span className="font-heading text-fluid-base font-semibold text-ink">
                          {n.nivel}
                        </span>
                        <code className="tabular text-fluid-xs text-nogal">
                          {n.tabla}
                        </code>
                      </div>
                      <p className="mt-1 text-fluid-sm text-caramel">
                        {n.pregunta}
                      </p>
                      <p className="tabular mt-2 text-fluid-sm text-muted">
                        {n.ejemplo}
                      </p>
                    </div>
                  </Reveal>
                </li>
              ))}
            </ol>

            <Reveal delay={0.2}>
              <p className="mt-6 max-w-[58ch] text-fluid-sm leading-relaxed text-muted">
                <strong className="font-medium text-ink">
                  Lo disponible es un cálculo, nunca una columna.
                </strong>{" "}
                Si fuera columna sería un tercer dato que puede desincronizarse
                de los otros dos, y no habría forma de saber cuál de los tres
                miente.
              </p>
            </Reveal>
          </div>

          {/* Máquina de estados + error */}
          <div className="lg:col-span-5">
            <Reveal>
              <h3 className="font-heading text-fluid-lg font-semibold text-ink">
                El pedido y su stock
              </h3>
            </Reveal>

            <Reveal delay={0.06}>
              <ol className="mt-6 space-y-3">
                {estados.map((e, i) => (
                  <li key={e} className="flex items-center gap-3">
                    <span className="tabular w-6 shrink-0 text-fluid-xs text-muted">
                      {String(i + 1).padStart(2, "0")}
                    </span>
                    <code className="flex-1 rounded-sm border border-line bg-background px-3 py-2 text-fluid-sm text-ink">
                      {e}
                    </code>
                  </li>
                ))}
                <li className="flex items-center gap-3 pt-1">
                  <span className="w-6 shrink-0" aria-hidden />
                  <span className="flex-1 text-fluid-xs text-muted">
                    más <code className="text-nogal">cancelled</code> y{" "}
                    <code className="text-nogal">returned</code>, ambos
                    terminales
                  </span>
                </li>
              </ol>
            </Reveal>

            <Reveal delay={0.12}>
              <p className="mt-6 text-fluid-sm leading-relaxed text-muted">
                El stock físico se descuenta al{" "}
                <strong className="font-medium text-ink">despachar</strong>, no
                al pagar. Mientras el paquete está en la bodega la unidad existe:
                está reservada, no vendida. Confundir esos dos momentos es el
                motivo más frecuente de descuadres de inventario.
              </p>
            </Reveal>
          </div>
        </div>

        {/* Formato de error */}
        <Reveal delay={0.08}>
          <div className="mt-16 grid gap-8 lg:grid-cols-12 lg:gap-14">
            <div className="lg:col-span-5">
              <h3 className="font-heading text-fluid-lg font-semibold text-ink">
                Un solo formato de error
              </h3>
              <p className="mt-3 text-fluid-sm leading-relaxed text-muted">
                Catorce códigos, siempre la misma envoltura y un{" "}
                <code className="text-nogal">traceId</code> que aparece también
                en el log y en la cabecera de la respuesta. Cuando un cliente
                dice &laquo;me falló ayer&raquo;, ese identificador es lo que
                hace el reporte investigable.
              </p>
              <p className="mt-4 text-fluid-sm leading-relaxed text-muted">
                <code className="text-nogal">details</code> es siempre un array,
                aunque tenga un solo elemento: un objeto no puede expresar dos
                errores sobre el mismo campo, y un carrito de doce líneas los
                produce constantemente.
              </p>
            </div>

            <div className="lg:col-span-7">
              <pre className="overflow-x-auto rounded-sm border border-line bg-ink p-5 text-[12px] leading-relaxed text-background/85">
                <code>{errorJson}</code>
              </pre>
            </div>
          </div>
        </Reveal>

        <Reveal delay={0.1}>
          <div className="mt-14 flex flex-wrap gap-3 border-t border-line pt-8">
            <a
              href="https://github.com/Rolando-Fonseca/P7-Laravel-Ecommerce"
              className="press inline-flex min-h-11 items-center rounded-full bg-nogal px-7 text-fluid-sm font-medium text-background hover:bg-ink"
            >
              Ver el código en GitHub
            </a>
            <a
              href="https://github.com/Rolando-Fonseca/P7-Laravel-Ecommerce/blob/main/docs/adr/README.md"
              className="press inline-flex min-h-11 items-center rounded-full border border-line px-7 text-fluid-sm font-medium text-foreground hover:border-nogal hover:text-nogal"
            >
              Leer las diez decisiones
            </a>
          </div>
        </Reveal>
      </div>
    </section>
  );
}
