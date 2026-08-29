---
description: Implementa un endpoint ya contratado, con sus tests
argument-hint: <METODO /api/v1/ruta>
allowed-tools: Read, Glob, Grep, Write, Edit, Bash(php artisan:*), Bash(vendor/bin/*)
---

Implementa el endpoint: **$ARGUMENTS**

**Requisito previo:** debe existir su contrato en `docs/api/contracts/`.
Si no existe, detente y ejecuta primero `/nuevo-contrato`.

Orden de trabajo (no lo alteres):
1. Lee el contrato completo. Es la especificación; el código se le somete.
2. FormRequest con las reglas de validación exactas de la tabla del contrato.
3. Servicio de dominio en `app/Domain/<Modulo>/` con la lógica. El controller no
   contiene reglas de negocio: valida, delega, responde.
4. API Resource para la forma de la respuesta. Nunca devuelvas modelos Eloquent crudos.
5. Ruta en `routes/api.php` dentro del grupo `v1`, con su rate limit.
6. Tests de feature en `tests/Feature/Api/V1/`: camino feliz + cada error de la tabla
   del contrato.
7. `php artisan test --filter=<clase>` hasta verde.
8. `vendor/bin/pint --dirty`.
9. Marca la casilla en `docs/implementation-checklist.md` y añade la línea a
   `docs/CHANGELOG.md`.

Si durante la implementación descubres que el contrato está mal, NO lo parchees en el
código: corrige el contrato, deja constancia en el CHANGELOG y luego implementa.
