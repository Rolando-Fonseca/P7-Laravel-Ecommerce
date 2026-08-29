# Contrato — Pedidos

Aplica `00-convenciones.md`. Es el módulo con las operaciones más delicadas del sistema.

---

## POST /api/v1/orders

Convierte un carrito en pedido. **La operación crítica del backend.**

| | |
|---|---|
| Autenticación | opcional (bearer). Si hay usuario, el pedido queda asociado. |
| Idempotencia | **obligatoria** — `Idempotency-Key` |
| Rate limit | 10 / min por IP |

### Headers

```
Content-Type: application/json
Accept: application/json
Idempotency-Key: 01JBQ9K4N6R8T0V2X4Z6B8D0F2
```

### Petición

```json
{
  "cart_token": "9f1c2b7e-4a83-4d21-9c55-7b0e1f3a6d84",
  "email": "cliente@ejemplo.com",
  "shipping_address": {
    "full_name": "Andrés Molina",
    "phone": "+57 300 123 4567",
    "line1": "Carrera 45 # 26-30",
    "line2": "Apartamento 802",
    "city": "Medellín",
    "state": "Antioquia",
    "postal_code": "050015",
    "country": "CO"
  },
  "billing_address": null,
  "notes": "Dejar en portería si no hay nadie."
}
```

| Campo | Reglas |
|---|---|
| `cart_token` | requerido, uuid, carrito existente y `open` |
| `email` | requerido, email válido, máximo 255 |
| `shipping_address` | requerido, objeto |
| `shipping_address.full_name` | requerido, 3 a 120 |
| `shipping_address.phone` | requerido, 7 a 20 |
| `shipping_address.line1` | requerido, 5 a 180 |
| `shipping_address.line2` | opcional, máximo 180 |
| `shipping_address.city` | requerido, 2 a 80 |
| `shipping_address.state` | requerido, 2 a 80 |
| `shipping_address.postal_code` | opcional, máximo 20 |
| `shipping_address.country` | requerido, ISO-3166 alfa-2, en el MVP solo `CO` |
| `billing_address` | opcional. `null` significa "igual que el envío". |
| `notes` | opcional, máximo 500 |

### Respuesta 201

```json
{
  "data": {
    "number": "NGL-2026-000123",
    "status": "created",
    "currency": "COP",
    "email": "cliente@ejemplo.com",
    "items": [
      {
        "sku": "NGL-CAM-OXF-AZC-M",
        "product_name": "Camisa Oxford Manga Larga",
        "variant_label": "Azul cielo / M",
        "quantity": 2,
        "unit_price_cents": 1890000,
        "line_total_cents": 3780000
      }
    ],
    "totals": {
      "subtotal_cents": 3780000,
      "discount_cents": 0,
      "shipping_cents": 0,
      "tax_cents": 0,
      "total_cents": 3780000
    },
    "shipping_address": {
      "full_name": "Andrés Molina",
      "phone": "+57 300 123 4567",
      "line1": "Carrera 45 # 26-30",
      "line2": "Apartamento 802",
      "city": "Medellín",
      "state": "Antioquia",
      "postal_code": "050015",
      "country": "CO"
    },
    "billing_address": null,
    "notes": "Dejar en portería si no hay nadie.",
    "allowed_transitions": ["paid", "cancelled"],
    "placed_at": "2026-08-29T18:14:02Z",
    "created_at": "2026-08-29T18:14:02Z"
  }
}
```

Header `Location: /api/v1/orders/NGL-2026-000123`.

`allowed_transitions` sale de la tabla de `docs/domain/04-pedidos.md`. Se expone para que
un panel de administración pueda pintar solo los botones legales en vez de mantener una
copia de la máquina de estados en el cliente — copia que se desincroniza siempre.

Los items del pedido guardan `product_name` y `variant_label` **en texto**. Si mañana se
renombra el producto, este pedido sigue diciendo lo que el cliente compró.

### Errores

