# ADR-0010 — SQLite en desarrollo y pruebas, MySQL 8 en producción

- **Estado:** Aceptado
- **Fecha:** 2026-08-29
- **Decide:** Equipo P7
- **Relacionados:** ADR-0006, ADR-0009

## Contexto

Laravel 12 trae SQLite configurado por defecto y crea `database/database.sqlite` al
instalar. Producción va a MySQL 8, que es lo que ofrece el hosting previsto.

Usar motores distintos en desarrollo y producción tiene un nombre y una mala fama
merecida: *dev/prod parity*. Pero la alternativa —levantar MySQL en cada máquina de cada
estudiante— tiene su propio coste, y en un curso ese coste se paga en horas de clase
resolviendo instalaciones.

## Decisión

- **Desarrollo y pruebas automatizadas:** SQLite (`:memory:` en los tests).
- **Producción y entorno de preproducción:** MySQL 8.
- **Antes de cada despliegue:** la batería completa se ejecuta también contra MySQL en CI.

Las migraciones se escriben en el constructor de esquemas de Laravel, sin SQL crudo salvo
las restricciones `CHECK`, que se declaran con `DB::statement()` condicionado al driver.

## Alternativas consideradas

| Alternativa | Por qué no |
|---|---|
| MySQL en todas partes con Docker | Es lo correcto en un equipo profesional. En un curso, Docker Desktop en Windows es una fuente constante de problemas ajenos al temario. |
| SQLite también en producción | No soporta bloqueo a nivel de fila, que es el pilar de ADR-0006. Con dos pedidos concurrentes, inaceptable. |
| PostgreSQL en ambos | Técnicamente superior, pero no es lo que ofrece el hosting previsto. |

## Consecuencias

### Positivas
- `git clone`, `composer install`, `php artisan migrate` y ya se trabaja. Sin Docker,
  sin servicios.
- Los tests con SQLite en memoria son entre 5 y 10 veces más rápidos.
- Sin dependencias externas en el entorno de cada estudiante.

### Negativas
- **SQLite no bloquea filas, bloquea la base de datos entera.** Los tests de concurrencia
  de ADR-0006 **no prueban lo que dicen probar** en SQLite. La prueba real de bloqueo debe
  correr contra MySQL en CI.
- SQLite aplica las `CHECK` de forma distinta y no todas se traducen igual.
- Diferencias de tipos: SQLite es laxo, MySQL estricto. Un `string` demasiado largo pasa
  en desarrollo y falla en producción.
- Ordenación de texto distinta: SQLite no distingue tildes de la misma forma que la
  colación `utf8mb4_unicode_ci`. El `sort=name` puede ordenar diferente.

### Mitigaciones obligatorias
1. La batería completa corre contra MySQL 8 en CI antes de cualquier despliegue.
2. Las invariantes de inventario tienen **test de dominio**, no solo restricción de base
   de datos, precisamente porque SQLite no las aplica igual.
3. Toda columna de texto declara longitud explícita en la migración.

## Revisión

Se reabre si aparece un bug en producción causado por la diferencia de motores. La primera
vez que eso ocurra, el coste de migrar el entorno local a MySQL con Docker ya estará
justificado.
