#!/bin/sh
set -e

DATA_DIR=/data
mkdir -p "$DATA_DIR" "$DATA_DIR/photos"

# Keep one application key per volume so sessions survive restarts.
if [ -z "$APP_KEY" ]; then
    if [ ! -f "$DATA_DIR/app.key" ]; then
        php -r 'echo "base64:".base64_encode(random_bytes(32));' > "$DATA_DIR/app.key"
    fi
    export APP_KEY="$(cat "$DATA_DIR/app.key")"
fi

touch "$DB_DATABASE"

php artisan migrate --force

# Seed once per volume, so operator changes (such as unpublishing) survive a restart.
if [ ! -f "$DATA_DIR/.seeded" ]; then
    php artisan db:seed --force
    touch "$DATA_DIR/.seeded"
fi

echo "The Board is running at ${APP_URL}"

exec "$@"
