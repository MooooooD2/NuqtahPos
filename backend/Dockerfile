FROM php:8.2-fpm-alpine AS base

RUN apk add --no-cache \
    nginx \
    supervisor \
    mysql-client \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    nodejs \
    npm

RUN docker-php-ext-install pdo_mysql bcmath gd zip opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.* ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .

RUN composer dump-autoload --optimize \
    && chown -R www-data:www-data storage bootstrap/cache \
    && php artisan storage:link

COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf

EXPOSE 80
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
