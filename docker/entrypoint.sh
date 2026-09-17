#!/bin/sh
set -eu

mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs

chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

# Keep one generated application key in the shared storage volume so the web
# and queue containers always encrypt credentials with the same key.
if [ -z "${APP_KEY:-}" ]; then
    key_file="storage/app/.app_key"
    env_key=""

    if [ -f .env ]; then
        env_key="$(sed -n 's/^APP_KEY=//p' .env | head -n 1)"
    fi

    if [ -n "$env_key" ]; then
        APP_KEY="$env_key"
    elif [ ! -s "$key_file" ]; then
        php -r 'echo "base64:", base64_encode(random_bytes(32)), PHP_EOL;' > "$key_file"
        chown www-data:www-data "$key_file"
    fi

    if [ -z "${APP_KEY:-}" ]; then
        APP_KEY="$(cat "$key_file")"
    fi

    export APP_KEY

    if [ "${DEV_MODE:-false}" = "true" ] && [ -f .env ] && [ -z "$env_key" ]; then
        sed -i "s|^APP_KEY=.*$|APP_KEY=$APP_KEY|" .env
    fi
fi

if [ "${WAIT_FOR_DB:-false}" = "true" ]; then
    echo "Waiting for database at ${DB_HOST:-db}:${DB_PORT:-3306}..."
    attempts=0
    until php -r '$socket = @fsockopen(getenv("DB_HOST") ?: "db", (int) (getenv("DB_PORT") ?: 3306)); if ($socket) { fclose($socket); exit(0); } exit(1);'; do
        attempts=$((attempts + 1))
        if [ "$attempts" -ge 60 ]; then
            echo "Database did not become available in time." >&2
            exit 1
        fi
        sleep 2
    done
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    gosu www-data php artisan migrate --force
    if [ "${DEV_MODE:-false}" != "true" ]; then
        ln -sfn /var/www/html/storage/app/public /var/www/html/public/storage
    fi
fi

exec gosu www-data "$@"
