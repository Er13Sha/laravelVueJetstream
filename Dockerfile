# syntax=docker/dockerfile:1

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts --ignore-platform-reqs

# ---------------------------------------------------------------------------
# 2) Сборка фронтенда (Vite + Vue + Element Plus).
# ---------------------------------------------------------------------------
FROM node:24-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
# Конфиги сборки: без postcss/tailwind Vite не скомпилирует @tailwind-директивы.
COPY vite.config.js postcss.config.js tailwind.config.js ./
RUN npm run build

# ---------------------------------------------------------------------------
# 3) Бэкенд: PHP-FPM 8.4 + pdo_pgsql + gd.
# ---------------------------------------------------------------------------
FROM php:8.4-fpm-alpine AS app
WORKDIR /app

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apk add --no-cache \
        bash git unzip \
        libpq postgresql-dev \
        libpng libpng-dev \
        libjpeg-turbo libjpeg-turbo-dev \
        freetype freetype-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql gd

# Исходники + собранные ассеты.
COPY . .
COPY --from=assets /app/public/build ./public/build

RUN cp .env.docker .env \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/app/public bootstrap/cache \
    && composer install --no-interaction --prefer-dist --optimize-autoloader \
    && php artisan key:generate --force \
    && sed -i 's/\r$//' docker/entrypoint.sh \
    && chmod +x docker/entrypoint.sh \
    && chmod -R 777 storage bootstrap/cache

ENTRYPOINT ["/app/docker/entrypoint.sh"]
CMD ["php-fpm", "-F"]

# ---------------------------------------------------------------------------
# 4) Веб/фронтенд: nginx раздаёт собранные ассеты и картинки, проксирует PHP.
# ---------------------------------------------------------------------------
FROM nginx:alpine AS web
COPY --from=app /app/public /app/public
COPY --from=app /app/storage/app/public /app/storage/app/public
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf

# ---------------------------------------------------------------------------
# DEV: PHP-FPM 8.4 для локальной разработки.
# Код НЕ копируется в образ — он монтируется из docker-compose (volume .:/app),
# поэтому правки видны сразу. Ассеты в dev раздаёт Vite dev-сервер (сервис frontend).
# ---------------------------------------------------------------------------
FROM php:8.4-fpm-alpine AS dev
WORKDIR /app

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apk add --no-cache \
        bash git unzip \
        libpq postgresql-dev \
        libpng libpng-dev \
        libjpeg-turbo libjpeg-turbo-dev \
        freetype freetype-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql gd

# Entrypoint кладём в образ (вне /app), чтобы его не перекрыл volume-mount кода.
COPY docker/dev-entrypoint.sh /usr/local/bin/dev-entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/dev-entrypoint.sh \
    && chmod +x /usr/local/bin/dev-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/dev-entrypoint.sh"]
CMD ["php-fpm", "-F"]
