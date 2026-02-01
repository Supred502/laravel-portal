if [ ! -f app/.env ]; then
  echo "Creating .env from .env.example"
  cp app/.env.example app/.env
fi

docker compose --env-file app/.env up -d --build

docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
