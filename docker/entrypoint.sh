#!/usr/bin/env bash
set -e

cd /app

echo "Ожидание PostgreSQL db:5432…"
until php -r "exit(@fsockopen('db', 5432) ? 0 : 1);" 2>/dev/null; do
    sleep 2
done
echo "База доступна."

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
fi

if [ ! -f .env ]; then
    cp .env.docker .env
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/app/public bootstrap/cache
chmod -R 777 storage bootstrap/cache 2>/dev/null || true

php artisan config:clear || true
php artisan storage:link 2>/dev/null || true
php artisan migrate --force

php artisan db:seed --force

echo "PHP-FPM запущен."
exec "$@"
