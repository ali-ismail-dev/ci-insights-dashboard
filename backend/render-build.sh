#!/usr/bin/env bash
# Exit on error
set -o errexit

echo "Running migrations..."
php artisan migrate --force

echo "Optimizing..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
