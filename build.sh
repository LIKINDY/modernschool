#!/usr/bin/env bash

# Exit on error
set -e

echo "==> Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Creating SQLite database..."
mkdir -p database
touch database/database.sqlite

echo "==> Setting up environment..."
cp .env.example .env
php artisan key:generate --force

echo "==> Configuring for production..."
sed -i 's/APP_ENV=local/APP_ENV=production/' .env
sed -i 's/APP_DEBUG=true/APP_DEBUG=false/' .env
sed -i 's|APP_URL=http://localhost|APP_URL=${APP_URL:-http://localhost}|' .env
sed -i 's/DB_CONNECTION=sqlite/DB_CONNECTION=sqlite/' .env
sed -i 's/SESSION_DRIVER=database/SESSION_DRIVER=file/' .env
sed -i 's/CACHE_STORE=database/CACHE_STORE=file/' .env

echo "==> Running migrations..."
php artisan migrate --force --no-interaction

echo "==> Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Setting storage permissions..."
chmod -R 775 storage bootstrap/cache

echo "==> Build complete!"
