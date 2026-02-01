:: For .bat (Windows)
docker compose --env-file .\app\.env up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
pause
