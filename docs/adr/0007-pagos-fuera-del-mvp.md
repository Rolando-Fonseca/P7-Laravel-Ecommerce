# ADR-0007 — Los pagos quedan fuera del MVP

- **Estado:** Aceptado
- **Fecha:** 2026-08-29
- **Decide:** Equipo P7
- **Relacionados:** ADR-0004, `docs/domain/04-pedidos.md`

> Responde a la pregunta 1 de la evaluación de la sesión: **el MVP se explica sin mezclar
> pagos.**

## Contexto

Un ecommerce completo cobra dinero. Integrar una pasarela (Wompi, Mercado Pago, Stripe)
implica, como mínimo:

- Credenciales y un entorno de pruebas con datos reales de comercio.
- Un endpoint de webhook público, idempotente y con verificación de firma.
- Manejo de estados intermedios: pendiente, autorizado, capturado, rechazado, revertido.
- Conciliación entre lo que dice la pasarela y lo que dice nuestra base de datos.
- Reembolsos totales y parciales.

Eso es un proyecto en sí mismo, y su complejidad no está en el ecommerce sino en la
integración.

## Decisión

**El MVP no cobra.** El estado `paid` existe en la máquina de estados, pero lo dispara
un endpoint de administración:

```
POST /api/v1/admin/orders/{number}/transitions   { "to": "paid" }
```

No hay pasarela, no hay webhook, no hay tabla `payments`, no hay campo `payment_method`.
El pedido representa el compromiso de compra; el cobro es un proceso externo que hoy se
registra a mano.

## Alternativas consideradas

| Alternativa | Por qué no |
|---|---|
| Integrar una pasarela en modo sandbox | El sandbox no cubre lo difícil: reintentos de webhook, pagos parciales, contracargos. Da falsa sensación de "resuelto". |
| Simulador de pasarela propio | Se acaba manteniendo un simulador que no se parece a ninguna pasarela real. Trabajo que se tira. |
| Añadir la tabla `payments` vacía "para después" | Una tabla sin escritores es deuda: la primera integración real la va a rediseñar entera. |

## Consecuencias

### Positivas
- El MVP se explica sin hablar de dinero: catálogo, inventario, carrito, pedido.
- La máquina de estados se prueba entera sin depender de un servicio externo.
- La demo es reproducible sin credenciales de nadie.
- Cuando entre la pasarela, el punto de enganche es evidente: la transición a `paid`.

### Negativas
- **El sistema no es vendible tal cual.** Es un backend de operación, no una tienda.
- Un operario puede marcar `paid` un pedido que nadie pagó. No hay control.
- No hay registro de **cómo** se pagó: ni método, ni referencia, ni fecha de cobro. Los
  pedidos creados durante esta etapa quedarán sin ese dato para siempre.
- Cuando entre la pasarela habrá que decidir qué hacer con el histórico. Decidirlo
  entonces será más caro que ahora.

## Revisión

Se retoma cuando exista contrato firmado con una pasarela y credenciales de producción.
El ADR que lo sustituya debe cubrir, como mínimo: idempotencia del webhook (aplica lo
mismo que ADR-0004), verificación de firma, y qué ocurre cuando llega el aviso de pago de
un pedido ya cancelado.
