# Contrato — Catálogo de productos

Aplica `00-convenciones.md`. Todos los endpoints son **públicos** y de solo lectura.

---

## GET /api/v1/products

Lista productos activos con paginación, filtros y orden.

| | |
|---|---|
| Autenticación | pública |
| Idempotencia | no aplica |
| Rate limit | 120 / min por IP |
| Cacheable | sí, 60 s |

### Parámetros de consulta

| Param | Tipo | Default | Válido | Ejemplo |
|---|---|---|---|---|
| `page` | int | 1 | 1 a 10000 | `2` |
| `per_page` | int | 20 | 1 a 100 | `40` |
| `category` | string | — | slug existente | `camisas` |
| `size` | string | — | lista separada por comas | `M,L` |
| `color` | string | — | lista separada por comas, normalizado | `azul,negro` |
| `price_min` | int | — | 0 a 100000000, en centavos | `1000000` |
| `price_max` | int | — | mayor o igual a `price_min` | `9000000` |
| `q` | string | — | 2 a 80 caracteres | `oxford` |
| `in_stock` | bool | — | `true` \| `false` | `true` |
| `sort` | string | `-created_at` | `name`, `-name`, `price`, `-price`, `created_at`, `-created_at` | `price` |

Un parámetro no listado devuelve `422 VALIDATION_FAILED` con
`details[0].field = "<nombre>"` e `issue = "parametro no reconocido"`.

### Respuesta 200

```json
{
  "data": [
    {
      "slug": "camisa-oxford-manga-larga",
      "name": "Camisa Oxford Manga Larga",
      "summary": "Algodón peinado, cuello con botones, corte regular.",
      "category": { "slug": "camisas", "name": "Camisas" },
      "material": "100% algodón peinado",
      "base_price_cents": 1890000,
      "currency": "COP",
      "price_range_cents": { "min": 1890000, "max": 2090000 },
      "primary_image": {
        "url": "https://cdn.nogal.store/p/oxford-azc-1.webp",
        "alt": "Camisa Oxford azul cielo sobre fondo neutro"
      },
      "available_sizes": ["S", "M", "L", "XL", "XXL"],
      "available_colors": [
        { "name": "Azul cielo", "hex": "#A8C5DA" },
        { "name": "Blanco hueso", "hex": "#F2EFE9" }
      ],
      "in_stock": true,
      "variant_count": 10,
      "created_at": "2026-03-14T09:20:00Z"
    }
  ],
  "meta": { "page": 1, "per_page": 20, "total": 187, "total_pages": 10 },
  "links": {
    "self":  "https://api.nogal.store/api/v1/products?page=1&per_page=20",
    "first": "https://api.nogal.store/api/v1/products?page=1&per_page=20",
    "prev":  null,
    "next":  "https://api.nogal.store/api/v1/products?page=2&per_page=20",
    "last":  "https://api.nogal.store/api/v1/products?page=10&per_page=20"
  }
}
```

El listado **no incluye las variantes completas**. Devuelve `available_sizes`,
`available_colors` y `price_range_cents` para poder pintar la tarjeta y los filtros.
Incluir 10 variantes por producto multiplica el tamaño de la respuesta por seis para un
dato que la vista de listado no usa.

### Errores

| Código | HTTP | Cuándo |
|---|---|---|
| `VALIDATION_FAILED` | 422 | Parámetro inválido, fuera de rango o desconocido |
| `RATE_LIMITED` | 429 | Más de 120 peticiones por minuto |

### Notas de implementación

- `with(['category', 'primaryImage'])` obligatorio. Sin eso son 3 consultas por producto.
- `available_sizes` y `available_colors` salen de una única consulta agregada sobre
  `product_variants`, no de recorrer la relación en PHP.
- El filtro de precio usa `COALESCE(pv.price_cents, p.base_price_cents)`. Filtrar solo
  por `products.base_price_cents` devuelve resultados incorrectos cuando hay variantes
  con precio propio.
- `in_stock=true` se resuelve con `EXISTS (SELECT 1 FROM inventory_items ... WHERE
  quantity_on_hand - quantity_reserved > 0)`. Es el filtro más caro del endpoint.
- Solo se listan productos con `status = 'active'` que tengan al menos una variante
  `active`.

---

## GET /api/v1/products/{slug}

Detalle completo con todas sus variantes.

