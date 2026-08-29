# Contrato — Carrito

Aplica `00-convenciones.md`. El carrito es público: la propiedad la da el token opaco.

---

## POST /api/v1/carts

Crea un carrito vacío y devuelve su token.

| | |
|---|---|
| Autenticación | opcional (bearer). Si hay token de usuario, el carrito queda asociado. |
| Idempotencia | no aplica |
| Rate limit | 60 / min por IP |

### Petición

```json
{}
```

Cuerpo vacío. `currency` no se acepta: es siempre `COP` y lo decide el servidor
(ADR-0008). Aceptar la moneda del cliente abre la puerta a que pida `USD` y reciba
precios en pesos etiquetados como dólares.

### Respuesta 201

```json
{
  "data": {
    "token": "9f1c2b7e-4a83-4d21-9c55-7b0e1f3a6d84",
    "status": "open",
    "currency": "COP",
    "items": [],
    "totals": {
      "subtotal_cents": 0,
      "discount_cents": 0,
      "shipping_cents": 0,
      "tax_cents": 0,
      "total_cents": 0
    },
    "item_count": 0,
    "expires_at": "2026-09-12T17:42:10Z",
    "created_at": "2026-08-29T17:42:10Z"
  }
}
```

Header `Location: /api/v1/carts/9f1c2b7e-4a83-4d21-9c55-7b0e1f3a6d84`.

`discount_cents`, `shipping_cents` y `tax_cents` **siempre valen 0** en el MVP. Están en
la respuesta desde el día uno para que añadirlos no sea un cambio incompatible.

---

## GET /api/v1/carts/{token}

| | |
|---|---|
| Autenticación | pública, el token es la credencial |
| Rate limit | 120 / min por IP |

### Respuesta 200

```json
{
  "data": {
    "token": "9f1c2b7e-4a83-4d21-9c55-7b0e1f3a6d84",
    "status": "open",
    "currency": "COP",
    "items": [
      {
        "id": 51,
        "sku": "NGL-CAM-OXF-AZC-M",
        "product": {
          "slug": "camisa-oxford-manga-larga",
          "name": "Camisa Oxford Manga Larga"
        },
        "variant_label": "Azul cielo / M",
        "image_url": "https://cdn.nogal.store/p/oxford-azc-1.webp",
        "quantity": 2,
        "unit_price_cents": 1890000,
        "line_total_cents": 3780000,
        "available": 7,
        "has_enough_stock": true
      },
      {
        "id": 52,
        "sku": "NGL-PAN-CHI-BEI-32",
        "product": {
          "slug": "pantalon-chino-slim",
          "name": "Pantalón Chino Slim"
        },
        "variant_label": "Beige arena / 32",
        "image_url": "https://cdn.nogal.store/p/chino-bei-1.webp",
        "quantity": 3,
        "unit_price_cents": 2150000,
        "line_total_cents": 6450000,
        "available": 1,
        "has_enough_stock": false
      }
    ],
    "totals": {
      "subtotal_cents": 10230000,
      "discount_cents": 0,
      "shipping_cents": 0,
      "tax_cents": 0,
      "total_cents": 10230000
    },
    "item_count": 5,
    "expires_at": "2026-09-12T17:42:10Z",
    "created_at": "2026-08-29T17:42:10Z",
    "updated_at": "2026-08-29T18:01:33Z"
  }
}
```

`available` y `has_enough_stock` son **informativos**. El carrito no bloquea nada: sirven
para que la interfaz avise antes de que el usuario llegue a la pantalla de confirmación y
se lleve el `409`.

`item_count` es la suma de cantidades (5), no el número de líneas (2). Es lo que va en la
burbuja del icono del carrito.

`unit_price_cents` es el precio **congelado al añadir**. No se recalcula al consultar.

### Errores

| Código | HTTP | Cuándo |
|---|---|---|
| `RESOURCE_NOT_FOUND` | 404 | El token no existe |
| `CART_EXPIRED` | 410 | Superó `expires_at`. El cliente debe crear un carrito nuevo. |

