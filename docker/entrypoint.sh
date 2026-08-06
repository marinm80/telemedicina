#!/bin/sh
set -e

# Cachea config/vistas con las env vars que Coolify inyecta en runtime (no en
# build). route:cache queda afuera a propósito: hay una ruta con closure en
# api.php y route:cache falla si encuentra una.
php artisan config:cache
php artisan view:cache

exec "$@"
