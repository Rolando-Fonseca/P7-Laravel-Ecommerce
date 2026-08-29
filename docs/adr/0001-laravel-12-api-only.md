# ADR-0001 — Laravel 12 como API pura, sin frontend acoplado

- **Estado:** Aceptado
- **Fecha:** 2026-08-29
- **Decide:** Equipo P7
- **Relacionados:** `docs/architecture.md`

## Contexto

El proyecto P7 debía entregar el backend de un ecommerce de ropa masculina. Laravel 12
ofrece starter kits con frontend incluido (Livewire, Inertia con React o Vue) y también
la vía de instalar solo el andamiaje de API.

Los consumidores previstos son tres y de naturaleza distinta: la tienda pública, un panel
de administración y futuras integraciones (ERP, pasarela de pago, marketplace). Ninguno
comparte ciclo de despliegue con los otros.

## Decisión

Laravel 12 en modo **API pura**. Se ejecuta `php artisan install:api` (Sanctum + rutas de
API) y **no** se instala ningún starter kit de frontend. No hay Blade, no hay Inertia,
no hay Vite en el flujo de producción.

Toda la superficie pública es `/api/v1/*` y devuelve JSON.

## Alternativas consideradas

| Alternativa | Por qué no |
|---|---|
| Starter kit con Livewire | Acopla el ciclo de vida del frontend al del backend. Un cambio de color obliga a desplegar la API. |
| Inertia + React | Igual de acoplado, y añade una cadena de build de Node a un repositorio que no la necesita. |
| Monolito con Blade y API secundaria | Termina con dos formas de leer los mismos datos y reglas duplicadas en las vistas. |

## Consecuencias

### Positivas
- Un solo contrato para los tres consumidores.
- El backend se despliega sin compilar nada de JavaScript.
- Los tests son de HTTP puro: rápidos y sin navegador.
- Cambiar de tecnología de frontend no toca este repositorio.

### Negativas
- **No hay ninguna interfaz para demostrar el proyecto.** La demo se hace con cURL,
  Postman o los tests de feature. Para la entrega hay que preparar esa secuencia
  explícitamente.
- El panel de administración es trabajo aparte que este repositorio no cubre.
- Sin CSRF de sesión hay que ser más cuidadoso con la autenticación por token.

## Revisión

Se reabre si aparece la necesidad de un panel de administración interno con menos de dos
semanas de plazo. En ese escenario, un Livewire acoplado puede salir más barato que un
frontend separado.
