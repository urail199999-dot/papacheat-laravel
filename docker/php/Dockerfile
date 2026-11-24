FROM php:8.4-fpm-alpine
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN apk add --no-cache ca-certificates icu-dev libzip-dev oniguruma-dev $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" mbstring pdo_mysql zip intl \
    && apk del $PHPIZE_DEPS
WORKDIR /var/www/html
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini
CMD ["php-fpm"]
