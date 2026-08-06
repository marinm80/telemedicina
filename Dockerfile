# ====================================================================
# Dockerfile de PRODUCCIÓN — Plataforma de Telemedicina
# AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
# ====================================================================
#
# NO reemplaza a backend/Dockerfile ni frontend/Dockerfile — esos son
# de desarrollo local (php artisan serve / vite dev) y se usan desde
# docker-compose.yml. Este Dockerfile es exclusivo de Coolify/producción:
# un solo contenedor, Apache sirviendo Laravel, con los assets de Vite
# ya compilados adentro.
#
# Stage 1: compila el frontend con Vite. vite.config.ts define
# outDir: '../backend/public/build' (relativo a /repo/frontend), así que
# el build cae directo en /repo/backend/public/build sin pasos extra.
FROM node:22-alpine AS frontend-build
WORKDIR /repo
COPY frontend/ ./frontend/
COPY docs/ ./docs/
WORKDIR /repo/frontend
RUN corepack enable && corepack prepare pnpm@latest --activate
RUN pnpm install --frozen-lockfile
RUN pnpm build

# Stage 2: runtime — Apache + PHP 8.4 sirviendo el backend Laravel.
FROM php:8.4-apache AS backend

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev \
        libzip-dev \
        unzip \
        git \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN apt-get update && apt-get install -y --no-install-recommends $PHPIZE_DEPS \
    && docker-php-ext-install pdo pdo_pgsql zip bcmath \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite
COPY docker/000-default.conf /etc/apache2/sites-available/000-default.conf

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY backend/ ./
COPY --from=frontend-build /repo/backend/public/build ./public/build

RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress \
    && cp .env.example .env \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
