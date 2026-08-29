# Carrito

## Identidad

El carrito se identifica por un **token opaco** (UUID v4) que devuelve el servidor al
crearlo. No requiere usuario autenticado.

```
POST /api/v1/carts  ->  { "token": "9f1c2b7e-..." }
```

El cliente guarda el token y lo manda en cada operación posterior. Si hay usuario
autenticado, el carrito se asocia a él (`user_id`) y además sigue funcionando por token.

**Por qué token y no sesión:** es una API sin estado. Las sesiones obligan a cookies,
cookies obligan a CORS con credenciales y a decidir política de SameSite. El token es un
dato más del cuerpo de la petición y funciona igual desde una app móvil.

**Por qué UUID y no id autoincremental:** el token es adivinable si es `1, 2, 3`. Con
un id secuencial cualquiera puede leer el carrito de otra persona incrementando el número.

## Líneas

`UNIQUE(cart_id, product_variant_id)`. Añadir una variante que ya está en el carrito
**suma cantidad**, no crea una segunda línea. Si no, terminas con "Camisa Azul M ×1"
tres veces seguidas y el usuario no entiende qué compró.

## Precio congelado

`cart_items.unit_price_cents` guarda el precio **en el momento de añadir**. No se
recalcula al consultar el carrito.

Esto es una decisión con coste. Si Nogal sube precios el martes, quien añadió el lunes
paga el precio viejo mientras su carrito viva. Lo aceptamos porque la alternativa —
recalcular al consultar — significa que el total cambia solo entre que el usuario lo mira
y le da a confirmar, y eso genera reclamos.

La mitigación es la caducidad: `carts.expires_at = created_at + 14 días`.

## Estados

| Estado | Significa |
|---|---|
| `open` | Activo, se puede modificar |
| `converted` | Ya generó un pedido. **Inmutable.** |
| `abandoned` | Caducado. Solo lectura. |

Un carrito `converted` que recibe un `POST /items` responde `409 CART_NOT_MODIFIABLE`.
Sin este estado, un doble clic en "confirmar pedido" seguido de "añadir ítem" corrompe
un pedido ya creado.

## Totales

El carrito calcula y devuelve:

```
subtotal_cents = Σ (unit_price_cents × quantity)
total_cents    = subtotal_cents        // MVP: sin impuestos ni envío
```

Los campos `discount_cents`, `shipping_cents` y `tax_cents` **existen en el contrato y
en la tabla, siempre valen 0** en el MVP. Se exponen desde el día uno para que añadirlos
después no sea un cambio incompatible en la respuesta (ADR-0008).

## Validaciones al añadir una línea

| Regla | Error |
|---|---|
| La variante existe y está `active` | `VARIANT_UNAVAILABLE` (409) |
| El producto padre está `active` | `VARIANT_UNAVAILABLE` (409) |
| `quantity` entre 1 y 20 | `VALIDATION_FAILED` (422) |
| El carrito está `open` | `CART_NOT_MODIFIABLE` (409) |
| El carrito no ha caducado | `CART_EXPIRED` (410) |

**El carrito NO valida stock.** Puedes añadir 10 camisas de las que quedan 2. La
validación de stock ocurre al crear el pedido, que es el único momento en que el stock
se compromete. El contrato sí devuelve el `available` de cada línea para que la interfaz
pueda avisar, pero es informativo.
