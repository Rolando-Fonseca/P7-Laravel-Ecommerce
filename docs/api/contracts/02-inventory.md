# Contrato — Inventario

Aplica `00-convenciones.md`. Dos zonas: consulta pública y administración.

---

## GET /api/v1/inventory/{sku}

Consulta de stock de una variante.

| | |
|---|---|
| Autenticación | pública |
| Rate limit | 120 / min por IP |

### Respuesta 200

```json
{
  "data": {
    "sku": "NGL-CAM-OXF-AZC-M",
    "available": 7,
    "in_stock": true,
    "by_warehouse": [
      { "warehouse_code": "NGL-CEN", "available": 7 }
    ]
  }
}
```

La respuesta pública **no expone `quantity_on_hand` ni `quantity_reserved`**. Solo
`available`. Las dos cantidades internas revelan volumen de ventas en curso, que es
información comercial. Un competidor que consulta `reserved` cada hora sabe exactamente
cuánto vendes.

### Errores

| Código | HTTP | Cuándo |
|---|---|---|
| `RESOURCE_NOT_FOUND` | 404 | El sku no existe o su variante no está `active` |
| `RATE_LIMITED` | 429 | — |

---

## POST /api/v1/inventory/availability

Consulta de disponibilidad en lote. Existe para que una página de producto con 10
variantes no haga 10 peticiones.

| | |
|---|---|
| Autenticación | pública |
| Rate limit | 60 / min por IP |

### Petición

```json
{
  "skus": ["NGL-CAM-OXF-AZC-M", "NGL-CAM-OXF-AZC-L", "NGL-PAN-CHI-BEI-32"]
}
```

| Campo | Reglas |
|---|---|
| `skus` | requerido, array, entre 1 y 50 elementos |
| `skus.*` | string, 5 a 40 caracteres |

### Respuesta 200

```json
{
  "data": [
    { "sku": "NGL-CAM-OXF-AZC-M",  "available": 7, "in_stock": true },
    { "sku": "NGL-CAM-OXF-AZC-L",  "available": 0, "in_stock": false },
    { "sku": "NGL-PAN-CHI-BEI-32", "available": null, "in_stock": false }
  ]
}
```

Un sku inexistente devuelve `available: null` en lugar de `404`. La petición en lote es
válida aunque un elemento no lo sea; devolver 404 por un sku obsoleto en caché rompería
la página entera.

### Errores

| Código | HTTP | Cuándo |
|---|---|---|
| `VALIDATION_FAILED` | 422 | Array vacío, más de 50 elementos, o tipos incorrectos |
| `RATE_LIMITED` | 429 | — |

---

## POST /api/v1/admin/inventory/adjustments

Ajuste manual de stock. Recepción de mercancía, corrección de conteo, merma.

| | |
|---|---|
| Autenticación | **bearer + habilidad `admin`** |
| Idempotencia | **obligatoria** (`Idempotency-Key`) |
| Rate limit | 300 / min por token |

### Petición

```json
{
  "sku": "NGL-CAM-OXF-AZC-M",
  "warehouse_code": "NGL-CEN",
  "quantity_delta": 24,
  "reason": "Recepción orden de compra OC-2026-0142"
}
```

| Campo | Reglas |
|---|---|
| `sku` | requerido, string, debe existir |
| `warehouse_code` | requerido, string, almacén existente y activo |
| `quantity_delta` | requerido, entero, distinto de 0, entre -10000 y 10000 |
| `reason` | requerido, string, 5 a 255 caracteres |

`reason` es **obligatorio**. Un ajuste sin motivo es un descuadre que nadie puede
auditar seis meses después. El campo es la mitad del valor del libro mayor.

`quantity_delta` no puede ser 0: un ajuste que no ajusta nada es un error del cliente,
no una operación válida.

### Respuesta 201