---

## POST /api/v1/carts/{token}/items

Añade una variante. Si ya está en el carrito, **suma** cantidad.

| | |
|---|---|
| Autenticación | pública |
| Rate limit | 60 / min por IP |

### Petición

```json
{
  "sku": "NGL-CAM-OXF-AZC-M",
  "quantity": 2
}
```

| Campo | Reglas |
|---|---|
| `sku` | requerido, string, variante existente y `active` |
| `quantity` | requerido, entero, 1 a 20 |

El tope de 20 por línea no es antojo: sin límite, un script mete `quantity: 999999999` y
revienta el `unsignedInteger` al calcular el total de línea.

### Respuesta 200

El carrito completo, con la misma forma que `GET /carts/{token}`.

Devuelve **200 y no 201** a propósito: el recurso que devuelve es el carrito, que ya
existía. Lo que se creó es una línea, que no tiene URL propia.

### Errores

| Código | HTTP | Cuándo |
|---|---|---|
| `RESOURCE_NOT_FOUND` | 404 | Token de carrito inexistente |
| `VALIDATION_FAILED` | 422 | `quantity` fuera de rango, `sku` ausente |
| `VARIANT_UNAVAILABLE` | 409 | La variante o su producto no están `active` |
| `CART_NOT_MODIFIABLE` | 409 | El carrito está `converted` o `abandoned` |
| `CART_EXPIRED` | 410 | Carrito caducado |
| `RATE_LIMITED` | 429 | — |

**No existe `INSUFFICIENT_STOCK` en esta tabla.** Es la decisión de ADR-0005: añadir al
carrito no compromete stock, así que no puede fallar por falta de él.

### Notas de implementación

- `UNIQUE(cart_id, product_variant_id)` en la tabla. La suma se hace con
  `updateOrCreate` dentro de una transacción, no con un `SELECT` previo.
- La suma se valida también contra el tope de 20: si hay 15 y se añaden 10, es `422`,
  no 25.

---

## PATCH /api/v1/carts/{token}/items/{itemId}

Fija la cantidad de una línea. **Reemplaza**, no suma.

### Petición

```json
{ "quantity": 3 }
```

| Campo | Reglas |
|---|---|
| `quantity` | requerido, entero, 1 a 20 |

Para eliminar se usa `DELETE`, no `quantity: 0`. Un `0` que significa "borrar" es un caso
especial escondido dentro de una operación de actualización, y siempre se olvida en algún
cliente.

### Respuesta 200

El carrito completo.

### Errores

| Código | HTTP | Cuándo |
|---|---|---|
| `RESOURCE_NOT_FOUND` | 404 | Carrito o línea inexistentes, o la línea es de otro carrito |
| `VALIDATION_FAILED` | 422 | `quantity` fuera de rango |
| `CART_NOT_MODIFIABLE` | 409 | Carrito `converted` o `abandoned` |
| `CART_EXPIRED` | 410 | — |

Si `itemId` existe pero pertenece a otro carrito, la respuesta es `404`, no `403`. Un
`403` confirmaría que la línea existe y permitiría enumerar el contenido de otros carritos.

---

## DELETE /api/v1/carts/{token}/items/{itemId}

### Respuesta 200

El carrito completo, ya sin esa línea.

Devuelve el carrito y no `204` porque el cliente necesita los totales recalculados. Un
`204` obligaría a un `GET` inmediato después, siempre.

### Errores

| Código | HTTP | Cuándo |
|---|---|---|
| `RESOURCE_NOT_FOUND` | 404 | Carrito o línea inexistentes |
| `CART_NOT_MODIFIABLE` | 409 | Carrito `converted` o `abandoned` |
| `CART_EXPIRED` | 410 | — |

---

## DELETE /api/v1/carts/{token}

Vacía el carrito. No lo borra: sigue siendo el mismo token.

### Respuesta 200

El carrito vacío, con `items: []` y todos los totales en 0.
