#!/bin/bash
set -e

echo "[init] Preparing Zinus IT application..."

php artisan view:clear || { echo "[WARNING] view:clear failed"; }

echo "[init] Running database migrations..."
php artisan migrate --force --no-interaction

if ! php artisan cache:clear; then
    echo "[WARNING] cache:clear failed, continuing..."
fi

php artisan config:cache
php artisan route:cache

if ! php artisan event:cache; then
    echo "[WARNING] event:cache failed, continuing..."
fi

if [ -f "/var/www/html/public/build/manifest.json" ]; then
    if ! php artisan view:cache; then
        echo "[WARNING] view:cache failed, continuing..."
    fi
else
    echo "[WARNING] public/build/manifest.json not found; production assets are required."
fi

if ! php artisan storage:link --force 2>&1; then
    echo "[WARNING] storage:link failed, continuing..."
fi

echo "[init] Initialization complete"