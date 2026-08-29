# Registro de cambios

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/).
Versionado semántico. La versión de la API (`v1`) es independiente de la versión del
repositorio.

## [No publicado]

### Añadido

**Configuración de trabajo**
- `.claude/settings.json` con permisos en tres niveles: `allow` para operaciones seguras,
  `ask` para `git commit` y `gh pr create`, `deny` para `git push`, `git reset --hard`,
  `git rebase` y lectura de `.env`.
- Hook `PostToolUse` que ejecuta `vendor/bin/pint --dirty` tras cada edición.
- Hook `Stop` que recuerda actualizar el CHANGELOG al cambiar un contrato.
- Cuatro agentes: `arquitecto-dominio`, `redactor-contratos`, `ingeniero-tests`,
  `escriba-adr`.
- Cinco comandos: `/nuevo-contrato`, `/implementar-endpoint`, `/nueva-adr`,
  `/revisar-cobertura`, `/changelog`.
- `CLAUDE.md` con las reglas de código y el alcance del MVP.

**Documentación de dominio**
- Glosario del lenguaje ubicuo.
- Separación Producto / Variante / Existencia con diagramas ER.
- Modelo de inventario con las tres cantidades y el libro mayor `inventory_movements`.
- Carrito por token opaco con precio congelado.
- Máquina de estados de pedido con su tabla de transiciones y su efecto sobre el stock.
- Modelo de datos completo: 12 tablas, índices y restricciones.

**Arquitectura**
- `docs/architecture.md` con el diagrama de capas y las responsabilidades de cada una.
- Trazabilidad por `traceId` (ULID) en logs, header `X-Trace-Id` y cuerpo de error.

**Contratos de API** (18 endpoints)
- Convenciones: formato de error único, catálogo de 14 códigos, paginación, filtros,
  ordenamiento, idempotencia, headers.
- Catálogo: listado con filtros, detalle, variante, categorías.
- Inventario: consulta pública, consulta en lote, ajuste admin, libro mayor.
- Carrito: crear, consultar, añadir, actualizar, quitar, vaciar.
- Pedidos: crear con idempotencia, consultar, historial, transiciones de estado.
- `docs/api/openapi.yaml` con la especificación formal equivalente.

**Decisiones**
- ADR-0001 a ADR-0010, todas en estado Aceptado.

### Pendiente de registrar

Nada. Este es el estado de cierre de la Sesión 14.

---

## Cómo se escribe una entrada aquí

Describe el **efecto para quien consume la API**, no el archivo que tocaste.

| Bien | Mal |
|---|---|
| `Añadido filtro por talla en GET /api/v1/products` | `Modificado ProductController.php` |
| `Corregido cálculo del precio efectivo cuando la variante tiene precio propio` | `Fix en el query builder` |
| `**BREAKING** El campo total pasa de decimal a total_cents entero` | `Cambio en totales` |

Un cambio marcado `**BREAKING**` obliga a subir la versión de la API (ADR-0002) y exige
un ADR que lo respalde.
