# Convenciones de la API

> Este documento manda sobre todos los demás contratos. Cualquier endpoint que se aparte
> de lo que hay aquí necesita un ADR que lo justifique.

## Base

| Aspecto | Valor |
|---|---|
| Base URL | `https://api.nogal.store/api/v1` |
| Versión | En la ruta: `/api/v1`. Nunca en un header. |
| Formato | JSON en petición y respuesta |
| `Content-Type` | `application/json` |
| `Accept` | `application/json` (obligatorio; sin él Laravel devuelve HTML) |
| Codificación | UTF-8 |
| Fechas | ISO-8601 en UTC: `2026-08-29T17:42:10Z` |
| Dinero | Entero en centavos + `currency` ISO-4217 separado. Nunca decimales. |
| Idioma | Mensajes de error en español |

### Por qué la versión va en la ruta

Un header `Accept-Version` es más "correcto" según REST, y es peor en la práctica: no se
puede pegar en un navegador, no se ve en los logs de acceso, y los proxies lo pierden. La
ruta es visible en todas partes. (ADR-0002)

### Por qué el dinero viaja en centavos

`{"price": 18.90}` obliga al cliente a hacer aritmética de coma flotante. `{"price_cents":
18900, "currency": "COP"}` no tiene ambigüedad. El formateo es responsabilidad de quien
presenta, no de quien sirve.

## Formato de error único

**Toda** respuesta 4xx y 5xx tiene exactamente esta forma:

```json
{
  "error": {
    "code": "INSUFFICIENT_STOCK",
    "message": "No hay unidades suficientes para completar el pedido.",
    "details": [
      {
        "field": "items.0.quantity",
        "issue": "solicitado 5, disponible 2",
        "meta": { "sku": "NGL-CAM-OXF-AZC-M", "available": 2 }
      }
    ],
    "traceId": "01JBQ7X4M2K9V3ZP8N6T0R5FGH"
  }
}
```

| Campo | Tipo | Regla |
|---|---|---|
| `code` | string | `SCREAMING_SNAKE_CASE`. Estable. Es lo que el cliente compara. |
| `message` | string | Legible por humanos, en español. **Puede cambiar** entre versiones sin aviso: no lo uses en un `if`. |
| `details` | array | **Siempre un array**, aunque tenga un elemento. Vacío `[]` si no hay detalle. Nunca `null`, nunca un objeto. |
| `details[].field` | string | Ruta con notación de puntos del campo culpable. Ausente si el error no es de campo. |
| `details[].issue` | string | Qué está mal, concreto. |
| `details[].meta` | object | Contexto opcional legible por máquina. |
| `traceId` | string | ULID de la petición. Coincide con el header `X-Trace-Id`. |

`details` es un array y no un objeto por una razón: un objeto `{"campo": "error"}` no
puede expresar dos errores sobre el mismo campo, y la validación de un carrito con 12
líneas los produce constantemente.

## Catálogo de códigos de error

| `code` | HTTP | Cuándo |
|---|---|---|
| `VALIDATION_FAILED` | 422 | El cuerpo o los parámetros no pasan validación |
| `RESOURCE_NOT_FOUND` | 404 | El recurso no existe o no es visible |
| `UNAUTHENTICATED` | 401 | Falta el token o es inválido |
| `FORBIDDEN` | 403 | Autenticado pero sin permiso |
| `VARIANT_UNAVAILABLE` | 409 | La variante o su producto no están `active` |
| `INSUFFICIENT_STOCK` | 409 | `available` menor que lo solicitado |
| `CART_NOT_MODIFIABLE` | 409 | El carrito ya está `converted` o `abandoned` |
| `CART_EMPTY` | 422 | Se intenta crear un pedido de un carrito sin líneas |
| `CART_EXPIRED` | 410 | El carrito superó su `expires_at` |
| `INVALID_STATE_TRANSITION` | 409 | Transición de pedido no permitida |
| `IDEMPOTENCY_KEY_REQUIRED` | 400 | Falta el header en un endpoint que lo exige |
| `IDEMPOTENCY_KEY_REUSED` | 409 | Misma clave, cuerpo distinto |
| `RATE_LIMITED` | 429 | Se superó el límite. Incluye `Retry-After` |
| `INTERNAL_ERROR` | 500 | Fallo no controlado. Solo `traceId` útil. |

### Por qué 409 y no 422 para el stock

`422 Unprocessable Entity` significa "el cuerpo está mal formado o es inválido en sí
mismo". Pedir 5 camisas es una petición perfectamente válida. Lo que falla es el **estado
del servidor**, no el input: mañana con reposición la misma petición funciona. Eso es
exactamente `409 Conflict`. La distinción importa porque un cliente reintenta un 409 y
no reintenta un 422.

## Paginación

Todas las colecciones. Basada en desplazamiento (ADR-0003).

**Parámetros de entrada**

| Param | Tipo | Default | Rango |
|---|---|---|---|
| `page` | int | 1 | 1 a 10000 |
| `per_page` | int | 20 | 1 a 100 |

