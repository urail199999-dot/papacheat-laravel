FROM php:8.4-fpm-alpine
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN sed -i 's/https:/http:/g' /etc/apk/repositories \
    && apk add --no-cache ca-certificates libzip-dev oniguruma-dev $PHPIZE_DEPS \
    && update-ca-certificates \
    && docker-php-ext-install -j"$(nproc)" mbstring pdo_mysql zip \
    && apk del $PHPIZE_DEPS
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-dev --no-scripts
COPY . .
RUN composer dump-autoload --optimize
RUN chown -R www-data:www-data storage bootstrap/cache
CMD ["php-fpm"]
