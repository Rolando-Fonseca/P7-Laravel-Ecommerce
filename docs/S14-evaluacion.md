# Sesión 14 — Respuestas a la evaluación

Las seis preguntas del punto 7 de la guía, respondidas con el artefacto que las respalda.

---

## 1. ¿Podemos explicar el MVP de ecommerce y sus límites sin mezclarlo con "pagos" todavía?

**Sí, y está documentado como decisión explícita en [ADR-0007](adr/0007-pagos-fuera-del-mvp.md).**

El MVP cubre cuatro capacidades: catálogo, inventario, carrito y pedidos. Un pedido llega
hasta el estado `paid`, y ese `paid` lo dispara un endpoint de administración, no una
pasarela.

No hay tabla `payments`, no hay `payment_method`, no hay webhook. Se decidió así porque
integrar una pasarela es un proyecto aparte: credenciales, webhook idempotente con
verificación de firma, estados intermedios, conciliación y reembolsos. Su complejidad no
está en el ecommerce, está en la integración.

El coste asumido está escrito: **el sistema no es vendible tal cual**, un operario puede
marcar como pagado algo que nadie pagó, y los pedidos de esta etapa quedarán sin registro
de cómo se cobraron.

---

## 2. ¿Nuestro modelo de dominio distingue claramente Producto vs Variante vs Stock?

**Sí, en tres niveles, y cada uno responde una pregunta distinta.**
Documentado en [01-catalogo.md](domain/01-catalogo.md) y [02-inventario.md](domain/02-inventario.md).

| Nivel | Pregunta | Ejemplo | Tabla |
|---|---|---|---|
| **Producto** | ¿Qué es? | "Camisa Oxford, 100% algodón" | `products` |
| **Variante** | ¿Cuál exactamente? | "Azul cielo / M" → `NGL-CAM-OXF-AZC-M` | `product_variants` |
| **Existencia** | ¿Cuántas y dónde? | "3 en mano, 1 reservada, en NGL-CEN" | `inventory_items` |

El caso que fuerza la separación: una camisa Oxford en 4 colores × 5 tallas son **20
combinaciones**. Cada una tiene su propio stock y su propio código de barras, pero
comparten nombre, descripción y material. Una sola tabla duplica la descripción 20 veces;
una sola fila no tiene dónde guardar que quedan 3 de la azul talla M.

Dos errores que el modelo evita:
- `products.stock` — deja de significar nada en cuanto hay tallas.
- `product_variants.stock` — funciona hasta que abres un segundo almacén, y entonces hay
  que migrar en producción.

Por eso el stock vive en una tabla de cruce **desde el día uno**, con
`UNIQUE(product_variant_id, warehouse_id)`.

Y `available` es un campo **calculado**, nunca una columna: si fuera columna sería un
tercer dato que puede desincronizarse de los otros dos, y no habría forma de saber cuál de
los tres miente.

---

## 3. ¿Los contratos API incluyen errores consistentes, paginación y filtros?

**Sí, definidos una sola vez en [00-convenciones.md](api/contracts/00-convenciones.md)
y verificados por tests.**

**Errores.** Una envoltura única `{code, message, details, traceId}` y un catálogo de 14
códigos con su HTTP correspondiente. Dos decisiones que suelen fallar:

- **`details` siempre es un array**, aunque tenga un elemento. Un objeto
  `{"campo": "error"}` no puede expresar dos errores sobre el mismo campo, y la validación
  de un carrito con 12 líneas los produce constantemente.
- **Stock insuficiente es 409, no 422.** Pedir 5 camisas es una petición válida; lo que
  falla es el estado del servidor. La diferencia práctica: un cliente reintenta un 409 y no
  reintenta un 422.

**Paginación.** Bloque `meta` + `links` idéntico en todos los endpoints. `prev` y `next`
son `null` en los extremos y **nunca se omiten**: un cliente que hace `if (links.next)` no
debe distinguir entre "ausente" y "nulo".

**Filtros.** Parámetros planos, valores múltiples separados por coma, y la regla que más
bugs evita: **un filtro desconocido devuelve 422, no se ignora**. Quien escriba
`?categoria=camisas` cuando el parámetro es `category` recibiría el catálogo entero
creyendo que filtró. Es el bug más caro y más difícil de detectar de una API de catálogo.

Tests que lo verifican: `test_un_parametro_desconocido_devuelve_422_y_no_se_ignora`,
`test_prev_y_next_son_null_en_los_extremos_pero_nunca_se_omiten`,
`test_toda_respuesta_de_error_lleva_traceid_y_details_como_array`.

---

## 4. ¿`settings.json` fuerza hábitos sanos sin bloquear el avance?

