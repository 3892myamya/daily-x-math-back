FROM php:8.3-cli

# 必要な拡張
RUN apt-get update && apt-get install -y \
    git unzip zip libzip-dev \
    sqlite3 libsqlite3-dev \
    && docker-php-ext-install zip pdo pdo_sqlite

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# sqlite
RUN mkdir -p database && touch database/database.sqlite && chmod -R 777 database

WORKDIR /app
COPY . .

# Laravel の必要フォルダに書き込み権限を付与
RUN chmod -R 777 storage bootstrap/cache

# Composer install
RUN composer install --no-dev --optimize-autoloader

# CMD
CMD php artisan serve --host=0.0.0.0 --port=${PORT}