Fuera de rango: `422 VALIDATION_FAILED`. No se recorta en silencio — recortar oculta un
bug del cliente.

**Bloque de respuesta, idéntico en todos los endpoints**

```json
{
  "data": [],
  "meta": {
    "page": 1,
    "per_page": 20,
    "total": 187,
    "total_pages": 10
  },
  "links": {
    "self":  "https://api.nogal.store/api/v1/products?page=1&per_page=20",
    "first": "https://api.nogal.store/api/v1/products?page=1&per_page=20",
    "prev":  null,
    "next":  "https://api.nogal.store/api/v1/products?page=2&per_page=20",
    "last":  "https://api.nogal.store/api/v1/products?page=10&per_page=20"
  }
}
```

`prev` y `next` son `null` en los extremos, nunca se omiten. Un cliente que hace
`if (links.next)` no debe tener que distinguir entre "ausente" y "nulo".

## Filtros

Parámetros planos en la query. Sin `filter[campo]`, sin sintaxis anidada.

| Convención | Ejemplo |
|---|---|
| Valor único | `?category=camisas` |
| Valores múltiples (OR) | `?size=M,L,XL` — separados por coma |
| Rango | `?price_min=1000&price_max=50000` en centavos |
| Booleano | `?in_stock=true` — solo `true` o `false`, no `1` ni `yes` |
| Texto libre | `?q=oxford` |

Reglas:

- **Un filtro desconocido devuelve `422`**, no se ignora. Ignorar un parámetro mal escrito
  hace que el cliente crea que filtró y reciba resultados sin filtrar. Es el bug más caro
  y más difícil de detectar de una API de catálogo.
- Los filtros se combinan con AND. Los valores dentro de un mismo filtro, con OR.
- Los valores de texto se normalizan a minúsculas sin tildes antes de comparar.

## Ordenamiento

`?sort=<campo>` ascendente, `?sort=-<campo>` descendente. Un solo campo.

| Endpoint | Campos válidos | Default |
|---|---|---|
| `GET /products` | `name`, `price`, `created_at` | `-created_at` |
| `GET /orders` | `created_at`, `total` | `-created_at` |
| `GET /inventory/movements` | `created_at` | `-created_at` |

Campo no permitido: `422 VALIDATION_FAILED`. La lista es blanca, no negra: aceptar
cualquier nombre de columna abre la puerta a ordenar por columnas que no tienen índice y
tumbar la base de datos.

## Idempotencia

Obligatoria en operaciones que crean o cambian estado de forma irreversible.

**Header:** `Idempotency-Key: <ULID o UUID v4>`

| Endpoint | Obligatorio |
|---|---|
| `POST /api/v1/orders` | Sí |
| `POST /api/v1/orders/{number}/{transicion}` | Sí |
| `POST /api/v1/admin/inventory/adjustments` | Sí |
| Todo lo demás | No |

**Comportamiento**

| Situación | Resultado |
|---|---|
| Clave nueva | Se procesa. Se guarda `(status, body)` 24 h. |
| Misma clave, mismo cuerpo | Se devuelve la respuesta guardada + `Idempotency-Replayed: true` |
| Misma clave, cuerpo distinto | `409 IDEMPOTENCY_KEY_REUSED` |
| Clave en curso (petición simultánea) | `409 IDEMPOTENCY_IN_PROGRESS`, reintentar |
| Falta la clave donde es obligatoria | `400 IDEMPOTENCY_KEY_REQUIRED` |

La comparación del cuerpo es un `sha256` del JSON normalizado, no del texto crudo: un
espacio de más no debe contar como cuerpo distinto.

## Headers de respuesta

| Header | Siempre | Significado |
|---|---|---|
| `X-Trace-Id` | Sí | ULID de la petición |
| `X-RateLimit-Limit` | Sí | Cupo del grupo |
| `X-RateLimit-Remaining` | Sí | Restante |
| `Retry-After` | En 429 | Segundos hasta poder reintentar |
| `Idempotency-Replayed` | En repeticiones | `true` |
| `Location` | En 201 | URL canónica del recurso creado |

## Nomenclatura

- Rutas en **plural y kebab-case**: `/product-variants`, no `/productVariant`.
- Campos JSON en **snake_case**: `base_price_cents`. Coherente con las columnas.
- Enums en **minúscula**: `"status": "paid"`.
- Identificadores públicos en la ruta: `slug` para productos, `sku` para variantes,
  `number` para pedidos, `token` para carritos. **Nunca el id autoincremental.**

## Convención de estados HTTP

| Código | Uso |
|---|---|
| 200 | Lectura correcta, o mutación que no crea recurso |
| 201 | Recurso creado. Lleva `Location`. |
| 204 | Borrado correcto. Sin cuerpo. |
| 400 | Petición mal formada (falta un header obligatorio) |
| 401 / 403 | Sin autenticar / sin permiso |
| 404 | No existe o no es visible |
| 409 | Conflicto con el estado actual del servidor |
| 410 | Existió y ya no es utilizable (carrito caducado) |
| 422 | Validación de contenido |
| 429 | Límite de peticiones |
| 500 | Fallo del servidor |
