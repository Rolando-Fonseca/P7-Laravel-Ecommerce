# ADR-0002 — La versión de la API va en la ruta

- **Estado:** Aceptado
- **Fecha:** 2026-08-29
- **Decide:** Equipo P7
- **Relacionados:** ADR-0001, `docs/api/contracts/00-convenciones.md`

## Contexto

La API la van a consumir clientes que no controlamos y que no se actualizan a la vez que
el servidor: una app móvil publicada en tiendas puede tardar semanas en que sus usuarios
la actualicen. Necesitábamos poder introducir cambios incompatibles sin romper a los
clientes viejos.

## Decisión

La versión va en el **primer segmento de la ruta**: `/api/v1/products`.

Toda ruta cuelga de un grupo `v1`. La versión sube solo ante cambios incompatibles:
quitar un campo, cambiar su tipo, cambiar el significado de un código de error o hacer
obligatorio un campo antes opcional. Añadir campos **no** sube la versión.

## Alternativas consideradas

| Alternativa | Por qué no |
|---|---|
| Header `Accept: application/vnd.nogal.v1+json` | Más "correcto" según REST y peor en la práctica: no se pega en un navegador, no aparece en los logs de acceso, algunos proxies lo eliminan y depurar con cURL exige recordarlo siempre. |
| Query param `?version=1` | Se pierde en redirecciones y ensucia todas las URLs de caché. |
| Sin versión | Funciona hasta el primer cambio incompatible, que siempre llega. |

## Consecuencias

### Positivas
- La versión es visible en logs, métricas, caché de CDN y en cualquier captura de red.
- Convivencia trivial de `v1` y `v2` en el mismo despliegue.
- Se puede medir el tráfico por versión para decidir cuándo apagar la vieja.

### Negativas
- Duplicación de rutas y controllers cuando exista `v2`. Se acepta: la alternativa es
  llenar los controllers de condicionales por versión, que es peor.
- Las URLs son más largas.
- Nada obliga a que `v1` y `v2` compartan lógica; hay que vigilar activamente que no se
  bifurquen las reglas de negocio.

## Revisión

Cuando exista `v2`, revisar si la duplicación de controllers se volvió inmanejable. Si
es así, extraer la lógica común a servicios de dominio compartidos, nunca a condicionales.