| Código | HTTP | Cuándo |
|---|---|---|
| `IDEMPOTENCY_KEY_REQUIRED` | 400 | Falta el header |
| `IDEMPOTENCY_KEY_REUSED` | 409 | Misma clave, cuerpo distinto |
| `RESOURCE_NOT_FOUND` | 404 | `cart_token` inexistente |
| `VALIDATION_FAILED` | 422 | Dirección o email inválidos |
| `CART_EMPTY` | 422 | El carrito no tiene líneas |
| `CART_NOT_MODIFIABLE` | 409 | El carrito ya está `converted` |
| `CART_EXPIRED` | 410 | Carrito caducado |
| `VARIANT_UNAVAILABLE` | 409 | Una variante del carrito se archivó mientras tanto |
| `INSUFFICIENT_STOCK` | 409 | No hay disponible suficiente en alguna línea |
| `RATE_LIMITED` | 429 | — |

#### Ejemplo de `409 INSUFFICIENT_STOCK`

```json
{
  "error": {
    "code": "INSUFFICIENT_STOCK",
    "message": "No hay unidades suficientes para completar el pedido.",
    "details": [
      {
        "field": "items.1.quantity",
        "issue": "solicitado 3, disponible 1",
        "meta": {
          "sku": "NGL-PAN-CHI-BEI-32",
          "product_name": "Pantalón Chino Slim",
          "variant_label": "Beige arena / 32",
          "requested": 3,
          "available": 1
        }
      }
    ],
    "traceId": "01JBQ9M2P4R6T8V0X2Z4B6D8F0"
  }
}
```

`details` lista **todas** las líneas que fallan, no solo la primera. Si el usuario tiene
tres problemas, tiene que verlos los tres a la vez: mostrarle uno, que lo arregle y
mostrarle el siguiente es la peor experiencia posible en una pantalla de pago.

**No hay pedidos parciales.** Si una línea falla, la transacción entera se revierte y no
se crea nada. Un pedido parcial obligaría a decidir qué hacer con las líneas que sí
entraron, y esa decisión es del cliente, no del servidor.

### Repetición idempotente

Segunda petición con la **misma clave y el mismo cuerpo**:

```
HTTP/1.1 201 Created
Idempotency-Replayed: true
Location: /api/v1/orders/NGL-2026-000123
```

Cuerpo idéntico al de la primera. **No se crea un segundo pedido y no se reserva stock
de nuevo.**

### Notas de implementación

Una sola `DB::transaction()` con este orden exacto:

1. `lockForUpdate()` sobre el carrito. Verificar `open`, no vacío, no caducado.
2. `lockForUpdate()` sobre los `inventory_items` implicados, **ordenados por `id`
   ascendente**. El orden fijo evita interbloqueos entre pedidos que comparten variantes.
3. Validar `available >= quantity` en todas las líneas. Acumular todos los fallos antes
   de lanzar la excepción.
4. Generar `number` con la secuencia del año.
5. Crear `orders` y `order_items` con los textos copiados.
6. Subir `quantity_reserved` y escribir un `inventory_movement` de tipo `reservation`
   por línea.
7. Marcar el carrito como `converted`.
8. Persistir la respuesta en `idempotency_keys`.

---

## GET /api/v1/orders/{number}

| | |
|---|---|
| Autenticación | pública si se envía `?email=`; bearer si el pedido tiene dueño |
| Rate limit | 60 / min por IP |

Un invitado consulta con `GET /api/v1/orders/NGL-2026-000123?email=cliente@ejemplo.com`.
El email debe coincidir con el del pedido. Es un control débil a propósito: el número de
pedido no es adivinable y exigir registro para ver una compra ya hecha es hostil.

### Respuesta 200

Misma forma que la respuesta de creación, más `status_history`:

```json
{
  "data": {
    "number": "NGL-2026-000123",
    "status": "shipped",
    "allowed_transitions": ["returned"],
    "status_history": [
      { "status": "created", "at": "2026-08-29T18:14:02Z", "by": null },
      { "status": "paid",    "at": "2026-08-29T18:20:11Z", "by": "admin:4" },
      { "status": "packed",  "at": "2026-08-30T09:02:00Z", "by": "admin:7" },
      { "status": "shipped", "at": "2026-08-30T14:35:20Z", "by": "admin:7" }
    ]
  }
}
```

### Errores

| Código | HTTP | Cuándo |
|---|---|---|
| `RESOURCE_NOT_FOUND` | 404 | Número inexistente, o email que no coincide |
| `UNAUTHENTICATED` | 401 | Pedido con dueño y sin token ni email |

Email que no coincide devuelve **404, no 403**. Un 403 confirma que el pedido existe y
convierte el endpoint en un oráculo para adivinar números de pedido.

---

## GET /api/v1/orders

