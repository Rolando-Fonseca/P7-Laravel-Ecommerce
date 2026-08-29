# ADR-0004 — Idempotencia obligatoria al crear pedidos

- **Estado:** Aceptado
- **Fecha:** 2026-08-29
- **Decide:** Equipo P7
- **Relacionados:** ADR-0006, `docs/domain/04-pedidos.md`

> **Este es el ADR más crítico del proyecto para evitar deuda técnica temprana.**
> Añadir idempotencia después obliga a migrar datos, cambiar el contrato de forma
> incompatible y auditar los pedidos duplicados que ya se crearon.

## Contexto

`POST /api/v1/orders` crea un pedido y reserva stock. Es una operación no idempotente por
naturaleza: llamarla dos veces produce dos pedidos y reserva el doble de unidades.

Las tres formas en que esto ocurre son cotidianas, no excepcionales:

1. El usuario hace doble clic en "confirmar pedido".
2. La red pierde la respuesta y el cliente reintenta automáticamente. El servidor **ya
   había creado el pedido**; el cliente no lo sabe.
3. Un balanceador o un cliente HTTP con reintentos configurados repite la petición ante
   un timeout.

En los tres casos el resultado sin protección es idéntico: dos pedidos, doble reserva de
stock, un cliente que reclama y un inventario que no cuadra.

## Decisión

`POST /api/v1/orders` y `POST /api/v1/admin/orders/{number}/transitions` **exigen** el
header `Idempotency-Key` (ULID o UUID v4 generado por el cliente).

Tabla `idempotency_keys`: `key`, `endpoint`, `request_hash`, `response_status`,
`response_body`, `locked_at`, `created_at`, con `UNIQUE(key, endpoint)` y retención de
24 horas.

| Situación | Respuesta |
|---|---|
| Clave nueva | Se procesa y se guarda `(status, body)` |
| Misma clave, mismo cuerpo | La respuesta guardada + `Idempotency-Replayed: true` |
| Misma clave, cuerpo distinto | `409 IDEMPOTENCY_KEY_REUSED` |
| Clave con `locked_at` reciente | `409 IDEMPOTENCY_IN_PROGRESS` |
| Sin header | `400 IDEMPOTENCY_KEY_REQUIRED` |

El `request_hash` es `sha256` del JSON **normalizado** (claves ordenadas, sin espacios en
blanco irrelevantes), no del texto crudo: un espacio de más no debe contar como cuerpo
distinto.

## Alternativas consideradas

| Alternativa | Por qué no |
|---|---|
| Deduplicar por `cart_token` | Solo cubre el caso del doble clic. Un reintento tras un timeout llega con el carrito ya `converted` y devuelve `409`, que el cliente interpreta como fallo real cuando en realidad su pedido sí se creó. |
| Header opcional en vez de obligatorio | Los clientes no lo mandan. Un mecanismo de seguridad que hay que recordar activar no protege nada. |
| Bloqueo distribuido por `(usuario, carrito)` | Cubre la concurrencia pero no la repetición: dos peticiones separadas por 5 segundos pasan las dos. |
| Nada en el MVP, se añade después | El coste de añadirlo después no es escribir el código: es migrar la tabla, romper el contrato y limpiar los duplicados ya creados. |

## Consecuencias

### Positivas
- Un doble clic o un reintento nunca crea un segundo pedido.
- El cliente puede reintentar con seguridad ante cualquier error de red.
- Una clave reutilizada con otro cuerpo falla ruidosamente: los bugs del cliente se
  detectan en desarrollo, no en producción.
- El mismo mecanismo protege los ajustes de inventario.

### Negativas
- **Todo cliente debe generar y conservar la clave.** Es carga de integración, y es la
  causa número uno de que un cliente nuevo no consiga crear su primer pedido.
- Una tabla más, con su escritura en cada pedido y su purga programada.
- `response_body` guarda JSON completo: la tabla crece rápido. La retención de 24 h no es
  opcional.
- La ventana de 24 h es arbitraria. Un reintento a las 25 horas crea un pedido duplicado.
  Se acepta: ningún cliente razonable reintenta un día después.

## Revisión

Se reabre si aparecen pedidos duplicados en producción pese al mecanismo, o si la tabla
`idempotency_keys` supera el 10% del tamaño de la base de datos. En el segundo caso, mover
el almacenamiento a Redis con TTL nativo.
