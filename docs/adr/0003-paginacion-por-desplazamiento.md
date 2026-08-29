# ADR-0003 — Paginación por desplazamiento, no por cursor

- **Estado:** Aceptado
- **Fecha:** 2026-08-29
- **Decide:** Equipo P7
- **Relacionados:** `docs/api/contracts/00-convenciones.md`

## Contexto

Todas las colecciones necesitan paginación. Las dos familias son:

- **Desplazamiento** (`page` + `per_page`): permite saltar a cualquier página y conocer
  el total.
- **Cursor** (`after=<token>`): más eficiente en tablas grandes y estable ante inserciones,
  pero no permite saltar ni contar.

El catálogo de Nogal ronda los 200 productos y 3.000 variantes. El historial de pedidos
por usuario rara vez pasa de 50.

## Decisión

**Paginación por desplazamiento** en todos los endpoints, con el bloque `meta` y `links`
definido en las convenciones.

## Alternativas consideradas

| Alternativa | Por qué no |
|---|---|
| Cursor en todo | La tienda necesita `total_pages` para pintar "1 2 3 ... 10" y un cursor no lo da. Optimiza un problema de escala que este catálogo no tiene. |
| Mixto: cursor en el libro mayor de inventario, desplazamiento en el resto | Dos formatos de paginación en la misma API es exactamente la incoherencia que las convenciones existen para evitar. |

## Consecuencias

### Positivas
- El cliente puede saltar a cualquier página y mostrar el total de resultados.
- Un único bloque `meta` y `links` idéntico en toda la API.
- Trivial de implementar con `paginate()` de Laravel y trivial de testear.

### Negativas
- **`COUNT(*)` en cada petición.** Con el filtro `in_stock` activo esa cuenta recorre
  `inventory_items`. Con 3.000 variantes es irrelevante; con 300.000 sería el cuello de
  botella del endpoint.
- **Resultados inestables ante inserciones.** Si entra un producto nuevo mientras el
  usuario pasa de la página 1 a la 2, un producto de la 1 se desplaza a la 2 y lo ve dos
  veces. Se mitiga con `sort=-created_at` por defecto, que empuja lo nuevo al principio.
- `inventory_movements` crece sin límite y es el primero que sufrirá.

## Revisión

Se reabre para `GET /admin/inventory/movements` cuando esa tabla pase de **1 millón de
filas** o cuando el p95 de ese endpoint supere 500 ms. En ese caso se añade paginación por
cursor **solo ahí**, con su propio ADR que supersede parcialmente a este.
