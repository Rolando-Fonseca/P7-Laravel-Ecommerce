# ADR-0006 — Bloqueo pesimista sobre el inventario, con orden fijo

- **Estado:** Aceptado
- **Fecha:** 2026-08-29
- **Decide:** Equipo P7
- **Relacionados:** ADR-0004, ADR-0005, `docs/domain/02-inventario.md`

## Contexto

Crear un pedido lee `available` y después escribe `quantity_reserved`. Entre la lectura y
la escritura, otra petición puede hacer lo mismo. Es el patrón *lost update*:

```
Peticion A                      Peticion B
SELECT available -> 1
                                SELECT available -> 1
UPDATE reserved = 1
                                UPDATE reserved = 2    <-- se vendieron 2 de 1
```

No es teórico. Con la última unidad de una talla popular y dos clientes en la pantalla de
pago, ocurre.

## Decisión

Tres capas, todas obligatorias:

1. **Transacción única.** Toda la creación del pedido dentro de un solo
   `DB::transaction()`. Nada de transacciones anidadas ni de escrituras fuera.

2. **`lockForUpdate()` sobre `inventory_items`, ordenados por `id` ascendente.**
   ```php
   $items = InventoryItem::whereIn('product_variant_id', $variantIds)
       ->orderBy('id')          // el orden fijo es la parte importante
       ->lockForUpdate()
       ->get();
   ```
   El orden fijo evita interbloqueos: si el pedido A bloquea la variante 10 y luego la 20,
   y el pedido B bloquea la 20 y luego la 10, se esperan mutuamente para siempre. Con
   `ORDER BY id` los dos las piden en el mismo orden y uno simplemente espera al otro.

3. **Restricciones `CHECK` en la base de datos** como última red:
   ```sql
   CHECK (quantity_on_hand  >= 0)
   CHECK (quantity_reserved >= 0)
   CHECK (quantity_reserved <= quantity_on_hand)
   ```

## Alternativas consideradas

| Alternativa | Por qué no |
|---|---|
| **Bloqueo optimista** (columna `version`, reintento al chocar) | Funciona, pero obliga a implementar la lógica de reintento en cada punto de escritura y a decidir cuántas veces reintentar. Con un pedido de 5 líneas, la probabilidad de choque se multiplica. |
| `UPDATE ... WHERE available >= ?` sin bloqueo previo | Atómico para **una** línea. Un pedido tiene varias y hay que validarlas todas antes de escribir ninguna. |
| Solo las restricciones `CHECK` | La base de datos rechaza, pero con un error de driver ilegible que hay que traducir a `409` adivinando cuál línea falló. |
| Cola serializada de pedidos | Elimina la concurrencia y con ella el problema, a cambio de convertir la creación de pedidos en asíncrona. Cambia el contrato entero. |

## Consecuencias

### Positivas
- Correcto por construcción: la lectura y la escritura ocurren bajo el mismo bloqueo.
- Sin lógica de reintento en el código de aplicación.
- El orden fijo por `id` elimina los interbloqueos, que son el fallo más difícil de
  reproducir en pruebas.
- Las `CHECK` protegen aunque una refactorización futura olvide el bloqueo.

### Negativas
- **Los pedidos que comparten variantes se serializan.** Con la última unidad de una talla
  muy demandada, todas las peticiones hacen cola.
- Las transacciones son más largas y hay que vigilar el timeout de bloqueo de MySQL
  (`innodb_lock_wait_timeout`, 50 s por defecto).
- **SQLite bloquea la base de datos entera**, no la fila. En desarrollo y en tests el
  comportamiento no es representativo: los tests de concurrencia real necesitan MySQL.
- Un bloqueo olvidado en una refactorización futura no rompe ningún test; solo falla en
  producción bajo carga. De ahí que las `CHECK` no sean opcionales.

## Revisión

Se reabre si el tiempo medio de espera de bloqueo en `POST /orders` supera **200 ms** en
producción. La salida natural sería mover la reserva a una cola por variante, no cambiar a
bloqueo optimista.
