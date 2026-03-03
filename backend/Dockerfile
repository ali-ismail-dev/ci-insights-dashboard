# ==============================================================================
# Laravel 11 Dockerfile for Production Deployment
# ==============================================================================
# Optimized for serverless/container deployments without bundled web server
# PHP-FPM only; web server handled by reverse proxy (Render, nginx, etc.)
# ==============================================================================

FROM php:8.2-fpm-alpine

# Install system dependencies and PHP extensions
RUN apk add --no-cache \
    libzip-dev bash curl git unzip zip \
    freetype-dev libjpeg-turbo-dev libpng-dev \
    mysql-client postgresql-dev \
    icu-dev ca-certificates openssl \
    autoconf g++ make

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    gd pdo_mysql pdo_pgsql opcache pcntl intl bcmath zip

# Install Redis
RUN pecl install redis-6.0.2 && docker-php-ext-enable redis

# Cleanup build tools
RUN apk del autoconf g++ make && rm -rf /var/cache/apk/* /tmp/*

# Install Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copy dependency files
COPY composer.json composer.lock ./

# Install dependencies
RUN COMPOSER_PROCESS_TIMEOUT=900 composer install \
    --no-dev --no-interaction --prefer-dist --no-scripts && \
    composer clear-cache

# Copy application code
COPY . .

# Create necessary directories
RUN mkdir -p /var/run/php storage/logs bootstrap/cache && \
    chown -R www-data:www-data /var/www && \
    chmod -R 755 /var/www && \
    chmod -R 775 storage bootstrap/cache

# Copy PHP configuration
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Copy and set entrypoint
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Configure PHP-FPM for production
RUN echo "listen = 0.0.0.0:9000" >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo "pm = dynamic" >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo "pm.max_children = 20" >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo "pm.start_servers = 5" >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo "pm.min_spare_servers = 3" >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo "pm.max_spare_servers = 10" >> /usr/local/etc/php-fpm.d/zz-docker.conf

EXPOSE 9000

HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
    CMD php-fpm -t || exit 1

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]