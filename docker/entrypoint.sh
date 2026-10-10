#!/bin/sh
set -e

echo "Esperando a que la base de datos PostgreSQL esté lista..."
until php -r "new PDO('pgsql:host=' . (getenv('DB_HOST') ?: 'db') . ';port=5432;dbname=' . (getenv('DB_DATABASE') ?: 'library'), getenv('DB_USERNAME') ?: 'library', getenv('DB_PASSWORD') ?: '');" >/dev/null 2>&1; do
  sleep 1
done
echo "Base de datos conectada correctamente."

php artisan config:clear >/dev/null
php artisan migrate --force
php artisan library:bootstrap-admin

exec php artisan serve --host=0.0.0.0 --port=8000
