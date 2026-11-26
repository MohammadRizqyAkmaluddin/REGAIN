FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    git curl unzip libzip-dev icu-dev gmp-dev openssl-dev oniguruma-dev \
    && docker-php-ext-install pdo_mysql bcmath gmp intl pcntl zip opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/backend

COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

COPY backend/ ./
RUN composer dump-autoload --optimize \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