| | |
|---|---|
| Autenticación | pública |
| Rate limit | 120 / min por IP |

### Respuesta 200

```json
{
  "data": {
    "slug": "camisa-oxford-manga-larga",
    "name": "Camisa Oxford Manga Larga",
    "description": "Tejido Oxford de algodón peinado, 140 g/m2. Cuello con botones, canesú posterior con pliegue central y puños ajustables de dos posiciones.",
    "category": { "slug": "camisas", "name": "Camisas" },
    "material": "100% algodón peinado",
    "care_instructions": "Lavar a máquina en frío. No usar blanqueador. Planchar a temperatura media.",
    "base_price_cents": 1890000,
    "currency": "COP",
    "size_system": "alpha",
    "images": [
      { "url": "https://cdn.nogal.store/p/oxford-azc-1.webp", "alt": "Camisa Oxford azul cielo, vista frontal", "position": 1 },
      { "url": "https://cdn.nogal.store/p/oxford-azc-2.webp", "alt": "Detalle del cuello y los botones", "position": 2 }
    ],
    "variants": [
      {
        "sku": "NGL-CAM-OXF-AZC-M",
        "size": "M",
        "color": { "name": "Azul cielo", "hex": "#A8C5DA" },
        "price_cents": 1890000,
        "status": "active",
        "available": 7
      },
      {
        "sku": "NGL-CAM-OXF-AZC-XXL",
        "size": "XXL",
        "color": { "name": "Azul cielo", "hex": "#A8C5DA" },
        "price_cents": 2090000,
        "status": "active",
        "available": 0
      }
    ],
    "created_at": "2026-03-14T09:20:00Z",
    "updated_at": "2026-08-02T11:05:00Z"
  }
}
```

`price_cents` de la variante ya viene **resuelto**: si la variante no tiene precio propio,
aquí aparece el del producto. El cliente nunca hace ese `??`.

`available` es la suma de `on_hand - reserved` de todos los almacenes. Se expone en el
detalle porque es donde el usuario elige talla y necesita ver qué está agotado.

### Errores

| Código | HTTP | Cuándo |
|---|---|---|
| `RESOURCE_NOT_FOUND` | 404 | El slug no existe, o el producto no está `active` |
| `RATE_LIMITED` | 429 | — |

Un producto `archived` devuelve **404, no 410**. 410 confirmaría que existió, y en un
catálogo eso filtra información comercial (qué se dejó de vender y cuándo).

---

## GET /api/v1/products/{slug}/variants/{sku}

Variante concreta con su desglose de stock por almacén.

### Respuesta 200

```json
{
  "data": {
    "sku": "NGL-CAM-OXF-AZC-M",
    "product": { "slug": "camisa-oxford-manga-larga", "name": "Camisa Oxford Manga Larga" },
    "size": "M",
    "size_system": "alpha",
    "color": { "name": "Azul cielo", "hex": "#A8C5DA" },
    "price_cents": 1890000,
    "currency": "COP",
    "barcode": "7709876543210",
    "weight_grams": 280,
    "status": "active",
    "stock": {
      "available": 7,
      "by_warehouse": [
        { "warehouse_code": "NGL-CEN", "available": 7 }
      ]
    }
  }
}
```

### Errores

| Código | HTTP | Cuándo |
|---|---|---|
| `RESOURCE_NOT_FOUND` | 404 | El sku no existe o no pertenece a ese slug |
| `RATE_LIMITED` | 429 | — |

---

## GET /api/v1/categories

Árbol de categorías para construir la navegación.

### Respuesta 200

```json
{
  "data": [
    { "slug": "camisas",     "name": "Camisas",     "size_system": "alpha",   "product_count": 34 },
    { "slug": "camisetas",   "name": "Camisetas",   "size_system": "alpha",   "product_count": 21 },
    { "slug": "pantalones",  "name": "Pantalones",  "size_system": "waist",   "product_count": 28 },
    { "slug": "chaquetas",   "name": "Chaquetas",   "size_system": "alpha",   "product_count": 17 },
    { "slug": "calzado",     "name": "Calzado",     "size_system": "eu_shoe", "product_count": 12 },
    { "slug": "accesorios",  "name": "Accesorios",  "size_system": "unica",   "product_count": 19 }
  ]
}
```

Sin paginación: son menos de 20 filas y siempre se piden todas.
`product_count` cuenta solo productos `active`.
