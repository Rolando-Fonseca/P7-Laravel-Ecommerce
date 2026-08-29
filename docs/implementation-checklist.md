# Checklist de implementación

Orden por dependencia, no por preferencia. Nada de una fase empieza sin la anterior
cerrada.

## Fase 0 — Cimientos

- [x] Laravel 12 instalado, `install:api` ejecutado (Sanctum + `routes/api.php`)
- [x] SQLite en desarrollo, migraciones base ejecutadas
- [x] Enums de dominio: `ProductStatus`, `VariantStatus`, `SizeSystem`, `CartStatus`,
      `OrderStatus`, `MovementType`
- [x] `OrderStatus::allowedTransitions()` implementando la tabla del dominio
- [x] Middleware `AssignTraceId` con ULID en contexto de log y header `X-Trace-Id`
- [x] Jerarquía `DomainException` con `code()` y `httpStatus()`
- [x] Handler que convierte toda excepción al formato de error único
- [x] Middleware `EnforceIdempotency`

## Fase 1 — Esquema

- [x] 12 migraciones con sus índices y restricciones `UNIQUE`
- [x] Restricciones `CHECK` de inventario (condicionadas al driver)
- [x] Modelos Eloquent con relaciones, casts y scopes
- [x] Accessor `available` en `InventoryItem`
- [x] Factories para todos los modelos
- [x] Seeder del catálogo de Nogal con datos reales de ropa masculina

## Fase 2 — Catálogo (lectura, sin riesgo)

- [x] `GET /api/v1/categories`
- [x] `GET /api/v1/products` con paginación, filtros y orden
- [x] `GET /api/v1/products/{slug}`
- [x] `GET /api/v1/products/{slug}/variants/{sku}`
- [x] Rechazo de parámetros desconocidos con `422`
- [x] Precio efectivo resuelto con `COALESCE` en SQL
- [x] Tests de contrato del catálogo

## Fase 3 — Inventario

- [x] `GET /api/v1/inventory/{sku}`
- [x] `POST /api/v1/inventory/availability`
- [x] `POST /api/v1/admin/inventory/adjustments` con idempotencia y bloqueo
- [x] `GET /api/v1/admin/inventory/movements`
- [x] Habilidad `admin` en el token de Sanctum
- [x] Tests de inventario, incluida la invariante del libro mayor

## Fase 4 — Carrito

- [x] `POST /api/v1/carts`
- [x] `GET /api/v1/carts/{token}`
- [x] `POST /api/v1/carts/{token}/items` con suma de cantidades
- [x] `PATCH /api/v1/carts/{token}/items/{itemId}`
- [x] `DELETE /api/v1/carts/{token}/items/{itemId}`
- [x] `DELETE /api/v1/carts/{token}`
- [x] Caducidad a 14 días
- [x] Tests de carrito

## Fase 5 — Pedidos (la parte crítica)

- [x] `CreateOrderService` con transacción, bloqueo ordenado y validación acumulada
- [x] Generación del número `NGL-AAAA-NNNNNN`
- [x] Copia de textos a `order_items`
- [x] Reserva de stock + movimiento `reservation`
- [x] `POST /api/v1/orders` con idempotencia obligatoria
- [x] `GET /api/v1/orders/{number}` con acceso por email para invitados
- [x] `GET /api/v1/orders` (historial autenticado)
- [x] `POST /api/v1/admin/orders/{number}/transitions`
- [x] Efectos sobre el stock de cada transición
- [x] Tests de pedidos e idempotencia

## Fase 6 — Cierre

- [x] `docs/api/openapi.yaml` alineado con los contratos
- [x] CHANGELOG al día
- [x] `php artisan test` en verde
- [ ] Cobertura verificada — **bloqueado**: la maquina de desarrollo no tiene Xdebug ni PCOV.
      `php artisan test --coverage` no puede ejecutarse. Se mide en CI.
- [ ] Rama y PR preparados
- [ ] Push (fuera de clase, requiere aprobación)

## Pendiente explícito — trabajo posterior a la sesión

Lo siguiente **no está implementado a propósito**. Cada punto tiene su ADR.

| Tarea | ADR | Prioridad |
|---|---|---|
| Batería de concurrencia contra MySQL en CI | 0010 | Alta |
| Métrica `orders.insufficient_stock_rate` | 0005 | Alta |
| Purga programada de `idempotency_keys` a 24 h | 0004 | Alta |
| Purga de carritos caducados a 90 días | — | Media |
| Métrica `search.zero_results_rate` | 0009 | Media |
| Panel de administración | 0001 | Media |
| Pasarela de pago | 0007 | Bloqueada |
| Reservas temporales de stock | 0005 | Bloqueada |
| Impuestos y envío calculados | 0008 | Bloqueada |
