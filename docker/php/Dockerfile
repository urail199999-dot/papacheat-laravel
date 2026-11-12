FROM php:8.4-fpm-alpine
RUN sed -i 's/https:/http:/g' /etc/apk/repositories \
    && apk add --no-cache libzip-dev oniguruma-dev $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" mbstring pdo_mysql zip \
    && apk del $PHPIZE_DEPS
WORKDIR /var/www/html
COPY . .
RUN chown -R www-data:www-data storage bootstrap/cache
CMD ["php-fpm"]
