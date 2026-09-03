FROM php:8.4-apache

# Apache modules
RUN a2enmod rewrite headers deflate

# System dependencies
RUN apt-get update && apt-get install -y \
    git curl zip unzip chromium \
    libpng-dev libonig-dev libxml2-dev libldap2-dev libzip-dev libicu-dev \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/pear

# PHP extensions
RUN docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu/ \
    && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd ldap zip opcache intl

# PHP production config
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && echo "upload_max_filesize = 20M" >> "$PHP_INI_DIR/php.ini" \
    && echo "post_max_size = 20M"       >> "$PHP_INI_DIR/php.ini" \
    && echo "memory_limit = 512M"       >> "$PHP_INI_DIR/php.ini" \
    && echo "output_buffering = 4096"   >> "$PHP_INI_DIR/php.ini"

# Apache: listen on port 80 (default)
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Apache: Security headers and compression
RUN echo '<Directory /var/www/html/public>' >> /etc/apache2/apache2.conf \
    && echo 'Header set X-Frame-Options "SAMEORIGIN"' >> /etc/apache2/apache2.conf \
    && echo 'Header set X-Content-Type-Options "nosniff"' >> /etc/apache2/apache2.conf \
    && echo 'Header set X-XSS-Protection "1; mode=block"' >> /etc/apache2/apache2.conf \
    && echo '</Directory>' >> /etc/apache2/apache2.conf

# Ensure www-data user exists with proper UID (33)
RUN useradd -m -u 33 -s /bin/bash www-data 2>/dev/null || true

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Apache document root → Laravel public/
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Copy project files
COPY --chown=www-data:www-data . .

# Install PHP dependencies only
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Storage permissions (secure: 755 for dirs, 644 for files)
RUN mkdir -p storage/framework/{sessions,views,cache} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 755 storage bootstrap/cache \
    && find storage bootstrap/cache -type f -exec chmod 644 {} \;

EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=10s --retries=3 --start-period=40s \
    CMD curl -f http://localhost/up || exit 1

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh && chown www-data:www-data /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["apache2-foreground"]
