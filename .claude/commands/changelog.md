---
description: Actualiza docs/CHANGELOG.md a partir de los cambios sin commitear
allowed-tools: Read, Edit, Bash(git status:*), Bash(git diff:*), Bash(git log:*)
---

Actualiza `docs/CHANGELOG.md` con el trabajo pendiente de registrar.

1. `git status --short` y `git diff --stat` para ver qué cambió.
2. Clasifica cada cambio en Keep a Changelog: `Añadido`, `Cambiado`, `Obsoleto`,
   `Eliminado`, `Corregido`, `Seguridad`.
3. Escribe en la sección `[No publicado]`. Una línea por cambio, en español,
   describiendo el efecto para quien consume la API — no el archivo que tocaste.
   Bien: `Añadido filtro por talla en GET /api/v1/products`.
   Mal: `Modificado ProductController.php`.
4. Si cambió un contrato de forma incompatible, márcalo con `**BREAKING**` y verifica
   que exista el ADR que respalda el cambio.
5. No hagas commit. Solo actualizas el archivo.
