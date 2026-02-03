#!/usr/bin/env bash

set -e

if [ ! -f app/.env ]; then
  echo "Creating .env from .env.example"
  cp app/.env.example app/.env
fi

echo "Starting containers..."
docker compose --env-file app/.env up -d --build

echo "Installing composer dependencies..."
docker compose exec app composer install

echo "Generating app key..."
docker compose exec app php artisan key:generate --force

echo "Running migrations..."
docker compose exec app php artisan migrate --force

echo "Creating storage link..."
docker compose exec app php artisan storage:link

echo "Done."
