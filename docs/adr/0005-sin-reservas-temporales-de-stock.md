# ADR-0005 — El carrito no reserva stock

- **Estado:** Aceptado
- **Fecha:** 2026-08-29
- **Decide:** Equipo P7
- **Relacionados:** ADR-0004, ADR-0006, `docs/domain/02-inventario.md`

## Contexto

Había que decidir en qué momento del recorrido del cliente se compromete una unidad de
inventario. Las dos opciones extremas:

- **Al añadir al carrito.** El stock se aparta con un plazo (típicamente 15 minutos). Es
  lo que hacen las plataformas de venta de entradas.
- **Al crear el pedido.** El carrito es una lista de deseos y nada se aparta hasta
  confirmar.

Nogal es un catálogo de moda de reposición continua, sin lanzamientos con demanda
concentrada en una ventana de minutos.

## Decisión

**El carrito no reserva nada.** `POST /carts/{token}/items` no consulta stock ni lo
compromete. El stock se reserva **únicamente** al crear el pedido, dentro de la
transacción descrita en `docs/domain/04-pedidos.md`.

`quantity_reserved` existe en el modelo desde el día uno, pero solo lo mueven los pedidos.

## Alternativas consideradas

| Alternativa | Por qué no |
|---|---|
| Reserva con caducidad de 15 min | Exige un proceso de expiración, un planificador y una tabla de reservas. Introduce **stock fantasma**: unidades bloqueadas por carritos que nadie va a confirmar, invisibles para el resto de clientes. |
| Validar stock al añadir, sin reservar | Da falsa seguridad: el usuario ve "disponible" y falla igual al confirmar, solo que ahora cree que el sistema le mintió. |
| Reserva solo para productos con menos de 5 unidades | La regla condicional es peor que cualquiera de las dos puras: el comportamiento del carrito depende de un umbral invisible para el usuario. |

## Consecuencias

### Positivas
- Sin planificador, sin proceso de expiración, sin tabla de reservas. El MVP es
  significativamente más pequeño.
- Sin stock fantasma: todo lo que aparece disponible se puede comprar de verdad.
- Un solo punto en el código donde el stock cambia por venta. Un solo sitio que auditar.
- El carrito nunca falla por inventario, lo que simplifica su contrato: no existe
  `INSUFFICIENT_STOCK` en `POST /carts/{token}/items`.

### Negativas
- **El cliente puede llegar a la pantalla de confirmación y llevarse un `409`.** Es la
  peor experiencia posible en el peor momento posible del embudo.
- En un lanzamiento con demanda concentrada, muchos clientes fallarían a la vez.
- El campo `available` del carrito es informativo y puede estar obsoleto en el instante
  siguiente. Un cliente que confíe en él se equivocará.

### Mitigación
`GET /carts/{token}` devuelve `available` y `has_enough_stock` por línea, para que la
interfaz avise **antes** de la pantalla de pago. Y el `409 INSUFFICIENT_STOCK` lista
**todas** las líneas problemáticas de una vez, no una por una.

## Revisión

Se reabre cuando ocurra cualquiera de estas dos señales:

1. Nogal programe un lanzamiento con stock limitado y demanda concentrada.
2. La tasa de `409 INSUFFICIENT_STOCK` sobre `POST /orders` supere el **2%**.

Métrica a instrumentar desde el primer despliegue: `orders.insufficient_stock_rate`.
Sin ese número, la revisión será una discusión de opiniones.
