# Imagen de despliegue de la API de Nogal (demo).
#
# SQLite sobre el sistema de archivos efimero del contenedor: en cada arranque
# la base se recrea y se siembra (ver docker/start.sh). Para una demo academica
# eso es una virtud, no un defecto: el catalogo siempre esta en su estado
# canonico y nadie puede dejarlo roto.
FROM php:8.4-cli-alpine

# intl y zip son las unicas extensiones que no vienen en la imagen base;
# pdo_sqlite si viene compilado en alpine.
RUN apk add --no-cache icu-dev libzip-dev sqlite \
    && docker-php-ext-install intl zip pdo_mysql > /dev/null

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Capa de dependencias separada: cambiar el codigo no invalida el composer install
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && chmod +x docker/start.sh

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/app/database/database.sqlite \
    SESSION_DRIVER=file \
    CACHE_STORE=file \
    QUEUE_CONNECTION=sync

EXPOSE 8080

CMD ["docker/start.sh"]
