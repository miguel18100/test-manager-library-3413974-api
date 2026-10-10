FROM php:8.4-cli

RUN apt-get update \
 && apt-get install -y --no-install-recommends libpq-dev libzip-dev unzip git \
 && docker-php-ext-install pdo_pgsql zip \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Capa de dependencias (se cachea mientras composer.json no cambie)
COPY composer.json composer.lock* ./
RUN composer install --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
RUN sed -i 's/\r$//' docker/entrypoint.sh \
 && composer dump-autoload --optimize \
 && mkdir -p storage/app/covers storage/framework/cache storage/framework/views storage/framework/sessions storage/logs bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache

ENV TZ=UTC
EXPOSE 8000
CMD ["sh", "docker/entrypoint.sh"]
