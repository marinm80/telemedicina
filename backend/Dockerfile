FROM php:8.4-cli-alpine

# Instalar dependencias del sistema
RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    bash

# Instalar extensiones de PHP pdo_pgsql, zip, bcmath y redis
RUN apk add --no-cache --virtual .phpredis-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .phpredis-deps
RUN docker-php-ext-install pdo pdo_pgsql zip bcmath

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configurar directorio de trabajo
WORKDIR /var/www/html

# Exponer el puerto de desarrollo de php artisan serve
EXPOSE 8000

# Comando por defecto
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
