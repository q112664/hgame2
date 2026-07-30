# syntax=docker/dockerfile:1

# -----------------------------------------------------------------------------
# Build: PHP deps + Wayfinder/Vite assets (Wayfinder needs artisan during vite)
# -----------------------------------------------------------------------------
FROM serversideup/php:8.4-cli AS build

USER root

RUN install-php-extensions gd intl zip exif bcmath pcntl pgsql redis \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

COPY --chown=www-data:www-data package.json package-lock.json ./
RUN npm install

COPY --chown=www-data:www-data . .

# Dummy env so artisan / filament / wayfinder can run at build time.
# Wayfinder must emit relative URLs (see Setting::applySiteUrlToConfig).
RUN cp .env.example .env \
    && php artisan key:generate --force \
    && composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && php artisan package:discover --ansi \
    && php artisan filament:upgrade \
    && php artisan wayfinder:generate --with-form --no-interaction \
    && npm run build \
    && rm -f .env \
    && rm -rf node_modules /root/.npm /var/www/.composer/cache

# -----------------------------------------------------------------------------
# Runtime: PHP-FPM + Nginx (port 8080)
# -----------------------------------------------------------------------------
FROM serversideup/php:8.4-fpm-nginx AS app

USER root

# GD/intl/zip for Filament media; pgsql for Docker PostgreSQL; redis for cache/queue/session
RUN install-php-extensions gd intl zip exif bcmath pcntl pgsql redis

WORKDIR /var/www/html

COPY --from=build --chown=www-data:www-data /var/www/html /var/www/html

COPY --chmod=755 docker/ensure-storage.sh /etc/entrypoint.d/99-ensure-storage.sh

RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        storage/app/public \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache

USER www-data

ENV SSL_MODE=off \
    AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_STORAGE_LINK=true \
    AUTORUN_LARAVEL_MIGRATION=true \
    AUTORUN_LARAVEL_OPTIMIZE=true \
    HEALTHCHECK_PATH=/up \
    PHP_OPCACHE_ENABLE=1

EXPOSE 8080 8443

HEALTHCHECK --interval=30s --timeout=5s --start-period=90s --retries=3 \
    CMD curl -f http://127.0.0.1:8080/up || exit 1
