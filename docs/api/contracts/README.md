# Contratos de API — índice

Lee primero [00-convenciones.md](00-convenciones.md). Manda sobre todo lo demás.

## Endpoints del MVP

| Método | Ruta | Auth | Idem. | Contrato |
|---|---|---|:---:|---|
| GET | `/api/v1/categories` | pública | — | [01](01-products.md) |
| GET | `/api/v1/products` | pública | — | [01](01-products.md) |
| GET | `/api/v1/products/{slug}` | pública | — | [01](01-products.md) |
| GET | `/api/v1/products/{slug}/variants/{sku}` | pública | — | [01](01-products.md) |
| GET | `/api/v1/inventory/{sku}` | pública | — | [02](02-inventory.md) |
| POST | `/api/v1/inventory/availability` | pública | — | [02](02-inventory.md) |
| POST | `/api/v1/admin/inventory/adjustments` | admin | Sí | [02](02-inventory.md) |
| GET | `/api/v1/admin/inventory/movements` | admin | — | [02](02-inventory.md) |
| POST | `/api/v1/carts` | opcional | — | [03](03-cart.md) |
| GET | `/api/v1/carts/{token}` | pública | — | [03](03-cart.md) |
| POST | `/api/v1/carts/{token}/items` | pública | — | [03](03-cart.md) |
| PATCH | `/api/v1/carts/{token}/items/{itemId}` | pública | — | [03](03-cart.md) |
| DELETE | `/api/v1/carts/{token}/items/{itemId}` | pública | — | [03](03-cart.md) |
| DELETE | `/api/v1/carts/{token}` | pública | — | [03](03-cart.md) |
| POST | `/api/v1/orders` | opcional | **Sí** | [04](04-orders.md) |
| GET | `/api/v1/orders/{number}` | mixta | — | [04](04-orders.md) |
| GET | `/api/v1/orders` | bearer | — | [04](04-orders.md) |
| POST | `/api/v1/admin/orders/{number}/transitions` | admin | **Sí** | [04](04-orders.md) |

18 endpoints. La especificación formal equivalente está en
[../openapi.yaml](../openapi.yaml).

## Regla de trabajo

El contrato se escribe **antes** que el código. Si al implementar descubres que el
contrato está mal, corrige el contrato, regístralo en el CHANGELOG y luego implementa. No
parchees el código para que encaje con una especificación equivocada: la próxima persona
leerá la especificación, no tu parche.
