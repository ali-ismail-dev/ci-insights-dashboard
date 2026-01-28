# ==============================================================================
# Multi-Stage Dockerfile for Laravel 11 Application
# ==============================================================================
# Purpose: Production-ready PHP 8.2 environment with minimal footprint
# Stages: base -> dependencies -> development -> production
# Image size target: < 150MB for production
# ==============================================================================

# ------------------------------------------------------------------------------
# Stage 1: Base Image with PHP Extensions
# ------------------------------------------------------------------------------
ARG COMPOSER_VERSION=2.7

FROM php:8.2-fpm-alpine AS base

# Install system dependencies and PHP extensions required by Laravel
# Using --no-cache to minimize image size
# 1. Install System Dependencies
RUN apk add --no-cache \
    libzip-dev bash curl git unzip zip \
    freetype-dev libjpeg-turbo-dev libpng-dev \
    mysql-client postgresql-dev \
    icu-dev \
    ca-certificates \
    openssl \
    autoconf g++ make supervisor wget

# 2. Configure and Install GD (separate to isolate errors)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd

# 3. Install other PHP Extensions
RUN docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    pdo_pgsql \
    opcache \
    pcntl \
    intl \
    bcmath \
    zip

# 4. Install Redis via PECL (Updated version for better Alpine compatibility)
RUN pecl install redis-6.0.2 \
    && docker-php-ext-enable redis

# 5. Cleanup (Optional, but keeps image small)
RUN apk del autoconf g++ make \
    && rm -rf /var/cache/apk/* /tmp/* /var/tmp/*
# Install Composer
# Install Composer - Hardcoded to bypass the expansion error
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Configure PHP-FPM for production performance
RUN echo "listen = /var/run/php/php-fpm.sock" >> /usr/local/etc/php-fpm.d/zz-docker.conf \
    && echo "listen.mode = 0666" >> /usr/local/etc/php-fpm.d/zz-docker.conf \
    && echo "pm = dynamic" >> /usr/local/etc/php-fpm.d/zz-docker.conf \
    && echo "pm.max_children = 20" >> /usr/local/etc/php-fpm.d/zz-docker.conf \
    && echo "pm.start_servers = 5" >> /usr/local/etc/php-fpm.d/zz-docker.conf \
    && echo "pm.min_spare_servers = 3" >> /usr/local/etc/php-fpm.d/zz-docker.conf \
    && echo "pm.max_spare_servers = 10" >> /usr/local/etc/php-fpm.d/zz-docker.conf \
    && echo "pm.max_requests = 500" >> /usr/local/etc/php-fpm.d/zz-docker.conf \
    && echo "catch_workers_output = yes" >> /usr/local/etc/php-fpm.d/zz-docker.conf \
    && echo "decorate_workers_output = no" >> /usr/local/etc/php-fpm.d/zz-docker.conf

# Copy PHP configuration
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# ------------------------------------------------------------------------------
# Stage 2: Dependencies Installation
# ------------------------------------------------------------------------------
FROM base AS dependencies

# Copy dependency manifests
COPY backend/composer.json backend/composer.lock ./
# Install Composer dependencies
# --no-dev for production, with dev for development stage
RUN COMPOSER_PROCESS_TIMEOUT=900 composer install \
    --no-interaction \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    && composer clear-cache

# ------------------------------------------------------------------------------
# Stage 3: Development Environment
# ------------------------------------------------------------------------------
FROM dependencies AS development

# 1. Enable community repo and install Node, NPM, and build tools for Xdebug
RUN echo 'http://dl-cdn.alpinelinux.org/alpine/v3.19/community' >> /etc/apk/repositories && \
    apk update && \
    apk add --no-cache \
        nodejs \
        npm \
        php82-dev \
        autoconf \
        g++ \
        make \
        linux-headers

# 2. Install Xdebug via PECL
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# 3. Clean up build tools
RUN apk del php82-dev autoconf g++ make linux-headers

# Copy Xdebug configuration
COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

# Copy application code
COPY backend/ .

# Generate optimized autoloader with dev dependencies
RUN composer dump-autoload --optimize

# Set proper permissions
RUN chown -R www-data:www-data /var/www \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

EXPOSE 9000

HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
    CMD php-fpm -t || exit 1

CMD ["php-fpm", "-F"]

# ------------------------------------------------------------------------------
# Stage 4: Production Environment
# ------------------------------------------------------------------------------
FROM dependencies AS production

# Copy application code (excluding dev files)
COPY --chown=www-data:www-data backend/ .

# Install production-only dependencies
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader \
    && composer clear-cache

# Generate optimized autoloader
RUN composer dump-autoload --optimize --classmap-authoritative

# Optimize Laravel for production
RUN php artisan config:cache || true \
    && php artisan route:cache || true \
    && php artisan view:cache || true

# Set production permissions (read-only except cache/logs)
RUN chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache \
    && find /var/www -type f -exec chmod 644 {} \; \
    && find /var/www -type d -exec chmod 755 {} \;

# Remove unnecessary files to reduce image size
RUN rm -rf \
    /var/www/tests \
    /var/www/.git \
    /var/www/.env.example \
    /var/www/README.md \
    /var/www/phpunit.xml

# Security: Run as non-root user
USER www-data

# Expose PHP-FPM port
EXPOSE 9000

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
    CMD php-fpm -t || exit 1

# Start PHP-FPM
CMD ["php-fpm", "-F"]