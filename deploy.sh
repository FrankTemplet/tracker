#!/usr/bin/env bash
set -e

cd /var/www/tracker

echo "Pulling latest code..."
git fetch origin main
git reset --hard origin/main

echo "Composer..."
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

echo "Building assets..."
npm ci
npm run build

echo "Migrations..."
php artisan migrate --force

echo "Optimizing..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Reload PHP-FPM..."
sudo systemctl reload php8.4-fpm

echo "Done!"
