@echo off

IF NOT EXIST app\.env (
  echo Creating .env from .env.example
  copy app\.env.example app\.env
)

echo Starting containers...
docker compose --env-file app\.env up -d --build

echo Installing composer dependencies...
docker compose exec app composer install

echo Installing npm dependencies...
docker compose exec app npm install

echo Building frontend assets...
docker compose exec app npm run build

echo Generating app key (if missing)...
docker compose exec app php artisan key:generate --force

echo Running migrations...
docker compose exec app php artisan migrate --force

echo Creating storage link...
docker compose exec app php artisan storage:link

echo Done.
pause