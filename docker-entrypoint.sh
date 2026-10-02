#!/bin/bash
set -e

echo "[entrypoint] Preparing Zinus IT runtime..."

# Fix permissions (secure: 755 for dirs, 644 for files)
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 755 /var/www/html/storage /var/www/html/bootstrap/cache
find /var/www/html/storage /var/www/html/bootstrap/cache -type f -exec chmod 644 {} \;

# Remove the Vite hot file so production uses built assets.
rm -f /var/www/html/public/hot
echo "[entrypoint] Runtime permissions ready"
exec "$@"
