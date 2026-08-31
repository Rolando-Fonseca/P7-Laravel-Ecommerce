#!/bin/sh
set -e

# APP_KEY viene del entorno en produccion; si falta (primer arranque de una
# demo), se genera una efimera. Con sesiones en archivo y sin datos reales,
# perderla en un reinicio no rompe nada.
if [ -z "$APP_KEY" ]; then
    export APP_KEY=$(php -r "echo 'base64:'.base64_encode(random_bytes(32));")
    echo "APP_KEY efimera generada para esta instancia."
fi

# Base de datos desde cero en cada arranque: el catalogo de Nogal siempre
# aparece en su estado canonico (8 productos, 76 variantes, stock sembrado).
touch "$DB_DATABASE"
php artisan migrate:fresh --force --seed

php artisan config:cache
php artisan route:cache

PORT="${PORT:-8080}"
echo "Nogal API escuchando en :$PORT"

# artisan serve es el servidor de desarrollo. Para una demo academica de
# trafico minimo es suficiente y evita meter nginx/fpm en la imagen; si esto
# fuera a recibir trafico real, la imagen deberia migrar a FrankenPHP u Octane.
exec php artisan serve --host=0.0.0.0 --port="$PORT"