Historial del usuario autenticado.

| | |
|---|---|
| Autenticación | **bearer obligatorio** |
| Rate limit | 60 / min por token |

### Parámetros de consulta

| Param | Tipo | Default | Válido |
|---|---|---|---|
| `page` | int | 1 | 1 a 10000 |
| `per_page` | int | 20 | 1 a 100 |
| `status` | string | — | lista: `created,paid,packed,shipped,cancelled,returned` |
| `from` / `to` | date | — | ISO-8601 |
| `sort` | string | `-created_at` | `created_at`, `-created_at`, `total`, `-total` |

### Respuesta 200

```json
{
  "data": [
    {
      "number": "NGL-2026-000123",
      "status": "shipped",
      "item_count": 3,
      "total_cents": 3780000,
      "currency": "COP",
      "placed_at": "2026-08-29T18:14:02Z"
    }
  ],
  "meta": { "page": 1, "per_page": 20, "total": 7, "total_pages": 1 },
  "links": { "self": "...", "first": "...", "prev": null, "next": null, "last": "..." }
}
```

El listado es un **resumen**: sin líneas, sin direcciones. El detalle se pide con
`GET /orders/{number}`. Un historial de 20 pedidos con todas sus líneas y direcciones son
cientos de kilobytes que la pantalla de historial no usa.

### Errores

| Código | HTTP | Cuándo |
|---|---|---|
| `UNAUTHENTICATED` | 401 | Sin token |
| `VALIDATION_FAILED` | 422 | Filtro o `sort` inválidos |

---

## POST /api/v1/admin/orders/{number}/transitions

Cambia el estado de un pedido. **Un solo endpoint para todas las transiciones.**

| | |
|---|---|
| Autenticación | bearer + habilidad `admin` |
| Idempotencia | **obligatoria** |
| Rate limit | 300 / min por token |

### Petición

```json
{
  "to": "shipped",
  "reason": "Guía Servientrega 9876543210"
}
```

| Campo | Reglas |
|---|---|
| `to` | requerido, uno de `paid,packed,shipped,cancelled,returned` |
| `reason` | opcional, máximo 255. **Obligatorio** cuando `to` es `cancelled` o `returned`. |

### Por qué un endpoint y no cinco

`POST /orders/{n}/pay`, `/pack`, `/ship`... son cinco rutas, cinco controllers y cinco
sitios donde olvidar validar la transición. Con uno solo, la máquina de estados vive en
un único lugar y añadir un estado futuro (`delivered`, `refunded`) no toca las rutas.

El coste: la ruta es menos expresiva y no se lee tan bien en un log de acceso. Vale la
pena.

### Respuesta 200

El pedido completo con su `status` nuevo, `allowed_transitions` actualizado y la nueva
entrada en `status_history`.

### Errores

| Código | HTTP | Cuándo |
|---|---|---|
| `UNAUTHENTICATED` | 401 | — |
| `FORBIDDEN` | 403 | Sin habilidad `admin` |
| `RESOURCE_NOT_FOUND` | 404 | Número inexistente |
| `VALIDATION_FAILED` | 422 | `to` no es un estado válido, o falta `reason` en cancelación |
| `INVALID_STATE_TRANSITION` | 409 | La transición no está permitida |
| `INSUFFICIENT_STOCK` | 409 | Al despachar, el stock físico no alcanza |
| `IDEMPOTENCY_KEY_REQUIRED` | 400 | — |

#### Ejemplo de `409 INVALID_STATE_TRANSITION`

```json
{
  "error": {
    "code": "INVALID_STATE_TRANSITION",
    "message": "Un pedido enviado no se puede cancelar.",
    "details": [
      {
        "field": "to",
        "issue": "transición no permitida",
        "meta": {
          "current_status": "shipped",
          "requested_status": "cancelled",
          "allowed_transitions": ["returned"]
        }
      }
    ],
    "traceId": "01JBQ9P6R8T0V2X4Z6B8D0F2H4"
  }
}
```

El error incluye `allowed_transitions`: el cliente sabe qué sí puede hacer sin tener que
consultar la documentación ni mantener su propia copia de la tabla.

### Efecto sobre el inventario

Cada transición dispara los movimientos de la tabla de `docs/domain/04-pedidos.md`, en la
misma transacción que el cambio de estado. Nunca se cambia el estado sin mover el stock,
ni al revés.
