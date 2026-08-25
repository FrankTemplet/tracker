#!/usr/bin/env bash
set -e

cd /var/www/tracker

echo "Pulling latest code"
git fetch origin main
git reset --hard origin/main

echo "Clearing caches from the previous deploy"
# git reset --hard leaves bootstrap/cache/ untouched, so the config and route
# caches written at the end of the previous deploy would still be loaded while
# the code is already the new one. Every artisan call below runs against that
# mismatch, including the one the asset build makes (wayfinder:generate).
# Deleting the files instead of running optimize:clear means no app boot is
# needed, so this also works while vendor/ still holds the previous release.
rm -f bootstrap/cache/*.php

echo "Composer"
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# Migrations run before the build: the build boots the app (wayfinder:generate
# reads the route table), so the schema has to be current by then.
echo "Migrations..."
php artisan migrate --force

echo "Building assets"
# This is a pnpm project: pnpm-lock.yaml is the lockfile under version control
# and there is no package-lock.json, so `npm ci` here was installing from a
# stale, untracked lockfile left on the server and resolving versions nobody
# controls. --frozen-lockfile makes the install fail loudly if package.json and
# pnpm-lock.yaml ever drift apart, instead of silently resolving something new.
if ! command -v pnpm > /dev/null; then
    echo "pnpm is not installed on this host. Install it with: npm install -g pnpm@11" >&2
    exit 1
fi

pnpm install --frozen-lockfile
pnpm run build

echo "Optimizing..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Reload PHP-FPM..."
sudo systemctl reload php8.4-fpm

echo "Done!"
