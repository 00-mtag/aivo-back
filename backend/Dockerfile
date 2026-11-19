FROM node:22-alpine AS frontend-assets

WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libcurl4-openssl-dev \
        libonig-dev \
        libxml2-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        curl \
        mbstring \
        pdo_mysql \
        xml \
        zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html
COPY . .

RUN composer install \
        --no-dev \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader \
    && mkdir -p \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
    && php artisan storage:link \
    && chown -R www-data:www-data storage bootstrap/cache

COPY --from=frontend-assets /app/public/build ./public/build

RUN sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf \
    && printf '%s\n' \
        '<Directory /var/www/html/public>' \
        '    AllowOverride All' \
        '    Require all granted' \
        '</Directory>' \
        >> /etc/apache2/apache2.conf

EXPOSE 10000

CMD ["sh", "-c", "php artisan migrate --force && php artisan db:seed --class=ExerciseSeeder --force && port=\"${PORT:-10000}\" && sed -i \"s/Listen 80/Listen ${port}/\" /etc/apache2/ports.conf && sed -i \"s/:80>/:${port}>/\" /etc/apache2/sites-available/000-default.conf && exec apache2-foreground"]