```json
{
  "data": {
    "movement_id": "01JBQ8M3P5T7W9X2Y4Z6A8B0C2",
    "sku": "NGL-CAM-OXF-AZC-M",
    "warehouse_code": "NGL-CEN",
    "type": "adjustment",
    "quantity_delta": 24,
    "reason": "Recepción orden de compra OC-2026-0142",
    "resulting_stock": {
      "quantity_on_hand": 31,
      "quantity_reserved": 2,
      "available": 29
    },
    "created_by": { "id": 4, "name": "Bodega Central" },
    "created_at": "2026-08-29T17:42:10Z"
  }
}
```

Aquí **sí** se exponen `on_hand` y `reserved`: es la zona de administración y el operario
necesita ver el efecto exacto de lo que acaba de hacer.

### Errores

| Código | HTTP | Cuándo |
|---|---|---|
| `UNAUTHENTICATED` | 401 | Sin token |
| `FORBIDDEN` | 403 | Token sin habilidad `admin` |
| `VALIDATION_FAILED` | 422 | Campos inválidos |
| `RESOURCE_NOT_FOUND` | 404 | Sku o almacén inexistentes |
| `INSUFFICIENT_STOCK` | 409 | El delta negativo dejaría `on_hand` por debajo de `reserved` |
| `IDEMPOTENCY_KEY_REQUIRED` | 400 | Falta el header |
| `IDEMPOTENCY_KEY_REUSED` | 409 | Misma clave, cuerpo distinto |

El `409 INSUFFICIENT_STOCK` en un ajuste negativo es la regla que protege la invariante
`reserved <= on_hand`. Si hay 3 unidades reservadas por pedidos en curso, no se puede
bajar el stock a 1: esos pedidos ya se comprometieron.

### Notas de implementación

- Todo dentro de `DB::transaction()` con `lockForUpdate()` sobre el `inventory_item`.
- El `inventory_movement` se escribe en la misma transacción. Nunca uno sin el otro.
- El `Idempotency-Key` se copia a `inventory_movements.idempotency_key`, que es `unique`.
  Es una segunda red: aunque falle la capa de idempotencia, la base de datos rechaza el
  movimiento duplicado.

---

## GET /api/v1/admin/inventory/movements

Libro mayor de movimientos. Es la herramienta de auditoría.

| | |
|---|---|
| Autenticación | bearer + habilidad `admin` |
| Rate limit | 300 / min por token |

### Parámetros de consulta

| Param | Tipo | Default | Válido |
|---|---|---|---|
| `page` | int | 1 | 1 a 10000 |
| `per_page` | int | 50 | 1 a 100 |
| `sku` | string | — | sku existente |
| `warehouse_code` | string | — | almacén existente |
| `type` | string | — | `adjustment,sale,return,reservation,release` (lista) |
| `from` | date | — | ISO-8601 |
| `to` | date | — | ISO-8601, mayor o igual a `from` |
| `sort` | string | `-created_at` | `created_at`, `-created_at` |

### Respuesta 200

```json
{
  "data": [
    {
      "movement_id": "01JBQ8M3P5T7W9X2Y4Z6A8B0C2",
      "sku": "NGL-CAM-OXF-AZC-M",
      "warehouse_code": "NGL-CEN",
      "type": "reservation",
      "quantity_delta": -2,
      "reason": "Reserva por pedido NGL-2026-000123",
      "reference": { "type": "Order", "id": "NGL-2026-000123" },
      "created_by": null,
      "created_at": "2026-08-29T18:03:44Z"
    }
  ],
  "meta": { "page": 1, "per_page": 50, "total": 4102, "total_pages": 83 },
  "links": { "self": "...", "first": "...", "prev": null, "next": "...", "last": "..." }
}
```

`created_by` es `null` cuando el movimiento lo generó el sistema (una reserva por pedido)
y trae el usuario cuando fue un ajuste manual.

### Errores

| Código | HTTP | Cuándo |
|---|---|---|
| `UNAUTHENTICATED` | 401 | — |
| `FORBIDDEN` | 403 | Sin habilidad `admin` |
| `VALIDATION_FAILED` | 422 | Filtro inválido o `to` anterior a `from` |
