# Registro de decisiones de arquitectura (ADR)

Formato Nygard. Un ADR aceptado **no se edita**: se supersede con uno nuevo.

| # | Decisión | Estado | Impacto |
|---|---|---|---|
| [0001](0001-laravel-12-api-only.md) | Laravel 12 como API pura, sin frontend acoplado | Aceptado | Alto |
| [0002](0002-versionado-en-la-ruta.md) | La versión de la API va en la ruta | Aceptado | Alto |
| [0003](0003-paginacion-por-desplazamiento.md) | Paginación por desplazamiento, no por cursor | Aceptado | Medio |
| [0004](0004-idempotencia-en-creacion-de-pedidos.md) | **Idempotencia obligatoria al crear pedidos** | Aceptado | **Crítico** |
| [0005](0005-sin-reservas-temporales-de-stock.md) | El carrito no reserva stock | Aceptado | Alto |
| [0006](0006-bloqueo-pesimista-para-el-inventario.md) | Bloqueo pesimista sobre el inventario, con orden fijo | Aceptado | **Crítico** |
| [0007](0007-pagos-fuera-del-mvp.md) | Los pagos quedan fuera del MVP | Aceptado | Alto |
| [0008](0008-campos-de-totales-desde-el-dia-uno.md) | Descuento, envío e impuesto existen valiendo 0 | Aceptado | Medio |
| [0009](0009-busqueda-con-like-en-el-mvp.md) | Búsqueda con LIKE en el MVP | Aceptado | Bajo |
| [0010](0010-sqlite-en-desarrollo-mysql-en-produccion.md) | SQLite en desarrollo, MySQL 8 en producción | Aceptado | Medio |

## Los dos que más deuda evitan

**ADR-0004 (idempotencia).** Añadirlo después no es escribir código: es migrar la tabla,
romper el contrato de forma incompatible y auditar los pedidos duplicados que ya se
crearon en producción. Es la decisión que hay que tomar antes de la primera línea de
`CreateOrderService`.

**ADR-0006 (bloqueo).** Un *lost update* no lanza ninguna excepción. No aparece en los
logs, no rompe ningún test y no lo detecta nadie hasta que el inventario físico no cuadra
con el sistema, semanas después. Para entonces no hay forma de saber cuántas veces pasó.

## Qué se pospuso, y con qué señal se retoma

| Pospuesto | ADR | Señal de revisión |
|---|---|---|
| Pasarela de pago | 0007 | Contrato firmado y credenciales de producción |
| Reservas temporales de stock | 0005 | Lanzamiento con stock limitado, o `409` por encima del 2% |
| Motor de búsqueda | 0009 | Más de 2.000 productos, o p95 > 300 ms, o 15% de búsquedas sin resultado |
| Paginación por cursor | 0003 | `inventory_movements` por encima de 1 millón de filas |
| Impuestos y envío calculados | 0008 | Definición de reglas fiscales por parte de negocio |
| Cupones y promociones | 0008 | — |
| Devoluciones parciales | — | Volumen de devoluciones que lo justifique |
