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
# Fotos semilla de los médicos ya seedeados: mismo nombre de archivo que
# el photo_path que se backfillea en doctor_profiles (ver runbook de
# deploy). Efímero igual que el resto de storage/app/public — sobrevive
# solo hasta el próximo build, pero eso alcanza para el seed inicial.
COPY frontend/public/images/doctors/ ./storage/app/public/doctor-photos/
COPY --from=frontend-build /repo/backend/public/build ./public/build
# LandingHero.vue referencia estas imágenes con ruta absoluta (/images/...),
# que el navegador resuelve contra la raíz pública de Laravel — no contra
# public/build/. Vite las necesita en frontend/public/ para resolver el
# import en build time; acá las duplicamos donde Apache realmente las sirve.
COPY --from=frontend-build /repo/backend/public/build/images ./public/images

# storage:link acá (build time) alcanza para que el symlink exista —
# no necesita nada del entorno de runtime. Lo que SÍ depende del entorno:
# storage/app/public es efímero en cada redeploy salvo que se monte un
# volumen persistente en Coolify sobre esa ruta. Sin volumen, las fotos
# subidas por el admin sobreviven hasta el próximo deploy, no más.
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress \
    && cp .env.example .env \
    && php artisan storage:link \
    && chown -R www-data:www-data storage bootstrap/cache public/storage \
    && chmod -R ug+rwX storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
