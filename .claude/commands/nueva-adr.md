---
description: Crea un ADR numerado a partir de una decisión técnica
argument-hint: <título de la decisión>
allowed-tools: Read, Glob, Write, Bash(ls:*)
---

Crea un nuevo ADR para la decisión: **$ARGUMENTS**

Pasos:
1. Lista `docs/adr/` y calcula el siguiente número secuencial de 4 dígitos.
2. Lee `docs/adr/0000-template.md`.
3. Lee los ADRs existentes para detectar si esta decisión contradice o supersede alguno.
   Si supersede, dilo explícitamente en el nuevo ADR y marca el viejo como
   `Supersedido por ADR-NNNN`.
4. Escribe `docs/adr/NNNN-<slug>.md` con estado `Propuesto` y fecha de hoy.
5. La sección de consecuencias negativas NO puede quedar vacía.
6. Añade la línea correspondiente a la tabla índice de `docs/adr/README.md`.
7. Registra la entrada en `docs/CHANGELOG.md` bajo `[No publicado] → Añadido`.
