@echo off
setlocal

IF NOT EXIST app\.env (
  echo Creating .env from .env.example
  copy app\.env.example app\.env
)

echo Starting containers...
docker compose --env-file app\.env up -d --build
if errorlevel 1 goto :error

echo Installing composer dependencies...
docker compose exec app composer install
if errorlevel 1 goto :error

echo Installing npm dependencies...
docker compose exec app npm install
if errorlevel 1 goto :error

echo Building frontend assets...
docker compose exec app npm run build
if errorlevel 1 goto :error

echo Generating app key (if missing)...
docker compose exec app php artisan key:generate --force
if errorlevel 1 goto :error

echo Running migrations...
docker compose exec app php artisan migrate --force
if errorlevel 1 goto :error

echo Creating storage link...
docker compose exec app php artisan storage:link
if errorlevel 1 goto :error

echo Verifying Vite build output...
docker compose exec app sh -c "test -f public/build/manifest.json"
if errorlevel 1 goto :manifest_missing

echo Done.
pause
exit /b 0

:manifest_missing
echo.
echo Vite manifest missing. Frontend build did not produce public/build/manifest.json.
echo Check npm output above and rerun the setup script.
pause
exit /b 1

:error
echo.
echo Setup failed. See errors above.
pause
exit /b 1