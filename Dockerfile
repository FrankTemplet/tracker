# ──────────────────────────────────────────────
# Stage 1: build frontend assets
# ──────────────────────────────────────────────
FROM node:22-alpine AS frontend

WORKDIR /app

# Copy package manifests and install deps
COPY package.json pnpm-lock.yaml pnpm-workspace.yaml .npmrc ./
RUN npm install -g pnpm && pnpm install --frozen-lockfile

# Copy source and build
COPY . .
RUN pnpm run build

# ──────────────────────────────────────────────
# Stage 2: PHP + nginx production image
# ──────────────────────────────────────────────
FROM php:8.3-fpm-alpine AS app

# System dependencies
RUN apk add --no-cache \
    nginx \
    curl \
    unzip \
    libpng-dev \
    libzip-dev \
    oniguruma-dev \
    icu-dev \
    sqlite \
    sqlite-dev \
    && docker-php-ext-install \
        pdo \
        pdo_sqlite \
        pdo_mysql \
        mbstring \
        zip \
        gd \
        intl \
        opcache

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy composer files first for layer caching
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Copy full application source
COPY . .

# Copy built frontend assets from stage 1
COPY --from=frontend /app/public/build ./public/build

# Finish composer install (autoloader + scripts)
RUN composer dump-autoload --optimize && \
    composer run-script post-autoload-dump 2>/dev/null || true

# Create SQLite database file and set permissions
RUN mkdir -p database && \
    touch database/database.sqlite && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html/storage /var/www/html/bootstrap/cache

# Nginx configuration
COPY docker/nginx.conf /etc/nginx/nginx.conf

# PHP-FPM opcache tuning for production
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.interned_strings_buffer=8'; \
    echo 'opcache.max_accelerated_files=10000'; \
    echo 'opcache.validate_timestamps=0'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

# Entrypoint
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

CMD ["/entrypoint.sh"]
