# syntax=docker/dockerfile:1

# Stage 1: Get dependencies
FROM composer:2.10 AS dependencies
WORKDIR /deps
RUN --mount=type=bind,source=composer.json,target=composer.json \
    --mount=type=bind,source=composer.lock,target=composer.lock \
    --mount=type=cache,target=/tmp/cache \
    composer install --no-dev --no-interaction

# Stage 2: Build final image
FROM php:8.5.11-apache
# TODO: look into updating to more recent PHP
WORKDIR /var/www/html/

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY --from=dependencies /deps/vendor ./vendor
COPY ./src .
COPY ./config/logging.ini.template $PHP_INI_DIR/conf.d/logging.ini

RUN chown -R www-data:www-data .
USER www-data

EXPOSE 8080
CMD ["php", "-S", "0.0.0.0:8080"]