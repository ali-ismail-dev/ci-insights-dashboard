#!/bin/bash
set -e

echo "Starting Render deployment..."

# Run migrations
echo "Running migrations..."
php artisan migrate --force

# Cache configuration
echo "Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Index searchable models
echo "Indexing searchable models in Meilisearch..."
php artisan scout:import "App\\Models\\Repository" || true
php artisan scout:import "App\\Models\\PullRequest" || true

echo "Deployment complete. Starting application..."

# Start PHP-FPM
exec php-fpm
