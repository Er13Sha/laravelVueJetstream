# syntax=docker/dockerfile:1

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts --ignore-platform-reqs

FROM node:24-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js postcss.config.js tailwind.config.js ./
COPY --from=vendor /app/vendor/tightenco/ziggy ./vendor/tightenco/ziggy
RUN npm run build

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

FROM nginx:alpine AS web
COPY --from=app /app/public /app/public
COPY --from=app /app/storage/app/public /app/storage/app/public
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf

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

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh \
    && chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm", "-F"]
