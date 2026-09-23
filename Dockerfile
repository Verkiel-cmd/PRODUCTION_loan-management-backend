FROM php:8.5-cli
WORKDIR /var/www/html
RUN apt-get update && apt-get install -y unzip libzip-dev libonig-dev \
    && docker-php-ext-install pdo_mysql mbstring zip \
    && rm -rf /var/lib/apt/lists/* \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
COPY . .
RUN composer install --no-dev --optimize-autoloader
RUN chmod -R 775 storage bootstrap/cache
EXPOSE 10000
CMD ["sh", "-c", "php artisan migrate:fresh --seed --force && php artisan serve --host=0.0.0.0 --port=${PORT:-10000}"]