**Sí, con tres niveles de permiso que hacen cosas distintas.**
Archivo: [`.claude/settings.json`](../.claude/settings.json).

| Nivel | Comportamiento | Qué contiene |
|---|---|---|
| `allow` | Sin preguntar | `php artisan`, `git status`, editar `app/` y `docs/` |
| `ask` | Pregunta siempre | `git commit`, `gh pr create`, `migrate:fresh` |
| `deny` | Bloqueo duro, no aprobable | `git push`, `git reset --hard`, `git rebase`, leer `.env` |

El equilibrio está en `allow`: sin él, el agente interrumpe decenas de veces por sesión y
el flujo se abandona. Las operaciones peligrosas están en `deny`, que es del harness y no
del modelo — a diferencia de un hook `PreToolUse`, que corre después de que el modelo ya
decidió llamar la herramienta y puede fallar por un error de scripting.

Los hooks se reservan para automatismos, no para seguridad: `PostToolUse` ejecuta
`vendor/bin/pint --dirty` tras cada edición, y `Stop` recuerda actualizar el CHANGELOG
cuando cambia un contrato.

---

## 5. ¿Qué ADR nos parece más crítico para evitar deuda técnica temprana?

**Dos, y por razones opuestas.**

**[ADR-0004 — Idempotencia](adr/0004-idempotencia-en-creacion-de-pedidos.md).** Crítico por
el **coste de añadirlo tarde**. No es escribir código: es migrar la tabla, romper el
contrato de forma incompatible y auditar los pedidos duplicados que ya existen en
producción. Sin él, un doble clic o un reintento por timeout crea dos pedidos y reserva el
stock dos veces — y esas tres causas son cotidianas, no excepcionales.

**[ADR-0006 — Bloqueo pesimista](adr/0006-bloqueo-pesimista-para-el-inventario.md).**
Crítico porque **falla en silencio**. Un *lost update* no lanza excepción, no aparece en
los logs y no rompe ningún test. Se descubre semanas después, cuando el inventario físico
no cuadra con el sistema, y para entonces no hay forma de saber cuántas veces ocurrió.

La regla que se deriva: **prioriza los ADRs cuyo error no produce señal.** Un error
ruidoso se arregla cuando aparece; uno silencioso acumula deuda invisible.

---

## 6. ¿Qué parte del flujo es más sensible a inconsistencias y por qué?

**El inventario**, y la razón es estructural, no de opinión.
Documentado en [02-inventario.md](domain/02-inventario.md).

Es el único dato del sistema que **se comparte entre peticiones concurrentes y no se puede
reconstruir a partir de otro**. Un total mal calculado se recalcula. Un pedido perdido se
reconstruye del log. Pero si vendes 2 unidades de 1, no hay forma de derivar cuál era la
verdad: ya despachaste algo que no existía.

El escenario exacto:

```
Petición A                          Petición B
SELECT available -> 1
                                    SELECT available -> 1
UPDATE reserved = 1
                                    UPDATE reserved = 2   <-- vendimos 2 de 1
```

Tres defensas en capas:

1. `DB::transaction()` envolviendo toda la creación de pedido.
2. `lockForUpdate()` sobre `inventory_items` **ordenados por `id` ascendente**. El orden
   fijo evita interbloqueos cuando dos pedidos comparten variantes en distinto orden.
3. `CHECK (quantity_reserved <= quantity_on_hand)` en la base de datos, como última red
   para cuando una refactorización futura olvide el bloqueo.

El segundo punto sensible es la **frontera entre pagar y despachar**. El stock físico solo
se descuenta al despachar; mientras el paquete está en el almacén, la unidad existe y está
reservada, no vendida. Confundir esos dos momentos es el motivo más frecuente de descuadres
de inventario, y por eso tiene su propio test:
`test_despachar_descuenta_el_stock_fisico_y_la_reserva`.

---

## Checklist de la sesión

| # | Ítem | Estado |
|---|---|---|
| 1 | `settings.json` alineado con el flujo (agentes, comandos, Git, docs) | Hecho |
| 2 | Estructura `/docs` con architecture, domain, api/contracts, adr, changelog | Hecho |
| 3 | Contratos API MVP (productos, inventario, carrito, pedidos) | Hecho — 18 endpoints |
| 4 | ADRs con decisiones explícitas de qué entra y qué se pospone | Hecho — 10 ADRs |
| 5 | Checklist de implementación y plan de pruebas | Hecho |
| 6 | PR preparado (push fuera de clase) | Rama y commits listos |

**Extra sobre lo pedido:** el backend está implementado y funcionando, no solo andamiado.
65 tests en verde sobre Laravel 12.68 con las 12 tablas migradas y el catálogo de Nogal
sembrado.
