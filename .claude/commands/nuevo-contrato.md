---
description: Redacta el contrato de API de un endpoint antes de implementarlo
argument-hint: <METODO /api/v1/ruta>
allowed-tools: Read, Glob, Grep, Write, Edit
---

Redacta el contrato del endpoint: **$ARGUMENTS**

Pasos:
1. Lee `docs/api/contracts/00-convenciones.md` — errores, paginación, filtros e
   idempotencia salen de ahí. No inventes formatos nuevos.
2. Lee `docs/domain/` para usar los nombres correctos del lenguaje ubicuo.
3. Identifica el archivo de contrato del módulo (products / inventory / cart / orders)
   y añade el endpoint respetando la plantilla de secciones. Si el módulo no existe, créalo.
4. Incluye TODAS las secciones obligatorias del agente `redactor-contratos`.
   Los JSON de ejemplo van completos: se copian a los tests.
5. Actualiza `docs/api/openapi.yaml` con el mismo endpoint.
6. Añade la fila a la tabla de endpoints de `docs/api/contracts/README.md`.
7. Anota la tarea de implementación en `docs/implementation-checklist.md`.

No escribas código de Laravel en este comando. Solo el contrato.
