# ─── Stage 1: Build ───────────────────────────────────────────────────────────
FROM php:8.2-cli AS builder

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    sqlite3 \
    && docker-php-ext-install pdo pdo_sqlite mbstring exif pcntl bcmath gd zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy composer files first (layer caching)
COPY composer.json composer.lock ./

# Install PHP dependencies (no dev)
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction --prefer-dist

# Copy rest of application
COPY . .

# Run composer scripts (post-install)
RUN composer run-script post-autoload-dump --no-interaction || true

# ─── Stage 2: Production Image ────────────────────────────────────────────────
FROM php:8.2-cli

# Install runtime dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    sqlite3 \
    && docker-php-ext-install pdo pdo_sqlite mbstring exif pcntl bcmath gd zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Copy app from builder
COPY --from=builder /app /app

# Setup environment
RUN cp .env.example .env \
    && sed -i 's/APP_ENV=local/APP_ENV=production/' .env \
    && sed -i 's/APP_DEBUG=true/APP_DEBUG=false/' .env \
    && sed -i 's/SESSION_DRIVER=database/SESSION_DRIVER=file/' .env \
    && sed -i 's/CACHE_STORE=database/CACHE_STORE=file/' .env \
    && sed -i 's/QUEUE_CONNECTION=database/QUEUE_CONNECTION=sync/' .env

# Create SQLite database
RUN mkdir -p database && touch database/database.sqlite

# Generate app key
RUN php artisan key:generate --force

# Run migrations
RUN php artisan migrate --force --no-interaction || true

# Cache config, routes, views
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache || true

# Set permissions
RUN chmod -R 775 storage bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache || true

# Expose port
EXPOSE 10000

# Start Laravel server
CMD php artisan serve --host=0.0.0.0 --port=${PORT:-10000}
