#!/bin/bash
set -e

echo "[entrypoint] Starting Zinus IT..."

# Fix permissions (secure: 755 for dirs, 644 for files)
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
chmod -R 755 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
find /var/www/html/storage /var/www/html/bootstrap/cache -type f -exec chmod 644 {} \; 2>/dev/null || true

# Hapus public/hot — kalau ada, Laravel akan pakai Vite dev server (blank page)
rm -f /var/www/html/public/hot
echo "[entrypoint] Removed public/hot (if existed)"

# Clear stale view cache (safe before migrate — no DB needed)
php artisan view:clear || { echo "[WARNING] view:clear failed"; }

# Run migrations first — cache table may not exist yet on fresh DB
echo "[entrypoint] Running migrations..."
if ! php artisan migrate --force --no-interaction; then
    echo "[ERROR] Database migrations failed!"
    exit 1
fi
echo "[entrypoint] ✓ Migrations completed"

# Clear app cache after migrate (cache table now exists)
if ! php artisan cache:clear; then
    echo "[WARNING] cache:clear failed, continuing..."
fi

# Cache config & routes
echo "[entrypoint] Optimizing cache..."
if ! php artisan config:cache; then
    echo "[ERROR] config:cache failed!"
    exit 1
fi
echo "[entrypoint] ✓ Config cached"

if ! php artisan route:cache; then
    echo "[ERROR] route:cache failed!"
    exit 1
fi
echo "[entrypoint] ✓ Routes cached"

if ! php artisan event:cache; then
    echo "[WARNING] event:cache failed, continuing..."
fi

# Cache views only kalau manifest ada
if [ -f "/var/www/html/public/build/manifest.json" ]; then
    echo "[entrypoint] Assets found — caching views..."
    if ! php artisan view:cache; then
        echo "[WARNING] view:cache failed, continuing..."
    fi
    echo "[entrypoint] ✓ Views cached"
else
    echo "[entrypoint] ⚠️  WARNING: public/build/manifest.json not found"
    echo "[entrypoint]    Built assets are required for production!"
    echo "[entrypoint]    To fix, run: npm run build && docker cp public/build <container>:/var/www/html/public/"
fi

# Storage symlink
if ! php artisan storage:link --force 2>&1; then
    echo "[WARNING] storage:link failed, continuing..."
fi

echo "[entrypoint] ✓ All initialization complete"
echo "[entrypoint] Starting Apache on port 80..."
exec "$@"
