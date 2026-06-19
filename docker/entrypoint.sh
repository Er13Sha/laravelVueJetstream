#!/usr/bin/env bash
set -e

echo "⏳ Ожидание базы данных db:5432…"
until php -r "exit(@fsockopen('db', 5432) ? 0 : 1);" 2>/dev/null; do
    sleep 2
done
echo "✅ База доступна."

# Ключ приложения (если ещё не задан в .env).
if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi

php artisan migrate --force
# Сидер best-effort: на повторном старте фиксированный test@example.com
# нарушил бы уникальный индекс, поэтому не валим контейнер.
php artisan db:seed --force || true
php artisan config:cache || true

echo "🚀 Бэкенд (PHP-FPM) запущен."

# Запускаем то, что указано в CMD (php-fpm).
exec "$@"
