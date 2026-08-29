# ADR-0009 — Búsqueda con LIKE en el MVP

- **Estado:** Aceptado
- **Fecha:** 2026-08-29
- **Decide:** Equipo P7
- **Relacionados:** `docs/api/contracts/01-products.md`

## Contexto

El contrato de `GET /api/v1/products` incluye el parámetro `q` para búsqueda por texto.
El catálogo tiene unos 200 productos.

Las opciones iban desde `LIKE '%termino%'` hasta un motor dedicado (Meilisearch,
Typesense, Algolia) pasando por el índice de texto completo de MySQL.

## Decisión

`LIKE '%termino%'` sobre `products.name` y `products.description`, con normalización
previa del término a minúsculas y sin tildes.

Longitud mínima de 2 caracteres, máxima de 80. Menos de 2 caracteres devuelve `422`: una
búsqueda de un carácter recorre la tabla entera para devolver casi todo.

## Alternativas consideradas

| Alternativa | Por qué no |
|---|---|
| `FULLTEXT` de MySQL | Nos ata a MySQL y rompe el entorno de desarrollo, que usa SQLite (ADR-0010). Habría que mantener dos rutas de código para la misma consulta. |
| Laravel Scout + Meilisearch | Un servicio más que desplegar, monitorizar y sincronizar. Para 200 productos, el coste operativo supera con creces el beneficio. |
| Algolia | Servicio de pago con coste por operación desde el primer día, para un catálogo que cabe en memoria. |

## Consecuencias

### Positivas
- Cero infraestructura añadida.
- Funciona igual en SQLite y en MySQL: un solo camino de código.
- Suficiente para 200 productos: el recorrido completo de tabla es de milisegundos.

### Negativas
- **`LIKE '%x%'` no usa índices.** Es un recorrido completo de tabla, siempre.
- **Sin tolerancia a errores de escritura.** Quien busque "oxfor" u "oksford" no encuentra
  nada. En una tienda real eso son ventas perdidas y no queda registro de ello.
- Sin relevancia: los resultados salen en el orden de `sort`, no por lo bien que encajan
  con el término.
- Sin sinónimos: "chaqueta" no encuentra "bomber" aunque sea lo que el cliente quiere.
- Sin lematización: "camisas" no encuentra "camisa".

## Revisión

Se reabre cuando ocurra cualquiera de estas señales:

1. El catálogo supere **2.000 productos**.
2. El p95 de `GET /products?q=` supere **300 ms**.
3. Se mida una tasa de búsquedas sin resultados por encima del **15%**.

Instrumentar desde el primer despliegue: `search.zero_results_rate` y el registro de los
términos buscados. Sin esos datos, la decisión de migrar a un motor real será una
corazonada.
