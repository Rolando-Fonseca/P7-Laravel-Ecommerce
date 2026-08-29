# Nogal — P7 Products & Inventory API

Backend de ecommerce de **ropa masculina** sobre **Laravel 12**. API REST, sin frontend.
Este repositorio se trabaja **docs-first**: la documentación es la especificación, el
código es su consecuencia.

## Regla número uno

**El contrato se escribe antes que el código.**
Si vas a implementar un endpoint que no está en `docs/api/contracts/`, tu tarea no es
implementarlo: es escribir el contrato. Sin excepciones.

## Alcance del MVP

| Dentro | Fuera (y por qué) |
|---|---|
| Catálogo: productos y variantes | Pagos reales — ADR-0007 |
| Inventario por variante y almacén | Reservas temporales de stock — ADR-0005 |
| Carrito anónimo por token | Cupones y promociones — ADR-0008 |
| Pedidos con máquina de estados | Envíos e impuestos calculados — ADR-0008 |
| Ajustes de inventario para admin | Devoluciones parciales — ADR-0008 |

Un pedido en este MVP llega hasta el estado `paid`, y ese `paid` lo dispara un endpoint
de administración, no una pasarela. Eso es deliberado.

## Estructura

```
docs/
  architecture.md                 visión de capas y diagramas
  domain/                         modelo de dominio y lenguaje ubicuo
  api/contracts/                  contratos de endpoints (la especificación)
  api/openapi.yaml                la misma especificación, formal
  adr/                            decisiones con sus consecuencias
  CHANGELOG.md                    registro de cambios
  implementation-checklist.md     tareas ordenadas
  test-plan.md                    qué se prueba y en qué orden
app/
  Domain/<Modulo>/                servicios de dominio, DTOs, excepciones
  Models/                         Eloquent, delgado
  Http/Controllers/Api/V1/        valida, delega, responde
  Http/Requests/Api/V1/           validación declarativa
  Http/Resources/Api/V1/          forma de la respuesta
  Exceptions/                     excepciones de dominio con su código de API
```

## Reglas de código

1. **Dinero en centavos, siempre.** Columnas `*_cents` enteras. Nunca `float`.
2. **El controller no contiene reglas de negocio.** Si hay un `if` sobre stock en un
   controller, está en el sitio equivocado.
3. **Nunca devuelvas un modelo Eloquent crudo.** Siempre un API Resource.
4. **Todo movimiento de stock genera una fila en `inventory_movements`.** Append-only.
5. **Las transiciones de estado pasan por `OrderStatus::canTransitionTo()`.** Ningún
   `$order->status = 'shipped'` suelto.
6. **Rutas públicas de catálogo usan `slug` y `sku`**, no ids autoincrementales.
7. `declare(strict_types=1);` en todo archivo nuevo de `app/`.

## Calidad

- Cobertura mínima global: **70%**. `app/Domain/`: **85%**.
- Todo endpoint nuevo trae tests de camino feliz **y** de cada error de su tabla de contrato.
- `vendor/bin/pint --dirty` antes de cada commit.
- Commits en español, formato convencional: `feat(orders): ...`, `docs(adr): ...`.

## Git

`git push`, `git reset --hard` y `git rebase` están **denegados** en `.claude/settings.json`.
`git commit` y `gh pr create` **preguntan** antes de ejecutarse. Es a propósito: en clase
nadie empuja nada por accidente.

## Comandos disponibles

| Comando | Para qué |
|---|---|
| `/nuevo-contrato <METODO /ruta>` | Contrato antes de implementar |
| `/implementar-endpoint <METODO /ruta>` | Implementación guiada con tests |
| `/nueva-adr <título>` | Registrar una decisión |
| `/revisar-cobertura` | Suite con cobertura contra el umbral |
| `/changelog` | Registrar los cambios pendientes |

## Agentes disponibles

| Agente | Cuándo invocarlo |
|---|---|
| `arquitecto-dominio` | Dudas de modelo, agregados, invariantes, estados |
| `redactor-contratos` | Antes de tocar cualquier endpoint |
| `ingeniero-tests` | Después de contratar y después de implementar |
| `escriba-adr` | Cuando se decide algo con consecuencias |
