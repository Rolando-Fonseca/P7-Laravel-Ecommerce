# P7 — Backend de ecommerce Nogal sobre Laravel 12

Rama: `feat/p7-ecommerce-mvp` → `main`

## Qué trae

Entregable de la Sesión 14. Repositorio docs-first con el backend de un ecommerce de
ropa masculina: catálogo, inventario, carrito y pedidos. Sin pagos, a propósito.

| | |
|---|---|
| Laravel | 12.68 |
| Endpoints | 18, todos implementados |
| Tablas | 12 |
| Tests | 80 en verde, 340 aserciones |
| Cobertura | 94.4% global, `app/Domain/` > 90% |
| ADRs | 10, todas aceptadas |
| Commits | 11 atómicos |

## Las cuatro decisiones que hay que revisar

**1. Producto / Variante / Existencia son tres niveles distintos.**
El stock no vive en `products` ni en `product_variants`, sino en `inventory_items`, que
cruza variante con almacén. `available` es `on_hand - reserved`: campo **calculado**, nunca
columna. Como columna sería un tercer dato desincronizable y no habría forma de saber cuál
de los tres miente.

**2. Idempotencia obligatoria al crear pedidos (ADR-0004).**
`POST /api/v1/orders` exige `Idempotency-Key`. Repetir con la misma clave y el mismo cuerpo
devuelve el mismo pedido y **no reserva stock por segunda vez**. Añadirlo después no sería
escribir código: sería migrar la tabla, romper el contrato y auditar los duplicados que ya
existieran en producción.

**3. Bloqueo pesimista con orden fijo (ADR-0006).**
`lockForUpdate()` sobre `inventory_items` **ordenados por `id` ascendente**. El orden fijo
es lo que evita interbloqueos entre pedidos que comparten variantes. Más una
`CHECK (quantity_reserved <= quantity_on_hand)` como última red.

**4. El carrito no reserva stock (ADR-0005).**
Añadir al carrito no compromete inventario. El coste asumido está escrito: el cliente puede
llegar a confirmar y llevarse un `409`. Se mitiga devolviendo `available` por línea y
listando **todas** las líneas que fallan de una vez, no una por una.

## Fallos reales encontrados durante la implementación

Los tests destaparon dos bugs que ya están corregidos en esta rama:

1. **`?price_max=` sin `price_min` devolvía 422.** La regla `gte:price_min` comparaba contra
   `null`. El mismo patrón afectaba a `after_or_equal:from` en los filtros de fecha.
2. **Una excepción de dominio dejaba la clave de idempotencia bloqueada para siempre.**
   El reintento legítimo del cliente recibía `IDEMPOTENCY_IN_PROGRESS` indefinidamente.

## Divergencia corregida entre contrato y código

El contrato del carrito prometía devolver el carrito dentro de `details` en el `410`. No se
implementó así. Se corrigió **el contrato**, no se parcheó el código: la próxima persona
leerá la especificación, no el parche.

## Cómo probarlo

```bash
composer install && php artisan migrate:fresh --seed && php artisan test
```

Flujo completo automatizado en
`tests/Feature/Api/V1/OrderTransitionTest::test_la_demo_completa_del_flujo_de_negocio`:
listar → añadir al carrito → crear pedido → consultar pedido.

## Pendiente explícito

| Tarea | ADR | Por qué no está |
|---|---|---|
| Batería de concurrencia contra MySQL | 0010 | SQLite no bloquea filas; esos tests darían verde sin probar nada |
| Purga de `idempotency_keys` a 24 h | 0004 | Requiere planificador |
| Métrica `orders.insufficient_stock_rate` | 0005 | Es la señal que dispara la revisión de ADR-0005 |
| Pasarela de pago | 0007 | Fuera del MVP por decisión |

## Checklist de la sesión

- [x] `settings.json` alineado con el flujo (agentes, comandos, Git, docs)
- [x] Estructura `/docs` con architecture, domain, api/contracts, adr, changelog
- [x] Contratos API MVP definidos
- [x] ADRs redactados con decisiones explícitas
- [x] Checklist de implementación y plan de pruebas
- [x] PR preparado
