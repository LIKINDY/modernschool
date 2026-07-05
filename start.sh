#!/bin/bash
set -e

echo "==> Starting ModernSchool Laravel App..."

# Clear old cached config (it was built without Render's env vars)
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Ensure storage directories exist
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/framework/cache/data
mkdir -p storage/logs
mkdir -p bootstrap/cache

# Ensure SQLite database exists
mkdir -p database
touch database/database.sqlite

# Set permissions
chmod -R 775 storage bootstrap/cache

# Run migrations (in case DB is fresh)
php artisan migrate --force --no-interaction || true

# Re-cache with correct runtime env vars
php artisan config:cache
php artisan route:cache

echo "==> Starting server on port ${PORT:-10000}..."
exec php artisan serve --host=0.0.0.0 --port=${PORT:-10000}
