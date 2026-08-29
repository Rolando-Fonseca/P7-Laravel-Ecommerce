---
description: Ejecuta la suite con cobertura y reporta contra el umbral del proyecto
allowed-tools: Read, Grep, Glob, Bash(php artisan test:*), Bash(vendor/bin/*)
---

Ejecuta la batería de pruebas con cobertura y evalúa el resultado.

1. `php artisan test --coverage --min=70`
2. Si falla el umbral, NO bajes el umbral. Lista los archivos por debajo del 70%
   ordenados por criticidad de dominio (Inventario > Pedidos > Carrito > Catálogo).
3. Para cada archivo crítico descubierto, propón el test concreto que falta
   nombrándolo, sin escribirlo todavía.
4. Verifica aparte que `app/Domain/` esté por encima del 85%. Es la regla más
   estricta del proyecto y la que más deuda evita.
5. Resume en una tabla: módulo, cobertura, veredicto.
