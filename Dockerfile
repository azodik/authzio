# syntax=docker/dockerfile:1

FROM node:24-bookworm AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js tsconfig.json ./
COPY public ./public
RUN npm run build \
    && rm -rf node_modules

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-progress
COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY database ./database
COPY routes ./routes
COPY resources ./resources
COPY public ./public
COPY artisan VERSION ./
RUN composer dump-autoload --optimize --no-dev --no-interaction

FROM php:8.5-fpm-bookworm

ARG AUTHZIO_VERSION=0.0.0
ARG AUTHZIO_BUILD=dev
ARG AUTHZIO_COMMIT=unknown

ENV AUTHZIO_VERSION=${AUTHZIO_VERSION} \
    AUTHZIO_BUILD=${AUTHZIO_BUILD} \
    AUTHZIO_COMMIT=${AUTHZIO_COMMIT}

LABEL org.opencontainers.image.title="Authzio" \
      org.opencontainers.image.version="${AUTHZIO_VERSION}" \
      org.opencontainers.image.revision="${AUTHZIO_COMMIT}" \
      org.opencontainers.image.description="Authzio IAM — SemVer ${AUTHZIO_VERSION}, build ${AUTHZIO_BUILD}"

# PHP 8.5 ships opcache built-in (no separate .so) — do not docker-php-ext-install it.
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    supervisor \
    libpq-dev \
    libzip-dev \
    unzip \
    curl \
    $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql zip \
    && pecl install redis-6.3.0 \
    && docker-php-ext-enable redis \
    && apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/* /tmp/pear /tmp/* /var/tmp/*

WORKDIR /var/www/html

COPY --from=vendor /app/vendor /var/www/html/vendor
COPY --from=vendor /app/composer.json /var/www/html/composer.json
COPY --from=vendor /app/composer.lock /var/www/html/composer.lock
COPY --from=vendor /app/app /var/www/html/app
COPY --from=vendor /app/bootstrap /var/www/html/bootstrap
COPY --from=vendor /app/config /var/www/html/config
COPY --from=vendor /app/database /var/www/html/database
COPY --from=vendor /app/routes /var/www/html/routes
COPY --from=vendor /app/resources /var/www/html/resources
COPY --from=vendor /app/artisan /var/www/html/artisan
COPY --from=vendor /app/VERSION /var/www/html/VERSION
COPY --from=vendor /app/public /var/www/html/public
COPY --from=frontend /app/public/build /var/www/html/public/build
COPY docker/nginx.conf /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisor/conf.d/authzio.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN printf '%s\n' "{\"version\":\"${AUTHZIO_VERSION}\",\"build\":\"${AUTHZIO_BUILD}\",\"commit\":\"${AUTHZIO_COMMIT}\"}" \
        > /var/www/html/build-info.json \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app/public bootstrap/cache \
    && chmod +x /usr/local/bin/entrypoint.sh \
    && chown -R www-data:www-data storage bootstrap/cache \
    && rm -f /etc/nginx/sites-enabled/default \
    && ln -s /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default \
    # Frontend source is not needed at runtime (Vite output lives in public/build).
    && rm -rf resources/js resources/css \
    && find storage bootstrap/cache -type d -exec chmod 775 {} \;

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD curl -fsS http://127.0.0.1/ >/dev/null || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
