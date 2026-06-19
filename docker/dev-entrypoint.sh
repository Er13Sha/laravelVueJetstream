#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# Entrypoint контейнера php (dev). Лежит в /usr/local/bin внутри образа,
# чтобы его не перекрыл volume-mount кода (.:/app).
# ---------------------------------------------------------------------------
set -e

cd /app

DB_HOST="${DB_HOST:-db}"
DB_PORT="${DB_PORT:-5432}"

echo "⏳ Ожидание PostgreSQL ${DB_HOST}:${DB_PORT}…"
until php -r "exit(@fsockopen(getenv('DB_HOST') ?: 'db', (int)(getenv('DB_PORT') ?: 5432)) ? 0 : 1);" 2>/dev/null; do
    sleep 2
done
echo "✅ База доступна."

# Зависимости PHP — если vendor пуст (чистый checkout или новый volume).
if [ ! -f vendor/autoload.php ]; then
    echo "📦 composer install…"
    composer install --no-interaction --prefer-dist
fi

# Каталоги storage/cache и права на запись (на bind-mount Windows chmod может быть no-op — это ок).
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/app/public bootstrap/cache
chmod -R 777 storage bootstrap/cache 2>/dev/null || true

# Ключ приложения — генерируем только если его ещё нет в .env.
if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
    php artisan key:generate --force
fi

# В dev не держим закэшированные конфиг/роуты/вью, чтобы правки применялись сразу.
php artisan config:clear || true
php artisan route:clear  || true
php artisan view:clear   || true

# Симлинк public/storage (фото профиля Jetstream и т.п.).
php artisan storage:link 2>/dev/null || true

# Миграции (идемпотентны). Сидер запускаем только когда таблица users пуста —
# иначе фиксированный test@example.com нарушит уникальный индекс.
php artisan migrate --force
USERS="$(php artisan tinker --execute='echo \App\Models\User::query()->count();' 2>/dev/null | tr -dc '0-9')"
if [ -z "$USERS" ] || [ "$USERS" = "0" ]; then
    echo "🌱 Заполнение базы (db:seed)…"
    php artisan db:seed --force || true
fi

echo "🚀 PHP-FPM (Laravel backend) запущен."

# Запускаем CMD (php-fpm -F).
exec "$@"
