#!/bin/bash
set -e

echo "[entrypoint] Preparing Zinus IT runtime..."

# Ensure SSL module and site are enabled (idempotent)
a2enmod ssl socache_shmcb 2>/dev/null || true
a2ensite 000-default-ssl 2>/dev/null || true

# Fix permissions (secure: 755 for dirs, 644 for files)
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 755 /var/www/html/storage /var/www/html/bootstrap/cache
find /var/www/html/storage /var/www/html/bootstrap/cache -type f -exec chmod 644 {} \;

# Remove the Vite hot file so production uses built assets.
rm -f /var/www/html/public/hot

# Verify SSL certificate exists
if [ -f /etc/apache2/ssl/apache-selfsigned.crt ] && [ -f /etc/apache2/ssl/apache-selfsigned.key ]; then
    echo "[entrypoint] ✅ SSL certificate found"
else
    echo "[entrypoint] ❌ SSL certificate missing! Regenerating..."
    openssl req -x509 -nodes -days 3650 -newkey rsa:4096 \
       -keyout /etc/apache2/ssl/apache-selfsigned.key \
       -out /etc/apache2/ssl/apache-selfsigned.crt \
       -subj "/C=ID/ST=West Java/L=Bogor/O=Zinus IT/OU=IT/CN=localhost"
fi

echo "[entrypoint] Runtime ready. Starting Apache with SSL..."
exec "$@"
