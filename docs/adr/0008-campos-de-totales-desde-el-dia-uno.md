# ADR-0008 — Los campos de descuento, envío e impuesto existen desde el día uno valiendo 0

- **Estado:** Aceptado
- **Fecha:** 2026-08-29
- **Decide:** Equipo P7
- **Relacionados:** ADR-0002, `docs/api/contracts/03-cart.md`

## Contexto

El MVP no calcula impuestos, no cobra envío y no aplica cupones. Todos son extensiones
previstas y ninguna se implementa ahora.

La tentación es devolver solo lo que existe:

```json
{ "totals": { "subtotal_cents": 3780000, "total_cents": 3780000 } }
```

El problema aparece después. Cuando entre el envío, la respuesta pasa a tener un campo
nuevo. Un cliente que hacía `Object.keys(totals)` o que validaba la forma con un esquema
estricto se rompe. Y por ADR-0002 eso obliga a subir a `v2`... por añadir un campo.

## Decisión

El bloque `totals` expone **los cinco campos desde el primer día**:

```json
{
  "totals": {
    "subtotal_cents": 3780000,
    "discount_cents": 0,
    "shipping_cents": 0,
    "tax_cents": 0,
    "total_cents": 3780000
  }
}
```

`discount_cents`, `shipping_cents` y `tax_cents` valen **siempre 0** en el MVP. Las
columnas correspondientes existen en `carts` y `orders` con `default 0`.

La fórmula está fijada y documentada aunque hoy sea trivial:

```
total_cents = subtotal_cents - discount_cents + shipping_cents + tax_cents
```

Se implementa así, con las cuatro operaciones, no como `total = subtotal`.

## Alternativas consideradas

| Alternativa | Por qué no |
|---|---|
| Devolver solo lo que existe | Añadirlos después es un cambio de forma de la respuesta. Con la regla de versionado de ADR-0002, eso es `v2` por un campo con valor cero. |
| Devolverlos como `null` | `null` obliga al cliente a decidir si es "no aplica" o "no calculado". `0` es un valor real y sumable. |
| Un objeto `adjustments` abierto | Flexible y sin contrato: el cliente no puede saber qué claves esperar y no puede pintar la factura. |

## Consecuencias

### Positivas
- Activar envío o impuestos es cambiar un cálculo, no el contrato. No hay `v2`.
- El cliente puede pintar el desglose completo de la factura desde ahora, con líneas en 0
  o escondidas según prefiera.
- La fórmula escrita con las cuatro operaciones evita la refactorización del día en que
  deje de ser trivial.

### Negativas
- **Campos que hoy no significan nada.** Un integrador nuevo pregunta por qué `tax_cents`
  siempre vale 0, y hay que explicárselo cada vez.
- Cuatro columnas con `default 0` que ningún código escribe. Un lector del esquema puede
  creer que hay lógica de descuentos y buscarla sin encontrarla.
- Riesgo de que alguien "aproveche" un campo para otra cosa. Se mitiga con el comentario
  en la migración.

## Revisión

No requiere revisión: los campos ya están. Cuando se implemente el cálculo real de
impuestos entra su propio ADR, y este seguirá vigente.
