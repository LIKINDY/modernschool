# ─── Stage 1: Build ───────────────────────────────────────────────────────────
FROM php:8.2-cli-bookworm AS builder

RUN apt-get update -y && apt-get install -y --no-install-recommends \
    git \
    curl \
    zip \
    unzip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libsqlite3-dev \
    sqlite3 \
    && docker-php-ext-install pdo pdo_sqlite mbstring bcmath gd zip \
    && apt-get autoremove -y \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction --prefer-dist

COPY . .

RUN composer run-script post-autoload-dump --no-interaction || true

# ─── Stage 2: Production ──────────────────────────────────────────────────────
FROM php:8.2-cli-bookworm

RUN apt-get update -y && apt-get install -y --no-install-recommends \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libsqlite3-dev \
    sqlite3 \
    && docker-php-ext-install pdo pdo_sqlite mbstring bcmath gd zip \
    && apt-get autoremove -y \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY --from=builder /app /app
COPY start.sh /start.sh
RUN chmod +x /start.sh

RUN cp .env.example .env \
    && sed -i 's/APP_ENV=local/APP_ENV=production/' .env \
    && sed -i 's/APP_DEBUG=true/APP_DEBUG=false/' .env \
    && sed -i 's/SESSION_DRIVER=database/SESSION_DRIVER=file/' .env \
    && sed -i 's/CACHE_STORE=database/CACHE_STORE=file/' .env \
    && sed -i 's/QUEUE_CONNECTION=database/QUEUE_CONNECTION=sync/' .env

RUN mkdir -p database && touch database/database.sqlite

RUN php artisan key:generate --force

RUN php artisan migrate --force --no-interaction || true


RUN chmod -R 775 storage bootstrap/cache

EXPOSE 10000

CMD ["/start.sh"]
