# Plan de pruebas

## Principio

**Los contratos primero.** Un test que verifica que la respuesta tiene la forma
documentada protege a todos los clientes de la API. Un test que verifica que un método
privado devuelve lo que devuelve no protege a nadie y se rompe en cada refactorización.

## Orden de prioridad

| # | Tipo | Qué verifica | Ejemplo |
|---|---|---|---|
| 1 | **Contrato** | Forma exacta de la respuesta y códigos de error de la tabla | `GET /products` devuelve `data`, `meta`, `links` con las claves documentadas |
| 2 | **Invariante de dominio** | Reglas que nunca pueden romperse | `reserved` nunca supera `on_hand` |
| 3 | **Camino feliz** | El flujo completo de negocio | listar, añadir al carrito, crear pedido, consultar |
| 4 | **Borde** | Límites, valores extremos, concurrencia | `quantity = 20` pasa, `21` es `422` |

## Cobertura exigida

| Ámbito | Mínimo | Motivo |
|---|---|---|
| Global | **70%** | Umbral del proyecto en `.claude/settings.json` |
| `app/Domain/` | **85%** | Ahí vive todo lo que puede corromper datos |
| `app/Http/Controllers/` | 70% | Son delgados: validar, delegar, responder |
| `app/Models/` | sin mínimo | Probar que Eloquent guarda es probar Laravel |

## Matriz por módulo

### Catálogo

| Test | Tipo |
|---|---|
| El listado devuelve la estructura `data` + `meta` + `links` completa | contrato |
| `per_page` fuera de rango devuelve `422 VALIDATION_FAILED` | contrato |
| Un parámetro desconocido devuelve `422`, no se ignora | contrato |
| `sort` con un campo no permitido devuelve `422` | contrato |
| Los productos `draft` y `archived` no aparecen en el listado | invariante |
| Un producto sin variantes activas no aparece en el listado | invariante |
| **El filtro de precio considera el precio propio de la variante** | invariante |
| `in_stock=true` excluye productos con todo agotado | invariante |
| El detalle de un producto `archived` devuelve `404`, no `410` | contrato |
| El listado de 20 productos ejecuta un número acotado de consultas (sin N+1) | rendimiento |

### Inventario

| Test | Tipo |
|---|---|
| La consulta pública **no** expone `on_hand` ni `reserved` | contrato |
| La consulta en lote con un sku inexistente devuelve `available: null`, no `404` | contrato |
| El ajuste sin token devuelve `401`; con token sin `admin`, `403` | contrato |
| El ajuste sin `Idempotency-Key` devuelve `400` | contrato |
| El ajuste con la misma clave y mismo cuerpo no duplica el movimiento | invariante |
| El ajuste con la misma clave y cuerpo distinto devuelve `409` | contrato |
| **Un delta negativo que dejaría `on_hand < reserved` devuelve `409`** | invariante |
| `quantity_delta = 0` devuelve `422` | contrato |
| **Todo ajuste genera exactamente un `inventory_movement`** | invariante |
| **`SUM(quantity_delta)` de los movimientos coincide con `quantity_on_hand`** | invariante |

### Carrito

| Test | Tipo |
|---|---|
| Crear carrito devuelve token, `status: open` y totales en 0 | contrato |
| Los cinco campos de `totals` están presentes aunque valgan 0 | contrato |
| Añadir una variante ya presente **suma** cantidad, no crea otra línea | invariante |
| La suma que supera 20 devuelve `422` | contrato |
| **Añadir NO valida stock**: se pueden añadir 10 de las que quedan 2 | invariante (ADR-0005) |
| Una variante `archived` devuelve `409 VARIANT_UNAVAILABLE` | contrato |
| Un carrito `converted` que recibe un item devuelve `409` | contrato |
| Un carrito caducado devuelve `410` | contrato |
| **El precio no se recalcula**: cambiar el precio del producto no cambia el del carrito | invariante |
| Una línea de otro carrito devuelve `404`, no `403` | seguridad |
| `item_count` es la suma de cantidades, no el número de líneas | contrato |

### Pedidos — el módulo con más peso

| Test | Tipo |
|---|---|
| Crear pedido sin `Idempotency-Key` devuelve `400` | contrato |
| **Misma clave y mismo cuerpo devuelve el MISMO pedido con `Idempotency-Replayed: true`** | invariante |
| **La repetición idempotente NO reserva stock por segunda vez** | invariante |
| Misma clave con cuerpo distinto devuelve `409 IDEMPOTENCY_KEY_REUSED` | contrato |
| Un carrito vacío devuelve `422 CART_EMPTY` | contrato |
| **Stock insuficiente devuelve `409` listando TODAS las líneas que fallan** | contrato |
| **Si una línea falla, NO se crea el pedido ni se reserva nada** (atomicidad) | invariante |
| Crear pedido sube `quantity_reserved` en la cantidad exacta | invariante |
| Crear pedido marca el carrito como `converted` | invariante |
| **Los items copian `product_name` y `sku` en texto**: renombrar el producto no cambia el pedido | invariante |
| `total_cents` es la suma de `line_total_cents` | invariante |
| El número de pedido sigue el formato `NGL-AAAA-NNNNNN` | contrato |
| `allowed_transitions` coincide con la tabla del dominio | contrato |
| **Cada transición ilegal de la tabla devuelve `409 INVALID_STATE_TRANSITION`** | invariante |
| Cancelar libera la reserva y genera un movimiento `release` | invariante |
| Despachar descuenta `on_hand` **y** `reserved`, y genera `sale` | invariante |
| Una devolución sube `on_hand` y genera `return` | invariante |
| Consultar con un email que no coincide devuelve `404`, no `403` | seguridad |
| El historial sin token devuelve `401` | contrato |
| El historial solo devuelve los pedidos del usuario autenticado | seguridad |

### Concurrencia

| Test | Nota |
|---|---|
| Dos pedidos simultáneos de la última unidad: uno tiene éxito, el otro recibe `409` | **Requiere MySQL.** En SQLite no prueba lo que dice (ADR-0010). |
| Dos ajustes simultáneos sobre la misma variante no pierden ninguno | Requiere MySQL |

Estos dos van marcados con `@group concurrency` y se ejecutan solo en CI contra MySQL.
Dejarlos corriendo en SQLite es peor que no tenerlos: dan verde sin probar nada.

## Convenciones

- `RefreshDatabase` siempre. Factories siempre. Cero SQL a mano.
- El nombre dice qué se rompe:
  `test_crear_pedido_falla_cuando_no_hay_stock_suficiente`, no `test_orders`.
- `assertJsonStructure` para la forma, `assertJsonPath` para valores concretos.
- Los JSON de ejemplo de los contratos se copian a los tests. Si divergen, uno de los dos
  está mal y hay que decidir cuál.

## Comandos

```bash
php artisan test
php artisan test --filter=OrderTest
php artisan test --coverage --min=70
php artisan test --exclude-group=concurrency
```
