#!/bin/sh
set -e

cd /var/www/html

# Generate app key if not set
if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force
fi

# Run database migrations
php artisan migrate --force

# Clear and cache config/routes for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Fix storage permissions (in case the volume was mounted fresh)
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Start PHP-FPM in background
php-fpm -D

# Start nginx in foreground (keeps the container alive)
nginx -g "daemon off;"
