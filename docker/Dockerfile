# syntax=docker/dockerfile:1

# ---- Composer dependencies ----
FROM composer:2.8 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-interaction \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --ignore-platform-reqs

COPY . .

RUN composer dump-autoload --optimize --classmap-authoritative


# ---- PHP-FPM runtime ----
FROM php:8.4-fpm-alpine AS runtime

RUN apk add --no-cache \
        icu-dev \
        libzip-dev \
        postgresql-dev \
        linux-headers \
        bash \
        git \
        unzip \
        $PHPIZE_DEPS \
    && docker-php-ext-configure intl \
    && docker-php-ext-install \
        bcmath \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS linux-headers \
    && rm -rf /tmp/pear /var/cache/apk/*

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/10-opcache.ini

WORKDIR /var/www/html

COPY --from=vendor /app/vendor ./vendor
COPY . .

RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY docker/app/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/scheduler/entrypoint.sh /var/www/html/docker/scheduler/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh /var/www/html/docker/scheduler/entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
